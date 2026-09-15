<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\EquipmentMaster;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CalibrationManagementReportService
{
    /* generate calibration management report */
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with(['process', 'stage', 'department', 'initiator', 'gridRecords'])->findOrFail($id);

            /* process data */
            $processData = $record->process_data;
            if (is_string($processData)) {
                $processData = json_decode($processData, true);
            }
            if (!is_array($processData)) {
                $processData = [];
            }

            /* prepare process fields */
            $processFields = [];
            foreach ($processData as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $key = $field['key'] ?? null;
                if (!$key) {
                    continue;
                }
                $processFields[$key] = [
                    'key' => $key,
                    'label' => $field['label'] ?? self::formatFieldLabel($key),
                    'value' => $field['value'] ?? null,
                ];
            }

            /* resolve instrument name from equipment master */
            if (isset($processFields['instrumentName'])) {
                $instrumentId = $processFields['instrumentName']['value'] ?? null;
                if ($instrumentId !== null && $instrumentId !== '' && is_numeric($instrumentId)) {
                    $equipment = EquipmentMaster::find($instrumentId);
                    if ($equipment) {
                        $processFields['instrumentName']['value'] = $equipment->name;
                    }
                }
            }

            /* get record attachments */
            $recordAttachments = $record->attachments;
            if (is_string($recordAttachments)) {
                $decoded = json_decode($recordAttachments, true);
                $recordAttachments = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($recordAttachments)) {
                $recordAttachments = [];
            }

            /* map attachments to process fields */
            foreach ($processFields as $key => &$field) {
                if ($key === 'attachment' || str_contains(strtolower($key), 'attachment')) {
                    $field['value'] = self::getAttachmentsByField($recordAttachments, $key);
                }
            }
            unset($field);

            /* basic record information */
            $recordNumber = self::getProcessFieldValue($processFields, 'recordNumber');
            $siteLocationCode = self::getProcessFieldValue($processFields, 'siteLocationCode');

            /* general information */
            $systemInformation = self::prepareReportFields(self::getFieldsByKeys($processFields, [
                'recordNumber', 'siteLocationCode', 'initiator', 'dateOfInitiation',
                'initiationDepartment', 'shortDescription',
            ]));

            $instrumentEquipmentFields = self::prepareReportFields(self::getFieldsByKeys($processFields, [
                'instrumentName', 'instrumentId', 'location', 'make', 'model', 'instrumentRange',
                'leastCount', 'accuracy', 'calibrationTestPoints', 'operatingRange',
                'envTemperature', 'envHumidity', 'previousCalibrationDate', 'nextCalibrationDate',
            ]));

            $commentsFields = self::prepareReportFields(self::getFieldsByKeys($processFields, ['comments']));

            $attachmentFields = self::prepareReportFields(self::getFieldsByKeys($processFields, ['attachment']));

            /* HOD / designee review */
            $hodReviewFields = self::prepareReportFields(self::getFieldsByKeys($processFields, [
                'implementorComments', 'implementorAttachment',
            ]));

            /* QA review & approval */
            $qaReviewFields = self::prepareReportFields(self::getFieldsByKeys($processFields, [
                'qaReviewComments', 'qaReviewAttachment',
            ]));

            /* grid data */
            $gridResult = self::prepareGridData($record->gridRecords ?? []);
            $grids = $gridResult['grids'];
            $monthlyCalibration = $gridResult['monthlyCalibration'];

            /* report tabs */
            $tabs = [
                [
                    'tab_title' => 'General Information',
                    'sections' => [
                        ['title' => 'System Information', 'fields' => $systemInformation],
                        ['title' => 'Instrument / Equipment Details', 'fields' => $instrumentEquipmentFields],
                        ['title' => 'Comments', 'fields' => $commentsFields],
                        ['title' => 'Attachment', 'fields' => $attachmentFields],
                    ],
                ],
                [
                    'tab_title' => 'HOD/Designee Review',
                    'sections' => [
                        ['title' => 'HOD/Designee Review', 'fields' => $hodReviewFields],
                    ],
                ],
                [
                    'tab_title' => 'QA Review & Approval',
                    'sections' => [
                        ['title' => 'QA Review & Approval', 'fields' => $qaReviewFields],
                    ],
                ],
            ];

            /* report data */
            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => $record->process?->name ?? 'Calibration Management',
                ],
                'record' => [
                    'record_id' => $record->id,
                    'record_number' => $recordNumber,
                    'site_location_code' => $siteLocationCode,
                    'process' => $record->process?->name,
                    'stage' => $record->stage?->name,
                    'department' => $record->department?->name,
                    'initiator' => $record->initiator?->name,
                    'short_description' => $record->short_description,
                    'initiation_date' => self::formatReportDate($record->initiation_date, 'd-m-Y H:i'),
                ],
                'tabs' => $tabs,
                /* calibrationResults, testResults = masterInstrumentsDetails */
                'grids' => $grids,
                'monthlyCalibration' => $monthlyCalibration,
                'footer' => [
                    'generated_by' => auth()->user()?->name,
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            /* generate PDF */
            $pdf = Pdf::loadView('Reports.User.CalibrationManagementReport', $data);
            $pdf->setPaper('a4', 'portrait');

            return $pdf->stream('calibration-management-' . $record->id . '.pdf');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /* prepare grid data */
    private static function prepareGridData($gridRecords)
    {
        $grids = [];
        $monthlyCalibration = [];

        foreach ($gridRecords as $gridRecord) {
            $gridData = $gridRecord->grid_data ?? null;
            if (is_string($gridData)) {
                $gridData = json_decode($gridData, true);
            }
            if (!is_array($gridData)) {
                continue;
            }

            /* support both [grid, grid] and a single grid object */
            if (isset($gridData['name'])) {
                $gridData = [$gridData];
            }

            foreach ($gridData as $grid) {
                if (!is_array($grid)) {
                    continue;
                }
                $gridName = $grid['name'] ?? null;
                if (!$gridName) {
                    continue;
                }

                $rows = $grid['rows'] ?? [];
                if (!is_array($rows)) {
                    $rows = [];
                }

                $preparedRows = [];
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    if (isset($row['monthlyCalibration']) && is_array($row['monthlyCalibration'])) {
                        $monthlyCalibration = $row['monthlyCalibration'];
                    }

                    $preparedRow = [];
                    foreach ($row as $key => $field) {
                        if ($key === 'row_id' || $key === 'monthlyCalibration') {
                            if ($key === 'row_id') {
                                $preparedRow['row_id'] = $field;
                            }
                            continue;
                        }

                        $fieldValue = is_array($field) ? ($field['value'] ?? null) : $field;
                        $storedLabel = is_array($field) ? ($field['label'] ?? null) : null;

                        $preparedRow[$key] = [
                            'key' => is_array($field) ? ($field['key'] ?? $key) : $key,
                            'label' => self::formatGridColumnLabel($key, $gridName, $storedLabel),
                            'value' => $fieldValue,
                        ];
                    }

                    if (!empty($preparedRow)) {
                        $preparedRows[] = $preparedRow;
                    }
                }

                if ($gridName === 'calibrationResults') {
                    $preparedRows = self::prepareCalibrationResultRows($preparedRows);
                } elseif ($gridName === 'masterInstrumentsDetails') {
                    $preparedRows = self::prepareMasterInstrumentRows($preparedRows);
                }

                /* keep original grid name so every grid is available to the report */
                $grids[$gridName] = [
                    'name' => $gridName,
                    'label' => self::getGridTitle($gridName),
                    'rows' => $preparedRows,
                ];
            }
        }

        return ['grids' => $grids, 'monthlyCalibration' => $monthlyCalibration];
    }

    /* prepare calibration result rows */
    private static function prepareCalibrationResultRows($rows)
    {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            /* if the base field has a value, the numbered columns are hidden */
            $masterBaseValue = self::getRowFieldValue($row, 'masterInstrumentReadings');
            $unitBaseValue = self::getRowFieldValue($row, 'unitUnderCalibrationReading');

            $preparedRow = [];
            foreach ($row as $key => $field) {
                if ($key === 'row_id') {
                    $preparedRow['row_id'] = $field;
                    continue;
                }
                if (strtolower($key) === 'result') {
                    continue;
                }

                $lowerKey = strtolower((string) $key);

                if ($masterBaseValue !== null && $masterBaseValue !== '' && preg_match('/^masterinstrumentreadings\d+$/i', $key)) {
                    continue;
                }
                if ($unitBaseValue !== null && $unitBaseValue !== '' && preg_match('/^unitundercalibrationreading\d+$/i', $key)) {
                    continue;
                }

                $fieldValue = is_array($field) ? ($field['value'] ?? null) : $field;
                $fieldLabel = self::formatGridColumnLabel($key, 'calibrationResults', is_array($field) ? ($field['label'] ?? null) : null);

                if (str_starts_with($lowerKey, 'masterinstrumentreadings') || str_starts_with($lowerKey, 'reading')) {
                    $group = 'Master Instrument Readings in';
                } elseif (str_starts_with($lowerKey, 'unitundercalibrationreading') || str_starts_with($lowerKey, 'result')) {
                    $group = 'Unit under calibration Readings in';
                } elseif ($lowerKey === 'error' || $lowerKey === 'errorin') {
                    $group = 'Error in';
                } else {
                    $group = null;
                }

                $preparedRow[$key] = [
                    'key' => $key,
                    'label' => $fieldLabel,
                    'value' => $fieldValue,
                    'group' => $group,
                ];
            }

            if (!empty($preparedRow)) {
                $result[] = $preparedRow;
            }
        }

        return $result;
    }

    /* get a grid row field value */
    private static function getRowFieldValue($row, $key)
    {
        if (!array_key_exists($key, $row)) {
            return null;
        }

        $field = $row[$key];

        return is_array($field) ? ($field['value'] ?? null) : $field;
    }

    /* prepare master instrument detail rows */
    private static function prepareMasterInstrumentRows($rows)
    {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $preparedRow = [];

            /* hide numbered columns when the main field has data */
            $accuracyValue = self::getRowFieldValue($row, 'accuracy');
            $rangeValue = self::getRowFieldValue($row, 'range');

            foreach ($row as $key => $field) {
                if ($key === 'row_id') {
                    $preparedRow['row_id'] = $field;
                    continue;
                }

                if ($accuracyValue !== null && $accuracyValue !== '' && preg_match('/^accuracy\d+$/i', $key)) {
                    continue;
                }
                if ($rangeValue !== null && $rangeValue !== '' && preg_match('/^range\d+$/i', $key)) {
                    continue;
                }

                $fieldValue = is_array($field) ? ($field['value'] ?? null) : $field;

                $preparedRow[$key] = [
                    'key' => $key,
                    'label' => self::formatGridColumnLabel($key, 'masterInstrumentsDetails', is_array($field) ? ($field['label'] ?? null) : null),
                    'value' => $fieldValue,
                ];
            }

            if (!empty($preparedRow)) {
                $result[] = $preparedRow;
            }
        }

        return $result;
    }

    /* grid title */
    private static function getGridTitle($gridName)
    {
        $titles = [
            'calibrationResults' => 'Calibration Results',
            'masterInstrumentsDetails' => 'Master Instruments Details',
            'testResults' => 'Master Instruments Details',
        ];

        return $titles[$gridName] ?? self::formatFieldLabel($gridName);
    }

    /* grid column label */
    private static function formatGridColumnLabel($key, $gridName = null, $storedLabel = null)
    {
        $lowerKey = strtolower((string) $key);

        if ($gridName === 'calibrationResults') {
            $labels = [
                'masterinstrumentreadings' => 'Master Instrument Readings in',
                'masterinstrumentreadings1' => 'Master Instrument Readings 1',
                'masterinstrumentreadings2' => 'Master Instrument Readings 2',
                'unitundercalibrationreading' => 'Unit Under Calibration Readings in',
                'unitundercalibrationreading1' => 'Unit Under Calibration Readings 1',
                'unitundercalibrationreading2' => 'Unit Under Calibration Readings 2',
                'errorin' => 'Error in',
                'error' => 'Error',
            ];

            if (isset($labels[$lowerKey])) {
                return $labels[$lowerKey];
            }
            if (preg_match('/^reading(\d+)$/i', $key, $matches)) {
                return 'Reading ' . $matches[1];
            }
            if (preg_match('/^result(\d+)$/i', $key, $matches)) {
                return 'Result ' . $matches[1];
            }
        }

        if ($gridName === 'masterInstrumentsDetails' || $gridName === 'testResults') {
            $labels = [
                'name' => 'Name',
                'idno' => 'ID No.',
                'accuracy' => 'Accuracy',
                'accuracy1' => 'Accuracy 1',
                'accuracy2' => 'Accuracy 2',
                'range' => 'Range',
                'range1' => 'Range 1',
                'range2' => 'Range 2',
                'calibrationdonedate' => 'Calibration Done Date',
                'calibrationnewduedate' => 'Calibration New Due Date',
                'parameter' => 'Parameter',
                'result' => 'Result',
                'error' => 'Error',
            ];

            if (isset($labels[$lowerKey])) {
                return $labels[$lowerKey];
            }
        }

        /* prefer actual stored UI label when it is meaningful */
        if ($storedLabel !== null && trim((string) $storedLabel) !== '' && strtolower(trim((string) $storedLabel)) !== strtolower((string) $key)) {
            return $storedLabel;
        }

        return self::formatFieldLabel($key);
    }

    /* attachments by field */
    private static function getAttachmentsByField($attachments, $attachmentField)
    {
        if (!is_array($attachments)) {
            return [];
        }

        $result = [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }
            if (($attachment['attachment_field'] ?? null) !== $attachmentField) {
                continue;
            }

            $result[] = [
                'attachment_field' => $attachmentField,
                'name' => $attachment['name'] ?? $attachment['file_name'] ?? 'Attachment',
                'file_name' => $attachment['file_name'] ?? null,
                'path' => $attachment['path'] ?? null,
                'url' => $attachment['url'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
                'size' => $attachment['size'] ?? null,
            ];
        }

        return $result;
    }

    /* process field value */
    private static function getProcessFieldValue($fields, $key)
    {
        return $fields[$key]['value'] ?? null;
    }

    /* get fields by keys */
    private static function getFieldsByKeys($fields, array $keys)
    {
        $result = [];
        foreach ($keys as $key) {
            if (isset($fields[$key])) {
                $result[] = $fields[$key];
            }
        }

        return $result;
    }

    /* prepare report fields */
    private static function prepareReportFields($fields)
    {
        $result = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $value = $field['value'] ?? null;
            $key = $field['key'] ?? '';

            /* attachments are rendered by Blade */
            if ($key === 'attachment' || str_contains(strtolower($key), 'attachment')) {
                if (!is_array($value) || empty($value)) {
                    $value = '-';
                }
            } elseif ($key === 'instrumentName') {
                if (is_numeric($value) && $value !== '') {
                    $equipment = EquipmentMaster::find($value);
                    if ($equipment) {
                        $value = $equipment->name;
                    }
                }
                if ($value === null || $value === '') {
                    $value = '-';
                }
            } elseif (is_array($value)) {
                if (isset($value['name'])) {
                    $value = $value['name'];
                } elseif (empty($value)) {
                    $value = '-';
                } else {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            if ($value === null || $value === '') {
                $value = '-';
            }

            $result[] = [
                'key' => $key,
                'label' => $field['label'] ?? '-',
                'value' => $value,
            ];
        }

        return $result;
    }

    /* safe report date */
    private static function formatReportDate($date, $format = 'd-m-Y H:i')
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format($format);
        }

        $date = trim((string) $date);

        /* app stores dates like: 14/09/2026 13:31 */
        $formats = [
            'd/m/Y H:i', 'd/m/Y H:i:s',
            'd-m-Y H:i', 'd-m-Y H:i:s',
            'Y-m-d H:i', 'Y-m-d H:i:s',
            'Y-m-d\TH:i', 'Y-m-d\TH:i:s',
        ];

        foreach ($formats as $inputFormat) {
            try {
                return Carbon::createFromFormat($inputFormat, $date)->format($format);
            } catch (\Exception $e) {
                continue;
            }
        }

        /* fallback for normal database dates */
        try {
            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            return $date;
        }
    }

    /* format field label */
    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', (string) $key);
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }
}