<?php

namespace App\Services\FAQ;

use App\Jobs\FAQIndexJob;
use App\Models\FAQ;
use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\NLP\TextPreprocessor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FAQIndexer
{
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
    ) {}

    /**
     * Index a single FAQ — generates embedding, builds searchable_text,
     * and syncs to Typesense.
     */
    public function index(FAQ $faq): void
    {
        $this->preprocessAndEmbed($faq);
        $faq->searchable();
    }

    /**
     * Update an existing FAQ in the index.
     */
    public function update(FAQ $faq): void
    {
        $this->preprocessAndEmbed($faq);
        $faq->searchable();
    }

    /**
     * Remove a single FAQ from Typesense.
     */
    public function delete(FAQ $faq): void
    {
        $faq->unsearchable();

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
        $count = 0;

        foreach ($faqs as $faq) {
            if (! $faq->shouldBeSearchable()) {
                $faq->unsearchable();
                continue;
            }

            $this->preprocessAndEmbed($faq);
            $count++;
        }

        // Bulk sync to Typesense via Scout
        $faqs->filter->shouldBeSearchable()->searchable();

        Log::debug('[FAQIndexer] Batch index completed', [
            'total'   => $faqs->count(),
            'indexed' => $count,
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
            DB::transaction(function () use ($faqs) {
                // Unsearchable all first to clear the index
                $faqs->each->unsearchable();
            });

            // Re-index only the searchable ones
            $searchable = $faqs->filter->shouldBeSearchable();

            foreach ($searchable as $faq) {
                $this->preprocessAndEmbed($faq);
            }

            $searchable->searchable();

            $total += $searchable->count();
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
     * Preprocess text, generate embedding, and persist to the model.
     */
    private function preprocessAndEmbed(FAQ $faq): void
    {
        // 1. Build raw text from question + answer
        $raw = trim(($faq->question ?? '') . ' ' . ($faq->answer ?? ''));

        // 2. Preprocess / normalize
        $processed = $this->preprocessor->process($raw, language: 'en');
        $searchableText = $processed->normalized;

        // 3. Generate embedding vector
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

        // 4. Persist to model (quietly to avoid recursive indexing)
        $faq->searchable_text = $searchableText;

        if (! empty($embedding)) {
            $faq->embedding_version = $this->embeddings->config('model', 'unknown');
        }

        $faq->saveQuietly();
    }
}
