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
use Illuminate\Support\Facades\Cache;
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

    public ?string $dispatchedAt;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly FAQ $faq,
        public readonly string $action, // 'index', 'update', 'delete'
        ?string $dispatchedAt = null,
        public readonly int $lockTimeout = 10,
    ) {
        $this->dispatchedAt = $dispatchedAt ?? now()->toIso8601String();
        $this->connection = 'database';
        $this->queue = 'faq';
    }

    /**
     * Execute the job.
     */
    public function handle(RetrievalClient $retrievalClient): void
    {
        $lock = Cache::lock("faq_indexing:{$this->faq->id}", 60);

        try {
            $lock->block($this->lockTimeout);

            // Guard against hard-deleted models
            if (! $this->faq->exists) {
                $retrievalClient->deleteFaq($this->faq->id, $this->faq->workspace_id);
                return;
            }

        try {
            $this->faq->refresh();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $retrievalClient->deleteFaq($this->faq->id, $this->faq->workspace_id);
            return;
        }

        try {
            // Case 1: Deletion, soft-deleted, or explicitly deactivated
            $isDeletion = ($this->action === 'delete' || $this->faq->trashed());
            $isExplicitlyInactive = (! $this->faq->is_active && $this->faq->lifecycle_status !== \App\Enums\FaqLifecycleStatus::SYNCING);

            if ($isDeletion || $isExplicitlyInactive) {
                $retrievalClient->deleteFaq($this->faq->id, $this->faq->workspace_id);
                if (! $this->faq->trashed() && $this->faq->lifecycle_status === \App\Enums\FaqLifecycleStatus::SYNCING) {
                    $this->faq->update([
                        'lifecycle_status' => \App\Enums\FaqLifecycleStatus::DRAFT,
                        'is_active'        => false,
                    ]);
                }
                return;
            }

            // Case 2: Fencing protection against stale jobs
            if ($this->dispatchedAt !== null && $this->faq->updated_at !== null) {
                $dispatchedTime = \Carbon\Carbon::parse($this->dispatchedAt)->timestamp;
                $faqUpdatedTime = $this->faq->updated_at->timestamp;

                if ($faqUpdatedTime > $dispatchedTime) {
                    Log::info('[FAQIndexJob] Discarding stale FAQ index job superseded by newer update', [
                        'faq_id'         => $this->faq->id,
                        'action'         => $this->action,
                        'dispatched_at'  => $this->dispatchedAt,
                        'faq_updated_at' => $this->faq->updated_at->toIso8601String(),
                    ]);
                    return;
                }
            }

            // Step 1: Transition to SYNCING only if currently in an indexable lifecycle state
            $affected = FAQ::where('id', $this->faq->id)
                ->whereIn('lifecycle_status', [
                    \App\Enums\FaqLifecycleStatus::SYNCING->value,
                    \App\Enums\FaqLifecycleStatus::ACTIVE->value,
                    \App\Enums\FaqLifecycleStatus::SYNC_FAILED->value,
                ])
                ->update([
                    'lifecycle_status' => \App\Enums\FaqLifecycleStatus::SYNCING->value,
                    'sync_error'       => null,
                ]);

            if ($affected === 0) {
                return;
            }

            $this->faq->refresh();
            $capturedUpdatedAt = $this->faq->updated_at;
            $capturedContentHash = hash('sha256', $this->faq->question . "\0" . $this->faq->answer . "\0" . $this->faq->workspace_id);

            // Step 2: Direct Vector Synchronization to Typesense & Python AI Retrieval Engine
            $synced = $retrievalClient->syncFaq($this->faq);

            // Check if authoritative database state was modified while remote sync was in-flight
            $currentFaq = FAQ::withTrashed()->find($this->faq->id);
            if (! $currentFaq) {
                $retrievalClient->deleteFaq($this->faq->id, $this->faq->workspace_id);
                return;
            }

            $currentContentHash = hash('sha256', $currentFaq->question . "\0" . $currentFaq->answer . "\0" . $currentFaq->workspace_id);
            $isModifiedDuringSync = (
                $currentFaq->trashed() ||
                ! $currentFaq->is_active && $currentFaq->lifecycle_status !== \App\Enums\FaqLifecycleStatus::SYNCING ||
                $currentFaq->updated_at != $capturedUpdatedAt ||
                $currentContentHash !== $capturedContentHash
            );

            // If a concurrent update modified the FAQ while this job was calling remote Typesense
            // If a concurrent update modified the FAQ while this job was calling remote Typesense
            if ($isModifiedDuringSync) {
                Log::info('[FAQIndexJob] Detected concurrent modification during remote sync window', [
                    'faq_id'             => $this->faq->id,
                    'is_trashed'         => $currentFaq->trashed(),
                    'is_active'          => $currentFaq->is_active,
                    'current_status'     => $currentFaq->lifecycle_status->value,
                    'content_hash_match' => ($currentContentHash === $capturedContentHash),
                ]);

                // If the FAQ was deleted or deactivated while sync was in-flight, ensure Typesense is purged
                if ($currentFaq->trashed() || (! $currentFaq->is_active && $currentFaq->lifecycle_status !== \App\Enums\FaqLifecycleStatus::SYNCING)) {
                    $retrievalClient->deleteFaq($currentFaq->id, $currentFaq->workspace_id);
                }

                // An expired/superseded job must NEVER initiate an uncoordinated corrective upsert,
                // as that can race with subsequent operations (e.g. V3, deactivations).
                // The authoritative sync belongs solely to the job dispatched for that newer version.
                return;
            }

            if ($synced === false) {
                // Update to SYNC_FAILED atomically only if the model is STILL in the exact captured state
                FAQ::where('id', $this->faq->id)
                    ->where('updated_at', $capturedUpdatedAt)
                    ->where('lifecycle_status', \App\Enums\FaqLifecycleStatus::SYNCING->value)
                    ->update([
                        'lifecycle_status' => \App\Enums\FaqLifecycleStatus::SYNC_FAILED->value,
                        'is_active'        => false,
                        'sync_error'       => 'Vector retrieval engine synchronization failed.',
                    ]);
                return;
            }

            // Step 3: Transition to ACTIVE & searchable atomically
            $updated = FAQ::where('id', $this->faq->id)
                ->where('updated_at', $capturedUpdatedAt)
                ->where('lifecycle_status', \App\Enums\FaqLifecycleStatus::SYNCING->value)
                ->update([
                    'lifecycle_status' => \App\Enums\FaqLifecycleStatus::ACTIVE->value,
                    'is_active'        => true,
                    'sync_error'       => null,
                ]);

            // If the record was altered/deactivated right at the commit moment, purge if inactive
            if ($updated === 0) {
                $currentFaq = FAQ::withTrashed()->find($this->faq->id);
                if ($currentFaq && ($currentFaq->trashed() || ! $currentFaq->is_active)) {
                    $retrievalClient->deleteFaq($currentFaq->id, $currentFaq->workspace_id);
                }
                return;
            }

            $this->faq->refresh();

            Log::debug('[FAQIndexJob] FAQ successfully synced and activated', [
                'faq_id' => $this->faq->id,
                'action' => $this->action,
                'status' => 'active',
            ]);
        } catch (\Throwable $e) {
            // Guard against stale failure overwriting newer state
            if (isset($capturedUpdatedAt)) {
                FAQ::where('id', $this->faq->id)
                    ->where('updated_at', $capturedUpdatedAt)
                    ->where('lifecycle_status', \App\Enums\FaqLifecycleStatus::SYNCING->value)
                    ->update([
                        'lifecycle_status' => \App\Enums\FaqLifecycleStatus::SYNC_FAILED->value,
                        'is_active'        => false,
                        'sync_error'       => $e->getMessage(),
                    ]);
            }

            Log::error('[FAQIndexJob] Sync failed', [
                'faq_id' => $this->faq->id,
                'action' => $this->action,
                'error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    } finally {
        optional($lock)->release();
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
