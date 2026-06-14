<?php
// app/Http/Middleware/RequireAdminRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['admin', 'superadmin'])) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Forbidden: Insufficient permissions'], 403);
            }
            abort(403, 'Forbidden: Insufficient permissions');
        }

        return $next($request);
    }
}
