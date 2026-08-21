<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Services\Admin\UserService;

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
}