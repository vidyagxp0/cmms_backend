<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Department;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        /* Get Engineering department */
        $department = Department::where('name', 'Engineering')->first();

        if (!$department) {
            $this->command->error('Engineering department not found.');
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