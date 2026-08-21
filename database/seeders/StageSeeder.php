<?php

namespace Database\Seeders;

use App\Models\Process;
use App\Models\Stage;
use Illuminate\Database\Seeder;

class StageSeeder extends Seeder
{
    public function run(): void
    {
        $processes = Process::pluck('id', 'name');

        $stageData = [

            'Calibration Planner' => [
                'Opened',
                'Pending HOD/Designee Review',
                'Pending QA Review',
                'Pending QA Approval',
                'Closed - Done',
                'Close - Cancelled',
            ],

            'Preventive Maintenance Planner' => [
                'Opened',
                'Pending Approval',
                'Pending QA Approval',
                'Pending Preventive Maintenance Execution',
                'Closed - Done',
                'Close - Cancelled',
            ],

            'Preventive Maintenance' => [
                'Opened',
                'Preventive In Progress',
                'Pending Out of Limit',
                'Pending QA Approval',
                'Closed - Done',
                'Close - Cancelled',
            ],

            'Calibration Management' => [
                'Opened',
                'Calibration In Progress',
                'Pending Out of Actions',
                'Pending QA Approval',
                'Closed - Done',
                'Close - Cancelled',
            ],
        ];

        foreach ($stageData as $processName => $stages) {

            $processId = $processes[$processName];

            foreach ($stages as $stageName) {

                Stage::create([
                    'process_id' => $processId,
                    'name' => $stageName,
                    'is_active' => true,
                ]);
            }
        }
    }
}