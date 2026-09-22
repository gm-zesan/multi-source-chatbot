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
        $this->faq->refresh();

        try {
            // Case 1: Deletion, soft-deleted, or explicitly deactivated
            $isDeletion = ($this->action === 'delete' || $this->faq->trashed());
            $isExplicitlyInactive = ($this->action === 'index' && ! $this->faq->is_active && ! $this->faq->hasFailed() && $this->faq->lifecycle_status !== \App\Enums\FaqLifecycleStatus::SYNCING);

            if ($isDeletion || $isExplicitlyInactive) {
                $retrievalClient->deleteFaq($this->faq->id, $this->faq->workspace_id);
                if (! $this->faq->trashed()) {
                    $this->faq->update([
                        'lifecycle_status' => \App\Enums\FaqLifecycleStatus::DRAFT,
                        'is_active'        => false,
                    ]);
                }
                return;
            }

            // Step 1: Transition to SYNCING
            $this->faq->update([
                'lifecycle_status' => \App\Enums\FaqLifecycleStatus::SYNCING,
                'sync_error'       => null,
            ]);

            // Step 2: Direct Vector Synchronization to Typesense & Python AI Retrieval Engine
            $synced = $retrievalClient->syncFaq($this->faq);

            if ($synced === false) {
                $this->faq->update([
                    'lifecycle_status' => \App\Enums\FaqLifecycleStatus::SYNC_FAILED,
                    'is_active'        => false,
                    'sync_error'       => 'Vector retrieval engine synchronization failed.',
                ]);
                return;
            }

            // Step 3: Successfully synced -> Transition to ACTIVE & searchable
            $this->faq->update([
                'lifecycle_status' => \App\Enums\FaqLifecycleStatus::ACTIVE,
                'is_active'        => true,
                'sync_error'       => null,
            ]);

            Log::debug('[FAQIndexJob] FAQ successfully synced and activated', [
                'faq_id' => $this->faq->id,
                'action' => $this->action,
                'status' => 'active',
            ]);
        } catch (\Throwable $e) {
            $this->faq->update([
                'lifecycle_status' => \App\Enums\FaqLifecycleStatus::SYNC_FAILED,
                'is_active'        => false,
                'sync_error'       => $e->getMessage(),
            ]);

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
