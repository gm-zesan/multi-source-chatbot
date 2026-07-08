<?php

namespace App\Services\FAQ;

use App\Models\FAQ;
use App\Services\NLP\Embedding\EmbeddingService;
use App\Services\NLP\TextPreprocessor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Typesense\Client as TypesenseClient;

class FAQSearch
{
    /**
     * Default weights for score combination.
     */
    private const DEFAULT_KEYWORD_WEIGHT = 0.4;
    private const DEFAULT_SEMANTIC_WEIGHT = 0.4;
    private const DEFAULT_PRIORITY_WEIGHT = 0.2;

    /**
     * Priority bonus cap to avoid overly boosting old high-priority items.
     */
    private const MAX_PRIORITY_BONUS = 0.2;

    private ?TypesenseClient $typesense = null;

    public function __construct(
        private readonly TextPreprocessor $preprocessor,
        private readonly EmbeddingService $embeddings,
    ) {}

    /**
     * Run a hybrid search — keyword + semantic + priority boost.
     *
     * @param string $query        The raw customer query.
     * @param int    $perPage      Results per page.
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
        $keywordTokens = $processed->keyword;

        Log::debug('[FAQSearch] Query preprocessed', [
            'original' => mb_substr($query, 0, 80),
            'normalized' => mb_substr($processedQuery, 0, 80),
        ]);

        // 2. Generate embedding vector for semantic search
        $queryVector = $this->generateQueryVector($processedQuery);

        // 3. Run keyword search via Scout/Typesense
        $keywordResults = $this->keywordSearch($processedQuery, $perPage, $workspaceId);

        // 4. Run semantic search via Typesense vector query
        $semanticResults = [];
        if (! empty($queryVector)) {
            $semanticResults = $this->semanticSearch($queryVector, $perPage, $workspaceId);
        }

        // 5. Merge, score, rank
        return $this->rankResults(
            keywordResults: $keywordResults,
            semanticResults: $semanticResults,
            perPage: $perPage,
        );
    }

    // ─── Search Strategies ────────────────────────────────────────────────

    /**
     * Keyword search via Laravel Scout (Typesense full-text search).
     *
     * @return array<string, array{faq: FAQ, textScore: float}>
     */
    private function keywordSearch(string $query, int $perPage, ?int $workspaceId): array
    {
        try {
            $builder = FAQ::search($query);

            // Apply workspace filter if needed
            if ($workspaceId !== null) {
                $builder->where('workspace_id', '=', $workspaceId);
            }

            // Only active FAQs
            $builder->where('is_active', '=', true);

            /** @var \Illuminate\Pagination\LengthAwarePaginator $results */
            $results = $builder->paginateRaw($perPage);

            $mapped = [];
            $hits = $results->items()['hits'] ?? [];

            foreach ($hits as $hit) {
                $document = $hit['document'] ?? [];
                $id = $document['id'] ?? null;

                if ($id === null) continue;

                $faq = FAQ::find($id);
                if (! $faq) continue;

                $textScore = ($hit['text_match_info']['score'] ?? 0) / 1000;
                $textScore = min(max($textScore, 0), 1);

                $mapped[$id] = [
                    'faq'      => $faq,
                    'textScore'=> $textScore,
                ];
            }

            return $mapped;
        } catch (\Throwable $e) {
            Log::error('[FAQSearch] Keyword search failed', [
                'query' => mb_substr($query, 0, 80),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Semantic search via Typesense vector query.
     *
     * @param array<float> $vector
     * @return array<string, array{faq: FAQ, vecScore: float}>
     */
    private function semanticSearch(array $vector, int $perPage, ?int $workspaceId): array
    {
        try {
            $client = $this->getTypesenseClient();

            $searchParams = [
                'q'            => '*',
                'query_by'     => 'searchable_text',
                'vector_query' => 'embedding:([ ' . implode(',', $vector) . ' ], k: ' . ($perPage * 2) . ')',
                'per_page'     => $perPage * 2,
                'filter_by'    => $this->buildFilter($workspaceId),
            ];

            $results = $client->getCollections()->{$this->getCollectionName()}->documents->search($searchParams);
            $hits = $results['hits'] ?? [];

            $mapped = [];
            foreach ($hits as $hit) {
                $doc = $hit['document'] ?? [];
                $id = $doc['id'] ?? null;

                if ($id === null) continue;

                $faq = FAQ::find($id);
                if (! $faq) continue;

                // Typesense vector_distance is roughly 0 = perfect match, higher = worse
                $vecDistance = $hit['vector_distance'] ?? 1.0;
                $vecScore = 1.0 - min(max($vecDistance, 0), 1);
                $vecScore = max($vecScore, 0);

                $mapped[$id] = [
                    'faq'      => $faq,
                    'vecScore' => $vecScore,
                ];
            }

            return $mapped;
        } catch (\Throwable $e) {
            Log::error('[FAQSearch] Semantic search failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ─── Scoring ──────────────────────────────────────────────────────────

    /**
     * Merge keyword + semantic results, compute final score, apply priority bonus, sort.
     *
     * @param array<string, array{faq: FAQ, textScore: float}>       $keywordResults
     * @param array<string, array{faq: FAQ, vecScore: float}>        $semanticResults
     * @return Collection<int, FAQSearchResult>
     */
    private function rankResults(
        array $keywordResults,
        array $semanticResults,
        int $perPage,
    ): Collection {
        $combined = [];

        // Merge all unique FAQ IDs from both result sets
        $allIds = array_unique(array_merge(array_keys($keywordResults), array_keys($semanticResults)));

        foreach ($allIds as $id) {
            $faq = $keywordResults[$id]['faq'] ?? $semanticResults[$id]['faq'] ?? null;
            if (! $faq) continue;

            $textScore = $keywordResults[$id]['textScore'] ?? 0;
            $vecScore  = $semanticResults[$id]['vecScore'] ?? 0;

            // Determine match type
            $matchType = 'keyword';
            if ($textScore > 0 && $vecScore > 0) {
                $matchType = 'hybrid';
            } elseif ($vecScore > 0) {
                $matchType = 'semantic';
            }

            // Combined score with weights
            $rawScore = ($textScore * self::DEFAULT_KEYWORD_WEIGHT)
                      + ($vecScore * self::DEFAULT_SEMANTIC_WEIGHT);

            // Priority bonus: higher priority = small boost (0 to MAX_PRIORITY_BONUS)
            $priorityNormalized = min(($faq->priority / 100), 1);
            $priorityBonus = $priorityNormalized * self::MAX_PRIORITY_BONUS * self::DEFAULT_PRIORITY_WEIGHT;

            $finalScore = $rawScore + $priorityBonus;
            $finalScore = min(max($finalScore, 0), 1);

            $combined[] = new FAQSearchResult(
                faq: $faq,
                keywordScore: $textScore,
                semanticScore: $vecScore,
                finalScore: $finalScore,
                matchType: $matchType,
            );
        }

        // Sort by final score descending
        usort($combined, fn (FAQSearchResult $a, FAQSearchResult $b) => $b->finalScore <=> $a->finalScore);

        // Limit results
        $combined = array_slice($combined, 0, $perPage);

        return Collection::make($combined);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Generate the embedding vector for the query.
     */
    private function generateQueryVector(string $query): array
    {
        try {
            $response = $this->embeddings->embed($query);
            return $response->vector;
        } catch (\Throwable $e) {
            Log::warning('[FAQSearch] Embedding generation failed for query', [
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
     * Get the Typesense collection name for FAQs.
     */
    private function getCollectionName(): string
    {
        $prefix = Config::get('scout.prefix', '');
        return $prefix . (new FAQ())->searchableAs();
    }

    /**
     * Get or create the Typesense HTTP client from Scout config.
     */
    private function getTypesenseClient(): TypesenseClient
    {
        if ($this->typesense === null) {
            $config = Config::get('scout.typesense.client-settings', []);
            $this->typesense = new TypesenseClient($config);
        }

        return $this->typesense;
    }
}
