<?php

namespace App\Services\ExcelImport;

use App\Imports\ExcelChunkReadFilter;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\Storage;

class ExcelImportService
{
    private const COLUMN_MAP = [
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
    ];

    public function processChunk(object $import, int $startRow): array
    {
        $chunkSize = max(1, (int) config('cmms-import.chunk_size', 500));
        $firstDataRow = (int) config('cmms-import.first_data_row', 7);
        $lastDataRow = $firstDataRow + (int) $import->total_rows - 1;
        $endRow = min($startRow + $chunkSize - 1, $lastDataRow);

        $disk = Storage::disk('local');
        $relativePath = $import->file_path;

        if (!$relativePath || !$disk->exists($relativePath)) {
            throw new RuntimeException(
                'The stored Excel file could not be found. Path: ' .
                ($relativePath ?? 'NULL')
            );
        }

        $path = $disk->path($relativePath);

        $table = config('cmms-import.tables.' . $import->category);
        $processId = config('cmms-import.process_ids.' . $import->category);

        if (!$table || !$processId) {
            throw new RuntimeException('The import category is not configured.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new ExcelChunkReadFilter($startRow, $endRow));

        $spreadsheet = $reader->load($path);

        try {
            $sheet = $spreadsheet->getSheet(0);
            $rowsToInsert = [];
            $errors = [];
            $processed = 0;
            $seenIds = [];

            for ($rowNumber = $startRow; $rowNumber <= $endRow; $rowNumber++) {
                $values = [];

                for ($col = 1; $col <= 13; $col++) {
                    $cell = $sheet->getCell(
                        Coordinate::stringFromColumnIndex($col) . $rowNumber
                    );

                    $value = $cell->getFormattedValue();
                    $values[$col] = is_string($value) ? trim($value) : $value;
                }

                $nonEmptyValues = array_filter(
                    $values,
                    fn ($value) => $value !== null && $value !== ''
                );

                if (count($nonEmptyValues) === 0) {
                    continue;
                }

                $processed++;

                $instrumentId = trim((string) ($values[4] ?? ''));

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
                $mapped = [];

                foreach (self::COLUMN_MAP as $excelColumn => $dbColumn) {
                    if (
                        $dbColumn === 'equipment_name' &&
                        $table !== 'field_instrument_masters'
                    ) {
                        continue;
                    }

                    if (
                        $dbColumn === 'category' &&
                        $table === 'spare_instrument_masters'
                    ) {
                        continue;
                    }

                    $value = $values[$excelColumn] ?? null;
                    $mapped[$dbColumn] = $value === '' || $value === null
                        ? null
                        : (string) $value;
                }

                $mapped['stage_id'] = null;
                $mapped['process_id'] = (int) $processId;
                $mapped['initiator_id'] = (int) $import->created_by;
                $mapped['is_imported'] = true;

                if ($table === 'instrument_masters') {
                    $mapped['checklist_config'] = null;
                }

                $mapped['hod_review_comments'] = null;
                $mapped['hod_review_attachment'] = null;
                $mapped['qa_review_comments'] = null;
                $mapped['qa_review_attachment'] = null;
                $mapped['created_at'] = now();
                $mapped['updated_at'] = now();

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

                if ($insertRows === []) {
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