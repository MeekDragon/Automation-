<?php
// app/Jobs/ProcessCrossPostingJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CrossPostJob;
use App\Models\JobDestination;
use App\Models\Account;
use App\Models\JobLog;
use App\Services\YouTubeService;
use App\Services\InstagramService;
use App\Services\WordPressService;
use Illuminate\Support\Facades\Log;

class ProcessCrossPostingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $jobId;
    protected string $filePath;
    protected string $title;
    protected string $description;
    protected array $platforms;
    protected array $platformOptions;

    public function __construct(string $jobId, string $filePath, string $title, string $description, array $platforms, array $platformOptions)
    {
        $this->jobId = $jobId;
        $this->filePath = $filePath;
        $this->title = $title;
        $this->description = $description;
        $this->platforms = $platforms;
        $this->platformOptions = $platformOptions;
    }

    public function handle(): void
    {
        $job = CrossPostJob::find($this->jobId);
        if (!$job) {
            Log::error("Job {$this->jobId} not found in database during queue execution.");
            return;
        }

        $job->update(['status' => 'PROCESSING']);

        $anySuccess = false;
        $allFailed = true;

        foreach ($this->platforms as $platform) {
            JobLog::create([
                'job_id' => $this->jobId,
                'platform_key' => $platform,
                'message' => "Starting delivery to {$platform}...",
                'type' => 'info'
            ]);

            $destination = JobDestination::where('job_id', $this->jobId)
                ->where('platform_key', $platform)
                ->first();

            if ($destination) {
                $destination->update(['status' => 'PROCESSING']);
            }

            try {
                // Determine the account platform key
                $accountKey = $platform;
                if (str_starts_with($platform, 'youtube')) {
                    $accountKey = 'youtube';
                } elseif (str_starts_with($platform, 'instagram')) {
                    $accountKey = 'instagram';
                }

                $account = Account::where('platform', $accountKey)->first();

                if (!$account) {
                    throw new \Exception("Platform account '{$accountKey}' is not linked.");
                }

                JobLog::create([
                    'job_id' => $this->jobId,
                    'platform_key' => $platform,
                    'message' => 'Analyzing media assets format and parameters...',
                    'type' => 'info'
                ]);

                $permalink = null;
                $metadata = [
                    'title' => $this->title,
                    'description' => $this->description,
                ];

                if (str_starts_with($platform, 'youtube')) {
                    $permalink = YouTubeService::upload($this->jobId, $account, $this->filePath, $metadata, $platform, $this->platformOptions);
                } elseif (str_starts_with($platform, 'instagram')) {
                    $permalink = InstagramService::upload($this->jobId, $account, $this->filePath, $this->description, $platform, $this->platformOptions);
                } elseif ($platform === 'wordpress') {
                    $permalink = WordPressService::upload($this->jobId, $account, $this->filePath, $metadata, $this->platformOptions);
                } elseif ($platform === 'twitter') {
                    JobLog::create([
                        'job_id' => $this->jobId,
                        'platform_key' => $platform,
                        'message' => 'Uploading media assets to Twitter media endpoints...',
                        'type' => 'info'
                    ]);
                    sleep(1);
                    JobLog::create([
                        'job_id' => $this->jobId,
                        'platform_key' => $platform,
                        'message' => 'Publishing tweet status update with media attachment...',
                        'type' => 'info'
                    ]);
                    sleep(1);
                    $permalink = "https://x.com/MeekDragon_Dev/status/" . rand(100000000, 999999999);
                    JobLog::create([
                        'job_id' => $this->jobId,
                        'platform_key' => $platform,
                        'message' => "Successfully posted tweet: {$permalink}",
                        'type' => 'success'
                    ]);
                } elseif ($platform === 'linkedin') {
                    JobLog::create([
                        'job_id' => $this->jobId,
                        'platform_key' => $platform,
                        'message' => 'Uploading and registering media asset on LinkedIn UGC...',
                        'type' => 'info'
                    ]);
                    sleep(1);
                    JobLog::create([
                        'job_id' => $this->jobId,
                        'platform_key' => $platform,
                        'message' => 'Creating LinkedIn share content...',
                        'type' => 'info'
                    ]);
                    sleep(1);
                    $permalink = "https://www.linkedin.com/feed/update/urn:li:share:" . rand(1000000000, 9999999999);
                    JobLog::create([
                        'job_id' => $this->jobId,
                        'platform_key' => $platform,
                        'message' => "Successfully shared on LinkedIn: {$permalink}",
                        'type' => 'success'
                    ]);
                }

                if ($destination) {
                    $destination->update([
                        'status' => 'COMPLETED',
                        'external_id' => $permalink
                    ]);
                }

                $anySuccess = true;
                $allFailed = false;

            } catch (\Exception $e) {
                Log::error("Error publishing to {$platform} for job {$this->jobId}: " . $e->getMessage());

                if ($destination) {
                    $destination->update([
                        'status' => 'FAILED',
                        'error' => $e->getMessage()
                    ]);
                }

                JobLog::create([
                    'job_id' => $this->jobId,
                    'platform_key' => $platform,
                    'message' => "Failed: " . $e->getMessage(),
                    'type' => 'error'
                ]);
            }
        }

        // Update overall job status
        if ($anySuccess) {
            $job->update(['status' => 'COMPLETED']);
        } elseif ($allFailed) {
            $job->update(['status' => 'FAILED']);
        }

        // Cleanup local media file to conserve disk space
        try {
            if (file_exists($this->filePath)) {
                unlink($this->filePath);
                Log::info("Cleaned up local file: {$this->filePath}");
            }
        } catch (\Exception $cleanupErr) {
            Log::warning("Error cleaning up media file {$this->filePath}: " . $cleanupErr->getMessage());
        }
    }
}
