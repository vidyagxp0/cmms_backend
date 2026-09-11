<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;

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

            /* get record attachments */
            $recordAttachments = $record->attachments;
            if (is_string($recordAttachments)) {
                $decodedAttachments = json_decode($recordAttachments, true);
                $recordAttachments = is_array($decodedAttachments) ? $decodedAttachments : [];
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

            /* system information */
            $systemInformation = self::getFieldsByKeys($processFields, [
                'recordNumber',
                'siteLocationCode',
                'initiator',
                'dateOfInitiation',
                'initiationDepartment',
                'shortDescription',
            ]);
            $systemInformation = self::prepareReportFields($systemInformation);

            /* instrument / equipment details */
            $instrumentEquipmentFields = self::getFieldsByKeys($processFields, [
                'instrumentName',
                'instrumentId',
                'location',
                'make',
                'model',
                'instrumentRange',
                'leastCount',
                'accuracy',
                'calibrationTestPoints',
                'operatingRange',
                'envTemperature',
                'envHumidity',
                'previousCalibrationDate',
                'nextCalibrationDate',
            ]);
            $instrumentEquipmentFields = self::prepareReportFields($instrumentEquipmentFields);

            /* comments */
            $commentsFields = self::getFieldsByKeys($processFields, ['comments']);
            $commentsFields = self::prepareReportFields($commentsFields);

            /* attachment */
            $attachmentFields = self::getFieldsByKeys($processFields, ['attachment']);
            $attachmentFields = self::prepareReportFields($attachmentFields);

            /* HOD / designee review */
            $hodReviewFields = self::getFieldsByKeys($processFields, ['implementorComments', 'implementorAttachment']);
            $hodReviewFields = self::prepareReportFields($hodReviewFields);

            /* QA review & approval */
            $qaReviewFields = self::getFieldsByKeys($processFields, ['qaReviewComments', 'qaReviewAttachment']);
            $qaReviewFields = self::prepareReportFields($qaReviewFields);

            /* grid data */
            $gridResult = self::prepareGridData($record->gridRecords ?? []);
            $grids = $gridResult['grids'];
            $monthlyCalibration = $gridResult['monthlyCalibration'];

            /* report tabs */
            $tabs = [];

            /* general information tab */
            $tabs[] = [
                'tab_title' => 'General Information',
                'sections' => [
                    ['title' => 'System Information', 'fields' => $systemInformation],
                    ['title' => 'Instrument / Equipment Details', 'fields' => $instrumentEquipmentFields],
                    ['title' => 'Comments', 'fields' => $commentsFields],
                    ['title' => 'Attachment', 'fields' => $attachmentFields],
                ],
            ];

            /* HOD / designee review tab */
            $tabs[] = [
                'tab_title' => 'HOD / Designee Review',
                'sections' => [
                    ['title' => 'HOD / Designee Review', 'fields' => $hodReviewFields],
                ],
            ];

            /* QA review & approval tab */
            $tabs[] = [
                'tab_title' => 'QA Review & Approval',
                'sections' => [
                    ['title' => 'QA Review & Approval', 'fields' => $qaReviewFields],
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
                    'initiation_date' => $record->initiation_date
                        ? \Carbon\Carbon::parse($record->initiation_date)->format('d-m-Y H:i')
                        : null,
                ],
                'tabs' => $tabs,
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
            $gridData = $gridRecord->grid_data;
            if (is_string($gridData)) {
                $gridData = json_decode($gridData, true);
            }
            if (!is_array($gridData)) {
                continue;
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

                foreach ($rows as $rowIndex => $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    /* extract monthly calibration from the row */
                    if (isset($row['monthlyCalibration']) && is_array($row['monthlyCalibration'])) {
                        $monthlyCalibration = $row['monthlyCalibration'];
                    }

                    $preparedRow = [];

                    foreach ($row as $key => $field) {
                        /* row_id is internal */
                        if ($key === 'row_id') {
                            $preparedRow['row_id'] = $field;
                            continue;
                        }

                        /* monthlyCalibration is rendered separately */
                        if ($key === 'monthlyCalibration') {
                            continue;
                        }

                        if (is_array($field)) {
                            $preparedRow[$key] = [
                                'key' => $field['key'] ?? $key,
                                'label' => $field['label'] ?? self::formatGridColumnLabel($key, $gridName),
                                'value' => $field['value'] ?? null,
                            ];
                        } else {
                            $preparedRow[$key] = [
                                'key' => $key,
                                'label' => self::formatGridColumnLabel($key, $gridName),
                                'value' => $field,
                            ];
                        }
                    }

                    if (!empty($preparedRow)) {
                        $preparedRows[] = $preparedRow;
                    }
                }

                /* special preparation for calibration results */
                if ($gridName === 'calibrationResults') {
                    $preparedRows = self::prepareCalibrationResultRows($preparedRows);
                }

                /* special preparation for master instrument details */
                if ($gridName === 'testResults') {
                    $preparedRows = self::prepareTestResultRows($preparedRows);
                }

                $grids[$gridName] = [
                    'name' => $gridName,
                    'label' => self::getGridTitle($gridName),
                    'rows' => $preparedRows,
                ];
            }
        }

        return [
            'grids' => $grids,
            'monthlyCalibration' => $monthlyCalibration,
        ];
    }

    /* prepare calibration result rows */
    private static function prepareCalibrationResultRows($rows)
    {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $preparedRow = [];

            foreach ($row as $key => $field) {
                /* never show the final "result" column */
                if (strtolower($key) === 'result') {
                    continue;
                }

                if ($key === 'row_id') {
                    $preparedRow['row_id'] = $field;
                    continue;
                }

                $fieldValue = is_array($field) ? ($field['value'] ?? null) : $field;

                /* reading columns */
                if (preg_match('/^reading(\d+)$/i', $key, $matches)) {
                    $fieldLabel = 'Reading ' . $matches[1];
                    $group = 'Master Instrument Readings in';
                }
                /* result columns */
                elseif (preg_match('/^result(\d+)$/i', $key, $matches)) {
                    $fieldLabel = 'Result ' . $matches[1];
                    $group = 'Unit under calibration Readings in';
                }
                /* error column */
                elseif (strtolower($key) === 'error') {
                    $fieldLabel = 'Error';
                    $group = 'Error in';
                }
                /* any other field */
                else {
                    $fieldLabel = self::formatGridColumnLabel($key, 'calibrationResults');
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

    /* prepare master instrument details */
    private static function prepareTestResultRows($rows)
    {
        $result = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $preparedRow = [];

            foreach ($row as $key => $field) {
                if ($key === 'row_id') {
                    $preparedRow['row_id'] = $field;
                    continue;
                }

                $fieldValue = is_array($field) ? ($field['value'] ?? null) : $field;

                $preparedRow[$key] = [
                    'key' => $key,
                    'label' => self::formatGridColumnLabel($key, 'testResults'),
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
            'testResults' => 'Master Instruments Details',
        ];

        return $titles[$gridName] ?? self::formatFieldLabel($gridName);
    }

    /* format grid column label */
    private static function formatGridColumnLabel($key, $gridName = null)
    {
        /* calibration results */
        if ($gridName === 'calibrationResults') {
            if (preg_match('/^reading(\d+)$/i', $key, $matches)) {
                return 'Reading ' . $matches[1];
            }
            if (preg_match('/^result(\d+)$/i', $key, $matches)) {
                return 'Result ' . $matches[1];
            }
            if (strtolower($key) === 'error') {
                return 'Error';
            }
        }

        /* master instruments details */
        if ($gridName === 'testResults') {
            $labels = [
                'parameter' => 'Parameter',
                'result' => 'Result',
                'error' => 'Error',
                'range' => 'Range',
                'calibrationdoneDATE' => 'Calibration Done Date',
                'calibrationNewDueDate' => 'New Calibration Due Date',
            ];

            if (isset($labels[$key])) {
                return $labels[$key];
            }

            /* case-insensitive fallback for calibration date keys */
            $lowerKey = strtolower($key);
            if ($lowerKey === 'calibrationdonedate') {
                return 'Calibration Done Date';
            }
            if ($lowerKey === 'calibrationnewduedate') {
                return 'New Calibration Due Date';
            }
        }

        return self::formatFieldLabel($key);
    }

    /* get attachments by field */
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

    /* get process field value */
    private static function getProcessFieldValue($fields, $key)
    {
        if (!isset($fields[$key])) {
            return null;
        }

        return $fields[$key]['value'] ?? null;
    }

    /* get fields by keys */
    private static function getFieldsByKeys($fields, array $keys)
    {
        $result = [];

        foreach ($keys as $key) {
            if (!isset($fields[$key])) {
                continue;
            }
            $result[] = $fields[$key];
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

            /* attachments */
            if ($key === 'attachment' || str_contains(strtolower($key), 'attachment')) {
                if (!is_array($value) || empty($value)) {
                    $value = '-';
                }
            }
            /* other arrays */
            elseif (is_array($value)) {
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

    /* format field label */
    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }
}