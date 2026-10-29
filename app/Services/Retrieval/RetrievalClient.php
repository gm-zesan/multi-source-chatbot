<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\DocumentLifecycleStatus;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RetrievalClient
{
    private array $lastTelemetry = [];
    private bool $lastSearchFailed = false;
    private ?string $lastSearchError = null;

    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $apiKey = null,
        private readonly int $timeout = 15,
        private readonly int $defaultTopK = 5,
    ) {}

    /**
     * Get the last received telemetry data from the Python retrieval service.
     *
     * @return array<string, mixed>
     */
    public function getLastTelemetry(): array
    {
        return $this->lastTelemetry;
    }

    /**
     * Check if the last search failed against the primary retrieval service and used database fallback.
     */
    public function wasLastSearchFallback(): bool
    {
        return $this->lastSearchFailed;
    }

    /**
     * Get the error message from the last retrieval failure, if any.
     */
    public function getLastSearchError(): ?string
    {
        return $this->lastSearchError;
    }

    /**
     * Reset the last search failure state.
     */
    public function resetSearchState(): void
    {
        $this->lastSearchFailed = false;
        $this->lastSearchError = null;
        $this->lastTelemetry = [];
    }

    /**
     * Get the base URL for the Python retrieval service.
     */
    public function baseUrl(): string
    {
        if ($this->baseUrl !== null && trim($this->baseUrl) !== '') {
            return rtrim($this->baseUrl, '/');
        }

        $configured = (string) config('retrieval.base_url');
        if (trim($configured) !== '') {
            return rtrim($configured, '/');
        }

        return 'http://127.0.0.1:8001';
    }

    /**
     * Search relevant FAQs through the Python Retrieval Service.
     *
     * @return Collection<int, FAQSearchResult>
     */
    public function search(
        string $query,
        ?int $workspaceId = null,
        ?int $topK = null,
        ?string $contextualSignal = null,
    ): Collection
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return new Collection();
        }

        $topK = $topK ?? $this->defaultTopK;
        $url = "{$this->baseUrl()}/api/v1/search";
        $payload = [
            'query'             => $trimmed,
            'workspace_id'      => $workspaceId,
            'top_k'             => $topK,
            'contextual_signal' => $contextualSignal,
        ];

        try {
            $req = Http::timeout($this->timeout);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Validate required response payload structure
                if (!is_array($data) || !array_key_exists('results', $data) || !is_array($data['results'])) {
                    $this->lastTelemetry = [];
                    $this->lastSearchFailed = true;
                    $this->lastSearchError = 'Malformed retrieval response: missing or invalid "results" array in payload';
                    Log::warning('[RetrievalClient] Search returned malformed HTTP 200 response', [
                        'query'    => mb_substr($trimmed, 0, 80),
                        'raw_body' => mb_substr((string) $response->body(), 0, 200),
                    ]);

                    return $this->fallbackDatabaseSearch($trimmed, $workspaceId, $topK);
                }

                $results = $data['results'];
                $telemetry = is_array($data['telemetry'] ?? null) ? $data['telemetry'] : [];
                $this->lastTelemetry = $telemetry;
                $this->lastSearchFailed = false;
                $this->lastSearchError = null;

                if (! empty($telemetry)) {
                    Log::info('[Retrieval Observability]', [
                        'workspace_id'     => $workspaceId,
                        'query'            => mb_substr($trimmed, 0, 60),
                        'first_pass_score' => $telemetry['first_pass_score'] ?? null,
                        'expansion'        => $telemetry['expansion_triggered'] ?? false,
                        'expanded_query'   => $telemetry['expanded_query'] ?? null,
                        'final_score'      => $telemetry['final_score'] ?? null,
                        'latency_total_ms' => $telemetry['total_retrieval_latency_ms'] ?? null,
                        'returned_ids'     => $telemetry['returned_faq_ids'] ?? [],
                    ]);
                }

                return $this->mapResults($results, $topK, $workspaceId);
            }

            $this->lastTelemetry = [];
            $this->lastSearchFailed = true;
            $this->lastSearchError = "Search returned HTTP {$response->status()}";
            Log::warning('[RetrievalClient] Search returned non-200 status', [
                'status' => $response->status(),
                'query'  => mb_substr($trimmed, 0, 80),
            ]);
        } catch (\Throwable $e) {
            $this->lastTelemetry = [];
            $this->lastSearchFailed = true;
            $this->lastSearchError = $e->getMessage();
            Log::warning('[RetrievalClient] Python retrieval service unreachable, falling back to DB', [
                'query' => mb_substr($trimmed, 0, 80),
                'error' => $e->getMessage(),
            ]);
        }

        // Graceful fallback to database keyword search
        return $this->fallbackDatabaseSearch($trimmed, $workspaceId, $topK);
    }

    /**
     * Sync an FAQ record to the Python retrieval service for embedding & indexing.
     */
    public function syncFaq(FAQ $faq): bool
    {
        $url = "{$this->baseUrl()}/api/v1/faqs/sync";

        $payload = [
            'id'            => $faq->id,
            'workspace_id'  => $faq->workspace_id,
            'question'      => $faq->question,
            'answer'        => $faq->answer,
            'document_type' => $faq->document_type ?? 'faq',
            'priority'      => $faq->priority ?? 100,
            'is_active'     => (bool) $faq->is_active,
            'lexicon_terms' => $this->extractLexiconTerms($faq),
        ];

        try {
            $req = Http::timeout($this->timeout);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->post($url, $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[RetrievalClient] FAQ sync to Python service failed', [
                'faq_id' => $faq->id,
                'error'  => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extract unique lexical terms domain-neutrally from FAQ question, answer, and searchable_text.
     *
     * @param FAQ $faq
     * @return string[]
     */
    public function extractLexiconTerms(FAQ $faq): array
    {
        try {
            $rawParts = [
                $faq->question ?? '',
                $faq->answer ?? '',
                $faq->searchable_text ?? '',
            ];

            $text = implode(' ', array_filter($rawParts));
            if (trim($text) === '') {
                return [];
            }

            // Strip HTML tags and decode entities
            $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = mb_strtolower($text, 'UTF-8');

            // Split into tokens based on whitespace, Unicode punctuation, and symbols
            $rawTokens = preg_split('/[\s\p{P}\p{S}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            // Universal domain-neutral grammatical stop words (Bengali and English)
            $stopWords = [
                // English functional / grammatical words
                'the', 'and', 'for', 'are', 'what', 'how', 'you', 'can', 'with', 'from',
                'this', 'that', 'your', 'our', 'have', 'does', 'doing', 'where', 'when',
                'will', 'was', 'were', 'about', 'into', 'all', 'any', 'not', 'but', 'out',
                'has', 'had', 'been', 'would', 'could', 'should', 'more', 'some', 'such',
                'than', 'them', 'then', 'there', 'they', 'their', 'only', 'also', 'over',
                'just', 'which', 'who', 'whom', 'whose', 'why', 'his', 'her', 'its', 'my',
                'to', 'or', 'it', 'in', 'on', 'at', 'by', 'an', 'as', 'is', 'if', 'of',
                'up', 'so', 'no', 'we', 'he', 'me', 'us', 'am', 'do', 'go',

                // Bengali functional / grammatical particles & pronouns
                'কি', 'কী', 'কিভাবে', 'কোন', 'থেকে', 'হলে', 'করব', 'করতে', 'পারি', 'হবে',
                'এই', 'সেই', 'এবং', 'বা', 'না', 'একটি', 'করা', 'আছে', 'ছিল', 'জন্য',
                'মতো', 'আর', 'তা', 'তো', 'যে', 'সে', 'ও', 'যা', 'কে', 'কীভাবে',
            ];
            $stopWordsFlip = array_flip($stopWords);

            $terms = [];
            foreach ($rawTokens as $token) {
                $token = trim($token);
                // Retain tokens with character length >= 2 and exclude stop words
                if (mb_strlen($token, 'UTF-8') >= 2 && !isset($stopWordsFlip[$token])) {
                    $terms[$token] = true;
                    if (count($terms) >= 50) {
                        break;
                    }
                }
            }

            return array_values(array_map('strval', array_keys($terms)));
        } catch (\Throwable $e) {
            Log::warning('[RetrievalClient] Failed to extract lexicon terms from FAQ', [
                'faq_id' => $faq->id ?? null,
                'error'  => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sync a knowledge document chunk to the Python retrieval service for embedding & indexing.
     */
    public function syncDocumentChunk(
        string $id,
        int $workspaceId,
        string $title,
        string $content,
        string $documentType = 'knowledge_document',
        int $priority = 100,
        bool $isActive = true,
        array $lexiconTerms = [],
        ?string $sourceId = null,
    ): bool {
        $url = "{$this->baseUrl()}/api/v1/faqs/sync";

        $payload = [
            'id'            => $id,
            'workspace_id'  => $workspaceId,
            'question'      => $title,
            'answer'        => $content,
            'document_type' => $documentType,
            'priority'      => $priority,
            'is_active'     => $isActive,
            'lexicon_terms' => $lexiconTerms,
        ];

        try {
            $req = Http::timeout($this->timeout);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->post($url, $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[RetrievalClient] Document chunk sync to Python service failed', [
                'chunk_id'     => $id,
                'workspace_id' => $workspaceId,
                'error'        => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete an FAQ document from the Python retrieval service index.
     */
    public function deleteFaq(string|int $faqId, ?int $workspaceId = null): bool
    {
        $url = "{$this->baseUrl()}/api/v1/faqs/{$faqId}";
        if ($workspaceId !== null) {
            $url .= "?workspace_id={$workspaceId}";
        }

        try {
            $req = Http::timeout($this->timeout);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->delete($url);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('[RetrievalClient] FAQ deletion in Python service failed', [
                'faq_id' => $faqId,
                'error'  => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Safe stub for reloading workspace lexicon to prevent runtime failures.
     *
     * @param int $workspaceId
     * @return array{ok: bool, workspace_id: int}
     */
    public function reloadLexicon(int $workspaceId = 0): array
    {
        return [
            'ok'           => true,
            'workspace_id' => $workspaceId,
        ];
    }

    /**
     * Check health of the Python retrieval service.
     *
     * @return array{ok: bool, latency_ms: float, error: ?string}
     */
    public function health(): array
    {
        $start = microtime(true);
        $url = "{$this->baseUrl()}/health";

        try {
            $req = Http::timeout(5);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->get($url);

            if (! $response->successful()) {
                $altResponse = $req->get("{$this->baseUrl()}/api/v1/health");
                if ($altResponse->successful()) {
                    $response = $altResponse;
                }
            }

            $latency = round((microtime(true) - $start) * 1000, 2);

            if (! $response->successful()) {
                Log::warning('[RetrievalClient] Health check non-200', [
                    'url'    => $url,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return [
                'ok'         => $response->successful(),
                'latency_ms' => $latency,
                'error'      => $response->successful() ? null : "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 2);
            Log::warning('[RetrievalClient] Health check exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok'         => false,
                'latency_ms' => $latency,
                'error'      => $e->getMessage(),
            ];
        }
    }

    /**
     * Map structured Python retrieval results to FAQSearchResult objects.
     *
     * @param array<int, array<string, mixed>> $results
     * @return Collection<int, FAQSearchResult>
     */
    /**
     * Map structured Python retrieval results to FAQSearchResult objects.
     *
     * @param array<int, array<string, mixed>> $results
     * @return Collection<int, FAQSearchResult>
     */
    private function mapResults(array $results, int $topK, ?int $workspaceId = null): Collection
    {
        if (empty($results)) {
            return new Collection();
        }

        $docIds = array_values(array_filter(array_map(fn ($r) => $r['id'] ?? null, $results)));
        $faqs = ! empty($docIds)
            ? FAQ::whereIn('id', $docIds)->get()->keyBy('id')
            : new Collection();

        $mapped = [];
        foreach ($results as $item) {
            $id = $item['id'] ?? null;
            $faq = $id ? ($faqs[$id] ?? null) : null;

            // If it is a knowledge document chunk, verify that the parent document is active and belongs to this workspace
            if (is_string($id) && str_starts_with($id, 'doc_')) {
                // Chunk ID formats:
                // 1) Legacy: doc_{docUuid}_{chunkIndex}
                // 2) Generation-scoped: doc_{docUuid}_{generationId}_{chunkIndex}
                $parts = explode('_', $id);
                $docUuid = $parts[1] ?? null;
                $chunkGen = count($parts) >= 4 ? $parts[2] : null;

                if ($docUuid) {
                    $doc = KnowledgeDocument::find($docUuid);
                    if ($doc === null || ! $doc->is_active || $doc->lifecycle_status !== DocumentLifecycleStatus::ACTIVE || $doc->trashed()) {
                        continue;
                    }
                    if ($workspaceId !== null && (int) $doc->workspace_id !== (int) $workspaceId) {
                        continue;
                    }
                    // Generation fencing: if chunk is generation-scoped, ensure it matches the currently active generation
                    if ($chunkGen !== null && ! empty($doc->active_generation_id) && $chunkGen !== (string) $doc->active_generation_id) {
                        continue;
                    }
                }
            }

            if ($faq === null && isset($item['question'], $item['answer'])) {
                // If model not in local DB (e.g. distributed store or document chunk), build a transient instance
                $itemWorkspaceId = isset($item['workspace_id']) ? (int) $item['workspace_id'] : $workspaceId;
                if ($workspaceId !== null && $itemWorkspaceId !== (int) $workspaceId) {
                    continue; // Strict tenant boundary guard
                }

                $faq = new FAQ([
                    'id'            => $id ?? 0,
                    'question'      => (string) $item['question'],
                    'answer'        => (string) $item['answer'],
                    'document_type' => (string) ($item['document_type'] ?? 'knowledge_document'),
                    'workspace_id'  => $itemWorkspaceId,
                    'is_active'     => isset($item['is_active']) ? (bool) $item['is_active'] : true,
                ]);
                $faq->exists = true;
            }

            if ($faq !== null && (! $faq->is_active || $faq->trashed())) {
                continue;
            }

            if ($faq !== null && $workspaceId !== null && (int) $faq->workspace_id !== (int) $workspaceId) {
                continue;
            }

            if ($faq !== null) {
                $score = (float) ($item['score'] ?? 0.85);
                $matchType = (string) ($item['match_type'] ?? 'hybrid');
                $isPureKeyword = in_array($matchType, ['keyword', 'bm25', 'keyword_fallback'], true);

                // Preserve explicit sub-scores from Python service contract.
                // If sub-scores are not provided in the payload, explicitly leave as null
                // rather than assuming semanticScore = finalScore or 0.0.
                $semanticScore = isset($item['semantic_score'])
                    ? (float) $item['semantic_score']
                    : ($isPureKeyword ? 0.0 : ($matchType === 'vector' ? $score : null));

                $keywordScore = isset($item['keyword_score'])
                    ? (float) $item['keyword_score']
                    : ($matchType === 'vector' ? 0.0 : ($isPureKeyword ? $score : null));

                $mapped[] = new FAQSearchResult(
                    faq: $faq,
                    keywordScore: $keywordScore,
                    semanticScore: $semanticScore,
                    finalScore: $score,
                    matchType: $matchType,
                );
            }
        }

        return new Collection(array_slice($mapped, 0, $topK));
    }

    /**
     * Database fallback when Python retrieval service is unavailable.
     * Enforces strict workspace isolation, MySQL FULLTEXT optimization where available,
     * graceful parameterized token fallback, and calibrated relevance scoring.
     *
     * @return Collection<int, FAQSearchResult>
     */
    private function fallbackDatabaseSearch(string $query, ?int $workspaceId, int $topK): Collection
    {
        if ($workspaceId === null || $workspaceId <= 0) {
            Log::warning('[RetrievalClient] Database fallback aborted: missing or invalid workspace_id for tenant isolation', [
                'workspace_id' => $workspaceId,
                'query'        => mb_substr($query, 0, 80),
            ]);

            return new Collection();
        }

        $cleanQuery = mb_strtolower(trim($query));
        if ($cleanQuery === '') {
            return new Collection();
        }

        // Tokenize query into searchable keywords (length >= 2)
        $queryWords = array_values(array_unique(array_filter(
            preg_split('/\s+/u', $cleanQuery),
            fn ($w) => mb_strlen(trim($w)) >= 2
        )));

        if (empty($queryWords)) {
            return new Collection();
        }

        // 1. Check database driver for MySQL FULLTEXT support
        $isMysql = false;
        try {
            $isMysql = DB::connection()->getDriverName() === 'mysql';
        } catch (\Throwable) {
            $isMysql = false;
        }

        $faqs = new Collection();

        // Attempt MySQL FullText Boolean Search if on MySQL driver
        if ($isMysql) {
            try {
                // Strip boolean operators that trigger MySQL syntax errors
                $sanitized = preg_replace('/[+\-><()~*\"@]/u', ' ', $cleanQuery);
                $ftTokens = array_values(array_filter(explode(' ', (string) $sanitized), fn ($t) => mb_strlen(trim($t)) >= 2));
                if (! empty($ftTokens)) {
                    $booleanExpr = implode(' ', array_map(fn ($t) => '+' . $t . '*', $ftTokens));
                    $faqs = FAQ::where('is_active', true)
                        ->where('workspace_id', $workspaceId)
                        ->whereRaw('MATCH(question, searchable_text) AGAINST(? IN BOOLEAN MODE)', [$booleanExpr])
                        ->limit($topK * 2)
                        ->get();
                }
            } catch (\Throwable $e) {
                Log::debug('[RetrievalClient] MySQL FullText query unviable, falling back to parameterized search', [
                    'error' => $e->getMessage(),
                ]);
                $faqs = new Collection();
            }
        }

        // If MySQL FullText produced 0 hits (e.g. stopword, short token, or SQLite driver), use parameterized LIKE
        if ($faqs->isEmpty()) {
            $dbQuery = FAQ::where('is_active', true)
                ->where('workspace_id', $workspaceId);

            $dbQuery->where(function ($q) use ($queryWords) {
                foreach ($queryWords as $word) {
                    $q->orWhere('question', 'LIKE', '%' . $word . '%')
                      ->orWhere('searchable_text', 'LIKE', '%' . $word . '%')
                      ->orWhere('answer', 'LIKE', '%' . $word . '%');
                }
            });

            $faqs = $dbQuery->limit($topK * 2)->get();
        }

        $mapped = [];
        foreach ($faqs as $faq) {
            $score = $this->calculateFallbackRelevance(
                titleOrQuestion: $faq->question,
                contentOrAnswer: $faq->answer,
                searchableText: $faq->searchable_text ?? '',
                queryWords: $queryWords,
                cleanQuery: $cleanQuery,
            );

            if ($score > 0.0) {
                $mapped[] = new FAQSearchResult(
                    faq: $faq,
                    keywordScore: $score,
                    semanticScore: 0.0,
                    finalScore: $score,
                    matchType: 'keyword_fallback',
                );
            }
        }

        // 2. Also search active knowledge documents in database fallback mode
        $docQuery = KnowledgeDocument::where('is_active', true)
            ->where('lifecycle_status', DocumentLifecycleStatus::ACTIVE)
            ->where('workspace_id', $workspaceId);

        $docQuery->where(function ($q) use ($queryWords) {
            foreach ($queryWords as $word) {
                $q->orWhere('title', 'LIKE', '%' . $word . '%')
                  ->orWhere('metadata', 'LIKE', '%' . $word . '%');
            }
        });

        $docs = $docQuery->limit($topK * 2)->get();
        foreach ($docs as $doc) {
            $rawText = $doc->metadata['raw_text'] ?? null;
            if (empty($rawText) && Storage::disk('local')->exists($doc->file_path)) {
                $rawText = (string) Storage::disk('local')->get($doc->file_path);
            }

            if (! empty($rawText)) {
                $score = $this->calculateFallbackRelevance(
                    titleOrQuestion: $doc->title,
                    contentOrAnswer: $rawText,
                    searchableText: '',
                    queryWords: $queryWords,
                    cleanQuery: $cleanQuery,
                );

                if ($score > 0.0) {
                    $transientFaq = new FAQ([
                        'id'            => $doc->id,
                        'question'      => $doc->title,
                        'answer'        => $rawText,
                        'document_type' => 'knowledge_document',
                        'workspace_id'  => $doc->workspace_id,
                        'is_active'     => true,
                    ]);
                    $transientFaq->exists = true;

                    $mapped[] = new FAQSearchResult(
                        faq: $transientFaq,
                        keywordScore: $score,
                        semanticScore: 0.0,
                        finalScore: $score,
                        matchType: 'keyword_fallback',
                    );
                }
            }
        }

        // 3. Sort hits descending by calibrated relevance score
        usort($mapped, fn (FAQSearchResult $a, FAQSearchResult $b) => $b->finalScore <=> $a->finalScore);

        return new Collection(array_slice($mapped, 0, $topK));
    }

    /**
     * Compute calibrated lexical relevance score in [0.0, 0.95].
     *
     * @param string   $titleOrQuestion
     * @param string   $contentOrAnswer
     * @param string   $searchableText
     * @param string[] $queryWords
     * @param string   $cleanQuery
     * @return float
     */
    private function calculateFallbackRelevance(
        string $titleOrQuestion,
        string $contentOrAnswer,
        string $searchableText,
        array $queryWords,
        string $cleanQuery,
    ): float {
        if (empty($queryWords)) {
            return 0.0;
        }

        $title = mb_strtolower(trim($titleOrQuestion));
        $content = mb_strtolower(trim($contentOrAnswer));
        $searchable = mb_strtolower(trim($searchableText));

        // 1. Exact title/question match (ignoring trailing punctuation)
        $normalizedTitle = trim(preg_replace('/[?!.,;:]+$/u', '', $title));
        $normalizedQuery = trim(preg_replace('/[?!.,;:]+$/u', '', $cleanQuery));
        if ($title === $cleanQuery || ($normalizedTitle !== '' && $normalizedTitle === $normalizedQuery)) {
            return 0.95;
        }

        // 2. Contiguous phrase containment in title
        if (str_contains($title, $cleanQuery)) {
            return 0.90;
        }

        // 3. Word-level match coverage in title
        $totalWords = count($queryWords);
        $matchedInTitle = 0;
        foreach ($queryWords as $word) {
            if (str_contains($title, $word)) {
                $matchedInTitle++;
            }
        }

        $titleRatio = $matchedInTitle / $totalWords;
        if ($titleRatio === 1.0) {
            // All query words match in the title/question
            return round(min(0.88, 0.82 + 0.02 * $totalWords), 4);
        }

        if ($titleRatio >= 0.5) {
            return round(0.60 + 0.20 * $titleRatio, 4);
        }

        // 4. Combined document text coverage (question + answer + searchable_text)
        $matchedInDoc = 0;
        foreach ($queryWords as $word) {
            if (str_contains($title, $word) || str_contains($searchable, $word) || str_contains($content, $word)) {
                $matchedInDoc++;
            }
        }

        $docRatio = $matchedInDoc / $totalWords;
        if ($docRatio === 1.0) {
            return round(0.65 + 0.10 * $titleRatio, 4);
        }

        if ($docRatio > 0.0) {
            return round(0.40 + 0.25 * $docRatio, 4);
        }

        return 0.0;
    }
}
