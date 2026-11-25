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
        $departmentId = $import->department_id ? (int) $import->department_id : null;

        $departmentRequired = !in_array($category, [
            'master_instrument',
            'spare_instrument_master',
        ], true);

        if ($departmentRequired && !$departmentId) {
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

    /* list all sheets with virtual row ranges */
    public function getEquipmentMasterSheets(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        $firstDataRow = (int) config('cmms-import.first_data_rows.equipment_master', 2);

        $sheets = [];
        $base = $firstDataRow;

        foreach ($reader->listWorksheetInfo($path) as $info) {
            $dataRows = max(0, (int) $info['totalRows'] - ($firstDataRow - 1));

            $sheets[] = [
                'name'       => $info['worksheetName'],
                'base'       => $base,                       // first virtual row of this sheet
                'data_rows'  => $dataRows,
                'last_virtual' => $base + $dataRows - 1,     // last virtual row of this sheet
            ];

            $base += $dataRows;
        }

        return $sheets;
    }

    /* total data rows across all sheets (use for total_rows) */
    public function countEquipmentMasterRows(string $path): int
    {
        return array_sum(array_column($this->getEquipmentMasterSheets($path), 'data_rows'));
    }

    /* unique codes in the file vs codes already in the database */
    public function checkEquipmentMasterFile(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $ids = [];

        try {
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $rows = $sheet->toArray(null, false, false, false);
                $header = array_shift($rows) ?: [];

                $headers = [];
                foreach ($header as $i => $value) {
                    $headers[$i + 1] = $this->normalizeHeader((string) $value);
                }

                $map = $this->buildEquipmentMasterColumnMap($headers);

                if (!isset($map['equipment_name'], $map['equipment_id_no'])) {
                    continue;
                }

                foreach ($rows as $row) {
                    $name = $this->normalizeExcelValue($row[$map['equipment_name'] - 1] ?? null);
                    $id = $this->normalizeExcelValue($row[$map['equipment_id_no'] - 1] ?? null);

                    if ($name !== null && $id !== null) {
                        $ids[mb_strtolower($id)] = $id;
                    }
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        $existing = [];

        foreach (array_chunk(array_values($ids), 500) as $chunk) {
            $found = DB::table('equipment_masters')
                ->whereIn('equipment_id_no', $chunk)
                ->pluck('equipment_id_no')
                ->all();

            $existing = array_merge($existing, $found);
        }

        return [
            'unique' => count($ids),
            'existing' => count($existing),
            'sample' => array_slice($existing, 0, 3),
        ];
    }

    /* throw a friendly error for empty files */
    public function assertEquipmentMasterFileUsable(string $path): void
    {
        $sheets = $this->getEquipmentMasterSheets($path);

        if (array_sum(array_column($sheets, 'data_rows')) <= 0) {
            throw new RuntimeException(
                'The Excel file you uploaded is empty. Please add the equipment details below the header row and upload it again.'
            );
        }
    }

    /* toaster [type, message] from final import totals */
    public function buildImportMessage(int $success, int $failed, array $errors = []): array
    {
        $allDuplicates = !empty($errors)
            && count(array_filter($errors, fn ($e) => ($e['type'] ?? '') === 'duplicate')) === count($errors);

        if ($success === 0 && $failed === 0) {
            return ['error', 'The Excel file you uploaded does not contain any equipment data. Please check the file and try again.'];
        }

        if ($success === 0 && $allDuplicates) {
            return ['warning', 'This file has already been imported. All ' . $failed . ' equipment records in it already exist, so nothing new was added.'];
        }

        if ($success === 0) {
            return ['error', 'No equipment could be imported. ' . $failed . ' rows had problems. Please check the error details, correct the file and upload it again.'];
        }

        if ($failed === 0) {
            return ['success', $success . ' equipment records were imported successfully.'];
        }

        return ['warning', $success . ' equipment records were imported. ' . $failed . ' rows were skipped because they already exist or have missing details.'];
    }

    /* equipment master import: all sheets */
    private function processEquipmentMasterChunk(object $import, int $startRow): array
    {
        $chunkSize = max(1, (int) config('cmms-import.chunk_size', 500));
        $firstDataRow = (int) config('cmms-import.first_data_rows.equipment_master', 2);
        $headerRow = $firstDataRow - 1;

        $departmentId = !empty($import->department_id)
            ? (int) $import->department_id
            : null;

        if (!$departmentId) {
            throw new RuntimeException('Please select a department before importing.');
        }

        $disk = Storage::disk('local');
        $relativePath = $import->file_path;

        if (!$relativePath || !$disk->exists($relativePath)) {
            throw new RuntimeException('The uploaded Excel file could not be found. Please upload it again.');
        }

        $path = $disk->path($relativePath);

        /* all sheets, with virtual row ranges */
        $sheets = $this->getEquipmentMasterSheets($path);
        $totalVirtualRows = array_sum(array_column($sheets, 'data_rows'));
        $lastVirtual = $firstDataRow + $totalVirtualRows - 1;

        if ($totalVirtualRows <= 0) {
            throw new RuntimeException(
                'The Excel file you uploaded is empty. Please add the equipment details below the header row and upload it again.'
            );
        }

        /* which sheet does startRow fall in? skip empty sheets */
        $sheet = null;
        foreach ($sheets as $candidate) {
            if ($candidate['data_rows'] > 0 && $startRow <= $candidate['last_virtual']) {
                $sheet = $candidate;
                break;
            }
        }

        if (!$sheet) {
            return [
                'processed' => 0,
                'success' => 0,
                'failed' => 0,
                'errors' => [],
                'next_row' => $startRow,
                'done' => true,
            ];
        }

        $startRow = max($startRow, $sheet['base']);
        $endVirtual = min($startRow + $chunkSize - 1, $sheet['last_virtual']);

        /* virtual row -> real row inside this sheet */
        $offset = $sheet['base'] - $firstDataRow;
        $realStart = $startRow - $offset;
        $realEnd = $endVirtual - $offset;
        $sheetName = $sheet['name'];

        /* headers of THIS sheet (column order differs between sheets, e.g. PP2 / WHD have no Asset code) */
        $headers = $this->getEquipmentMasterHeaders($path, $sheetName, $headerRow);
        $columnMap = $this->buildEquipmentMasterColumnMap($headers);

        if (!isset($columnMap['equipment_name']) || !isset($columnMap['equipment_id_no'])) {
            /* a sheet with unknown headers is skipped with a clear message, the import continues */
            return [
                'processed' => $endVirtual - $startRow + 1,
                'success' => 0,
                'failed' => 1,
                'errors' => [[
                    'type' => 'header',
                    'sheet' => $sheetName,
                    'row' => $headerRow,
                    'equipment_id_no' => null,
                    'error' => "Sheet \"{$sheetName}\" was skipped because its Equipment Name or Equipment Code Number column heading could not be found.",
                ]],
                'next_row' => $endVirtual + 1,
                'done' => $endVirtual >= $lastVirtual,
            ];
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheetName]);
        $reader->setReadFilter(new ExcelChunkReadFilter($realStart, $realEnd));

        $spreadsheet = $reader->load($path);

        try {
            $sheetObj = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getSheet(0);
            $rowsToInsert = [];
            $errors = [];
            $seenIds = [];
            $processed = $endVirtual - $startRow + 1;

            for ($rowNumber = $realStart; $rowNumber <= $realEnd; $rowNumber++) {
                $mapped = [];

                foreach ($columnMap as $dbColumn => $excelColumn) {
                    $cell = $sheetObj->getCell(
                        Coordinate::stringFromColumnIndex($excelColumn) . $rowNumber
                    );

                    $mapped[$dbColumn] = $this->normalizeExcelValue($cell->getFormattedValue());
                }

                $equipmentName = trim((string) ($mapped['equipment_name'] ?? ''));
                $equipmentId = trim((string) ($mapped['equipment_id_no'] ?? ''));

                /* blank row, or a stray cell (e.g. only a capacity note) -> ignore silently */
                if ($equipmentName === '' && $equipmentId === '') {
                    continue;
                }

                if ($equipmentName === '') {
                    $errors[] = [
                        'type' => 'missing',
                        'sheet' => $sheetName,
                        'row' => $rowNumber,
                        'equipment_id_no' => $equipmentId,
                        'error' => "Sheet {$sheetName}, row {$rowNumber}: the equipment name is missing for code {$equipmentId}.",
                    ];
                    continue;
                }

                if ($equipmentId === '') {
                    $errors[] = [
                        'type' => 'missing',
                        'sheet' => $sheetName,
                        'row' => $rowNumber,
                        'equipment_id_no' => null,
                        'error' => "Sheet {$sheetName}, row {$rowNumber}: the equipment code number is missing for \"{$equipmentName}\".",
                    ];
                    continue;
                }

                $key = mb_strtolower($equipmentId);

                if (isset($seenIds[$key])) {
                    $errors[] = [
                        'type' => 'duplicate',
                        'sheet' => $sheetName,
                        'row' => $rowNumber,
                        'equipment_id_no' => $equipmentId,
                        'error' => "Sheet {$sheetName}, row {$rowNumber}: code {$equipmentId} appears more than once in this file.",
                    ];
                    continue;
                }

                $seenIds[$key] = true;

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
                        'stage_id' => null,
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
                    ->map(fn ($id) => mb_strtolower((string) $id))
                    ->all();

                $existingLookup = array_fill_keys($existingIds, true);
                $insertRows = [];

                foreach ($batch as $item) {
                    $id = $item['equipment_id_no'];
                    $key = mb_strtolower($id);

                    if (isset($existingLookup[$key])) {
                        $errors[] = [
                            'type' => 'duplicate',
                            'sheet' => $sheetName,
                            'row' => $item['row_number'],
                            'equipment_id_no' => $id,
                            'error' => "Sheet {$sheetName}, row {$item['row_number']}: code {$id} already exists in the system.",
                        ];
                        continue;
                    }

                    $existingLookup[$key] = true;
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
                'next_row' => $endVirtual + 1,
                'done' => $endVirtual >= $lastVirtual,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /* headers of one sheet */
    private function getEquipmentMasterHeaders(string $path, string $sheetName, int $headerRow = 1): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([$sheetName]);
        $reader->setReadFilter(new ExcelChunkReadFilter($headerRow, $headerRow));

        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getSheet(0);
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn($headerRow));

            $headers = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $sheet->getCell(
                    Coordinate::stringFromColumnIndex($column) . $headerRow
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