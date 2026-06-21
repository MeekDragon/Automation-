<?php
// app/Services/InstagramService.php

namespace App\Services;

use App\Models\Account;
use App\Models\JobLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InstagramService
{
    private static function getPublicUrlForFile($jobId, $platformName, $filePath): string
    {
        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => 'Uploading media to a temporary public URL for platform retrieval...',
            'type' => 'info'
        ]);

        try {
            $response = Http::attach(
                'file', file_get_contents($filePath), basename($filePath)
            )->post('https://tmpfiles.org/api/v1/upload');

            if ($response->successful() && $response->json()['status'] === 'success') {
                $uploadUrl = $response->json()['data']['url'];
                $directUrl = str_replace('https://tmpfiles.org/', 'https://tmpfiles.org/dl/', $uploadUrl);
                
                JobLog::create([
                    'job_id' => $jobId,
                    'platform_key' => $platformName,
                    'message' => "Temporary direct media URL generated: {$directUrl}",
                    'type' => 'info'
                ]);

                return $directUrl;
            } else {
                throw new \Exception('Failed to upload file to tmpfiles.org');
            }
        } catch (\Exception $e) {
            Log::error('Instagram Temp Upload Error: ' . $e->getMessage());
            throw new \Exception("Failed to provision temporary public URL for media: " . $e->getMessage());
        }
    }

    public static function upload($jobId, Account $account, $filePath, $caption, $type = 'instagram_post', $options = []): string
    {
        $platformName = $type;
        $igAccountId = $account->platform_id;
        $accessToken = $account->access_token;

        if ($accessToken === 'mock-instagram-token' || $igAccountId === 'instagram-mock-id') {
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => $platformName,
                'message' => 'Uploading media to a temporary public URL for platform retrieval...',
                'type' => 'info'
            ]);
            sleep(1);
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => $platformName,
                'message' => 'Creating Instagram media container (' . strtoupper(str_replace('instagram_', '', $type)) . ')...',
                'type' => 'info'
            ]);
            sleep(1);
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => $platformName,
                'message' => 'Processing complete. Publishing post now...',
                'type' => 'info'
            ]);
            sleep(1);
            $postUrl = 'https://www.instagram.com/omyadav_16';
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => $platformName,
                'message' => "Post published successfully! Link: {$postUrl}",
                'type' => 'success'
            ]);
            return $postUrl;
        }

        // 1. Host file temporarily
        $publicMediaUrl = self::getPublicUrlForFile($jobId, $platformName, $filePath);

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => "Creating Instagram media container (" . strtoupper(str_replace('instagram_', '', $type)) . ")...",
            'type' => 'info'
        ]);

        // 2. Initialize Media Container on Meta Graph API
        $containerParams = [
            'access_token' => $accessToken
        ];

        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $isVideo = in_array($ext, ['mp4', 'mov', 'avi']);

        if ($type === 'instagram_story') {
            $containerParams['media_type'] = 'STORIES';
            if ($isVideo) {
                $containerParams['video_url'] = $publicMediaUrl;
            } else {
                $containerParams['image_url'] = $publicMediaUrl;
            }
        } elseif ($type === 'instagram_reel') {
            $containerParams['media_type'] = 'REELS';
            $containerParams['video_url'] = $publicMediaUrl;
            $containerParams['share_to_feed'] = true;
            $containerParams['caption'] = $caption;
        } else {
            // instagram_post
            $containerParams['caption'] = $caption;
            if ($isVideo) {
                $containerParams['media_type'] = 'VIDEO';
                $containerParams['video_url'] = $publicMediaUrl;
            } else {
                $containerParams['image_url'] = $publicMediaUrl;
            }
        }

        $containerRes = Http::post("https://graph.facebook.com/v19.0/{$igAccountId}/media", $containerParams);

        if ($containerRes->failed()) {
            throw new \Exception($containerRes->json()['error']['message'] ?? 'Failed to initialize container');
        }

        $containerId = $containerRes->json()['id'];

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => "Container initialized. ID: {$containerId}. Waiting for Instagram to process...",
            'type' => 'info'
        ]);

        // 3. Poll Container Status
        $status = 'IN_PROGRESS';
        $maxPolls = 15;
        $attempts = 0;

        while ($status !== 'FINISHED' && $attempts < $maxPolls) {
            $attempts++;
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => $platformName,
                'message' => "Polling processing status (Attempt {$attempts}/{$maxPolls})...",
                'type' => 'info'
            ]);

            sleep(10);

            $statusRes = Http::get("https://graph.facebook.com/v19.0/{$containerId}", [
                'fields' => 'status_code,failure_reason',
                'access_token' => $accessToken
            ]);

            if ($statusRes->successful()) {
                $status = $statusRes->json()['status_code'] ?? 'IN_PROGRESS';

                if ($status === 'FINISHED') {
                    break;
                } elseif ($status === 'ERROR' || $status === 'EXPIRED') {
                    $reason = $statusRes->json()['failure_reason'] ?? 'Unknown processing error';
                    throw new \Exception("Instagram media processing failed: {$reason}");
                }
            }
        }

        if ($status !== 'FINISHED') {
            throw new \Exception('Instagram media processing timed out.');
        }

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => 'Processing complete. Publishing post now...',
            'type' => 'info'
        ]);

        // 4. Publish the media container
        $publishRes = Http::post("https://graph.facebook.com/v19.0/{$igAccountId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $accessToken
        ]);

        if ($publishRes->failed()) {
            throw new \Exception($publishRes->json()['error']['message'] ?? 'Failed to publish post');
        }

        $postId = $publishRes->json()['id'];

        // 5. Query public permalink for the published post
        $postUrl = 'https://instagram.com';
        try {
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => $platformName,
                'message' => 'Fetching public permalink from Instagram...',
                'type' => 'info'
            ]);

            $permalinkRes = Http::get("https://graph.facebook.com/v19.0/{$postId}", [
                'fields' => 'permalink',
                'access_token' => $accessToken
            ]);

            if ($permalinkRes->successful() && isset($permalinkRes->json()['permalink'])) {
                $postUrl = $permalinkRes->json()['permalink'];
            }
        } catch (\Exception $permalinkErr) {
            Log::warning('Failed to retrieve Instagram permalink, falling back to general profile link: ' . $permalinkErr->getMessage());
        }

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => "Post published successfully! Link: {$postUrl}",
            'type' => 'success'
        ]);

        return $postUrl;
    }
}
