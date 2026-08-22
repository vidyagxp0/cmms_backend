<?php
namespace App\Services\Admin;
use App\Helpers\ResponseHelper;
use App\Helpers\AuditHelper;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class UserService
{
    /* get user PID */
    public static function getUserPID()
    {
        try {
            $users = User::where(['is_active' => "1"])->count();
            $pid = 'PID000' . $users;
            return ResponseHelper::success($pid, 'Users ID fetched successfully.');
        } catch (\Exception $e) {
            info('Error in UserService@getUserPID', ['error' => $e->getMessage()]);
            return ResponseHelper::error('Failed to retrieve PID.', 500);
        }
    }

    /* get user listing */
     public static function getUsers()
        {
            try {
                $query = User::with(['department', 'roles'])
                    ->whereDoesntHave('roles', function ($query) {
                        $query->where('name', 'Admin');
                    })
                    ->orderByDesc('id');
                $search = trim((string) request()->input('search', ''));

                if ($search !== '') {
                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('person_id', 'like', "%{$search}%");
                    });
                }
                $perPage = (int) request()->input('per_page', 10);
                $perPage = min(max($perPage, 1), 100);

                $users = $query
                    ->paginate($perPage)
                    ->withQueryString();
                return ResponseHelper::success(
                    $users,
                    'Users fetched successfully.'
                );

            } catch (\Exception $e) {
                info('Error in UserService@getUsers', [
                    'error' => $e->getMessage(),
                ]);
                return ResponseHelper::error(
                    'Failed to retrieve users.'
                    
                );
            }
        }
    public static function getUser($id)
    {
        try {
            $user = User::with(['department', 'roles'])->findOrFail($id);
            return ResponseHelper::success($user, 'User fetched successfully.');
        } catch (\Exception $e) {
            info('Error in UserService@getUser', ['error' => $e]);
            return ResponseHelper::error('User not found.', 404);
        }
    }

    /* store user apis */
    public static function storeUser(UserRequest $request)
    {
        DB::beginTransaction();
        try {
            $user = User::create([
                'salutation' => $request->salutation,
                'person_id' => $request->person_id,
                'name' => $request->name,
                'username' => $request->username,
                'email' => $request->email,
                'mobile_no' => $request->mobile_no,
                'password' => Hash::make($request->password),
                'department_id' => $request->department_id,
            ]);
            $user->roles()->sync($request->roles);
            $user->load(['department', 'roles']);

            /* Audit code */
            $newValue = [
                'salutation' => $user->salutation,
                'person_id' => $user->person_id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'mobile_no' => $user->mobile_no,
                'department' => $user->department?->name,
                'roles' => $user->roles->pluck('name')->toArray(),
            ];
            AuditHelper::log(
                'User',
                'Created',
                'User created successfully.',
                $user->id,
                null,
                $newValue,
                User::class
            );

            DB::commit();
            return ResponseHelper::success($user, 'User created successfully.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            info('Error in UserService@storeUser', ['error' => $e]);
            return ResponseHelper::error('Failed to create user.', 500);
        }
    }

    /* update user apis */
    public static function updateUser(UpdateUserRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $user = User::with(['department', 'roles'])->findOrFail($id);
            $oldValue = [];
            $newValue = [];
            $updateData = [];

            if ($request->has('salutation') && $request->salutation !== $user->salutation) {
                $oldValue['salutation'] = $user->salutation;
                $newValue['salutation'] = $request->salutation;

                $updateData['salutation'] = $request->salutation;
            }

            if ($request->has('person_id') && $request->person_id !== $user->person_id) {
                $oldValue['person_id'] = $user->person_id;
                $newValue['person_id'] = $request->person_id;

                $updateData['person_id'] = $request->person_id;
            }

            if ($request->has('name') && $request->name !== $user->name) {
                $oldValue['name'] = $user->name;
                $newValue['name'] = $request->name;

                $updateData['name'] = $request->name;
            }

            if ($request->has('username') && $request->username !== $user->username) {
                $oldValue['username'] = $user->username;
                $newValue['username'] = $request->username;

                $updateData['username'] = $request->username;
            }

            if ($request->has('email') && $request->email !== $user->email) {
                $oldValue['email'] = $user->email;
                $newValue['email'] = $request->email;

                $updateData['email'] = $request->email;
            }

            if ($request->has('mobile_no') && $request->mobile_no !== $user->mobile_no) {
                $oldValue['mobile_no'] = $user->mobile_no;
                $newValue['mobile_no'] = $request->mobile_no;

                $updateData['mobile_no'] = $request->mobile_no;
            }

            if ($request->has('department_id') && $request->department_id !== $user->department_id) {
                $oldValue['department'] = $user->department?->name;
                $department = \App\Models\Department::find($request->department_id);
                $newValue['department'] = $department?->name;

                $updateData['department_id'] = $request->department_id;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }
            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);
            }

            if ($request->has('roles')) {
                $oldRoles = $user->roles->pluck('name')->sort()->values()->toArray();
                $user->roles()->sync($request->roles);
                $user->load('roles');
                $newRoles = $user->roles->pluck('name')->sort()->values()->toArray();
                if ($oldRoles !== $newRoles) {
                    $oldValue['roles'] = $oldRoles;
                    $newValue['roles'] = $newRoles;
                }
            }

            /* Audit Code */
            if (!empty($newValue)) {
                AuditHelper::log(
                    'User',
                    'Updated',
                    'User updated successfully.',
                    $user->id,
                    $oldValue,
                    $newValue,
                    User::class
                );
            }

            DB::commit();
            $user->load(['department', 'roles']);
            return ResponseHelper::success($user, 'User updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            info('Error in UserService@updateUser', ['error' => $e]);
            return ResponseHelper::error('Failed to update user.', 500);
        }
    }

    /* delete user apis */
    public static function deleteUser($id)
    {
        DB::beginTransaction();
        try {
            $user = User::with(['department', 'roles'])->findOrFail($id);

            /* old values */
            $oldValue = [
                'salutation' => $user->salutation,
                'person_id' => $user->person_id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'mobile_no' => $user->mobile_no,
                'department' => $user->department?->name,
                'roles' => $user->roles->pluck('name')->toArray(),
            ];
            $user->roles()->detach();
            $user->delete();

            /* audit code */
            AuditHelper::log(
                'User',
                'Deleted',
                'User deleted successfully.',
                $user->id,
                $oldValue,
                null,
                User::class
            );

            DB::commit();
            return ResponseHelper::success(null, 'User deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            info('Error in UserService@deleteUser', ['error' => $e]);
            return ResponseHelper::error('Failed to delete user.', 500);
        }
    }

    /* active/inactive user apis */
    public static function toggleActive($id)
    {
        DB::beginTransaction();
        try {
            $user = User::findOrFail($id);

            $oldValue = ['is_active' => $user->is_active];
            $user->is_active = $user->is_active ? 0 : 1;
            $user->save();

            $newValue = ['is_active' => $user->is_active];
            $message = $user->is_active ? 'User activated successfully.' : 'User deactivated successfully.';

            /* audit code */
            AuditHelper::log(
                'User',
                'Status Updated',
                $message,
                $user->id,
                $oldValue,
                $newValue,
                User::class
            );
            
            DB::commit();
            return ResponseHelper::success($user, $message);
        } catch (\Exception $e) {
            DB::rollBack();
            info('Error in UserService@toggleActive', ['error' => $e]);
            return ResponseHelper::error('Failed to update user status.', 500);
        }
    }
}