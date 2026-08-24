<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Http\Requests\User\ProcessRecordRequest;
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
}