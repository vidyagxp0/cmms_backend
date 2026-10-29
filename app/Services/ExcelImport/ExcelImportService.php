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
        $chunkSize = max(1, (int) config('cmms-import.chunk_size', 500));
        $firstDataRow = (int) config('cmms-import.first_data_row', 7);
        $lastDataRow = $firstDataRow + (int) $import->total_rows - 1;
        $endRow = min($startRow + $chunkSize - 1, $lastDataRow);

        $category = $import->category;
        $columnMap = self::COLUMN_MAP[$category] ?? null;

        $table = config('cmms-import.tables.' . $category);
        $processId = config('cmms-import.process_ids.' . $category);

        if (!$columnMap || !$table || !$processId) {
            throw new RuntimeException('The import category is not configured correctly.');
        }

        $disk = Storage::disk('local');
        $relativePath = $import->file_path;

        if (!$relativePath || !$disk->exists($relativePath)) {
            throw new RuntimeException(
                'The stored Excel file could not be found. Path: ' .
                ($relativePath ?? 'NULL')
            );
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

                // Skip blank rows using only the mapped data columns.
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

                if (empty($insertRows)) {
                    continue;
                }

                DB::transaction(function () use ($table, $insertRows) {
                    DB::table($table)->insert($insertRows);
                });

                $success += count($insertRows);
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
}