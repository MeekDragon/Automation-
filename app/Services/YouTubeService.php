<?php
// app/Services/YouTubeService.php

namespace App\Services;

use App\Models\Account;
use App\Models\JobLog;
use Google\Client;
use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;

class YouTubeService
{
    public static function upload($jobId, Account $account, $filePath, $metadata, $type = 'youtube_video', $options = []): string
    {
        $platformName = $type;

        if ($type === 'youtube_post') {
            throw new \Exception('YouTube API v3 does not support publishing Community Posts (text/image updates) via third-party application credentials. To post live community posts, please use the YouTube web interface directly.');
        }

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => 'Initializing YouTube Data API client...',
            'type' => 'info'
        ]);

        $client = new Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        
        $client->setAccessToken([
            'access_token' => $account->access_token,
            'refresh_token' => $account->refresh_token,
        ]);

        if ($client->isAccessTokenExpired()) {
            if ($account->refresh_token) {
                $tokens = $client->fetchAccessTokenWithRefreshToken($account->refresh_token);
                if (isset($tokens['error'])) {
                    throw new \Exception('Failed to refresh YouTube access token: ' . ($tokens['error_description'] ?? $tokens['error']));
                }
                $account->update([
                    'access_token' => $tokens['access_token'],
                    'expires_at' => isset($tokens['expires_in']) ? now()->addSeconds($tokens['expires_in']) : null,
                ]);
                $client->setAccessToken($tokens);
            } else {
                throw new \Exception('YouTube access token is expired and no refresh token is available.');
            }
        }

        $youtube = new YouTube($client);

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => $platformName,
            'message' => 'Uploading video stream directly to YouTube...',
            'type' => 'info'
        ]);

        $privacyStatus = $options['youtubePrivacy'] ?? 'public';
        $categoryId = $options['youtubeCategory'] ?? '22';

        $video = new Video();
        
        $snippet = new VideoSnippet();
        $snippet->setTitle($metadata['title']);
        $description = $metadata['description'] . ($type === 'youtube_shorts' ? ' #Shorts' : '');
        $snippet->setDescription($description);
        $snippet->setCategoryId($categoryId);
        if (!empty($options['tags'])) {
            $snippet->setTags($options['tags']);
        }
        $video->setSnippet($snippet);

        $status = new VideoStatus();
        $status->setPrivacyStatus($privacyStatus);
        $status->setSelfDeclaredMadeForKids(false);
        $video->setStatus($status);

        $chunkSizeBytes = 1 * 1024 * 1024; // 1MB chunk size
        $client->setDefer(true);
        $insertRequest = $youtube->videos->insert('snippet,status', $video);
        
        $media = new \Google\Http\MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            $chunkSizeBytes
        );
        
        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            throw new \Exception('Media file is empty.');
        }
        $media->setFileSize($fileSize);

        $statusResult = false;
        $handle = fopen($filePath, "rb");
        $bytesRead = 0;

        while (!$statusResult && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $bytesRead += strlen($chunk);
            $statusResult = $media->nextChunk($chunk);
            
            $progress = round(($bytesRead / $fileSize) * 100);
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => $platformName,
                'message' => "Uploading progress: {$progress}%",
                'type' => 'info'
            ]);
        }
        fclose($handle);
        $client->setDefer(false);

        if ($statusResult && $statusResult->getId()) {
            $videoId = $statusResult->getId();
            $videoUrl = $type === 'youtube_shorts' 
                ? "https://youtube.com/shorts/{$videoId}" 
                : "https://www.youtube.com/watch?v={$videoId}";

            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => $platformName,
                'message' => "Video successfully posted! Video URL: {$videoUrl}",
                'type' => 'success'
            ]);

            return $videoUrl;
        }

        throw new \Exception('Failed to upload video to YouTube.');
    }
}
