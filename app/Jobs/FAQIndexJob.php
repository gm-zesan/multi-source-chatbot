<?php

namespace App\Jobs;

use App\Models\FAQ;
use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\NLP\TextPreprocessor;
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
    ): void {
        try {
            match ($this->action) {
                'delete' => $this->handleDelete(),
                default  => $this->handleIndex($preprocessor, $embeddings),
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
        TextPreprocessor $preprocessor,
        EmbeddingService $embeddings,
    ): void {
        $faq = $this->faq;

        // 1. Preprocess text
        $processed = $preprocessor->process(
            text: $faq->question . ' ' . $faq->answer,
            language: 'en',
        );

        // 2. Generate embedding from the normalized text
        $searchableText = $processed->normalized;

        try {
            $embeddingResponse = $embeddings->embed($searchableText);
            $embedding = $embeddingResponse->vector;
        } catch (\Throwable $e) {
            Log::warning('[FAQIndexJob] Embedding generation failed, indexing without vector', [
                'faq_id' => $faq->id,
                'error'  => $e->getMessage(),
            ]);
            $embedding = [];
        }

        // 3. Store embedding on the model
        if (! empty($embedding)) {
            $faq->embedding_version = $embeddings->config('model', 'unknown');
        }

        // 4. Save searchable_text to the DB for MySQL full-text fallback
        $faq->searchable_text = $searchableText;
        $faq->saveQuietly();

        // 5. Sync to Typesense via Scout
        $faq->searchable();
    }

    /**
     * Remove the FAQ from Typesense.
     */
    private function handleDelete(): void
    {
        $this->faq->unsearchable();
    }
}
