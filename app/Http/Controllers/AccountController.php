<?php
// app/Http/Controllers/AccountController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use Illuminate\Http\JsonResponse;

class AccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $platforms = ['youtube', 'instagram', 'wordpress'];
        $accountsInfo = [];

        foreach ($platforms as $p) {
            $acc = Account::where('platform', $p)->first();
            $accountsInfo[$p] = $acc ? [
                'linked' => true,
                'accountName' => $acc->account_name,
                'platformId' => $acc->platform_id,
                'linkedAt' => $acc->linked_at->toIso8601String()
            ] : [
                'linked' => false
            ];
        }

        return response()->json($accountsInfo);
    }

    public function unlink(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => ['required', 'string']
        ]);

        $platform = $request->input('platform');

        $deleted = Account::where('platform', $platform)->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => ucfirst($platform) . ' unlinked successfully'
            ]);
        }

        return response()->json(['error' => 'Platform connection not found or already unlinked.'], 404);
    }
}
