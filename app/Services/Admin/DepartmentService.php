<?php

namespace App\Services\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Helpers\AuditHelper;

class DepartmentService
{
    /* department listing */
    public static function getDepartments()
    {
        try {
            $departments = Department::where('name', '!=', 'Admin')
                ->orderBy('id', 'desc')
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
                'name'      => $request->name,
                'is_active' => $request->is_active ?? 1,
            ]);

            /* audit code  */
             $newValue = [
                'name'      => $department->name,
            ];

            AuditHelper::log(
                'Department',
                'Created',
                'Department created successfully.',
                $department->id,
                null,
                $newValue,
                Department::class
            );

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

            $oldValue = [];
            $newValue = [];

            $updateData = [];
            if ($request->has('name')) {
                $oldValue['name'] = $department->name;
                $newValue['name'] = $request->name;

                $updateData['name'] = $request->name;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }

            /* save and audit code */
            if (!empty($newValue)) {
                $department->save();

                AuditHelper::log(
                    'Department',
                    'Updated',
                    'Department updated successfully.',
                    $department->id,
                    $oldValue,
                    $newValue,
                    Department::class
                );
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
            $oldValue = [
                'name'      => $department->name,
            ];
            $department->delete();

            /* audit code */
            AuditHelper::log(
                'Department',
                'Deleted',
                'Department deleted successfully.',
                $department->id,
                $oldValue,
                null,
                Department::class
            );

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
            $oldValue = [
                'is_active' => $department->is_active,
            ];
            $department->is_active = $department->is_active ? 0 : 1;
            $department->save();

            $newValue = [
                'is_active' => $department->is_active,
            ];

            DB::commit();

            /* audit code */
            AuditHelper::log(
                'Department',
                'Status Updated',
                'Status changed successfully.',
                $department->id,
                $oldValue,
                $newValue,
                Department::class
            );

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
                $e->getMessage(),
                // 'Failed to update department status.',
                500
            );
        }
    }
}