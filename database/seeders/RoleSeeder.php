<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Department;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        /* Role name => Department name */
        $roles = [
            'Admin'                           => 'Admin',
            'Initiator'                           => 'ENG',
            'HOD/Designee'                           => 'ENG',
            'QA Approver'                           => 'ENG',
            'Engineering Department Role User'                           => 'ENG',
            'Engineering Department Reviewer' => 'ENG',
            'UserChecker/Reviewer'            => 'ENG',
        ];

        foreach ($roles as $roleName => $departmentName) {
            $department = Department::where('name', $departmentName)->first();

            if (!$department) {
                $this->command->error("{$departmentName} department not found. Skipping role: {$roleName}");
                continue;
            }

            Role::updateOrCreate(
                [
                    'name' => $roleName,
                ],
                [
                    'department_id' => $department->id,
                    'is_active'     => true,
                ]
            );
        }
    }
}