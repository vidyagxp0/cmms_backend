<?php

namespace App\Jobs;

use App\Services\ExcelImport\ExcelImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessExcelImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $importId,
        public int $startRow
    ) {}

    public function handle(ExcelImportService $service): void
    {
        $import = DB::table('excel_imports')
            ->where('id', $this->importId)
            ->first();

        if (!$import || in_array($import->status, ['completed', 'failed'], true)) {
            return;
        }

        DB::table('excel_imports')
            ->where('id', $this->importId)
            ->update([
                'status' => 'processing',
                'updated_at' => now(),
            ]);

        $result = $service->processChunk($import, $this->startRow);

        DB::transaction(function () use ($result) {
            $current = DB::table('excel_imports')
                ->where('id', $this->importId)
                ->lockForUpdate()
                ->first();

            if (!$current) {
                return;
            }

            $oldErrors = json_decode($current->errors ?: '[]', true) ?: [];
            $newErrors = array_slice(
                array_merge($oldErrors, $result['errors']),
                0,
                2000
            );

            DB::table('excel_imports')
                ->where('id', $this->importId)
                ->update([
                    'processed_rows' => min(
                        (int) $current->total_rows,
                        (int) $current->processed_rows + (int) $result['processed']
                    ),
                    'success_rows' => (int) $current->success_rows + (int) $result['success'],
                    'failed_rows' => (int) $current->failed_rows + (int) $result['failed'],
                    'errors' => json_encode($newErrors, JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
        });

        if ($result['done']) {
            DB::table('excel_imports')
                ->where('id', $this->importId)
                ->update([
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);

            Storage::disk('local')->delete($import->file_path);
            return;
        }

        self::dispatch(
            $this->importId,
            (int) $result['next_row']
        )->onQueue(config('cmms-import.queue', 'excel-imports'));
    }

    public function failed(Throwable $exception): void
    {
        DB::table('excel_imports')
            ->where('id', $this->importId)
            ->update([
                'status' => 'failed',
                'errors' => json_encode([
                    [
                        'row' => null,
                        'error' => 'Import failed after retries. Check Laravel logs for details.',
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        report($exception);
    }
}