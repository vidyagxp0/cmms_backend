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

            /* merge all audits, newest id first */
            $audits = $processAuditQuery->get()
                ->concat($gridAuditQuery->get())
                ->concat($checklistAuditQuery->get())
                ->sortByDesc(fn($audit) => (int) $audit->id)
                ->values();

            $auditRows = [];

            /* convert each raw audit into one or more display rows, by type */
            foreach ($audits as $audit) {
                $oldValue = self::prepareValue($audit->old_value);
                $newValue = self::prepareValue($audit->new_value);

                if (strtolower(trim($audit->action)) === 'activity performed') {
                    $activityRow = self::prepareActivityAudit($audit, $oldValue, $newValue);
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

            /* CRITICAL FIX: Filter out phantom rows where the field or value is just the grid name itself (e.g. "Name : calibrationResults") */
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

            /* preserve audit-id order */
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
                    'initiation_date' => self::formatDate($processRecord->initiation_date, 'd/m/Y H:i:s'),
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

    /* Identifies and removes phantom rows that only contain technical grid names */
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

        // Strip "name : " prefix if present
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

    /* Formats grid names into clean titles (e.g. masterInstrumentsDetails -> Master Instruments Details) */
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

    private static function getColumnLabel($column, $fallbackKey): string
    {
        if (is_array($column) && !empty($column['label'])) {
            return (string) $column['label'];
        }

        $custom = UserAuditHelper::getGridFieldLabel($fallbackKey);
        if (!empty($custom)) {
            return (string) $custom;
        }

        return self::formatFieldLabel($fallbackKey);
    }

    private static function normalizeRowFields($row): array
    {
        if (!is_array($row) || empty($row)) return [];

        $fields = [];
        $skipKeys = ['_rowId', 'row_id', 'id', 'created_at', 'updated_at', 'rows', 'grid_record_id', 'process_record_id'];

        if (isset($row[0]) && is_array($row[0]) && (isset($row[0]['key']) || isset($row[0]['label']))) {
            foreach ($row as $f) {
                if (!is_array($f)) continue;
                $k = $f['key'] ?? $f['label'] ?? null;
                if (!$k || in_array($k, $skipKeys)) continue;

                $val = $f['value'] ?? null;
                if (self::isEmptyValue($val)) continue;
                if (self::isGridNameField($k, $val)) continue;

                $label = $f['label'] ?? UserAuditHelper::getGridFieldLabel($k) ?? self::formatFieldLabel($k);
                $fields[$k] = [
                    'label' => $label,
                    'value' => $val,
                ];
            }
            return $fields;
        }

        foreach ($row as $k => $v) {
            if (in_array($k, $skipKeys)) continue;

            $val = self::extractColumnValue($v);
            if (self::isEmptyValue($val)) continue;
            if (self::isGridNameField($k, $val)) continue;

            $label = self::getColumnLabel($v, $k);
            $fields[$k] = [
                'label' => $label,
                'value' => $val,
            ];
        }

        return $fields;
    }

    /* Normalize grid audit data */
    private static function normalizeAuditGrids($value): array
    {
        if (empty($value)) {
            return [];
        }

        $data = $value;

        if (is_array($value) && isset($value['grid_data'])) {
            $data = $value['grid_data'];
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        if (!is_array($data) || empty($data)) {
            return [];
        }

        $normalized = [];

        foreach ($data as $idx => $item) {

            if (!is_array($item)) {
                continue;
            }

            /* unwrap key/value structure */
            if (
                isset($item[0]) &&
                is_array($item[0]) &&
                isset($item[0]['key'])
            ) {
                $unwrapped = [];

                foreach ($item as $prop) {

                    if (
                        is_array($prop) &&
                        isset($prop['key'])
                    ) {
                        $unwrapped[$prop['key']] =
                            $prop['value'] ?? null;
                    }
                }

                $item = $unwrapped;
            }

            /* already normalized row format */
            if (
                isset($item['row']) &&
                isset($item['fields'])
            ) {
                $gridName = $item['grid_name'] ?? null;
                $module = trim((string) ($item['module'] ?? ''));

                $gridLabel = $item['grid_label']
                    ?? self::formatGridTitle($gridName);

                if ($module !== '') {
                    $gridLabel = preg_replace(
                        '/\s*\(Row\s+\d+\)\s*$/i',
                        '',
                        $module
                    );
                    $gridLabel = trim((string) $gridLabel);
                }

                $rowNum = (int) $item['row'];

                $gridKey = $module !== ''
                    ? $module
                    : ($gridName ?: $gridLabel ?: 'default');

                $fields = self::normalizeRowFields(
                    $item['fields']
                );

                if (empty($fields)) {
                    continue;
                }

                $normalized[$gridKey]['grid_name'] =
                    $gridName;

                $normalized[$gridKey]['grid_label'] =
                    $gridLabel;

                $normalized[$gridKey]['module'] =
                    $module !== ''
                        ? $module
                        : ($gridLabel . ' (Row ' . $rowNum . ')');

                $normalized[$gridKey]['rows'][$rowNum] = [
                    'row_number' => $rowNum,
                    'fields' => $fields,
                ];

                continue;
            }

            /*
            * Normal grid structure:
            *
            * [
            *     'name' => 'calibrationResults',
            *     'rows' => [...]
            * ]
            */
            $gridName = isset($item['name'])
                ? trim((string) $item['name'])
                : null;

            $rows = isset($item['rows']) &&
                is_array($item['rows'])
                ? array_values($item['rows'])
                : [];

            /*
            * IMPORTANT:
            * A grid definition with no rows is not an
            * audit entry.
            */
            if (empty($rows)) {
                continue;
            }

            $gridLabel = self::formatGridTitle(
                $gridName,
                $item['label'] ?? null
            );

            $gridKey = $gridName
                ?: ('grid_' . $idx);

            foreach ($rows as $rIdx => $row) {

                if (!is_array($row)) {
                    continue;
                }

                $rowNum = $rIdx + 1;

                $fields = self::normalizeRowFields($row);

                /*
                * If the row contains only technical data
                * or the grid name, don't create an audit row.
                */
                if (empty($fields)) {
                    continue;
                }

                $normalized[$gridKey]['grid_name'] =
                    $gridName;

                $normalized[$gridKey]['grid_label'] =
                    $gridLabel;

                $normalized[$gridKey]['module'] =
                    $gridLabel . ' (Row ' . $rowNum . ')';

                $normalized[$gridKey]['rows'][$rowNum] = [
                    'row_number' => $rowNum,
                    'fields' => $fields,
                ];
            }
        }

        /*
        * Remove grids that ended up with no real rows.
        */
        foreach ($normalized as $gridKey => $grid) {

            if (
                empty($grid['rows']) ||
                !is_array($grid['rows'])
            ) {
                unset($normalized[$gridKey]);
            }
        }

        return $normalized;
    }

    /* normalize grid rows into grid label + row number + fields */
    private static function normalizeGridRows($rows)
    {
        $result = [];

        if (!is_array($rows)) {
            return $result;
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowNumber = $index + 1;
            $gridLabel = null;
            $fields = [];

            foreach ($row as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $key = $item['key'] ?? null;
                $value = $item['value'] ?? null;

                /* row number */
                if ($key === 'row') {
                    if (
                        $value !== null &&
                        $value !== ''
                    ) {
                        $rowNumber = (int) $value;
                    }

                    continue;
                }

                /* grid label */
                if (
                    $key === 'grid_label' ||
                    $key === 'Grid Label'
                ) {
                    if (
                        $value !== null &&
                        $value !== ''
                    ) {
                        $gridLabel = $value;
                    }

                    continue;
                }

                /* old grid name is intentionally ignored */
                if (
                    $key === 'grid_name' ||
                    $key === 'Grid Name'
                ) {
                    continue;
                }

                /* nested fields */
                if ($key === 'fields') {
                    if (!is_array($value)) {
                        continue;
                    }

                    foreach ($value as $field) {
                        if (!is_array($field)) {
                            continue;
                        }

                        $fieldKey =
                            $field['key']
                            ?? $field['Key']
                            ?? null;

                        if (!$fieldKey) {
                            continue;
                        }

                        $fieldValue =
                            array_key_exists(
                                'value',
                                $field
                            )
                                ? $field['value']
                                : (
                                    $field['Value']
                                    ?? null
                                );

                        if (
                            self::isEmptyValue(
                                $fieldValue
                            )
                        ) {
                            continue;
                        }

                        $fields[$fieldKey] = [
                            'label' =>
                                UserAuditHelper::getGridFieldLabel(
                                    $fieldKey
                                ),
                            'value' => $fieldValue,
                        ];
                    }

                    continue;
                }

                /* fallback for flat structure */
                if ($key) {
                    if (
                        self::isEmptyValue($value)
                    ) {
                        continue;
                    }

                    $fields[$key] = [
                        'label' =>
                            UserAuditHelper::getGridFieldLabel(
                                $key
                            ),
                        'value' => $value,
                    ];
                }
            }

            if (empty($fields)) {
                continue;
            }

            $result[$rowNumber] = [
                'row_number' => $rowNumber,
                'grid_label' => $gridLabel,
                'fields' => $fields,
            ];
        }

        return $result;
    }


    /* prepare grid audit */
    private static function prepareGridAudit(
        $audit,
        $oldValue,
        $newValue
    ) {
        $oldGrid = self::normalizeGridRows(
            is_array($oldValue) ? $oldValue : []
        );

        $newGrid = self::normalizeGridRows(
            is_array($newValue) ? $newValue : []
        );

        $gridKeys = array_unique(array_merge(
            array_keys($oldGrid),
            array_keys($newGrid)
        ));

        $rows = [];

        foreach ($gridKeys as $gridKey) {
            $oldMeta = $oldGrid[$gridKey] ?? [];
            $newMeta = $newGrid[$gridKey] ?? [];

            $module = trim((string) (
                $newMeta['module']
                ?? $oldMeta['module']
                ?? ''
            ));

            $gridLabel = trim((string) (
                $newMeta['grid_label']
                ?? $oldMeta['grid_label']
                ?? ''
            ));

            $oldRows = $oldMeta['rows'] ?? [];
            $newRows = $newMeta['rows'] ?? [];

            $rowNumbers = array_unique(array_merge(
                array_keys($oldRows),
                array_keys($newRows)
            ));

            sort($rowNumbers, SORT_NUMERIC);

            foreach ($rowNumbers as $rowNumber) {
                $oldRow = $oldRows[$rowNumber] ?? null;
                $newRow = $newRows[$rowNumber] ?? null;

                $oldFields = $oldRow['fields'] ?? [];
                $newFields = $newRow['fields'] ?? [];

                $rowModule = $module;

                if ($rowModule === '' || !preg_match('/\(Row\s+' . (int) $rowNumber . '\)$/i', $rowModule)) {
                    $rowModule = ($gridLabel !== '' ? $gridLabel : 'Grid')
                        . ' (Row ' . (int) $rowNumber . ')';
                }

                $oldDisplay = self::formatGridFieldsForAudit($oldFields);
                $newDisplay = self::formatGridFieldsForAudit($newFields);

                /* Never create an audit row for an empty grid row. */
                if (
                    self::isEmptyValue($oldDisplay)
                    && self::isEmptyValue($newDisplay)
                ) {
                    continue;
                }

                $rows[] = self::makeAuditRow(
                    $audit,
                    $rowModule,
                    $oldDisplay,
                    $newDisplay
                );
            }
        }

        return $rows;
    }

    private static function prepareProcessAudit($audit, $oldValue, $newValue)
    {
        $oldValue = is_array($oldValue) ? $oldValue : [];
        $newValue = is_array($newValue) ? $newValue : [];

        $oldData = self::getProcessDataValues($oldValue['process_data'] ?? []);
        $newData = self::getProcessDataValues($newValue['process_data'] ?? []);

        $rows = [];

        foreach (array_unique(array_merge(array_keys($oldData), array_keys($newData))) as $label) {
            $old = $oldData[$label] ?? null;
            $new = $newData[$label] ?? null;

            if (self::valuesAreSame($old, $new)) continue;
            if (self::isEmptyValue($old) && self::isEmptyValue($new)) continue;

            $rows[] = self::makeAuditRow($audit, $label, $old, $new);
        }

        foreach (array_unique(array_merge(array_keys($oldValue), array_keys($newValue))) as $field) {
            if (in_array($field, ['process_data', 'record_number', 'short_description'])) continue;

            $old = $oldValue[$field] ?? null;
            $new = $newValue[$field] ?? null;

            if (self::valuesAreSame($old, $new)) continue;
            if (self::isEmptyValue($old) && self::isEmptyValue($new)) continue;

            if ($field === 'attachments') {
                $old = self::formatAttachmentAuditValue($old);
                $new = self::formatAttachmentAuditValue($new);
            }

            $rows[] = self::makeAuditRow($audit, FieldLabelHelper::getLabel('Process Record', $field), $old, $new);
        }

        return $rows;
    }

    private static function formatAttachmentAuditValue($attachments)
    {
        if ($attachments === null || $attachments === '') return null;

        if (is_string($attachments)) {
            $decoded = json_decode($attachments, true);
            $attachments = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($attachments)) return null;

        $result = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) continue;

            $name = $attachment['name'] ?? $attachment['Name'] ?? null;
            if (!$name) continue;

            $result[] = ['name' => $name, 'url' => $attachment['url'] ?? $attachment['Url'] ?? null];
        }

        return empty($result) ? null : $result;
    }

    private static function getProcessDataValues($data)
    {
        if (!is_array($data)) return [];

        $result = [];

        foreach ($data as $key => $item) {
            if (is_array($item)) {
                if (array_key_exists('label', $item) && array_key_exists('value', $item)) {
                    if ($item['label']) $result[$item['label']] = $item['value'] ?? null;
                    continue;
                }
                if (isset($item['key']) && array_key_exists('value', $item)) {
                    $result[$item['key']] = $item['value'] ?? null;
                    continue;
                }
            }

            if (is_string($key)) $result[$key] = $item;
        }

        return $result;
    }

    private static function auditDisplayValue($value)
    {
        if ($value === null || $value === '') return '-';

        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (!is_array($value)) return (string) $value;
        if (isset($value['name'])) return (string) $value['name'];
        if (isset($value['value']) && count($value) <= 3) return self::auditDisplayValue($value['value']);

        $lines = [];

        foreach ($value as $key => $item) {
            if (self::isEmptyValue($item)) continue;

            if (is_array($item)) {
                $nestedLines = [];

                foreach ($item as $nestedKey => $nestedValue) {
                    if (self::isEmptyValue($nestedValue)) continue;
                    if (is_array($nestedValue)) $nestedValue = self::auditDisplayValue($nestedValue);

                    $nestedLines[] = self::formatFieldLabel((string) $nestedKey) . ' : ' . $nestedValue;
                }

                if (!empty($nestedLines)) $lines[] = $key . ' : ' . implode(', ', $nestedLines);
                continue;
            }

            $lines[] = self::formatFieldLabel((string) $key) . ' : ' . $item;
        }

        return empty($lines) ? '-' : implode("\n", $lines);
    }

    private static function prepareChecklistAudit($audit, $oldValue, $newValue)
    {
        $oldRow = (self::extractChecklistRows($oldValue) ?: [[]])[0];
        $newRow = (self::extractChecklistRows($newValue) ?: [[]])[0];

        $rows = [];
        $skip = ['id', 'row_id', 'checklist_record_id', 'process_record_id', 'created_at', 'updated_at'];

        foreach (array_unique(array_merge(array_keys($oldRow), array_keys($newRow))) as $key) {
            if (in_array(strtolower($key), $skip)) continue;

            $old = $oldRow[$key] ?? null;
            $new = $newRow[$key] ?? null;

            if (self::isEmptyValue($old) && self::isEmptyValue($new)) continue;
            if (self::valuesAreSame($old, $new)) continue;

            $rows[] = self::makeAuditRow($audit, self::formatFieldLabel($key), $old, $new);
        }

        return $rows;
    }

    private static function prepareActivityAudit($audit, $oldValue, $newValue)
    {
        $oldValue = is_array($oldValue) ? $oldValue : [];
        $newValue = is_array($newValue) ? $newValue : [];

        $activityName = $newValue['activity'] ?? $oldValue['activity'] ?? $audit->module ?? 'Activity';
        $oldStage = $oldValue['stage'] ?? null;
        $newStage = $newValue['stage'] ?? null;
        $comment = $newValue['comment'] ?? null;

        if (self::isEmptyValue($comment)) $comment = $audit->description ?? null;

        if (self::isEmptyValue($oldStage) && self::isEmptyValue($newStage) && self::isEmptyValue($comment)) {
            return null;
        }

        return [
            'id' => $audit->id,
            'action' => 'Activity Performed',
            'module' => $activityName,
            'old_value' => self::isEmptyValue($oldStage) ? null : self::formatDisplayValue($oldStage),
            'new_value' => self::isEmptyValue($newStage) ? null : self::formatDisplayValue($newStage),
            'comment' => self::isEmptyValue($comment) ? null : self::formatDisplayValue($comment),
            'responsible_person' => $audit->user?->name ?? '-',
            'user_id' => $audit->user_id,
            'record_id' => $audit->record_id,
            'model' => $audit->model,
            'created_at' => self::formatDate($audit->created_at, 'd/m/Y H:i:s'),
        ];
    }

    private static function makeAuditRow($audit, $module, $oldValue, $newValue, $action = null)
    {
        return [
            'id' => $audit->id,
            'action' => $action ?? $audit->action,
            'module' => $module,
            'old_value' => self::isEmptyValue($oldValue) ? null : self::formatDisplayValue($oldValue),
            'new_value' => self::isEmptyValue($newValue) ? null : self::formatDisplayValue($newValue),
            'comment' => null,
            'responsible_person' => $audit->user?->name ?? '-',
            'user_id' => $audit->user_id,
            'record_id' => $audit->record_id,
            'model' => $audit->model,
            'created_at' => self::formatDate($audit->created_at, 'd-m-Y H:i:s'),
        ];
    }

    private static function formatGridFieldsForAudit($fields)
    {
        if (!is_array($fields)) return null;

        $lines = [];

        foreach ($fields as $key => $field) {
            if (is_array($field) && (array_key_exists('label', $field) || array_key_exists('value', $field))) {
                $label = $field['label'] ?? self::formatFieldLabel($key);
                $value = $field['value'] ?? null;
            } elseif (!is_array($field)) {
                $label = self::formatFieldLabel($key);
                $value = $field;
            } else {
                continue;
            }

            if (self::isEmptyValue($value) || self::isEmptyValue($label)) continue;

            $lines[] = $label . ' : ' . self::auditDisplayValue($value);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    private static function formatGridFieldListForAudit($fields)
    {
        if (!is_array($fields) || empty($fields)) return null;

        $lines = [];

        foreach ($fields as $field) {
            if (!is_array($field)) continue;

            $label = $field['label'] ?? null;
            $value = $field['value'] ?? null;
            if (self::isEmptyValue($label) || self::isEmptyValue($value)) continue;

            $lines[] = $label . ' : ' . self::auditDisplayValue($value);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    private static function extractChecklistRows($value)
    {
        if (!is_array($value)) return [];
        if (isset($value['checklist_data']) && is_array($value['checklist_data'])) return array_values($value['checklist_data']);
        if (isset($value[0]) && is_array($value[0])) return array_values($value);

        return [];
    }

    private static function prepareValue($value)
    {
        if ($value === null) return null;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
        }

        if (is_object($value)) $value = $value->toArray();
        if (!is_array($value)) return self::isEmptyValue($value) ? null : $value;

        return self::removeEmptyValues($value);
    }

    private static function removeEmptyValues($value)
    {
        if (!is_array($value)) return self::isEmptyValue($value) ? null : $value;

        $result = [];

        foreach ($value as $key => $item) {
            if (self::isEmptyValue($item)) continue;

            if (is_string($item)) {
                $decoded = json_decode($item, true);
                if (json_last_error() === JSON_ERROR_NONE) $item = $decoded;
            }

            if (is_array($item)) {
                $cleaned = self::removeEmptyValues($item);
                if (!empty($cleaned)) $result[$key] = $cleaned;
                continue;
            }

            if (!self::isEmptyValue($item)) $result[$key] = $item;
        }

        return $result;
    }

    private static function isEmptyValue($value)
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return empty($value);

        return false;
    }

    private static function valuesAreSame($old, $new)
    {
        if (self::isEmptyValue($old) && self::isEmptyValue($new)) return true;

        if (is_array($old) || is_array($new)) {
            if (!is_array($old) || !is_array($new)) return false;
            return $old == $new;
        }

        if (is_object($old) || is_object($new)) {
            $old = is_object($old) ? $old->toArray() : $old;
            $new = is_object($new) ? $new->toArray() : $new;
            return self::valuesAreSame($old, $new);
        }

        return $old === $new;
    }

    private static function formatDisplayValue($value)
    {
        if ($value === null || $value === '') return null;
        if (is_string($value)) return $value;

        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (is_array($value)) {
            if (isset($value[0]) && is_array($value[0]) && array_key_exists('label', $value[0]) && array_key_exists('value', $value[0])) {
                return self::formatGridFieldListForAudit($value);
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', (string) $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }

    private static function formatDate($date, $format = 'd/m/Y H:i:s')
    {
        if ($date === null || $date === '') return null;
        if ($date instanceof \DateTimeInterface) return $date->format($format);

        try {
            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            return (string) $date;
        }
    }

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
                'old_value' => self::prepareValue($audit->old_value),
                'new_value' => self::prepareValue($audit->new_value),
                'created_at' => self::formatDate($audit->created_at, 'd/m/Y H:i:s'),
            ]);

            return ResponseHelper::success($audits, 'Equipment Master audits fetched successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Failed to retrieve equipment master audits.', 500);
        }
    }
}