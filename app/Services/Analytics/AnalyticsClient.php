<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Services\Analytics\DTOs\Contract3\SemanticQueryPlan;
use App\Services\Analytics\DTOs\Contract3\PreparedQueryArtifact;
use App\Services\Analytics\DTOs\Contract3\ExecutionAuthorizationGrant;
use App\Services\Analytics\DTOs\Contract3\AnalyticsExecutionResponse;
use App\Services\Spreadsheet\Relationships\DTOs\SpreadsheetRelationshipGraph;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AnalyticsClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?int $timeout = null,
        private readonly ?string $apiKey = null,
    ) {}

    /**
     * Get the base URL for the Python Analytics Service.
     */
    public function baseUrl(): string
    {
        return rtrim($this->baseUrl ?? (string) config('analytics.base_url', 'http://127.0.0.1:8001'), '/');
    }

    /**
     * Get the request timeout in seconds.
     */
    public function timeout(): int
    {
        return $this->timeout ?? (int) config('analytics.timeout', 30);
    }

    /**
     * Get the API key for authenticating requests to the Python Analytics Service.
     */
    public function apiKey(): ?string
    {
        return $this->apiKey ?? config('analytics.api_key', config('retrieval.api_key'));
    }

    /**
     * Compute the dynamically bounded request timeout in seconds.
     * Respects active AgentContext monotonic SLA budget if available.
     */
    public function effectiveTimeout(): float
    {
        $defaultTimeout = (float) $this->timeout();

        if (\App\AI\DTOs\AgentContext::hasCurrent()) {
            $remaining = \App\AI\DTOs\AgentContext::current()->getRemainingTimeout();
            return max(0.1, min($defaultTimeout, $remaining));
        }

        return $defaultTimeout;
    }

    /**
     * Create an authenticated HTTP pending request with timeout and API credentials.
     * Bounded transfer timeout and connectTimeout prevent DNS and TCP connection hangs.
     */
    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        $timeout = $this->effectiveTimeout();
        $connectTimeout = min($timeout, 2.0);

        $req = Http::timeout($timeout)->connectTimeout($connectTimeout);
        $key = $this->apiKey();
        if (! empty($key)) {
            $req = $req->withHeaders([
                'X-API-Key' => $key,
            ])->withToken($key);
        }

        return $req;
    }

    /**
     * Dispatch an analytics natural-language question to the Python Baseline Analytics Service.
     *
     * @param string $query Natural language business intelligence query
     * @param int $workspaceId Authenticated runtime workspace context
     * @return array<string, mixed>
     */
    public function query(string $query, int $workspaceId, array $history = []): array
    {
        $t_start = microtime(true);
        $url = "{$this->baseUrl()}/analytics/query";

        try {
            $payload = [
                'query' => $query,
                'workspace_id' => $workspaceId,
                'engine' => 'semantic',
            ];
            if (!empty($history)) {
                $payload['history'] = $history;
            }

            $response = $this->http()
                ->asJson()
                ->acceptJson()
                ->post($url, $payload);

            $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!is_array($data) || !isset($data['success'])) {
                    throw new \RuntimeException('Malformed or unexpected JSON schema returned.');
                }
                
                // Explicitly check for success flag
                if ($data['success'] !== true) {
                    throw new \RuntimeException('Analytics service reported an internal failure.');
                }

                Log::info('[AnalyticsClient] Query processed successfully', [
                    'workspace_id'      => $workspaceId,
                    'engine'            => 'semantic',
                    'intent'            => $data['intent'] ?? 'business_analytics',
                    'is_ambiguous'      => (bool) ($data['is_ambiguous'] ?? false),
                    'latency_ms'        => (float) ($data['latency_ms'] ?? $elapsedMs),
                    'client_latency_ms' => $elapsedMs,
                ]);

                return [
                    'success'               => true,
                    'engine'                => 'semantic',
                    'intent'                => (string) ($data['intent'] ?? 'business_analytics'),
                    'report'                => (string) ($data['report'] ?? ''),
                    'sql'                   => $data['sql'] ?? null,
                    'rows'                  => $data['rows'] ?? [],
                    'is_security_rejection' => (bool) ($data['is_security_rejection'] ?? false),
                    'is_ambiguous'          => (bool) ($data['is_ambiguous'] ?? false),
                    'latency_ms'            => (float) ($data['latency_ms'] ?? $elapsedMs),
                    'client_latency_ms'     => $elapsedMs,
                ];
            }

            // HTTP 4xx / 5xx handling
            Log::error('[AnalyticsClient] HTTP error from Python analytics service', [
                'status'       => $response->status(),
                'workspace_id' => $workspaceId,
            ]);

            return [
                'success'           => false,
                'engine'            => 'semantic',
                'intent'            => 'service_unavailable',
                'report'            => "⚠️ **Analytics Service Error**\n\nThe analytics engine is currently unavailable (HTTP {$response->status()}). Please try again later.",
                'error'             => [
                    'code' => 'service_error',
                    'message' => 'Analytics service returned an HTTP error'
                ],
                'latency_ms'        => $elapsedMs,
                'client_latency_ms' => $elapsedMs,
            ];

        } catch (Throwable $e) {
            $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

            Log::warning('[AnalyticsClient] Connection or parsing failed', [
                'url'          => $url,
                'error'        => $e->getMessage(),
                'workspace_id' => $workspaceId,
            ]);

            return [
                'success'           => false,
                'engine'            => 'semantic',
                'intent'            => 'service_unavailable',
                'report'            => "⚠️ **Analytics Service Unavailable**\n\nCould not connect to the analytics engine or process the response. Please ensure the service is running.",
                'error'             => [
                    'code' => 'service_unavailable',
                    'message' => 'Analytics connection failed'
                ],
                'latency_ms'        => $elapsedMs,
                'client_latency_ms' => $elapsedMs,
            ];
        }
    }

    /**
     * Upload a Document or Spreadsheet (Excel/CSV) to the Python Analytics Virtual Database Engine.
     *
     * @param string $filePath Full path to local temporary/stored file
     * @param string $filename Original filename
     * @param int $workspaceId
     * @param string|null $conversationId
     * @param string|null $format Optional source format override (e.g. 'xlsx', 'csv')
     * @return array<string, mixed>
     */
    public function uploadSpreadsheet(
        string $filePath,
        string $filename,
        int $workspaceId,
        ?string $conversationId = null,
        ?string $format = null,
        ?string $sourceId = null,
    ): array {
        $url = "{$this->baseUrl()}/analytics/excel/upload";
        try {
            $req = $this->http()
                ->attach('file', file_get_contents($filePath), $filename);

            $payload = ['workspace_id' => $workspaceId];
            if ($conversationId !== null) {
                $payload['conversation_id'] = $conversationId;
            }
            if ($format !== null) {
                $payload['format'] = $format;
            }
            if ($sourceId !== null && trim($sourceId) !== '') {
                $payload['source_id'] = trim($sourceId);
            }

            $response = $req->post($url, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            $json = $response->json();
            $errorMessage = $json['message'] ?? $json['detail'] ?? ('HTTP error uploading data source: ' . $response->status());

            return [
                'success' => false,
                'message' => is_string($errorMessage) ? $errorMessage : ('HTTP error uploading data source: ' . $response->status()),
            ];
        } catch (Throwable $e) {
            Log::error('[AnalyticsClient] Failed to upload data source: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to connect to analytics engine: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Upload any supported data source document to the Python Data Source Registry.
     *
     * @param string $filePath
     * @param string $filename
     * @param int $workspaceId
     * @param string|null $conversationId
     * @param string|null $format
     * @param string|null $sourceId
     * @return array<string, mixed>
     */
    public function uploadDocumentSource(
        string $filePath,
        string $filename,
        int $workspaceId,
        ?string $conversationId = null,
        ?string $format = null,
        ?string $sourceId = null,
    ): array {
        return $this->uploadSpreadsheet($filePath, $filename, $workspaceId, $conversationId, $format, $sourceId);
    }

    /**
     * Query an uploaded document/spreadsheet virtual database using natural language.
     *
     * @deprecated Direct legacy Excel query execution is permanently retired under P0-C.
     * All Excel query executions must be authorized via Contract 3.0 virtual execution gateway.
     *
     * @param string $question
     * @param int $workspaceId
     * @param string|null $fileId
     * @param array<int, array<string, string>>|null $history
     * @return array<string, mixed>
     */
    public function queryExcel(
        string $question,
        int $workspaceId,
        ?string $fileId = null,
        ?array $history = null,
    ): array {
        Log::warning('[AnalyticsClient] Deprecated queryExcel() invoked - direct legacy Excel query execution is disabled.');

        return [
            'success' => false,
            'status'  => 'retired',
            'report'  => 'Direct legacy Excel query execution is permanently retired. Use Contract 3.0 virtual execution gateway.',
            'rows'    => [],
        ];
    }

    /**
     * Query document sources in the Python Data Source Engine.
     *
     * @param string $question
     * @param int $workspaceId
     * @param string|null $fileId
     * @param array<int, array<string, string>>|null $history
     * @return array<string, mixed>
     */
    public function queryDocumentSource(
        string $question,
        int $workspaceId,
        ?string $fileId = null,
        ?array $history = null,
    ): array {
        return $this->queryExcel($question, $workspaceId, $fileId, $history);
    }

    /**
     * List all registered data sources for a workspace.
     *
     * @param int $workspaceId
     * @return array<string, mixed>
     */
    public function listSources(int $workspaceId): array
    {
        $url = "{$this->baseUrl()}/analytics/sources/list";
        try {
            $response = $this->http()
                ->acceptJson()
                ->get($url, ['workspace_id' => $workspaceId]);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'success' => false,
                'sources' => [],
                'message' => "HTTP {$response->status()}",
            ];
        } catch (Throwable $e) {
            Log::error('[AnalyticsClient] Failed to list sources: ' . $e->getMessage());
            return [
                'success' => false,
                'sources' => [],
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a registered data source.
     *
     * @param int $workspaceId
     * @param string $sourceId
     * @return array<string, mixed>
     */
    public function deleteSource(int $workspaceId, string $sourceId): array
    {
        $url = "{$this->baseUrl()}/analytics/sources/{$sourceId}";
        try {
            $response = $this->http()
                ->acceptJson()
                ->delete($url, ['workspace_id' => $workspaceId]);

            if ($response->successful() || $response->status() === 404) {
                return [
                    'success' => true,
                    'message' => $response->status() === 404 ? 'Source already removed or not found remotely.' : ($response->json('message') ?? 'Source deleted successfully.'),
                ];
            }

            $json = $response->json();
            $errorMessage = $json['message'] ?? $json['detail'] ?? "HTTP {$response->status()}";

            return [
                'success' => false,
                'message' => is_string($errorMessage) ? $errorMessage : "HTTP {$response->status()}",
            ];
        } catch (Throwable $e) {
            Log::error('[AnalyticsClient] Failed to delete source: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve the most relevant generic analytics source for a query using Typesense and schema matching.
     *
     * @param string $query
     * @param int $workspaceId
     * @param string|null $explicitSourceId
     * @return array<string, mixed>
     */
    public function resolveSource(string $query, int $workspaceId, ?string $explicitSourceId = null): array
    {
        $url = "{$this->baseUrl()}/analytics/sources/resolve";
        try {
            $payload = [
                'query'        => $query,
                'workspace_id' => $workspaceId,
            ];
            if ($explicitSourceId !== null && trim($explicitSourceId) !== '') {
                $payload['explicit_source_id'] = trim($explicitSourceId);
            }

            $response = $this->http()
                ->acceptJson()
                ->post($url, $payload);

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'success' => false,
                'status'  => 'error',
                'message' => "HTTP {$response->status()}",
            ];
        } catch (Throwable $e) {
            Log::warning('[AnalyticsClient] Source resolution request failed: ' . $e->getMessage());
            return [
                'success' => false,
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Stage A: Compile SemanticQueryPlan into PreparedQueryArtifact without executing it.
     *
     * @param SemanticQueryPlan $plan
     * @param int $workspaceId
     * @param string $datasetId
     * @param string $generationId
     * @param SpreadsheetRelationshipGraph|null $graph
     * @param array<string, mixed> $sourceDescriptors
     * @return PreparedQueryArtifact
     * @throws RuntimeException
     */
    public function prepareVirtualQuery(
        SemanticQueryPlan $plan,
        int $workspaceId,
        string $datasetId,
        string $generationId,
        ?SpreadsheetRelationshipGraph $graph = null,
        array $sourceDescriptors = [],
    ): PreparedQueryArtifact {
        $url = "{$this->baseUrl()}/analytics/v3/prepare";

        $payload = [
            'contract_version' => PreparedQueryArtifact::CONTRACT_VERSION,
            'workspace_id' => $workspaceId,
            'dataset_id' => $datasetId,
            'generation_id' => $generationId,
            'query_plan' => $plan->toArray(),
            'relationship_graph' => $graph?->toArray(),
            'source_descriptors' => $sourceDescriptors,
        ];

        try {
            $response = $this->http()
                ->asJson()
                ->acceptJson()
                ->post($url, $payload);

            if (!$response->successful()) {
                $err = $response->json('detail') ?? "HTTP {$response->status()}";
                throw new RuntimeException("Virtual query compilation failed: {$err}");
            }

            $data = $response->json();
            if (!is_array($data) || !isset($data['query_fingerprint'])) {
                throw new RuntimeException('Invalid prepared query artifact schema returned from analytics service.');
            }

            return PreparedQueryArtifact::fromArray($data);
        } catch (Throwable $e) {
            Log::error('[AnalyticsClient] prepareVirtualQuery error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stage C: Execute authorized prepared query against isolated virtual SQLite database.
     * Requires cryptographically signed ExecutionAuthorizationGrant.
     *
     * @param PreparedQueryArtifact $artifact
     * @param ExecutionAuthorizationGrant $grant
     * @return AnalyticsExecutionResponse
     * @throws RuntimeException
     */
    public function executeVirtualQuery(
        PreparedQueryArtifact $artifact,
        ExecutionAuthorizationGrant $grant,
    ): AnalyticsExecutionResponse {
        $url = "{$this->baseUrl()}/analytics/v3/execute";

        $payload = [
            'contract_version' => PreparedQueryArtifact::CONTRACT_VERSION,
            'workspace_id' => $artifact->workspaceId,
            'dataset_id' => $artifact->datasetId,
            'generation_id' => $artifact->generationId,
            'query_fingerprint' => $artifact->queryFingerprint,
            'sql' => $artifact->sqlTemplate,
            'params' => $artifact->params,
            'authorization_grant' => $grant->toArray(),
        ];

        try {
            $response = $this->http()
                ->asJson()
                ->acceptJson()
                ->post($url, $payload);

            if (!$response->successful()) {
                $json = $response->json();
                $msg = is_array($json) && isset($json['detail']) ? (is_array($json['detail']) ? json_encode($json['detail']) : $json['detail']) : "HTTP {$response->status()}";
                throw new RuntimeException("Virtual query execution rejected: {$msg}");
            }

            $data = $response->json();
            if (!is_array($data) || !isset($data['success'])) {
                throw new RuntimeException('Invalid execution response schema returned from analytics service.');
            }

            return AnalyticsExecutionResponse::fromArray($data);
        } catch (Throwable $e) {
            Log::error('[AnalyticsClient] executeVirtualQuery error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Plan candidate SemanticQueryPlan from natural language question without executing SQL.
     *
     * @param string $question
     * @param int $workspaceId
     * @param string $datasetId
     * @param array<int, array<string, string>>|null $history
     * @return SemanticQueryPlan
     */
    public function planCandidateQuery(
        string $question,
        int $workspaceId,
        string $datasetId,
        ?array $history = null,
    ): SemanticQueryPlan {
        $url = "{$this->baseUrl()}/analytics/v3/plan-candidate";

        $payload = [
            'question' => $question,
            'workspace_id' => $workspaceId,
            'dataset_id' => $datasetId,
            'history' => $history,
        ];

        try {
            $response = $this->http()
                ->asJson()
                ->acceptJson()
                ->post($url, $payload);

            if ($response->successful() && is_array($response->json())) {
                return SemanticQueryPlan::fromArray($response->json());
            }

            // Fallback to default heuristic plan
            return new SemanticQueryPlan(
                domain: 'sales',
                measures: [['name' => '*', 'aggregation' => 'count', 'alias' => 'total_count']],
            );
        } catch (Throwable $e) {
            Log::warning('[AnalyticsClient] planCandidateQuery fallback: ' . $e->getMessage());
            return new SemanticQueryPlan(
                domain: 'sales',
                measures: [['name' => '*', 'aggregation' => 'count', 'alias' => 'total_count']],
            );
        }
    }
}


