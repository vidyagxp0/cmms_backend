<?php

namespace App\Services\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DepartmentService
{
    /* department listing */
    public static function getDepartments()
    {
        try {
            $departments = Department::orderBy('id', 'desc')
                ->get();

            return ResponseHelper::success(
                $departments,
                'Departments fetched successfully.'
            );

        } catch (\Exception $e) {
            info('Error in DepartmentService@getDepartments', [
                'error' => $e->getMessage(),
            ]);
            return ResponseHelper::error(
                'Failed to retrieve departments.',
                500
            );
        }
    }

    /* get department details */
    public static function getDepartmentById($id)
    {
        try {
            $department = Department::findOrFail($id);

            return ResponseHelper::success(
                $department,
                'Department fetched successfully.'
            );

        } catch (\Exception $e) {
            info('Error in DepartmentService@getDepartmentById', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Department not found.',
                404
            );
        }
    }

    /* store department */
    public static function storeDepartment(StoreDepartmentRequest $request)
    {
        DB::beginTransaction();

        try {
            $department = Department::create([
                'name'          => $request->name,
                'is_active'     => $request->is_active ?? 1,
            ]);

            DB::commit();

            return ResponseHelper::success(
                $department,
                'Department created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in DepartmentService@storeDepartment', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to create department.',
                500
            );
        }
    }

    /* update department */
    public static function updateDepartment(UpdateDepartmentRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $department = Department::findOrFail($id);
            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }

            if (!empty($updateData)) {
                $department->update($updateData);
            }

            DB::commit();

            return ResponseHelper::success(
                $department,
                'Department updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in DepartmentService@updateDepartment', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to update department.',
                500
            );
        }
    }

    /* delete department */
    public static function deleteDepartment($id)
    {
        DB::beginTransaction();

        try {
            $department = Department::findOrFail($id);
            $department->delete();

            DB::commit();

            return ResponseHelper::success(
                null,
                'Department deleted successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in DepartmentService@deleteDepartment', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                //  $e->getMessage(),
                'Failed to update department status.',
                500
            );
        }
    }

    public static function toggleActive($id)
    {
        DB::beginTransaction();

        try {
            $department = Department::findOrFail($id);
            $department->is_active = $department->is_active ? 0 : 1;
            $department->save();

            DB::commit();

            return ResponseHelper::success(
                $department,
                $department->is_active
                    ? 'Department activated successfully.'
                    : 'Department deactivated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in DepartmentService@toggleActive', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                $e->getMessages(),
                // 'Failed to update department status.',
                500
            );
        }
    }
}