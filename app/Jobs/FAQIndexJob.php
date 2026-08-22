<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FAQ;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FAQIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Maximum number of allowed exceptions.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly FAQ $faq,
        public readonly string $action, // 'index', 'update', 'delete'
    ) {
        $this->connection = 'database';
        $this->queue = 'faq';
    }

    /**
     * Execute the job.
     */
    public function handle(RetrievalClient $retrievalClient): void
    {
        try {
            if ($this->action === 'delete' || ! $this->faq->shouldBeSearchable()) {
                $retrievalClient->deleteFaq($this->faq->id, $this->faq->workspace_id);
            } else {
                $retrievalClient->syncFaq($this->faq);
            }

            Log::debug('[FAQIndexJob] Synced FAQ to Python retrieval service', [
                'faq_id' => $this->faq->id,
                'action' => $this->action,
            ]);
        } catch (\Throwable $e) {
            Log::error('[FAQIndexJob] Sync failed', [
                'faq_id' => $this->faq->id,
                'action' => $this->action,
                'error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[FAQIndexJob] Job failed after all retries', [
            'faq_id' => $this->faq->id,
            'action' => $this->action,
            'error'  => $exception->getMessage(),
        ]);
    }
}
