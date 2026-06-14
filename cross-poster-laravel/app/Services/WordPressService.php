<?php
// app/Services/WordPressService.php

namespace App\Services;

use App\Models\Account;
use App\Models\JobLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressService
{
    public static function upload($jobId, Account $account, $filePath, $metadata, $options = []): string
    {
        $platformName = 'wordpress';

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => 'Connecting to WordPress REST API...',
            'type' => 'info'
        ]);

        // Decrypt the credentials stored in access_token
        $creds = json_decode($account->access_token, true);
        if (!$creds || !isset($creds['url']) || !isset($creds['username']) || !isset($creds['appPassword'])) {
            throw new \Exception('WordPress credentials are missing or corrupted.');
        }

        $wpUrl = $creds['url'];
        $username = $creds['username'];
        $appPassword = $creds['appPassword'];

        // 1. Upload Media to WordPress library
        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => 'Uploading media file to WordPress library...',
            'type' => 'info'
        ]);

        $mediaResponse = Http::withBasicAuth($username, $appPassword)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post("{$wpUrl}/wp-json/wp/v2/media");

        if ($mediaResponse->failed()) {
            $msg = $mediaResponse->json()['message'] ?? 'Media upload failed';
            throw new \Exception("WordPress Media Upload Failed: {$msg}");
        }

        $mediaId = $mediaResponse->json()['id'];
        $mediaSourceUrl = $mediaResponse->json()['source_url'];
        $mimeType = $mediaResponse->json()['mime_type'] ?? '';
        $isVideo = str_starts_with($mimeType, 'video/');

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => "Media uploaded successfully. Media ID: {$mediaId}",
            'type' => 'info'
        ]);

        // 2. Create Blog Post
        $wpPostStatus = $options['wordpressStatus'] ?? 'publish';
        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => "Creating new blog post (status: {$wpPostStatus})...",
            'type' => 'info'
        ]);

        $description = $metadata['description'] ?? '';

        if ($isVideo) {
            $postContent = "
              <figure class=\"wp-block-video\"><video controls src=\"{$mediaSourceUrl}\"></video></figure>
              <p>" . nl2br(e($description)) . "</p>
            ";
        } else {
            $postContent = "
              <figure class=\"wp-block-image\"><img src=\"{$mediaSourceUrl}\" alt=\"" . e($metadata['title']) . "\"/></figure>
              <p>" . nl2br(e($description)) . "</p>
            ";
        }

        $postResponse = Http::withBasicAuth($username, $appPassword)
            ->post("{$wpUrl}/wp-json/wp/v2/posts", [
                'title' => $metadata['title'],
                'content' => $postContent,
                'status' => $wpPostStatus,
                'featured_media' => $isVideo ? 0 : $mediaId
            ]);

        if ($postResponse->failed()) {
            $msg = $postResponse->json()['message'] ?? 'Post creation failed';
            throw new \Exception("WordPress Post Creation Failed: {$msg}");
        }

        $postUrl = $postResponse->json()['link'];

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => "Blog post published successfully! Link: {$postUrl}",
            'type' => 'success'
        ]);

        return $postUrl;
    }
}
