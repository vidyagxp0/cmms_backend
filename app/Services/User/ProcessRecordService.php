<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Http\Requests\User\ProcessRecordRequest;
use App\Models\Process;
use App\Models\ProcessRecord;
use Illuminate\Http\Request;

class ProcessRecordService
{
    /* all process records */
    public static function getEngineeringRecords(Request $request) 
    {
        try {
            $records = ProcessRecord::with([
                'process',
                'department',
                'initiator',
                'stage',
            ])
            ->when($request->process_id, function ($query) use ($request) {
                $query->where(
                    'process_id',
                    $request->process_id
                );
            })
            ->when($request->search, function ($query) use ($request) {

                /* search functionality */
                $search = $request->search;

                $query->where(function ($query) use ($search) {

                    $query->where('short_description', 'like', "%{$search}%")

                        ->orWhereHas('process', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        })

                        ->orWhereHas('initiator', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        })

                        ->orWhereHas('department', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })

            /* date filters */
            ->when($request->from_date, function ($query) use ($request) {
                $query->whereDate(
                    'initiation_date',
                    '>=',
                    $request->from_date
                );
            })
            ->when($request->to_date, function ($query) use ($request) {
                $query->whereDate(
                    'initiation_date',
                    '<=',
                    $request->to_date
                );
            })
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 10);

            return ResponseHelper::success(
                $records,
                'Process records fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                $e->getMessage(),
                500
            );
        }
    }

    /* Get raw generated record number string */
    public static function getGeneratedRecordNumber($processId)
    {
        $process = Process::findOrFail($processId);
        
        // Generate prefix using first letter of each word (e.g., "Calibration Planner" -> "CP")
        $words = explode(' ', trim($process->name));
        if (count($words) > 1) {
            $prefix = '';
            foreach ($words as $word) {
                if (!empty($word)) {
                    $prefix .= substr($word, 0, 1);
                }
            }
            $prefix = strtoupper($prefix);
        } else {
            $prefix = strtoupper(substr($process->name, 0, 3));
        }
        
        $year = date('y');
        
        $count = ProcessRecord::where('process_id', $processId)
            ->whereYear('created_at', date('Y'))
            ->count();
        
        $nextNumber = $count + 1;
        $sequence = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        
        return "{$prefix}/{$year}/{$sequence}";
    }

    /* generate dynamic record number */
    public static function generateRecordNumber($processId)
    {
        try {
            $recordNumber = self::getGeneratedRecordNumber($processId);
            
            return ResponseHelper::success(
                ['record_number' => $recordNumber],
                'Record number generated successfully.'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error(
                'Process not found.',
                404
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to generate record number.',
                500
            );
        }
    }
}