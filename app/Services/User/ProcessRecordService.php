<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Http\Requests\User\ProcessRecordRequest;
use App\Models\Process;
use App\Models\ProcessRecord;
use Auth;
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

    /* user record permissions function */
    public static function checkRecordPermission($id)
    {
        try {
            /* logged in user */
            $user = Auth::user();

            if (!$user) {
                return ResponseHelper::error(
                    'Unauthenticated.',
                    401
                );
            }

            /* get record with current stage */
            $record = ProcessRecord::with([
                'stage',
                'process',
            ])->findOrFail($id);

            /* get user roles */
            $roles = $user->roles()
                ->pluck('name')
                ->toArray();

            /* process wise stage allowed */
            $stageRoles = [

                /* Calibration Planner - Process ID 1 */
                1 => [
                    'Opened' => 'Initiator',
                    'Pending HOD/Designee Review' => 'HOD/Designee',
                    'Pending QA Review' => 'QA Reviewer',
                    'Pending QA Approval' => 'QA Approver',
                ],

                /* Calibration Management - Process ID 5 */
                5 => [
                    'Opened' => 'Initiator',
                    'Calibration In Progress' => 'HOD/Designee',
                    'Pending Out of Actions' => 'QA Reviewer',
                    'Pending QA Approval' => 'QA Approver',
                ],
            ];

            $processId = $record->process_id;
            $stageName = $record->stage?->name;

            /* get stage => role mapping for current process */
            $currentProcessStageRoles = $stageRoles[$processId] ?? [];

            /* get allowed role for current stage */
            $allowedRole = $currentProcessStageRoles[$stageName] ?? null;

            /* check permission */
            $canPerformAction = $allowedRole
                ? in_array($allowedRole, $roles)
                : false;

            return ResponseHelper::success(
                [
                    'record_id' => $record->id,

                    'process' => [
                        'id' => $record->process?->id,
                        'name' => $record->process?->name,
                    ],
                    'stage' => [
                        'id' => $record->stage?->id,
                        'name' => $stageName,
                    ],
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'roles' => $roles,
                    ],
                    'permission' => [
                        'allowed_role' => $allowedRole,
                        'can_perform_action' => $canPerformAction,
                    ],
                ],
                'Record permission checked successfully.'
            );

        } catch (\Exception $e) {
            return ResponseHelper::error(
                $e->getMessage(),
                500
            );
        }
    }
}