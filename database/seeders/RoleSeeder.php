<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Department;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        /* Get Admin department */
        $department = Department::where('name', 'Admin')->first();

        if (!$department) {
            $this->command->error('Admin department not found.');
            return;
        }

        $roles = [
            'Admin',
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                [
                    'name' => $role,
                ],
                [
                    'department_id' => $department->id,
                    'is_active' => true,
                ]
            );
        }
    }
}