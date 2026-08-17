<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return ResponseHelper::error(
                'Unauthenticated.',
                401
            );
        }

        if ($user->role !== 'user') {
            return ResponseHelper::error(
                'You are not authorized to access this resource.',
                403
            );
        }

        return $next($request);
    }
}