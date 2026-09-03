<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Services\AI\DTOs\AnswerabilityDecision;
use App\Services\AI\Enums\AnswerabilityStatus;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pre-Generation Semantic Answerability Gate (Phase 3).
 *
 * Core Operating Principle: "Retrieval Score ≠ Answerability".
 * Deterministically evaluates evidence sufficiency, router state, domain alignment,
 * and top-2 margin before authorizing grounded knowledge answering.
 *
 * Execution time: < 1ms (Zero LLM, embedding, or external network dependencies).
 */
class SemanticAnswerabilityGate
{
    /**
     * Multilingual commerce domain concept signals (EN, Native Bangla, Banglish, Code-mixed).
     */
    private const COMMERCE_CONCEPT_PATTERN = '/\b(return|ferot|ফেরত|refund|রিফান্ড|টাকা\s*ফেরত|delivery|shipping|ডেলিভারি|শিপিং|courier|কুরিয়ার|কুরিয়ার|charge|fee|চার্জ|ফি|খরচ|payment|pay|টাকা|পেমেন্ট|cod|cash\s*on\s*delivery|বিকাশ|bkash|nagad|card|কার্ড|রকেট|rocket|warranty|guarantee|ওয়ারেন্টি|ওয়ারেন্টি|গ্যারান্টি|defect|broken|ভাঙা|নষ্ট|ছিঁড়া|selai|sewing|বোতাম|exchange|replace|বদলানো|পরিবর্তন|size|সাইজ|fitting|fit|মাপ|chest|track|tracking|ট্র্যাক|ট্র্যাকিং|order|অর্ডার|parcel|পার্সেল|cancel|বাতিল|ক্যানসেল|invoice|বিল|ইনভয়েস|ইনভয়েস|মেমো|memo|terms|শর্ত|privacy|গোপনীয়তা|গোপনীয়তা|contact|যোগাযোগ|support|হেল্প|help|discount|promo|কুপন|coupon|voucher|price|dam|taka|tk|দাম|মূল্য|offer|অফার|password|reset|login|signin|signup|register|account|store|hours|outlet|branch|location|address|open|close|পাসওয়ার্ড|লগইন|অ্যাকাউন্ট|দোকান|শাখা|ঠিকানা|সময়)\b/ui';

    /**
     * Pattern matching underspecified / generic fee questions without domain anchor.
     */
    private const AMBIGUOUS_FEE_PATTERN = '/^(\s*|\b)(how\s+much\s+is\s+the\s+fee|what\s+is\s+the\s+fee|fee\s+koto|charge\s+koto|service\s+fee\s+koto|চার্জ\s+কত|ফি\s+কত|কত\s+চার্জ|কত\s+ফি|koto\s+charge|koto\s+fee)(\s*|\?|\.)$/ui';

    /**
     * Evaluate retrieval results against answerability criteria.
     *
     * @param string             $query Raw user query
     * @param Collection         $retrievalHits Typesense hybrid hits
     * @param RoutingResult|null $routingResult Output of HybridRouter
     * @return AnswerabilityDecision
     */
    public function evaluate(
        string $query,
        Collection $retrievalHits,
        ?RoutingResult $routingResult = null,
    ): AnswerabilityDecision {
        $cleanQuery = trim($query);

        // 1. Authoritative Hard Safety Barrier: Router OOD Flag
        if ($this->isRouterOod($routingResult)) {
            return new AnswerabilityDecision(
                status: AnswerabilityStatus::UNANSWERABLE,
                groundedHits: new Collection(),
                confidenceScore: 0.0,
                reasons: [
                    'rule'             => 'router_ood_hard_block',
                    'router_route'     => $routingResult?->route->value ?? 'unknown',
                    'authorized'       => false,
                ],
            );
        }

        // 2. Pre-Retrieval Ambiguity Detection (e.g. standalone "How much is the fee?")
        if ($this->detectAmbiguity($cleanQuery)) {
            return new AnswerabilityDecision(
                status: AnswerabilityStatus::AMBIGUOUS,
                groundedHits: new Collection(),
                confidenceScore: 0.35,
                reasons: [
                    'rule'             => 'ambiguous_query_detection',
                    'ambiguous_type'   => 'underspecified_fee',
                    'authorized'       => false,
                    'suggested_action' => 'clarify',
                ],
            );
        }

        $topHit = $retrievalHits->first();
        if ($topHit === null) {
            return new AnswerabilityDecision(
                status: AnswerabilityStatus::UNANSWERABLE,
                groundedHits: new Collection(),
                confidenceScore: 0.0,
                reasons: [
                    'rule'       => 'zero_retrieval_hits',
                    'authorized' => false,
                ],
            );
        }

        $topScore = (float) $topHit->finalScore;
        $secondScore = $retrievalHits->count() > 1 ? (float) ($retrievalHits->get(1)->finalScore ?? 0.0) : 0.0;
        $margin = round($topScore - $secondScore, 4);

        $hasCommerceIntent = $this->detectCommerceAlignment($cleanQuery);
        $hasLexicalAlignment = $this->hasLexicalAlignmentWithDocument($cleanQuery, $topHit->faq ?? null);
        $hasRetailIntent = $hasCommerceIntent || $hasLexicalAlignment;

        // 3. Second Safety Barrier: Off-Topic / Non-Commerce Queries
        // E.g., "Python code for quick sort" or "Mirpur bus route" where router slipped to knowledge
        if (!$hasRetailIntent && $topScore < 0.65) {
            return new AnswerabilityDecision(
                status: AnswerabilityStatus::UNANSWERABLE,
                groundedHits: new Collection(),
                confidenceScore: 0.0,
                reasons: [
                    'rule'             => 'non_commerce_off_topic_guard',
                    'has_commerce'     => false,
                    'has_alignment'    => false,
                    'top_score'        => $topScore,
                    'top_doc_type'     => $topHit->faq?->document_type ?? 'none',
                    'authorized'       => false,
                ],
            );
        }

        // 4. Evidence Sufficiency & Margin Verification
        $isSufficient = ($topScore >= 0.45 && $hasRetailIntent)
            || ($topScore >= 0.38 && $margin >= 0.05 && $hasRetailIntent);

        if ($isSufficient) {
            $groundedHits = $this->filterGroundedHits($retrievalHits);

            // Composite confidence score (distinct from raw retrieval similarity score)
            $compositeConfidence = round(min(1.0, 0.50 + ($topScore * 0.40) + ($margin * 0.10)), 4);

            return new AnswerabilityDecision(
                status: AnswerabilityStatus::CONFIDENT,
                groundedHits: $groundedHits,
                confidenceScore: $compositeConfidence,
                reasons: [
                    'rule'             => 'evidence_sufficient',
                    'top_score'        => $topScore,
                    'second_score'     => $secondScore,
                    'margin'           => $margin,
                    'has_commerce'     => $hasCommerceIntent,
                    'authorized'       => true,
                ],
            );
        }

        // 5. Insufficient Evidence Fallback
        return new AnswerabilityDecision(
            status: AnswerabilityStatus::UNANSWERABLE,
            groundedHits: new Collection(),
            confidenceScore: round($topScore * 0.50, 4),
            reasons: [
                'rule'             => 'insufficient_evidence',
                'top_score'        => $topScore,
                'margin'           => $margin,
                'authorized'       => false,
            ],
        );
    }

    /**
     * Determine if HybridRouter flagged the query as Out-Of-Domain.
     */
    public function isRouterOod(?RoutingResult $routingResult): bool
    {
        return $routingResult !== null && $routingResult->route === RouteType::OOD;
    }

    /**
     * Detect if a query is underspecified / ambiguous across commerce domains.
     */
    public function detectAmbiguity(string $query): bool
    {
        return (bool) preg_match(self::AMBIGUOUS_FEE_PATTERN, $query);
    }

    /**
     * Detect if the query expresses a commerce domain concept.
     */
    public function detectCommerceAlignment(string $query): bool
    {
        return (bool) preg_match(self::COMMERCE_CONCEPT_PATTERN, $query);
    }

    /**
     * Filter retrieval hits to include strictly validated evidence for the Knowledge Support Agent.
     */
    public function filterGroundedHits(Collection $retrievalHits): Collection
    {
        $topHit = $retrievalHits->first();
        if ($topHit === null) {
            return new Collection();
        }

        $topScore = (float) $topHit->finalScore;

        // Grounded hits must have individual scores within 0.10 of top hit and >= 0.38
        return $retrievalHits->filter(function ($hit) use ($topScore) {
            $score = (float) $hit->finalScore;
            return ($score >= 0.45) || ($score >= 0.38 && ($topScore - $score) <= 0.10);
        });
    }

    /**
     * Check if query shares meaningful content tokens with the retrieved FAQ title/question.
     */
    public function hasLexicalAlignmentWithDocument(string $query, ?object $faq): bool
    {
        if ($faq === null) {
            return false;
        }

        $qLower = mb_strtolower($query);
        $faqTitle = mb_strtolower((string) ($faq->question ?? ''));
        if ($faqTitle === '') {
            return false;
        }

        $qTokens = preg_split('/[\s\p{P}]+/u', $qLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $titleTokens = preg_split('/[\s\p{P}]+/u', $faqTitle, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stopWords = [
            'the', 'and', 'for', 'are', 'what', 'how', 'you', 'can', 'with', 'from',
            'this', 'that', 'your', 'our', 'have', 'does', 'doing', 'where', 'when',
            'কি', 'কিভাবে', 'কোন', 'থেকে', 'হলে', 'করব', 'করতে', 'পারি', 'হবে',
        ];

        $meaningfulQ = array_filter($qTokens, fn ($t) => mb_strlen($t) >= 3 && !in_array($t, $stopWords, true));
        $meaningfulTitle = array_filter($titleTokens, fn ($t) => mb_strlen($t) >= 3 && !in_array($t, $stopWords, true));

        $overlap = array_intersect($meaningfulQ, $meaningfulTitle);

        return count($overlap) >= 1;
    }
}
