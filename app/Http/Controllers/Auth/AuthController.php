<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function login(AuthRequest $request): JsonResponse
    {
        try {
            $data = $this->authService->login(
                $request->validated()
            );

            return ResponseHelper::success(
                $data,
                'Login successful.'
            );

        } catch (Throwable $e) {
            return ResponseHelper::error(
                $e->getMessage(),
                401
            );
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout(
                $request->user()
            );

            return ResponseHelper::success(
                null,
                'Logout successful.'
            );

        } catch (Throwable $e) {
            return ResponseHelper::error(
                $e->getMessage(),
                500
            );
        }
    }
}