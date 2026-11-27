<?php

namespace App\Services\User\Dashboard;

use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getDashboardCounts()
    {
        $instrumentMasters = [
            'master_instruments' => DB::table('instrument_masters')->get()->count(),
            'field_instruments' => DB::table('field_instrument_masters')->get()->count(),
            'spare_instruments' => DB::table('spare_instrument_masters')->get()->count(),
            'equipment_master' => DB::table('equipment_masters')->get()->count(),
        ];

        $processNames = [
            'Calibration Planner',
            'Preventive Maintenance Planner',
            'Preventive Maintenance',
            'Calibration Management',
            'Field Instrument Calibration Addendum',
        ];

        $processes = DB::table('processes')
            ->whereIn('name', $processNames)
            ->get();

        $processCounts = [];

        $processStageIds = $this->getProcessStageIds();

        foreach ($processes as $process) {
            $records = DB::table('process_records')
                ->where('process_id', $process->id)
                ->get();

            $stageIds = $processStageIds[$process->name] ?? [
                'Opened' => [],
                'Closed - Done' => [],
            ];

            $total = $records->count();
            $Opened = 0;
            $closed = 0;

            foreach ($records as $record) {
                if (in_array($record->stage_id, $stageIds['Opened'])) {
                    $Opened++;
                } elseif (in_array($record->stage_id, $stageIds['Closed - Done'])) {
                    $closed++;
                }
            }

            $processCounts[] = [
                'process_id' => $process->id,
                'process_name' => $process->name,
                'total_records' => $total,
                'Opened_records' => $Opened,
                'Closed - Done_records' => $closed,
            ];
        }

        return [
            'instrument_masters' => $instrumentMasters,
            'process_records' => $processCounts,
        ];
    }

    private function getProcessStageIds()
    {
        return [
            'Calibration Planner' => [
                'Opened' => [1],
                'Closed - Done' => [5],
            ],

            'Preventive Maintenance Planner' => [
                'Opened' => [7],
                'Closed - Done' => [11],
            ],

            'Preventive Maintenance' => [
                'Opened' => [13],
                'Closed - Done' => [17],
            ],

            'Calibration Management' => [
                'Opened' => [19],
                'Closed - Done' => [22],
            ],

            'Field Instrument Calibration Addendum' => [
                'Opened' => [40],
                'Closed - Done' => [44],
            ],
        ];
    }
}