<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Helpers\UserAuditHelper;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Models\InstrumentMaster;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\User\RecordActivityRequest;
use App\Models\ProcessRecord;
use App\Models\Activity;
use App\Models\RecordActivityHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EquipmentMasterService
{
    /* instrument fields */
    private static function instrumentFields(): array
    {
        return [
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
        ];
    }

    /* equipment listing */
    public static function getEquipments(EquipmentMasterRequest $request)
    {
        try {
            $equipments = InstrumentMaster::query()

                ->when($request->search, function ($query) use ($request) {
                    $search = $request->search;

                    $query->where(function ($query) use ($search) {
                        $query->where('instrument_name', 'like', "%{$search}%")
                            ->orWhere('instrument_id_no', 'like', "%{$search}%")
                            ->orWhere('make', 'like', "%{$search}%")
                            ->orWhere('sr_no_model_no', 'like', "%{$search}%")
                            ->orWhere('category', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%");
                    });
                })

                ->when($request->category, function ($query) use ($request) {
                    $query->where('category', $request->category);
                })

                ->orderBy('id', 'desc')
                ->paginate($request->per_page ?? 10);

            return ResponseHelper::success(
                $equipments,
                'Instrument fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve Instrument.',
                500
            );
        }
    }

    /* get equipment details */
    public static function getEquipmentById($id)
    {
        try {
            $equipment = InstrumentMaster::findOrFail($id);

            return ResponseHelper::success(
                $equipment,
                'Instrument fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Equipment not found.',
                404
            );
        }
    }

    /* store equipment */
    public static function storeEquipment(EquipmentMasterRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->only(self::instrumentFields());

            $equipment = InstrumentMaster::create($data);

            /* audit data */
            $newValue = $equipment->only(self::instrumentFields());

            UserAuditHelper::log(
                'Instrument Master',
                'Created',
                'Instrument created successfully.',
                $equipment->id,
                null,
                $newValue,
                InstrumentMaster::class
            );

            DB::commit();

            return ResponseHelper::success(
                $equipment,
                'Instrument created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                $e->getMessage(),
                500
            );
        }
    }

    /* update Instrument */
    public static function updateEquipment(
        EquipmentMasterRequest $request,
        $id
    ) {
        DB::beginTransaction();

        try {
            $equipment = InstrumentMaster::findOrFail($id);

            $updateData = $request->only(self::instrumentFields());

            $oldValue = [];
            $newValue = [];
            $changedData = [];

            /* identify changed fields */
            foreach ($updateData as $field => $value) {
                $currentValue = $equipment->getAttribute($field);

                /* compare nested JSON and scalar values */
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

                /* audit only changed fields */
                UserAuditHelper::log(
                    'Instrument Master',
                    'Updated',
                    'Instrument updated successfully.',
                    $equipment->id,
                    $oldValue,
                    $newValue,
                    InstrumentMaster::class
                );
            }

            DB::commit();

            return ResponseHelper::success(
                $equipment->fresh(),
                'Instrument updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return ResponseHelper::error(
                    'Instrument not found.',
                    404
                );
            }

            return ResponseHelper::error(
                'Failed to update Instrument.',
                500
            );
        }
    }

    /* get all Instruments */
    public static function getAllEquipments()
    {
        try {
            $equipments = InstrumentMaster::orderBy('id', 'asc')->get();

            return ResponseHelper::success(
                $equipments,
                'Instrument fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve Instrument.',
                500
            );
        }
    }

    /* get Instrument Master checklist by frequency */
    public static function getChecklistByFrequency($equipmentId, $frequency)
    {
        try {
            $equipment = InstrumentMaster::find($equipmentId);

            if (!$equipment) {
                return ResponseHelper::error(
                    'Instrument not found.',
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
                'Checklist fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve Instrument checklist.',
                500
            );
        }
    }

    
    /* get instrument audit trail */
    public static function getInstrumentAuditTrail($id)
    {
        try {
            $instrument = InstrumentMaster::find($id);

            if (!$instrument) {
                return ResponseHelper::error(
                    'Instrument not found.',
                    404
                );
            }

            $auditTrail = DB::table('audits')
                ->where('module', 'Instrument Master')
                ->where('record_id', $instrument->id)
                ->where('model', InstrumentMaster::class)
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
                'Instrument audit trail fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve instrument audit trail.',
                500
            );
        }
    }

    
    /* move Instrument Master process record stage */
    public static function moveStage(RecordActivityRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $processRecord = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
            ])->findOrFail($id);

            /* verify process record belongs to Instrument Master */
            if (
                !$processRecord->process ||
                $processRecord->process->name !== 'Instrument Master'
            ) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Process record does not belong to Instrument Master.',
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
                'process_record_id' => $processRecord->id,
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
                ProcessRecord::class
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
                'Failed to update Instrument Master stage.',
                500
            );
        }
    }


}
