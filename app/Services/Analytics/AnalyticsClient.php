<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AnalyticsClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?int $timeout = null,
    ) {}

    /**
     * Get the base URL for the Python Analytics Service.
     */
    public function baseUrl(): string
    {
        return rtrim($this->baseUrl ?? (string) config('analytics.base_url', 'http://127.0.0.1:8002'), '/');
    }

    /**
     * Get the request timeout in seconds.
     */
    public function timeout(): int
    {
        return $this->timeout ?? (int) config('analytics.timeout', 30);
    }

    /**
     * Dispatch an analytics natural-language question to the Python Baseline Analytics Service.
     *
     * @param string $query Natural language business intelligence query
     * @param int $workspaceId Authenticated runtime workspace context
     * @return array<string, mixed>
     */
    public function query(string $query, int $workspaceId): array
    {
        $t_start = microtime(true);
        $url = "{$this->baseUrl()}/analytics/query";

        try {
            $response = Http::timeout($this->timeout())
                ->asJson()
                ->acceptJson()
                ->post($url, [
                    'query' => $query,
                    'workspace_id' => $workspaceId,
                ]);

            $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success'               => (bool) ($data['success'] ?? true),
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

            Log::error('[AnalyticsClient] HTTP error from Python analytics service', [
                'status'       => $response->status(),
                'body'         => $response->body(),
                'workspace_id' => $workspaceId,
                'query'        => $query,
            ]);

            return [
                'success'           => false,
                'intent'            => 'service_error',
                'report'            => "⚠️ **Business Analytics Service Error** (HTTP {$response->status()})\n\nUnable to complete analysis for: `{$query}`.",
                'sql'               => null,
                'rows'              => [],
                'latency_ms'        => $elapsedMs,
                'client_latency_ms' => $elapsedMs,
            ];

        } catch (Throwable $e) {
            $elapsedMs = round((microtime(true) - $t_start) * 1000, 2);

            Log::warning('[AnalyticsClient] Connection failed to Python analytics service', [
                'url'          => $url,
                'error'        => $e->getMessage(),
                'workspace_id' => $workspaceId,
            ]);

            return [
                'success'           => false,
                'intent'            => 'service_unavailable',
                'report'            => "⚠️ **Business Analytics Service Unavailable**\n\nCould not connect to the Python Analytics Service (`{$url}`).\n\nPlease ensure the Python analytics service is running on port 8200:\n```bash\nuvicorn app.main:app --port 8200 --reload\n```",
                'sql'               => null,
                'rows'              => [],
                'latency_ms'        => $elapsedMs,
                'client_latency_ms' => $elapsedMs,
            ];
        }
    }
}
