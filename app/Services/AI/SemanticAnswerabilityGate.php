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
    private const COMMERCE_CONCEPT_PATTERN = '/(?:^|[^\p{L}\p{N}])(return|ferot|ফেরত|refund|রিফান্ড|টাকা\s*ফেরত|delivery|shipping|ডেলিভারি|শিপিং|courier|কুরিয়ার|কুরিয়ার|charge|fee|চার্জ|ফি|খরচ|payment|pay|টাকা|পেমেন্ট|cod|cash\s*on\s*delivery|বিকাশ|bkash|nagad|card|কার্ড|রকেট|rocket|warranty|guarantee|ওয়ারেন্টি|ওয়ারেন্টি|গ্যারান্টি|defect|broken|ভাঙা|নষ্ট|ছিঁড়া|selai|sewing|বোতাম|exchange|replace|বদলানো|পরিবর্তন|size|সাইজ|fitting|fit|মাপ|chest|track|tracking|ট্র্যাক|ট্র্যাকিং|order|অর্ডার|parcel|পার্সেল|cancel|বাতিল|ক্যানসেল|invoice|বিল|ইনভয়েস|ইনভয়েস|মেমো|memo|terms|শর্ত|privacy|গোপনীয়তা|গোপনীয়তা|contact|যোগাযোগ|support|হেল্প|help|discount|promo|কুপন|coupon|voucher|price|dam|taka|tk|দাম|মূল্য|offer|অফার|password|reset|login|signin|signup|register|account|akaunt|accaunt|acount|kulbo|store|hours|outlet|branch|location|address|open|close|পাসওয়ার্ড|লগইন|অ্যাকাউন্ট|একাউন্ট|দোকান|শাখা|ঠিকানা|সময়)(?:$|[^\p{L}\p{N}])/ui';

    /**
     * Pattern matching underspecified / generic fee questions without domain anchor.
     */
    private const AMBIGUOUS_FEE_PATTERN = '/^(\s*|\b)(how\s+much\s+is\s+the\s+fee|what\s+is\s+the\s+fee|fee\s+koto|charge\s+koto|service\s+fee\s+koto|চার্জ\s+কত|ফি\s+কত|কত\s+চার্জ|কত\s+ফি|koto\s+charge|koto\s+fee)(\s*|\?|\.)$/ui';

    /**
     * Evaluate retrieval results against answerability criteria.
     *
     * @param string             $query         Raw user query
     * @param Collection         $retrievalHits Typesense hybrid hits
     * @param RoutingResult|null $routingResult Output of HybridRouter
     * @param int|null           $workspaceId   Multi-tenant workspace ID (fail-closed guard)
     * @return AnswerabilityDecision
     */
    public function evaluate(
        string $query,
        Collection $retrievalHits,
        ?RoutingResult $routingResult = null,
        ?int $workspaceId = null,
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

        // 2. Tenant Context Fail-Closed Guard (Criterion 7)
        // Missing (null) or invalid (<= 0) workspace context must fail closed
        if ($workspaceId === null || $workspaceId <= 0) {
            return new AnswerabilityDecision(
                status: AnswerabilityStatus::UNANSWERABLE,
                groundedHits: new Collection(),
                confidenceScore: 0.0,
                reasons: [
                    'rule'             => 'invalid_workspace_context',
                    'workspace_id'     => $workspaceId,
                    'authorized'       => false,
                ],
            );
        }

        // 3. Pre-Retrieval Ambiguity Detection (e.g. standalone "How much is the fee?")
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

        // 4. Hit-Level Tenant Boundary Guard (Criterion 7)
        // Fail-closed if ANY candidate hit belongs to a different workspace
        foreach ($retrievalHits as $hit) {
            if ($hit->faq !== null && isset($hit->faq->workspace_id)) {
                $docWsId = (int) $hit->faq->workspace_id;
                if ($docWsId > 0 && $docWsId !== $workspaceId) {
                    return new AnswerabilityDecision(
                        status: AnswerabilityStatus::UNANSWERABLE,
                        groundedHits: new Collection(),
                        confidenceScore: 0.0,
                        reasons: [
                            'rule'             => 'tenant_isolation_violation',
                            'workspace_id'     => $workspaceId,
                            'doc_workspace_id' => $docWsId,
                            'authorized'       => false,
                        ],
                    );
                }
            }
        }

        $topScore = (float) $topHit->finalScore;
        $hasSecondCandidate = $retrievalHits->count() > 1;
        $secondHit = $hasSecondCandidate ? $retrievalHits->get(1) : null;
        $secondScore = $secondHit !== null ? (float) ($secondHit->finalScore ?? 0.0) : 0.0;

        // Margin separation between the top two raw hits
        $margin = $hasSecondCandidate ? round($topScore - $secondScore, 4) : 0.0;
        $isNearlyTied = $hasSecondCandidate && ($margin < 0.03);

        // Freshness & Authoritative Source Handling:
        // An active topHit must not be blocked by an archived/superseded candidate hit.
        $topIsActive = !$this->isArchivedDocument($topHit);
        $secondIsArchived = $hasSecondCandidate && $this->isArchivedDocument($secondHit);

        // Corroboration & Contradiction Evaluation:
        $hasContradiction = $hasSecondCandidate && $this->hasContradictionWithTopHit($topHit, $secondHit);
        $isCorroborating = $hasSecondCandidate && !$hasContradiction && $this->isCorroboratingEvidence($topHit, $secondHit);

        // Identify the first non-corroborating competitor candidate
        $firstCompetitor = null;
        if ($hasSecondCandidate) {
            foreach ($retrievalHits->slice(1) as $candidate) {
                // If top is active, ignore archived candidates as valid competitors
                if ($topIsActive && $this->isArchivedDocument($candidate)) {
                    continue;
                }
                if ($this->isCorroboratingEvidence($topHit, $candidate)) {
                    continue;
                }
                $firstCompetitor = $candidate;
                break;
            }
        }

        $hasCompetitor = $firstCompetitor !== null;
        $competitorScore = $hasCompetitor ? (float) ($firstCompetitor->finalScore ?? 0.0) : 0.0;
        $competitorMargin = $hasCompetitor ? round($topScore - $competitorScore, 4) : 0.0;

        $matchType = property_exists($topHit, 'matchType') ? (string) $topHit->matchType : 'hybrid';
        $isDegradedMode = in_array($matchType, ['bm25', 'keyword', 'keyword_fallback'], true);
        $isDenseEvaluated = !$isDegradedMode || (property_exists($topHit, 'semanticScore') && $topHit->semanticScore !== null && (float) $topHit->semanticScore > 0.0);
        $semanticScore = property_exists($topHit, 'semanticScore') && $topHit->semanticScore !== null
            ? (float) $topHit->semanticScore
            : ($matchType === 'vector' ? $topScore : null);

        // 5. Conflicting Retrieval Evidence Guard (Criterion 5)
        // A. Genuine factual contradiction between candidates (regardless of margin)
        if ($hasContradiction && $topScore < 0.85) {
            return new AnswerabilityDecision(
                status: AnswerabilityStatus::UNANSWERABLE,
                groundedHits: new Collection(),
                confidenceScore: round($topScore * 0.40, 4),
                reasons: [
                    'rule'             => 'conflicting_retrieval_evidence',
                    'conflict_type'    => 'factual_claim_contradiction',
                    'top_score'        => $topScore,
                    'second_score'     => $secondScore,
                    'margin'           => $margin,
                    'authorized'       => false,
                ],
            );
        }

        // B. Active vs Archived: Archived document cannot override or create conflict against active policy
        if ($topIsActive && $secondIsArchived) {
            // Archived candidate does not trigger conflict block against authoritative active document
        } elseif ($isCorroborating) {
            // Corroborating bilingual/paraphrase twin:
            // Check if there is an unseparated competitor candidate tied with the entity
            if ($hasCompetitor && $competitorMargin < 0.03 && $topScore < 0.65 && !$this->isBroadCatalogInquiry($cleanQuery)) {
                return new AnswerabilityDecision(
                    status: AnswerabilityStatus::UNANSWERABLE,
                    groundedHits: new Collection(),
                    confidenceScore: round($topScore * 0.40, 4),
                    reasons: [
                        'rule'             => 'conflicting_retrieval_evidence',
                        'conflict_type'    => 'unseparated_competing_candidate',
                        'top_score'        => $topScore,
                        'competitor_score' => $competitorScore,
                        'margin'           => $competitorMargin,
                        'authorized'       => false,
                    ],
                );
            }
        } elseif ($isNearlyTied && $topScore < 0.65 && !$this->isBroadCatalogInquiry($cleanQuery)) {
            // Genuine unseparated non-corroborating candidates (e.g. different products)
            return new AnswerabilityDecision(
                status: AnswerabilityStatus::UNANSWERABLE,
                groundedHits: new Collection(),
                confidenceScore: round($topScore * 0.40, 4),
                reasons: [
                    'rule'             => 'conflicting_retrieval_evidence',
                    'conflict_type'    => 'unseparated_rival_candidates',
                    'top_score'        => $topScore,
                    'second_score'     => $secondScore,
                    'margin'           => $margin,
                    'authorized'       => false,
                ],
            );
        }

        // 6. Semantic Meaning Mismatch Guard (Criterion 6)
        // When dense embedding was computed, low semantic similarity reveals that the query meaning
        // diverges from the document despite incidental lexical overlap.
        if ($isDenseEvaluated && $semanticScore !== null && $semanticScore < 0.35) {
            return new AnswerabilityDecision(
                status: AnswerabilityStatus::UNANSWERABLE,
                groundedHits: new Collection(),
                confidenceScore: 0.0,
                reasons: [
                    'rule'             => 'semantic_meaning_mismatch',
                    'semantic_score'   => $semanticScore,
                    'top_score'        => $topScore,
                    'match_type'       => $matchType,
                    'authorized'       => false,
                ],
            );
        }

        $hasCommerceIntent = $this->detectCommerceAlignment($cleanQuery);
        $hasLexicalAlignment = $this->hasLexicalAlignmentWithDocument($cleanQuery, $topHit->faq ?? null);
        $hasAlignedEvidence = $hasCommerceIntent || $hasLexicalAlignment;

        // High-confidence dense semantic retrieval (>= 0.65) does not require commerce keywords or word overlap.
        // Requires verified dense evaluation with explicit semantic score >= 0.65; never assume missing semanticScore = topScore.
        $isHighConfidenceSemantic = ($isDenseEvaluated && $semanticScore !== null && $semanticScore >= 0.65 && $topScore >= 0.65);

        // 7. Off-Topic / Non-Aligned Queries Guard
        // E.g., "Python code for quick sort" or "Mirpur bus route" where router slipped to knowledge.
        // Pure BM25 or keyword fallback matches MUST have aligned evidence.
        if (!$hasAlignedEvidence && !$isHighConfidenceSemantic) {
            return new AnswerabilityDecision(
                status: AnswerabilityStatus::UNANSWERABLE,
                groundedHits: new Collection(),
                confidenceScore: 0.0,
                reasons: [
                    'rule'             => 'non_commerce_off_topic_guard',
                    'has_commerce'     => $hasCommerceIntent,
                    'has_alignment'    => $hasLexicalAlignment,
                    'top_score'        => $topScore,
                    'match_type'       => $matchType,
                    'top_doc_type'     => $topHit->faq?->document_type ?? 'none',
                    'authorized'       => false,
                ],
            );
        }

        // 8. Evidence Sufficiency & Margin Verification (Domain-Neutral)
        // - High-confidence semantic retrieval (>= 0.65) does not require commerce keywords.
        // - Moderate confidence (>= 0.45) is accepted with aligned evidence (lexical or domain) and separation from competitors.
        // - Corroborated evidence (>= 0.38) is accepted with aligned evidence and separation from competitors.
        // - Marginal scores (0.38 - 0.44) require validated margin separation (>= 0.05) from competitors and alignment.
        $isModerateSeparated = ($topScore >= 0.45 && $hasAlignedEvidence && (!$hasCompetitor || $competitorMargin >= 0.03 || $this->isBroadCatalogInquiry($cleanQuery)));
        $isMarginalSeparated = ($topScore >= 0.38 && $competitorMargin >= 0.05 && $hasAlignedEvidence);
        $isCorroboratedSufficient = ($isCorroborating && $topScore >= 0.38 && $hasAlignedEvidence && (!$hasCompetitor || $competitorMargin >= 0.03 || $this->isBroadCatalogInquiry($cleanQuery)));

        $isSufficient = $isHighConfidenceSemantic || $isModerateSeparated || $isMarginalSeparated || $isCorroboratedSufficient;

        if ($isSufficient) {
            $groundedHits = $this->filterGroundedHits($retrievalHits, $workspaceId);

            $effectiveMargin = $isCorroborating ? $competitorMargin : $margin;
            $compositeConfidence = round(min(1.0, 0.50 + ($topScore * 0.40) + ($effectiveMargin * 0.10)), 4);

            return new AnswerabilityDecision(
                status: AnswerabilityStatus::CONFIDENT,
                groundedHits: $groundedHits,
                confidenceScore: $compositeConfidence,
                reasons: [
                    'rule'             => ($isCorroborating && $isNearlyTied) ? 'evidence_sufficient_corroborated' : 'evidence_sufficient',
                    'top_score'        => $topScore,
                    'second_score'     => $secondScore,
                    'margin'           => $margin,
                    'competitor_margin'=> $competitorMargin,
                    'is_corroborating' => $isCorroborating,
                    'has_commerce'     => $hasCommerceIntent,
                    'has_alignment'    => $hasLexicalAlignment,
                    'authorized'       => true,
                ],
            );
        }

        // 9. Insufficient Evidence Fallback
        return new AnswerabilityDecision(
            status: AnswerabilityStatus::UNANSWERABLE,
            groundedHits: new Collection(),
            confidenceScore: round($topScore * 0.50, 4),
            reasons: [
                'rule'             => 'insufficient_evidence',
                'top_score'        => $topScore,
                'margin'           => $margin,
                'competitor_margin'=> $competitorMargin,
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
    public function filterGroundedHits(Collection $retrievalHits, ?int $workspaceId = null): Collection
    {
        $topHit = $retrievalHits->first();
        if ($topHit === null) {
            return new Collection();
        }

        $topScore = (float) $topHit->finalScore;
        $topFaq = $topHit->faq ?? $topHit;
        $topIsActive = !$this->isArchivedDocument($topFaq);

        return $retrievalHits->filter(function ($hit) use ($topScore, $topFaq, $topIsActive, $workspaceId) {
            // 1. Hit-Level Tenant Boundary Guard
            if ($workspaceId !== null && isset($hit->faq?->workspace_id)) {
                $docWsId = (int) $hit->faq->workspace_id;
                if ($docWsId > 0 && $docWsId !== $workspaceId) {
                    return false;
                }
            }

            $candFaq = $hit->faq ?? $hit;

            // 2. Archived documents cannot be grounded evidence when top hit is active
            if ($topIsActive && $this->isArchivedDocument($candFaq)) {
                return false;
            }

            // 3. Factual contradictions cannot be grounded evidence
            if ($this->hasNumericContradiction($topFaq, $candFaq)) {
                return false;
            }

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
        $faqAnswer = mb_strtolower((string) ($faq->answer ?? ''));
        $combinedDoc = trim($faqTitle . ' ' . $faqAnswer);
        if ($combinedDoc === '') {
            return false;
        }

        $qTokens = preg_split('/[\s\p{P}]+/u', $qLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $docTokens = preg_split('/[\s\p{P}]+/u', $combinedDoc, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $stopWords = [
            'the', 'and', 'for', 'are', 'what', 'how', 'you', 'can', 'with', 'from',
            'this', 'that', 'your', 'our', 'have', 'does', 'doing', 'where', 'when',
            'কি', 'কিভাবে', 'কোন', 'থেকে', 'হলে', 'করব', 'করতে', 'পারি', 'হবে',
        ];

        $meaningfulQ = array_filter($qTokens, fn ($t) => mb_strlen($t) >= 3 && !in_array($t, $stopWords, true));
        $meaningfulDoc = array_filter($docTokens, fn ($t) => mb_strlen($t) >= 3 && !in_array($t, $stopWords, true));

        // Exact token overlap
        $overlap = array_intersect($meaningfulQ, $meaningfulDoc);
        if (count($overlap) >= 1) {
            return true;
        }

        // Bengali definitive/inflectional suffix stemming overlap
        $suffixes = ['ের', 'তে', 'এ', 'র', 'কে', 'গুলো', 'টার', 'টির', 'টা', 'টি'];
        foreach ($meaningfulQ as $qt) {
            foreach ($suffixes as $suf) {
                if (str_ends_with($qt, $suf)) {
                    $stem = mb_substr($qt, 0, mb_strlen($qt) - mb_strlen($suf));
                    if (mb_strlen($stem) >= 3 && in_array($stem, $meaningfulDoc, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Extract product SKU from FAQ question, answer, title, or properties.
     */
    public function extractSku(?object $faq): ?string
    {
        if ($faq === null) {
            return null;
        }

        if (isset($faq->sku) && !empty($faq->sku)) {
            return strtoupper(trim((string) $faq->sku));
        }

        $text = ($faq->question ?? '') . ' ' . ($faq->answer ?? '');

        // Pattern 1: Brand prefixed SKUs like NC-PAN-101, NC-POL-01, NC-JKT-505
        if (preg_match('/\b(NC-[A-Z0-9\-]+)\b/ui', $text, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        // Pattern 2: Explicit SKU: prefix e.g. "SKU: PROD-101"
        if (preg_match('/\bSKU:\s*([A-Za-z0-9\-]+)\b/ui', $text, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        // Pattern 3: Standard uppercase alphanumeric catalog codes like [A-Z]{2,4}-[0-9]{3,4}
        if (preg_match('/\b([A-Z]{2,4}-[A-Z0-9]{2,5}(?:-[0-9]{2,4})?)\b/u', $text, $matches)) {
            return strtoupper(trim($matches[1]));
        }

        return null;
    }

    /**
     * Extract normalized policy topic key from FAQ model or text.
     */
    public function extractPolicyTopic(?object $faq): ?string
    {
        if ($faq === null) {
            return null;
        }

        $docType = strtolower(trim((string) ($faq->document_type ?? '')));
        if (!empty($docType) && !in_array($docType, ['faq', 'none', 'general', 'product'], true)) {
            return match ($docType) {
                'return_policy', 'exchange_policy' => 'return_exchange',
                'delivery_policy', 'shipping_policy' => 'delivery_shipping',
                'refund_policy' => 'refund',
                'payment_policy' => 'payment',
                'warranty_policy' => 'warranty',
                'cancellation_policy' => 'cancellation',
                'terms', 'terms_and_conditions' => 'terms',
                'privacy_policy' => 'privacy',
                default => null,
            };
        }

        $text = mb_strtolower(($faq->question ?? '') . ' ' . ($faq->answer ?? ''));

        if (preg_match('/(return|ফেরত|exchange|বদলানো|পরিবর্তন)/u', $text)) {
            return 'return_exchange';
        }
        if (preg_match('/(payment|cod|cash\s*on\s*delivery|advance\s*(?:deposit|confirmation)?|অগ্রিম|পেমেন্ট|বিকাশ|bkash|nagad|card)/u', $text)) {
            return 'payment';
        }
        if (preg_match('/(delivery|shipping|ডেলিভারি|শিপিং|কুরিয়ার|কুরিয়ার)/u', $text)) {
            return 'delivery_shipping';
        }
        if (preg_match('/(refund|রিফান্ড|টাকা\s*ফেরত)/u', $text)) {
            return 'refund';
        }
        if (preg_match('/(warranty|guarantee|ওয়ারেন্টি|ওয়ারেন্টি)/u', $text)) {
            return 'warranty';
        }

        return null;
    }

    /**
     * Determine if two policy topics are related and non-conflicting clauses.
     */
    public function areCompatiblePolicyTopics(string $topicA, string $topicB): bool
    {
        if ($topicA === $topicB) {
            return true;
        }

        // Return and refund are closely related policy domains
        if (in_array($topicA, ['return_exchange', 'refund'], true) && in_array($topicB, ['return_exchange', 'refund'], true)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a hit is an archived, superseded, or inactive document.
     */
    public function isArchivedDocument(?object $hit): bool
    {
        if ($hit === null) {
            return false;
        }

        $faq = $hit->faq ?? $hit;

        // 1. Inactive boolean flag
        if (isset($faq->is_active) && $faq->is_active === false) {
            return true;
        }

        // 2. Lifecycle status (handles both string and BackedEnum)
        if (isset($faq->lifecycle_status)) {
            $statusVal = $faq->lifecycle_status instanceof \BackedEnum
                ? (string) $faq->lifecycle_status->value
                : (string) $faq->lifecycle_status;

            if (in_array(strtolower($statusVal), ['archived', 'pending_ingestion', 'superseded'], true)) {
                return true;
            }
        }

        // 3. Metadata flags
        if (isset($faq->metadata)) {
            $meta = is_array($faq->metadata) ? $faq->metadata : (is_string($faq->metadata) ? json_decode($faq->metadata, true) : []);
            if (is_array($meta)) {
                if (isset($meta['is_active_policy']) && $meta['is_active_policy'] === false) {
                    return true;
                }
                if (!empty($meta['superseded_by'])) {
                    return true;
                }
            }
        }

        // 4. Superseded or legacy keywords in title/question
        $text = (string) ($faq->question ?? $faq->title ?? '');
        if (preg_match('/\b(superseded|archived|legacy|সুপারসিড|বাতিলকৃত)\b/ui', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a hit is an active, authoritative document.
     */
    public function isActiveDocument(?object $hit): bool
    {
        return !$this->isArchivedDocument($hit);
    }

    /**
     * Normalize Bengali numerals into standard ASCII digits.
     */
    public function normalizeDigits(string $text): string
    {
        $bnDigits = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
        $enDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($bnDigits, $enDigits, $text);
    }

    /**
     * Extract structured numeric metrics (prices, days, fees, thresholds) from text.
     */
    public function extractFactualMetrics(string $text): array
    {
        $normalized = $this->normalizeDigits($text);
        $metrics = [
            'prices' => [],
            'days' => [],
            'fees' => [],
            'thresholds' => [],
        ];

        // 1. Currency & Prices: ৳3,450, ৳ 3450, 3450 টাকা, 3,450 taka, price 3450
        if (preg_match_all('/(?:৳|tk|taka|bdt|\$)\s*([0-9]+(?:,[0-9]{3})*)|([0-9]+(?:,[0-9]{3})*)\s*(?:টাকা|tk|taka|bdt)/ui', $normalized, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $raw = !empty($m[1]) ? $m[1] : $m[2];
                $val = (int) str_replace(',', '', $raw);
                if ($val > 0) {
                    if ($val >= 500) {
                        $metrics['prices'][] = $val;
                    } else {
                        $metrics['fees'][] = $val;
                    }
                }
            }
        }

        // 2. Day metrics: 7 calendar days, 7 days, ৭ দিন, 14 days, 3-5 days
        if (preg_match_all('/([0-9]+)(?:\s*(?:to|-)\s*([0-9]+))?\s*(?:calendar\s*days?|days?|business\s*days?|banking\s*days?|ক্যালেন্ডার\s*দিন|কার্যদিবস|ব্যাংকিং\s*দিন|দিনের|দিন)/ui', $normalized, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $metrics['days'][] = (int) $m[1];
                if (!empty($m[2])) {
                    $metrics['days'][] = (int) $m[2];
                }
            }
        }

        // 3. Thresholds: orders over ৳3,000, ৩,০০০ টাকা বা তার বেশি
        if (preg_match_all('/(?:over|above|exceeding|orders\s+of|অর্ডারে|বেশি|কমপক্ষে)\s*(?:৳|tk)?\s*([0-9]+(?:,[0-9]{3})*)/ui', $normalized, $matches)) {
            foreach ($matches[1] as $val) {
                $cleanVal = (int) str_replace(',', '', $val);
                if ($cleanVal > 0) {
                    $metrics['thresholds'][] = $cleanVal;
                }
            }
        }

        return $metrics;
    }

    /**
     * Check if two FAQ models or documents contain mutually contradictory numeric facts.
     */
    public function hasNumericContradiction(?object $faqA, ?object $faqB): bool
    {
        if ($faqA === null || $faqB === null) {
            return false;
        }

        $skuA = $this->extractSku($faqA);
        $skuB = $this->extractSku($faqB);

        // Different explicit SKUs are different items, not mutually contradictory facts
        if ($skuA !== null && $skuB !== null && $skuA !== $skuB) {
            return false;
        }

        $textA = ($faqA->question ?? '') . ' ' . ($faqA->answer ?? '');
        $textB = ($faqB->question ?? '') . ' ' . ($faqB->answer ?? '');

        $metricsA = $this->extractFactualMetrics($textA);
        $metricsB = $this->extractFactualMetrics($textB);

        // 1. Price contradiction check:
        // When both reference prices for the SAME product SKU, do they clash?
        if ($skuA !== null && $skuA === $skuB) {
            if (!empty($metricsA['prices']) && !empty($metricsB['prices'])) {
                $commonPrices = array_intersect($metricsA['prices'], $metricsB['prices']);
                if (empty($commonPrices)) {
                    return true;
                }
            }
        }

        // 2. Day count contradiction check:
        $topicA = $this->extractPolicyTopic($faqA);
        $topicB = $this->extractPolicyTopic($faqB);
        if ($topicA !== null && $topicA === $topicB && in_array($topicA, ['return_exchange', 'refund'], true) && !empty($metricsA['days']) && !empty($metricsB['days'])) {
            $primaryDayA = $metricsA['days'][0];
            $primaryDayB = $metricsB['days'][0];
            if ($primaryDayA !== $primaryDayB) {
                return true;
            }
        }

        // 3. Delivery fee contradiction check:
        if ($topicA !== null && $topicA === $topicB && ($topicA === 'delivery_shipping') && !empty($metricsA['fees']) && !empty($metricsB['fees'])) {
            $isExpressA = (bool) preg_match('/(express|সেম\s*ডে|একই\s*দিনে|urgent)/ui', $textA);
            $isExpressB = (bool) preg_match('/(express|সেম\s*ডে|একই\s*দিনে|urgent)/ui', $textB);
            if ($isExpressA !== $isExpressB) {
                // Different delivery tiers (Express vs Standard) are not contradictory
                return false;
            }
            $commonFees = array_intersect($metricsA['fees'], $metricsB['fees']);
            if (empty($commonFees)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if candidate hit has a genuine factual contradiction with the top hit.
     */
    public function hasContradictionWithTopHit(?object $topHit, ?object $candidate): bool
    {
        if ($topHit === null || $candidate === null) {
            return false;
        }

        $faqTop = $topHit->faq ?? $topHit;
        $faqCand = $candidate->faq ?? $candidate;

        // Archived documents cannot cause genuine contradiction with active top hits
        if (!$this->isArchivedDocument($faqTop) && $this->isArchivedDocument($faqCand)) {
            return false;
        }

        $skuTop = $this->extractSku($faqTop);
        $skuCand = $this->extractSku($faqCand);

        // If both specify SKUs:
        // - Same SKU: Check if prices/specs contradict (e.g. 3450 vs 2250)
        // - Different SKUs: They are different products, NOT in factual contradiction!
        if ($skuTop !== null && $skuCand !== null) {
            if ($skuTop === $skuCand) {
                return $this->hasNumericContradiction($faqTop, $faqCand);
            }
            return false;
        }

        $topicTop = $this->extractPolicyTopic($faqTop);
        $topicCand = $this->extractPolicyTopic($faqCand);

        // If same policy topic has contradictory numbers
        if ($topicTop !== null && $topicCand !== null && $topicTop === $topicCand) {
            return $this->hasNumericContradiction($faqTop, $faqCand);
        }

        return false;
    }

    /**
     * Determine if candidate B corroborates candidate A (same entity/policy and consistent facts).
     */
    public function isCorroboratingEvidence(?object $hitA, ?object $hitB): bool
    {
        if ($hitA === null || $hitB === null) {
            return false;
        }

        $faqA = $hitA->faq ?? $hitA;
        $faqB = $hitB->faq ?? $hitB;

        // An archived document cannot be treated as corroborating active evidence
        if (!$this->isArchivedDocument($faqA) && $this->isArchivedDocument($faqB)) {
            return false;
        }

        // 1. SKU / Product Check
        $skuA = $this->extractSku($faqA);
        $skuB = $this->extractSku($faqB);

        if ($skuA !== null && $skuB !== null) {
            if ($skuA === $skuB) {
                return !$this->hasNumericContradiction($faqA, $faqB);
            }
            // Different explicit SKUs: Never corroborate
            return false;
        }

        // If one is comprehensive knowledge document and neither has contradictory numbers
        if ((($faqA->document_type ?? '') === 'knowledge_document' || ($faqB->document_type ?? '') === 'knowledge_document')) {
            return !$this->hasNumericContradiction($faqA, $faqB);
        }

        if (($skuA !== null && $skuB === null) || ($skuA === null && $skuB !== null)) {
            // Check if one text explicitly references the other's SKU
            $targetSku = $skuA ?? $skuB;
            $otherText = ($skuA === null ? ($faqA->question ?? '') . ' ' . ($faqA->answer ?? '') : ($faqB->question ?? '') . ' ' . ($faqB->answer ?? ''));
            if (!preg_match('/\b' . preg_quote($targetSku, '/') . '\b/i', $otherText)) {
                return false;
            }
        }

        // 2. Policy Topic Check
        $topicA = $this->extractPolicyTopic($faqA);
        $topicB = $this->extractPolicyTopic($faqB);

        if ($topicA !== null && $topicB !== null) {
            if ($topicA === $topicB) {
                return !$this->hasNumericContradiction($faqA, $faqB);
            }
            if ($this->areCompatiblePolicyTopics($topicA, $topicB)) {
                return !$this->hasNumericContradiction($faqA, $faqB);
            }
            return false;
        }

        // 3. Fallback: Translation / Named Entity Overlap
        return $this->sharesCoreEntityAndFacts($faqA, $faqB);
    }

    /**
     * Check if two FAQs share core named entities and factual numbers without contradiction.
     * Uses dynamic substantive token extraction without domain-specific entity dictionaries.
     */
    public function sharesCoreEntityAndFacts(?object $faqA, ?object $faqB): bool
    {
        if ($faqA === null || $faqB === null) {
            return false;
        }

        if ($this->hasNumericContradiction($faqA, $faqB)) {
            return false;
        }

        $textA = mb_strtolower(($faqA->question ?? '') . ' ' . ($faqA->answer ?? ''));
        $textB = mb_strtolower(($faqB->question ?? '') . ' ' . ($faqB->answer ?? ''));

        // Dynamic substantive token extraction (minimum 4 letters, non-stopword)
        preg_match_all('/[\p{L}\p{M}]{4,}/u', $textA, $mA);
        preg_match_all('/[\p{L}\p{M}]{4,}/u', $textB, $mB);

        $stopwords = [
            'about', 'where', 'which', 'their', 'there', 'please', 'details', 'question', 'answer',
            'বিবরণ', 'সম্পর্কিত', 'আমাদের', 'আপনার', 'থাকলে', 'নিয়মাবলী', 'সংক্রান্ত', 'হলে'
        ];
        $wordsA = array_diff(array_unique($mA[0] ?? []), $stopwords);
        $wordsB = array_diff(array_unique($mB[0] ?? []), $stopwords);

        $common = array_intersect($wordsA, $wordsB);

        return count($common) >= 1;
    }

    /**
     * Check if the query is a broad catalog inquiry across multiple products/services.
     * Domain-neutral: applies to apparel, electronics, watches, services, clinic, etc.
     */
    public function isBroadCatalogInquiry(string $query): bool
    {
        $qLower = mb_strtolower($query);

        return (bool) preg_match('/(?:^|[^\p{L}\p{M}\p{N}])(collection|কালেকশন|available|অ্যাভেইলেবল|options?|অপশন|items?|পণ্য|আইটেম|products?|services?|সার্ভিস|তালিকা|catalog|catalogue)(?:$|[^\p{L}\p{M}\p{N}])/ui', $qLower);
    }
}
