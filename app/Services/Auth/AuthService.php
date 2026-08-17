<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use DB;

class AuthService
{
    public function login(array $data): array
    {
        $user = User::with([
            'roles' => function ($query) {
                $query->where('is_active', true);
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

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values(),
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
            $user->currentAccessToken()?->delete();
        } catch (Throwable $e) {
            throw $e;
        }
    }
}