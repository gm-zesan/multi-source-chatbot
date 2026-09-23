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

            $response = Http::timeout($this->timeout())
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
}
