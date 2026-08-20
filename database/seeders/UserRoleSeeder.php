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

        $adminRole = Role::where('name', 'admin')->first();

        if ($admin && $adminRole) {
            $admin->roles()->syncWithoutDetaching([
                $adminRole->id,
            ]);
        }
    }
}