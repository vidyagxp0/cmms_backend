<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class CalibrationReportService
{
    /* generate calibration report */
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
            ])->findOrFail($id);

            /* Decode process data if it is stored as JSON */
            $processData = $record->process_data;

            if (is_string($processData)) {
                $processData = json_decode($processData, true);
            }

            if (!is_array($processData)) {
                $processData = [];
            }

            /* report data */
            $data = [
                'header' => [
                    'company_name' => config('app.name'),
                    'report_title' => $record->process?->name ?? 'Process Report',
                ],

                'record' => [
                    'record_id' => $record->id,
                    'process' => $record->process?->name,
                    'stage' => $record->stage?->name,
                    'department' => $record->department?->name,
                    'initiator' => $record->initiator?->name,
                    'short_description' => $record->short_description,
                    'initiation_date' => $record->initiation_date
                        ? \Carbon\Carbon::parse($record->initiation_date)
                            ->format('d-m-Y')
                        : null,
                ],

                'fields' => $processData,

                'footer' => [
                    'generated_by' => auth()->user()?->name,
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            /* load view file */
            $pdf = Pdf::loadView(
                'Reports.User.CalibrationSingleReport',
                $data
            );

            $pdf->setPaper('a4', 'portrait');
            return $pdf->stream(
                'process-record-' . $record->id . '.pdf'
            );

        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to generate process report.',
                500
            );
        }
    }
}