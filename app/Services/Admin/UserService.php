<?php

namespace App\Services\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /* user listing */
    public static function getUsers()
    {
        try {
            $users = User::with(['department', 'roles'])
                ->whereDoesntHave('roles', function ($query) {
                    $query->where('name', 'Admin');
                })
                ->orderBy('id', 'desc')
                ->get();

            return ResponseHelper::success(
                $users,
                'Users fetched successfully.'
            );

        } catch (\Exception $e) {
            info('Error in UserService@getUsers', [
                'error' => $e->getMessage(),
            ]);

            return ResponseHelper::error(
                'Failed to retrieve users.',
                500
            );
        }
    }

    /* get user details */
    public static function getUser($id)
    {
        try {
            $user = User::with(['department', 'roles'])->findOrFail($id);

            return ResponseHelper::success(
                $user,
                'User fetched successfully.'
            );

        } catch (\Exception $e) {
            info('Error in UserService@getUser', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'User not found.',
                404
            );
        }
    }

    /* store user */
    public static function storeUser(UserRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = User::create([
                'salutation'    => $request->salutation,
                'person_id'     => $request->person_id,
                'name'          => $request->name,
                'username'      => $request->username,
                'email'         => $request->email,
                'mobile_no'     => $request->mobile_no,
                'password'      => Hash::make($request->password),
                'department_id' => $request->department_id,
            ]);

            $user->roles()->sync($request->roles);

            DB::commit();

            $user->load(['department', 'roles']);

            return ResponseHelper::success(
                $user,
                'User created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in UserService@storeUser', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to create user.',
                500
            );
        }
    }

    /* update user */
    public static function updateUser(UpdateUserRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);
            $updateData = [];

            if ($request->has('salutation')) {
                $updateData['salutation'] = $request->salutation;
            }

            if ($request->has('person_id')) {
                $updateData['person_id'] = $request->person_id;
            }

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->has('username')) {
                $updateData['username'] = $request->username;
            }

            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }

            if ($request->has('mobile_no')) {
                $updateData['mobile_no'] = $request->mobile_no;
            }

            if ($request->has('department_id')) {
                $updateData['department_id'] = $request->department_id;
            }

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->is_active;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            if ($request->filled('password')) {
                $user->update([
                    'password' => Hash::make($request->password),
                ]);
            }

            if ($request->has('roles')) {
                $user->roles()->sync($request->roles);
            }

            DB::commit();

            $user->load(['department', 'roles']);

            return ResponseHelper::success(
                $user,
                'User updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in UserService@updateUser', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to update user.',
                500
            );
        }
    }

    /* delete user */
    public static function deleteUser($id)
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);
            $user->roles()->detach();
            $user->delete();

            DB::commit();

            return ResponseHelper::success(
                null,
                'User deleted successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in UserService@deleteUser', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to delete user.',
                500
            );
        }
    }

    public static function toggleActive($id)
    {
        DB::beginTransaction();

        try {
            $user = User::findOrFail($id);
            $user->is_active = $user->is_active ? 0 : 1;
            $user->save();

            DB::commit();

            return ResponseHelper::success(
                $user,
                $user->is_active
                    ? 'User activated successfully.'
                    : 'User deactivated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            info('Error in UserService@toggleActive', [
                'error' => $e
            ]);
            return ResponseHelper::error(
                'Failed to update user status.',
                500
            );
        }
    }
}