<?php

namespace App\Http\Controllers\ExcelImport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ExcelImportRequest;
use App\Jobs\ProcessExcelImportChunkJob;
use App\Services\ExcelImport\ExcelImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ExcelImportController extends Controller
{
    public function import(ExcelImportRequest $request): JsonResponse
    {
        $category = $request->input('euipmentMasterType');
        $departmentId = (int) $request->input('department_id');
        $file = $request->file('file');
        $storedPath = null;

        try {
            $storedPath = $file->store('cmms-imports', 'local');

            if (!$storedPath) {
                throw new \RuntimeException('Unable to store the uploaded file.');
            }

            $absolutePath = Storage::disk('local')->path($storedPath);

            $reader = IOFactory::createReaderForFile($absolutePath);
            $reader->setReadDataOnly(true);

            $worksheets = $reader->listWorksheetInfo($absolutePath);

            if (empty($worksheets)) {
                throw new \RuntimeException('The workbook contains no worksheets.');
            }

            $firstDataRow = (int) config(
                "cmms-import.first_data_rows.{$category}",
                config('cmms-import.first_data_row', 7)
            );

            /* equipment master: count rows of all sheets */
            if ($category === 'equipment_master') {
                $totalRows = app(ExcelImportService::class)->countEquipmentMasterRows($absolutePath);
            } else {
                $highestRow = (int) ($worksheets[0]['totalRows'] ?? 0);
                $totalRows = max(0, $highestRow - $firstDataRow + 1);
            }

            /* empty file: stop here with a clear message */
            if ($totalRows <= 0) {
                Storage::disk('local')->delete($storedPath);

                return response()->json([
                    'success' => false,
                    'message' => 'The Excel file you uploaded is empty. Please add the equipment details below the header row and upload it again.',
                ], 422);
            }

            /* codes already in the database */
            $duplicateNote = null;

            if ($category === 'equipment_master') {
                $check = app(ExcelImportService::class)->checkEquipmentMasterFile($absolutePath);

                if ($check['unique'] > 0 && $check['existing'] >= $check['unique']) {
                    Storage::disk('local')->delete($storedPath);

                    return response()->json([
                        'success' => false,
                        'message' => 'This file has already been imported. All ' . $check['unique'] . ' equipment records in it already exist, so nothing new was added.',
                    ], 422);
                }

                if ($check['existing'] > 0) {
                    $duplicateNote = $check['existing'] . ' of ' . $check['unique']
                        . ' equipment records already exist (for example ' . implode(', ', $check['sample'])
                        . ') and will be skipped.';
                }
            }

            $importId = DB::table('excel_imports')->insertGetId([
                'category' => $category,
                'department_id' => $departmentId,
                'original_filename' => mb_substr(
                    $file->getClientOriginalName(),
                    0,
                    255
                ),
                'file_path' => $storedPath,
                'status' => 'queued',
                'total_rows' => $totalRows,
                'processed_rows' => 0,
                'success_rows' => 0,
                'failed_rows' => 0,
                'errors' => json_encode([]),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            ProcessExcelImportChunkJob::dispatch(
                $importId,
                $firstDataRow
            )->onQueue(config('cmms-import.queue', 'excel-imports'));

            return response()->json([
                'success' => true,
                'message' => $duplicateNote
                    ? 'Import started. ' . $duplicateNote . ' The remaining records are being imported.'
                    : 'Your file was uploaded. The import is running, the result will be shown shortly.',
                'data' => [
                    'import_id' => $importId,
                    'category' => $category,
                    'department_id' => $departmentId,
                    'status' => 'queued',
                    'total_rows' => $totalRows,
                    'duplicate_warning' => $duplicateNote,
                ],
            ], 202);
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'We could not read this Excel file. Please make sure it is a valid .xlsx file and try again.',
            ], 422);
        }
    }

    public function status(int $importId, Request $request): JsonResponse
    {
        $import = DB::table('excel_imports')
            ->where('id', $importId)
            ->first();

        if (!$import) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found.',
            ], 404);
        }

        if ((int) $import->created_by !== (int) $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this import.',
            ], 403);
        }

        $totalRows = (int) $import->total_rows;
        $processedRows = (int) $import->processed_rows;
        $errors = json_decode($import->errors ?: '[]', true) ?: [];

        /* human readable result once the import has finished */
        $toastType = null;
        $toastMessage = null;

        if ($import->status === 'completed') {
            [$toastType, $toastMessage] = app(ExcelImportService::class)->buildImportMessage(
                (int) $import->success_rows,
                (int) $import->failed_rows,
                $errors
            );
        } elseif ($import->status === 'failed') {
            $toastType = 'error';
            $toastMessage = 'The import could not be completed. Please try again, or contact support if it keeps happening.';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'import_id' => (int) $import->id,
                'category' => $import->category,
                'department_id' => $import->department_id,
                'filename' => $import->original_filename,
                'status' => $import->status,
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
                'success_rows' => (int) $import->success_rows,
                'failed_rows' => (int) $import->failed_rows,
                'progress_percent' => $totalRows > 0
                    ? round(($processedRows / $totalRows) * 100, 2)
                    : 100,
                'errors' => $errors,
                'toast_type' => $toastType,
                'toast_message' => $toastMessage,
                'created_at' => $import->created_at,
                'updated_at' => $import->updated_at,
            ],
        ]);
    }
}