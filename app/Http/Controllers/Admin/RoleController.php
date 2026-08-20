<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Services\Admin\RoleService;

class RoleController extends Controller
{
    public function index()
    {
        try {
            return RoleService::getRoles();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve roles.',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            return RoleService::getRole($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve role.',
            ], 500);
        }
    }

    public function store(RoleRequest $request)
    {
        try {
            return RoleService::storeRole($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role.',
            ], 500);
        }
    }

    public function update(RoleRequest $request, $id)
    {
        try {
            return RoleService::updateRole($request, $id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role.',
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            return RoleService::deleteRole($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role.',
            ], 500);
        }
    }

    public function toggleActive($id)
    {
        try {
            return RoleService::toggleActive($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role status.',
            ], 500);
        }
    }
}