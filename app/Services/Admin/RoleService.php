<?php

namespace App\Services\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Admin\RoleRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\Department;
use App\Models\Permission;
use App\Helpers\AuditHelper;

class RoleService
{
    /* get listing */
    public static function getRoles()
    {
        try {
            $roles = Role::with(['department', 'permissions'])
                ->whereHas('department', function ($query) {
                    $query->where('name', '!=', 'Admin');
                })
                ->orderBy('name')
                ->get();

            return ResponseHelper::success(
                $roles,
                'Roles fetched successfully.'
            );
        } catch (\Exception $e) {
            info('Error in RoleService@getRoles', [
                'error' => $e->getMessage(),
            ]);
            return ResponseHelper::error(
                'Failed to retrieve roles.',
                500
            );
        }
    }

    /* get role data by ID */
    public static function getRole($id)
    {
        try {
            $role = Role::with(['department', 'permissions'])
                ->findOrFail($id);

            return ResponseHelper::success(
                $role,
                'Role fetched successfully.'
            );

        } catch (\Exception $e) {
            info('Error in RoleService@getRole', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Role not found.',
                404
            );
        }
    }

    /* store role data */
    public static function storeRole(RoleRequest $request)
    {
        DB::beginTransaction();

        try {

            $role = Role::create([
                'department_id' => $request->department_id,
                'name'          => $request->name,
                'is_active'     => $request->is_active ?? 1,
            ]);

            $permission = Permission::create([
                'name' => $request->permissions,
            ]);

            $role->permissions()->attach($permission->id);

            /* Audit code */
            $newValue = [
                'department'    => $role->department?->name,
                'name'          => $role->name,
                'permissions'   => $permission->name,
            ];

            AuditHelper::log(
                'Role',
                'Created',
                'Role created successfully.',
                $role->id,
                null,
                $newValue,
                Role::class
            );

            DB::commit();

            $role->load('permissions');

            return ResponseHelper::success(
                $role,
                'Role created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in RoleService@storeRole', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to create role.',
                500
            );
        }
    }

    /* update role data */
    public static function updateRole(RoleRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $role = Role::findOrFail($id);

            $oldValue = [];
            $newValue = [];
            $updateData = [];

            if ($request->has('department_id')) {
                $oldValue['department'] = $role->department?->name;
                $newValue['department'] = Department::find($request->department_id)?->name;

                $updateData['department_id'] = $request->department_id;
            }

            if ($request->has('name')) {
                $oldValue['name'] = $role->name;
                $newValue['name'] = $request->name;

                $updateData['name'] = $request->name;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }

            if (!empty($updateData)) {
                $role->update($updateData);
            }

            if ($request->has('permissions')) {
                $permission = $role->permissions()->first();

                if ($permission) {
                    $oldValue['permissions'] = $permission->name;

                    $permission->update([
                        'name' => $request->permissions,
                    ]);
                    $newValue['permissions'] = $request->permissions;
                } else {
                    $permission = Permission::create([
                        'name' => $request->permissions,
                    ]);

                    $role->permissions()->attach($permission->id);

                    $oldValue['permissions'] = null;
                    $newValue['permissions'] = $request->permissions;
                }
            }

            if (!empty($newValue)) {
                AuditHelper::log(
                    'Role',
                    'Updated',
                    'Role updated successfully.',
                    $role->id,
                    $oldValue,
                    $newValue,
                    Role::class
                );
            }

            DB::commit();

            $role->load(['department', 'permissions']);

            return ResponseHelper::success(
                $role,
                'Role updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in RoleService@updateRole', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to update role.',
                500
            );
        }
    }

    /* delete role */
    public static function deleteRole($id)
    {
        DB::beginTransaction();

        try {
            $role = Role::findOrFail($id);

            // if ($role->users()->exists()) {
            //     throw new \Exception(
            //         'This role cannot be deleted because it is assigned to users.'
            //     );
            // }

            $oldValue = [
                'name'          => $role->name,
            ];

            $permission = $role->permissions()->first();

            if ($permission) {
                $oldValue['permissions'] = $permission->name;
            }

            $role->delete();

            /* audit code */
            AuditHelper::log(
                'Role',
                'Deleted',
                'Role deleted successfully.',
                $role->id,
                $oldValue,
                null,
                Role::class
            );

            DB::commit();

            return ResponseHelper::success(
                null,
                'Role deleted successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in RoleService@deleteRole', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                $e->getMessage(),
                400
            );
        }
    }

    public static function toggleActive($id)
    {
        DB::beginTransaction();

        try {
            $role = Role::findOrFail($id);
            $oldValue = [
                'is_active' => $role->is_active,
            ];

            $role->is_active = $role->is_active ? 0 : 1;
            $role->save();

            DB::commit();

            $newValue = [
                'is_active' => $role->is_active,
            ];

            $message = $role->is_active
                ? 'Role activated successfully.'
                : 'Role deactivated successfully.';

            AuditHelper::log(
                'Role',
                'Status Updated',
                $message,
                $role->id,
                $oldValue,
                $newValue,
                Role::class
            );

            return ResponseHelper::success(
                $role,
                $message
            );
        } catch (\Exception $e) {
            DB::rollBack();
            info('Error in RoleService@toggleActive', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to update role status.',
                500
            );
        }
    }
}