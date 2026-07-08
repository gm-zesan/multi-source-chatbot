<?php

namespace App\Services\NLP\Embedding;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\NLP\Embedding\EmbeddingException;

class EmbeddingService
{
    /**
     * @param array<string, mixed> $config Embedding configuration.
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * Generate an embedding vector for a single text.
     *
     * @throws EmbeddingException
     */
    public function embed(string $text): EmbeddingResponse
    {
        $this->validateText($text);

        return $this->retry(fn () => $this->callEmbed($text));
    }

    /**
     * Generate embedding vectors for multiple texts in one request.
     *
     * @param string[] $texts
     * @return BatchEmbeddingResponse
     * @throws EmbeddingException
     */
    public function embedBatch(array $texts): BatchEmbeddingResponse
    {
        foreach ($texts as $text) {
            $this->validateText($text);
        }

        return $this->retry(fn () => $this->callEmbedBatch($texts));
    }

    /**
     * Check if the FastAPI embedding service is reachable and healthy.
     *
     * @return array{status: string, model: string, dimensions: int, latency_ms: float}
     */
    public function health(): array
    {
        $start = microtime(true);

        try {
            $response = Http::timeout($this->config('timeout', 30))
                ->get($this->url('/health'));

            if ($response->successful()) {
                $body = $response->json();
                $latency = (microtime(true) - $start) * 1000;

                Log::debug('[EmbeddingService] Health check passed', [
                    'latency_ms' => round($latency, 2),
                ]);

                return [
                    'status'     => $body['status'] ?? 'ok',
                    'model'      => $body['model'] ?? $this->config('model', 'unknown'),
                    'dimensions' => (int) ($body['dimensions'] ?? $this->config('dimensions', 384)),
                    'latency_ms' => round($latency, 2),
                ];
            }

            throw new EmbeddingException(
                'Health check failed with status ' . $response->status(),
                $response->status(),
            );
        } catch (ConnectionException $e) {
            throw new EmbeddingException(
                'Cannot reach embedding service: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * Get the current configuration value.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    // ─── Internal ──────────────────────────────────────────────────────────

    /**
     * Call the FastAPI /embed endpoint for a single text.
     *
     * @throws EmbeddingException
     */
    private function callEmbed(string $text): EmbeddingResponse
    {
        $start = microtime(true);

        try {
            $response = Http::timeout($this->config('timeout', 30))
                ->post($this->url('/embed'), [
                    'text' => $text,
                ]);

            $latency = (microtime(true) - $start) * 1000;

            $response->throw();

            $body = $response->json();
            $result = EmbeddingResponse::fromArray($body);

            Log::debug('[EmbeddingService] Embedding generated', [
                'dimensions' => $result->dimensions,
                'model'      => $result->model,
                'time_ms'    => round($latency, 2),
                'text_preview' => mb_substr($text, 0, 80),
            ]);

            return $result;
        } catch (RequestException $e) {
            $this->logError('embed', $text, $e);
            throw new EmbeddingException(
                'Embedding request failed: ' . $e->getMessage(),
                $e->response?->status() ?? 0,
                $e,
            );
        } catch (ConnectionException $e) {
            $this->logError('embed', $text, $e);
            throw new EmbeddingException(
                'Embedding service unreachable: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * Call the FastAPI /embed-batch endpoint for multiple texts.
     *
     * @param string[] $texts
     * @throws EmbeddingException
     */
    private function callEmbedBatch(array $texts): BatchEmbeddingResponse
    {
        $start = microtime(true);

        try {
            $response = Http::timeout($this->config('timeout', 30))
                ->post($this->url('/embed-batch'), [
                    'texts' => $texts,
                ]);

            $latency = (microtime(true) - $start) * 1000;

            $response->throw();

            $body = $response->json();
            $result = BatchEmbeddingResponse::fromArray($body);

            Log::debug('[EmbeddingService] Batch embedding generated', [
                'count'      => count($result->items),
                'dimensions' => $result->dimensions,
                'model'      => $result->model,
                'time_ms'    => round($latency, 2),
            ]);

            return $result;
        } catch (RequestException $e) {
            Log::error('[EmbeddingService] Batch embedding failed', [
                'count'  => count($texts),
                'status' => $e->response?->status(),
                'error'  => $e->getMessage(),
            ]);
            throw new EmbeddingException(
                'Batch embedding request failed: ' . $e->getMessage(),
                $e->response?->status() ?? 0,
                $e,
            );
        } catch (ConnectionException $e) {
            Log::error('[EmbeddingService] Batch embedding service unreachable', [
                'count' => count($texts),
                'error' => $e->getMessage(),
            ]);
            throw new EmbeddingException(
                'Embedding service unreachable: ' . $e->getMessage(),
                0,
                $e,
            );
        }
    }

    /**
     * Execute a callable with retry logic.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws EmbeddingException
     */
    private function retry(callable $callback): mixed
    {
        $maxAttempts = $this->config('retry.max_attempts', 3);
        $delayMs = $this->config('retry.delay_ms', 500);
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $callback();
            } catch (EmbeddingException $e) {
                // Don't retry 4xx client errors (except 429 rate limit)
                $status = $e->getCode();
                if ($status >= 400 && $status < 500 && $status !== 429) {
                    throw $e;
                }

                if ($attempt >= $maxAttempts) {
                    Log::error('[EmbeddingService] All retry attempts exhausted', [
                        'attempts' => $attempt,
                        'error'    => $e->getMessage(),
                    ]);
                    throw $e;
                }

                Log::warning('[EmbeddingService] Retrying after failure', [
                    'attempt'  => $attempt,
                    'max'      => $maxAttempts,
                    'error'    => $e->getMessage(),
                    'delay_ms' => $delayMs,
                ]);

                usleep($delayMs * 1000);
            }
        }
    }

    /**
     * Build a full URL for the embedding API endpoint.
     */
    private function url(string $path): string
    {
        return rtrim($this->config('base_url', 'http://127.0.0.1:8000'), '/') . $path;
    }

    /**
     * Validate that the input text is not empty.
     *
     * @throws EmbeddingException
     */
    private function validateText(string $text): void
    {
        $trimmed = trim($text);

        if ($trimmed === '') {
            throw new EmbeddingException('Input text must not be empty.');
        }
    }

    /**
     * Log an embedding error with context.
     */
    private function logError(string $operation, string $text, \Throwable $e): void
    {
        Log::error("[EmbeddingService] {$operation} failed", [
            'text_preview' => mb_substr($text, 0, 80),
            'error'        => $e->getMessage(),
        ]);
    }
}
