<?php

declare(strict_types=1);

namespace App\Services\FAQ;

use App\Models\FAQ;
use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\NLP\TextPreprocessor;
use App\Services\Search\TypesenseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class FAQSearch
{
    /**
     * Typesense collection name for FAQs.
     */
    private const COLLECTION = 'faqs';

    /**
     * Fields queried for keyword matching.
     */
    private const QUERY_BY = 'searchable_text,question,answer';

    /**
     * Default weights for Typesense hybrid search score combination.
     */
    private const KEYWORD_WEIGHT = 0.4;
    private const VECTOR_WEIGHT = 0.6;

    /**
     * Number of vector candidates to retrieve.
     */
    private const VECTOR_K = 100;

    public function __construct(
        private readonly TextPreprocessor $preprocessor,
        private readonly EmbeddingService $embeddings,
        private readonly TypesenseService $typesense,
    ) {}

    /**
     * Run a hybrid search — keyword + vector via Typesense.
     *
     * @param string   $query        The raw customer query.
     * @param int      $perPage      Results per page.
     * @param int|null $workspaceId  Optional workspace filter.
     * @return Collection<int, FAQSearchResult>
     */
    public function search(
        string $query,
        int $perPage = 10,
        ?int $workspaceId = null,
    ): Collection {
        if (trim($query) === '') {
            return Collection::empty();
        }

        // 1. Preprocess the query
        $processed = $this->preprocessor->process($query, language: 'en');
        $processedQuery = $processed->normalized;

        Log::debug('[FAQSearch] Query preprocessed', [
            'original'   => mb_substr($query, 0, 80),
            'normalized' => mb_substr($processedQuery, 0, 80),
        ]);

        // 2. Generate embedding vector for the query
        $queryVector = $this->generateQueryVector($processedQuery);

        // 3. Build filter expression
        $filterBy = $this->buildFilter($workspaceId);

        // 4. Search — hybrid if vector available, keyword-only fallback otherwise
        $hasVector = ! empty($queryVector);

        if ($hasVector) {
            $response = $this->typesense->hybridSearch(
                collection: self::COLLECTION,
                query: $processedQuery,
                vector: $queryVector,
                params: [
                    'query_by'       => self::QUERY_BY,
                    'filter_by'      => $filterBy,
                    'per_page'       => $perPage,
                    'keyword_weight' => self::KEYWORD_WEIGHT,
                    'vector_weight'  => self::VECTOR_WEIGHT,
                    'vector_k'       => self::VECTOR_K,
                ],
            );
        } else {
            Log::info('[FAQSearch] Embedding unavailable, using keyword-only search', [
                'query' => mb_substr($processedQuery, 0, 80),
            ]);

            $response = $this->typesense->search(
                collection: self::COLLECTION,
                query: $processedQuery,
                params: [
                    'query_by'  => self::QUERY_BY,
                    'filter_by' => $filterBy,
                    'per_page'  => $perPage,
                ],
            );
        }

        // 5. Map TypesenseSearchResult → FAQSearchResult (load FAQ models)
        return $this->mapResults($response['results'] ?? [], $perPage);
    }

    // ─── Internal ──────────────────────────────────────────────────────────

    /**
     * Generate the embedding vector for the query text.
     *
     * @return array<float>
     */
    private function generateQueryVector(string $query): array
    {
        try {
            $response = $this->embeddings->embed($query);

            return $response->vector;
        } catch (\Throwable $e) {
            Log::warning('[FAQSearch] Query embedding failed, will use keyword-only fallback', [
                'query' => mb_substr($query, 0, 80),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Build a Typesense filter expression.
     */
    private function buildFilter(?int $workspaceId): string
    {
        $filters = ['is_active:=true'];

        if ($workspaceId !== null) {
            $filters[] = 'workspace_id:=' . $workspaceId;
        }

        return implode(' && ', $filters);
    }

    /**
     * Map TypesenseSearchResult documents to FAQSearchResult objects.
     *
     * Each Typesense document is loaded as a FAQ model so downstream
     * consumers (FAQAnswerEngine, FAQScoreCalculator) receive full Eloquent models.
     *
     * @param array<int, \App\Services\Search\TypesenseSearchResult> $results
     * @return Collection<int, FAQSearchResult>
     */
    private function mapResults(array $results, int $perPage): Collection
    {
        $mapped = [];

        foreach ($results as $result) {
            $docId = $result->get('id');

            if ($docId === null) {
                continue;
            }

            // Load the FAQ model (hit-count and last_used_at are tracked separately)
            $faq = FAQ::find($docId);
            if (! $faq) {
                Log::debug('[FAQSearch] FAQ not found in DB, skipping', [
                    'faq_id' => $docId,
                ]);
                continue;
            }

            $mapped[] = new FAQSearchResult(
                faq: $faq,
                keywordScore: $result->keywordScore,
                semanticScore: $result->vectorScore,
                finalScore: $result->finalScore,
                matchType: $result->matchType,
            );
        }

        // Already sorted by TypesenseService, but enforce just in case
        usort($mapped, fn (FAQSearchResult $a, FAQSearchResult $b)
            => $b->finalScore <=> $a->finalScore);

        return Collection::make(array_slice($mapped, 0, $perPage));
    }
}

