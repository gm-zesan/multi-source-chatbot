<?php

declare(strict_types=1);

/**
 * =============================================================================
 * CONTROLLED LLM PROVIDER & MODEL BENCHMARK
 * =============================================================================
 * Evaluates candidate models on OpenRouter using the EXACT SAME prompt & context:
 *   - Query: "notun akaunt kivabe khulbo?"
 *   - Retrieved FAQ Context: "How do I create an account?" (Doc #1)
 *
 * Metrics Evaluated:
 *   1. Time To First Token (TTFT) in ms
 *   2. Total Generation Latency in ms
 *   3. Output Token Count (Target: 120 - 220 tokens vs current 506)
 *   4. Generation Speed (tokens/sec)
 *   5. Response HTTP Status / 429 Error Safety
 *   6. Language Naturalness & Citation Correctness
 * =============================================================================
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = config('ai.providers.openrouter.key', env('OPENROUTER_API_KEY'));

if (empty($apiKey)) {
    echo "❌ Error: OPENROUTER_API_KEY is not set.\n";
    exit(1);
}

$query = 'notun akaunt kivabe khulbo?';
$retrievedDocs = "[Document #1]\nQuestion: How do I create an account?\nAnswer: Click Sign Up on the top right, enter your email and password, then verify your email to get started.";

$prompt = <<<PROMPT
You are a professional, helpful, and polite Enterprise Customer Support AI Assistant.
Your goal is to assist customers accurately, politely, and concisely.

Core Architectural Operating Principle:
Knowledge Base (KB) is the preferred source for business-specific information, but not the only source. When relevant KB context is unavailable, you may answer using general knowledge, provided the query is sufficiently understood and does not require proprietary company policies.

Instructions:
1. Grounded Answers: Ground your answer on relevant retrieved documents.
2. Conciseness: Keep the answer clear, structured, and concise (under 150-200 words).
3. Multi-language: Respond naturally in the user's language (Bengali/Banglish).

Retrieved Knowledge Base Documents:
{$retrievedDocs}
PROMPT;

$candidateModels = [
    'openrouter/free'                               => 'OpenRouter Free Router (Baseline)',
    'nvidia/nemotron-3.5-lightning:free'            => 'NVIDIA Nemotron 3.5 Lightning',
    'z-ai/glm-5.2:free'                             => 'Z.ai GLM 5.2',
    'google/gemma-4-26b-a4b-it:free'                => 'Google Gemma 4 26B',
    'google/gemma-4-31b-it:free'                    => 'Google Gemma 4 31B',
    'minimax/minimax-m2.7:free'                     => 'MiniMax M2.7',
    'minimax/minimax-m3:free'                       => 'MiniMax M3 (Current default)',
    'liquid/lfm-2.5-2.6b:free'                      => 'LiquidAI LFM 2.5 2.6B',
];

echo "=================================================================================================\n";
echo "🔬 CONTROLLED LLM MODEL BENCHMARK (Exact Same Prompt & Grounded KB Context)\n";
echo "Query: \"{$query}\"\n";
echo "=================================================================================================\n\n";

$results = [];

function benchmarkModel(string $modelId, string $label, string $apiKey, string $systemPrompt, string $userQuery): array {
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'HTTP-Referer: http://localhost:8000',
        'X-Title: Benchmark Controller',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $modelId,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userQuery],
        ],
        'stream' => true,
        'stream_options' => ['include_usage' => true],
        'max_tokens' => 350,
        'temperature' => 0.2,
    ]));

    $ttftMs = null;
    $fullContent = '';
    $actualModel = 'unknown';
    $promptTokens = 0;
    $completionTokens = 0;

    $tStart = microtime(true);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$ttftMs, $tStart, &$fullContent, &$actualModel, &$promptTokens, &$completionTokens) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line === 'data: [DONE]') {
                continue;
            }
            if (str_starts_with($line, 'data: ')) {
                $jsonStr = substr($line, 6);
                $parsed = json_decode($jsonStr, true);
                if ($parsed) {
                    if (!empty($parsed['model'])) {
                        $actualModel = $parsed['model'];
                    }
                    if (!empty($parsed['usage'])) {
                        $promptTokens = $parsed['usage']['prompt_tokens'] ?? $promptTokens;
                        $completionTokens = $parsed['usage']['completion_tokens'] ?? $completionTokens;
                    }
                    $delta = $parsed['choices'][0]['delta']['content'] ?? '';
                    if ($delta !== '') {
                        if ($ttftMs === null) {
                            $ttftMs = round((microtime(true) - $tStart) * 1000, 2);
                        }
                        $fullContent .= $delta;
                    }
                }
            }
        }
        return strlen($data);
    });

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $totalMs = round((microtime(true) - $tStart) * 1000, 2);
    $genSec = max(0.001, ($totalMs - ($ttftMs ?? 0)) / 1000);
    $tps = $completionTokens > 0 ? round($completionTokens / $genSec, 1) : 0;

    // Approximate token count from word count if usage was omitted by provider
    if ($completionTokens === 0 && mb_strlen($fullContent) > 0) {
        $completionTokens = (int) round(mb_strlen($fullContent) / 3.5);
        $tps = round($completionTokens / $genSec, 1);
    }

    return [
        'id'            => $modelId,
        'label'         => $label,
        'actual_model'  => $actualModel,
        'http_code'     => $httpCode,
        'ttft_ms'       => $ttftMs ?? $totalMs,
        'total_ms'      => $totalMs,
        'tokens'        => $completionTokens,
        'tps'           => $tps,
        'content'       => $fullContent,
        'error'         => $err,
    ];
}

foreach ($candidateModels as $id => $label) {
    echo "Testing: {$label} ({$id})...\n";
    $res = benchmarkModel($id, $label, $apiKey, $prompt, $query);
    $results[] = $res;
    echo "  ↳ HTTP: {$res['http_code']} | TTFT: {$res['ttft_ms']} ms | Total: {$res['total_ms']} ms | Tokens: {$res['tokens']} | Speed: {$res['tps']} t/s\n";
    echo "  ↳ Sample: " . mb_substr(str_replace("\n", " ", $res['content']), 0, 70) . "...\n\n";
    sleep(1); // 1s safety interval to prevent 429
}

echo "=================================================================================================\n";
echo "📊 CONTROLLED LLM BENCHMARK SCORECARD\n";
echo "=================================================================================================\n";
printf("%-35s | %-6s | %-10s | %-10s | %-8s | %-10s\n", "Model", "HTTP", "TTFT (ms)", "Total (ms)", "Tokens", "Speed (t/s)");
echo "-------------------------------------------------------------------------------------------------\n";

foreach ($results as $r) {
    printf(
        "%-35s | %-6s | %10.1f | %10.1f | %8d | %10.1f\n",
        mb_substr($r['label'], 0, 35),
        (string) $r['http_code'],
        (float) $r['ttft_ms'],
        (float) $r['total_ms'],
        (int) $r['tokens'],
        (float) $r['tps']
    );
}
echo "=================================================================================================\n";
