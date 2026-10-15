<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Helpers\UserAuditHelper;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Models\FieldInstrumentMaster;
use App\Models\InstrumentMaster;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\User\RecordActivityRequest;
use App\Models\Activity;
use App\Models\RecordActivityHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
            'stage_id',
            'process_id',
            'hod_review_comments',
            'qa_review_comments'
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
                ->with('stage', 'process', 'initiator')
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

    /* Fields that should never appear in the audit table */
    private const AUDIT_HIDDEN_FIELDS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'created_by',
        'updated_by',
        'password',
    ];

    /* audit fields label */
    private const AUDIT_FIELD_LABELS = [
        'instrument_id_no'        => 'Instrument ID No.',
        'sr_no_model_no'          => 'Sr. No. / Model No.',
        'checklist_config'        => 'Checklist Configuration',
        'calibration_test_points' => 'Calibration Test Points',
    ];

    /* get instrument audit trail */
    public static function getInstrumentAuditTrail(Request $request, $id)
    {
        try {
            $instrument = InstrumentMaster::find($id);

            if (!$instrument) {
                return ResponseHelper::error('Instrument not found.', 404);
            }

            $page = max((int) $request->query('page', 1), 1);
            $perPage = min(max((int) $request->query('per_page', 10), 1), 100);
            $search = trim((string) $request->query('search', ''));
            $fromDate = $request->query('from_date');
            $toDate = $request->query('to_date');

            /* validate date filters */
            foreach ([
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ] as $field => $date) {
                if ($date !== null && $date !== '') {
                    $parsedDate = \DateTime::createFromFormat('!Y-m-d', $date);

                    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
                        return ResponseHelper::error(
                            "Invalid {$field}. Expected YYYY-MM-DD.",
                            422
                        );
                    }
                }
            }

            if ($fromDate && $toDate && $fromDate > $toDate) {
                return ResponseHelper::error(
                    'From date cannot be later than To date.',
                    422
                );
            }

            /*
            * Fetch normal Instrument Master audits and
            * Activity Performed audits for this instrument.
            */
            $query = DB::table('audits')
                ->where('record_id', $instrument->id)
                ->where(function ($query) {
                    $query->where(function ($normalQuery) {
                        $normalQuery
                            ->where('module', 'Instrument Master')
                            ->where('model', InstrumentMaster::class);
                    })->orWhere(function ($activityQuery) {
                        $activityQuery
                            ->where('module', 'Process Record')
                            ->where('action', 'Activity Performed')
                            ->where('model', InstrumentMaster::class);
                    });
                });

            if ($fromDate) {
                $query->whereDate('created_at', '>=', $fromDate);
            }

            if ($toDate) {
                $query->whereDate('created_at', '<=', $toDate);
            }

            $audits = $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->select([
                    'id',
                    'user_id',
                    'old_value',
                    'new_value',
                    'action',
                    'description',
                    'created_at',
                ])
                ->get();

            /* fetch usernames */
            $userIds = $audits
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->values();

            $userNames = $userIds->isEmpty()
                ? collect()
                : DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('name', 'id');

            $rows = collect();

            /* convert audits into display rows */
            foreach ($audits as $audit) {
                $old = self::decodeAuditValue($audit->old_value);
                $new = self::decodeAuditValue($audit->new_value);

                $action = ucfirst(strtolower((string) $audit->action));
                $user = $userNames[$audit->user_id] ?? '-';
                $date = Carbon::parse($audit->created_at)->format('d-m-Y H:i:s');

                /* handle audits without field-level values */
                if (!is_array($old) && !is_array($new)) {
                    $rows->push([
                        'id' => $audit->id . '-record',
                        'audit_id' => $audit->id,
                        'action' => $action,
                        'field' => 'Record',
                        'old_value' => self::formatAuditValue($old),
                        'new_value' => self::formatAuditValue($new),
                        'remarks' => $audit->description,
                        'performed_by' => $user,
                        'performed_at' => $date,
                    ]);

                    continue;
                }

                $old = is_array($old) ? $old : [];
                $new = is_array($new) ? $new : [];

                $keys = array_unique(array_merge(
                    array_keys($old),
                    array_keys($new)
                ));

                foreach ($keys as $key) {
                    if (in_array($key, self::AUDIT_HIDDEN_FIELDS, true)) {
                        continue;
                    }

                    $oldRaw = $old[$key] ?? null;
                    $newRaw = $new[$key] ?? null;

                    /* skip unchanged values */
                    if (json_encode($oldRaw) === json_encode($newRaw)) {
                        continue;
                    }

                    $oldText = self::formatAuditValue(
                        $oldRaw,
                        'Previous configuration'
                    );

                    $newText = self::formatAuditValue(
                        $newRaw,
                        strtolower($action) === 'created'
                            ? 'Configured'
                            : 'Updated configuration'
                    );

                    if ($oldText === null && $newText === null) {
                        continue;
                    }

                    $rows->push([
                        'id' => $audit->id . '-' . $key,
                        'audit_id' => $audit->id,
                        'action' => $action,
                        'field' => self::AUDIT_FIELD_LABELS[$key]
                            ?? Str::headline($key),
                        'old_value' => $oldText,
                        'new_value' => $newText,
                        'remarks' => $audit->description,
                        'performed_by' => $user,
                        'performed_at' => $date,
                    ]);
                }
            }

            /* search across displayed audit columns */
            if ($search !== '') {
                $needle = mb_strtolower($search);

                $rows = $rows->filter(function ($row) use ($needle) {
                    $text = implode(' ', array_map(
                        fn ($value) => (string) ($value ?? ''),
                        [
                            $row['action'] ?? '',
                            $row['field'] ?? '',
                            $row['old_value'] ?? '',
                            $row['new_value'] ?? '',
                            $row['remarks'] ?? '',
                            $row['performed_by'] ?? '',
                            $row['performed_at'] ?? '',
                            $row['audit_id'] ?? '',
                        ]
                    ));

                    return str_contains(mb_strtolower($text), $needle);
                })->values();
            }

            /* paginate after flattening and searching */
            $total = $rows->count();
            $lastPage = max((int) ceil($total / $perPage), 1);
            $page = min($page, $lastPage);

            $items = $rows
                ->slice(($page - 1) * $perPage, $perPage)
                ->values();

            return ResponseHelper::success([
                'meta' => [
                    'process' => 'Instrument Master',
                    'instrument_name' => $instrument->instrument_name,
                    'instrument_id_no' => $instrument->instrument_id_no,
                    'created_at' => $instrument->created_at
                        ? Carbon::parse($instrument->created_at)->format('d/m/Y H:i:s')
                        : null,
                ],
                'items' => $items,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
                    'to' => $total ? min($page * $perPage, $total) : 0,
                ],
            ], 'Instrument audit trail fetched successfully.');

        } catch (\Throwable $e) {
            \Log::error('INSTRUMENT AUDIT ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ResponseHelper::error(
                'Failed to retrieve instrument audit trail.',
                500
            );
        }
    }

    /* Decode JSON strings */
    private static function decodeAuditValue($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    /* format audit values */
    private static function formatAuditValue($value, string $complexLabel = 'Configured'): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            $text = trim((string) $value);
            return $text === '' ? null : $text;
        }

        if (is_array($value)) {
            if (empty($value)) {
                return null;
            }

            $isScalarList = array_is_list($value)
                && count(array_filter($value, 'is_scalar')) === count($value);

            if ($isScalarList) {
                $parts = array_filter(
                    array_map(fn ($v) => trim((string) $v), $value),
                    fn ($v) => $v !== ''
                );
                return $parts ? implode(', ', $parts) : null;
            }
            return $complexLabel;
        }
        return null;
    }

    
    /* move Instrument Master process record stage */
    public static function moveStage(RecordActivityRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $processRecord = InstrumentMaster::with([
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
                'instrument_id' => $processRecord->id,
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
                InstrumentMaster::class
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
                // 'Failed to update Instrument Master stage.',
                $e->getMessage(),
                500
            );
        }
    }

    /* check whether the logged-in user's role can act on a record's current stage */
    public static function checkInstrumentRecordPermission($id)
    {
        try {
            $user = Auth::user();
            if (!$user) return ResponseHelper::error('Unauthenticated.', 401);

            $record = InstrumentMaster::with(['stage', 'process'])->findOrFail($id);
            $roles = $user->roles()->pluck('name')->toArray();

            /* stage => required role, per process id */
            $stageRoles = [
                3 => [ // Instrument Master
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