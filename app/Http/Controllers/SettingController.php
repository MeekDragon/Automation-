<?php
// app/Http/Controllers/SettingController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    private function getSettingsArray(): array
    {
        $keys = [
            'defaultTags' => '',
            'defaultDescription' => '',
            'youtubePrivacy' => 'public',
            'youtubeCategory' => '22',
            'wordpressStatus' => 'publish'
        ];

        $settings = [];
        foreach ($keys as $key => $default) {
            $setting = Setting::where('key', $key)->first();
            $settings[$key] = $setting ? $setting->value : $default;
        }

        return $settings;
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->getSettingsArray());
    }

    public function save(Request $request): JsonResponse
    {
        $request->validate([
            'defaultTags' => ['nullable', 'string'],
            'defaultDescription' => ['nullable', 'string'],
            'youtubePrivacy' => ['nullable', 'string'],
            'youtubeCategory' => ['nullable', 'string'],
            'wordpressStatus' => ['nullable', 'string'],
        ]);

        $keys = ['defaultTags', 'defaultDescription', 'youtubePrivacy', 'youtubeCategory', 'wordpressStatus'];

        foreach ($keys as $key) {
            if ($request->has($key)) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $request->input($key) ?? '']
                );
            }
        }

        return response()->json([
            'success' => true,
            'settings' => $this->getSettingsArray()
        ]);
    }
}
