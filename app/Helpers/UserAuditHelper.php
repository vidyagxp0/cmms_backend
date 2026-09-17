<?php

namespace App\Helpers;

use App\Models\Audit;
use App\Models\Department;
use App\Models\Process;
use App\Models\Stage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class UserAuditHelper
{
    /* create an audit log entry */
    public static function log(
        string $module,
        string $action,
        string $description,
        $recordId = null,
        $oldValue = null,
        $newValue = null,
        ?string $model = null
    ) {
        return Audit::create([
            'user_id' => Auth::id(),
            'module' => $module,
            'model' => $model,
            'action' => $action,
            'description' => $description,
            'record_id' => $recordId,
            'old_value' => self::prepareValue($oldValue, $module),
            'new_value' => self::prepareValue($newValue, $module),
        ]);
    }

    /* decode + clean a value before storing it in the audit */
    private static function prepareValue($value, string $module)
    {
        if ($value === null) return null;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
        }

        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (!is_array($value)) return self::formatDateValue($value);

        return self::cleanArray($value, $module);
    }

    /* clean a top-level audit array: strip technical fields, resolve foreign ids to names, label the rest */
    private static function cleanArray(array $data, string $module)
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) continue;

            /* never expose calibration frequency start date in audits */
            if ($key === 'calibrationFrequencyStartDate') continue;

            if ($key === 'department_id') {
                $result['Department'] = self::getDepartmentName($value);
                continue;
            }
            if ($key === 'initiator_id') {
                $result['Initiator'] = self::getUserName($value);
                continue;
            }
            if ($key === 'process_id') {
                $result['Process'] = self::getProcessName($value);
                continue;
            }
            if ($key === 'stage_id') {
                $result['Stage'] = self::getStageName($value);
                continue;
            }

            if ($key === 'process_data') {
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                if (is_array($decoded)) $result['process_data'] = self::cleanProcessData($decoded);
                continue;
            }

            if ($key === 'grid_data') {
                $decoded = is_string($value) ? json_decode($value, true) : $value;
                if (is_array($decoded)) $result['grid_data'] = self::cleanGridData($decoded);
                continue;
            }

            if (is_array($value)) {
                $cleaned = self::cleanNestedValue($value, (string) $key);
                if ($cleaned !== [] && $cleaned !== null) {
                    $result[FieldLabelHelper::getLabel($module, $key)] = $cleaned;
                }
                continue;
            }

            $result[FieldLabelHelper::getLabel($module, $key)] = self::formatDateValue($value, (string) $key);
        }

        return $result;
    }

    /* clean process_data entries into key/label/value triples */
    private static function cleanProcessData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $item) {
            if (!is_array($item)) continue;

            if (array_key_exists('key', $item) && array_key_exists('label', $item) && array_key_exists('value', $item)) {
                $result[] = [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'value' => self::extractDisplayValue($item['value'], $item['key']),
                ];
                continue;
            }

            if (array_key_exists('label', $item) && array_key_exists('value', $item)) {
                $result[] = [
                    'key' => $item['key'] ?? null,
                    'label' => $item['label'],
                    'value' => self::extractDisplayValue($item['value'], $item['key'] ?? $key),
                ];
            }
        }

        return $result;
    }

    /* unwrap a field value (name-object, {value} wrapper, or nested array) for display */
    private static function extractDisplayValue($value, ?string $fieldKey = null)
    {
        if ($value === null || $value === '') return $value;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
        }

        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (is_array($value)) {
            if (isset($value['name'])) return $value['name'];
            if (array_key_exists('value', $value) && count($value) <= 3) {
                return self::extractDisplayValue($value['value'], $fieldKey);
            }

            $cleaned = self::cleanNestedValue($value, $fieldKey);
            return $cleaned === [] ? null : $cleaned;
        }

        return self::formatDateValue($value, $fieldKey);
    }

    /* clean grid rows and preserve multiple named grids */
    private static function cleanGridData(array $data): array
    {
        $result = [];
        $skip = [
            'id',
            'row_id',
            '_rowid',
            'grid_record_id',
            'process_record_id',
            'created_at',
            'updated_at',
            'calibrationfrequencystartdate',
        ];

        $isMultiGrid = false;

        foreach ($data as $item) {
            if (
                is_array($item) &&
                isset($item['name']) &&
                isset($item['rows']) &&
                is_array($item['rows'])
            ) {
                $isMultiGrid = true;
                break;
            }
        }

        if ($isMultiGrid) {
            foreach ($data as $grid) {
                if (!is_array($grid)) {
                    continue;
                }

                $gridName = isset($grid['name'])
                    ? trim((string) $grid['name'])
                    : null;

                $gridLabel = isset($grid['label']) && !is_numeric($grid['label'])
                    ? trim((string) $grid['label'])
                    : $gridName;

                $rows = isset($grid['rows']) && is_array($grid['rows'])
                    ? array_values($grid['rows'])
                    : [];

                $cleanRows = [];

                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $calibrationLabel = self::getCalibrationLabel(
                        self::getCalibrationFrequency($row)
                    );

                    $cleanRow = [];

                    foreach ($row as $columnKey => $column) {
                        if (
                            in_array(
                                strtolower((string) $columnKey),
                                $skip,
                                true
                            )
                        ) {
                            continue;
                        }

                        if (!is_array($column)) {
                            $value = self::extractDisplayValue(
                                $column,
                                (string) $columnKey
                            );

                            if ($value === null || $value === '') {
                                continue;
                            }

                            $cleanRow[] = [
                                'key' => $columnKey,
                                'label' => self::getGridFieldLabel($columnKey),
                                'value' => $value,
                            ];

                            continue;
                        }

                        $fieldKey = $column['key']
                            ?? $column['Key']
                            ?? $columnKey;

                        if (
                            strtolower((string) $fieldKey) ===
                            'calibrationfrequencystartdate'
                        ) {
                            continue;
                        }

                        $fieldValue = array_key_exists('value', $column)
                            ? $column['value']
                            : ($column['Value'] ?? $column);

                        $cleanValue = self::extractDisplayValue(
                            $fieldValue,
                            $fieldKey
                        );

                        if ($fieldKey === 'monthlyCalibration') {
                            if (
                                $cleanValue !== null &&
                                $cleanValue !== []
                            ) {
                                $cleanRow[] = [
                                    'key' => 'monthlyCalibration',
                                    'label' => $calibrationLabel,
                                    'value' => $cleanValue,
                                ];
                            }

                            continue;
                        }

                        if ($cleanValue === null || $cleanValue === '') {
                            continue;
                        }

                        $cleanRow[] = [
                            'key' => $fieldKey,
                            'label' => self::getGridFieldLabel($fieldKey),
                            'value' => $cleanValue,
                        ];
                    }

                    if (!empty($cleanRow)) {
                        $cleanRows[] = $cleanRow;
                    }
                }

                if (!empty($cleanRows)) {
                    $result[] = [
                        'name' => $gridName,
                        'label' => $gridLabel,
                        'rows' => $cleanRows,
                    ];
                }
            }

            return $result;
        }

        /* legacy single-grid row format */
        foreach ($data as $row) {
            if (!is_array($row)) {
                continue;
            }

            $calibrationLabel = self::getCalibrationLabel(
                self::getCalibrationFrequency($row)
            );

            $cleanRow = [];

            foreach ($row as $columnKey => $column) {
                if (
                    in_array(
                        strtolower((string) $columnKey),
                        $skip,
                        true
                    )
                ) {
                    continue;
                }

                if (!is_array($column)) {
                    $value = self::extractDisplayValue(
                        $column,
                        (string) $columnKey
                    );

                    if ($value === null || $value === '') {
                        continue;
                    }

                    $cleanRow[] = [
                        'key' => $columnKey,
                        'label' => self::getGridFieldLabel($columnKey),
                        'value' => $value,
                    ];

                    continue;
                }

                $fieldKey = $column['key']
                    ?? $column['Key']
                    ?? $columnKey;

                if (
                    strtolower((string) $fieldKey) ===
                    'calibrationfrequencystartdate'
                ) {
                    continue;
                }

                $fieldValue = array_key_exists('value', $column)
                    ? $column['value']
                    : ($column['Value'] ?? $column);

                if ($fieldKey === 'monthlyCalibration') {
                    $cleanValue = self::cleanNestedValue(
                        $fieldValue,
                        'monthlyCalibration'
                    );

                    if ($cleanValue !== null && $cleanValue !== []) {
                        $cleanRow[] = [
                            'key' => 'monthlyCalibration',
                            'label' => $calibrationLabel,
                            'value' => $cleanValue,
                        ];
                    }

                    continue;
                }

                $cleanValue = self::extractDisplayValue(
                    $fieldValue,
                    $fieldKey
                );

                if ($cleanValue === null || $cleanValue === '') {
                    continue;
                }

                $cleanRow[] = [
                    'key' => $fieldKey,
                    'label' => self::getGridFieldLabel($fieldKey),
                    'value' => $cleanValue,
                ];
            }

            if (!empty($cleanRow)) {
                $result[] = $cleanRow;
            }
        }

        return $result;
    }

    /* find the calibrationFrequency value within a grid row */
    private static function getCalibrationFrequency(array $row): ?string
    {
        foreach ($row as $key => $column) {
            $fieldKey = $key;
            $value = $column;

            if (is_array($column)) {
                $fieldKey = $column['key'] ?? $column['Key'] ?? $key;
                $value = array_key_exists('value', $column) ? $column['value'] : ($column['Value'] ?? null);
            }

            if (strtolower((string) $fieldKey) === 'calibrationfrequency') {
                return self::extractSimpleValue($value);
            }
        }

        return null;
    }

    /* reduce a name-object / {value} wrapper / scalar down to a plain string */
    private static function extractSimpleValue($value): ?string
    {
        if ($value === null || $value === '') return null;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
        }

        if (is_array($value)) {
            if (isset($value['name'])) return (string) $value['name'];
            if (isset($value['value'])) return self::extractSimpleValue($value['value']);
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /* map a calibration frequency to its display label, e.g. "monthly" -> "Monthly Calibration" */
    private static function getCalibrationLabel(?string $frequency): string
    {
        if (!$frequency) return 'Calibration';

        $normalized = strtolower(trim(str_replace(['-', '_'], ' ', $frequency)));

        $labels = [
            'monthly' => 'Monthly Calibration',
            'quarterly' => 'Quarterly Calibration',
            'half yearly' => 'Half Yearly Calibration',
            'halfyearly' => 'Half Yearly Calibration',
            'yearly' => 'Yearly Calibration',
            'two yearly' => 'Two Yearly Calibration',
            'twoyearly' => 'Two Yearly Calibration',
            'five yearly' => 'Five Yearly Calibration',
            'fiveyearly' => 'Five Yearly Calibration',
        ];

        return $labels[$normalized] ?? ucwords($frequency) . ' Calibration';
    }

    /* known display labels for grid columns, falling back to a formatted key */
    public static function getGridFieldLabel(string $key): string
    {
        $labels = [
            'equipmentInstrumentName' => 'Equipment / Instrument Name',
            'equipmentInstrumentId' => 'Equipment / Instrument ID',
            'IdNo' => 'ID No.',
            'idNo' => 'ID No.',
            'idno' => 'ID No.',
            'masterInstrumentReadings' => 'Master Instrument Readings in',
            'unitUnderCalibrationReading' => 'Unit Under Calibration Readings in',
            'masterinstrumentreadings' => 'Master Instrument Readings in',
            'unitundercalibrationreading' => 'Unit Under Calibration Readings in',
            'department' => 'Department',
            'location' => 'Location',
            'makeModel' => 'Make & Model',
            'instrumentrange' => 'Instrument Range',
            'operatingrange' => 'Operating Range',
            'range' => 'Range',
            'range1' => 'Range 1',
            'range2' => 'Range 2',
            'leastCount' => 'Least Count',
            'accuracy' => 'Accuracy',
            'accuracy1' => 'Accuracy 1',
            'accuracy2' => 'Accuracy 2',
            'cNc' => 'C / NC',
            'calibrationFrequency' => 'Calibration Frequency',
            'previousCalibrationDate' => 'Previous / Calibration Date',
            'nextCalibrationDate' => 'Next Calibration Date',
            'alert' => 'Alert',
            'remark' => 'Remark',
            'monthlyCalibration' => 'Calibration',
            'calibrationFrequencyStartDate' => 'Calibration Frequency Start Date',
            'masterInstrumentReadings1' => 'Master Instrument Reading 1',
            'masterInstrumentReadings2' => 'Master Instrument Reading 2',
            'unitUnderCalibrationReading1' => 'Unit Under Calibration Reading 1',
            'unitUnderCalibrationReading2' => 'Unit Under Calibration Reading 2',
            'masterinstrumentreadings1' => 'Master Instrument Reading 1',
            'masterinstrumentreadings2' => 'Master Instrument Reading 2',
            'unitundercalibrationreading1' => 'Unit Under Calibration Reading 1',
            'unitundercalibrationreading2' => 'Unit Under Calibration Reading 2',
            'errorIn' => 'Error in',
            'errorin' => 'Error in',
            'schedulerDate' => 'Scheduler Date',
            'calibrationDate' => 'Calibration Date',
        ];

        return $labels[$key] ?? self::formatGridLabel($key);
    }

    /* fallback label formatter: camelCase / snake_case -> "Title Case" */
    private static function formatGridLabel(string $key): string
    {
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $key = str_replace(['_', '-'], ' ', $key);

        return ucwords($key);
    }

    /* recursively clean a nested value, labeling keys and dropping empties */
    private static function cleanNestedValue($value, ?string $parentKey = null)
    {
        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (!is_array($value)) {
            return self::isEmptyValue($value) ? null : self::formatDateValue($value, $parentKey);
        }

        $result = [];

        foreach ($value as $key => $item) {
            /* never expose calibration frequency start date anywhere nested */
            if (strtolower((string) $key) === 'calibrationfrequencystartdate') continue;

            if (is_object($item)) {
                $item = method_exists($item, 'toArray') ? $item->toArray() : (array) $item;
            }

            if (is_array($item)) {
                $cleaned = self::cleanNestedValue($item, (string) $key);
                if ($cleaned !== null && $cleaned !== []) $result[self::getNestedLabel($key)] = $cleaned;
                continue;
            }

            if (!self::isEmptyValue($item)) $result[self::getNestedLabel($key)] = self::formatDateValue($item, (string) $key);
        }

        return $result;
    }

    /* known display labels for nested field keys, falling back to a formatted key */
    private static function getNestedLabel(string $key): string
    {
        $labels = [
            'masterInstrumentReadings1' => 'Master Instrument Reading 1',
            'masterInstrumentReadings2' => 'Master Instrument Reading 2',
            'unitUnderCalibrationReading1' => 'Unit Under Calibration Reading 1',
            'unitUnderCalibrationReading2' => 'Unit Under Calibration Reading 2',
            'masterinstrumentreadings1' => 'Master Instrument Reading 1',
            'masterinstrumentreadings2' => 'Master Instrument Reading 2',
            'unitundercalibrationreading1' => 'Unit Under Calibration Reading 1',
            'unitundercalibrationreading2' => 'Unit Under Calibration Reading 2',
            'errorIn' => 'Error in',
            'errorin' => 'Error in',
            'schedulerDate' => 'Scheduler Date',
            'calibrationDate' => 'Calibration Date',
            'monthlyCalibration' => 'Calibration',
            'calibrationFrequencyStartDate' => 'Calibration Frequency Start Date',
        ];

        return $labels[$key] ?? self::formatGridLabel($key);
    }

    /* format date-like values as DD/MM/YYYY; only applies when the field name looks like a date */
    private static function formatDateValue($value, ?string $fieldKey = null)
    {
        if ($value === null || $value === '') return $value;

        $key = strtolower((string) $fieldKey);
        $isDateField = str_contains($key, 'date') || str_contains($key, '_at') || str_ends_with($key, 'at');
        if (!$isDateField) return $value;

        if ($value instanceof \DateTimeInterface) return $value->format('d/m/Y');
        if (!is_string($value)) return $value;

        $value = trim($value);

        /* try common date/datetime formats, keep only the date portion */
        $formats = [
            'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:s.u', 'Y-m-d',
            'd-m-Y H:i:s', 'd-m-Y H:i', 'd-m-Y',
            'd/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) return $date->format('d/m/Y');
            } catch (\Exception $e) {
                continue;
            }
        }

        return $value;
    }

    private static function getDepartmentName($id)
    {
        return $id ? Department::where('id', $id)->value('name') : null;
    }

    private static function getUserName($id)
    {
        return $id ? User::where('id', $id)->value('name') : null;
    }

    private static function getProcessName($id)
    {
        return $id ? Process::where('id', $id)->value('name') : null;
    }

    private static function getStageName($id)
    {
        return $id ? Stage::where('id', $id)->value('name') : null;
    }

    /* check for null / blank string / empty array */
    private static function isEmptyValue($value): bool
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return empty($value);

        return false;
    }

    /* turn a raw key into a readable label, e.g. "some_field" -> "Some Field" */
    public static function formatAuditFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }

    /* render a value (scalar, name-object, or array) for audit display */
    public static function formatAuditDisplayValue($value)
    {
        if ($value === null || $value === '') return '-';

        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (is_array($value)) {
            if (isset($value['name'])) return (string) $value['name'];
            if (isset($value['value']) && count($value) <= 3) return self::formatAuditDisplayValue($value['value']);

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    /* prepare audit history values for display */
    public static function prepareAuditHistoryValue($value)
    {
        if ($value === null) return null;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
        }

        if (is_object($value)) $value = $value->toArray();
        if (!is_array($value)) return self::isAuditEmptyValue($value) ? null : $value;

        return self::removeEmptyAuditValues($value);
    }

    /* recursively remove empty audit values */
    public static function removeEmptyAuditValues($value)
    {
        if (!is_array($value)) return self::isAuditEmptyValue($value) ? null : $value;

        $result = [];

        foreach ($value as $key => $item) {
            if (self::isAuditEmptyValue($item)) continue;

            if (is_string($item)) {
                $decoded = json_decode($item, true);
                if (json_last_error() === JSON_ERROR_NONE) $item = $decoded;
            }

            if (is_array($item)) {
                $cleaned = self::removeEmptyAuditValues($item);
                if (!empty($cleaned)) $result[$key] = $cleaned;
                continue;
            }

            if (!self::isAuditEmptyValue($item)) $result[$key] = $item;
        }

        return $result;
    }

    /* check for null / blank string / empty array */
    public static function isAuditEmptyValue($value)
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return empty($value);

        return false;
    }

    /* compare two audit values */
    public static function auditValuesAreSame($old, $new)
    {
        if (self::isAuditEmptyValue($old) && self::isAuditEmptyValue($new)) return true;

        if (is_array($old) || is_array($new)) {
            if (!is_array($old) || !is_array($new)) return false;
            return $old == $new;
        }

        if (is_object($old) || is_object($new)) {
            $old = is_object($old) ? $old->toArray() : $old;
            $new = is_object($new) ? $new->toArray() : $new;
            return self::auditValuesAreSame($old, $new);
        }

        return $old === $new;
    }

    /* render a nested audit value */
    public static function formatAuditNestedValue($value)
    {
        if ($value === null || $value === '') return '-';

        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (!is_array($value)) return (string) $value;
        if (isset($value['name'])) return (string) $value['name'];
        if (isset($value['value']) && count($value) <= 3) return self::formatAuditNestedValue($value['value']);

        $lines = [];

        foreach ($value as $key => $item) {
            if (self::isAuditEmptyValue($item)) continue;

            if (is_array($item)) {
                $nestedLines = [];

                foreach ($item as $nestedKey => $nestedValue) {
                    if (self::isAuditEmptyValue($nestedValue)) continue;
                    if (is_array($nestedValue)) $nestedValue = self::formatAuditNestedValue($nestedValue);

                    $nestedLines[] = self::formatAuditFieldLabel((string) $nestedKey) . ' : ' . $nestedValue;
                }

                if (!empty($nestedLines)) $lines[] = $key . ' : ' . implode(', ', $nestedLines);
                continue;
            }

            $lines[] = self::formatAuditFieldLabel((string) $key) . ' : ' . $item;
        }

        return empty($lines) ? '-' : implode("\n", $lines);
    }

    /* format a value for the final audit row */
    public static function formatAuditHistoryDisplayValue($value)
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

    /* format an audit date */
    public static function formatAuditDate($date, $format = 'd/m/Y H:i:s')
    {
        if ($date === null || $date === '') return null;
        if ($date instanceof \DateTimeInterface) return $date->format($format);

        try {
            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            return (string) $date;
        }
    }

    /* flatten process_data into label => value pairs */
    public static function getProcessDataValues($data)
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

    /* reduce attachment data to name/url entries */
    public static function formatAttachmentAuditValue($attachments)
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

            $result[] = [
                'name' => $name,
                'url' => $attachment['url'] ?? $attachment['Url'] ?? null,
            ];
        }

        return empty($result) ? null : $result;
    }

    /* build one display-ready audit row */
    public static function makeAuditRow($audit, $module, $oldValue, $newValue, $action = null)
    {
        return [
            'id' => $audit->id,
            'action' => $action ?? $audit->action,
            'module' => $module,
            'old_value' => self::isAuditEmptyValue($oldValue) ? null : self::formatAuditHistoryDisplayValue($oldValue),
            'new_value' => self::isAuditEmptyValue($newValue) ? null : self::formatAuditHistoryDisplayValue($newValue),
            'comment' => null,
            'responsible_person' => $audit->user?->name ?? '-',
            'user_id' => $audit->user_id,
            'record_id' => $audit->record_id,
            'model' => $audit->model,
            'created_at' => self::formatAuditDate($audit->created_at, 'd-m-Y H:i:s'),
        ];
    }

    /* activity audit row */
    public static function prepareActivityAudit($audit, $oldValue, $newValue)
    {
        $oldValue = is_array($oldValue) ? $oldValue : [];
        $newValue = is_array($newValue) ? $newValue : [];

        $activityName = $newValue['activity'] ?? $oldValue['activity'] ?? $audit->module ?? 'Activity';
        $oldStage = $oldValue['stage'] ?? null;
        $newStage = $newValue['stage'] ?? null;
        $comment = $newValue['comment'] ?? null;

        if (self::isAuditEmptyValue($comment)) $comment = $audit->description ?? null;

        if (self::isAuditEmptyValue($oldStage) && self::isAuditEmptyValue($newStage) && self::isAuditEmptyValue($comment)) {
            return null;
        }

        return [
            'id' => $audit->id,
            'action' => 'Activity Performed',
            'module' => $activityName,
            'old_value' => self::isAuditEmptyValue($oldStage) ? null : self::formatAuditHistoryDisplayValue($oldStage),
            'new_value' => self::isAuditEmptyValue($newStage) ? null : self::formatAuditHistoryDisplayValue($newStage),
            'comment' => self::isAuditEmptyValue($comment) ? null : self::formatAuditHistoryDisplayValue($comment),
            'responsible_person' => $audit->user?->name ?? '-',
            'user_id' => $audit->user_id,
            'record_id' => $audit->record_id,
            'model' => $audit->model,
            'created_at' => self::formatAuditDate($audit->created_at, 'd/m/Y H:i:s'),
        ];
    }

    /* pull raw grid rows from either supported audit key */
    public static function extractGridRows($value)
    {
        if (!is_array($value)) return [];
        if (isset($value['grid_data']) && is_array($value['grid_data'])) return array_values($value['grid_data']);
        if (isset($value['gridData']) && is_array($value['gridData'])) return array_values($value['gridData']);
        if (isset($value[0]) && is_array($value[0])) return array_values($value);

        return [];
    }

    /* format every non-empty grid field */
    public static function formatGridFieldsForAudit($fields)
    {
        if (!is_array($fields)) return null;

        $lines = [];

        foreach ($fields as $key => $field) {
            if (is_array($field) && (array_key_exists('label', $field) || array_key_exists('value', $field))) {
                $label = $field['label'] ?? self::formatAuditFieldLabel($key);
                $value = $field['value'] ?? null;
            } elseif (!is_array($field)) {
                $label = self::formatAuditFieldLabel($key);
                $value = $field;
            } else {
                continue;
            }

            if (self::isAuditEmptyValue($value) || self::isAuditEmptyValue($label)) continue;

            $lines[] = $label . ' : ' . self::formatAuditNestedValue($value);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    /* format only changed grid fields */
    public static function formatGridFieldListForAudit($fields)
    {
        if (!is_array($fields) || empty($fields)) return null;

        $lines = [];

        foreach ($fields as $field) {
            if (!is_array($field)) continue;

            $label = $field['label'] ?? null;
            $value = $field['value'] ?? null;
            if (self::isAuditEmptyValue($label) || self::isAuditEmptyValue($value)) continue;

            $lines[] = $label . ' : ' . self::formatAuditNestedValue($value);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    /* pull raw checklist rows from either supported audit key */
    public static function extractChecklistRows($value)
    {
        if (!is_array($value)) return [];
        if (isset($value['checklist_data']) && is_array($value['checklist_data'])) return array_values($value['checklist_data']);
        if (isset($value[0]) && is_array($value[0])) return array_values($value);

        return [];
    }

}