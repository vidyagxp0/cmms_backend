<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

    /* for logging out */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}