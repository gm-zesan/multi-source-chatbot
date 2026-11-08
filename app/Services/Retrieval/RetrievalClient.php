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

    /**
     * Domain-neutral and conversational stopwords to prevent bag-of-words score inflation.
     */
    private const FALLBACK_STOPWORDS = [
        'what', 'whats', "what's", 'how', 'does', 'can', 'could', 'would', 'will', 'is', 'are', 'am', 'was', 'were',
        'the', 'a', 'an', 'of', 'in', 'for', 'to', 'at', 'by', 'from', 'on', 'with', 'about', 'tell', 'me', 'you',
        'your', 'do', 'have', 'has', 'had', 'any', 'there', 'this', 'that', 'please', 'i', 'my', 'we', 'our',
        'ami', 'tumi', 'apni', 'she', 'tara', 'ki', 'koto', 'kobe', 'kemne', 'kivabe', 'asche', 'ashbe', 'pabo', 'pawa', 'jabe', 'hobe', 'ache', 'bhai', 'mama', 'vai',
        'apnader', 'amader', 'amar', 'shob', 'kono', 'ekto', 'ektu', 'ashole', 'jante', 'chaisilam', 'je', 'okhane', 'ar', 'eta', 'otar', 'ta', 'te', 'kore', 'korle', 'korben',
        'lagbe', 'katben', 'bolben', 'diben', 'deben', 'nite', 'pab', 'jodi', 'tahole', 'option', 'shomoy', 'beshi', 'lage', 'pochondo', 'na',
        'kemon', 'achen',
        'কি', 'কী', 'কে', 'কেন', 'কখন', 'কোথায়', 'কীভাবে', 'কিভাবে', 'হবে', 'হবেকি', 'আছে', 'আছেকি',
        'করা', 'যায়', 'যায়কি', 'পারি', 'পারব', 'পারা', 'জন্য', 'এর', 'তে', 'এ', 'দেওয়া', 'দেয়া',
        'দেবেন', 'দিবেন', 'নাকি', 'বলুন', 'আমাকে', 'একটু', 'ভাই', 'মামা', 'আমাদের', 'আপনার', 'আপনাদের', 'আমার', 'আমি', 'বা', 'বাকি', 'পাওয়া', 'গেলে', 'হলে', 'হওয়া',
        'লাগবে', 'কাটবেন', 'বলবেন', 'নিতে', 'পাব', 'পাবো', 'পৌঁছাইতে', 'পৌঁছাতে', 'করতে', 'অপেক্ষা', 'স্পষ্ট', 'লেখা', 'বলছেন', 'কোনো',
    ];

    /**
     * Domain-neutral cross-lingual vocabulary & synonyms for search candidate expansion.
     * Preserves language translation, transliteration, and universal commerce terms
     * without domain/product-specific hardcoding.
     */
    private const GENERIC_SYNONYMS = [
        'pure' => ['পিওর', 'খাঁটি'],
        'পিওর' => ['pure', 'খাঁটি'],
        'খাঁটি' => ['pure', 'পিওর'],
        'জরুরি' => ['দ্রুত', 'urgent', 'express'],
        'দ্রুত' => ['জরুরি', 'urgent', 'express'],
        'urgent' => ['জরুরি', 'দ্রুত', 'express'],
        'emergency' => ['জরুরি', 'urgent', 'express'],
        'express' => ['জরুরি', 'দ্রুত', 'urgent', 'fast'],
        'দাম' => ['মূল্য', 'price', 'rate', 'টাকা'],
        'মূল্য' => ['দাম', 'price', 'rate', 'টাকা'],
        'price' => ['দাম', 'মূল্য', 'টাকা'],
        'খরচ' => ['ফি', 'fee', 'charge', 'cost'],
        'charge' => ['ফি', 'খরচ', 'cost'],
        'fee' => ['ফি', 'খরচ', 'charge'],
    ];

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
     * Dynamically compute the bounded timeout in seconds.
     * Respects active AgentContext monotonic SLA budget if available.
     */
    public function effectiveTimeout(?int $default = null): float
    {
        $base = (float) ($default ?? $this->timeout);

        if (\App\AI\DTOs\AgentContext::hasCurrent()) {
            $remaining = \App\AI\DTOs\AgentContext::current()->getRemainingTimeout();
            return max(0.1, min($base, $remaining));
        }

        return $base;
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
            $timeout = $this->effectiveTimeout();
            $connectTimeout = min($timeout, 2.0);
            $req = Http::timeout($timeout)->connectTimeout($connectTimeout);
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
                        'workspace_id' => $workspaceId,
                        'query_sha256' => hash('sha256', $trimmed),
                    ]);

                    return $this->fallbackDatabaseSearch($trimmed, $workspaceId, $topK, $contextualSignal);
                }

                $results = $data['results'];
                $telemetry = is_array($data['telemetry'] ?? null) ? $data['telemetry'] : [];
                $this->lastTelemetry = $telemetry;
                $this->lastSearchFailed = false;
                $this->lastSearchError = null;

                if (! empty($telemetry)) {
                    Log::info('[Retrieval Observability]', [
                        'workspace_id'     => $workspaceId,
                        'query_sha256'     => hash('sha256', $trimmed),
                        'first_pass_score' => $telemetry['first_pass_score'] ?? null,
                        'expansion'        => $telemetry['expansion_triggered'] ?? false,
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
                'workspace_id' => $workspaceId,
                'status'       => $response->status(),
                'query_sha256' => hash('sha256', $trimmed),
            ]);
        } catch (\Throwable $e) {
            $this->lastTelemetry = [];
            $this->lastSearchFailed = true;
            $this->lastSearchError = $e->getMessage();
            Log::warning('[RetrievalClient] Python retrieval service unreachable, falling back to DB', [
                'workspace_id' => $workspaceId,
                'query_sha256' => hash('sha256', $trimmed),
                'error'        => $e->getMessage(),
            ]);
        }

        // Graceful fallback to database keyword search
        return $this->fallbackDatabaseSearch($trimmed, $workspaceId, $topK, $contextualSignal);
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
    private function fallbackDatabaseSearch(string $query, ?int $workspaceId, int $topK, ?string $contextualSignal = null): Collection
    {
        if ($workspaceId === null || $workspaceId <= 0) {
            Log::warning('[RetrievalClient] Database fallback aborted: missing or invalid workspace_id for tenant isolation', [
                'workspace_id' => $workspaceId,
                'query'        => mb_substr($query, 0, 80),
            ]);

            return new Collection();
        }

        $cleanQuery = mb_strtolower(trim($query));
        $cleanContext = ($contextualSignal !== null && trim($contextualSignal) !== '' && trim($contextualSignal) !== trim($query))
            ? mb_strtolower(trim($contextualSignal))
            : null;

        if (($cleanQuery === '' || mb_strlen($cleanQuery) < 2) && empty($cleanContext)) {
            return new Collection();
        }

        // Detect negated attribute expressions (e.g., "সুতি বাদে", "কটন ছাড়া", "synthetic pochondo na")
        $negatedWords = [];
        if (preg_match_all('/([^\p{L}\p{M}\p{N}\-_]+|^)([\p{L}\p{M}\p{N}\-_]+)\s+(?:pochondo\s+na|pochondo\s*nei|পছন্দ\s*না|পছন্দ\s*নেই|বাদে|ছাড়া|chara|bade|without|except)/ui', $cleanQuery, $negMatches)) {
            foreach ($negMatches[2] as $nw) {
                $negatedWords[] = mb_strtolower($nw);
            }
        }

        // Tokenize query with punctuation normalization (length >= 2 or alphanumeric single token, excluding negated attributes)
        $normalizedQuery = preg_replace('/[^\p{L}\p{M}\p{N}\-_]+/u', ' ', $cleanQuery);
        $queryWords = array_values(array_unique(array_filter(
            preg_split('/\s+/u', $normalizedQuery),
            fn ($w) => (mb_strlen(trim($w)) >= 2 || preg_match('/^[\p{L}\p{N}]$/u', trim($w))) && !in_array($w, $negatedWords, true)
        )));

        // Tokenize contextual signal if present
        $normalizedCtx = $cleanContext !== null ? preg_replace('/[^\p{L}\p{M}\p{N}\-_]+/u', ' ', $cleanContext) : null;
        $contextWords = $normalizedCtx !== null
            ? array_values(array_unique(array_filter(
                preg_split('/\s+/u', $normalizedCtx),
                fn ($w) => (mb_strlen(trim($w)) >= 2 || preg_match('/^[\p{L}\p{N}]$/u', trim($w))) && !in_array($w, $negatedWords, true)
            )))
            : [];

        // Combined unique words for database candidate filtering
        $allWords = array_values(array_unique(array_merge($queryWords, $contextWords)));
        if (empty($allWords)) {
            return new Collection();
        }

        // Substantive words vs stopwords
        $substantiveWords = array_values(array_filter($allWords, fn ($w) => !in_array($w, self::FALLBACK_STOPWORDS, true)));
        $searchWords = !empty($substantiveWords) ? $substantiveWords : $allWords;

        // Expand candidate filtering keywords using generic morphological stemming and language synonyms
        $expandedSearchWords = $searchWords;
        $stemSuffixes = ['গুলোতে', 'গুলোর', 'টিতে', 'টাতে', 'টার', 'টির', 'গুলো', 'গুলা', 'য়ের', 'ের', 'তে', 'এ', 'র', 'কে', 'টা', 'টি'];
        foreach ($searchWords as $w) {
            foreach ($stemSuffixes as $suf) {
                if (str_ends_with($w, $suf)) {
                    $stem = mb_substr($w, 0, mb_strlen($w) - mb_strlen($suf));
                    if (mb_strlen($stem) >= 3) {
                        $expandedSearchWords[] = $stem;
                    }
                }
            }
        }
        foreach ($expandedSearchWords as $w) {
            if (isset(self::GENERIC_SYNONYMS[$w])) {
                foreach (self::GENERIC_SYNONYMS[$w] as $syn) {
                    $expandedSearchWords[] = $syn;
                }
            }
        }
        $expandedSearchWords = array_values(array_unique($expandedSearchWords));

        // 1. Check database driver for MySQL FULLTEXT support
        $isMysql = false;
        try {
            $isMysql = (new FAQ)->getConnection()->getDriverName() === 'mysql';
        } catch (\Throwable) {
            $isMysql = false;
        }

        $faqs = new Collection();

        // Attempt MySQL FullText Boolean Search if on MySQL driver
        if ($isMysql) {
            try {
                // Strip boolean operators and punctuation that break MySQL boolean mode or token boundary parsing
                $ftSource = $cleanContext ?? $cleanQuery;
                $sanitized = preg_replace('/[+\-><()~*\"@:;|&!?,.\/\\\]/u', ' ', (string) $ftSource);
                $ftTokens = array_values(array_filter(
                    preg_split('/\s+/u', (string) $sanitized),
                    fn ($t) => mb_strlen(trim($t)) >= 2
                ));
                if (! empty($ftTokens)) {
                    // Use wildcard tokens without mandatory '+' conjunction to prevent built-in InnoDB
                    // stopwords and short tokens (< innodb_ft_min_token_size) from causing 0-hit rejection.
                    $booleanExpr = implode(' ', array_map(fn ($t) => $t . '*', $ftTokens));
                    $faqs = FAQ::where('is_active', true)
                        ->where('workspace_id', $workspaceId)
                        ->whereRaw('MATCH(question, searchable_text) AGAINST(? IN BOOLEAN MODE)', [$booleanExpr])
                        ->orderByRaw('MATCH(question, searchable_text) AGAINST(? IN BOOLEAN MODE) DESC', [$booleanExpr])
                        ->limit(max(100, $topK * 10))
                        ->get();
                }
            } catch (\Throwable $e) {
                Log::debug('[RetrievalClient] MySQL FullText query unviable, falling back to parameterized search', [
                    'error' => $e->getMessage(),
                ]);
                $faqs = new Collection();
            }
        }

        // If candidate filtering with expanded search words produced 0 hits, fall back to all words
        if ($faqs->isEmpty()) {
            $dbQuery = FAQ::where('is_active', true)
                ->where('workspace_id', $workspaceId);

            $dbQuery->where(function ($q) use ($expandedSearchWords) {
                foreach ($expandedSearchWords as $word) {
                    $q->orWhere('question', 'LIKE', '%' . $word . '%')
                      ->orWhere('searchable_text', 'LIKE', '%' . $word . '%')
                      ->orWhere('answer', 'LIKE', '%' . $word . '%');
                }
            });

            $faqs = $dbQuery->limit(max(100, $topK * 10))->get();
        }

        $mapped = [];
        foreach ($faqs as $faq) {
            $rawScore = $this->calculateFallbackRelevance(
                titleOrQuestion: $faq->question,
                contentOrAnswer: $faq->answer,
                searchableText: $faq->searchable_text ?? '',
                queryWords: $queryWords,
                cleanQuery: $cleanQuery,
                negatedWords: $negatedWords,
            );

            $ctxScore = !empty($contextWords) && $cleanContext !== null
                ? $this->calculateFallbackRelevance(
                    titleOrQuestion: $faq->question,
                    contentOrAnswer: $faq->answer,
                    searchableText: $faq->searchable_text ?? '',
                    queryWords: $contextWords,
                    cleanQuery: $cleanContext,
                    negatedWords: $negatedWords,
                )
                : 0.0;

            $score = max($rawScore, $ctxScore);

            // If an explicit SKU is matched in query or context, ensure precision boost
            if (!empty($faq->sku)) {
                $skuLower = mb_strtolower($faq->sku);
                if (str_contains($cleanQuery, $skuLower) || ($cleanContext !== null && str_contains($cleanContext, $skuLower))) {
                    $score = round(min(0.95, $score + 0.05), 4);
                }
            }

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

        $docQuery->where(function ($q) use ($expandedSearchWords) {
            foreach ($expandedSearchWords as $word) {
                $q->orWhere('title', 'LIKE', '%' . $word . '%')
                  ->orWhere('metadata', 'LIKE', '%' . $word . '%');
            }
        });

        $docs = $docQuery->limit(max(50, $topK * 5))->get();

        if ($docs->isEmpty()) {
            $docQuery = KnowledgeDocument::where('is_active', true)
                ->where('lifecycle_status', DocumentLifecycleStatus::ACTIVE)
                ->where('workspace_id', $workspaceId);

            $docQuery->where(function ($q) use ($allWords) {
                foreach ($allWords as $word) {
                    $q->orWhere('title', 'LIKE', '%' . $word . '%')
                      ->orWhere('metadata', 'LIKE', '%' . $word . '%');
                }
            });

            $docs = $docQuery->limit(max(50, $topK * 5))->get();
        }
        foreach ($docs as $doc) {
            $rawText = $doc->metadata['raw_text'] ?? null;
            if (empty($rawText)) {
                if (Storage::disk('local')->exists($doc->file_path)) {
                    $rawText = (string) Storage::disk('local')->get($doc->file_path);
                } elseif (file_exists($doc->file_path)) {
                    $rawText = (string) file_get_contents($doc->file_path);
                }
            }

            if (! empty($rawText)) {
                $rawScore = $this->calculateFallbackRelevance(
                    titleOrQuestion: $doc->title,
                    contentOrAnswer: $rawText,
                    searchableText: '',
                    queryWords: $queryWords,
                    cleanQuery: $cleanQuery,
                    negatedWords: $negatedWords,
                );

                $ctxScore = !empty($contextWords) && $cleanContext !== null
                    ? $this->calculateFallbackRelevance(
                        titleOrQuestion: $doc->title,
                        contentOrAnswer: $rawText,
                        searchableText: '',
                        queryWords: $contextWords,
                        cleanQuery: $cleanContext,
                        negatedWords: $negatedWords,
                    )
                    : 0.0;

                $score = max($rawScore, $ctxScore);

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
     * @param string[] $negatedWords
     * @return float
     */
    private function calculateFallbackRelevance(
        string $titleOrQuestion,
        string $contentOrAnswer,
        string $searchableText,
        array $queryWords,
        string $cleanQuery,
        array $negatedWords = [],
    ): float {
        if (empty($queryWords)) {
            return 0.0;
        }

        $title = mb_strtolower(trim($titleOrQuestion));
        $content = mb_strtolower(trim($contentOrAnswer));
        $searchable = mb_strtolower(trim($searchableText));

        // Penalty if candidate features negated attribute
        if (!empty($negatedWords)) {
            foreach ($negatedWords as $neg) {
                if (str_contains($title, $neg) || str_contains($content, $neg)) {
                    return 0.10;
                }
            }
        }

        // 1. Exact title/question match (ignoring trailing punctuation)
        $normalizedTitle = trim(preg_replace('/[?!.,;:]+$/u', '', $title));
        $normalizedQuery = trim(preg_replace('/[?!.,;:]+$/u', '', $cleanQuery));
        if ($title === $cleanQuery || ($normalizedTitle !== '' && $normalizedTitle === $normalizedQuery)) {
            return 0.95;
        }

        // 2. Contiguous phrase containment in title
        if (str_contains($title, $cleanQuery) || ($normalizedQuery !== '' && str_contains($title, $normalizedQuery))) {
            return 0.90;
        }

        // Substantive tokens
        $subWords = array_values(array_filter($queryWords, fn ($w) => !in_array($w, self::FALLBACK_STOPWORDS, true)));
        $effectiveSubWords = !empty($subWords) ? $subWords : $queryWords;

        // Multi-word phrase matching in title
        $hasTrigramInTitle = false;
        $hasBigramInTitle = false;
        if (count($effectiveSubWords) >= 3) {
            for ($i = 0; $i < count($effectiveSubWords) - 2; $i++) {
                $trigram = $effectiveSubWords[$i] . ' ' . $effectiveSubWords[$i + 1] . ' ' . $effectiveSubWords[$i + 2];
                if (str_contains($title, $trigram)) {
                    $hasTrigramInTitle = true;
                    break;
                }
            }
        }
        if (count($effectiveSubWords) >= 2) {
            for ($i = 0; $i < count($effectiveSubWords) - 1; $i++) {
                $bigram = $effectiveSubWords[$i] . ' ' . $effectiveSubWords[$i + 1];
                if (str_contains($title, $bigram)) {
                    $hasBigramInTitle = true;
                    break;
                }
            }
        }

        $phraseBonus = $hasTrigramInTitle ? 0.12 : ($hasBigramInTitle ? 0.08 : 0.0);

        // Word matches in title
        $matchedInTitle = 0;
        foreach ($effectiveSubWords as $word) {
            if ($this->wordMatches($title, $word)) {
                $matchedInTitle++;
            }
        }
        $titleRatio = $matchedInTitle / count($effectiveSubWords);

        // Word matches in document
        $matchedInDoc = 0;
        foreach ($effectiveSubWords as $word) {
            if ($this->wordMatches($title, $word) || $this->wordMatches($searchable, $word) || $this->wordMatches($content, $word)) {
                $matchedInDoc++;
            }
        }
        $docRatio = $matchedInDoc / count($effectiveSubWords);

        // Numeric specification precision bonus
        preg_match_all('/\b\d+(?:[.:]\d+)?\b/u', $cleanQuery, $numMatches);
        $numBonus = 0.0;
        if (!empty($numMatches[0])) {
            foreach ($numMatches[0] as $num) {
                if (str_contains($title, $num) || str_contains($content, $num) || str_contains($searchable, $num)) {
                    $numBonus = 0.06;
                    break;
                }
            }
        }

        if ($titleRatio === 1.0) {
            return round(min(0.92, 0.82 + 0.02 * count($effectiveSubWords) + $phraseBonus + $numBonus), 4);
        }

        if ($titleRatio >= 0.5) {
            return round(min(0.88, 0.62 + 0.20 * $titleRatio + $phraseBonus + $numBonus), 4);
        }

        if ($phraseBonus > 0.0) {
            return round(min(0.85, 0.58 + 0.15 * $docRatio + $phraseBonus + $numBonus), 4);
        }

        if ($docRatio === 1.0) {
            return round(min(0.85, 0.65 + 0.10 * $titleRatio + $phraseBonus + $numBonus), 4);
        }

        if ($docRatio >= 0.5) {
            return round(min(0.70, 0.40 + 0.25 * $docRatio + $phraseBonus + $numBonus), 4);
        }

        if ($docRatio > 0.0) {
            return round(min(0.40, 0.60 * $docRatio + $phraseBonus + $numBonus), 4);
        }

        return 0.0;
    }

    /**
     * Check if a token or its morphological/synonym variants match within target text.
     */
    private function wordMatches(string $haystack, string $needle): bool
    {
        $suffixes = ['গুলোতে', 'গুলোর', 'টিতে', 'টাতে', 'টার', 'টির', 'গুলো', 'গুলা', 'য়ের', 'ের', 'তে', 'এ', 'র', 'কে', 'টা', 'টি'];
        $variants = [$needle];
        foreach ($suffixes as $suf) {
            if (str_ends_with($needle, $suf)) {
                $stem = mb_substr($needle, 0, mb_strlen($needle) - mb_strlen($suf));
                if (mb_strlen($stem) >= 3) {
                    $variants[] = $stem;
                }
            }
        }

        foreach ($variants as $var) {
            if (mb_strlen($var) <= 3) {
                if ((bool) preg_match('/(?:^|[^\p{L}\p{M}\p{N}])' . preg_quote($var, '/') . '(?:$|[^\p{L}\p{M}\p{N}])/ui', $haystack)) {
                    return true;
                }
            } elseif (stripos($haystack, $var) !== false) {
                return true;
            }

            if (isset(self::GENERIC_SYNONYMS[$var])) {
                foreach (self::GENERIC_SYNONYMS[$var] as $syn) {
                    if (mb_strlen($syn) <= 3) {
                        if ((bool) preg_match('/(?:^|[^\p{L}\p{M}\p{N}])' . preg_quote($syn, '/') . '(?:$|[^\p{L}\p{M}\p{N}])/ui', $haystack)) {
                            return true;
                        }
                    } elseif (stripos($haystack, $syn) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
