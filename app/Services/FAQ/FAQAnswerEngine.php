<?php

namespace App\Services\FAQ;

use App\Models\KnowledgeSearchLog;
use App\Models\UnansweredQuestion;
use App\Services\NLP\TextPreprocessor;
use Illuminate\Support\Facades\Log;

class FAQAnswerEngine
{
    /**
     * Default minimum confidence threshold (0–100) to return an answer.
     */
    private const DEFAULT_THRESHOLD = 50.0;

    /**
     * How many top results to fetch from the search layer for scoring.
     */
    private const SEARCH_TOP_K = 5;

    public function __construct(
        private readonly TextPreprocessor $preprocessor,
        private readonly FAQSearch $search,
        private readonly FAQScoreCalculator $calculator,
    ) {}

    /**
     * Run the full answer pipeline.
     *
     * @param string   $query         The raw customer query.
     * @param int|null $workspaceId   Workspace to scope the search.
     * @param int|null $conversationId  Optional conversation for logging.
     * @param int|null $messageId     Optional message for logging.
     * @param float    $threshold     Minimum confidence to return answer (0–100).
     * @return FAQAnswerResult
     */
    public function answer(
        string $query,
        ?int $workspaceId = null,
        ?int $conversationId = null,
        ?int $messageId = null,
        float $threshold = self::DEFAULT_THRESHOLD,
    ): FAQAnswerResult {
        $startTime = microtime(true);

        // ── 1. Preprocess ────────────────────────────────────────────────
        $processed = $this->preprocessor->process($query, language: 'en');
        $normalized = $processed->normalized;
        $keywordTokens = $processed->keyword;

        Log::info('[FAQAnswerEngine] Processing query', [
            'original'     => mb_substr($query, 0, 120),
            'normalized'   => mb_substr($normalized, 0, 120),
            'workspace_id' => $workspaceId,
        ]);

        if (trim($normalized) === '') {
            $elapsed = (microtime(true) - $startTime) * 1000;

            Log::warning('[FAQAnswerEngine] Empty query after normalization', [
                'original' => mb_substr($query, 0, 120),
            ]);

            return $this->buildResult(
                faq: null,
                keywordScore: 0,
                semanticScore: 0,
                matchType: 'none',
                responseTimeMs: $elapsed,
            );
        }

        // ── 2. Search ────────────────────────────────────────────────────
        $results = $this->search->search(
            query: $normalized,
            perPage: self::SEARCH_TOP_K,
            workspaceId: $workspaceId,
        );

        // ── 3. Score & pick best ─────────────────────────────────────────
        $bestFaq = null;
        $bestScore = 0.0;
        $bestKeywordScore = 0.0;
        $bestSemanticScore = 0.0;
        $bestMatchType = 'none';

        foreach ($results as $result) {
            $finalScore = $this->calculator->calculate(
                faq: $result->faq,
                semanticScore: $result->semanticScore,
                keywordScore: $result->keywordScore,
                rawQuery: $query,
            );

            if ($finalScore > $bestScore) {
                $bestScore = $finalScore;
                $bestFaq = $result->faq;
                $bestKeywordScore = $result->keywordScore;
                $bestSemanticScore = $result->semanticScore;
                $bestMatchType = $result->matchType;
            }
        }

        $elapsed = (microtime(true) - $startTime) * 1000;

        // ── 4. Threshold check ───────────────────────────────────────────
        $answered = $bestFaq !== null && $bestScore >= $threshold;

        // ── 5. Log search event ──────────────────────────────────────────
        $this->logSearchEvent(
            workspaceId: $workspaceId,
            conversationId: $conversationId,
            messageId: $messageId,
            query: $query,
            matchedFaqId: $bestFaq?->id,
            keywordScore: $bestKeywordScore,
            semanticScore: $bestSemanticScore,
            finalScore: $bestScore,
            responseTimeMs: $elapsed,
            answerSource: $answered ? ($bestMatchType === 'hybrid' ? 'faq_match' : $bestMatchType) : 'none',
        );

        // ── 6. Handle unanswered ─────────────────────────────────────────
        if (! $answered) {
            $this->saveUnanswered(
                workspaceId: $workspaceId,
                conversationId: $conversationId,
                originalQuery: $query,
                normalizedQuery: $normalized,
            );

            Log::info('[FAQAnswerEngine] No answer found', [
                'query'      => mb_substr($query, 0, 120),
                'best_score' => round($bestScore, 2),
                'threshold'  => $threshold,
            ]);
        } else {
            // Record usage on the matched FAQ
            try {
                $bestFaq?->recordUsage();
            } catch (\Throwable $e) {
                Log::warning('[FAQAnswerEngine] Failed to record FAQ usage', [
                    'faq_id' => $bestFaq?->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            Log::info('[FAQAnswerEngine] Answer found', [
                'query'        => mb_substr($query, 0, 120),
                'faq_id'       => $bestFaq?->id,
                'confidence'   => round($bestScore, 2),
                'threshold'    => $threshold,
                'response_ms'  => round($elapsed, 2),
            ]);
        }

        return $this->buildResult(
            faq: $bestFaq,
            keywordScore: $bestKeywordScore,
            semanticScore: $bestSemanticScore,
            matchType: $answered ? $bestMatchType : 'none',
            responseTimeMs: $elapsed,
            confidence: $bestScore,
            answered: $answered,
        );
    }

    // ─── Internal ─────────────────────────────────────────────────────────

    /**
     * Log the search event to knowledge_search_logs.
     */
    private function logSearchEvent(
        ?int $workspaceId,
        ?int $conversationId,
        ?int $messageId,
        string $query,
        ?string $matchedFaqId,
        float $keywordScore,
        float $semanticScore,
        float $finalScore,
        float $responseTimeMs,
        string $answerSource,
    ): void {
        try {
            KnowledgeSearchLog::create([
                'workspace_id'    => $workspaceId ?? 0,
                'conversation_id' => $conversationId ?? 0,
                'message_id'      => $messageId ?? 0,
                'customer_query'  => $query,
                'matched_faq_id'  => $matchedFaqId,
                'keyword_score'   => $keywordScore,
                'semantic_score'  => $semanticScore,
                'final_score'     => $finalScore,
                'response_time_ms'=> (int) round($responseTimeMs),
                'answer_source'   => $answerSource,
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[FAQAnswerEngine] Failed to log search event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Save or increment an unanswered question record.
     */
    private function saveUnanswered(
        ?int $workspaceId,
        ?int $conversationId,
        string $originalQuery,
        string $normalizedQuery,
    ): void {
        try {
            $existing = UnansweredQuestion::where('workspace_id', $workspaceId ?? 0)
                ->where('original_question', $originalQuery)
                ->where('status', 'pending')
                ->first();

            if ($existing) {
                $existing->incrementOccurrence();
                Log::debug('[FAQAnswerEngine] Incremented unanswered question count', [
                    'id'       => $existing->id,
                    'count'    => $existing->fresh()->occurrence_count,
                ]);
            } else {
                UnansweredQuestion::create([
                    'workspace_id'       => $workspaceId ?? 0,
                    'conversation_id'    => $conversationId ?? 0,
                    'original_question'  => $originalQuery,
                    'normalized_question' => $normalizedQuery,
                    'occurrence_count'   => 1,
                    'status'             => 'pending',
                    'notes'              => null,
                ]);

                Log::debug('[FAQAnswerEngine] Saved new unanswered question');
            }
        } catch (\Throwable $e) {
            Log::error('[FAQAnswerEngine] Failed to save unanswered question', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the result DTO.
     */
    private function buildResult(
        ?object $faq,
        float $keywordScore,
        float $semanticScore,
        string $matchType,
        float $responseTimeMs,
        float $confidence = 0.0,
        bool $answered = false,
    ): FAQAnswerResult {
        return new FAQAnswerResult(
            faq: $faq,
            confidence: $confidence,
            answered: $answered,
            keywordScore: $keywordScore,
            semanticScore: $semanticScore,
            matchType: $matchType,
            responseTimeMs: $responseTimeMs,
        );
    }
}
