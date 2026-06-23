<?php
// app/Services/TwitterService.php

namespace App\Services;

use App\Models\Account;
use App\Models\JobLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TwitterService
{
    private static function refreshAccessToken(Account $account): string
    {
        $clientId = env('TWITTER_CLIENT_ID');
        $clientSecret = env('TWITTER_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            throw new \Exception('Twitter API credentials (TWITTER_CLIENT_ID / TWITTER_CLIENT_SECRET) not set in .env.');
        }

        $response = Http::asForm()->withBasicAuth($clientId, $clientSecret)
            ->post('https://api.twitter.com/2/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);

        if ($response->failed()) {
            throw new \Exception('Failed to refresh Twitter access token: ' . ($response->json()['error_description'] ?? $response->body()));
        }

        $data = $response->json();
        $account->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'expires_at' => isset($data['expires_in']) ? now()->addSeconds($data['expires_in']) : null,
        ]);

        return $data['access_token'];
    }

    public static function upload($jobId, Account $account, $filePath, $text): string
    {
        $accessToken = $account->access_token;
        
        // Handle token refresh
        if ($account->expires_at && now()->greaterThan($account->expires_at)) {
            if ($account->refresh_token) {
                $accessToken = self::refreshAccessToken($account);
            }
        }

        $mediaId = null;
        if ($filePath && file_exists($filePath)) {
            JobLog::create([
                'job_id' => $jobId,
                'platform_key' => 'twitter',
                'message' => 'Uploading media to Twitter...',
                'type' => 'info'
            ]);

            // Upload media using Twitter's upload endpoint
            $uploadRes = Http::withToken($accessToken)
                ->attach('media', file_get_contents($filePath), basename($filePath))
                ->post('https://upload.twitter.com/1.1/media/upload.json');

            if ($uploadRes->successful()) {
                $mediaId = $uploadRes->json()['media_id_string'] ?? null;
                JobLog::create([
                    'job_id' => $jobId,
                    'platform_key' => 'twitter',
                    'message' => "Media uploaded successfully. Media ID: {$mediaId}",
                    'type' => 'info'
                ]);
            } else {
                Log::warning('Twitter media upload failed: ' . $uploadRes->body());
                JobLog::create([
                    'job_id' => $jobId,
                    'platform_key' => 'twitter',
                    'message' => 'Media upload failed (falling back to text-only tweet). Error: ' . ($uploadRes->json()['errors'][0]['message'] ?? $uploadRes->body()),
                    'type' => 'warning'
                ]);
            }
        }

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => 'twitter',
            'message' => 'Publishing tweet...',
            'type' => 'info'
        ]);

        $payload = ['text' => $text];
        if ($mediaId) {
            $payload['media'] = [
                'media_ids' => [$mediaId]
            ];
        }

        $res = Http::withToken($accessToken)
            ->post('https://api.twitter.com/2/tweets', $payload);

        if ($res->failed()) {
            throw new \Exception('Failed to post tweet: ' . ($res->json()['detail'] ?? $res->body()));
        }

        $tweetId = $res->json()['data']['id'] ?? null;
        
        // Extract Twitter handle
        $username = str_replace('@', '', $account->account_name);
        if (!$username || $username === 'twitter-mock-id') {
            $username = 'OmY1606';
        }

        $permalink = "https://x.com/{$username}/status/{$tweetId}";
        
        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => 'twitter',
            'message' => "Successfully posted tweet: {$permalink}",
            'type' => 'success'
        ]);

        return $permalink;
    }
}
