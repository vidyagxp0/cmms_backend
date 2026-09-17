<?php

namespace App\Services\User\CalibrationPlanner;

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

class CalibrationManagementAuditService
{
    /* get paginated, filtered audit trail for a process record */
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
            $gridRecordIds = GridRecord::where('process_record_id', $id)->pluck('id')->values();
            $checklistRecordIds = ChecklistRecord::where('process_record_id', $id)->pluck('id')->values();

            $processAuditQuery = Audit::with('user')
                ->where('record_id', $id)
                ->where('model', ProcessRecord::class);

            /* also match grid audits by stored process_record_id, in case the GridRecord id changed */
            $gridAuditQuery = Audit::with('user')
                ->where('model', GridRecord::class)
                ->where(function ($query) use ($gridRecordIds, $id) {
                    if ($gridRecordIds->isNotEmpty()) {
                        $query->whereIn('record_id', $gridRecordIds);
                    }

                    $query->orWhereRaw(
                        "JSON_EXTRACT(new_value, '$.process_record_id') = ?",
                        [$id]
                    )->orWhereRaw(
                        "JSON_EXTRACT(old_value, '$.process_record_id') = ?",
                        [$id]
                    );
                });

            $checklistAuditQuery = Audit::with('user')
                ->where('model', ChecklistRecord::class)
                ->whereIn('record_id', $checklistRecordIds);

            /* apply shared date range filter */
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

            $processAudits = $processAuditQuery->get();
            $gridAudits = $gridAuditQuery->get();
            $checklistAudits = $checklistAuditQuery->get();

            /* merge all audits, newest id first */
            $audits = $processAudits
                ->concat($gridAudits)
                ->concat($checklistAudits)
                ->sortByDesc(fn($audit) => (int) $audit->id)
                ->values();

            $auditRows = [];

            /* convert each raw audit into display rows */
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

            /* drop phantom rows where the field/value is just the grid name itself */
            $auditRows = array_values(array_filter($auditRows, function ($row) {
                return !self::isBogusGridNameRow($row);
            }));

            /* free-text search across module/user/field */
            if ($search !== '') {
                $searchLower = strtolower($search);
                $auditRows = array_values(array_filter($auditRows, function ($row) use ($searchLower) {
                    return str_contains(strtolower((string) ($row['module'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['responsible_person'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['field'] ?? '')), $searchLower);
                }));
            }

            /* newest audit id first */
            usort($auditRows, function ($a, $b) {
                return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
            });

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
            return ResponseHelper::error($e->getMessage() . ' | Line: ' . $e->getLine(), 500);
        }
    }

    /* drop rows that only contain a technical grid name */
    private static function isBogusGridNameRow(array $row): bool
    {
        $knownGridNames = [
            'calibrationresults',
            'masterinstrumentsdetails',
            'masterinstruments',
            'testresults',
            'instrumentdetails',
        ];

        $newVal = strtolower(trim((string)($row['new_value'] ?? '')));
        $oldVal = strtolower(trim((string)($row['old_value'] ?? '')));
        $module = strtolower(trim((string)($row['module'] ?? '')));

        /* strip "name : " prefix if present */
        $cleanNew = trim(preg_replace('/^name\s*[:=]\s*/i', '', $newVal));
        $cleanOld = trim(preg_replace('/^name\s*[:=]\s*/i', '', $oldVal));

        $cleanNew = str_replace(['_', '-', ' '], '', $cleanNew);
        $cleanOld = str_replace(['_', '-', ' '], '', $cleanOld);

        $isNewGridName = in_array($cleanNew, $knownGridNames);
        $isOldGridName = in_array($cleanOld, $knownGridNames);

        if (($isNewGridName && ($oldVal === '' || $oldVal === '-' || $isOldGridName)) ||
            ($isOldGridName && ($newVal === '' || $newVal === '-' || $isNewGridName))) {
            return true;
        }

        $cleanModule = str_replace(['_', '-', ' '], '', $module);
        if (in_array($cleanModule, $knownGridNames) && ($newVal === '' || $newVal === '-' || $isNewGridName)) {
            return true;
        }

        return false;
    }

    /* format grid names into clean titles, e.g. masterInstrumentsDetails -> Master Instruments Details */
    public static function formatGridTitle(?string $name, ?string $label = null): string
    {
        if (!empty($label) && !is_numeric($label)) {
            return trim($label);
        }

        if (empty($name)) {
            return 'Grid';
        }

        $known = [
            'calibrationResults'       => 'Calibration Results',
            'masterInstrumentsDetails' => 'Master Instruments Details',
            'masterInstruments'        => 'Master Instruments Details',
            'instrumentDetails'        => 'Instrument Details',
            'testResults'              => 'Test Results',
        ];

        if (isset($known[$name])) {
            return $known[$name];
        }

        $text = preg_replace('/(?<!^)[A-Z]/', ' $0', $name);
        $text = str_replace(['_', '-'], ' ', $text);

        return ucwords(strtolower(trim($text)));
    }

    /* true if this key/value pair is just a grid name marker, not a real field */
    private static function isGridNameField($key, $val): bool
    {
        if (empty($val) || !is_string($val)) return false;

        $knownGridNames = [
            'calibrationresults',
            'masterinstrumentsdetails',
            'masterinstruments',
            'testresults',
            'instrumentdetails',
        ];

        $cleanVal = strtolower(str_replace(['_', '-', ' '], '', trim($val)));
        if (in_array($cleanVal, $knownGridNames)) {
            if (in_array(strtolower((string)$key), ['name', 'grid_name', 'grid', 'table'])) {
                return true;
            }
        }

        return false;
    }

    /* unwrap a {value}/{name} column into its display value */
    private static function extractColumnValue($value)
    {
        if (is_array($value)) {
            if (array_key_exists('value', $value)) {
                return $value['value'];
            }
            if (isset($value['name'])) {
                return $value['name'];
            }
        }

        return $value;
    }

    /* resolve the display label for a grid column */
    private static function getColumnLabel($column, $fallbackKey): string
    {
        /* prefer the configured UI label over the stored audit label */
        $custom = UserAuditHelper::getGridFieldLabel($fallbackKey);

        if (!empty($custom)) {
            return (string) $custom;
        }

        if (is_array($column) && !empty($column['label'])) {
            return (string) $column['label'];
        }

        return UserAuditHelper::formatAuditFieldLabel($fallbackKey);
    }

    /* normalize a raw grid row (list or associative) into key => {label, value} */
    private static function normalizeRowFields($row): array
    {
        if (!is_array($row) || empty($row)) {
            return [];
        }

        $fields = [];

        $skipKeys = ['_rowId', 'row_id', 'id', 'created_at', 'updated_at', 'rows', 'grid_record_id', 'process_record_id'];

        /* [{key,label,value}] / [{Key,Label,Value}] structure */
        if (isset($row[0]) && is_array($row[0])) {
            foreach ($row as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $key = $field['key'] ?? $field['Key'] ?? $field['field_key'] ?? $field['Field'] ?? null;

                if (!$key || in_array($key, $skipKeys, true)) {
                    continue;
                }

                $value = array_key_exists('value', $field) ? $field['value'] : ($field['Value'] ?? null);

                if (UserAuditHelper::isAuditEmptyValue($value)) {
                    continue;
                }

                if (self::isGridNameField($key, $value)) {
                    continue;
                }

                $label = UserAuditHelper::getGridFieldLabel($key);

                if (empty($label)) {
                    $label = $field['label'] ?? $field['Label'] ?? UserAuditHelper::formatAuditFieldLabel($key);
                }

                $fields[$key] = [
                    'label' => $label,
                    'value' => $value,
                ];
            }
            return $fields;
        }

        /* normal associative grid row */
        foreach ($row as $k => $v) {
            if (in_array($k, $skipKeys, true)) {
                continue;
            }

            $val = self::extractColumnValue($v);

            if (UserAuditHelper::isAuditEmptyValue($val)) {
                continue;
            }

            if (self::isGridNameField($k, $val)) {
                continue;
            }

            $fields[$k] = [
                'label' => self::getColumnLabel($v, $k),
                'value' => $val,
            ];
        }
        return $fields;
    }

    /* normalize grid data into grid_name => rows */
    private static function normalizeGridRows($rows)
    {
        $result = [];

        if (!is_array($rows)) {
            return $result;
        }

        foreach ($rows as $gridIndex => $grid) {
            if (!is_array($grid)) {
                continue;
            }

            /* prepared audit format: {grid_label, row, fields} */
            if (
                isset($grid['grid_label']) &&
                isset($grid['row']) &&
                array_key_exists('fields', $grid)
            ) {
                $gridLabel = self::formatGridTitle(null, $grid['grid_label']);
                $rowNumber = (int) $grid['row'];
                $fields = self::normalizeRowFields($grid['fields']);

                if (empty($fields) || $rowNumber < 1) {
                    continue;
                }

                $gridKey = $gridLabel ?: ('grid_' . $gridIndex);

                $result[$gridKey]['grid_name'] = null;
                $result[$gridKey]['grid_label'] = $gridLabel;
                $result[$gridKey]['rows'][$rowNumber] = [
                    'row_number' => $rowNumber,
                    'fields' => $fields,
                ];

                continue;
            }

            /* stored as flat [{key,value}, ...] wrapping grid_label/row/fields */
            if (isset($grid[0]) && is_array($grid[0]) && isset($grid[0]['key'])) {
                $unwrapped = [];

                foreach ($grid as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $key = $item['key'] ?? null;
                    if ($key !== null) {
                        $unwrapped[$key] = $item['value'] ?? null;
                    }
                }

                if (isset($unwrapped['grid_label'], $unwrapped['row'], $unwrapped['fields'])) {
                    $gridLabel = self::formatGridTitle(null, $unwrapped['grid_label']);
                    $rowNumber = (int) $unwrapped['row'];
                    $fields = self::normalizeRowFields($unwrapped['fields']);

                    if (!empty($fields) && $rowNumber > 0) {
                        $result[$gridLabel]['grid_name'] = null;
                        $result[$gridLabel]['grid_label'] = $gridLabel;
                        $result[$gridLabel]['rows'][$rowNumber] = [
                            'row_number' => $rowNumber,
                            'fields' => $fields,
                        ];
                    }

                    continue;
                }
            }

            $gridName = null;
            $gridLabel = null;
            $gridRows = [];

            /* legacy audit format: {name/grid_name, grid_label, rows} */
            foreach ($grid as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $key = $item['key'] ?? null;
                $value = $item['value'] ?? null;

                if ($key === 'name' || $key === 'grid_name') {
                    $gridName = $value;
                    continue;
                }

                if ($key === 'grid_label') {
                    $gridLabel = $value;
                    continue;
                }

                if ($key !== 'rows' || !is_array($value)) {
                    continue;
                }

                foreach ($value as $rowIndex => $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $fields = self::normalizeRowFields($row);

                    if (empty($fields)) {
                        continue;
                    }

                    $gridRows[$rowIndex + 1] = [
                        'row_number' => $rowIndex + 1,
                        'fields' => $fields,
                    ];
                }
            }

            if (empty($gridRows)) {
                continue;
            }

            $gridLabel = self::formatGridTitle($gridName, $gridLabel);
            $gridKey = $gridName ?: $gridLabel ?: ('grid_' . $gridIndex);

            $result[$gridKey] = [
                'grid_name' => $gridName,
                'grid_label' => $gridLabel,
                'rows' => $gridRows,
            ];
        }

        return $result;
    }

    /* grid record audit - one row per created/updated/deleted grid row, grouped by grid */
    private static function prepareGridAudit($audit, $oldValue, $newValue)
    {
        $oldGrid = self::normalizeGridRows(UserAuditHelper::extractGridRows($oldValue));
        $newGrid = self::normalizeGridRows(UserAuditHelper::extractGridRows($newValue));

        $gridKeys = array_unique(array_merge(array_keys($oldGrid), array_keys($newGrid)));

        $rows = [];

        foreach ($gridKeys as $gridKey) {
            $oldGridRows = $oldGrid[$gridKey]['rows'] ?? [];
            $newGridRows = $newGrid[$gridKey]['rows'] ?? [];

            $gridLabel =
                $newGrid[$gridKey]['grid_label']
                ?? $oldGrid[$gridKey]['grid_label']
                ?? UserAuditHelper::formatAuditFieldLabel($gridKey);

            $rowNumbers = array_unique(array_merge(array_keys($oldGridRows), array_keys($newGridRows)));
            sort($rowNumbers, SORT_NUMERIC);

            foreach ($rowNumbers as $rowNumber) {
                $oldRow = $oldGridRows[$rowNumber] ?? null;
                $newRow = $newGridRows[$rowNumber] ?? null;

                $oldFields = $oldRow['fields'] ?? [];
                $newFields = $newRow['fields'] ?? [];

                /* row deleted */
                if ($oldRow !== null && $newRow === null) {
                    $oldDisplay = UserAuditHelper::formatGridFieldsForAudit($oldFields);
                    if ($oldDisplay === null) continue;

                    $rows[] = UserAuditHelper::makeAuditRow(
                        $audit,
                        $gridLabel . ' (Row ' . $rowNumber . ')',
                        $oldDisplay,
                        null,
                        'Deleted'
                    );

                    continue;
                }

                /* row created */
                if ($oldRow === null && $newRow !== null) {
                    $newDisplay = UserAuditHelper::formatGridFieldsForAudit($newFields);
                    if ($newDisplay === null) continue;

                    $rows[] = UserAuditHelper::makeAuditRow(
                        $audit,
                        $gridLabel . ' (Row ' . $rowNumber . ')',
                        null,
                        $newDisplay,
                        'Created'
                    );

                    continue;
                }

                /* existing row - collect changed fields only */
                $changedOldFields = [];
                $changedNewFields = [];

                $fieldKeys = array_unique(array_merge(array_keys($oldFields), array_keys($newFields)));

                foreach ($fieldKeys as $fieldKey) {
                    $oldField = $oldFields[$fieldKey] ?? null;
                    $newField = $newFields[$fieldKey] ?? null;

                    $oldFieldValue = $oldField['value'] ?? null;
                    $newFieldValue = $newField['value'] ?? null;

                    if (
                        UserAuditHelper::isAuditEmptyValue($oldFieldValue) &&
                        UserAuditHelper::isAuditEmptyValue($newFieldValue)
                    ) {
                        continue;
                    }

                    if (UserAuditHelper::auditValuesAreSame($oldFieldValue, $newFieldValue)) {
                        continue;
                    }

                    $label = $newField['label'] ?? $oldField['label'] ?? UserAuditHelper::formatAuditFieldLabel($fieldKey);

                    if (!UserAuditHelper::isAuditEmptyValue($oldFieldValue)) {
                        $changedOldFields[] = ['label' => $label, 'value' => $oldFieldValue];
                    }

                    if (!UserAuditHelper::isAuditEmptyValue($newFieldValue)) {
                        $changedNewFields[] = ['label' => $label, 'value' => $newFieldValue];
                    }
                }

                if (empty($changedOldFields) && empty($changedNewFields)) {
                    continue;
                }

                $rows[] = UserAuditHelper::makeAuditRow(
                    $audit,
                    $gridLabel . ' (Row ' . $rowNumber . ')',
                    UserAuditHelper::formatGridFieldListForAudit($changedOldFields),
                    UserAuditHelper::formatGridFieldListForAudit($changedNewFields),
                    'Updated'
                );
            }
        }

        return $rows;
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

            if (UserAuditHelper::auditValuesAreSame($old, $new)) continue;
            if (UserAuditHelper::isAuditEmptyValue($old) && UserAuditHelper::isAuditEmptyValue($new)) continue;

            $rows[] = UserAuditHelper::makeAuditRow($audit, $label, $old, $new);
        }

        /* diff remaining top-level fields */
        foreach (array_unique(array_merge(array_keys($oldValue), array_keys($newValue))) as $field) {
            if (in_array($field, ['process_data', 'record_number', 'short_description'])) continue;

            $old = $oldValue[$field] ?? null;
            $new = $newValue[$field] ?? null;

            if (UserAuditHelper::auditValuesAreSame($old, $new)) continue;
            if (UserAuditHelper::isAuditEmptyValue($old) && UserAuditHelper::isAuditEmptyValue($new)) continue;

            /* attachment fields get their own row, keyed by the actual field name */
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

            $oldDisplay = UserAuditHelper::formatAttachmentAuditValue($old);
            $newDisplay = UserAuditHelper::formatAttachmentAuditValue($new);

            if (
                UserAuditHelper::isAuditEmptyValue($oldDisplay) &&
                UserAuditHelper::isAuditEmptyValue($newDisplay)
            ) {
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
            isset($attachments['Url'])
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
            $field =
                $attachment['attachment_field']
                ?? $attachment['Attachment Field']
                ?? $attachment['field']
                ?? $attachment['Field']
                ?? null;

            $field = $field ?: 'attachment';

            $grouped[$field][] = $attachment;
        }

        return $grouped;
    }

    /* display label for an attachment field */
    private static function getAttachmentFieldLabel($field): string
    {
        $labels = [
            'attachment' => 'Attachment',
            'implementorAttachment' => 'HOD / Designee Review Attachment',
            'hod_review_attachment' => 'HOD Review Comment',
            'user_dept_review_attachment' => 'User Department Review Attachment',
            'qa_review_attachment' => 'QA Review Attachment',
            'cancellation_attachment' => 'Cancellation Attachment',
        ];

        if (isset($labels[$field])) {
            return $labels[$field];
        }

        return UserAuditHelper::formatAuditFieldLabel($field);
    }

    /* checklist record audit - one row per changed field */
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