<?php
// routes/web.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\WordPressController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\SettingController;

// Web Views
Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->to('/login');
    }
    return view('dashboard');
})->name('dashboard');

Route::get('/login', function () {
    if (auth()->check()) {
        return redirect()->to('/');
    }
    return view('login');
})->name('login');

// Authentication API
Route::post('/api/auth/login', [AuthController::class, 'login']);
Route::post('/api/auth/logout', [AuthController::class, 'logout']);
Route::get('/api/auth/me', [AuthController::class, 'me']);

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    // Accounts API
    Route::get('/api/accounts', [AccountController::class, 'index']);
    
    // Settings Presets API
    Route::get('/api/settings', [SettingController::class, 'index']);
    
    // Jobs & Telemetry API
    Route::get('/api/jobs', [JobController::class, 'index']);
    Route::get('/api/jobs/{id}/logs', [JobController::class, 'logs']);
    Route::get('/api/logs', [JobController::class, 'logs']);
    Route::get('/api/telemetry', [JobController::class, 'telemetry']);
    
    // Administrative-only routes (Superadmin and Admin)
    Route::middleware([\App\Http\Middleware\RequireAdminRole::class])->group(function () {
        Route::post('/api/accounts/unlink', [AccountController::class, 'unlink']);
        Route::post('/api/accounts/wordpress', [WordPressController::class, 'link']);
        Route::post('/api/settings', [SettingController::class, 'save']);
        Route::post('/api/jobs', [JobController::class, 'store']);
        
        // OAuth links
        Route::get('/auth/youtube', [OAuthController::class, 'youtubeAuth']);
        Route::get('/auth/instagram', [OAuthController::class, 'instagramAuth']);
        Route::get('/auth/twitter', [OAuthController::class, 'twitterAuth']);
        Route::get('/auth/linkedin', [OAuthController::class, 'linkedinAuth']);
    });
});

// OAuth Callback routes (must be guest/public accessible since external providers redirect here)
Route::get('/auth/youtube/callback', [OAuthController::class, 'youtubeCallback']);
Route::get('/auth/instagram/callback', [OAuthController::class, 'instagramCallback']);
Route::get('/auth/twitter/callback', [OAuthController::class, 'twitterCallback']);
Route::get('/auth/linkedin/callback', [OAuthController::class, 'linkedinCallback']);
