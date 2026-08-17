<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@gmail.com')->first();

        $user = User::where('email', 'user@gmail.com')->first();

        $adminRole = Role::where('name', 'admin')->first();

        $userRole = Role::where('name', 'user')->first();

        if ($admin && $adminRole) {
            $admin->roles()->syncWithoutDetaching([
                $adminRole->id,
            ]);
        }

        if ($user && $userRole) {
            $user->roles()->syncWithoutDetaching([
                $userRole->id,
            ]);
        }
    }
}