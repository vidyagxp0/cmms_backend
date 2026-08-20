<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Services\Admin\DepartmentService;

class DepartmentController extends Controller
{
    /* department listing function */
    public function index()
    {
        try {
            return DepartmentService::getDepartments();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve departments.',
            ], 500);
        }
    }

    /* department details function */
    public function show($id)
    {
        try {
            return DepartmentService::getDepartmentById($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve department.',
            ], 500);
        }
    }

    /* store department function */
    public function store(StoreDepartmentRequest $request)
    {
        try {
            return DepartmentService::storeDepartment($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department.',
            ], 500);
        }
    }

    /* update department function */
    public function update(UpdateDepartmentRequest $request, $id)
    {
        try {
            return DepartmentService::updateDepartment($request, $id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department.',
            ], 500);
        }
    }

    /* department delete function */
    public function destroy($id)
    {
        try {
            return DepartmentService::deleteDepartment($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department.',
            ], 500);
        }
    }

    /* department active/inactive function */
    public function toggleActive($id)
    {
        try {
            return DepartmentService::toggleActive($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department status.',
            ], 500);
        }
    }
}