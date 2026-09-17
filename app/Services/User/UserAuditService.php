<?php

namespace App\Services\User;

use App\Helpers\FieldLabelHelper;
use App\Helpers\UserAuditHelper;
use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;
use App\Models\Audit;
use App\Models\GridRecord;
use App\Models\ChecklistRecord;
use App\Models\ProcessRecord;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class UserAuditService
{
    /* get paginated, filtered audit trail for a process record (process + grid + checklist + activity) */
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

            $processAuditQuery = Audit::with('user')->where('record_id', $id)->where('model', ProcessRecord::class);
            $gridAuditQuery = Audit::with('user')->where('model', GridRecord::class)->whereIn('record_id', $gridRecordIds);
            $checklistAuditQuery = Audit::with('user')->where('model', ChecklistRecord::class)->whereIn('record_id', $checklistRecordIds);

            /* apply shared date range filter to all three queries */
            if (!empty($fromDate)) {
                $from = Carbon::parse($fromDate)->startOfDay();
                $processAuditQuery->where('created_at', '>=', $from);
                $gridAuditQuery->where('created_at', '>=', $from);
                $checklistAuditQuery->where('created_at', '>=', $from);
            }
            if (!empty($toDate)) {
                $to = Carbon::parse($toDate)->endOfDay();
                $processAuditQuery->where('created_at', '<=', $to);
                $gridAuditQuery->where('created_at', '<=', $to);
                $checklistAuditQuery->where('created_at', '<=', $to);
            }

            /* merge all audits, newest id first (id is the source of truth for ordering) */
            $audits = $processAuditQuery->get()
                ->concat($gridAuditQuery->get())
                ->concat($checklistAuditQuery->get())
                ->sortByDesc(fn($audit) => (int) $audit->id)
                ->values();

            $auditRows = [];

            /* convert each raw audit into one or more display rows, by type */
            foreach ($audits as $audit) {
                $oldValue = UserAuditHelper::prepareAuditHistoryValue($audit->old_value);
                $newValue = UserAuditHelper::prepareAuditHistoryValue($audit->new_value);

                if (strtolower(trim($audit->action)) === 'activity performed') {
                    $activityRow = UserAuditHelper::prepareActivityAudit($audit, $oldValue, $newValue);
                    if ($activityRow) $auditRows[] = $activityRow;
                    continue;
                }

                if ($audit->model === ProcessRecord::class) {
                    foreach (self::prepareProcessAudit($audit, $oldValue, $newValue) as $row) $auditRows[] = $row;
                    continue;
                }

                if ($audit->model === GridRecord::class) {
                    foreach (self::prepareGridAudit($audit, $oldValue, $newValue) as $row) $auditRows[] = $row;
                    continue;
                }

                if ($audit->model === ChecklistRecord::class) {
                    foreach (self::prepareChecklistAudit($audit, $oldValue, $newValue) as $row) $auditRows[] = $row;
                    continue;
                }
            }

            /* free-text search across module/user/field */
            if ($search !== '') {
                $searchLower = strtolower($search);
                $auditRows = array_values(array_filter($auditRows, function ($row) use ($searchLower) {
                    return str_contains(strtolower((string) ($row['module'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['responsible_person'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['field'] ?? '')), $searchLower);
                }));
            }

            /* preserve audit-id order across the rows produced from each audit */
            usort($auditRows, fn($a, $b) => (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0));

            /* manual pagination over the flattened row list */
            $total = count($auditRows);
            $offset = ($page - 1) * $perPage;
            $paginatedRows = array_slice($auditRows, $offset, $perPage);

            $paginator = new LengthAwarePaginator($paginatedRows, $total, $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            return ResponseHelper::success([
                'record' => [
                    'id' => $processRecord->id,
                    'process' => $processRecord->process?->name,
                    'stage' => $processRecord->stage?->name,
                    'department' => $processRecord->department?->name,
                    'initiator' => $processRecord->initiator?->name,
                    'initiation_date' => UserAuditHelper::formatAuditDate($processRecord->initiation_date, 'd/m/Y H:i:s'),
                ],
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
            \Log::error('AUDIT HISTORY ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ResponseHelper::error($e->getMessage() . ' | Line: ' . $e->getLine(), 500);
        }
    }

    /* build audit rows for a process-record change (process_data fields + top-level scalar fields) */
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

            if (UserAuditHelper::auditValuesAreSame($old, $new)) continue;
            if (UserAuditHelper::isAuditEmptyValue($old) && UserAuditHelper::isAuditEmptyValue($new)) continue;

            $rows[] = UserAuditHelper::makeAuditRow($audit, $label, $old, $new);
        }

        /* diff remaining top-level fields (skip ones already covered above) */
        foreach (array_unique(array_merge(array_keys($oldValue), array_keys($newValue))) as $field) {
            if (in_array($field, ['process_data', 'record_number', 'short_description'])) continue;

            $old = $oldValue[$field] ?? null;
            $new = $newValue[$field] ?? null;

            if (UserAuditHelper::auditValuesAreSame($old, $new)) continue;
            if (UserAuditHelper::isAuditEmptyValue($old) && UserAuditHelper::isAuditEmptyValue($new)) continue;

            /* show the actual attachment field name instead of generic attachments */
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

    /* reduce an attachments payload down to [name, url] entries for audit display */
    /* create one audit row per actual attachment field */
    private static function prepareAttachmentAuditRows($audit, $oldAttachments, $newAttachments): array
    {
        $oldAttachments = self::normalizeAttachmentList($oldAttachments);
        $newAttachments = self::normalizeAttachmentList($newAttachments);

        $oldByField = self::groupAttachmentsByField($oldAttachments);
        $newByField = self::groupAttachmentsByField($newAttachments);

        $fields = array_unique(array_merge(array_keys($oldByField), array_keys($newByField)));
        $rows = [];

        foreach ($fields as $field) {
            $old = $oldByField[$field] ?? [];
            $new = $newByField[$field] ?? [];

            if (empty($old) && empty($new)) {
                continue;
            }

            $oldDisplay = self::formatAttachmentAuditValue($old);
            $newDisplay = self::formatAttachmentAuditValue($new);

            if (UserAuditHelper::isAuditEmptyValue($oldDisplay) && UserAuditHelper::isAuditEmptyValue($newDisplay)) {
                continue;
            }

            $rows[] = UserAuditHelper::makeAuditRow(
                $audit,
                self::getAttachmentFieldLabel($field),
                $oldDisplay,
                $newDisplay
            );
        }

        return $rows;
    }

    /* normalize attachment payload to a simple list */
    private static function normalizeAttachmentList($attachments): array
    {
        if ($attachments === null || $attachments === '') {
            return [];
        }

        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($attachments)) {
            return [];
        }

        if (isset($attachments['attachments'])) {
            $attachments = $attachments['attachments'];
        }

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

    /* group attachments using their actual field name */
    private static function groupAttachmentsByField(array $attachments): array
    {
        $grouped = [];

        foreach ($attachments as $attachment) {
            $field = $attachment['attachment_field']
                ?? $attachment['Attachment Field']
                ?? $attachment['field']
                ?? $attachment['Field']
                ?? null;

            $field = $field ?: 'attachment';
            $grouped[$field][] = $attachment;
        }

        return $grouped;
    }

    /* return the display label for an attachment field */
    private static function getAttachmentFieldLabel($field): string
    {
        $labels = [
            'attachment' => 'Attachment',
            'hod_review_attachment' => 'HOD / Designee Review Attachment',
            'user_dept_review_attachment' => 'User Department Review Attachment',
            'qa_review_attachment' => 'QA Review Attachment',
        ];

        if (isset($labels[$field])) {
            return $labels[$field];
        }

        return UserAuditHelper::formatAuditFieldLabel($field);
    }

    /* keep only attachment name and url in the audit value */
    private static function formatAttachmentAuditValue($attachments)
    {
        if ($attachments === null || $attachments === '') {
            return null;
        }

        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($attachments)) {
            return null;
        }

        $result = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $name = $attachment['name'] ?? $attachment['Name'] ?? null;

            if (!$name) {
                continue;
            }

            $result[] = [
                'name' => $name,
                'url' => $attachment['url'] ?? $attachment['Url'] ?? null,
            ];
        }

        return empty($result) ? null : $result;
    }

    /* flatten process_data into label => value pairs */
    /* render a value (scalar, name-object, or nested array) for display */
    /* grid record audit - one row per created/updated/deleted grid row */
    private static function prepareGridAudit($audit, $oldValue, $newValue)
    {
        $oldGrid = self::normalizeGridRows(UserAuditHelper::extractGridRows($oldValue));
        $newGrid = self::normalizeGridRows(UserAuditHelper::extractGridRows($newValue));

        $rowNumbers = array_unique(array_merge(array_keys($oldGrid), array_keys($newGrid)));
        sort($rowNumbers, SORT_NUMERIC);

        $rows = [];

        foreach ($rowNumbers as $rowNumber) {
            $oldRow = $oldGrid[$rowNumber] ?? null;
            $newRow = $newGrid[$rowNumber] ?? null;
            $oldFields = $oldRow['fields'] ?? [];
            $newFields = $newRow['fields'] ?? [];

            /* row deleted */
            if ($oldRow !== null && $newRow === null) {
                $oldDisplay = UserAuditHelper::formatGridFieldsForAudit($oldFields);
                if ($oldDisplay === null) continue;

                $rows[] = UserAuditHelper::makeAuditRow($audit, 'Row ' . $rowNumber, $oldDisplay, null, 'Deleted');
                continue;
            }

            /* row added */
            if ($oldRow === null && $newRow !== null) {
                $newDisplay = UserAuditHelper::formatGridFieldsForAudit($newFields);
                if ($newDisplay === null) continue;

                $rows[] = UserAuditHelper::makeAuditRow($audit, 'Row ' . $rowNumber, null, $newDisplay, 'Created');
                continue;
            }

            /* existing row - collect all changed fields into a single row */
            $changedOldFields = [];
            $changedNewFields = [];

            foreach (array_unique(array_merge(array_keys($oldFields), array_keys($newFields))) as $fieldKey) {
                $oldField = $oldFields[$fieldKey] ?? null;
                $newField = $newFields[$fieldKey] ?? null;
                $oldFieldValue = $oldField['value'] ?? null;
                $newFieldValue = $newField['value'] ?? null;

                if (UserAuditHelper::isAuditEmptyValue($oldFieldValue) && UserAuditHelper::isAuditEmptyValue($newFieldValue)) continue;
                if (UserAuditHelper::auditValuesAreSame($oldFieldValue, $newFieldValue)) continue;

                $label = $newField['label'] ?? $oldField['label'] ?? UserAuditHelper::formatAuditFieldLabel($fieldKey);

                if (!UserAuditHelper::isAuditEmptyValue($oldFieldValue)) $changedOldFields[] = ['label' => $label, 'value' => $oldFieldValue];
                if (!UserAuditHelper::isAuditEmptyValue($newFieldValue)) $changedNewFields[] = ['label' => $label, 'value' => $newFieldValue];
            }

            if (empty($changedOldFields) && empty($changedNewFields)) continue;

            $rows[] = UserAuditHelper::makeAuditRow(
                $audit,
                'Row ' . $rowNumber,
                UserAuditHelper::formatGridFieldListForAudit($changedOldFields),
                UserAuditHelper::formatGridFieldListForAudit($changedNewFields),
                'Updated'
            );
        }

        return $rows;
    }

    /* checklist record audit - single row per changed field */
    private static function prepareChecklistAudit($audit, $oldValue, $newValue)
    {
        $oldRow = (UserAuditHelper::extractChecklistRows($oldValue) ?: [[]])[0];
        $newRow = (UserAuditHelper::extractChecklistRows($newValue) ?: [[]])[0];

        $rows = [];
        $skip = ['id', 'row_id', 'checklist_record_id', 'process_record_id', 'created_at', 'updated_at'];

        foreach (array_unique(array_merge(array_keys($oldRow), array_keys($newRow))) as $key) {
            if (in_array(strtolower($key), $skip)) continue;

            $old = $oldRow[$key] ?? null;
            $new = $newRow[$key] ?? null;

            if (UserAuditHelper::isAuditEmptyValue($old) && UserAuditHelper::isAuditEmptyValue($new)) continue;
            if (UserAuditHelper::auditValuesAreSame($old, $new)) continue;

            $rows[] = UserAuditHelper::makeAuditRow($audit, UserAuditHelper::formatAuditFieldLabel($key), $old, $new);
        }

        return $rows;
    }

    /* activity (stage move) audit */
    /* build a single display-ready audit row */
    /* pull the raw grid row array out of an audit value */
    /* normalize grid rows into row_number => fields structure, regenerating labels from keys for consistency */
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

                /* fallback for flat grid structure */
                if ($key) {
                    $fields[$key] = ['label' => UserAuditHelper::getGridFieldLabel($key), 'value' => $value];
                }
            }

            $result[$rowNumber] = ['row_number' => $rowNumber, 'fields' => $fields];
        }

        return $result;
    }

    /* format every non-empty field of a grid row */
    /* format only the changed fields of a grid row */
    /* pull the raw checklist row array out of an audit value */
    /* decode (if JSON string) and strip empty values from a raw audit value */
    /* recursively strip empty values, decoding nested JSON strings along the way */
    /* check for null / blank string / empty array */
    /* compare two audit values for equality (arrays, objects, or scalars) */
    /* format a value for the final audit row display */
    /* turn a raw key like "some_field" into "Some Field" */
    /* format a date value, tolerating Carbon instances, strings, or bad input */
    /* equipment master audit trail */
    public static function getEquipmentMasterAudit($recordId)
    {
        try {
            $audits = Audit::with('user')
                ->where('record_id', $recordId)
                ->where('model', 'App\Models\EquipmentMaster')
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
}
