<?php
// app/Http/Controllers/WordPressController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;

class WordPressController extends Controller
{
    public function link(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'string'],
            'username' => ['required', 'string'],
            'appPassword' => ['required', 'string'],
        ]);

        $url = $request->input('url');
        $username = $request->input('username');
        $appPassword = $request->input('appPassword');

        try {
            $cleanUrl = rtrim(trim($url), '/');
            if (!str_starts_with($cleanUrl, 'http://') && !str_starts_with($cleanUrl, 'https://')) {
                $cleanUrl = 'https://' . $cleanUrl;
            }

            // Verify credentials with WordPress API
            $response = Http::withBasicAuth($username, $appPassword)
                ->get("{$cleanUrl}/wp-json/wp/v2/users/me");

            if ($response->failed()) {
                $msg = $response->json()['message'] ?? 'Verification failed';
                return response()->json(['error' => "WordPress Verification Failed: {$msg}"], 401);
            }

            // Save WordPress account details.
            // We serialize url, username and appPassword into access_token so it's fully encrypted.
            $credentials = json_encode([
                'url' => $cleanUrl,
                'username' => $username,
                'appPassword' => $appPassword
            ]);

            Account::updateOrCreate(
                ['platform' => 'wordpress'],
                [
                    'account_name' => "{$username} @ " . preg_replace('/^https?:\/\//', '', $cleanUrl),
                    'platform_id' => 'wordpress-api',
                    'access_token' => $credentials,
                    'refresh_token' => null,
                    'expires_at' => null,
                    'linked_at' => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'WordPress account linked successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'WordPress Verification Failed: ' . $e->getMessage()], 500);
        }
    }
}
