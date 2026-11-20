<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\Admin\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    public function getUserPID()
    {
        try {
            return UserService::getUserPID();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve pid.',
            ], 500);
        }
    }

    /* user listing function */
    public function index()
    {
        try {
            return UserService::getUsers();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users.',
            ], 500);
        }
    }

    /* user details function */
    public function show($id)
    {
        try {
            return UserService::getUser($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user.',
            ], 500);
        }
    }

    /* store user function */
    public function store(UserRequest $request)
    {
        try {
            return UserService::storeUser($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user.',
            ], 500);
        }
    }

    /* update user function */
    public function update(UpdateUserRequest $request, $id)
    {
        try {
            return UserService::updateUser($request, $id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user.',
            ], 500);
        }
    }

    /* user delete function */
    public function destroy($id)
    {
        try {
            return UserService::deleteUser($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user.',
            ], 500);
        }
    }

    /* user active/inactive function */
    public function toggleActive($id)
    {
        try {
            return UserService::toggleActive($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user status.',
            ], 500);
        }
    }

    /* checks username and password */
    public function checkCredentials(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            $user = User::where('username', $request->username)->first();

            if (!$user) {
                return ResponseHelper::error('Invalid username or password.', 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                return ResponseHelper::error('Invalid username or password.', 401);
            }

            return ResponseHelper::success(
                [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                ],
                'Credentials verified successfully.'
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;

        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Something went wrong while checking credentials.',
                500
            );
        }
    }
}