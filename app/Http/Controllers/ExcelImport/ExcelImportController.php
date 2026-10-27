<?php

namespace App\Http\Controllers\ExcelImport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Import\ExcelImportRequest;
use App\Jobs\ProcessExcelImportChunkJob;
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

            $firstDataRow = (int) config('cmms-import.first_data_row', 7);
            $highestRow = (int) ($worksheets[0]['totalRows'] ?? 0);
            $totalRows = max(0, $highestRow - $firstDataRow + 1);

            $importId = DB::table('excel_imports')->insertGetId([
                'category' => $category,
                'original_filename' => mb_substr(
                    $file->getClientOriginalName(),
                    0,
                    255
                ),
                'file_path' => $storedPath,
                'status' => $totalRows > 0 ? 'queued' : 'completed',
                'total_rows' => $totalRows,
                'processed_rows' => 0,
                'success_rows' => 0,
                'failed_rows' => 0,
                'errors' => json_encode([]),
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($totalRows > 0) {
                ProcessExcelImportChunkJob::dispatch(
                    $importId,
                    $firstDataRow
                )->onQueue(config('cmms-import.queue', 'excel-imports'));
            } else {
                Storage::disk('local')->delete($storedPath);
            }

            return response()->json([
                'success' => true,
                'message' => $totalRows > 0
                    ? 'Import queued successfully.'
                    : 'The workbook contains no data rows to import.',
                'data' => [
                    'import_id' => $importId,
                    'status' => $totalRows > 0 ? 'queued' : 'completed',
                    'total_rows' => $totalRows,
                ],
            ], 202);
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue the Excel import. Verify the workbook and try again.',
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

        return response()->json([
            'success' => true,
            'data' => [
                'import_id' => (int) $import->id,
                'category' => $import->category,
                'filename' => $import->original_filename,
                'status' => $import->status,
                'total_rows' => $totalRows,
                'processed_rows' => $processedRows,
                'success_rows' => (int) $import->success_rows,
                'failed_rows' => (int) $import->failed_rows,
                'progress_percent' => $totalRows > 0
                    ? round(($processedRows / $totalRows) * 100, 2)
                    : 100,
                'errors' => json_decode($import->errors ?: '[]', true) ?: [],
                'created_at' => $import->created_at,
                'updated_at' => $import->updated_at,
            ],
        ]);
    }
}