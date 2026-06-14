<?php
// app/Http/Controllers/JobController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CrossPostJob;
use App\Models\JobDestination;
use App\Models\JobLog;
use App\Jobs\ProcessCrossPostingJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class JobController extends Controller
{
    private function transformJob(CrossPostJob $job): array
    {
        $destinations = [];
        foreach ($job->destinations as $d) {
            $destinations[$d->platform_key] = [
                'status' => $d->status,
                'error' => $d->error,
                'externalId' => $d->external_id
            ];
        }

        return [
            'id' => $job->id,
            'title' => $job->title,
            'description' => $job->description,
            'mediaPath' => $job->media_path,
            'status' => $job->status,
            'scheduledAt' => $job->scheduled_at ? $job->scheduled_at->toIso8601String() : null,
            'createdBy' => $job->created_by,
            'createdAt' => $job->created_at->toIso8601String(),
            'destinations' => $destinations
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = CrossPostJob::with('destinations')->orderBy('created_at', 'desc');

        if ($user->role === 'user') {
            $query->where('created_by', $user->username);
        }

        $jobs = $query->get()->map(function ($job) {
            return $this->transformJob($job);
        });

        return response()->json($jobs);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'destinations' => ['required', 'array'],
            'destinations.*' => ['required', 'string'],
            'media' => ['required', 'file'],
            'scheduledAt' => ['nullable', 'string'],
            'youtubePrivacy' => ['nullable', 'string'],
            'youtubeCategory' => ['nullable', 'string'],
            'wordpressStatus' => ['nullable', 'string'],
        ]);

        $user = Auth::user();

        // Save uploaded media file
        $file = $request->file('media');
        $fileName = time() . '-' . rand(100000000, 999999999) . '.' . $file->getClientOriginalExtension();
        
        // Save to public uploads
        $file->move(public_path('uploads'), $fileName);
        $mediaPath = public_path('uploads/' . $fileName);

        $jobId = 'job_' . round(microtime(true) * 1000);
        $scheduledAt = $request->input('scheduledAt') ? new \DateTime($request->input('scheduledAt')) : null;

        $status = $scheduledAt ? 'SCHEDULED' : 'PENDING';

        $platformOptions = [
            'youtubePrivacy' => $request->input('youtubePrivacy') ?? 'public',
            'youtubeCategory' => $request->input('youtubeCategory') ?? '22',
            'wordpressStatus' => $request->input('wordpressStatus') ?? 'publish',
            'tags' => $request->input('tags') ? array_map('trim', explode(',', $request->input('tags'))) : []
        ];

        // Create Job
        $job = CrossPostJob::create([
            'id' => $jobId,
            'title' => $request->input('title'),
            'description' => $request->input('description') ?? '',
            'media_path' => $mediaPath,
            'status' => $status,
            'platform_options' => $platformOptions,
            'scheduled_at' => $scheduledAt,
            'created_by' => $user->username,
        ]);

        $platforms = $request->input('destinations');
        foreach ($platforms as $platform) {
            JobDestination::create([
                'job_id' => $jobId,
                'platform_key' => $platform,
                'status' => $scheduledAt ? 'SCHEDULED' : 'PENDING',
            ]);
        }

        // Create log entry
        if ($scheduledAt) {
            $dateStr = $scheduledAt->format('Y-m-d H:i:s');
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => 'system',
                'message' => "Post successfully scheduled for execution at: {$dateStr}",
                'type' => 'info'
            ]);
        } else {
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => 'system',
                'message' => 'Publishing job created. Delivery in progress...',
                'type' => 'info'
            ]);

            // Dispatch immediately to background queue
            ProcessCrossPostingJob::dispatch($jobId, $mediaPath, $request->input('title'), $request->input('description') ?? '', $platforms, $platformOptions);
        }

        return response()->json([
            'success' => true,
            'jobId' => $jobId,
            'message' => 'Publishing job enqueued in background'
        ]);
    }

    public function logs(Request $request, string $id = null): JsonResponse
    {
        $user = Auth::user();
        $jobId = $id ?? $request->query('jobId');

        if (!$jobId) {
            return response()->json(['error' => 'Job ID required'], 400);
        }

        $job = CrossPostJob::find($jobId);

        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        if ($user->role === 'user' && $job->created_by !== $user->username) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $logs = JobLog::where('job_id', $jobId)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($log) {
                return [
                    'timestamp' => $log->created_at->toIso8601String(),
                    'platform' => $log->platform_key,
                    'message' => $log->message,
                    'type' => $log->type
                ];
            });

        return response()->json($logs);
    }

    public function telemetry(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = CrossPostJob::query();

        if ($user->role === 'user') {
            $query->where('created_by', $user->username);
        }

        $allJobs = $query->with('destinations')->get();

        $total = 0;
        $completed = 0;
        $failed = 0;
        $pending = 0;

        $ytSuccess = 0;
        $ytTotal = 0;
        $igSuccess = 0;
        $igTotal = 0;
        $wpSuccess = 0;
        $wpTotal = 0;

        foreach ($allJobs as $job) {
            foreach ($job->destinations as $d) {
                $total++;
                if ($d->status === 'COMPLETED') {
                    $completed++;
                } elseif ($d->status === 'FAILED') {
                    $failed++;
                } else {
                    $pending++;
                }

                $pk = $d->platform_key;
                if (str_starts_with($pk, 'youtube')) {
                    $ytTotal++;
                    if ($d->status === 'COMPLETED') $ytSuccess++;
                } elseif (str_starts_with($pk, 'instagram')) {
                    $igTotal++;
                    if ($d->status === 'COMPLETED') $igSuccess++;
                } elseif ($pk === 'wordpress') {
                    $wpTotal++;
                    if ($d->status === 'COMPLETED') $wpSuccess++;
                }
            }
        }

        return response()->json([
            'jobsProcessed' => $total,
            'successRatio' => $total > 0 ? round(($completed / $total) * 100) : 0,
            'statusCounts' => [
                'completed' => $completed,
                'failed' => $failed,
                'pending' => $pending
            ],
            'platforms' => [
                'youtube' => [
                    'posts' => $ytTotal,
                    'pct' => $total > 0 ? round(($ytTotal / $total) * 100) : 0,
                    'successRate' => $ytTotal > 0 ? round(($ytSuccess / $ytTotal) * 100) : 0
                ],
                'instagram' => [
                    'posts' => $igTotal,
                    'pct' => $total > 0 ? round(($igTotal / $total) * 100) : 0,
                    'successRate' => $igTotal > 0 ? round(($igSuccess / $igTotal) * 100) : 0
                ],
                'wordpress' => [
                    'posts' => $wpTotal,
                    'pct' => $total > 0 ? round(($wpTotal / $total) * 100) : 0,
                    'successRate' => $wpTotal > 0 ? round(($wpSuccess / $wpTotal) * 100) : 0
                ]
            ]
        ]);
    }
}
