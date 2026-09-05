<?php

namespace App\Helpers;

use App\Models\Audit;
use App\Models\Department;
use App\Models\Process;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserAuditHelper
{
    /* create user audit */
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

    /* prepare audit data */
    private static function prepareValue($value, string $module)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = method_exists($value, 'toArray')
                ? $value->toArray()
                : (array) $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        return self::cleanArray($value, $module);
    }

    /* clean array data */
    private static function cleanArray(array $data, string $module)
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (
                in_array($key, [
                    'id',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ], true)
            ) {
                continue;
            }

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
                $decoded = is_string($value)
                    ? json_decode($value, true)
                    : $value;

                if (is_array($decoded)) {
                    $result['process_data'] = self::cleanProcessData($decoded);
                }

                continue;
            }

            if ($key === 'grid_data') {
                $decoded = is_string($value)
                    ? json_decode($value, true)
                    : $value;

                if (is_array($decoded)) {
                    $result['grid_data'] = self::cleanGridData($decoded);
                }

                continue;
            }

            if (is_array($value)) {
                $cleaned = self::cleanNestedValue($value);

                if ($cleaned !== [] && $cleaned !== null) {
                    $result[FieldLabelHelper::getLabel($module, $key)] = $cleaned;
                }

                continue;
            }

            $result[FieldLabelHelper::getLabel($module, $key)] = $value;
        }

        return $result;
    }

    /* clean process data */
    private static function cleanProcessData(array $data): array
    {
        $result = [];

        foreach ($data as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            if (
                array_key_exists('key', $item) &&
                array_key_exists('label', $item) &&
                array_key_exists('value', $item)
            ) {
                $result[] = [
                    'key' => $item['key'],
                    'label' => $item['label'],
                    'value' => self::extractDisplayValue($item['value']),
                ];

                continue;
            }

            if (
                array_key_exists('label', $item) &&
                array_key_exists('value', $item)
            ) {
                $result[] = [
                    'key' => $item['key'] ?? null,
                    'label' => $item['label'],
                    'value' => self::extractDisplayValue($item['value']),
                ];
            }
        }

        return $result;
    }

    /* extract audit display value */
    private static function extractDisplayValue($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = method_exists($value, 'toArray')
                ? $value->toArray()
                : (array) $value;
        }

        if (is_array($value)) {
            if (isset($value['name'])) {
                return $value['name'];
            }

            if (
                array_key_exists('value', $value) &&
                count($value) <= 3
            ) {
                return self::extractDisplayValue($value['value']);
            }

            $cleaned = self::cleanNestedValue($value);

            return $cleaned === [] ? null : $cleaned;
        }

        return $value;
    }

    /* clean grid data */
    private static function cleanGridData(array $data): array
    {
        $result = [];

        foreach ($data as $rowIndex => $row) {
            if (!is_array($row)) {
                continue;
            }

            $cleanRow = [];

            foreach ($row as $columnKey => $column) {
                if (
                    in_array(strtolower((string) $columnKey), [
                        'id',
                        'row_id',
                        '_rowid',
                        'grid_record_id',
                        'process_record_id',
                        'created_at',
                        'updated_at',
                    ], true)
                ) {
                    continue;
                }

                if (!is_array($column)) {
                    $cleanRow[] = [
                        'key' => $columnKey,
                        'label' => self::getGridFieldLabel($columnKey),
                        'value' => self::extractDisplayValue($column),
                    ];

                    continue;
                }

                $fieldKey = $column['key']
                    ?? $column['Key']
                    ?? $columnKey;

                $fieldValue = array_key_exists('value', $column)
                    ? $column['value']
                    : ($column['Value'] ?? $column);

                if ($fieldKey === 'monthlyCalibration') {
                    $cleanValue = self::cleanNestedValue($fieldValue);

                    if ($cleanValue !== null && $cleanValue !== []) {
                        $cleanRow[] = [
                            'key' => 'monthlyCalibration',
                            'label' => 'Monthly Calibration',
                            'value' => $cleanValue,
                        ];
                    }

                    continue;
                }

                $cleanRow[] = [
                    'key' => $fieldKey,
                    'label' => self::getGridFieldLabel($fieldKey),
                    'value' => self::extractDisplayValue($fieldValue),
                ];
            }

            if (!empty($cleanRow)) {
                $result[] = $cleanRow;
            }
        }

        return $result;
    }


    /* get grid field label */
    private static function getGridFieldLabel(string $key): string
    {
        $labels = [
            'equipmentInstrumentName' => 'Equipment / Instrument Name',
            'equipmentInstrumentId' => 'Equipment / Instrument ID',
            'department' => 'Department',
            'location' => 'Location',
            'makeModel' => 'Make & Model',
            'range' => 'Range',
            'leastCount' => 'Least Count',
            'accuracy' => 'Accuracy',
            'cNc' => 'C / NC',
            'calibrationFrequency' => 'Calibration Frequency',
            'previousCalibrationDate' => 'Previous / Calibration Date',
            'nextCalibrationDate' => 'Next Calibration Date',
            'alert' => 'Alert',
            'remark' => 'Remark',
            'monthlyCalibration' => 'Monthly Calibration',
            'calibrationFrequencyStartDate' => 'Calibration Frequency Start Date',
            'schedulerDate' => 'Scheduler Date',
            'calibrationDate' => 'Calibration Date',
        ];

        return $labels[$key] ?? self::formatGridLabel($key);
    }

    /* format grid label */
    private static function formatGridLabel(string $key): string
    {
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $key = str_replace(['_', '-'], ' ', $key);

        return ucwords($key);
    }

    /* clean nested values */
    private static function cleanNestedValue($value)
    {
        if (is_object($value)) {
            $value = method_exists($value, 'toArray')
                ? $value->toArray()
                : (array) $value;
        }

        if (!is_array($value)) {
            return self::isEmptyValue($value) ? null : $value;
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_object($item)) {
                $item = method_exists($item, 'toArray')
                    ? $item->toArray()
                    : (array) $item;
            }

            if (is_array($item)) {
                $cleaned = self::cleanNestedValue($item);

                if ($cleaned !== null && $cleaned !== []) {
                    $result[self::getNestedLabel($key)] = $cleaned;
                }

                continue;
            }

            if (!self::isEmptyValue($item)) {
                $result[self::getNestedLabel($key)] = $item;
            }
        }

        return $result;
    }

    /* get nested label */
    private static function getNestedLabel(string $key): string
    {
        $labels = [
            'schedulerDate' => 'Scheduler Date',
            'calibrationDate' => 'Calibration Date',
            'monthlyCalibration' => 'Monthly Calibration',
            'calibrationFrequencyStartDate' => 'Calibration Frequency Start Date',
        ];

        return $labels[$key] ?? self::formatGridLabel($key);
    }

    /* get department name */
    private static function getDepartmentName($id)
    {
        return $id
            ? Department::where('id', $id)->value('name')
            : null;
    }

    /* get user name */
    private static function getUserName($id)
    {
        return $id
            ? User::where('id', $id)->value('name')
            : null;
    }

    /* get process name */
    private static function getProcessName($id)
    {
        return $id
            ? Process::where('id', $id)->value('name')
            : null;
    }

    /* get stage name */
    private static function getStageName($id)
    {
        return $id
            ? Stage::where('id', $id)->value('name')
            : null;
    }

    /* check empty value */
    private static function isEmptyValue($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return empty($value);
        }

        return false;
    }
}