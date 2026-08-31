<?php

declare(strict_types=1);

namespace App\Services\Retrieval;

use App\Models\FAQ;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RetrievalClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $apiKey = null,
        private readonly int $timeout = 15,
        private readonly int $defaultTopK = 5,
    ) {}

    /**
     * Get the base URL for the Python retrieval service.
     */
    public function baseUrl(): string
    {
        return rtrim($this->baseUrl ?? (string) config('retrieval.base_url', 'http://127.0.0.1:8002'), '/');
    }

    /**
     * Search relevant FAQs through the Python Retrieval Service.
     *
     * @return Collection<int, FAQSearchResult>
     */
    public function search(string $query, ?int $workspaceId = null, ?int $topK = null): Collection
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            return new Collection();
        }

        $topK = $topK ?? $this->defaultTopK;
        $url = "{$this->baseUrl()}/api/v1/search";
        $payload = [
            'query'        => $trimmed,
            'workspace_id' => $workspaceId,
            'top_k'        => $topK,
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

                return $this->mapResults($results, $topK);
            }

            Log::warning('[RetrievalClient] Search returned non-200 status', [
                'status' => $response->status(),
                'query'  => mb_substr($trimmed, 0, 80),
            ]);
        } catch (\Throwable $e) {
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
        $lexiconTerms = $faq->relationLoaded('lexicon') && $faq->lexicon ? $faq->lexicon->allTerms() : [];

        $payload = [
            'id'            => $faq->id,
            'workspace_id'  => $faq->workspace_id,
            'question'      => $faq->question,
            'answer'        => $faq->answer,
            'priority'      => $faq->priority ?? 100,
            'is_active'     => (bool) $faq->is_active,
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
            Log::warning('[RetrievalClient] FAQ sync to Python service failed', [
                'faq_id' => $faq->id,
                'error'  => $e->getMessage(),
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
            $req = Http::timeout(3);
            $key = $this->apiKey ?? config('retrieval.api_key');
            if (! empty($key)) {
                $req = $req->withToken($key);
            }

            $response = $req->get($url);
            $latency = round((microtime(true) - $start) * 1000, 2);

            return [
                'ok'         => $response->successful(),
                'latency_ms' => $latency,
                'error'      => $response->successful() ? null : "HTTP {$response->status()}",
            ];
        } catch (\Throwable $e) {
            $latency = round((microtime(true) - $start) * 1000, 2);

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
    private function mapResults(array $results, int $topK): Collection
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

            if ($faq === null && isset($item['question'], $item['answer'])) {
                // If model not in local DB (e.g. distributed store), build a transient instance
                $faq = new FAQ([
                    'id'           => $id ?? 0,
                    'question'     => (string) $item['question'],
                    'answer'       => (string) $item['answer'],
                    'workspace_id' => $item['workspace_id'] ?? null,
                    'is_active'    => true,
                ]);
                $faq->exists = true;
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
        $words = array_filter(explode(' ', $query), fn ($w) => mb_strlen(trim($w)) > 2);

        $dbQuery = FAQ::where('is_active', true);
        if ($workspaceId !== null) {
            $dbQuery->where('workspace_id', $workspaceId);
        }

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

        return new Collection($mapped);
    }
}
