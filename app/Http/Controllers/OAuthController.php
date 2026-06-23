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

    private function getTwitterRedirectUri(Request $request): string
    {
        $redirectUri = env('TWITTER_REDIRECT_URI', 'http://localhost:3000/auth/twitter/callback');
        $host = $request->getHost();
        $port = $request->getPort();
        $hostWithPort = $port && $port != 80 && $port != 443 ? "{$host}:{$port}" : $host;
        if ($host && !str_contains($redirectUri, $host)) {
            $redirectUri = $request->getScheme() . "://{$hostWithPort}/auth/twitter/callback";
        }
        return $redirectUri;
    }

    private function getLinkedinRedirectUri(Request $request): string
    {
        $redirectUri = env('LINKEDIN_REDIRECT_URI', 'http://localhost:3000/auth/linkedin/callback');
        $host = $request->getHost();
        $port = $request->getPort();
        $hostWithPort = $port && $port != 80 && $port != 443 ? "{$host}:{$port}" : $host;
        if ($host && !str_contains($redirectUri, $host)) {
            $redirectUri = $request->getScheme() . "://{$hostWithPort}/auth/linkedin/callback";
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

        if ($request->query('mock') === 'true' || !$appId || !env('FACEBOOK_APP_SECRET')) {
            try {
                Account::updateOrCreate(
                    ['platform' => 'instagram'],
                    [
                        'account_name' => 'omyadav_16 (Insta)',
                        'platform_id' => 'instagram-mock-id',
                        'access_token' => 'mock-instagram-token',
                        'refresh_token' => null,
                        'expires_at' => now()->addDays(60),
                        'linked_at' => now(),
                    ]
                );

                return redirect()->to('/?success=instagram');
            } catch (\Exception $e) {
                Log::error('Instagram Mock OAuth Error: ' . $e->getMessage());
                return redirect()->to('/?error=instagram_auth_failed&msg=' . urlencode($e->getMessage()));
            }
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
        $clientId = env('TWITTER_CLIENT_ID');
        if ($request->query('mock') === 'true' || !$clientId) {
            try {
                Account::updateOrCreate(
                    ['platform' => 'twitter'],
                    [
                        'account_name' => '@OmY1606',
                        'platform_id' => 'twitter-mock-id',
                        'access_token' => 'mock-twitter-token',
                        'refresh_token' => null,
                        'expires_at' => null,
                        'linked_at' => now(),
                    ]
                );
                return redirect()->to('/?success=twitter');
            } catch (\Exception $e) {
                return redirect()->to('/?error=twitter_auth_failed&msg=' . urlencode($e->getMessage()));
            }
        }

        $redirectUri = $this->getTwitterRedirectUri($request);
        $state = \Illuminate\Support\Str::random(40);
        $codeVerifier = \Illuminate\Support\Str::random(128);
        
        $request->session()->put('twitter_oauth_state', $state);
        $request->session()->put('twitter_oauth_verifier', $codeVerifier);
        
        $codeChallenge = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(hash('sha256', $codeVerifier, true)));
        
        $url = "https://twitter.com/i/oauth2/authorize?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => 'tweet.read tweet.write users.read offline.access media.write',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
        
        return redirect()->away($url);
    }

    public function twitterCallback(Request $request): \Illuminate\Http\RedirectResponse
    {
        $state = $request->query('state');
        $code = $request->query('code');
        $error = $request->query('error');
        
        if ($error) {
            return redirect()->to('/?error=twitter_auth_failed&msg=' . urlencode($error));
        }

        $sessionState = $request->session()->pull('twitter_oauth_state');
        $codeVerifier = $request->session()->pull('twitter_oauth_verifier');

        if (!$state || $state !== $sessionState) {
            return redirect()->to('/?error=twitter_auth_failed&msg=' . urlencode('Invalid OAuth state.'));
        }

        try {
            $clientId = env('TWITTER_CLIENT_ID');
            $clientSecret = env('TWITTER_CLIENT_SECRET');
            $redirectUri = $this->getTwitterRedirectUri($request);

            $response = Http::asForm()->withBasicAuth($clientId, $clientSecret)
                ->post('https://api.twitter.com/2/oauth2/token', [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $redirectUri,
                    'code_verifier' => $codeVerifier,
                ]);

            if ($response->failed()) {
                throw new \Exception('Failed to exchange Twitter code: ' . ($response->json()['error_description'] ?? $response->body()));
            }

            $data = $response->json();
            $accessToken = $data['access_token'];
            $refreshToken = $data['refresh_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? null;
            $expiresAt = $expiresIn ? now()->addSeconds($expiresIn) : null;

            $userRes = Http::withToken($accessToken)->get('https://api.twitter.com/2/users/me');
            if ($userRes->failed()) {
                throw new \Exception('Failed to fetch Twitter user profile: ' . $userRes->body());
            }

            $userData = $userRes->json()['data'] ?? [];
            $username = $userData['username'] ?? 'OmY1606';
            $twitterUserId = $userData['id'] ?? 'twitter-id';

            Account::updateOrCreate(
                ['platform' => 'twitter'],
                [
                    'account_name' => '@' . $username,
                    'platform_id' => $twitterUserId,
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'expires_at' => $expiresAt,
                    'linked_at' => now(),
                ]
            );

            return redirect()->to('/?success=twitter');
        } catch (\Exception $e) {
            Log::error('Twitter OAuth Error: ' . $e->getMessage());
            return redirect()->to('/?error=twitter_auth_failed&msg=' . urlencode($e->getMessage()));
        }
    }

    public function linkedinAuth(Request $request): \Illuminate\Http\RedirectResponse
    {
        $clientId = env('LINKEDIN_CLIENT_ID');
        if ($request->query('mock') === 'true' || !$clientId) {
            try {
                Account::updateOrCreate(
                    ['platform' => 'linkedin'],
                    [
                        'account_name' => 'Om Yadav (omyadav16)',
                        'platform_id' => 'linkedin-mock-id',
                        'access_token' => 'mock-linkedin-token',
                        'refresh_token' => null,
                        'expires_at' => null,
                        'linked_at' => now(),
                    ]
                );
                return redirect()->to('/?success=linkedin');
            } catch (\Exception $e) {
                return redirect()->to('/?error=linkedin_auth_failed&msg=' . urlencode($e->getMessage()));
            }
        }

        $redirectUri = $this->getLinkedinRedirectUri($request);
        $state = \Illuminate\Support\Str::random(40);
        $request->session()->put('linkedin_oauth_state', $state);

        $url = "https://www.linkedin.com/oauth/v2/authorization?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'w_member_social r_liteprofile',
        ]);

        return redirect()->away($url);
    }

    public function linkedinCallback(Request $request): \Illuminate\Http\RedirectResponse
    {
        $state = $request->query('state');
        $code = $request->query('code');
        $error = $request->query('error');
        
        if ($error) {
            return redirect()->to('/?error=linkedin_auth_failed&msg=' . urlencode($error));
        }

        $sessionState = $request->session()->pull('linkedin_oauth_state');
        if (!$state || $state !== $sessionState) {
            return redirect()->to('/?error=linkedin_auth_failed&msg=' . urlencode('Invalid OAuth state.'));
        }

        try {
            $clientId = env('LINKEDIN_CLIENT_ID');
            $clientSecret = env('LINKEDIN_CLIENT_SECRET');
            $redirectUri = $this->getLinkedinRedirectUri($request);

            $response = Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if ($response->failed()) {
                throw new \Exception('Failed to exchange LinkedIn code: ' . ($response->json()['error_description'] ?? $response->body()));
            }

            $data = $response->json();
            $accessToken = $data['access_token'];
            $expiresIn = $data['expires_in'] ?? null;
            $expiresAt = $expiresIn ? now()->addSeconds($expiresIn) : null;

            $profileRes = Http::withToken($accessToken)->get('https://api.linkedin.com/v2/me');
            if ($profileRes->failed()) {
                throw new \Exception('Failed to fetch LinkedIn profile: ' . $profileRes->body());
            }

            $profileData = $profileRes->json();
            $firstName = $profileData['localizedFirstName'] ?? 'Om';
            $lastName = $profileData['localizedLastName'] ?? 'Yadav';
            $personId = $profileData['id'] ?? 'linkedin-id';
            $accountName = "{$firstName} {$lastName} ({$personId})";

            Account::updateOrCreate(
                ['platform' => 'linkedin'],
                [
                    'account_name' => $accountName,
                    'platform_id' => $personId,
                    'access_token' => $accessToken,
                    'refresh_token' => null,
                    'expires_at' => $expiresAt,
                    'linked_at' => now(),
                ]
            );

            return redirect()->to('/?success=linkedin');
        } catch (\Exception $e) {
            Log::error('LinkedIn OAuth Error: ' . $e->getMessage());
            return redirect()->to('/?error=linkedin_auth_failed&msg=' . urlencode($e->getMessage()));
        }
    }
}
