<?php

namespace App\Services;

use App\Models\Embedding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VectorEmbeddingService
{
    /**
     * Cache prefix for embedding vectors.
     */
    protected string $cachePrefix;

    /**
     * Cache TTL in seconds (default 24 hours).
     */
    protected int $cacheTtl;

    /**
     * Python process timeout in seconds.
     */
    protected int $processTimeout;

    public function __construct()
    {
        $this->cachePrefix   = config('chatbot.vector.cache_prefix', 'embedding:');
        $this->cacheTtl      = (int) config('chatbot.vector.cache_ttl', 86400);
        $this->processTimeout = (int) config('chatbot.embedding.timeout', 120);
    }

    // ─── Core Embedding Generation ───────────────────────────────────────

    /**
     * Generate an embedding vector for the given text.
     *
     * Checks cache first, then falls back to the Python sentence-transformers
     * script. If the Python call fails, attempts to find a pre-computed
     * embedding in the database for similar text.
     *
     * @param  string $text The text to embed
     * @return array<int, float> 384-dimension normalized vector
     *
     * @throws \RuntimeException If embedding generation fails entirely
     */
    public function generateEmbedding(string $text): array
    {
        // 1. Check cache
        $cached = $this->getCachedEmbedding($text);
        if ($cached !== null) {
            return $cached;
        }

        // 2. Generate via Python
        try {
            $vector = $this->callPythonScript($text);
            $this->cacheEmbedding($text, $vector);
            return $vector;
        } catch (ProcessFailedException $e) {
            Log::warning('Python embedding failed, trying DB fallback', [
                'text' => mb_substr($text, 0, 100),
                'error' => $e->getMessage(),
            ]);
        }

        // 3. Fallback: check the database for a pre-computed embedding
        $dbVector = $this->findEmbeddingInDatabase($text);
        if ($dbVector !== null) {
            $this->cacheEmbedding($text, $dbVector);
            return $dbVector;
        }

        Log::error('Embedding generation failed completely', [
            'text' => mb_substr($text, 0, 100),
        ]);

        throw new \RuntimeException(
            'Failed to generate embedding. Ensure the Python environment is configured '
            . 'or that embeddings exist in the database.'
        );
    }

    /**
     * Generate embeddings for multiple texts at once (batched).
     *
     * @param  array<string> $texts
     * @return array<array<float>>
     */
    public function generateEmbeddings(array $texts): array
    {
        $results = [];
        $uncached = [];
        $keys = [];

        foreach ($texts as $index => $text) {
            $key = $this->cacheKey($text);
            $keys[$index] = $key;

            $cached = Cache::get($key);
            if ($cached !== null) {
                $results[$index] = $cached;
            } else {
                $uncached[$index] = $text;
            }
        }

        if (!empty($uncached)) {
            $batchResults = $this->callPythonScriptBatch(array_values($uncached));
            $i = 0;
            foreach ($uncached as $index => $text) {
                $vector = $batchResults[$i] ?? [];
                $results[$index] = $vector;
                Cache::put($keys[$index], $vector, $this->cacheTtl);
                $i++;
            }
        }

        ksort($results);
        return array_values($results);
    }

    // ─── Cache Helpers ───────────────────────────────────────────────────

    /**
     * Get a cached embedding for the given text.
     *
     * @param  string $text The original text
     * @return array|null Null if not cached
     */
    public function getCachedEmbedding(string $text): ?array
    {
        $cached = Cache::get($this->cacheKey($text));

        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        return null;
    }

    /**
     * Store an embedding vector in the cache.
     *
     * @param string       $text   The original text (used to derive cache key)
     * @param array<float> $vector The 384-dimension vector
     */
    public function cacheEmbedding(string $text, array $vector): void
    {
        Cache::put($this->cacheKey($text), $vector, $this->cacheTtl);
    }

    // ─── Similarity Matching ─────────────────────────────────────────────

    /**
     * Compute cosine similarity between two vectors.
     *
     * Both vectors must be non-empty, same length, and L2-normalized
     * for the result to be meaningful (range: -1.0 to 1.0).
     *
     * @param  array<float> $vec1
     * @param  array<float> $vec2
     * @return float Cosine similarity (-1.0 to 1.0, 0.0 on error)
     */
    public function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (empty($vec1) || empty($vec2) || count($vec1) !== count($vec2)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        foreach ($vec1 as $i => $val1) {
            $val2 = $vec2[$i] ?? 0.0;
            $dotProduct += $val1 * $val2;
            $norm1 += $val1 * $val1;
            $norm2 += $val2 * $val2;
        }

        $denominator = sqrt($norm1) * sqrt($norm2);

        return $denominator > 0 ? $dotProduct / $denominator : 0.0;
    }

    /**
     * Find the best matching candidate for an input string from a set of candidates.
     *
     * Accepts an array of candidates or a Laravel Collection of Embedding models.
     * Each candidate must expose an embedding vector accessible via:
     *   - `$candidate->embedding` (Embedding model)
     *   - `$candidate['vector']` (plain array)
     *
     * @param  string                $input      The user's natural language input
     * @param  array|Collection      $candidates Array of candidates or Collection of Embedding models
     * @param  float                 $threshold  Minimum similarity score (default 0.65)
     * @return array{key: string, similarity: float, candidate: mixed}|null
     */
    public function findBestMatch(
        string $input,
        array|Collection $candidates,
        float $threshold = 0.65,
    ): ?array {
        $inputVector = $this->generateEmbedding($input);

        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $candidate) {
            $candidateVector = $this->extractVector($candidate);
            $key = $this->extractKey($candidate);

            if (empty($candidateVector)) {
                continue;
            }

            $score = $this->cosineSimilarity($inputVector, $candidateVector);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'key'        => $key,
                    'similarity' => $score,
                    'candidate'  => $candidate,
                ];
            }
        }

        return ($best !== null && $bestScore >= $threshold) ? $best : null;
    }

    /**
     * Find the top N best matches, sorted by similarity descending.
     *
     * @param  string           $input      The user's natural language input
     * @param  array|Collection $candidates Array of candidates or Collection of Embedding models
     * @param  int              $n          Maximum number of results
     * @param  float            $threshold  Minimum similarity (default 0.0)
     * @return array<array{key: string, similarity: float, candidate: mixed}>
     */
    public function findTopN(
        string $input,
        array|Collection $candidates,
        int $n = 5,
        float $threshold = 0.0,
    ): array {
        $inputVector = $this->generateEmbedding($input);
        $scores = [];

        foreach ($candidates as $candidate) {
            $candidateVector = $this->extractVector($candidate);
            $key = $this->extractKey($candidate);

            if (empty($candidateVector)) {
                continue;
            }

            $score = $this->cosineSimilarity($inputVector, $candidateVector);

            if ($score >= $threshold) {
                $scores[] = [
                    'key'        => $key,
                    'similarity' => $score,
                    'candidate'  => $candidate,
                ];
            }
        }

        usort($scores, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($scores, 0, $n);
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    /**
     * Extract the embedding vector from a candidate (supports Embedding models and arrays).
     *
     * @param  mixed $candidate
     * @return array
     */
    private function extractVector(mixed $candidate): array
    {
        if ($candidate instanceof Embedding) {
            return $candidate->embedding ?? [];
        }

        if (is_array($candidate)) {
            return $candidate['vector'] ?? $candidate['embedding'] ?? [];
        }

        return [];
    }

    /**
     * Extract a unique key from a candidate.
     *
     * @param  mixed $candidate
     * @return string
     */
    private function extractKey(mixed $candidate): string
    {
        if ($candidate instanceof Embedding) {
            return $candidate->entity_key ?? $candidate->entity_name ?? '';
        }

        if (is_array($candidate)) {
            return $candidate['key'] ?? $candidate['entity_key'] ?? $candidate['text'] ?? '';
        }

        return '';
    }

    /**
     * Build a consistent cache key for a text.
     */
    private function cacheKey(string $text): string
    {
        return $this->cachePrefix . md5($text);
    }

    /**
     * Attempt to find a pre-computed embedding in the database for text
     * that closely matches the input.
     */
    private function findEmbeddingInDatabase(string $text): ?array
    {
        // Try exact text match against metadata descriptions
        $embedding = Embedding::where('metadata', 'like', '%' . $text . '%')
            ->orWhere('entity_name', $text)
            ->orWhere('entity_key', $text)
            ->first();

        if ($embedding && $embedding->hasValidEmbedding()) {
            Log::info('Fallback: found database embedding for text', [
                'text' => mb_substr($text, 0, 100),
                'entity' => $embedding->entity_key,
            ]);
            return $embedding->getVector();
        }

        return null;
    }

    // ─── Python Script Execution ─────────────────────────────────────────

    /**
     * Call the Python script to embed a single text.
     *
     * @throws ProcessFailedException
     */
    protected function callPythonScript(string $text): array
    {
        $pythonPath = config('chatbot.embedding.python', 'python');
        $scriptPath = config('chatbot.embedding.script');

        $process = new Process([
            $pythonPath,
            $scriptPath,
            $text,
        ]);

        $process->setTimeout($this->processTimeout);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Python embedding script failed', [
                'exit_code' => $process->getExitCode(),
                'error'     => mb_substr($process->getErrorOutput(), 0, 500),
            ]);
            throw new ProcessFailedException($process);
        }

        $output = trim($process->getOutput());
        $decoded = json_decode($output, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'Invalid embedding output from Python script: ' . mb_substr($output, 0, 200)
            );
        }

        return $decoded;
    }

    /**
     * Call the Python script to embed multiple texts in batch.
     *
     * @param  array<string> $texts
     * @return array<array<float>>
     */
    protected function callPythonScriptBatch(array $texts): array
    {
        $pythonPath = config('chatbot.embedding.python', 'python');
        $scriptPath = config('chatbot.embedding.script');
        $timeout    = max($this->processTimeout, count($texts) * 10);

        // Write input to temp file to avoid shell argument limits on large batches
        $tempFile = tempnam(sys_get_temp_dir(), 'emb_');
        file_put_contents($tempFile, json_encode($texts));

        try {
            $process = new Process([
                $pythonPath,
                $scriptPath,
                '--input-file', $tempFile,
            ]);

            $process->setTimeout($timeout);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('Batch embedding generation failed', [
                    'exit_code' => $process->getExitCode(),
                    'error'     => mb_substr($process->getErrorOutput(), 0, 500),
                    'count'     => count($texts),
                ]);
                throw new ProcessFailedException($process);
            }

            $output = trim($process->getOutput());
            $decoded = json_decode($output, true);

            if (!is_array($decoded)) {
                throw new \RuntimeException(
                    'Invalid batch embedding output: ' . mb_substr($output, 0, 200)
                );
            }

            return $decoded;
        } finally {
            @unlink($tempFile);
        }
    }
}
