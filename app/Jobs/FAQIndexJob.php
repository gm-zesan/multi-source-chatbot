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
    public function handle(
        RetrievalClient $retrievalClient,
        ?\App\Services\FAQ\FaqLexiconGeneratorService $lexiconGenerator = null,
    ): void {
        $lexiconGenerator = $lexiconGenerator ?? app(\App\Services\FAQ\FaqLexiconGeneratorService::class);
        $this->faq->refresh();

        try {
            // Case 1: Deletion, soft-deleted, or explicitly deactivated
            $isDeletion = ($this->action === 'delete' || $this->faq->trashed());
            $isExplicitlyInactive = ($this->action === 'index' && ! $this->faq->is_active && ! $this->faq->hasFailed() && $this->faq->lifecycle_status !== \App\Enums\FaqLifecycleStatus::VALIDATING);

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

            // Step 1: Transition to VALIDATING
            $this->faq->update([
                'lifecycle_status' => \App\Enums\FaqLifecycleStatus::VALIDATING,
                'sync_error'       => null,
            ]);

            // Step 2: Generate & validate commerce domain lexicon
            $lexicon = $lexiconGenerator->generateAndStore($this->faq);

            if (! $lexicon || ! $lexicon->is_validated) {
                // Validation failed -> Do not sync to Typesense, remove from Typesense if existed
                $this->faq->update([
                    'lifecycle_status' => \App\Enums\FaqLifecycleStatus::VALIDATION_FAILED,
                    'is_active'        => false,
                    'sync_error'       => 'Commerce lexicon validation failed or anti-hallucination boundary triggered.',
                ]);
                $retrievalClient->deleteFaq($this->faq->id, $this->faq->workspace_id);

                Log::warning('[FAQIndexJob] Lexicon validation failed; document withheld from retrieval', [
                    'faq_id' => $this->faq->id,
                ]);
                return;
            }

            // Step 3: Transition to SYNCING
            $this->faq->update([
                'lifecycle_status' => \App\Enums\FaqLifecycleStatus::SYNCING,
            ]);

            $this->faq->load('lexicon');

            $synced = $retrievalClient->syncFaq($this->faq);

            if ($synced === false) {
                $this->faq->update([
                    'lifecycle_status' => \App\Enums\FaqLifecycleStatus::SYNC_FAILED,
                    'is_active'        => false,
                    'sync_error'       => 'Typesense vector synchronization failed.',
                ]);
                return;
            }

            // Step 4: Successfully validated & synced -> Transition to ACTIVE
            $this->faq->update([
                'lifecycle_status' => \App\Enums\FaqLifecycleStatus::ACTIVE,
                'is_active'        => true,
                'sync_error'       => null,
            ]);

            Log::debug('[FAQIndexJob] FAQ successfully validated, synced and activated', [
                'faq_id' => $this->faq->id,
                'action' => $this->action,
                'status' => 'active',
            ]);
        } catch (\Throwable $e) {
            dump('CAUGHT IN FAQIndexJob: ' . $e->getMessage());
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
