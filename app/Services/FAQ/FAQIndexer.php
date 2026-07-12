<?php

declare(strict_types=1);

namespace App\Services\FAQ;

use App\Jobs\FAQIndexJob;
use App\Models\FAQ;
use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\NLP\TextPreprocessor;
use App\Services\Search\TypesenseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class FAQIndexer
{
    /**
     * Typesense collection name for FAQs.
     */
    private const COLLECTION = 'faqs';

    /**
     * Maximum number of FAQs per batch.
     */
    public const BATCH_SIZE = 100;

    /**
     * Chunk size for reindex operations.
     */
    public const REINDEX_CHUNK = 200;

    public function __construct(
        private readonly TextPreprocessor $preprocessor,
        private readonly EmbeddingService $embeddings,
        private readonly TypesenseService $typesense,
    ) {}

    /**
     * Index a single FAQ — generates embedding, builds document, syncs to Typesense.
     */
    public function index(FAQ $faq): void
    {
        $document = $this->buildDocument($faq);

        $this->typesense->upsertDocument(self::COLLECTION, $document);

        Log::debug('[FAQIndexer] Indexed', [
            'faq_id' => $faq->id,
        ]);
    }

    /**
     * Update an existing FAQ in the index.
     */
    public function update(FAQ $faq): void
    {
        $document = $this->buildDocument($faq);

        $this->typesense->upsertDocument(self::COLLECTION, $document);

        Log::debug('[FAQIndexer] Updated', [
            'faq_id' => $faq->id,
        ]);
    }

    /**
     * Remove a single FAQ from Typesense.
     */
    public function delete(FAQ $faq): void
    {
        $this->typesense->deleteDocument(self::COLLECTION, (string) $faq->id);

        Log::debug('[FAQIndexer] Removed from index', [
            'faq_id' => $faq->id,
        ]);
    }

    /**
     * Index multiple FAQs in one batch.
     *
     * @param Collection<int, FAQ> $faqs
     */
    public function batchIndex(Collection $faqs): void
    {
        $documents = [];

        foreach ($faqs as $faq) {
            if (! $faq->shouldBeSearchable()) {
                $this->typesense->deleteDocument(self::COLLECTION, (string) $faq->id);
                continue;
            }

            $documents[] = $this->buildDocument($faq);
        }

        if (! empty($documents)) {
            $this->typesense->upsertDocuments(self::COLLECTION, $documents);
        }

        Log::debug('[FAQIndexer] Batch index completed', [
            'total'   => $faqs->count(),
            'indexed' => count($documents),
        ]);
    }

    /**
     * Reindex all FAQs — useful after schema changes or service recovery.
     *
     * Processes in chunks to avoid memory exhaustion.
     */
    public function reindexAll(): void
    {
        $total = 0;

        FAQ::withTrashed()->chunk(self::REINDEX_CHUNK, function (Collection $faqs) use (&$total) {
            // Remove non-searchable (soft-deleted / inactive) from index
            foreach ($faqs as $faq) {
                if (! $faq->shouldBeSearchable()) {
                    $this->typesense->deleteDocument(self::COLLECTION, (string) $faq->id);
                }
            }

            // Build documents for searchable ones
            $searchable = $faqs->filter->shouldBeSearchable();
            $documents = [];

            foreach ($searchable as $faq) {
                $documents[] = $this->buildDocument($faq);
            }

            if (! empty($documents)) {
                $this->typesense->upsertDocuments(self::COLLECTION, $documents);
            }

            $total += count($documents);
        });

        Log::info('[FAQIndexer] Reindex completed', [
            'total_indexed' => $total,
        ]);
    }

    /**
     * Dispatch a queued job for single FAQ indexing.
     */
    public function dispatchIndex(FAQ $faq, string $action = 'index'): void
    {
        FAQIndexJob::dispatch($faq, $action);
    }

    /**
     * Dispatch queued jobs for batch indexing.
     *
     * @param Collection<int, FAQ> $faqs
     */
    public function dispatchBatch(Collection $faqs, string $action = 'index'): void
    {
        $faqs->each(fn (FAQ $faq) => FAQIndexJob::dispatch($faq, $action));
    }

    // ─── Internal ──────────────────────────────────────────────────────────

    /**
     * Build the full document array for Typesense — preprocesses text,
     * generates the embedding vector, and returns a complete document
     * ready for upsert.
     *
     * Also persists searchable_text and embedding_version to MySQL
     * for fallback / auditing.
     *
     * @return array<string, mixed>
     */
    public function buildDocument(FAQ $faq): array
    {
        // 1. Build raw text from question + answer
        $raw = trim(($faq->question ?? '') . ' ' . ($faq->answer ?? ''));

        // 2. Preprocess / normalize
        $processed = $this->preprocessor->process($raw, language: 'en');
        $searchableText = $processed->normalized;

        // 3. Generate embedding vector via Python service
        $embedding = [];
        try {
            $response = $this->embeddings->embed($searchableText);
            $embedding = $response->vector;
        } catch (\Throwable $e) {
            Log::warning('[FAQIndexer] Embedding failed, indexing without vector', [
                'faq_id' => $faq->id,
                'error'  => $e->getMessage(),
            ]);
        }

        // 4. Persist metadata to MySQL (quietly — avoid recursive events)
        $faq->searchable_text = $searchableText;

        if (! empty($embedding)) {
            $faq->embedding_version = $this->embeddings->config('model', 'unknown');
        }

        $faq->saveQuietly();

        // 5. Build the Typesense document WITH the embedding vector
        return [
            'id'              => (string) $faq->id,
            'workspace_id'    => $faq->workspace_id,
            'question'        => $faq->question,
            'answer'          => $faq->answer,
            'searchable_text' => $searchableText,
            'priority'        => (int) $faq->priority,
            'embedding'       => $embedding,
            'is_active'       => $faq->is_active,
            'created_at'      => $faq->created_at?->timestamp ?? time(),
        ];
    }
}
