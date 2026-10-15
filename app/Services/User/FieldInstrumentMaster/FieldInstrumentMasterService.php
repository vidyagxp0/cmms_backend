<?php

namespace App\Services\User\FieldInstrumentMaster;

use App\Helpers\ResponseHelper;
use App\Helpers\UserAuditHelper;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Http\Requests\User\RecordActivityRequest;
use App\Models\Activity;
use App\Models\FieldInstrumentMaster;
use App\Models\ProcessRecord;
use App\Models\RecordActivityHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FieldInstrumentMasterService
{
    /* field instrument fields */
    private static function instrumentFields(): array
    {
        return [
            'equipment_name',
            'instrument_name',
            'instrument_id_no',
            'make',
            'sr_no_model_no',
            'location',
            'least_count',
            'accuracy',
            'instrument_range',
            'operating_range',
            'calibration_test_points',
            'category',
            'checklist_config',
            'stage_id',
            'process_id'
        ];
    }

    /* field instrument listing */
    public static function getEquipments(EquipmentMasterRequest $request)
    {
        try {
            $equipments = FieldInstrumentMaster::query()
                ->when($request->search, function ($query) use ($request) {
                    $search = $request->search;

                    $query->where(function ($query) use ($search) {
                        $query->where(
                            'instrument_name',
                            'like',
                            "%{$search}%"
                        )
                            ->orWhere(
                                'instrument_id_no',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'make',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'sr_no_model_no',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'category',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'location',
                                'like',
                                "%{$search}%"
                            );
                    });
                })
                ->when($request->category, function ($query) use ($request) {
                    $query->where('category', $request->category);
                })
                ->orderByDesc('id')
                ->paginate($request->per_page ?? 10);

            return ResponseHelper::success(
                $equipments,
                'Field instruments fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve field instruments.',
                500
            );
        }
    }

    /* get field instrument details */
    public static function getEquipmentById($id)
    {
        try {
            $equipment = FieldInstrumentMaster::findOrFail($id);

            return ResponseHelper::success(
                $equipment,
                'Field instrument fetched successfully.'
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::error(
                'Field instrument not found.',
                404
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve field instrument.',
                500
            );
        }
    }

    /* store field instrument */
    public static function storeEquipment(EquipmentMasterRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->only(self::instrumentFields());

            $equipment = FieldInstrumentMaster::create($data);

            /* audit data */
            $newValue = $equipment->only(self::instrumentFields());

            UserAuditHelper::log(
                'Field Instrument Master',
                'Created',
                'Field instrument created successfully.',
                $equipment->id,
                null,
                $newValue,
                FieldInstrumentMaster::class
            );

            DB::commit();

            return ResponseHelper::success(
                $equipment,
                'Field instrument created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                'Failed to create field instrument.',
                500
            );
        }
    }

    /* update field instrument */
    public static function updateEquipment(
        EquipmentMasterRequest $request,
        $id
    ) {
        DB::beginTransaction();

        try {
            $equipment = FieldInstrumentMaster::findOrFail($id);

            $updateData = $request->only(self::instrumentFields());

            $oldValue = [];
            $newValue = [];
            $changedData = [];

            /* identify changed fields */
            foreach ($updateData as $field => $value) {
                $currentValue = $equipment->getAttribute($field);

                /* compare checklist configuration */
                if ($field === 'checklist_config') {
                    if (is_string($value)) {
                        $value = json_decode($value, true);
                    }

                    if ($value == $currentValue) {
                        continue;
                    }
                } elseif ((string) $value === (string) $currentValue) {
                    continue;
                }

                $oldValue[$field] = $currentValue;
                $newValue[$field] = $value;
                $changedData[$field] = $value;
            }

            /* update only changed fields */
            if (!empty($changedData)) {
                $equipment->update($changedData);

                /* audit changed fields only */
                UserAuditHelper::log(
                    'Field Instrument Master',
                    'Updated',
                    'Field instrument updated successfully.',
                    $equipment->id,
                    $oldValue,
                    $newValue,
                    FieldInstrumentMaster::class
                );
            }

            DB::commit();

            return ResponseHelper::success(
                $equipment->fresh(),
                'Field instrument updated successfully.'
            );
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return ResponseHelper::error(
                'Field instrument not found.',
                404
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                'Failed to update field instrument.',
                500
            );
        }
    }

    /* get all field instruments */
    public static function getAllEquipments()
    {
        try {
            $equipments = FieldInstrumentMaster::orderBy('id', 'asc')->get();

            return ResponseHelper::success(
                $equipments,
                'Field instruments fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve field instruments.',
                500
            );
        }
    }

    /* get field instrument checklist by frequency */
    public static function getChecklistByFrequency($equipmentId, $frequency)
    {
        try {
            $equipment = FieldInstrumentMaster::find($equipmentId);

            if (!$equipment) {
                return ResponseHelper::error(
                    'Field instrument not found.',
                    404
                );
            }

            $allowedFrequencies = [
                'quarterly' => ['quarterly'],
                'half-yearly' => ['quarterly', 'half yearly'],
                'yearly' => ['quarterly', 'half yearly', 'yearly'],
            ];

            $selectedFrequency = strtolower(trim($frequency));

            if (!isset($allowedFrequencies[$selectedFrequency])) {
                return ResponseHelper::error(
                    'Invalid frequency. Allowed values are Quarterly, Half Yearly and Yearly.',
                    422
                );
            }

            $checklistConfig = $equipment->checklist_config;

            if (is_string($checklistConfig)) {
                $checklistConfig = json_decode($checklistConfig, true);
            }

            if (!is_array($checklistConfig)) {
                return ResponseHelper::error(
                    'Checklist configuration not found.',
                    404
                );
            }

            $frequencies = $allowedFrequencies[$selectedFrequency];

            $questions = array_values(array_filter(
                $checklistConfig['questions'] ?? [],
                function ($question) use ($frequencies) {
                    $questionFrequency = strtolower(
                        trim($question['frequency'] ?? '')
                    );

                    return in_array(
                        $questionFrequency,
                        $frequencies,
                        true
                    );
                }
            ));

            $checklistConfig['questions'] = $questions;

            return ResponseHelper::success(
                $checklistConfig,
                'Field instrument checklist fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve field instrument checklist.',
                500
            );
        }
    }

    /* get field instrument audit trail */
    public static function getInstrumentAuditTrail($id)
    {
        try {
            $instrument = FieldInstrumentMaster::find($id);

            if (!$instrument) {
                return ResponseHelper::error(
                    'Field instrument not found.',
                    404
                );
            }

            $auditTrail = DB::table('audits')
                ->where('module', 'Field Instrument Master')
                ->where('record_id', $instrument->id)
                ->where('model', FieldInstrumentMaster::class)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->select([
                    'id',
                    'user_id',
                    'old_value',
                    'new_value',
                    'module',
                    'action',
                    'description',
                    'model',
                    'record_id',
                    'created_at',
                ])
                ->get()
                ->map(function ($audit) {
                    /* decode old values */
                    if (is_string($audit->old_value)) {
                        $decoded = json_decode($audit->old_value, true);

                        if (json_last_error() === JSON_ERROR_NONE) {
                            $audit->old_value = $decoded;
                        }
                    }

                    /* decode new values */
                    if (is_string($audit->new_value)) {
                        $decoded = json_decode($audit->new_value, true);

                        if (json_last_error() === JSON_ERROR_NONE) {
                            $audit->new_value = $decoded;
                        }
                    }

                    return $audit;
                });

            return ResponseHelper::success(
                $auditTrail,
                'Field instrument audit trail fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve field instrument audit trail.',
                500
            );
        }
    }

    
    /* move Field Instrument Master process record stage */
    public static function moveStage(RecordActivityRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $processRecord = FieldInstrumentMaster::with([
                'process',
                'stage',
                'department',
                'initiator',
            ])->findOrFail($id);

            /* verify process record belongs to Field Instrument Master */
            if (
                !$processRecord->process ||
                $processRecord->process->name !== 'Field Instrument Master'
            ) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Process record does not belong to Field Instrument Master.',
                    422
                );
            }

            /* get active activity for current stage */
            $activity = Activity::with([
                'fromStage',
                'toStage',
            ])
                ->where('id', $request->activity_id)
                ->where('is_active', true)
                ->where('from_stage', $processRecord->stage_id)
                ->first();

            if (!$activity) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Selected activity is not available for the current stage.',
                    422
                );
            }

            /* validate source stage */
            if (
                !$activity->fromStage ||
                $activity->fromStage->process_id != $processRecord->process_id
            ) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Selected activity does not belong to this process.',
                    422
                );
            }

            /* validate target stage */
            if (
                !$activity->toStage ||
                $activity->toStage->process_id != $processRecord->process_id
            ) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Invalid target stage for this process.',
                    422
                );
            }

            $currentStage = $processRecord->stage;
            $targetStage = $activity->toStage;

            /* verify user credentials */
            $user = User::find($request->user_id);

            if (
                !$user ||
                $user->email !== $request->email ||
                !Hash::check($request->password, $user->password)
            ) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Invalid email or password.',
                    422
                );
            }

            /* record activity history */
            RecordActivityHistory::create([
                'field_instrument_id' => $processRecord->id,
                'activity_id' => $activity->id,
                'performed_by' => Auth::id(),
                'stage_id' => $currentStage->id,
                'target_stage' => $targetStage->id,
                'comment' => $request->comment,
                'performed_at' => now(),
            ]);

            /* update process record stage */
            $processRecord->stage_id = $targetStage->id;
            $processRecord->save();

            /* record audit trail */
            $description = $request->filled('comment')
                ? $request->comment
                : 'Process record stage updated successfully.';

            UserAuditHelper::log(
                'Process Record',
                'Activity Performed',
                $description,
                $processRecord->id,
                [
                    'stage' => $currentStage?->name,
                ],
                [
                    'stage' => $targetStage?->name,
                ],
                FieldInstrumentMaster::class
            );

            DB::commit();

            /* return updated process record */
            $processRecord->load([
                'process',
                'stage',
                'department',
                'initiator',
            ]);

            return ResponseHelper::success([
                'record' => $processRecord,
                'activity' => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'from_stage' => $currentStage->name,
                    'to_stage' => $targetStage->name,
                ],
            ], 'Process record stage updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                'Failed to update Field Instrument Master stage.',
                500
            );
        }
    }

    /* check whether the logged-in user's role can act on a record's current stage */
    public static function checkFieldInstrumentRecordPermission($id)
    {
        try {
            $user = Auth::user();
            if (!$user) return ResponseHelper::error('Unauthenticated.', 401);

            $record = FieldInstrumentMaster::with(['stage', 'process'])->findOrFail($id);
            $roles = $user->roles()->pluck('name')->toArray();

            /* stage => required role, per process id */
            $stageRoles = [
                22 => [ // Instrument Master
                    'Opened' => 'Initiator',
                    'HOD Review' => 'HOD/Designee',
                    'QA Approval' => 'QA Approver',
                ],
            ];

            $stageName = $record->stage?->name;
            $allowedRole = $stageRoles[$record->process_id][$stageName] ?? null;
            $canPerformAction = $allowedRole ? in_array($allowedRole, $roles) : false;

            return ResponseHelper::success([
                'record_id' => $record->id,
                'process' => ['id' => $record->process?->id, 'name' => $record->process?->name],
                'stage' => ['id' => $record->stage?->id, 'name' => $stageName],
                'user' => ['id' => $user->id, 'name' => $user->name, 'roles' => $roles],
                'permission' => ['allowed_role' => $allowedRole, 'can_perform_action' => $canPerformAction],
            ], 'Record permission checked successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

}
