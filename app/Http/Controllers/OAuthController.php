<?php
// app/Http/Controllers/OAuthController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class OAuthController extends Controller
{
    private function getGoogleRedirectUri(Request $request): string
    {
        $redirectUri = env('GOOGLE_REDIRECT_URI', 'http://localhost:3000/auth/youtube/callback');
        $host = $request->getHost();
        $port = $request->getPort();
        $hostWithPort = $port && $port != 80 && $port != 443 ? "{$host}:{$port}" : $host;
        if ($host && !str_contains($redirectUri, $host)) {
            $redirectUri = $request->getScheme() . "://{$hostWithPort}/auth/youtube/callback";
        }
        return $redirectUri;
    }

    private function getInstagramRedirectUri(Request $request): string
    {
        $redirectUri = env('FACEBOOK_REDIRECT_URI', 'http://localhost:3000/auth/instagram/callback');
        $host = $request->getHost();
        $port = $request->getPort();
        $hostWithPort = $port && $port != 80 && $port != 443 ? "{$host}:{$port}" : $host;
        if ($host && !str_contains($redirectUri, $host)) {
            $redirectUri = $request->getScheme() . "://{$hostWithPort}/auth/instagram/callback";
        }
        return $redirectUri;
    }

    public function youtubeAuth(Request $request): RedirectResponse
    {
        $redirectUri = $this->getGoogleRedirectUri($request);

        $client = new \Google\Client();
        $client->setClientId(env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri($redirectUri);
        $client->addScope(\Google\Service\YouTube::YOUTUBE_UPLOAD);
        $client->addScope(\Google\Service\YouTube::YOUTUBE_READONLY);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return redirect()->away($client->createAuthUrl());
    }

    public function youtubeCallback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        if (!$code) {
            return redirect()->to('/?error=youtube_auth_cancelled');
        }

        try {
            $redirectUri = $this->getGoogleRedirectUri($request);

            $client = new \Google\Client();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
            $client->setRedirectUri($redirectUri);

            $tokens = $client->fetchAccessTokenWithAuthCode($code);
            if (isset($tokens['error'])) {
                throw new \Exception($tokens['error_description'] ?? $tokens['error']);
            }

            $client->setAccessToken($tokens);

            $youtube = new \Google\Service\YouTube($client);
            $channelsRes = $youtube->channels->listChannels('snippet', ['mine' => true]);

            if (empty($channelsRes->getItems())) {
                throw new \Exception('No YouTube channel found.');
            }

            $channel = $channelsRes->getItems()[0];
            $channelName = $channel->getSnippet()->getTitle();
            $channelId = $channel->getId();

            $expiresAt = null;
            if (isset($tokens['expires_in'])) {
                $expiresAt = now()->addSeconds($tokens['expires_in']);
            }

            Account::updateOrCreate(
                ['platform' => 'youtube'],
                [
                    'account_name' => $channelName,
                    'platform_id' => $channelId,
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'] ?? null,
                    'expires_at' => $expiresAt,
                    'linked_at' => now(),
                ]
            );

            return redirect()->to('/?success=youtube');
        } catch (\Exception $e) {
            Log::error('YouTube OAuth Error: ' . $e->getMessage());
            return redirect()->to('/?error=youtube_auth_failed&msg=' . urlencode($e->getMessage()));
        }
    }

    public function instagramAuth(Request $request): RedirectResponse
    {
        $appId = env('FACEBOOK_APP_ID');
        $redirectUri = $this->getInstagramRedirectUri($request);

        if (!$appId || !env('FACEBOOK_APP_SECRET')) {
            abort(500, 'Facebook OAuth credentials not configured in .env');
        }

        $configId = env('FACEBOOK_CONFIG_ID');
        if ($configId) {
            $url = "https://www.facebook.com/v19.0/dialog/oauth?client_id={$appId}&redirect_uri=" . urlencode($redirectUri) . "&config_id={$configId}&response_type=code&override_default_response_type=true";
        } else {
            $url = "https://www.facebook.com/v19.0/dialog/oauth?client_id={$appId}&redirect_uri=" . urlencode($redirectUri) . "&scope=instagram_basic,instagram_content_publish,pages_show_list,pages_read_engagement";
        }
        return redirect()->away($url);
    }

    public function instagramCallback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        if (!$code) {
            return redirect()->to('/?error=instagram_auth_cancelled');
        }

        try {
            $appId = env('FACEBOOK_APP_ID');
            $appSecret = env('FACEBOOK_APP_SECRET');
            $redirectUri = $this->getInstagramRedirectUri($request);

            // 1. Get short-lived token
            $response = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
                'client_id' => $appId,
                'redirect_uri' => $redirectUri,
                'client_secret' => $appSecret,
                'code' => $code,
            ]);

            if ($response->failed()) {
                throw new \Exception($response->json()['error']['message'] ?? 'Failed to exchange code');
            }

            $shortToken = $response->json()['access_token'];

            // 2. Get long-lived token
            $longTokenRes = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
                'grant_type' => 'fb_exchange_token',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'fb_exchange_token' => $shortToken,
            ]);

            if ($longTokenRes->failed()) {
                throw new \Exception($longTokenRes->json()['error']['message'] ?? 'Failed to obtain long-lived token');
            }

            $longToken = $longTokenRes->json()['access_token'];
            $expiresIn = $longTokenRes->json()['expires_in'] ?? (60 * 24 * 60 * 60);
            $expiresAt = now()->addSeconds($expiresIn);

            // 3. Get Pages linked to this user token to find Instagram Account
            $pagesRes = Http::get('https://graph.facebook.com/v19.0/me/accounts', [
                'fields' => 'instagram_business_account,name',
                'access_token' => $longToken,
            ]);

            if ($pagesRes->failed()) {
                throw new \Exception($pagesRes->json()['error']['message'] ?? 'Failed to retrieve Facebook pages');
            }

            $pages = $pagesRes->json()['data'] ?? [];
            $linkedPage = null;
            foreach ($pages as $p) {
                if (isset($p['instagram_business_account'])) {
                    $linkedPage = $p;
                    break;
                }
            }

            if (!$linkedPage) {
                throw new \Exception('No Instagram Business/Creator account found linked to Facebook page.');
            }

            $igAccountId = $linkedPage['instagram_business_account']['id'];
            $pageName = $linkedPage['name'];

            Account::updateOrCreate(
                ['platform' => 'instagram'],
                [
                    'account_name' => "{$pageName} (Insta)",
                    'platform_id' => $igAccountId,
                    'access_token' => $longToken,
                    'refresh_token' => null,
                    'expires_at' => $expiresAt,
                    'linked_at' => now(),
                ]
            );

            return redirect()->to('/?success=instagram');
        } catch (\Exception $e) {
            Log::error('Instagram OAuth Error: ' . $e->getMessage());
            return redirect()->to('/?error=instagram_auth_failed&msg=' . urlencode($e->getMessage()));
        }
    }

    public function twitterAuth(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            Account::updateOrCreate(
                ['platform' => 'twitter'],
                [
                    'account_name' => '@MeekDragon_Dev',
                    'platform_id' => 'twitter-mock-id',
                    'access_token' => 'mock-twitter-token',
                    'refresh_token' => null,
                    'expires_at' => null,
                    'linked_at' => now(),
                ]
            );

            return redirect()->to('/?success=twitter');
        } catch (\Exception $e) {
            Log::error('Twitter Mock OAuth Error: ' . $e->getMessage());
            return redirect()->to('/?error=twitter_auth_failed&msg=' . urlencode($e->getMessage()));
        }
    }

    public function linkedinAuth(Request $request): \Illuminate\Http\RedirectResponse
    {
        try {
            Account::updateOrCreate(
                ['platform' => 'linkedin'],
                [
                    'account_name' => 'Om Yadav (LinkedIn)',
                    'platform_id' => 'linkedin-mock-id',
                    'access_token' => 'mock-linkedin-token',
                    'refresh_token' => null,
                    'expires_at' => null,
                    'linked_at' => now(),
                ]
            );

            return redirect()->to('/?success=linkedin');
        } catch (\Exception $e) {
            Log::error('LinkedIn Mock OAuth Error: ' . $e->getMessage());
            return redirect()->to('/?error=linkedin_auth_failed&msg=' . urlencode($e->getMessage()));
        }
    }
}
