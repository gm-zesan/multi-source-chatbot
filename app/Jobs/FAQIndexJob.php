<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\FAQ;
use App\Services\FAQ\FAQIndexer;
use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\NLP\TextPreprocessor;
use App\Services\Search\TypesenseService;
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
     * Typesense collection name for FAQs.
     */
    private const COLLECTION = 'faqs';

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
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TextPreprocessor $preprocessor,
        EmbeddingService $embeddings,
        TypesenseService $typesense,
        FAQIndexer $indexer,
    ): void {
        try {
            match ($this->action) {
                'delete' => $this->handleDelete($typesense),
                default  => $this->handleIndex($typesense, $indexer),
            };

            Log::debug('[FAQIndexJob] Completed', [
                'faq_id' => $this->faq->id,
                'action' => $this->action,
            ]);
        } catch (\Throwable $e) {
            Log::error('[FAQIndexJob] Failed', [
                'faq_id' => $this->faq->id,
                'action' => $this->action,
                'error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Index or update the FAQ in Typesense.
     */
    private function handleIndex(
        TypesenseService $typesense,
        FAQIndexer $indexer,
    ): void {
        $faq = $this->faq;

        // Guard: skip indexing if FAQ is no longer searchable
        if (! $faq->shouldBeSearchable()) {
            $typesense->deleteDocument(self::COLLECTION, (string) $faq->id);

            Log::info('[FAQIndexJob] Skipped indexing, FAQ is not searchable; document removed from Typesense', [
                'faq_id' => $faq->id,
                'action' => $this->action,
            ]);

            return;
        }

        // Delegate document building to FAQIndexer (single source of truth)
        $document = $indexer->buildDocument($faq);

        $typesense->upsertDocument(self::COLLECTION, $document);

        Log::debug('[FAQIndexJob] Document upserted to Typesense', [
            'faq_id'     => $faq->id,
            'has_vector' => ! empty($document['embedding'] ?? []),
        ]);
    }

    /**
     * Remove the FAQ from Typesense.
     */
    private function handleDelete(TypesenseService $typesense): void
    {
        $typesense->deleteDocument(self::COLLECTION, (string) $this->faq->id);

        Log::debug('[FAQIndexJob] Document deleted from Typesense', [
            'faq_id' => $this->faq->id,
        ]);
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
