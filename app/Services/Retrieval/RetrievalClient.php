<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Enums\DocumentLifecycleStatus;
use App\Models\FAQ;
use App\Models\KnowledgeDocument;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RetrievalClient
{
    private array $lastTelemetry = [];

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
                $results = $data['results'] ?? [];
                $telemetry = $data['telemetry'] ?? [];
                $this->lastTelemetry = $telemetry;

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
            Log::warning('[RetrievalClient] Search returned non-200 status', [
                'status' => $response->status(),
                'query'  => mb_substr($trimmed, 0, 80),
            ]);
        } catch (\Throwable $e) {
            $this->lastTelemetry = [];
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
                    'is_active'     => true,
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

                $mapped[] = new FAQSearchResult(
                    faq: $faq,
                    keywordScore: $matchType === 'keyword' ? $score : 0.0,
                    semanticScore: $matchType === 'vector' ? $score : 0.0,
                    finalScore: $score,
                    matchType: $matchType,
                );
            }
        }

        return new Collection(array_slice($mapped, 0, $topK));
    }

    /**
     * Database fallback when Python service is unavailable.
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

        $words = array_filter(explode(' ', $query), fn ($w) => mb_strlen(trim($w)) > 2);

        $dbQuery = FAQ::where('is_active', true)
            ->where('workspace_id', $workspaceId);

        if (! empty($words)) {
            $dbQuery->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhere('question', 'LIKE', '%' . $word . '%')
                      ->orWhere('answer', 'LIKE', '%' . $word . '%')
                      ->orWhere('searchable_text', 'LIKE', '%' . $word . '%');
                }
            });
        }

        $faqs = $dbQuery->limit($topK)->get();

        $mapped = $faqs->map(fn (FAQ $faq) => new FAQSearchResult(
            faq: $faq,
            keywordScore: 0.85,
            semanticScore: 0.0,
            finalScore: 0.85,
            matchType: 'keyword_fallback',
        ))->all();

        // Also search active knowledge documents in database fallback mode
        $docQuery = KnowledgeDocument::where('is_active', true)
            ->where('lifecycle_status', DocumentLifecycleStatus::ACTIVE)
            ->where('workspace_id', $workspaceId);

        if (! empty($words)) {
            $docQuery->where(function ($q) use ($words) {
                foreach ($words as $word) {
                    $q->orWhere('title', 'LIKE', '%' . $word . '%')
                      ->orWhere('metadata', 'LIKE', '%' . $word . '%');
                }
            });
        }

        $docs = $docQuery->limit($topK)->get();
        foreach ($docs as $doc) {
            $rawText = $doc->metadata['raw_text'] ?? null;
            if (empty($rawText) && Storage::disk('local')->exists($doc->file_path)) {
                $rawText = (string) Storage::disk('local')->get($doc->file_path);
            }
            if (! empty($rawText)) {
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
                    keywordScore: 0.85,
                    semanticScore: 0.0,
                    finalScore: 0.85,
                    matchType: 'keyword_fallback',
                );
            }
        }

        return new Collection(array_slice($mapped, 0, $topK));
    }
}
