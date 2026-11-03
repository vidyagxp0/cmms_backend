<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            'Admin',
            'ENG',
            'HRD',
            'MIC',
            'PDI',
            'PDO',
            'PP2',
            'PP3',
            'QAD',
            'QCD',
            'WHD',
        ];

        foreach ($departments as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}