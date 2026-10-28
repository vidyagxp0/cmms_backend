<?php

namespace App\Services\User\SpareInstrumentMaster;

use App\Helpers\ResponseHelper;
use App\Helpers\UserAuditHelper;
use App\Http\Requests\User\RecordActivityRequest;
use App\Models\Activity;
use App\Models\RecordActivityHistory;
use App\Models\SpareInstrumentMaster;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class SpareInstrumentMasterService
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
            'stage_id',
            'process_id',            
            'hod_review_comments',
            'qa_review_comments',
            'initiator_id'
        ];
    }

    /* field instrument listing */
    public static function getSpareInstruments(Request $request)
    {
        try {
            $equipments = SpareInstrumentMaster::query()
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
                                'location',
                                'like',
                                "%{$search}%"
                            );
                    });
                })
                ->when($request->category, function ($query) use ($request) {
                    $query->where('category', $request->category);
                })                
                ->with('stage', 'process', 'initiator')
                ->orderByDesc('id')
                ->paginate($request->per_page ?? 10);

            return ResponseHelper::success(
                $equipments,
                'Spare instruments fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve Spare instruments.',
                500
            );
        }
    }

    /* get Spare instrument details */
    public static function getSpareInstrumentById($id)
    {
        try {
            $equipment = SpareInstrumentMaster::findOrFail($id);

            return ResponseHelper::success(
                $equipment,
                'Spare instrument fetched successfully.'
            );
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::error(
                'Spare instrument not found.',
                404
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve Spare instrument.',
                500
            );
        }
    }

    /* store Spare instrument */
    public static function storeSpareInstrumentMaster(Request $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->only(self::instrumentFields());

            $equipment = SpareInstrumentMaster::create($data);

            /* audit data */
            $newValue = $equipment->only(self::instrumentFields());

            UserAuditHelper::log(
                'Spare Instrument Master',
                'Created',
                'Spare instrument created successfully.',
                $equipment->id,
                null,
                $newValue,
                SpareInstrumentMaster::class
            );

            DB::commit();

            return ResponseHelper::success(
                $equipment,
                'Spare instrument created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                // 'Failed to create Spare instrument.',
                $e->getMessage(),
                500
            );
        }
    }

    /* update Spare instrument */
    public static function updateSpareInstrumentMaster(
        Request $request,
        $id
    ) {
        DB::beginTransaction();

        try {
            $equipment = SpareInstrumentMaster::findOrFail($id);

            $updateData = $request->only(self::instrumentFields());

            $oldValue = [];
            $newValue = [];
            $changedData = [];

            /* identify changed Spare */
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
                    'Spare Instrument Master',
                    'Updated',
                    'Spare instrument updated successfully.',
                    $equipment->id,
                    $oldValue,
                    $newValue,
                    SpareInstrumentMaster::class
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
    public static function getAllSpareInstrument()
    {
        try {
            $equipments = SpareInstrumentMaster::where(function ($query) {
                    $query->where('is_imported', true)
                        ->orWhereHas('stage', function ($q) {
                            $q->where('name', 'Closed - Done');
                        });
                })
                ->orderBy('id', 'asc')
                ->get();

            return ResponseHelper::success(
                $equipments,
                'Spare instruments fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve Spare instruments.',
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

    /* Friendly labels where Str::headline() is not good enough */
    private const AUDIT_FIELD_LABELS = [
        'instrument_id_no'        => 'Instrument ID No.',
        'sr_no_model_no'          => 'Sr. No. / Model No.',
        'checklist_config'        => 'Checklist Configuration',
        'calibration_test_points' => 'Calibration Test Points',
    ];

    /* get field instrument audit trail */
    public static function getSpareInstrumentAuditTrail(Request $request, $id)
    {
        try {
            $instrument = SpareInstrumentMaster::find($id);

            if (!$instrument) {
                return ResponseHelper::error(
                    'Spare instrument not found.',
                    404
                );
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
            * Fetch normal Field Instrument Master audits and
            * Activity Performed audits for this instrument.
            */
            $query = DB::table('audits')
                ->where('record_id', $instrument->id)
                ->where(function ($query) {
                    $query->where(function ($normalQuery) {
                        $normalQuery
                            ->where('module', 'Spare Instrument Master')
                            ->where('model', SpareInstrumentMaster::class);
                    })->orWhere(function ($activityQuery) {
                        $activityQuery
                            ->where('module', 'Process Record')
                            ->where('action', 'Activity Performed')
                            ->where('model', SpareInstrumentMaster::class);
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
                
                if (strtolower(trim((string) $audit->action)) === 'activity performed') {
                    $oldData = is_array($old) ? $old : [];
                    $newData = is_array($new) ? $new : [];

                    $activityName = $newData['activity']
                        ?? $oldData['activity']
                        ?? 'Activity';

                    $oldStage = $oldData['stage'] ?? null;
                    $newStage = $newData['stage'] ?? null;

                    $rows->push([
                        'id' => $audit->id . '-activity',
                        'audit_id' => $audit->id,
                        'action' => 'Activity performed',
                        'field' => Str::headline((string) $activityName),
                        'old_value' => self::formatAuditValue($oldStage),
                        'new_value' => self::formatAuditValue($newStage),
                        'remarks' => $audit->description,
                        'performed_by' => $userNames[$audit->user_id] ?? '-',
                        'performed_at' => Carbon::parse($audit->created_at)
                            ->format('d-m-Y H:i:s'),
                    ]);

                    continue;
                }


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
                    'process' => 'Spare Instrument Master',
                    'instrument_name' => $instrument->instrument_name
                        ?? $instrument->name
                        ?? null,
                    'instrument_id_no' => $instrument->instrument_id_no
                        ?? $instrument->tag_no
                        ?? null,
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
            ], 'Spare instrument audit trail fetched successfully.');

        } catch (\Throwable $e) {
            return ResponseHelper::error(
                'Failed to retrieve field instrument audit trail.',
                500
            );
        }
    }

    /* Decode JSON strings, pass everything else through */
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

    /* Turn any value into display text. Returns null when there is nothing to show. */
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

            /* flat list of scalars → "a, b, c" */
            $isScalarList = array_is_list($value)
                && count(array_filter($value, 'is_scalar')) === count($value);

            if ($isScalarList) {
                $parts = array_filter(
                    array_map(fn ($v) => trim((string) $v), $value),
                    fn ($v) => $v !== ''
                );
                return $parts ? implode(', ', $parts) : null;
            }

            /* nested structure (e.g. checklist_config) → never dump raw JSON */
            return $complexLabel;
        }

        return null;
    }

    
    /* move Spare Instrument Master process record stage */
    public static function moveStage(RecordActivityRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $processRecord = SpareInstrumentMaster::with([
                'process',
                'stage',
                'department',
                'initiator',
            ])->findOrFail($id);

            /* verify process record belongs to Spare Instrument Master */
            if (
                !$processRecord->process ||
                $processRecord->process->name !== 'Spare Instrument Master'
            ) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Process record does not belong to Spare Instrument Master.',
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
                'spare_instrument_id' => $processRecord->id,
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

            /* audit code */
            $oldValue = [
                'activity' => $activity->name,
                'stage' => $currentStage?->name,
            ];

            $newValue = [
                'activity' => $activity->name,
                'stage' => $targetStage?->name,
            ];

            /* record audit trail */
            $description = $request->filled('comment')
                ? $request->comment
                : 'Process record stage updated successfully.';

            UserAuditHelper::log(
                'Process Record',
                'Activity Performed',
                $description,
                $processRecord->id,
                $oldValue,
                $newValue,
                SpareInstrumentMaster::class
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
                'Failed to update Spare Instrument Master stage.',
                500
            );
        }
    }

    /* check whether the logged-in user's role can act on a record's current stage */
    public static function checkSpareInstrumentRecordPermission($id)
    {
        try {
            $user = Auth::user();
            if (!$user) return ResponseHelper::error('Unauthenticated.', 401);

            $record = SpareInstrumentMaster::with(['stage', 'process'])->findOrFail($id);
            $roles = $user->roles()->pluck('name')->toArray();

            /* stage => required role, per process id */
            $stageRoles = [
                9 => [ // Spare Instrument Master
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
