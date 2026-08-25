<?php

namespace App\Http\Controllers\Auth;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthRequest;
use App\Http\Requests\Auth\ProfileRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
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

    /* get profile */
    public function profile(Request $request): JsonResponse
    {
        try {

            $data = $this->authService->profile(
                $request->user()
            );

            return ResponseHelper::success(
                $data,
                'Profile fetched successfully.'
            );
        } catch (Throwable $e) {
            return ResponseHelper::error(
                'Unable to fetch profile.',
                500
            );
        }
    }

    /* update profile */
    public function updateProfile(ProfileRequest $request): JsonResponse 
    {
        try {
            $data = $this->authService->updateProfile(
                $request->user(),
                $request->validated()
            );

            return ResponseHelper::success(
                $data,
                'Profile updated successfully.'
            );

        } catch (Throwable $e) {
            return ResponseHelper::error(
                'Unable to update profile.',
                500
            );
        }
    }

    /* change password api */
    public function changePassword(ChangePasswordRequest $request): JsonResponse 
    {
        try {
            $this->authService->changePassword(
                $request->user(),
                $request->validated('password')
            );

            return ResponseHelper::success(
                null,
                'Password changed successfully.'
            );
        } catch (Throwable $e) {
            return ResponseHelper::error(
                'Unable to change password.',
                500
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
                'Unable to logout.',
                500
            );
        }
    }

    public function getUserActivities()
    {
        try {
            return AuthService::getUserActivities();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user activities.',
            ], 500);
        }
    }
    
    /* get users data of different roles */
    public function getRoleBasedUsers()
    {
        try {
            return AuthService::getRoleBasedUsers();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workflow users.',
            ], 500);
        }
    }
}