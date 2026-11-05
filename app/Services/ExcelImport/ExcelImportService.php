<?php

namespace App\Services\ExcelImport;

use App\Imports\ExcelChunkReadFilter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class ExcelImportService
{
    private const COLUMN_MAP = [
        'master_instrument' => [
            2 => 'instrument_name',
            3 => 'instrument_id_no',
            4 => 'make',
            5 => 'sr_no_model_no',
            6 => 'location',
            7 => 'least_count',
            8 => 'accuracy',
            9 => 'instrument_range',
            10 => 'operating_range',
            11 => 'calibration_test_points',
            12 => 'category',
        ],
        'field_instrument_master' => [
            2 => 'equipment_name',
            3 => 'instrument_name',
            4 => 'instrument_id_no',
            5 => 'make',
            6 => 'sr_no_model_no',
            7 => 'least_count',
            8 => 'location',
            9 => 'accuracy',
            10 => 'instrument_range',
            11 => 'operating_range',
            12 => 'calibration_test_points',
            13 => 'category',
        ],
        'spare_instrument_master' => [
            2 => 'instrument_name',
            3 => 'instrument_id_no',
            4 => 'make',
            5 => 'sr_no_model_no',
            6 => 'location',
            7 => 'least_count',
            8 => 'accuracy',
            9 => 'instrument_range',
            10 => 'operating_range',
            11 => 'calibration_test_points',
        ],
    ];

    public function processChunk(object $import, int $startRow): array
    {
        if ($import->category === 'equipment_master') {
            return $this->processEquipmentMasterChunk($import, $startRow);
        }

        return $this->processExistingChunk($import, $startRow);
    }

    private function processExistingChunk(object $import, int $startRow): array
    {
        $chunkSize = max(1, (int) config('cmms-import.chunk_size', 500));
        $firstDataRow = (int) config('cmms-import.first_data_row', 7);
        $lastDataRow = $firstDataRow + (int) $import->total_rows - 1;
        $endRow = min($startRow + $chunkSize - 1, $lastDataRow);

        $category = $import->category;
        $columnMap = self::COLUMN_MAP[$category] ?? null;
        $table = config('cmms-import.tables.' . $category);
        $processId = config('cmms-import.process_ids.' . $category);
        $departmentId = (int) ($import->department_id ?? 0);

        if (!$departmentId) {
            throw new RuntimeException('Department ID is missing from this import.');
        }

        if (!$columnMap || !$table || !$processId) {
            throw new RuntimeException('The import category is not configured correctly.');
        }

        $disk = Storage::disk('local');
        $relativePath = $import->file_path;

        if (!$relativePath || !$disk->exists($relativePath)) {
            throw new RuntimeException('The stored Excel file could not be found.');
        }

        $path = $disk->path($relativePath);
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new ExcelChunkReadFilter($startRow, $endRow));

        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheet(0);
            $rowsToInsert = [];
            $errors = [];
            $seenIds = [];
            $processed = max(0, $endRow - $startRow + 1);
            $maxColumn = max(array_keys($columnMap));

            for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
                $values = [];

                for ($col = 1; $col <= $maxColumn; $col++) {
                    $cell = $sheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . $rowNumber
                    );

                    $value = $cell->getFormattedValue();
                    $values[$col] = is_string($value) ? trim($value) : $value;
                }

                $mapped = [];

                foreach ($columnMap as $excelColumn => $dbColumn) {
                    $value = $values[$excelColumn] ?? null;

                    $mapped[$dbColumn] = $value === '' || $value === null
                        ? null
                        : (string) $value;
                }

                $hasData = false;

                foreach ($mapped as $value) {
                    if ($value !== null && trim((string) $value) !== '') {
                        $hasData = true;
                        break;
                    }
                }

                if (!$hasData) {
                    continue;
                }

                $instrumentId = trim((string) ($mapped['instrument_id_no'] ?? ''));

                if ($instrumentId === '') {
                    $errors[] = [
                        'row' => $rowNumber,
                        'instrument_id_no' => null,
                        'error' => 'Instrument ID No. is required.',
                    ];
                    continue;
                }

                if (isset($seenIds[$instrumentId])) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'instrument_id_no' => $instrumentId,
                        'error' => 'Duplicate instrument ID in this Excel file.',
                    ];
                    continue;
                }

                $seenIds[$instrumentId] = true;

                $mapped['stage_id'] = null;
                $mapped['process_id'] = (int) $processId;
                $mapped['initiator_id'] = (int) $import->created_by;
                $mapped['department_id'] = $departmentId;
                $mapped['is_imported'] = true;
                $mapped['created_at'] = now();
                $mapped['updated_at'] = now();

                if ($table === 'instrument_masters') {
                    $mapped['checklist_config'] = null;
                }

                $mapped['hod_review_comments'] = null;
                $mapped['hod_review_attachment'] = null;
                $mapped['qa_review_comments'] = null;
                $mapped['qa_review_attachment'] = null;

                $rowsToInsert[] = [
                    'row_number' => $rowNumber,
                    'instrument_id_no' => $instrumentId,
                    'data' => $mapped,
                ];
            }

            $batchSize = max(1, (int) config('cmms-import.batch_size', 500));
            $success = 0;

            foreach (array_chunk($rowsToInsert, $batchSize) as $batch) {
                $ids = array_column($batch, 'instrument_id_no');

                $existingIds = DB::table($table)
                    ->whereIn('instrument_id_no', $ids)
                    ->pluck('instrument_id_no')
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $existingLookup = array_fill_keys($existingIds, true);
                $insertRows = [];

                foreach ($batch as $item) {
                    $id = $item['instrument_id_no'];

                    if (isset($existingLookup[$id])) {
                        $errors[] = [
                            'row' => $item['row_number'],
                            'instrument_id_no' => $id,
                            'error' => 'Instrument ID already exists in the destination table.',
                        ];
                        continue;
                    }

                    $existingLookup[$id] = true;
                    $insertRows[] = $item['data'];
                }

                if ($insertRows) {
                    DB::transaction(function () use ($table, $insertRows) {
                        DB::table($table)->insert($insertRows);
                    });

                    $success += count($insertRows);
                }
            }

            return [
                'processed' => $processed,
                'success' => $success,
                'failed' => count($errors),
                'errors' => $errors,
                'next_row' => $endRow + 1,
                'done' => $endRow >= $lastDataRow,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function processEquipmentMasterChunk(object $import, int $startRow): array
    {
        $chunkSize = max(1, (int) config('cmms-import.chunk_size', 500));
        $firstDataRow = (int) config('cmms-import.first_data_rows.equipment_master', 2);
        $lastDataRow = $firstDataRow + (int) $import->total_rows - 1;
        $endRow = min($startRow + $chunkSize - 1, $lastDataRow);

        $departmentId = (int) ($import->department_id ?? 0);

        if (!$departmentId) {
            throw new RuntimeException('Department ID is missing from this import.');
        }

        $disk = Storage::disk('local');
        $relativePath = $import->file_path;

        if (!$relativePath || !$disk->exists($relativePath)) {
            throw new RuntimeException('The stored Excel file could not be found.');
        }

        $path = $disk->path($relativePath);
        $headers = $this->getEquipmentMasterHeaders($path);
        $columnMap = $this->buildEquipmentMasterColumnMap($headers);

        if (!isset($columnMap['equipment_name'])) {
            throw new RuntimeException('Equipment / Instrument Name column is missing.');
        }

        if (!isset($columnMap['equipment_id_no'])) {
            throw new RuntimeException('Equipment / Instrument Code Number column is missing.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new ExcelChunkReadFilter($startRow, $endRow));

        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheet(0);
            $rowsToInsert = [];
            $errors = [];
            $seenIds = [];
            $processed = max(0, $endRow - $startRow + 1);

            for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
                $mapped = [];

                foreach ($columnMap as $dbColumn => $excelColumn) {
                    $cell = $sheet->getCell(
                        Coordinate::stringFromColumnIndex($excelColumn) . $rowNumber
                    );

                    $mapped[$dbColumn] = $this->normalizeExcelValue(
                        $cell->getFormattedValue()
                    );
                }

                $hasData = false;

                foreach ($mapped as $value) {
                    if ($value !== null && trim((string) $value) !== '') {
                        $hasData = true;
                        break;
                    }
                }

                if (!$hasData) {
                    continue;
                }

                $equipmentName = trim((string) ($mapped['equipment_name'] ?? ''));
                $equipmentId = trim((string) ($mapped['equipment_id_no'] ?? ''));

                if ($equipmentName === '') {
                    $errors[] = [
                        'row' => $rowNumber,
                        'equipment_id_no' => $equipmentId ?: null,
                        'error' => 'Equipment / Instrument Name is required.',
                    ];
                    continue;
                }

                if ($equipmentId === '') {
                    $errors[] = [
                        'row' => $rowNumber,
                        'equipment_id_no' => null,
                        'error' => 'Equipment / Instrument Code Number is required.',
                    ];
                    continue;
                }

                if (isset($seenIds[$equipmentId])) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'equipment_id_no' => $equipmentId,
                        'error' => 'Duplicate equipment ID in this Excel file.',
                    ];
                    continue;
                }

                $seenIds[$equipmentId] = true;

                $rowsToInsert[] = [
                    'row_number' => $rowNumber,
                    'equipment_id_no' => $equipmentId,
                    'data' => [
                        'equipment_name' => $equipmentName,
                        'equipment_id_no' => $equipmentId,
                        'equipment_make' => $mapped['equipment_make'] ?? null,
                        'equipment_model' => $mapped['equipment_model'] ?? null,
                        'equipment_location' => $mapped['equipment_location'] ?? null,
                        'asset_code' => $mapped['asset_code'] ?? null,
                        'capacity' => $mapped['capacity'] ?? null,
                        'checklist_config' => json_encode([
                            'checklist_name' => '',
                            'include_serial_number' => true,
                            'question_columns' => [],
                            'data_columns' => [],
                            'questions' => [],
                            'notes' => [
                                'heading' => 'Note:',
                                'include_serial_number' => true,
                                'items' => [],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                        'stage_id' => (int) config('cmms-import.equipment_master_stage_id', 50),
                        'process_id' => 10,
                        'initiator_id' => (int) $import->created_by,
                        'is_imported' => true,
                        'department_id' => $departmentId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ];
            }

            $batchSize = max(1, (int) config('cmms-import.batch_size', 500));
            $success = 0;

            foreach (array_chunk($rowsToInsert, $batchSize) as $batch) {
                $ids = array_column($batch, 'equipment_id_no');

                $existingIds = DB::table('equipment_masters')
                    ->whereIn('equipment_id_no', $ids)
                    ->pluck('equipment_id_no')
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $existingLookup = array_fill_keys($existingIds, true);
                $insertRows = [];

                foreach ($batch as $item) {
                    $id = $item['equipment_id_no'];

                    if (isset($existingLookup[$id])) {
                        $errors[] = [
                            'row' => $item['row_number'],
                            'equipment_id_no' => $id,
                            'error' => 'Equipment ID already exists in equipment_masters.',
                        ];
                        continue;
                    }

                    $existingLookup[$id] = true;
                    $insertRows[] = $item['data'];
                }

                if ($insertRows) {
                    DB::transaction(function () use ($insertRows) {
                        DB::table('equipment_masters')->insert($insertRows);
                    });

                    $success += count($insertRows);
                }
            }

            return [
                'processed' => $processed,
                'success' => $success,
                'failed' => count($errors),
                'errors' => $errors,
                'next_row' => $endRow + 1,
                'done' => $endRow >= $lastDataRow,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function getEquipmentMasterHeaders(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new ExcelChunkReadFilter(1, 1));

        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheet(0);
            $highestColumn = Coordinate::columnIndexFromString(
                $sheet->getHighestDataColumn(1)
            );

            $headers = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $sheet->getCell(
                    Coordinate::stringFromColumnIndex($column) . '1'
                )->getFormattedValue();

                $headers[$column] = $this->normalizeHeader((string) $value);
            }

            return $headers;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function buildEquipmentMasterColumnMap(array $headers): array
    {
        $aliases = [
            'equipment_name' => [
                'equipment instrument name',
                'equipment name',
                'instrument name',
            ],
            'equipment_id_no' => [
                'equipment instrument code number',
                'equipment instrument code no',
                'equipment code number',
                'instrument code number',
                'equipment id no',
            ],
            'asset_code' => ['asset code'],
            'equipment_make' => ['make', 'equipment make'],
            'equipment_model' => ['model', 'equipment model'],
            'capacity' => ['capacity'],
            'equipment_location' => ['location', 'equipment location'],
        ];

        $map = [];

        foreach ($headers as $column => $header) {
            foreach ($aliases as $dbColumn => $possibleHeaders) {
                if (in_array($header, $possibleHeaders, true)) {
                    $map[$dbColumn] = $column;
                    break;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $header): string
    {
        $header = str_replace(["\r", "\n", "\t", "\xc2\xa0"], ' ', $header);
        $header = mb_strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/u', ' ', $header);

        return trim(preg_replace('/\s+/', ' ', $header));
    }

    private function normalizeExcelValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(str_replace("\xc2\xa0", ' ', (string) $value));

        return $value === '' ? null : $value;
    }
}
