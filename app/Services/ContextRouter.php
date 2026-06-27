<?php

namespace App\Services;

use App\DTO\RoutingResult;
use App\Models\Embedding;
use App\Models\SourceTable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ContextRouter
{
    /**
     * Cache key prefix for candidate lists.
     */
    protected const CACHE_KEY_CANDIDATES = 'chatbot:router:candidates';

    /**
     * TTL for cached candidate lists (1 hour).
     */
    protected const CACHE_TTL_CANDIDATES = 3600;

    /**
     * The last computed confidence score (for external access via getConfidence()).
     */
    protected float $lastConfidence = 0.0;

    /**
     * The last routing result (for external access via getConfidence(), getFallbackMessage()).
     */
    protected ?RoutingResult $lastResult = null;

    public function __construct(
        protected VectorEmbeddingService $embeddingService,
    ) {}

    // ─── Main Routing ────────────────────────────────────────────────────

    /**
     * Route a natural language input through multi-level semantic matching.
     *
     * Three routing levels:
     *   1. Database (source) — which DB connection matches the input
     *   2. Table — which table within the matched source
     *   3. Query Type — SELECT, COUNT, aggregate, filtered_search
     *
     * Falls back to keyword matching if semantic confidence is below the
     * fallback threshold from config.
     *
     * @param  string $input The user's natural language query
     * @return RoutingResult Immutable result with source, table, confidence
     */
    public function route(string $input): RoutingResult
    {
        $startTime = microtime(true);

        // ── Level 1: Database Routing ──
        $dbThreshold    = config('chatbot.router.thresholds.database', 0.65);
        $dbCandidates   = $this->getDatabaseCandidates();
        $dbMatch        = $this->embeddingService->findBestMatch($input, $dbCandidates, $dbThreshold);
        $dbConfidence   = $dbMatch ? $dbMatch['similarity'] : 0.0;

        // ── Level 2: Table Routing ──
        $tableThreshold  = config('chatbot.router.thresholds.table', 0.55);
        $tableCandidates = $this->getTableCandidates($dbMatch['key'] ?? null);
        $tableMatch      = $this->embeddingService->findBestMatch($input, $tableCandidates, $tableThreshold);
        $tableConfidence = $tableMatch ? $tableMatch['similarity'] : 0.0;

        // ── Level 3: Query Type Routing ──
        $queryType = $this->detectQueryType($input);

        // ── Composite Confidence ──
        // When only database match exists, use its score for table as well
        // to avoid penalizing queries that are fully resolved at DB level.
        $effectiveTableConfidence = $tableConfidence > 0 ? $tableConfidence : $dbConfidence;
        $weights = $this->getCompositeWeights();
        $compositeConfidence = $effectiveTableConfidence * $weights['table']
                             + $dbConfidence              * $weights['database']
                             + max($dbConfidence, $tableConfidence) * $weights['query_type'];

        $elapsed = (microtime(true) - $startTime) * 1000;

        // ── Fallback Decision ──
        $fallbackThreshold = config('chatbot.router.thresholds.fallback', 0.45);

        if ($compositeConfidence < $fallbackThreshold
            && config('chatbot.router.enable_fallback', true)) {

            $fallbackResult = $this->keywordFallback($input);

            if ($fallbackResult !== null) {
                $compositeConfidence = max($compositeConfidence, $fallbackResult->confidence);
                $dbConfidence   = max($dbConfidence, $fallbackResult->matchedDbScore);
                $tableConfidence = max($tableConfidence, $fallbackResult->matchedTableScore);

                $result = new RoutingResult(
                    sourceId:         $fallbackResult->sourceId ?? ($dbMatch['key'] ?? null),
                    table:            $fallbackResult->table,
                    queryType:        $queryType,
                    confidence:       round($compositeConfidence, 4),
                    matchedDbScore:   round($dbConfidence, 4),
                    matchedTableScore: round($tableConfidence, 4),
                    metadata:         $this->buildMetadata('keyword_fallback', $dbMatch, $tableMatch, $dbConfidence, $tableConfidence, $elapsed),
                    usedFallback:     true,
                );

                $this->lastResult = $result;
                $this->lastConfidence = $result->confidence;
                return $result;
            }
        }

        // ── Semantic Result ──
        $result = new RoutingResult(
            sourceId:         $dbMatch['key'] ?? null,
            table:            $tableMatch['key'] ?? null,
            queryType:        $queryType,
            confidence:       round($compositeConfidence, 4),
            matchedDbScore:   round($dbConfidence, 4),
            matchedTableScore: round($tableConfidence, 4),
            metadata:         $this->buildMetadata('semantic', $dbMatch, $tableMatch, $dbConfidence, $tableConfidence, $elapsed),
            usedFallback:     false,
        );

        $this->lastResult = $result;
        $this->lastConfidence = $result->confidence;
        return $result;
    }

    // ─── Candidate Providers ─────────────────────────────────────────────

    /**
     * Get database-level embedding candidates (Collection of Embedding models).
     *
     * @return array<int, Embedding>
     */
    public function getDatabaseCandidates(): array
    {
        return $this->getCachedCandidates('databases', function () {
            $candidates = Embedding::byType(Embedding::ENTITY_TYPE_DATABASE)->get();
            return $candidates->isNotEmpty()
                ? $candidates
                : $this->buildDatabaseCandidatesFromConfig();
        });
    }

    /**
     * Get table-level embedding candidates, optionally scoped to a source.
     *
     * @param  string|null $sourceId Optional connection name to filter by
     * @return array<int, Embedding>
     */
    public function getTableCandidates(?string $sourceId = null): array
    {
        $cacheKey = 'tables' . ($sourceId ? ":{$sourceId}" : '');

        return $this->getCachedCandidates($cacheKey, function () use ($sourceId) {
            $query = Embedding::byType(Embedding::ENTITY_TYPE_TABLE);
            if ($sourceId) {
                $query->bySource($sourceId);
            }
            $candidates = $query->get();
            return $candidates->isNotEmpty()
                ? $candidates
                : $this->buildTableCandidatesFromRegistry($sourceId);
        });
    }

    // ─── Query Type Detection ────────────────────────────────────────────

    /**
     * Detect the query intent type from the input string.
     *
     * Uses a combination of keyword matching and (if available) semantic
     * matching against query type embeddings.
     *
     * @param  string $input The user's natural language query
     * @return string One of: 'select', 'count', 'aggregate', 'filtered_search'
     */
    public function detectQueryType(string $input): string
    {
        $inputLower = strtolower(trim($input));

        // ── Fast keyword matching ──
        $keywordMap = [
            'count'          => 'count',
            'how many'       => 'count',
            'total count'    => 'count',
            'sum'            => 'aggregate',
            'total'          => 'aggregate',
            'average'        => 'aggregate',
            'avg'            => 'aggregate',
            'minimum'        => 'aggregate',
            'minimum of'     => 'aggregate',
            'maximum'        => 'aggregate',
            'max of'         => 'aggregate',
            'where'          => 'filtered_search',
            'filter'         => 'filtered_search',
            'having'         => 'filtered_search',
            'greater than'   => 'filtered_search',
            'less than'      => 'filtered_search',
            'between'        => 'filtered_search',
        ];

        foreach ($keywordMap as $keyword => $type) {
            if (str_contains($inputLower, $keyword)) {
                return $type;
            }
        }

        // ── Semantic fallback ──
        try {
            $candidates = $this->getQueryTypeCandidates();
            $match = $this->embeddingService->findBestMatch($input, $candidates, 0.4);
            if ($match !== null) {
                return $match['key'];
            }
        } catch (\Throwable $e) {
            Log::debug('Query type semantic detection failed, defaulting to select', [
                'error' => $e->getMessage(),
            ]);
        }

        return 'select';
    }

    // ─── Confidence & Status ─────────────────────────────────────────────

    /**
     * Get the confidence score from the last routing operation.
     *
     * @return float 0.0 – 1.0
     */
    public function getConfidence(): float
    {
        return $this->lastConfidence;
    }

    /**
     * Get a user-facing message explaining what went wrong when routing fails.
     *
     * @return string
     */
    public function getFallbackMessage(): string
    {
        if ($this->lastResult === null) {
            return 'No query has been processed yet.';
        }

        if ($this->lastResult->isActionable()) {
            return ''; // No fallback needed
        }

        if ($this->lastResult->usedFallback) {
            return 'I used keyword matching to find the closest data source. '
                 . 'For better results, try rephrasing your question or being more specific.';
        }

        return 'I could not find a matching data source for your query. '
             . 'Try using words like "show", "list", or "find" with a table name '
             . '(e.g., "show customers" or "list products").';
    }

    // ─── Private: Fallback ───────────────────────────────────────────────

    /**
     * Keyword-based fallback using the source_tables registry.
     *
     * Matches the input against table names and aliases via simple substring
     * matching. Less accurate than semantic routing but always available.
     */
    private function keywordFallback(string $input): ?RoutingResult
    {
        $inputLower = strtolower(trim($input));

        $sourceTables = SourceTable::select('table_name', 'alias', 'source_id')->get();

        foreach ($sourceTables as $table) {
            $aliasList = array_map('trim', explode(',', strtolower($table->alias ?? '')));
            $aliasList[] = strtolower($table->table_name);
            $aliasList = array_unique($aliasList);

            foreach ($aliasList as $alias) {
                if ($alias !== '' && str_contains($inputLower, $alias)) {
                    return new RoutingResult(
                        sourceId:         $table->source_id,
                        table:            $table->table_name,
                        queryType:        'select',
                        confidence:       0.70,
                        matchedDbScore:   0.0,
                        matchedTableScore: 0.0,
                        metadata:         [
                            'matched_keyword' => $alias,
                            'fallback'        => true,
                        ],
                        usedFallback:     true,
                    );
                }
            }
        }

        return null;
    }

    // ─── Private: Caching ────────────────────────────────────────────────

    /**
     * Get cached candidates or compute them via the provided callback.
     *
     * @param  string   $cacheKeySuffix Suffix for the cache key
     * @param  callable $callback       Returns Collection or array of candidates
     * @return array
     */
    private function getCachedCandidates(string $cacheKeySuffix, callable $callback): array
    {
        $cacheKey = self::CACHE_KEY_CANDIDATES . ':' . $cacheKeySuffix;

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached instanceof Collection ? $cached->all() : $cached;
        }

        $candidates = $callback();
        $array = $candidates instanceof Collection ? $candidates->all() : $candidates;

        Cache::put($cacheKey, $array, self::CACHE_TTL_CANDIDATES);

        return $array;
    }

    /**
     * Get query type embedding candidates.
     *
     * @return array
     */
    private function getQueryTypeCandidates(): array
    {
        return $this->getCachedCandidates('query_types', function () {
            $candidates = Embedding::byType('query_type')->get();
            return $candidates->isNotEmpty()
                ? $candidates
                : collect($this->buildQueryTypeCandidatesFromConfig());
        });
    }

    // ─── Private: Metadata ───────────────────────────────────────────────

    /**
     * Build a metadata array for the routing result.
     */
    private function buildMetadata(
        string $path,
        ?array $dbMatch,
        ?array $tableMatch,
        float $dbConfidence,
        float $tableConfidence,
        float $elapsedMs,
    ): array {
        return [
            'routing_path' => $path,
            'db_match'     => $dbMatch,
            'table_match'  => $tableMatch,
            'elapsed_ms'   => round($elapsedMs, 2),
            'level_scores' => [
                'database' => round($dbConfidence, 4),
                'table'    => round($tableConfidence, 4),
            ],
        ];
    }

    /**
     * Get composite weights from config.
     *
     * @return array<string, float>
     */
    private function getCompositeWeights(): array
    {
        return [
            'table'      => (float) config('chatbot.router.composite_weights.table', 0.50),
            'database'   => (float) config('chatbot.router.composite_weights.database', 0.30),
            'query_type' => (float) config('chatbot.router.composite_weights.query_type', 0.20),
        ];
    }

    // ─── Private: Fallback Builders ──────────────────────────────────────

    /**
     * Build in-memory database candidates from config (no-embedding fallback).
     */
    private function buildDatabaseCandidatesFromConfig(): Collection
    {
        $sources = config('chatbot.sources', []);
        $records = [];

        foreach ($sources as $sourceId => $cfg) {
            $records[] = new Embedding([
                'entity_type' => Embedding::ENTITY_TYPE_DATABASE,
                'entity_name' => $sourceId,
                'entity_key'  => $sourceId,
                'source_id'   => $sourceId,
                'embedding'   => [],
                'metadata'    => ['description' => $cfg['description'] ?? ''],
            ]);
        }

        return Collection::make($records);
    }

    /**
     * Build in-memory table candidates from registry (no-embedding fallback).
     */
    private function buildTableCandidatesFromRegistry(?string $sourceId = null): Collection
    {
        $query = SourceTable::query();
        if ($sourceId) {
            $query->where('source_id', $sourceId);
        }

        $tables = $query->get();
        $records = [];

        foreach ($tables as $table) {
            $description = "Table {$table->table_name}";
            $aliases = array_filter(array_map('trim', explode(',', $table->alias ?? '')));
            if (!empty($aliases)) {
                $description .= ' (also known as: ' . implode(', ', $aliases) . ')';
            }

            $records[] = new Embedding([
                'entity_type' => Embedding::ENTITY_TYPE_TABLE,
                'entity_name' => $table->table_name,
                'entity_key'  => $table->table_name,
                'source_id'   => $table->source_id,
                'embedding'   => [],
                'metadata'    => [
                    'description' => $description,
                    'aliases'     => $aliases,
                ],
            ]);
        }

        return Collection::make($records);
    }

    /**
     * Build in-memory query type candidates from config.
     */
    private function buildQueryTypeCandidatesFromConfig(): array
    {
        $types = config('chatbot.query_types', []);
        $records = [];

        foreach ($types as $type) {
            $records[] = new Embedding([
                'entity_type' => 'query_type',
                'entity_name' => $type['name'],
                'entity_key'  => $type['name'],
                'embedding'   => [],
                'metadata'    => ['description' => $type['description']],
            ]);
        }

        return $records;
    }
}
