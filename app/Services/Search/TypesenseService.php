<?php

declare(strict_types=1);

namespace App\Services\Search;

use Illuminate\Support\Facades\Log;
use Typesense\Client as TypesenseClient;
use Typesense\Exceptions\ObjectNotFound;

class TypesenseService
{
    /**
     * Default number of results per page for search operations.
     */
    private const DEFAULT_PER_PAGE = 10;

    /**
     * Default weights for hybrid search score combination.
     */
    private const DEFAULT_KEYWORD_WEIGHT = 0.5;
    private const DEFAULT_VECTOR_WEIGHT = 0.5;

    /**
     * Default number of candidates for vector search (k in vector_query).
     */
    private const DEFAULT_VECTOR_K = 100;

    private ?TypesenseClient $client = null;

    /**
     * Cached client config built from our flat config array.
     * @var array<string, mixed>|null
     */
    private ?array $resolvedClientConfig = null;

    /**
     * @param array<string, mixed> $config Flat Typesense configuration (from config/typesense.php).
     */
    public function __construct(
        private readonly array $config,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // Collection Management
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Create a new Typesense collection with the given schema.
     *
     * @param string                $collection Collection name (without prefix).
     * @param array<string, mixed>  $schema     Typesense collection schema.
     *
     * @throws \Typesense\Exceptions\TypesenseClientError
     */
    public function createCollection(string $collection, array $schema): void
    {
        $name = $this->resolveCollectionName($collection);

        $schema['name'] = $name;

        $this->client()->getCollections()->create($schema);

        Log::info('[TypesenseService] Collection created', [
            'collection' => $name,
        ]);
    }

    /**
     * Delete a Typesense collection.
     *
     * @param string $collection Collection name (without prefix).
     */
    public function deleteCollection(string $collection): void
    {
        $name = $this->resolveCollectionName($collection);

        try {
            $this->client()->getCollections()->{$name}->delete();

            Log::info('[TypesenseService] Collection deleted', [
                'collection' => $name,
            ]);
        } catch (ObjectNotFound) {
            Log::warning('[TypesenseService] Collection not found for deletion', [
                'collection' => $name,
            ]);
        }
    }

    /**
     * Check whether a collection exists.
     */
    public function collectionExists(string $collection): bool
    {
        $name = $this->resolveCollectionName($collection);

        try {
            $this->client()->getCollections()->{$name}->retrieve();

            return true;
        } catch (ObjectNotFound) {
            return false;
        }
    }

    /**
     * Retrieve the collection schema.
     *
     * @return array<string, mixed>|null
     */
    public function getCollectionSchema(string $collection): ?array
    {
        $name = $this->resolveCollectionName($collection);

        try {
            return $this->client()->getCollections()->{$name}->retrieve();
        } catch (ObjectNotFound) {
            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Document CRUD
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Insert or update a document in the given collection.
     *
     * Uses the Typesense upsert action (default: create if not exists, update if exists).
     *
     * @param string               $collection Collection name (without prefix).
     * @param array<string, mixed> $document   Document payload. Must include an 'id' field.
     */
    public function upsertDocument(string $collection, array $document): void
    {
        $name = $this->resolveCollectionName($collection);

        $this->client()->getCollections()->{$name}->documents->upsert($document);

        Log::debug('[TypesenseService] Document upserted', [
            'collection' => $name,
            'id'         => $document['id'] ?? '?',
        ]);
    }

    /**
     * Update an existing document. Fails if the document does not exist.
     *
     * @param string               $collection Collection name (without prefix).
     * @param array<string, mixed> $document   Document payload. Must include an 'id' field.
     *
     * @throws ObjectNotFound
     */
    public function updateDocument(string $collection, array $document): void
    {
        $name = $this->resolveCollectionName($collection);

        $this->client()->getCollections()->{$name}->documents->update($document);

        Log::debug('[TypesenseService] Document updated', [
            'collection' => $name,
            'id'         => $document['id'] ?? '?',
        ]);
    }

    /**
     * Delete a document by ID.
     *
     * @param string $collection Collection name (without prefix).
     * @param string $id         Document ID.
     */
    public function deleteDocument(string $collection, string $id): void
    {
        $name = $this->resolveCollectionName($collection);

        try {
            $this->client()->getCollections()->{$name}->documents->{$id}->delete();

            Log::debug('[TypesenseService] Document deleted', [
                'collection' => $name,
                'id'         => $id,
            ]);
        } catch (ObjectNotFound) {
            Log::warning('[TypesenseService] Document not found for deletion', [
                'collection' => $name,
                'id'         => $id,
            ]);
        }
    }

    /**
     * Retrieve a single document by ID.
     *
     * @return array<string, mixed>|null
     */
    public function getDocument(string $collection, string $id): ?array
    {
        $name = $this->resolveCollectionName($collection);

        try {
            return $this->client()->getCollections()->{$name}->documents->{$id}->retrieve();
        } catch (ObjectNotFound) {
            return null;
        }
    }

    /**
     * Upsert multiple documents in a single batch operation.
     *
     * @param string                  $collection Collection name (without prefix).
     * @param array<int, array<string, mixed>> $documents
     */
    public function upsertDocuments(string $collection, array $documents): void
    {
        $name = $this->resolveCollectionName($collection);

        if (empty($documents)) {
            return;
        }

        $importDocs = '';
        foreach ($documents as $doc) {
            $importDocs .= json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }

        $this->client()->getCollections()->{$name}->documents->import($importDocs, [
            'action' => 'upsert',
        ]);

        Log::debug('[TypesenseService] Batch upsert completed', [
            'collection' => $name,
            'count'      => count($documents),
        ]);
    }

    /**
     * Delete documents matching a filter expression.
     *
     * @param string $collection Collection name (without prefix).
     * @param string $filterBy   Typesense filter expression (e.g. 'workspace_id:=5').
     *
     * @return int Number of documents deleted.
     */
    public function deleteDocumentsByFilter(string $collection, string $filterBy): int
    {
        $name = $this->resolveCollectionName($collection);

        $result = $this->client()->getCollections()->{$name}->documents->delete([
            'filter_by' => $filterBy,
        ]);

        $deleted = (int) ($result['num_deleted'] ?? 0);

        Log::info('[TypesenseService] Documents deleted by filter', [
            'collection' => $name,
            'filter'     => $filterBy,
            'deleted'    => $deleted,
        ]);

        return $deleted;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Search
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Run a keyword (full-text) search against a collection.
     *
     * @param string               $collection Collection name (without prefix).
     * @param string               $query      Search query text.
     * @param array<string, mixed> $params     Optional search parameters:
     *                                          - query_by: string (fields to search)
     *                                          - filter_by: string
     *                                          - sort_by: string
     *                                          - per_page: int (default 10)
     *                                          - page: int (default 1)
     *                                          - include_fields: string
     *                                          - exclude_fields: string
     *
     * @return array{results: array<int, TypesenseSearchResult>, found: int, page: int}
     */
    public function search(string $collection, string $query, array $params = []): array
    {
        $name = $this->resolveCollectionName($collection);

        $searchParams = array_merge([
            'q'        => $query,
            'per_page' => self::DEFAULT_PER_PAGE,
        ], $params);

        try {
            $response = $this->client()->getCollections()->{$name}->documents->search($searchParams);

            $hits = $response['hits'] ?? [];
            $found = (int) ($response['found'] ?? 0);
            $page = (int) ($response['page'] ?? 1);

            $results = [];
            foreach ($hits as $hit) {
                $document = $hit['document'] ?? [];

                // text_match_info.best_field_score is already a 0.0–1.0 float.
                // Avoid text_match_info.score — it is a large compound integer
                // (encodes token positions, field weights, etc.) and has no
                // meaningful divisor that works across all queries.
                $textScore = 0.0;
                $tmi = $hit['text_match_info'] ?? [];
                if (isset($tmi['best_field_score'])) {
                    $textScore = min(max((float) $tmi['best_field_score'], 0.0), 1.0);
                } elseif (isset($tmi['tokens_matched'], $tmi['num_tokens_dropped'])) {
                    // Fallback: fraction of query tokens that matched
                    $totalTokens = ($tmi['tokens_matched'] + $tmi['num_tokens_dropped']);
                    $textScore = $totalTokens > 0
                        ? min($tmi['tokens_matched'] / $totalTokens, 1.0)
                        : 0.0;
                }

                $results[] = new TypesenseSearchResult(
                    document: $document,
                    keywordScore: $textScore,
                    vectorScore: 0.0,
                    finalScore: $textScore,
                    matchType: 'keyword',
                );
            }

            Log::debug('[TypesenseService] Keyword search completed', [
                'collection' => $name,
                'query'      => mb_substr($query, 0, 80),
                'found'      => $found,
            ]);

            return [
                'results' => $results,
                'found'   => $found,
                'page'    => $page,
            ];
        } catch (\Throwable $e) {
            Log::error('[TypesenseService] Keyword search failed', [
                'collection' => $name,
                'query'      => mb_substr($query, 0, 80),
                'error'      => $e->getMessage(),
                'exception'  => $e::class,
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            return [
                'results' => [],
                'found'   => 0,
                'page'    => 1,
            ];
        }
    }

    /**
     * Run a hybrid search combining keyword relevance and vector similarity.
     *
     * @param string               $collection Collection name (without prefix).
     * @param string               $query      Search query text (for keyword part).
     * @param array<float>         $vector     Embedding vector for semantic matching.
     * @param array<string, mixed> $params     Optional search parameters:
     *                                          - query_by: string
     *                                          - vector_field: string (default 'embedding')
     *                                          - filter_by: string
     *                                          - sort_by: string
     *                                          - per_page: int (default 10)
     *                                          - page: int (default 1)
     *                                          - keyword_weight: float (default 0.5)
     *                                          - vector_weight: float (default 0.5)
     *                                          - vector_k: int (default 100)
     *
     * @return array{results: array<int, TypesenseSearchResult>, found: int, page: int}
     */
    public function hybridSearch(string $collection, string $query, array $vector, array $params = []): array
    {
        $name = $this->resolveCollectionName($collection);

        $perPage = (int) ($params['per_page'] ?? self::DEFAULT_PER_PAGE);
        $vectorField = $params['vector_field'] ?? 'embedding';
        $vectorK = (int) ($params['vector_k'] ?? self::DEFAULT_VECTOR_K);
        $keywordWeight = (float) ($params['keyword_weight'] ?? self::DEFAULT_KEYWORD_WEIGHT);
        $vectorWeight = (float) ($params['vector_weight'] ?? self::DEFAULT_VECTOR_WEIGHT);

        // Build the vector_query string: "field_name:([vector], k:N, flat_search_cutoff:M)"
        $vectorQueryStr = $vectorField . ':([ ' . implode(',', $vector) . ' ], k: ' . $vectorK . ')';

        $searchRequest = [
            'collection'   => $name,
            'q'            => $query,
            'query_by'     => $params['query_by'] ?? 'searchable_text',
            'vector_query' => $vectorQueryStr,
            'per_page'     => $perPage,
        ];

        if (! empty($params['filter_by'])) {
            $searchRequest['filter_by'] = $params['filter_by'];
        }

        if (! empty($params['sort_by'])) {
            $searchRequest['sort_by'] = $params['sort_by'];
        }

        try {
            // Use multiSearch (HTTP POST) to avoid GET query string length limits (4000 chars) for 768-dim vectors
            $multiResponse = $this->client()->getMultiSearch()->perform([
                'searches' => [$searchRequest],
            ], []);

            $response = $multiResponse['results'][0] ?? [];

            $hits = $response['hits'] ?? [];
            $found = (int) ($response['found'] ?? 0);
            $page = (int) ($response['page'] ?? 1);

            $results = [];
            foreach ($hits as $hit) {
                $document = $hit['document'] ?? [];

                // text_match_info.best_field_score is already a 0.0–1.0 float.
                // Avoid text_match_info.score — it is a large compound integer
                // (encodes token positions, field weights, etc.) and has no
                // meaningful divisor that works across all queries.
                $textScore = 0.0;
                $tmi = $hit['text_match_info'] ?? [];
                if (isset($tmi['best_field_score'])) {
                    $textScore = min(max((float) $tmi['best_field_score'], 0.0), 1.0);
                } elseif (isset($tmi['tokens_matched'], $tmi['num_tokens_dropped'])) {
                    // Fallback: fraction of query tokens that matched
                    $totalTokens = ($tmi['tokens_matched'] + $tmi['num_tokens_dropped']);
                    $textScore = $totalTokens > 0
                        ? min($tmi['tokens_matched'] / $totalTokens, 1.0)
                        : 0.0;
                }

                // Vector score from vector_distance (0 = perfect match, higher = worse)
                $vecScore = 0.0;
                if (isset($hit['vector_distance'])) {
                    $vecDistance = (float) $hit['vector_distance'];
                    $vecScore = 1.0 - min(max($vecDistance, 0.0), 1.0);
                    $vecScore = max($vecScore, 0.0);
                }

                // Determine match type
                $matchType = 'keyword';
                if ($textScore > 0 && $vecScore > 0) {
                    $matchType = 'hybrid';
                } elseif ($vecScore > 0) {
                    $matchType = 'vector';
                }

                // Combined weighted score
                $finalScore = ($textScore * $keywordWeight) + ($vecScore * $vectorWeight);
                $finalScore = min(max($finalScore, 0.0), 1.0);

                $results[] = new TypesenseSearchResult(
                    document: $document,
                    keywordScore: $textScore,
                    vectorScore: $vecScore,
                    finalScore: $finalScore,
                    matchType: $matchType,
                );
            }

            // Sort by final score descending (Typesense may already do this, but enforce)
            usort($results, fn (TypesenseSearchResult $a, TypesenseSearchResult $b)
                => $b->finalScore <=> $a->finalScore);

            $results = array_slice($results, 0, $perPage);

            Log::debug('[TypesenseService] Hybrid search completed', [
                'collection' => $name,
                'query'      => mb_substr($query, 0, 80),
                'found'      => $found,
            ]);

            return [
                'results' => $results,
                'found'   => $found,
                'page'    => $page,
            ];
        } catch (\Throwable $e) {
            Log::error('[TypesenseService] Hybrid search failed', [
                'collection' => $name,
                'query'      => mb_substr($query, 0, 80),
                'error'      => $e->getMessage(),
                'exception'  => $e::class,
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            return [
                'results' => [],
                'found'   => 0,
                'page'    => 1,
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Get the underlying Typesense HTTP client (lazy-loaded).
     */
    public function client(): TypesenseClient
    {
        if ($this->client === null) {
            $this->client = new TypesenseClient($this->buildClientConfig());
        }

        return $this->client;
    }

    /**
     * Build the Typesense PHP client configuration array from our flat config.
     *
     * @return array<string, mixed>
     */
    private function buildClientConfig(): array
    {
        if ($this->resolvedClientConfig !== null) {
            return $this->resolvedClientConfig;
        }

        $node = [
            'host'     => $this->config('host', 'localhost'),
            'port'     => $this->config('port', '8108'),
            'protocol' => $this->config('protocol', 'http'),
        ];

        $path = $this->config('path', '');
        if ($path !== '' && $path !== null) {
            $node['path'] = $path;
        }

        $this->resolvedClientConfig = [
            'api_key'                       => $this->config('api_key', ''),
            'nodes'                         => [$node],
            'nearest_node'                  => $node,
            'connection_timeout_seconds'    => (int) $this->config('connection_timeout_seconds', 2),
            'healthcheck_interval_seconds'  => (int) $this->config('healthcheck_interval_seconds', 30),
            'num_retries'                   => (int) $this->config('num_retries', 3),
            'retry_interval_seconds'        => (int) $this->config('retry_interval_seconds', 1),
        ];

        return $this->resolvedClientConfig;
    }

    /**
     * Get a configuration value.
     */
    private function config(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Resolve a collection name by applying the configured prefix.
     */
    public function resolveCollectionName(string $collection): string
    {
        return $this->config('collection_prefix', '') . $collection;
    }

    /**
     * Health-check the Typesense cluster.
     *
     * @return array{ok: bool, latency_ms: float}
     */
    public function health(): array
    {
        $start = microtime(true);

        try {
            $health = $this->client()->getHealth()->retrieve();
            $latency = (microtime(true) - $start) * 1000;

            return [
                'ok'         => ($health['ok'] ?? false) === true,
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Throwable $e) {
            $latency = (microtime(true) - $start) * 1000;

            Log::error('[TypesenseService] Health check failed', [
                'error'      => $e->getMessage(),
                'latency_ms' => round($latency, 2),
            ]);

            return [
                'ok'         => false,
                'latency_ms' => round($latency, 2),
            ];
        }
    }
}
