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
    /* known foreign-id fields resolved to a display name in cleanArray() */
    private const ID_FIELDS = [
        'department_id' => ['label' => 'Department', 'model' => Department::class],
        'initiator_id' => ['label' => 'Initiator', 'model' => User::class],
        'process_id' => ['label' => 'Process', 'model' => Process::class],
        'stage_id' => ['label' => 'Stage', 'model' => Stage::class],
    ];

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

            if (isset(self::ID_FIELDS[$key])) {
                $meta = self::ID_FIELDS[$key];
                $result[$meta['label']] = self::resolveName($value, $meta['model']);
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

    /* clean grid rows: strip technical columns, relabel calibration field by frequency */
    private static function cleanGridData(array $data): array
    {
        $result = [];
        $skip = ['id', 'row_id', '_rowid', 'grid_record_id', 'process_record_id', 'created_at', 'updated_at', 'calibrationfrequencystartdate'];

        foreach ($data as $row) {
            if (!is_array($row)) continue;

            /* resolve calibration frequency first so monthlyCalibration can be relabeled (Monthly / Quarterly / etc.) */
            $calibrationLabel = self::getCalibrationLabel(self::getCalibrationFrequency($row));
            $cleanRow = [];

            foreach ($row as $columnKey => $column) {
                if (in_array(strtolower((string) $columnKey), $skip, true)) continue;

                if (!is_array($column)) {
                    $cleanRow[] = [
                        'key' => $columnKey,
                        'label' => self::getGridFieldLabel($columnKey),
                        'value' => self::extractDisplayValue($column, $columnKey),
                    ];
                    continue;
                }

                $fieldKey = $column['key'] ?? $column['Key'] ?? $columnKey;
                if (strtolower((string) $fieldKey) === 'calibrationfrequencystartdate') continue;

                $fieldValue = array_key_exists('value', $column) ? $column['value'] : ($column['Value'] ?? $column);

                /* calibration schedule field keeps its internal key but its label follows the frequency */
                if ($fieldKey === 'monthlyCalibration') {
                    $cleanValue = self::cleanNestedValue($fieldValue, 'monthlyCalibration');
                    if ($cleanValue !== null && $cleanValue !== []) {
                        $cleanRow[] = ['key' => 'monthlyCalibration', 'label' => $calibrationLabel, 'value' => $cleanValue];
                    }
                    continue;
                }

                $cleanRow[] = [
                    'key' => $fieldKey,
                    'label' => self::getGridFieldLabel($fieldKey),
                    'value' => self::extractDisplayValue($fieldValue, $fieldKey),
                ];
            }

            if (!empty($cleanRow)) $result[] = $cleanRow;
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
            'category' => 'Category',
            'location' => 'Location',
            'makeModel' => 'Make & Model',
            'instrumentrange' => 'Instrument Range',
            'operatingrange' => 'Operating Range',
            'range' => 'Range',
            'leastCount' => 'Least Count',
            'accuracy' => 'Accuracy',
            'cNc' => 'C / NC',
            'calibrationFrequency' => 'Calibration Frequency',
            'previousCalibrationDate' => 'Previous / Calibration Date',
            'nextCalibrationDate' => 'Next Calibration Date',
            'alert' => 'Alert',
            'remark' => 'Remark',
            'monthlyCalibration' => 'Calibration',
            'calibrationFrequencyStartDate' => 'Calibration Frequency Start Date',
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

    /* resolve a foreign id to its model's "name" column */
    private static function resolveName($id, string $modelClass): ?string
    {
        return $id ? $modelClass::where('id', $id)->value('name') : null;
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
}