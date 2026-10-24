<?php

namespace App\Services\User;

use App\Helpers\FieldLabelHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\UserAuditHelper;
use App\Models\Audit;
use App\Models\ChecklistRecord;
use App\Models\GridRecord;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UserAuditService
{
    /* paginated, filtered audit trail for a process record */
    public static function getProcessRecordAudits($id, Request $request)
    {
        try {
            $perPage = 10;
            $page = max((int) $request->input('page', 1), 1);
            $search = trim((string) $request->input('search', ''));
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            $processRecord = ProcessRecord::with(['process', 'stage', 'department', 'initiator'])->findOrFail($id);

            /* child record ids used to pull grid/checklist audits */
            $gridRecordIds = GridRecord::where('process_record_id', $id)->pluck('id');
            $checklistRecordIds = ChecklistRecord::where('process_record_id', $id)->pluck('id');

            $queries = [
                Audit::with('user')->where('record_id', $id)->where('model', ProcessRecord::class),
                Audit::with('user')->where('model', GridRecord::class)->whereIn('record_id', $gridRecordIds),
                Audit::with('user')->where('model', ChecklistRecord::class)->whereIn('record_id', $checklistRecordIds),
            ];

            /* apply shared date range filter */
            foreach ($queries as $query) {
                if (!empty($fromDate)) {
                    $query->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay());
                }
                if (!empty($toDate)) {
                    $query->where('created_at', '<=', Carbon::parse($toDate)->endOfDay());
                }
            }

            $audits = collect($queries)->flatMap(fn($query) => $query->get());
            $auditRows = self::buildAuditRows($audits);

            /* free-text search across module/user/field */
            if ($search !== '') {
                $searchLower = strtolower($search);
                $auditRows = array_values(array_filter($auditRows, function ($row) use ($searchLower) {
                    return str_contains(strtolower((string) ($row['module'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['responsible_person'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['field'] ?? '')), $searchLower);
                }));
            }

            /* manual pagination over the flattened row list */
            $total = count($auditRows);
            $paginatedRows = array_slice($auditRows, ($page - 1) * $perPage, $perPage);

            $paginator = new LengthAwarePaginator($paginatedRows, $total, $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            return ResponseHelper::success([
                'record' => self::buildRecordSummary($processRecord),
                'audits' => $paginator->items(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
                'filters' => [
                    'search' => $search !== '' ? $search : null,
                    'from_date' => $fromDate ?: null,
                    'to_date' => $toDate ?: null,
                ],
            ], 'Process record audits fetched successfully.');
        } catch (\Exception $e) {
            Log::error('AUDIT HISTORY ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ResponseHelper::error($e->getMessage() . ' | Line: ' . $e->getLine(), 500);
        }
    }

    /* equipment master audit trail */
    public static function getEquipmentMasterAudit($recordId)
    {
        try {
            $audits = Audit::with('user')
                ->where('record_id', $recordId)
                ->where('model', 'App\Models\InstrumentMaster')
                ->orderBy('id', 'desc')
                ->get();

            $audits->transform(fn($audit) => [
                'id' => $audit->id,
                'user_id' => $audit->user_id,
                'user_name' => $audit->user?->name,
                'module' => $audit->module,
                'model' => $audit->model,
                'action' => $audit->action,
                'description' => $audit->description,
                'record_id' => $audit->record_id,
                'old_value' => UserAuditHelper::prepareAuditHistoryValue($audit->old_value),
                'new_value' => UserAuditHelper::prepareAuditHistoryValue($audit->new_value),
                'created_at' => UserAuditHelper::formatAuditDate($audit->created_at, 'd/m/Y H:i:s'),
            ]);

            return ResponseHelper::success($audits, 'Equipment Master audits fetched successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Failed to retrieve equipment master audits.', 500);
        }
    }

    /* downloadable PDF audit report for a process record */
    public static function generateProcessRecordAuditReport($id)
    {
        try {
            $processRecord = ProcessRecord::with(['process', 'stage', 'department', 'initiator'])->find($id);

            if (!$processRecord) {
                return ResponseHelper::error('Process record not found.', 404);
            }

            $gridRecordIds = GridRecord::where('process_record_id', $id)->pluck('id');
            $checklistRecordIds = ChecklistRecord::where('process_record_id', $id)->pluck('id');

            $processAudits = Audit::with('user')
                ->where('record_id', $id)
                ->where('model', ProcessRecord::class);

            /* grid audits matched by child ids or by process_record_id inside the payload */
            $gridAudits = Audit::with('user')
                ->where('model', GridRecord::class)
                ->where(function ($query) use ($gridRecordIds, $id) {
                    if ($gridRecordIds->isNotEmpty()) {
                        $query->whereIn('record_id', $gridRecordIds);
                    }

                    $query->orWhereRaw("JSON_EXTRACT(new_value, '$.process_record_id') = ?", [$id])
                        ->orWhereRaw("JSON_EXTRACT(old_value, '$.process_record_id') = ?", [$id]);
                });

            $checklistAudits = Audit::with('user')
                ->where('model', ChecklistRecord::class)
                ->whereIn('record_id', $checklistRecordIds);

            $audits = $processAudits->get()
                ->concat($gridAudits->get())
                ->concat($checklistAudits->get());

            /* drop empty "Grid Name" placeholder rows */
            $auditRows = array_values(array_filter(
                self::buildAuditRows($audits),
                fn($row) => !self::isBogusGridNameRow($row)
            ));

            $pdf = Pdf::loadView('Reports.AuditReport.CalibrationAuditReport', [
                'record' => self::buildRecordSummary($processRecord),
                'audits' => $auditRows,
                'generated_at' => Carbon::now()->format('d/m/Y H:i:s'),
            ])->setPaper('a4', 'portrait');

            $fileName = 'CalibrationAuditReport' . $processRecord->id . '_' . now()->format('Ymd_His') . '.pdf';

            return $pdf->download($fileName);
        } catch (\Throwable $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /* header block describing the process record */
    private static function buildRecordSummary($processRecord): array
    {
        return [
            'id' => $processRecord->id,
            'process' => $processRecord->process?->name,
            'stage' => $processRecord->stage?->name,
            'department' => $processRecord->department?->name,
            'initiator' => $processRecord->initiator?->name,
            'initiation_date' => UserAuditHelper::formatAuditDate($processRecord->initiation_date, 'd/m/Y H:i:s'),
        ];
    }

    /* convert raw audits into display rows, newest audit id first */
    private static function buildAuditRows(Collection $audits): array
    {
        $auditRows = [];

        foreach ($audits as $audit) {
            $oldValue = UserAuditHelper::prepareAuditHistoryValue($audit->old_value);
            $newValue = UserAuditHelper::prepareAuditHistoryValue($audit->new_value);

            /* activity entries use their own helper */
            if (strtolower(trim((string) $audit->action)) === 'activity performed') {
                $activityRow = UserAuditHelper::prepareActivityAudit($audit, $oldValue, $newValue);
                if ($activityRow) $auditRows[] = $activityRow;
                continue;
            }

            $rows = match ($audit->model) {
                ProcessRecord::class => self::prepareProcessAudit($audit, $oldValue, $newValue),
                GridRecord::class => self::prepareGridAudit($audit, $oldValue, $newValue),
                ChecklistRecord::class => self::prepareChecklistAudit($audit, $oldValue, $newValue),
                default => [],
            };

            foreach ($rows as $row) $auditRows[] = $row;
        }

        usort($auditRows, fn($a, $b) => (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));

        return $auditRows;
    }

    /* process record audit - process_data fields + top-level scalar fields */
    private static function prepareProcessAudit($audit, $oldValue, $newValue)
    {
        $oldValue = is_array($oldValue) ? $oldValue : [];
        $newValue = is_array($newValue) ? $newValue : [];

        $oldData = UserAuditHelper::getProcessDataValues($oldValue['process_data'] ?? []);
        $newData = UserAuditHelper::getProcessDataValues($newValue['process_data'] ?? []);

        $rows = [];

        /* diff dynamic process_data fields */
        foreach (array_unique(array_merge(array_keys($oldData), array_keys($newData))) as $label) {
            $old = $oldData[$label] ?? null;
            $new = $newData[$label] ?? null;

            if (self::isUnchanged($old, $new)) continue;

            $rows[] = UserAuditHelper::makeAuditRow($audit, $label, $old, $new);
        }

        /* diff remaining top-level fields */
        foreach (array_unique(array_merge(array_keys($oldValue), array_keys($newValue))) as $field) {
            if (in_array($field, ['process_data', 'record_number', 'short_description'])) continue;

            $old = $oldValue[$field] ?? null;
            $new = $newValue[$field] ?? null;

            if (self::isUnchanged($old, $new)) continue;

            if ($field === 'attachments') {
                foreach (self::prepareAttachmentAuditRows($audit, $old, $new) as $attachmentRow) {
                    $rows[] = $attachmentRow;
                }
                continue;
            }

            $rows[] = UserAuditHelper::makeAuditRow($audit, FieldLabelHelper::getLabel('Process Record', $field), $old, $new);
        }

        return $rows;
    }

    /* one audit row per attachment field */
    private static function prepareAttachmentAuditRows($audit, $oldAttachments, $newAttachments): array
    {
        $oldByField = self::groupAttachmentsByField(self::normalizeAttachmentList($oldAttachments));
        $newByField = self::groupAttachmentsByField(self::normalizeAttachmentList($newAttachments));

        $rows = [];

        foreach (array_unique(array_merge(array_keys($oldByField), array_keys($newByField))) as $field) {
            $oldDisplay = self::formatAttachmentAuditValue($oldByField[$field] ?? []);
            $newDisplay = self::formatAttachmentAuditValue($newByField[$field] ?? []);

            if (UserAuditHelper::isAuditEmptyValue($oldDisplay) && UserAuditHelper::isAuditEmptyValue($newDisplay)) continue;

            $rows[] = UserAuditHelper::makeAuditRow($audit, self::getAttachmentFieldLabel($field), $oldDisplay, $newDisplay);
        }

        return $rows;
    }

    /* normalize attachment payload to a simple list */
    private static function normalizeAttachmentList($attachments): array
    {
        if ($attachments === null || $attachments === '') return [];

        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($attachments)) return [];

        if (isset($attachments['attachments'])) {
            $attachments = $attachments['attachments'];
        }

        /* single attachment object instead of a list */
        if (
            isset($attachments['name']) ||
            isset($attachments['Name']) ||
            isset($attachments['url']) ||
            isset($attachments['Url']) ||
            isset($attachments['Field']) ||
            isset($attachments['field'])
        ) {
            $attachments = [$attachments];
        }

        return array_values(array_filter($attachments, 'is_array'));
    }

    /* group attachments by their field name */
    private static function groupAttachmentsByField(array $attachments): array
    {
        $grouped = [];

        foreach ($attachments as $attachment) {
            $field = $attachment['attachment_field']
                ?? $attachment['Attachment Field']
                ?? $attachment['field']
                ?? $attachment['Field']
                ?? null;

            $grouped[$field ?: 'attachment'][] = $attachment;
        }

        return $grouped;
    }

    /* display label for an attachment field */
    private static function getAttachmentFieldLabel($field): string
    {
        $labels = [
            'attachment' => 'Attachment',
            'hod_review_attachment' => 'HOD / Designee Review Attachment',
            'user_dept_review_attachment' => 'User Department Review Attachment',
            'qa_review_attachment' => 'QA Review Attachment',
        ];

        return $labels[$field] ?? UserAuditHelper::formatAuditFieldLabel($field);
    }

    /* keep only name and url for attachment audit display */
    private static function formatAttachmentAuditValue(array $attachments)
    {
        $result = [];

        foreach ($attachments as $attachment) {
            $name = $attachment['name'] ?? $attachment['Name'] ?? null;
            if (!$name) continue;

            $result[] = [
                'name' => $name,
                'url' => $attachment['url'] ?? $attachment['Url'] ?? null,
            ];
        }

        return $result ?: null;
    }

    /* grid record audit - one row per created/updated/deleted grid row */
    private static function prepareGridAudit($audit, $oldValue, $newValue)
    {
        $oldGrid = self::normalizeGridRows(UserAuditHelper::extractGridRows($oldValue));
        $newGrid = self::normalizeGridRows(UserAuditHelper::extractGridRows($newValue));

        $rowNumbers = array_unique(array_merge(array_keys($oldGrid), array_keys($newGrid)));
        sort($rowNumbers, SORT_NUMERIC);

        $rows = [];

        foreach ($rowNumbers as $rowNumber) {
            $oldFields = $oldGrid[$rowNumber] ?? null;
            $newFields = $newGrid[$rowNumber] ?? null;
            $rowLabel = 'Row ' . $rowNumber;

            /* row deleted */
            if ($oldFields !== null && $newFields === null) {
                $oldDisplay = UserAuditHelper::formatGridFieldsForAudit($oldFields);
                if ($oldDisplay !== null) {
                    $rows[] = UserAuditHelper::makeAuditRow($audit, $rowLabel, $oldDisplay, null, 'Deleted');
                }
                continue;
            }

            /* row added */
            if ($oldFields === null && $newFields !== null) {
                $newDisplay = UserAuditHelper::formatGridFieldsForAudit($newFields);
                if ($newDisplay !== null) {
                    $rows[] = UserAuditHelper::makeAuditRow($audit, $rowLabel, null, $newDisplay, 'Created');
                }
                continue;
            }

            /* existing row - collect changed fields only */
            $changedOld = [];
            $changedNew = [];

            foreach (array_unique(array_merge(array_keys($oldFields), array_keys($newFields))) as $fieldKey) {
                $oldField = $oldFields[$fieldKey] ?? null;
                $newField = $newFields[$fieldKey] ?? null;
                $oldFieldValue = $oldField['value'] ?? null;
                $newFieldValue = $newField['value'] ?? null;

                if (self::isUnchanged($oldFieldValue, $newFieldValue)) continue;

                $label = $newField['label'] ?? $oldField['label'] ?? UserAuditHelper::formatAuditFieldLabel($fieldKey);

                if (!UserAuditHelper::isAuditEmptyValue($oldFieldValue)) $changedOld[] = ['label' => $label, 'value' => $oldFieldValue];
                if (!UserAuditHelper::isAuditEmptyValue($newFieldValue)) $changedNew[] = ['label' => $label, 'value' => $newFieldValue];
            }

            if (empty($changedOld) && empty($changedNew)) continue;

            $rows[] = UserAuditHelper::makeAuditRow(
                $audit,
                $rowLabel,
                UserAuditHelper::formatGridFieldListForAudit($changedOld),
                UserAuditHelper::formatGridFieldListForAudit($changedNew),
                'Updated'
            );
        }

        return $rows;
    }

    /* checklist record audit - one row per changed field */
    private static function prepareChecklistAudit($audit, $oldValue, $newValue)
    {
        $oldRow = (UserAuditHelper::extractChecklistRows($oldValue) ?: [[]])[0];
        $newRow = (UserAuditHelper::extractChecklistRows($newValue) ?: [[]])[0];

        $skip = ['id', 'row_id', 'checklist_record_id', 'process_record_id', 'created_at', 'updated_at'];
        $rows = [];

        foreach (array_unique(array_merge(array_keys($oldRow), array_keys($newRow))) as $key) {
            if (in_array(strtolower($key), $skip)) continue;

            $old = $oldRow[$key] ?? null;
            $new = $newRow[$key] ?? null;

            if (self::isUnchanged($old, $new)) continue;

            $rows[] = UserAuditHelper::makeAuditRow($audit, UserAuditHelper::formatAuditFieldLabel($key), $old, $new);
        }

        return $rows;
    }

    /* normalize grid rows into row_number => [field_key => [label, value]] */
    private static function normalizeGridRows($rows)
    {
        $result = [];
        if (!is_array($rows)) return $result;

        foreach ($rows as $index => $row) {
            if (!is_array($row)) continue;

            $rowNumber = $index + 1;
            $fields = [];

            foreach ($row as $item) {
                if (!is_array($item)) continue;

                $key = $item['key'] ?? null;
                $value = $item['value'] ?? null;

                /* row number override */
                if ($key === 'row') {
                    if ($value !== null && $value !== '') $rowNumber = (int) $value;
                    continue;
                }

                /* nested fields container */
                if ($key === 'fields') {
                    if (!is_array($value)) continue;

                    foreach ($value as $field) {
                        if (!is_array($field)) continue;

                        $fieldKey = $field['key'] ?? $field['Key'] ?? null;
                        if (!$fieldKey) continue;

                        $fieldValue = array_key_exists('value', $field) ? $field['value'] : ($field['Value'] ?? null);
                        $fields[$fieldKey] = ['label' => UserAuditHelper::getGridFieldLabel($fieldKey), 'value' => $fieldValue];
                    }
                    continue;
                }

                /* flat grid structure fallback */
                if ($key) {
                    $fields[$key] = ['label' => UserAuditHelper::getGridFieldLabel($key), 'value' => $value];
                }
            }

            $result[$rowNumber] = $fields;
        }

        return $result;
    }

    /* true when old and new values are equal or both empty */
    private static function isUnchanged($old, $new): bool
    {
        return UserAuditHelper::auditValuesAreSame($old, $new)
            || (UserAuditHelper::isAuditEmptyValue($old) && UserAuditHelper::isAuditEmptyValue($new));
    }

    /* empty placeholder rows labelled "Grid Name" (rows with real values are kept) */
    private static function isBogusGridNameRow(array $row): bool
    {
        if (strtolower(trim((string) ($row['field'] ?? ''))) !== 'grid name') {
            return false;
        }

        $oldValue = $row['old_value'] ?? $row['old'] ?? null;
        $newValue = $row['new_value'] ?? $row['new'] ?? null;

        return UserAuditHelper::isAuditEmptyValue($oldValue)
            && UserAuditHelper::isAuditEmptyValue($newValue);
    }
}