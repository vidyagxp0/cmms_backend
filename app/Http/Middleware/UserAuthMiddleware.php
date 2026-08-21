<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UserAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($user->roles()->where('name', 'Admin')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to access these APIs.',
            ], 403);
        }

        return $next($request);
    }
}