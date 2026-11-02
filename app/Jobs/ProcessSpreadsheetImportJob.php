<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SpreadsheetImportJob;
use App\Services\Spreadsheet\SpreadsheetIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSpreadsheetImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance with generation fencing token.
     */
    public function __construct(
        public readonly SpreadsheetImportJob $importJob,
        public readonly string $fencingGenerationId,
        public readonly int $chunkSize = 250
    ) {
        $this->queue = 'default';
    }

    /**
     * Execute the job with checkpoint resumption and generation fencing.
     */
    public function handle(SpreadsheetIngestionService $service): void
    {
        $this->importJob->refresh();

        // 1. Generation Fencing Check: Invalidate stale attempts if a new import or cancellation occurred
        if ($this->importJob->generation_id !== $this->fencingGenerationId) {
            Log::warning("[ProcessSpreadsheetImportJob] Generation fencing mismatch for job {$this->importJob->id}. Current: {$this->importJob->generation_id}, Job: {$this->fencingGenerationId}. Aborting stale worker.");
            return;
        }

        if (in_array($this->importJob->status, ['cancelled', 'completed', 'failed'], true)) {
            Log::info("[ProcessSpreadsheetImportJob] Job {$this->importJob->id} is already in terminal state {$this->importJob->status}. Aborting.");
            return;
        }

        // 2. Process one chunk
        $result = $service->processChunk($this->importJob, $this->chunkSize);

        // 3. Chain next chunk if not finished and not cancelled
        if (! $result['is_finished'] && $result['status'] === 'processing') {
            self::dispatch($this->importJob, $this->fencingGenerationId, $this->chunkSize);
        } else {
            Log::info("[ProcessSpreadsheetImportJob] Finished processing job {$this->importJob->id} with status: {$result['status']}");
        }
    }
}
