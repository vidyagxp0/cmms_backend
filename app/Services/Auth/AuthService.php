<?php

namespace App\Services\Auth;

use App\Helpers\ResponseHelper;
use Illuminate\Support\Facades\Hash;
use DB;
use App\Models\User;
use App\Models\UserActivityLog;
use Throwable;

class AuthService
{
    public function login(array $data): array
    {
        $user = User::with([
            'roles' => function ($query) {
                $query->where('is_active', true)
                    ->with('permissions');
            }
        ])
        ->where('email', $data['email'])
        ->first();

        /* check password */
        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new \Exception('Invalid email or password.');
        }

        /* check logged in user roles */
        if ($user->roles->isEmpty()) {
            throw new \Exception('No active role assigned to this user.');
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        /* user type */
        $type = $user->roles->contains('name', 'Admin')
            ? 'Admin'
            : 'User';

        /* create user activity log */
        UserActivityLog::create([
            'user_id' => $user->id,
            'login_time' => now(),
            'status' => 'Active',
        ]);

        return [
            'user' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role_type' => $type,

                'roles'     => $user->roles
                    ->pluck('name')
                    ->values(),

                'permissions' => $user->roles
                    ->flatMap(function ($role) {
                        return $role->permissions;
                    })
                    ->pluck('name')
                    ->values(),
            ],

            'token' => $token,
        ];
    }

    /* user/admin profile apis */
    public function profile(User $user): array
    {
        try {
            $user->load([
                'roles' => function ($query) {
                    $query->where('is_active', true);
                }
            ]);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles
                    ->pluck('name')
                    ->values(),
            ];
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /* update profile */
    public function updateProfile(User $user, array $data): array
    {
        try {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $user->load([
                'roles' => function ($query) {
                    $query->where('is_active', true);
                }
            ]);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles
                    ->pluck('name')
                    ->values(),
            ];
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /* change password apis */
    public function changePassword(User $user, string $password): void 
    {
        try {
            DB::transaction(function () use ($user, $password) {
                $user->update([
                    'password' => Hash::make($password),
                ]);
                $user->tokens()->delete();
            });
        } catch (Throwable $e) {
            throw $e;
        }
    }

    /* for logging out */
    public function logout(User $user): void
    {
        try {

            $activityLog = UserActivityLog::where('user_id', $user->id)
                ->where('status', 'Active')
                ->latest('login_time')
                ->first();

            if ($activityLog) {
                $activityLog->update([
                    'logout_time' => now(),
                    'status' => 'Inactive',
                ]);
            }

            $user->currentAccessToken()?->delete();

        } catch (Throwable $e) {
            throw $e;
        }
    }

    /* user activity listing */
    public static function getUserActivities()
    {
        try {
            $query = UserActivityLog::with('user')
                ->orderBy('id', 'desc');

            // Search by user/person name
            if (request()->filled('search')) {
                $search = request('search');

                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                });
            }
            if (request()->filled('start_date')) {
                $query->whereDate('login_time', '>=', request('start_date'));
            }

            if (request()->filled('end_date')) {
                $query->whereDate('login_time', '<=', request('end_date'));
            }

            if (request()->filled('status')) {
                $query->where('status', '=', request('status'));
            }

            // Pagination
            $perPage = request()->get('per_page', 10);

            $activities = $query->paginate($perPage);

            // Format paginated collection
            $activities->getCollection()->transform(function ($activity) {
                return [
                    'user_name'   => $activity->user?->name,
                    'login_time'  => $activity->login_time?->format('d-m-Y H:i:s'),
                    'logout_time' => $activity->logout_time?->format('d-m-Y H:i:s'),
                    'status'      => $activity->status,
                ];
            });

            return ResponseHelper::success(
                $activities,
                'User activities fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve user activities.'
                
            );
        }
    }
}