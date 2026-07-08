<?php

namespace App\Services\FAQ;

use App\Models\FAQ;

class FAQScoreCalculator
{
    /**
     * Weight configuration for each scoring factor.
     * All weights must sum to 1.0.
     */
    private const WEIGHT_SEMANTIC   = 0.30;
    private const WEIGHT_KEYWORD    = 0.30;
    private const WEIGHT_PRIORITY   = 0.15;
    private const WEIGHT_POPULARITY = 0.10;
    private const WEIGHT_EXACT      = 0.15;

    /**
     * Exact match bonus when the query matches the question exactly.
     */
    private const EXACT_MATCH_BOOST = 1.0;

    /**
     * Partial exact match boost when query is contained in the question.
     */
    private const PARTIAL_MATCH_BOOST = 0.5;

    /**
     * Max hit_count across all FAQs — used for popularity normalization.
     * Set dynamically if null (lazy-loaded from DB).
     */
    private static ?int $maxHitCount = null;

    /**
     * Max priority value for normalization.
     */
    private static ?int $maxPriority = null;

    /**
     * Calculate the final score for a single FAQ.
     *
     * All input scores are expected in the 0–1 range.
     *
     * @param FAQ    $faq              The FAQ to score.
     * @param float  $semanticScore    Semantic similarity from vector search (0–1).
     * @param float  $keywordScore     Keyword relevance from full-text search (0–1).
     * @param string $rawQuery         The original user query (for exact match detection).
     * @return float  Final score between 0 and 100.
     */
    public function calculate(
        FAQ $faq,
        float $semanticScore,
        float $keywordScore,
        string $rawQuery,
    ): float {
        // Clamp all input scores to 0–1
        $semanticScore = $this->clamp($semanticScore);
        $keywordScore  = $this->clamp($keywordScore);

        // 1. Semantic similarity
        $semantic = $semanticScore * self::WEIGHT_SEMANTIC;

        // 2. Keyword relevance
        $keyword = $keywordScore * self::WEIGHT_KEYWORD;

        // 3. FAQ priority (normalized 0–1)
        $priorityNormalized = $this->normalizePriority($faq->priority);
        $priority = $priorityNormalized * self::WEIGHT_PRIORITY;

        // 4. FAQ popularity (normalized 0–1 from hit_count)
        $popularityNormalized = $this->normalizePopularity($faq->hit_count);
        $popularity = $popularityNormalized * self::WEIGHT_POPULARITY;

        // 5. Exact match bonus
        $exactBonus = $this->computeExactMatchBonus($rawQuery, $faq->question);
        $exact = $exactBonus * self::WEIGHT_EXACT;

        // Aggregate raw score (0–1 range)
        $rawTotal = $semantic + $keyword + $priority + $popularity + $exact;

        // Scale to 0–100 and clamp
        return round($this->clamp($rawTotal) * 100, 2);
    }

    /**
     * Batch-calculate scores for multiple FAQs.
     *
     * @param array<int, array{semantic?: float, keyword?: float}> $scores  Map of faq_id => scores.
     * @param string $rawQuery
     * @param \Illuminate\Support\Collection<int, FAQ> $faqs
     * @return array<int, float>  Map of faq_id => final score.
     */
    public function calculateBatch(
        iterable $faqs,
        array $scores,
        string $rawQuery,
    ): array {
        $results = [];

        foreach ($faqs as $faq) {
            $id = $faq->id;
            $row = $scores[$id] ?? [];

            $results[$id] = $this->calculate(
                faq: $faq,
                semanticScore: $row['semantic'] ?? 0.0,
                keywordScore: $row['keyword'] ?? 0.0,
                rawQuery: $rawQuery,
            );
        }

        return $results;
    }

    // ─── Normalization ────────────────────────────────────────────────────

    /**
     * Normalize FAQ priority to a 0–1 scale.
     *
     * Uses the max priority in the database as the denominator.
     */
    public function normalizePriority(int $priority): float
    {
        if ($priority <= 0) {
            return 0.0;
        }

        $max = $this->getMaxPriority();

        if ($max <= 0) {
            return 0.0;
        }

        return min($priority / $max, 1.0);
    }

    /**
     * Normalize FAQ popularity (hit_count) to a 0–1 scale.
     *
     * Uses logarithmic scaling to prevent a single very popular FAQ
     * from dominating the score.
     */
    public function normalizePopularity(int $hitCount): float
    {
        if ($hitCount <= 0) {
            return 0.0;
        }

        $max = $this->getMaxHitCount();

        if ($max <= 0) {
            return 0.0;
        }

        // Log scaling: log(1 + hits) / log(1 + max_hits)
        return log(1 + $hitCount) / log(1 + $max);
    }

    /**
     * Compute an exact-match bonus based on how closely the query matches the question.
     *
     * @return float 0–1 where 1 = perfect match.
     */
    public function computeExactMatchBonus(string $query, string $question): float
    {
        $query    = $this->normalizeForComparison($query);
        $question = $this->normalizeForComparison($question);

        if ($query === '' || $question === '') {
            return 0.0;
        }

        // Perfect match
        if ($query === $question) {
            return self::EXACT_MATCH_BOOST;
        }

        // Query is fully contained in the question
        if (str_contains($question, $query)) {
            return self::PARTIAL_MATCH_BOOST;
        }

        // Word overlap ratio
        $queryWords    = array_unique(str_word_count($query, 1));
        $questionWords = array_unique(str_word_count($question, 1));

        if (empty($queryWords) || empty($questionWords)) {
            return 0.0;
        }

        $matchCount = count(array_intersect($queryWords, $questionWords));
        $overlap    = $matchCount / count($queryWords);

        // Scale overlap (0–1) to a smaller bonus
        return $overlap * 0.3;
    }

    // ─── Internal ─────────────────────────────────────────────────────────

    /**
     * Clamp a float value between 0 and 1.
     */
    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /**
     * Normalize a string for case-insensitive comparison.
     */
    private function normalizeForComparison(string $text): string
    {
        return trim(mb_strtolower(strip_tags($text), 'UTF-8'));
    }

    /**
     * Get the max priority across all FAQs.
     */
    private function getMaxPriority(): int
    {
        if (self::$maxPriority === null) {
            self::$maxPriority = (int) FAQ::max('priority');
        }

        return self::$maxPriority;
    }

    /**
     * Get the max hit_count across all FAQs.
     */
    private function getMaxHitCount(): int
    {
        if (self::$maxHitCount === null) {
            self::$maxHitCount = (int) FAQ::max('hit_count');
        }

        return self::$maxHitCount;
    }

    /**
     * Reset cached max values (useful for testing).
     */
    public static function resetCache(): void
    {
        self::$maxHitCount = null;
        self::$maxPriority = null;
    }
}
