<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Process;
use App\Models\Stage;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        /* Calibration Planner */
        $this->createActivities(
            'Calibration Planner',
            [
                [
                    'from' => 'Opened',
                    'to' => 'Reviewed By HOD/Designee (Engineering)',
                    'name' => 'Submit',
                ],
                [
                    'from' => 'Reviewed By HOD/Designee (Engineering)',
                    'to' => 'Reviewed By User Department',
                    'name' => 'HOD Review Complete',
                ],
                [
                    'from' => 'Reviewed By User Department',
                    'to' => 'Pending QA Approval',
                    'name' => 'User Department Review Complete',
                ],
                [
                    'from' => 'Pending QA Approval',
                    'to' => 'Closed - Done',
                    'name' => 'QA Approval Complete',
                ],
            ],
            [
                [
                    'from' => 'Reviewed By HOD/Designee (Engineering)',
                    'to' => 'Opened',
                    'name' => 'More Info Required',
                ],
                [
                    'from' => 'Reviewed By User Department',
                    'to' => 'Reviewed By HOD/Designee (Engineering)',
                    'name' => 'More Info Required',
                ],
                [
                    'from' => 'Pending QA Approval',
                    'to' => 'Reviewed By User Department',
                    'name' => 'More Info Required',
                ],
            ]
        );

        /* Preventive Maintenance Planner */
        $this->createActivities(
            'Preventive Maintenance Planner',
            [
                [
                    'from' => 'Opened',
                    'to' => 'Pending HOD/Designee Review',
                    'name' => 'Submit',
                ],
                [
                    'from' => 'Pending HOD/Designee Review',
                    'to' => 'Pending QA Review',
                    'name' => 'HOD Review Complete',
                ],
                [
                    'from' => 'Pending QA Review',
                    'to' => 'Pending QA Approval',
                    'name' => 'QA Review Complete',
                ],
                [
                    'from' => 'Pending QA Approval',
                    'to' => 'Closed - Done',
                    'name' => 'QA Approval Complete',
                ],
            ],
            [
                [
                    'from' => 'Pending HOD/Designee Review',
                    'to' => 'Opened',
                    'name' => 'More Info Required',
                ],
                [
                    'from' => 'Pending QA Review',
                    'to' => 'Pending HOD/Designee Review',
                    'name' => 'More Info Required',
                ],
                [
                    'from' => 'Pending QA Approval',
                    'to' => 'Pending QA Review',
                    'name' => 'More Info Required',
                ],
            ]
        );

        /* Preventive Maintenance */
        $this->createActivities(
            'Preventive Maintenance',
            [
                [
                    'from' => 'Opened',
                    'to' => 'Engineering Department Review',
                    'name' => 'Start Maintenance',
                ],
                [
                    'from' => 'Engineering Department Review',
                    'to' => 'Pending QA Approval',
                    'name' => 'Engineering Review Complete',
                ],
                [
                    'from' => 'Pending QA Approval',
                    'to' => 'Closed - Done',
                    'name' => 'QA Approval Complete',
                ],
            ],
            [
                [
                    'from' => 'Engineering Department Review',
                    'to' => 'Opened',
                    'name' => 'More Info Required',
                ],
                [
                    'from' => 'Pending QA Approval',
                    'to' => 'Engineering Department Review',
                    'name' => 'More Info Required',
                ],
            ]
        );

        /* Calibration Management */
        $this->createActivities(
            'Calibration Management',
            [
                [
                    'from' => 'Opened',
                    'to' => 'Reviewed By HOD/Designee',
                    'name' => 'Start Calibration',
                ],
                [
                    'from' => 'Reviewed By HOD/Designee',
                    'to' => 'Verified By QA',
                    'name' => 'HOD Review Complete',
                ],
                [
                    'from' => 'Verified By QA',
                    'to' => 'Closed - Done',
                    'name' => 'QA Verification Complete',
                ],
            ],
            [
                [
                    'from' => 'Reviewed By HOD/Designee',
                    'to' => 'Opened',
                    'name' => 'More Info Required',
                ],
                [
                    'from' => 'Verified By QA',
                    'to' => 'Reviewed By HOD/Designee',
                    'name' => 'More Info Required',
                ],
            ]
        );
    }

    /* Create Activities */
    private function createActivities(
        string $processName,
        array $forwardActivities,
        array $backwardActivities
    ): void {
        $process = Process::where('name', $processName)->first();

        if (!$process) {
            return;
        }

        /* create activity based on the stages */
        foreach ($forwardActivities as $forwardActivity) {
            $fromStage = Stage::where('process_id', $process->id)
                ->where('name', $forwardActivity['from'])
                ->first();

            $toStage = Stage::where('process_id', $process->id)
                ->where('name', $forwardActivity['to'])
                ->first();

            if (!$fromStage || !$toStage) {
                continue;
            }

            /* forward activity */
            Activity::create([
                'name' => $forwardActivity['name'],
                'from_stage' => $fromStage->id,
                'to_stage' => $toStage->id,
                'is_active' => true,
                'assigned_role' => null,
            ]);

            /* cancel activity */
            if ($fromStage->name === 'Opened') {
                $cancelledStage = Stage::where('process_id', $process->id)
                    ->where('name', 'Close - Cancelled')
                    ->first();

                if ($cancelledStage) {
                    Activity::create([
                        'name' => 'Cancel',
                        'from_stage' => $fromStage->id,
                        'to_stage' => $cancelledStage->id,
                        'is_active' => true,
                        'assigned_role' => null,
                    ]);
                }
            }

            /* backward activity */
            foreach ($backwardActivities as $backwardActivity) {
                if ($backwardActivity['from'] !== $forwardActivity['to']) {
                    continue;
                }

                $backwardFromStage = Stage::where('process_id', $process->id)
                    ->where('name', $backwardActivity['from'])
                    ->first();

                $backwardToStage = Stage::where('process_id', $process->id)
                    ->where('name', $backwardActivity['to'])
                    ->first();

                if (!$backwardFromStage || !$backwardToStage) {
                    continue;
                }

                Activity::create([
                    'name' => $backwardActivity['name'],
                    'from_stage' => $backwardFromStage->id,
                    'to_stage' => $backwardToStage->id,
                    'is_active' => true,
                    'assigned_role' => null,
                ]);

                break;
            }
        }
    }
}