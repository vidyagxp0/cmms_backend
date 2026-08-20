<?php

namespace App\Services\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Admin\RoleRequest;
use Illuminate\Support\Facades\DB;
use App\Models\Role;
use App\Models\Permission;

class RoleService
{
    /* get listing */
    public static function getRoles()
    {
        try {
            $roles = Role::orderBy('name')->get();
            return ResponseHelper::success(
                $roles,
                'Roles fetched successfully.'
            );
        } catch (\Exception $e) {
            info('Error in RoleService@getRoles', [
                'error' => $e
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
            $role = Role::findOrFail($id);
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

            $role->update([
                'name' => $request->name,
                'is_active' => $request->is_active ?? 1,
            ]);

            $permission = $role->permissions()->first();

            if ($permission) {
                $permission->update([
                    'name' => $request->permissions,
                ]);
            } else {
                $permission = Permission::create([
                    'name' => $request->permissions,
                ]);
                $role->permissions()->attach($permission->id);
            }

            DB::commit();

            $role->load('permissions');

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
            if ($role->users()->exists()) {
                throw new \Exception(
                    'This role cannot be deleted because it is assigned to users.'
                );
            }

            $role->delete();

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

            $role->is_active = $role->is_active ? 0 : 1;
            $role->save();

            DB::commit();
            $message = $role->is_active
                ? 'Role activated successfully.'
                : 'Role deactivated successfully.';

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