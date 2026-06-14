<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Models\CrossPostJob;
use App\Models\JobLog;
use App\Jobs\ProcessCrossPostingJob;
use Illuminate\Support\Facades\Log;

Schedule::call(function () {
    $now = now();
    $scheduledJobs = CrossPostJob::where('status', 'SCHEDULED')
        ->whereNotNull('scheduled_at')
        ->where('scheduled_at', '<=', $now)
        ->get();

    foreach ($scheduledJobs as $job) {
        Log::info("Scheduler triggering job {$job->id} (\"{$job->title}\")");
        
        JobLog::create([
            'job_id' => $job->id,
            'platform_key' => 'system',
            'message' => 'Scheduled posting time reached. Initiating background delivery...',
            'type' => 'info'
        ]);

        $job->update(['status' => 'PENDING']);
        $job->destinations()->where('status', 'SCHEDULED')->update(['status' => 'PENDING']);

        $platforms = $job->destinations->pluck('platform_key')->toArray();
        $platformOptions = $job->platform_options ?? [];

        ProcessCrossPostingJob::dispatch(
            $job->id,
            $job->media_path,
            $job->title,
            $job->description ?? '',
            $platforms,
            $platformOptions
        );
    }
})->everyMinute();
