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
                'Reviewed By HOD/Designee (Engineering)',
                'Reviewed By User Department',
                'Pending QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],

            'Preventive Maintenance Planner' => [
                'Opened',
                'Pending HOD/Designee Review',
                'Pending QA Review',
                'Pending QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],

            'Preventive Maintenance' => [
                'Opened',
                'Engineering Department Review',
                'Pending QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],

            'Calibration Management' => [
                'Opened',
                'Reviewed By HOD/Designee',
                'Verified By QA',
                'Closed - Done',
                'Closed - Cancelled',
            ],

            'Instrument Master' => [
                'Opened',
                'HOD Review',
                'QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],

            'Field Instrument Master' => [
                'Opened',
                'HOD Review',
                'QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],
            
            'Field Instrument Planner' => [
                'Opened',
                'Reviewed By HOD/Designee (Engineering)',
                'Reviewed By User Department',
                'Pending QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],

            'Field Instrument Calibration Addendum' => [
                'Opened',
                'Reviewed By HOD/Designee (Engineering)',
                'Reviewed By User Department',
                'Pending QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],

            'Spare Instrument Master' => [
                'Opened',
                'HOD Review',
                'QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],

            'Equipment Master' => [
                'Opened',
                'HOD Review',
                'QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],

            'Preventive Addendum' => [
                'Opened',
                'Reviewed By HOD/Designee (Engineering)',
                'Pending QA Approval',
                'Closed - Done',
                'Closed - Cancelled',
            ],
        ];

        foreach ($stageData as $processName => $stages) {
            $processId = $processes[$processName] ?? null;

            if (!$processId) {
                continue;
            }

            foreach ($stages as $stageName) {
                Stage::firstOrCreate(
                    [
                        'process_id' => $processId,
                        'name' => $stageName,
                    ],
                    [
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
