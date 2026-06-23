<?php
// app/Services/LinkedInService.php

namespace App\Services;

use App\Models\Account;
use App\Models\JobLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInService
{
    public static function upload($jobId, Account $account, $filePath, $text): string
    {
        $accessToken = $account->access_token;
        $authorUrn = str_starts_with($account->platform_id, 'urn:li:') 
            ? $account->platform_id 
            : "urn:li:person:{$account->platform_id}";

        // If platform_id is mock, use a placeholder
        if ($account->platform_id === 'linkedin-mock-id') {
            $authorUrn = 'urn:li:person:omyadav16'; 
        }

        $mediaUrn = null;

        // Try to upload image if it's an image file
        if ($filePath && file_exists($filePath)) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);

            if ($isImage) {
                try {
                    JobLog::create([
                        'job_id' => $jobId,
                        'platform_key' => 'linkedin',
                        'message' => 'Initializing LinkedIn image upload...',
                        'type' => 'info'
                    ]);

                    // 1. Initialize Upload
                    $initRes = Http::withHeaders([
                        'Authorization' => "Bearer {$accessToken}",
                        'LinkedIn-Version' => '202306',
                        'X-Restli-Protocol-Version' => '2.0.0',
                        'Content-Type' => 'application/json'
                    ])->post('https://api.linkedin.com/rest/images?action=initializeUpload', [
                        'initializeUploadRequest' => [
                            'owner' => $authorUrn
                        ]
                    ]);

                    if ($initRes->successful()) {
                        $uploadUrl = $initRes->json()['value']['uploadUrl'] ?? null;
                        $mediaUrn = $initRes->json()['value']['image'] ?? null;

                        if ($uploadUrl && $mediaUrn) {
                            JobLog::create([
                                'job_id' => $jobId,
                                'platform_key' => 'linkedin',
                                'message' => 'Uploading image binary to LinkedIn...',
                                'type' => 'info'
                            ]);

                            // 2. Upload binary
                            $uploadRes = Http::withBody(file_get_contents($filePath), 'image/jpeg')
                                ->put($uploadUrl);

                            if (!$uploadRes->successful()) {
                                $mediaUrn = null;
                                throw new \Exception('Failed to upload image binary to LinkedIn: ' . $uploadRes->body());
                            }

                            JobLog::create([
                                'job_id' => $jobId,
                                'platform_key' => 'linkedin',
                                'message' => "Image successfully uploaded. Media URN: {$mediaUrn}",
                                'type' => 'info'
                            ]);
                        }
                    } else {
                        throw new \Exception('Failed to initialize image upload: ' . $initRes->body());
                    }
                } catch (\Exception $uploadErr) {
                    Log::warning('LinkedIn media upload failed: ' . $uploadErr->getMessage());
                    JobLog::create([
                        'job_id' => $jobId,
                        'platform_key' => 'linkedin',
                        'message' => 'Image upload failed. Falling back to text-only post. Details: ' . $uploadErr->getMessage(),
                        'type' => 'warning'
                    ]);
                }
            } else {
                JobLog::create([
                    'job_id' => $jobId,
                    'platform_key' => 'linkedin',
                    'message' => 'Videos/Shorts require chunked enterprise permissions. Falling back to text-only post.',
                    'type' => 'info'
                ]);
            }
        }

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => 'linkedin',
            'message' => 'Creating LinkedIn post...',
            'type' => 'info'
        ]);

        // Construct payload using standard LinkedIn Versioned Shares format
        $payload = [
            'author' => $authorUrn,
            'commentary' => $text,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED'
            ],
            'lifecycleState' => 'PUBLISHED'
        ];

        if ($mediaUrn) {
            $payload['content'] = [
                'media' => [
                    'title' => 'CrossPublish Post',
                    'id' => $mediaUrn
                ]
            ];
        }

        $res = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'LinkedIn-Version' => '202306',
            'X-Restli-Protocol-Version' => '2.0.0',
            'Content-Type' => 'application/json'
        ])->post('https://api.linkedin.com/rest/posts', $payload);

        if ($res->failed()) {
            throw new \Exception('Failed to create LinkedIn post: ' . ($res->json()['message'] ?? $res->body()));
        }

        // The post URN is returned in the 'x-linkedin-id' response header
        $postId = $res->header('x-linkedin-id') ?? $res->json()['id'] ?? null;

        // Try to get handle for link redirection, default to omyadav16
        $username = $account->platform_id === 'linkedin-mock-id' ? 'omyadav16' : $account->platform_id;
        
        $permalink = $postId 
            ? "https://www.linkedin.com/feed/update/{$postId}" 
            : "https://www.linkedin.com/in/omyadav16";

        JobLog::create([
            'job_id' => $jobId,
            'platform_key' => 'linkedin',
            'message' => "Successfully shared on LinkedIn: {$permalink}",
            'type' => 'success'
        ]);

        return $permalink;
    }
}
