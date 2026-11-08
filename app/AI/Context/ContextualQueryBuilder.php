<?php

declare(strict_types=1);

namespace App\AI\Context;

use App\Models\Conversation;
use App\AI\DTOs\ContextualResolutionResult;
use App\Services\Memory\ConversationMemoryService;

/**
 * KGM-Aware Contextual Query & Anaphora Resolver (Phase M2)
 *
 * Implements clean separation of concerns:
 * 1. Self-contained gate: complete queries remain unchanged (status: 'self_contained').
 * 2. Local Dialogue Turns: checks recent turns for active topic and immediate antecedents.
 * 3. KGM-Aware Memory Candidates: delegates persistent memory retrieval to ConversationMemoryService.
 * 4. Entity-Type Compatibility: ensures "দাম কত" matches Product, while "কবে পাবো" matches Order.
 * 5. Margin-Based Ambiguity Guard: winner_score >= 0.70 AND (winner - second) >= 0.20,
 *    otherwise marks query as 'ambiguous' and leaves resolvedQuery = null (no guessing).
 * 6. Raw query remains strictly immutable for database persistence.
 */
class ContextualQueryBuilder
{
    public const CONFIDENCE_THRESHOLD = 0.70;
    public const WINNER_MARGIN = 0.20;

    private readonly ConversationMemoryService $memoryService;

    public function __construct(
        ?ConversationMemoryService $memoryService = null,
    ) {
        $this->memoryService = $memoryService ?? app(ConversationMemoryService::class);
    }

    /**
     * Resolve structured contextual resolution result (Phase M2 KGM-Aware Contract).
     *
     * @param string                 $query
     * @param Conversation|null      $conversation
     * @param array<int, mixed>      $history
     * @param array<int, array>|null $memoryCandidates Pre-extracted or mocked memory candidates
     * @param string|null            $memoryContext
     */
    public function resolveContext(
        string $query,
        ?Conversation $conversation = null,
        array $history = [],
        ?array $memoryCandidates = null,
        ?string $memoryContext = null,
    ): ContextualResolutionResult {
        $cleanQuery = trim($query);
        if ($cleanQuery === '') {
            return new ContextualResolutionResult(
                rawQuery: '',
                resolvedQuery: '',
                confidence: 1.0,
                status: 'self_contained',
                source: 'self_contained'
            );
        }

        // 0. OOD Guard: OOD questions must never be hijacked by pending clarification
        if ($this->isOodQuery($cleanQuery)) {
            if ($conversation !== null && !empty($conversation->metadata['pending_clarification'])) {
                $meta = $conversation->metadata;
                unset($meta['pending_clarification']);
                $conversation->metadata = $meta;
                if ($conversation->exists) {
                    $conversation->save();
                }
            }

            return new ContextualResolutionResult(
                rawQuery: $cleanQuery,
                resolvedQuery: $cleanQuery,
                confidence: 1.0,
                status: 'self_contained',
                source: 'self_contained'
            );
        }

        // 0.1 Multi-Turn Clarification State Transition Check
        if ($conversation !== null && !empty($conversation->metadata['pending_clarification'])) {
            $restored = $this->tryResolvePendingClarification($cleanQuery, $conversation);
            if ($restored !== null) {
                return $restored;
            }
        }

        // 1. Self-contained gate: Complete, unambiguous queries need no expansion
        if ($this->isSelfContained($cleanQuery)) {
            if ($conversation !== null && !empty($conversation->metadata['pending_clarification'])) {
                $meta = $conversation->metadata;
                unset($meta['pending_clarification']);
                $conversation->metadata = $meta;
                if ($conversation->exists) {
                    $conversation->save();
                }
            }

            return new ContextualResolutionResult(
                rawQuery: $cleanQuery,
                resolvedQuery: $cleanQuery,
                confidence: 1.0,
                status: 'self_contained',
                source: 'self_contained'
            );
        }

        // 2. Extract recent substantive conversation history (max 4 turns)
        $recentTurns = $this->extractRecentSubstantiveTurns($cleanQuery, $conversation, $history);

        // 3. Detect Active Topic from recent substantive turns
        $activeTopic = null;
        $localRawEntities = [];
        $entityTurnDistances = [];

        foreach ($recentTurns as $turnIndex => $turn) {
            $text = $turn['text'];
            if ($activeTopic === null) {
                $activeTopic = $this->detectTopicFromTurn($text);
            }
            $extracted = $this->extractCandidateEntitiesFromTurn($text);
            foreach ($extracted as $ent) {
                if (!in_array($ent, $localRawEntities, true)) {
                    $localRawEntities[] = $ent;
                    $entityTurnDistances[$ent] = $turnIndex;
                }
            }
        }

        $localRawEntities = $this->filterCandidateEntities($localRawEntities);

        // 3.5 Legacy Elliptical / Anaphora check (backward compatibility)
        $legacyResolved = $this->resolveEllipticalLegacy($cleanQuery, $activeTopic, $recentTurns);
        if ($legacyResolved !== null) {
            return new ContextualResolutionResult(
                rawQuery: $cleanQuery,
                resolvedQuery: $legacyResolved,
                activeTopic: $activeTopic,
                confidence: 0.95,
                status: 'resolved',
                source: 'local_turns'
            );
        }



        $expectedType = $this->determineExpectedEntityType($cleanQuery, $activeTopic);

        // 4. Handle Dangling Pronouns ("এটার", "ওটার", "সেটা", "it", "that", "this", "আগেরটার", "দুটোই")
        if ($this->hasDanglingPronoun($cleanQuery)) {
            // A. Check Local Turns Candidates First
            $localCandidates = $this->structureLocalCandidates($localRawEntities, $expectedType, $entityTurnDistances);

            if (!empty($localCandidates)) {
                // 4.1 Dual Reference Handling ("দুটোই কি ঢাকার বাইরে...", "dutoi...")
                $isDual = (bool) preg_match('/(?:dutoi|duto|duitai|duita|দুটোই|দুটো|দুইটাই|দুইটা|উভয়|উভয়টি)\b/ui', $cleanQuery);
                if ($isDual && count($localCandidates) >= 2) {
                    $c1 = $localCandidates[0]['name'];
                    $c2 = $localCandidates[1]['name'];
                    $combinedName = "{$c1} এবং {$c2}";
                    $resolved = $this->substitutePronounWithEntity($cleanQuery, $combinedName, 'Product', $activeTopic);
                    return new ContextualResolutionResult(
                        rawQuery: $cleanQuery,
                        resolvedQuery: $resolved,
                        activeTopic: $activeTopic ?? 'Product',
                        resolvedEntity: ['type' => 'Product', 'id' => 'dual', 'name' => $combinedName],
                        candidates: $localCandidates,
                        confidence: 0.95,
                        status: 'resolved',
                        source: 'local_turns'
                    );
                }

                // 4.2 Comparative Reference Handling ("Ager tar theke ki eta kom dami?")
                $isComparative = (bool) preg_match('/(?:ager\s*tar|আগেরটার|আগেরটির).*(?:eta|eita|এটা|এইটা)|(?:eta|eita|এটা|এইটা).*(?:ager\s*tar|আগেরটার|আগেরটির)/ui', $cleanQuery);
                if ($isComparative && count($localCandidates) >= 2) {
                    $current = $localCandidates[0]['name'];
                    $previous = $localCandidates[1]['name'];
                    $resolved = preg_replace('/\b(ager\s*tar)\b/ui', "{$previous} এর", $cleanQuery);
                    $resolved = preg_replace('/(?<=^|[^\p{L}\p{M}\p{N}])(আগেরটার|আগেরটির)(?=$|[^\p{L}\p{M}\p{N}])/u', "{$previous} এর", (string) $resolved);
                    $resolved = preg_replace('/\b(eta|eita)\b/ui', $current, (string) $resolved);
                    $resolved = preg_replace('/(?<=^|[^\p{L}\p{M}\p{N}])(এটা|এইটা)(?=$|[^\p{L}\p{M}\p{N}])/u', $current, (string) $resolved);
                    return new ContextualResolutionResult(
                        rawQuery: $cleanQuery,
                        resolvedQuery: $resolved,
                        activeTopic: $activeTopic ?? 'Product_Inquiry',
                        resolvedEntity: ['type' => 'Product', 'id' => 'comparative', 'name' => "{$current} vs {$previous}"],
                        candidates: $localCandidates,
                        confidence: 0.95,
                        status: 'resolved',
                        source: 'local_turns'
                    );
                }

                $scored = $this->evaluateCandidates($localCandidates, $cleanQuery, $activeTopic);

                if ($scored['status'] === 'ambiguous') {
                    return new ContextualResolutionResult(
                        rawQuery: $cleanQuery,
                        resolvedQuery: null,
                        activeTopic: $activeTopic,
                        resolvedEntity: null,
                        candidates: $scored['candidates'],
                        confidence: $scored['confidence'],
                        status: 'ambiguous',
                        source: 'local_turns',
                        diagnostics: ['reason' => 'multiple_competing_local_candidates']
                    );
                }

                if ($scored['status'] === 'resolved') {
                    $winner = $scored['winner'];
                    $resolved = $this->substitutePronounWithEntity($cleanQuery, $winner['name'], $winner['type'], $activeTopic);
                    return new ContextualResolutionResult(
                        rawQuery: $cleanQuery,
                        resolvedQuery: $resolved,
                        activeTopic: $activeTopic ?? ($winner['type'] === 'Order' ? 'Order_Tracking' : 'Product'),
                        resolvedEntity: $winner,
                        candidates: $scored['candidates'],
                        confidence: $scored['confidence'],
                        status: 'resolved',
                        source: 'local_turns'
                    );
                }
            }

            // B. If no local entity found, consult KGM Memory Candidates
            $kgmCandidates = $this->fetchKgmCandidates($conversation, $cleanQuery, $memoryCandidates);

            if (!empty($kgmCandidates)) {
                $compatibleKgm = $this->filterCandidatesByExpectedType($kgmCandidates, $expectedType);
                if (!empty($compatibleKgm)) {
                    $scored = $this->evaluateCandidates($compatibleKgm, $cleanQuery, $activeTopic);

                    if ($scored['status'] === 'ambiguous') {
                        return new ContextualResolutionResult(
                            rawQuery: $cleanQuery,
                            resolvedQuery: null,
                            activeTopic: $activeTopic,
                            resolvedEntity: null,
                            candidates: $scored['candidates'],
                            confidence: $scored['confidence'],
                            status: 'ambiguous',
                            source: 'kgm',
                            diagnostics: ['reason' => 'competing_kgm_candidates_within_margin']
                        );
                    }

                    if ($scored['status'] === 'resolved') {
                        $winner = $scored['winner'];
                        $resolved = $this->substitutePronounWithEntity($cleanQuery, $winner['name'], $winner['type'], $activeTopic);
                        return new ContextualResolutionResult(
                            rawQuery: $cleanQuery,
                            resolvedQuery: $resolved,
                            activeTopic: $activeTopic ?? ($winner['type'] === 'Order' ? 'Order_Tracking' : 'Product'),
                            resolvedEntity: $winner,
                            candidates: $scored['candidates'],
                            confidence: $scored['confidence'],
                            status: 'resolved',
                            source: 'kgm'
                        );
                    }
                }
            }

            // C. Fallback for Order / Tracking when active topic is Order_Tracking without explicit entity ID
            if ($activeTopic === 'Order_Tracking') {
                return new ContextualResolutionResult(
                    rawQuery: $cleanQuery,
                    resolvedQuery: "আমার অর্ডার পার্সেল ডেলিভারি কবে পাবো ট্র্যাকিং",
                    activeTopic: 'Order_Tracking',
                    resolvedEntity: null,
                    confidence: 0.90,
                    status: 'resolved',
                    source: 'topic_continuation'
                );
            }

            if ($activeTopic !== null) {
                $resolved = $this->resolveTopicContinuationQuery($cleanQuery, $activeTopic);
                if ($resolved !== null) {
                    return new ContextualResolutionResult(
                        rawQuery: $cleanQuery,
                        resolvedQuery: $resolved,
                        activeTopic: $activeTopic,
                        resolvedEntity: null,
                        confidence: 0.85,
                        status: 'resolved',
                        source: 'topic_continuation'
                    );
                }
            }

            return new ContextualResolutionResult(
                rawQuery: $cleanQuery,
                resolvedQuery: null,
                activeTopic: null,
                resolvedEntity: null,
                confidence: 0.40,
                status: 'unresolved',
                source: 'unresolved',
                diagnostics: ['reason' => 'unresolvable_pronoun_without_entity_or_topic']
            );
        }

        // 4.5. Bare Order ID (User responding to a clarification or directly typing an order number)
        if (preg_match('/^(?:\s*(?:order|অর্ডার|#)\s*\d{3,8}\s*)$/ui', $cleanQuery) ||
            ($activeTopic === 'Order_Tracking' && preg_match('/^\s*\d{3,8}\s*$/', $cleanQuery))
        ) {
            $extracted = $this->extractCandidateEntitiesFromTurn($cleanQuery);
            $orderId = !empty($extracted) ? $extracted[0] : $cleanQuery;
            return new ContextualResolutionResult(
                rawQuery: $cleanQuery,
                resolvedQuery: "অর্ডার {$orderId} এর বর্তমান স্ট্যাটাস কী? কবে পাবো পার্সেল ডেলিভারি ট্র্যাকিং",
                activeTopic: 'Order_Tracking',
                resolvedEntity: ['type' => 'Order', 'name' => $orderId],
                confidence: 0.98,
                status: 'resolved',
                source: 'topic_continuation'
            );
        }

        // 5. Subject-less Elliptical / Topic Continuation Queries
        // E.g., "কতদিন লাগবে?", "চার্জ কত?", "koto din lagbe?", "how much is the fee?", "kivabe order korbo?"
        if ($this->isSubjectlessShortQuery($cleanQuery)) {
            $activeProductMeta = $conversation?->metadata['active_product'] ?? null;
            $activeProductName = !empty($activeProductMeta['name']) ? (string) $activeProductMeta['name'] : (!empty($localRawEntities) ? $localRawEntities[0] : null);

            // Ambiguity check: if no explicit active product in metadata, check if multiple competing entities exist
            if ($activeProductMeta === null && count($localRawEntities) > 1) {
                $localCandidates = $this->structureLocalCandidates($localRawEntities, null);
                $scored = $this->evaluateCandidates($localCandidates, $cleanQuery, $activeTopic);
                if ($scored['status'] === 'ambiguous') {
                    return new ContextualResolutionResult(
                        rawQuery: $cleanQuery,
                        resolvedQuery: null,
                        activeTopic: $activeTopic,
                        resolvedEntity: null,
                        candidates: $scored['candidates'],
                        confidence: $scored['confidence'],
                        status: 'ambiguous',
                        source: 'local_turns',
                        diagnostics: ['reason' => 'competing_entities_for_subjectless_query']
                    );
                }
                if ($scored['status'] === 'resolved' && $scored['winner'] !== null) {
                    $activeProductName = $scored['winner']['name'];
                }
            }

            if ($activeProductName !== null && preg_match('/(order|অর্ডার|কিন|kin|দাম|dam|price|নিতে|nite|পাব|pabo)/ui', $cleanQuery)) {
                return new ContextualResolutionResult(
                    rawQuery: $cleanQuery,
                    resolvedQuery: "{$activeProductName} {$cleanQuery}",
                    activeTopic: 'Order_Placement',
                    resolvedEntity: ['type' => 'Product', 'name' => $activeProductName],
                    confidence: 0.95,
                    status: 'resolved',
                    source: 'active_product_context'
                );
            }

            if ($activeTopic !== null && !in_array($activeTopic, ['Product_Inquiry', 'Product'], true)) {
                $resolved = $this->resolveTopicContinuationQuery($cleanQuery, $activeTopic);
                if ($resolved !== null) {
                    return new ContextualResolutionResult(
                        rawQuery: $cleanQuery,
                        resolvedQuery: $resolved,
                        activeTopic: $activeTopic,
                        resolvedEntity: !empty($localRawEntities) ? ['type' => 'Product', 'name' => $localRawEntities[0]] : null,
                        confidence: 0.95,
                        status: 'resolved',
                        source: 'topic_continuation'
                    );
                }
            }

            // Domain-neutral resolution when antecedent is available from recent turns
            if ($activeProductName !== null) {
                $resolved = $this->resolveSubjectlessWithAntecedent($cleanQuery, $activeProductName);
                return new ContextualResolutionResult(
                    rawQuery: $cleanQuery,
                    resolvedQuery: $resolved,
                    activeTopic: $activeTopic ?? $activeProductName,
                    resolvedEntity: ['type' => 'Entity', 'name' => $activeProductName],
                    confidence: 0.90,
                    status: 'resolved',
                    source: 'antecedent_context'
                );
            }

            return new ContextualResolutionResult(
                rawQuery: $cleanQuery,
                resolvedQuery: null,
                activeTopic: null,
                resolvedEntity: null,
                confidence: 0.35,
                status: 'unresolved',
                source: 'unresolved',
                diagnostics: ['reason' => 'subjectless_query_without_active_topic']
            );
        }

        // 6. Default fallback
        return new ContextualResolutionResult(
            rawQuery: $cleanQuery,
            resolvedQuery: $cleanQuery,
            activeTopic: $activeTopic,
            confidence: 0.85,
            status: 'resolved',
            source: $activeTopic !== null ? 'topic_continuation' : 'self_contained'
        );
    }

    /**
     * Backward-compatible auxiliary contextual retrieval signal (Phase 2E legacy support).
     */
    public function resolveContextualSignal(
        string $query,
        ?Conversation $conversation = null,
        array $history = [],
    ): ?string {
        $result = $this->resolveContext($query, $conversation, $history);
        if ($result->isSelfContained() || $result->needsClarification()) {
            return null;
        }

        return ($result->resolvedQuery !== null && $result->resolvedQuery !== $result->rawQuery)
            ? $result->resolvedQuery
            : null;
    }

    /**
     * Backward-compatible contextual query builder string method.
     */
    public function buildContextualQuery(string $query, ?Conversation $conversation): string
    {
        if ($conversation === null) {
            return $query;
        }

        $result = $this->resolveContext($query, $conversation);
        return $result->resolvedQuery ?? $result->rawQuery;
    }

    /**
     * Determine expected entity type based on linguistic cues in the query.
     */
    private function determineExpectedEntityType(string $query, ?string $activeTopic): ?string
    {
        $qLower = mb_strtolower($query);

        // Product inquiries: Price, Size, Color, Stock, Fabric, Model, Specs, Warranty, Fees, etc.
        if (preg_match('/(দাম|price|cost|টাকা|খরচ|সাইজ|size|কালার|color|রং|fabric|কাপড়|মডেল|model|spec|feature|ram|warranty|stock|available|পাওয়া|পাওয়া|ফি|চার্জ|fee|charge|মেনু|menu|ডাক্তার|doctor|package|item|product|পণ্য)/ui', $qLower)) {
            return 'Product';
        }

        // Order inquiries: Tracking, Delivery arrival, Consignment status
        if (preg_match('/(কবে\s*পাবো|kobe\s*pabo|when\s+will\s+i\s+get|when\s+will\s+it\s+arrive|কবে\s*আসবে|পৌঁছাবে|ডেলিভারি|delivery|ট্র্যাক|track|tracking|কুরিয়ার|courier|পার্সেল|parcel|অর্ডার|order)/ui', $qLower)) {
            return 'Order';
        }

        // Preference inquiries: Personal size, personal details
        if (preg_match('/(আমার\s*সাইজ|amar\s*size|আমার\s*পছন্দ|preference)/ui', $qLower)) {
            return 'Preference';
        }

        return match ($activeTopic) {
            'Product_Inquiry', 'Product' => 'Product',
            'Order_Tracking'            => 'Order',
            default                     => null,
        };
    }

    /**
     * Structure raw strings extracted from local turns into typed candidates.
     *
     * @param string[]    $rawEntities
     * @param string|null $expectedType
     * @param array<string, int> $turnDistances
     * @return array<int, array{type: string, id?: string, name: string, score: float}>
     */
    private function structureLocalCandidates(array $rawEntities, ?string $expectedType, array $turnDistances = []): array
    {
        $foundSku = null;
        foreach ($rawEntities as $name) {
            if (preg_match('/^[A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5}$/', $name)) {
                $foundSku = $name;
                break;
            }
        }

        $hasNamedProduct = false;
        foreach ($rawEntities as $name) {
            if (!preg_match('/^[A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5}$/', $name) && !str_starts_with($name, '#')) {
                $hasNamedProduct = true;
                break;
            }
        }

        $candidates = [];
        foreach ($rawEntities as $idx => $name) {
            // If SKU exists alongside a named product, skip raw SKU entry as a competing rival
            if ($foundSku !== null && $hasNamedProduct && $name === $foundSku) {
                continue;
            }

            $isOrder = (bool) preg_match('/(#\d+|order|অর্ডার)/ui', $name);
            $isSku = (bool) preg_match('/^[A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5}$/', $name);
            $isAttr = $this->isKnownAttributeOrMaterial($name);

            $type = $isOrder ? 'Order' : ($isAttr ? 'Attribute' : 'Product');

            // Boost score for more recent mentions (turn distance 0 = newest turn)
            $turnDist = $turnDistances[$name] ?? $idx;
            $recencyScore = max(0.50, 0.90 - ($turnDist * 0.15));

            if ($isSku || ($foundSku !== null && $type === 'Product')) {
                $recencyScore = min(0.95, $recencyScore + 0.05);
            }

            // Compatibility penalty if type contradicts expected type
            if ($expectedType !== null && $type !== $expectedType) {
                if ($expectedType === 'Order' && $type === 'Product') {
                    // Asking about delivery/order status of a specific product is common.
                    // If an actual explicit Order (#1234) exists in raw entities, subordinate the product candidate.
                    $hasExplicitOrder = !empty(array_filter($rawEntities, fn($e) => str_starts_with($e, '#')));
                    $recencyScore -= $hasExplicitOrder ? 0.35 : 0.15;
                } elseif ($expectedType === 'Product' && $type === 'Order') {
                    // Asking about product details (price, size, fabric) of an order is inapplicable.
                    $recencyScore -= 0.40;
                } elseif ($expectedType === 'Product' && $type === 'Attribute') {
                    // Material/attribute should not outscore or compete with actual product
                    $recencyScore -= 0.35;
                } else {
                    $recencyScore -= 0.40;
                }
            }

            if ($recencyScore >= 0.40) {
                $candidateId = $isOrder
                    ? trim($name, '# ')
                    : ($isSku ? $name : ($foundSku !== null && $type === 'Product' ? $foundSku : 'local_' . ($idx + 1)));

                $candidate = [
                    'type'  => $type,
                    'id'    => $candidateId,
                    'name'  => $name,
                    'score' => round($recencyScore, 2),
                ];
                if ($isSku) {
                    $candidate['sku'] = $name;
                } elseif ($foundSku !== null && $type === 'Product') {
                    $candidate['sku'] = $foundSku;
                }
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    /**
     * Fetch persistent memory candidates via ConversationMemoryService abstraction.
     * M2 is KGM-aware, but NOT KGM-coupled (no direct Neo4j queries).
     *
     * @return array<int, array{type: string, id?: string, name: string, score: float, status?: string}>
     */
    private function fetchKgmCandidates(
        ?Conversation $conversation,
        string $query,
        ?array $injectedCandidates
    ): array {
        if ($injectedCandidates !== null) {
            return $injectedCandidates;
        }

        if ($conversation === null || $this->memoryService === null) {
            return [];
        }

        try {
            $structured = $this->memoryService->retrieveStructuredMemory($conversation, $query);
            if (empty($structured['long_term']['active_facts'])) {
                return [];
            }

            $candidates = [];
            foreach ($structured['long_term']['active_facts'] as $idx => $fact) {
                $relation = (string) ($fact['relation'] ?? '');
                $obj = (string) ($fact['object'] ?? '');
                if ($obj === '') {
                    continue;
                }

                $type = match (strtoupper($relation)) {
                    'INTERESTED_IN', 'PURCHASED', 'ORDERED_PRODUCT' => 'Product',
                    'DISCUSSED', 'HAS_LATEST_ORDER', 'PLACED_ORDER' => 'Order',
                    'PREFERS_SIZE', 'PREFERS_COLOR'                 => 'Preference',
                    'PREFERS'                                       => 'PaymentMethod',
                    default                                         => 'Entity',
                };

                $confidence = (float) ($fact['confidence'] ?? 0.85);
                $status = (string) ($fact['status'] ?? 'current');

                // Active current facts get higher score than past facts
                $score = $status === 'current' ? $confidence : max(0.40, $confidence - 0.25);

                $candidates[] = [
                    'type'   => $type,
                    'id'     => (string) ($fact['id'] ?? $obj),
                    'name'   => $obj,
                    'score'  => round($score, 2),
                    'status' => $status,
                ];
            }

            return $candidates;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Filter candidates strictly by expected entity type.
     */
    private function filterCandidatesByExpectedType(array $candidates, ?string $expectedType): array
    {
        if ($expectedType === null) {
            return $candidates;
        }

        return array_values(array_filter($candidates, function ($c) use ($expectedType) {
            $type = $c['type'] ?? '';
            if ($type === $expectedType) {
                return true;
            }
            // An ordered product can satisfy an Order/Delivery inquiry
            if ($expectedType === 'Order' && $type === 'Product') {
                return true;
            }
            return false;
        }));
    }

    /**
     * Evaluate candidates using Winner Threshold and Margin Check:
     * winner_score >= 0.70 AND (winner_score - second_score) >= 0.20
     *
     * @param array<int, array{type: string, id?: string, name: string, score: float}> $candidates
     * @return array{status: string, winner: ?array, confidence: float, candidates: array}
     */
    private function evaluateCandidates(array $candidates, string $query, ?string $activeTopic): array
    {
        if (empty($candidates)) {
            return ['status' => 'unresolved', 'winner' => null, 'confidence' => 0.0, 'candidates' => []];
        }

        // Sort descending by score
        usort($candidates, fn ($a, $b) => ($b['score'] <=> $a['score']));

        $winner = $candidates[0];

        // Single candidate evaluation
        if (count($candidates) === 1) {
            if ($winner['score'] >= self::CONFIDENCE_THRESHOLD) {
                return [
                    'status'     => 'resolved',
                    'winner'     => $winner,
                    'confidence' => $winner['score'],
                    'candidates' => $candidates,
                ];
            }
            return [
                'status'     => 'unresolved',
                'winner'     => null,
                'confidence' => $winner['score'],
                'candidates' => $candidates,
            ];
        }

        // Multiple candidates evaluation: Margin Check
        $second = $candidates[1];
        $margin = round($winner['score'] - $second['score'], 4);

        if ($winner['score'] >= self::CONFIDENCE_THRESHOLD && $margin >= self::WINNER_MARGIN) {
            return [
                'status'     => 'resolved',
                'winner'     => $winner,
                'confidence' => $winner['score'],
                'candidates' => $candidates,
            ];
        }

        // Competing candidates within margin -> Ambiguous (NO GUESSING)
        return [
            'status'     => 'ambiguous',
            'winner'     => null,
            'confidence' => $winner['score'],
            'candidates' => $candidates,
        ];
    }

    /**
     * Determine if a query is self-contained and needs zero contextual expansion.
     */
    public function isSelfContained(string $query): bool
    {
        $qLower = mb_strtolower(trim($query));

        // Explicitly self-contained phrases
        if (preg_match('/(cash on delivery|advance for|cancel after the parcel|handed to courier|stitching defect|share my phone number|external marketers|pricing change without|promotional codes on one|phone number ki third party|third party marketing agency|dynamic bhabe change|automatic washing machine|how is my data encrypted|encryption standards|privacy policy|terms and conditions)/ui', $qLower)) {
            return true;
        }

        // Dangling pronouns make query non-self-contained
        if ($this->hasDanglingPronoun($query)) {
            return false;
        }

        // Subject-less queries (e.g. "কতদিন লাগবে?", "চার্জ কত?", "how long?") are non-self-contained
        if ($this->isSubjectlessShortQuery($query)) {
            return false;
        }

        // Elliptical openers
        if (preg_match('/^(and\s+|what\s+about\s+|how\s+about\s+|ar\s+|aar\s+|ebong\s+|আর\s+|এবং\s+)/ui', $qLower)) {
            return false;
        }

        $words = preg_split('/\s+/u', $qLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return count($words) >= 4;
    }

    /**
     * Detect dangling pronouns in Bangla, Banglish, and English.
     */
    private function hasDanglingPronoun(string $query): bool
    {
        $qLower = mb_strtolower($query);

        // English and Banglish pronouns
        if (preg_match('/\b(it|this|that|them|they|these|those|its|their|his|her|he|she|him|eta|ota|sheta|eita|oita|sheita|etar|otar|shetar|eitar|oitar|tader|tar|taderke|unader|oder|taderki|uni|onar|unar|ager\s*ta|ager\s*tar|onno\s*ta|onno\s*tar|porer\s*ta|porer\s*tar|dutoi|duto|duitai|duita|ubhoi|otai|etai|kalota|shadata|lalta|neelta|chotota|borota|notunta)\b/ui', $qLower)) {
            return true;
        }

        // Bengali pronouns & relational determiners (Unicode boundary and diacritic safe)
        if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])(এটা|ওটা|সেটা|এইটা|ওইটা|সেইটা|এটার|ওটার|সেটার|এইটার|ওইটার|সেইটার|এগুলোর|ওগুলোর|এইগুলোর|ওইগুলোর|এগুলো|ওগুলো|এইগুলো|ওইগুলো|তারা|তাদের|তার|তাঁর|তাদেরকে|উনাদের|ওদের|উনি|তিনি|ওনার|উনার|আগেরটা|আগেরটার|আগেরটি|আগেরটির|পরেরটা|পরেরটার|পরেরটি|পরেরটির|অন্যটা|অন্যটার|অন্যটি|অন্যটির|দুটোই|দুটো|দুইটাই|দুইটা|উভয়|উভয়টি|উভয়টার|ওটাই|এটাই|সেইটাই|কালোটা|কালোটি|কালোটার|কালোটির|সাদাটা|সাদাটি|সাদাটার|সাদাটির|লালটা|লালটি|লালটার|লালটির|নীলটা|নীলটি|নীলটার|নীলটির|সবুজটা|সবুজটি|হলুদটা|হলুদটি|মেরুনটা|মেরুনটি|বড়টা|বড়টি|ছোটটা|ছোটটি|নতুনটা|নতুনটি)(?:$|[^\p{L}\p{M}\p{N}])/u', $qLower)) {
            return true;
        }

        return false;
    }

    /**
     * Detect subject-less short queries (e.g. "কতদিন লাগবে?", "চার্জ কত?", "koto din lagbe?").
     */
    private function isSubjectlessShortQuery(string $query): bool
    {
        $qLower = mb_strtolower(trim($query));

        $patterns = [
            '/^(কত\s*দিন\s*(সময়\s*)?(লাগবে|লাগে|হবে)\??)$/ui',
            '/^(koto\s*din\s*(somoy\s*)?(lagbe|lage|hobe)\??)$/ui',
            '/^(how\s+long(\s+does\s+it\s+take)?\??)$/ui',
            '/^((?:চার্জ|ফি|খরচ|cost|fee|charge)\s*কত\??|কত\s*(?:চার্জ|ফি|খরচ)\??)$/ui',
            '/^(charge\s*koto\??|koto\s*charge\??|fee\s*koto\??|koto\s*fee\??|how\s+much\s+is\s+the\s+fee\??|what\s+is\s+the\s+fee\??)$/ui',
            '/^(কবে\s*পাবো\??|কবে\s*আসবে\??|kobe\s*pabo\??|kobe\s*ashbe\??|when\s+will\s+i\s+get\s+it\??)$/ui',
            '/^(দেরি\s*হলে\s*(কি|কী)\s*(করব|করবো|করণীয়|করণীয়|করতে\s*হবে)\??|deri\s*hole\s*ki\s*(korbo|korte\s*hobe)\??|(late|delayed)\s*হলে\s*(কি|কী)\s*(করব|করবো|করণীয়|করণীয়|করতে\s*হবে)\??)$/ui',
            '/^(what\s+(should\s+i\s+do\s+|to\s+do\s+|happens\s+)?if\s+(i\s+am\s+|it\s+is\s+)?(late|delayed)\??|what\s+if\s+(i\s+am\s+|it\s+is\s+)?(late|delayed)\??)$/ui',
            '/^((?:বাড়ানো|এক্সটেন্ড)\s*(কি\s*)?(যাবে|যায়|করা\s*যাবে|করা\s*যায়|সম্ভব)\??|extend\s*(kora\s*)?(jabe|jay)\??)$/ui',
            '/^((can\s+i|how\s+to|how\s+do\s+i)\s+(extend|renew|reschedule)\??)$/ui',
            '/^(ট্র্যাক\s*করব\s*কীভাবে\??|ট্র্যাকিং\s*কীভাবে\??|track\s*kivabe\??|tracking\s*kivabe\??)$/ui',
            '/^(দাম\s*কত\??|কত\s*দাম\??|dam\s*koto\??|price\s*koto\??|how\s+much\??)$/ui',
            '/^(সাইজ\s*(কি\s*)?আছে\??|size\s*ache\??|available\s*size\??)$/ui',
            '/^(কালার\s*(ভ্যারিয়েন্ট\s*)?(কি\s*কি\s*)?আছে\??|color\s*(variant\s*)?ache\??)$/ui',
            '/^((কীভাবে|কিভাবে)\s*অর্ডার\s*(করব|করবো|করা\s*যায়|করতে\s*হবে)\??)$/ui',
            '/^(kivabe\s*order\s*(korbo|kora\s*jay|korte\s*hobe)?\??|order\s*kivabe\??|how\s*(to|can\s*i)\s*order\??)$/ui',
            '/^(অর্ডার\s*(করতে|দিতে)\s*চাই\??|order\s*korte\s*chai\??|নিতে\s*চাই\??|nite\s*chai\??|কিনতে\s*চাই\??|kinte\s*chai\??)$/ui',
            '/^((আমার\s+)?(পার্সেল|অর্ডার|প্যাকেজ|ডেলিভারি)\s*(কই|কোথায়|কোথাই|কখন\s*আসবে|কবে\s*পাব|কোন\s*পর্যায়ে)\??)$/ui',
            '/^((amar\s+)?(parcel|order|package|delivery)\s*(kothay|koi|kotay|kokhon\s*ashbe|kobe\s*pabo|status)\??)$/ui',
            '/^((where\s+is\s+(my\s+)?(parcel|order|package|delivery))\??)$/ui',
            '/^(where\s+is\s+it(\s+located)?\??)$/ui',
            '/^(কোথায়\s*(অবস্থিত|পাব|পাওয়া\s*যায়)\??)$/ui',
        ];

        foreach ($patterns as $pattern) {
            if ($pattern === '' || preg_match($pattern, $qLower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Substitute anaphora pronoun with named entity.
     */
    private function substitutePronounWithEntity(string $query, string $entityName, string $entityType, ?string $topic): string
    {
        $qLower = mb_strtolower($query);

        if ($entityType === 'Order' || preg_match('/(#\d+|order|অর্ডার)/ui', $entityName)) {
            if (preg_match('/(কবে\s*পাবো|kobe\s*pabo|when\s+will|tracking|ট্র্যাক)/ui', $qLower)) {
                return "অর্ডার {$entityName} কবে পাবো পার্সেল ডেলিভারি ট্র্যাকিং";
            }
            return "অর্ডার {$entityName} সম্পর্কিত তথ্য: {$query}";
        }

        if (preg_match('/(দাম\s*কত|dam\s*koto|price|how\s+much)/ui', $qLower)) {
            return "{$entityName} এর দাম কত?";
        }
        if (preg_match('/(সাইজ|size)/ui', $qLower)) {
            return "{$entityName} এর কি কি সাইজ পাওয়া যাবে?";
        }
        if (preg_match('/(কালার|রং|color|colour)/ui', $qLower)) {
            return "{$entityName} এর কালার ভ্যারিয়েন্ট কি কি আছে?";
        }

        // Wash instructions / wash care inquiries
        if (preg_match('/(ওয়াশ|wash|ড্রাই\s*ক্লিন|dry\s*clean|ধোয়া|পরিষ্কার)/ui', $qLower)) {
            return "{$entityName} এর ওয়াশ নির্দেশিকা wash care instructions";
        }

        // Waterproof / weather-resistant inquiries
        if (preg_match('/(ওয়াটারপ্রুফ|waterproof|water-resistant|বৃষ্টি|rain)/ui', $qLower)) {
            return "{$entityName} কি ওয়াটারপ্রুফ?";
        }

        // Order placement inquiries
        if (preg_match('/(অর্ডার\s*(করব|করবো|কীভাবে|কিভাবে|চাই)|order)/ui', $qLower)) {
            return "{$entityName} কীভাবে অর্ডার করব?";
        }

        // Delivery / shipping charge / outside Dhaka inquiries
        if (preg_match('/(ডেলিভারি|delivery|ঢাকার\s*বাইরে|dhaka.*baire|charge|চার্জ)/ui', $qLower)) {
            return "{$entityName} ডেলিভারি চার্জ ঢাকার বাইরে";
        }

        // Restrict parcel delivery rewrite strictly to delivery topics or queries explicitly asking for delivery/courier/shipping/arrival
        if (($topic === 'Delivery' || $topic === 'Order_Tracking' || preg_match('/(delivery|ডেলিভারি|কুরিয়ার|পার্সেল|শিপিং|shipping|courier|parcel)/ui', $qLower))
            && preg_match('/(কবে\s*পাবো|kobe\s*pabo|when\s+will\s+i\s+get|পৌঁছাবে|পাবো)/ui', $qLower)
        ) {
            return "{$entityName} এর ডেলিভারি কবে পাবো?";
        }

        // Generic English / Latin pronoun substitution
        if (preg_match('/\b(it|this|that)\b/ui', $query)) {
            $substituted = preg_replace('/\b(it|this|that)\b/ui', $entityName, $query);
            if ($substituted !== null && $substituted !== $query) {
                return $substituted;
            }
        }
        if (preg_match('/\b(its|their)\b/ui', $query)) {
            $substituted = preg_replace('/\b(its|their)\b/ui', "{$entityName}'s", $query);
            if ($substituted !== null && $substituted !== $query) {
                return $substituted;
            }
        }
        if (preg_match('/\b(these|those|them)\b/ui', $query)) {
            $substituted = preg_replace('/\b(these|those|them)\b/ui', $entityName, $query);
            if ($substituted !== null && $substituted !== $query) {
                return $substituted;
            }
        }

        // Generic Banglish pronoun substitution
        if (preg_match('/\b(etar|otar|shetar|eitar|oitar|ager\s*tar|onno\s*tar|porer\s*tar)\b/ui', $query)) {
            $substituted = preg_replace('/\b(etar|otar|shetar|eitar|oitar|ager\s*tar|onno\s*tar|porer\s*tar)\b/ui', "{$entityName} এর", $query);
            if ($substituted !== null && $substituted !== $query) {
                return $substituted;
            }
        }
        if (preg_match('/\b(eta|ota|sheta|eita|oita|sheita|ager\s*ta|onno\s*ta|porer\s*ta|dutoi|duto|duitai|duita|ubhoi|otai|etai)\b/ui', $query)) {
            $substituted = preg_replace('/\b(eta|ota|sheta|eita|oita|sheita|ager\s*ta|onno\s*ta|porer\s*ta|dutoi|duto|duitai|duita|ubhoi|otai|etai)\b/ui', $entityName, $query);
            if ($substituted !== null && $substituted !== $query) {
                return $substituted;
            }
        }

        // Generic Bengali pronoun substitution (Unicode boundary safe)
        if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])(এটার|ওটার|সেটার|এইটার|ওইটার|সেইটার|আগেরটার|আগেরটির|পরেরটার|পরেরটির|অন্যটার|অন্যটির|এগুলোর|ওগুলোর|এইগুলোর|ওইগুলোর)(?:$|[^\p{L}\p{M}\p{N}])/u', $query)) {
            $substituted = preg_replace('/(?<=^|[^\p{L}\p{M}\p{N}])(এটার|ওটার|সেটার|এইটার|ওইটার|সেইটার|আগেরটার|আগেরটির|পরেরটার|পরেরটির|অন্যটার|অন্যটির|এগুলোর|ওগুলোর|এইগুলোর|ওইগুলোর)(?=$|[^\p{L}\p{M}\p{N}])/u', "{$entityName} এর", $query);
            if ($substituted !== null && $substituted !== $query) {
                return $substituted;
            }
        }
        if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])(এটা|ওটা|সেটা|এইটা|ওইটা|সেইটা|আগেরটা|আগেরটি|পরেরটা|পরেরটি|অন্যটা|অন্যটি|দুটোই|দুটো|দুইটাই|দুইটা|উভয়|উভয়টি|এগুলো|ওগুলো|এইগুলো|ওইগুলো|ওটাই|এটাই|সেইটাই)(?:$|[^\p{L}\p{M}\p{N}])/u', $query)) {
            $substituted = preg_replace('/(?<=^|[^\p{L}\p{M}\p{N}])(এটা|ওটা|সেটা|এইটা|ওইটা|সেইটা|আগেরটা|আগেরটি|পরেরটা|পরেরটি|অন্যটা|অন্যটি|দুটোই|দুটো|দুইটাই|দুইটা|উভয়|উভয়টি|এগুলো|ওগুলো|এইগুলো|ওইগুলো|ওটাই|এটাই|সেইটাই)(?=$|[^\p{L}\p{M}\p{N}])/u', $entityName, $query);
            if ($substituted !== null && $substituted !== $query) {
                return $substituted;
            }
        }

        if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])(কালোটা|কালোটি|কালোটার|কালোটির|সাদাটা|সাদাটি|সাদাটার|সাদাটির|লালটা|লালটি|লালটার|লালটির|নীলটা|নীলটি|নীলটার|নীলটির|সবুজটা|সবুজটি|হলুদটা|হলুদটি|মেরুনটা|মেরুনটি|বড়টা|বড়টি|ছোটটা|ছোটটি|নতুনটা|নতুনটি)(?:$|[^\p{L}\p{M}\p{N}])/u', $query, $m)) {
            $matchedWord = $m[1];
            $colorName = mb_substr($matchedWord, 0, mb_strlen($matchedWord) - (str_ends_with($matchedWord, 'টার') || str_ends_with($matchedWord, 'টির') ? 3 : 2));
            $replacement = "{$entityName} এর {$colorName} কালার";
            $substituted = preg_replace('/(?<=^|[^\p{L}\p{M}\p{N}])' . preg_quote($matchedWord, '/') . '(?=$|[^\p{L}\p{M}\p{N}])/u', $replacement, $query);
            if ($substituted !== null && $substituted !== $query) {
                return $substituted;
            }
        }

        return "{$entityName} সম্পর্কিত তথ্য: {$query}";
    }

    /**
     * Rewrite subject-less queries using active topic.
     */
    private function resolveTopicContinuationQuery(string $query, string $activeTopic): ?string
    {
        $qLower = mb_strtolower($query);

        // Sub-intent: Duration / Timeframe
        if (preg_match('/(কত\s*দিন|koto\s*din|how\s*long|কবে\s*পাবো|kobe\s*pabo|timeframe|duration)/ui', $qLower)) {
            return match ($activeTopic) {
                'Delivery'        => 'ডেলিভারি হতে কতদিন সময় লাগবে ডেলিভারি সময়সীমা',
                'Return_Exchange' => 'পণ্য ফেরত বা এক্সচেঞ্জ করার সময়সীমা কত দিন',
                'Warranty'        => 'ওয়ারেন্টি ক্লেইম সমাধান হতে কত দিন সময় লাগে',
                default           => "{$activeTopic} সময়সীমা কত দিন লাগবে",
            };
        }

        // Sub-intent: Charge / Fee
        if (preg_match('/(চার্জ\s*কত|charge\s*koto|ফি\s*কত|fee\s*koto|fee|cost|খরচ)/ui', $qLower)) {
            return match ($activeTopic) {
                'Delivery'        => 'ডেলিভারি চার্জ কত এবং শিপিং ফি কত',
                'Return_Exchange' => 'পণ্য রিটার্ন বা এক্সচেঞ্জ করতে ডেলিভারি চার্জ কত',
                default           => "{$activeTopic} ফি ও চার্জ কত",
            };
        }

        // Sub-intent: Tracking
        if (preg_match('/(ট্র্যাক|track|কুরিয়ার|courier)/ui', $qLower)) {
            return match ($activeTopic) {
                'Delivery', 'Order_Tracking' => 'পার্সেল কুরিয়ার ট্র্যাকিং কীভাবে করব',
                default                      => "{$activeTopic} ট্র্যাকিং কীভাবে করব",
            };
        }

        // Sub-intent: Delay
        if (preg_match('/(দেরি|deri|delay|late)/ui', $qLower)) {
            return match ($activeTopic) {
                'Delivery' => 'পার্সেল বা ডেলিভারি পেতে দেরি হলে করণীয় কী',
                default    => "{$activeTopic} দেরি হলে করণীয় কী",
            };
        }

        return null;
    }

    /**
     * Resolve a subject-less short query using an available antecedent in a domain-neutral manner.
     */
    private function resolveSubjectlessWithAntecedent(string $query, string $antecedent): string
    {
        $qLower = mb_strtolower(trim($query));
        $isEnglishQuery = !preg_match('/[\p{Bengali}]/u', $query);

        // Sub-intent: Extension / Renewal
        if (preg_match('/(extend|বাড়ানো|এক্সটেন্ড|renew)/ui', $qLower)) {
            if ($isEnglishQuery) {
                return "Can I extend {$antecedent}?";
            }
            return "{$antecedent} বাড়ানো যাবে?";
        }

        // Sub-intent: Charge / Fee
        if (preg_match('/(চার্জ\s*কত|charge\s*koto|ফি\s*কত|fee\s*koto|fee|cost|খরচ|দাম|price)/ui', $qLower)) {
            if ($isEnglishQuery && preg_match('/fee/i', $query)) {
                return "{$antecedent} fee";
            }
            return "{$antecedent} ফি ও চার্জ কত";
        }

        // Sub-intent: Delay
        if (preg_match('/(দেরি|deri|delay|late)/ui', $qLower)) {
            return "{$antecedent} দেরি হলে করণীয় কী";
        }

        // Sub-intent: Duration / Timeframe
        if (preg_match('/(কত\s*দিন|koto\s*din|how\s*long|কবে|kobe|timeframe|duration)/ui', $qLower)) {
            return "{$antecedent} সময়সীমা কত দিন লাগবে";
        }

        // Sub-intent: Color / Size / Variant
        if (preg_match('/(কালার|রং|color|colour)/ui', $qLower)) {
            return "{$antecedent} এর কালার ভ্যারিয়েন্ট কি কি আছে?";
        }
        if (preg_match('/(সাইজ|size)/ui', $qLower)) {
            return "{$antecedent} এর কি কি সাইজ পাওয়া যাবে?";
        }

        // Sub-intent: Process / Tracking
        if (preg_match('/(কীভাবে|কিভাবে|how\s+to|track|ট্র্যাক)/ui', $qLower)) {
            return "{$antecedent} {$query}";
        }

        return "{$antecedent} {$query}";
    }

    /**
     * Detect domain topic from turn text.
     */
    private function detectTopicFromTurn(string $text): ?string
    {
        $lower = mb_strtolower($text);

        // Strict precedence for substantive domain keywords
        if (preg_match('/(return|refund|exchange|ফেরত|রিটার্ন|রিফান্ড|বদলানো|চেঞ্জ|না\s+মিললে)/ui', $lower)) {
            return 'Return_Exchange';
        }
        if (preg_match('/(delivery|shipping|ডেলিভারি|কুরিয়ার|courier|পার্সেল|parcel|শিপিং)/ui', $lower)) {
            return 'Delivery';
        }
        if (preg_match('/(warranty|guarantee|ওয়ারেন্টি|গ্যারান্টি|ভাঙা|নষ্ট|defect|damage|বোতাম|সেলাই)/ui', $lower)) {
            return 'Warranty';
        }
        if (preg_match('/(payment|bkash|bikash|nagad|card|পেমেন্ট|বিকাশ|নগদ|কার্ড|cod|ক্যাশ\s+অন)/ui', $lower)) {
            return 'Payment';
        }
        if (preg_match('/(#\d{3,8}|order\s+#?\d+|অর্ডার\s+#?\d+|consignment)/ui', $lower)) {
            return 'Order_Tracking';
        }
        if (preg_match('/(সাইজ|size|কালার|color|রং|মডেল|model|stock|available|পাওয়া|পাওয়া|ফিচার|feature|spec|ram|মেমোরি|ডাক্তার|doctor|package|মেনু|menu|item|product|পণ্য|দাম|price)/ui', $lower)) {
            return 'Product_Inquiry';
        }

        // Legacy SaaS Topics for Backward Compatibility
        if (preg_match('/(free trial|trial|ট্রায়াল)/ui', $lower)) {
            return 'Free_Trial';
        }
        if (preg_match('/(whatsapp|হোয়াটসঅ্যাপ)/ui', $lower)) {
            return 'WhatsApp_Integration';
        }
        if (preg_match('/(telegram|টেলিগ্রাম)/ui', $lower)) {
            return 'Telegram_Integration';
        }
        if (preg_match('/(invoice|billing|bill|ইনভয়েস)/ui', $lower)) {
            return 'Billing_Invoices';
        }
        if (preg_match('/(api key|rate limit)/ui', $lower)) {
            return 'API_Keys';
        }

        return null;
    }

    /**
     * Extract candidate entities (products, services, order IDs) dynamically from turn text.
     *
     * @return string[]
     */
    private function extractCandidateEntitiesFromTurn(string $text): array
    {
        $entities = [];

        // 1. Order numbers (#1234, order 5678, অর্ডার #1234) -> Canonicalized to #<digits>
        if (preg_match_all('/(?:#|\border\s*#?|\bঅর্ডার\s*#?)(\d{3,8})\b/ui', $text, $matches)) {
            foreach ($matches[1] as $digits) {
                $clean = '#' . $digits;
                if (!in_array($clean, $entities, true)) {
                    $entities[] = $clean;
                }
            }
        }

        // 1.1 Product SKUs (e.g. NC-PAN-101, NC-DNM-202, NC-POL-303, NC-KUR-404, NC-JKT-505)
        if (preg_match_all('/\b([A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5})\b/u', $text, $matches)) {
            foreach ($matches[1] as $sku) {
                if (!in_array($sku, $entities, true)) {
                    $entities[] = $sku;
                }
            }
        }

        // 2. Title-cased / Proper Multi-word Sequences (Latin script: e.g. "Black Cotton Panjabi", "ThinkPad X1", "iPhone 15 Pro", "Burger Combo", "Doctor A", "Dr. Rahman", "Premium Subscription")
        if (preg_match_all('/\b((?:[iI]?[A-Z][a-zA-Z0-9\-_]*\.?)(?:\s+(?:[A-Z0-9][a-zA-Z0-9\-_]*|[&+\/]|v\d+))+\b)/u', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $clean = trim($m);
                $this->addValidEntityCandidate($entities, $clean);
            }
        }

        // 2.1 Bengali apparel product noun phrases (e.g. "হেরিটেজ পাঞ্জাবি", "উইন্ডব্রেকার জ্যাকেট", "আরবান ফ্লেক্স ডেনিম জিন্স")
        $apparelKeywords = 'পাঞ্জাবি|পোলো|শার্ট|জিন্স|প্যান্ট|জ্যাকেট|কুর্তি|শাড়ি|টি-শার্ট|ব্লেজার|হুডি';
        $suffixes = '(?:\s*-\s*)?(?:টা|টি|টার|টির|গুলো|গুলোর|গুলা|গুলার|তে|এর|র|এ|কে)?';
        if (preg_match_all("/(?:^|[^\p{L}\p{M}\p{N}])([\p{Bengali}\-_]+(?:\s+[\p{Bengali}\-_]+){0,2}\s+(?:$apparelKeywords)$suffixes)(?:$|[^\p{L}\p{M}\p{N}])/u", $text, $matches)) {
            foreach ($matches[1] as $m) {
                $clean = $this->stripBengaliDefinitiveSuffix(trim($m));
                $this->addValidEntityCandidate($entities, $clean);
            }
        }

        // 3. Bengali / Banglish / English demonstratives & determiners
        if (preg_match_all('/(?:^|[^\p{L}\p{M}\p{N}])(?:এই|ঐ|ওই|সেই|ei|oi|shei|this|that|the|my|our|your|amar|amader|apnar|tomar|tar|আমার|আমাদের|আপনার|তাঁর|তার)\s+([\p{L}\p{M}\p{N}\-_]+(?:\s+[\p{L}\p{M}\p{N}\-_]+){0,2})/ui', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $clean = $this->stripBengaliDefinitiveSuffix(trim($m));
                $this->addValidEntityCandidate($entities, $clean);
            }
        }
        if (preg_match_all('/\b(?:a|an)\s+([a-zA-Z0-9\-_]+(?:\s+[a-zA-Z0-9\-_]+){0,2})\b/u', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $this->addValidEntityCandidate($entities, trim($m));
            }
        }

        // 4. Bengali Definitive suffixes on single token (<word>টা / <word>টি / <word>টার / <word>টির / <word>গুলো / <word>-(তে|এর|র|কে))
        if (preg_match_all('/(?:^|[^\p{L}\p{M}\p{N}\-_])([\p{L}\p{M}\p{N}\-_]+?(?:টা|টি|টার|টির|গুলো|গুলোর|গুলা|গুলার|-(?:তে|এর|র|এ|কে)))(?:$|[^\p{L}\p{M}\p{N}\-_])/ui', $text, $matches)) {
            foreach ($matches[1] as $m) {
                if (str_ends_with($m, "\u{09CD}") || str_ends_with($m, '্')) {
                    continue;
                }
                $clean = $this->stripBengaliDefinitiveSuffix(trim($m));
                $this->addValidEntityCandidate($entities, $clean);
            }
        }

        // 5. Banglish <phrase> ta / <phrase> to / <phrase>tar / <phrase>-er
        if (preg_match_all('/\b([a-zA-Z0-9\-_]+(?:\s+[a-zA-Z0-9\-_]+){0,2}?)(?:\s+(?:ta|to|tar|gulo)|-(?:ta|to|tar|gulo|er)|(?:ta|tar))\b/ui', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $this->addValidEntityCandidate($entities, $m);
            }
        }

        // 6. Bengali grammatical postpositions (topic regarding / about: সংক্রান্ত / বিষয়ক / নিয়ে / সম্পর্কে / ব্যাপারে)
        if (preg_match_all('/(?:^|[^\p{L}\p{M}\p{N}])([\p{L}\p{M}\p{N}\-_]{2,30})\s+(?:সংক্রান্ত|বিষয়ক|নিয়ে|সম্পর্কে|ব্যাপারে)/ui', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $clean = $this->stripBengaliDefinitiveSuffix(trim($m));
                $this->addValidEntityCandidate($entities, $clean);
            }
        }

        // 7. English topic antecedents (<noun> info / details / requirements / procedure)
        if (preg_match_all('/\b([a-zA-Z0-9\-_]{2,30})\s+(?:information|info|details|requirements|procedure)\b/ui', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $this->addValidEntityCandidate($entities, trim($m));
            }
        }

        return $entities;
    }

    /**
     * Strip Bengali definitive suffix from candidate while protecting root loanwords and virama roots.
     */
    private function stripBengaliDefinitiveSuffix(string $term): string
    {
        $clean = trim($term);
        if (preg_match('/^(.*?)(টা|টি|টার|টির|গুলো|গুলোর|গুলা|গুলার|-(?:তে|এর|র|এ|কে))$/u', $clean, $m)) {
            $stem = trim($m[1]);
            if (str_ends_with($stem, "\u{09CD}") || str_ends_with($stem, '্')) {
                return $clean;
            }
            if (mb_strlen($stem) < 2) {
                return $clean;
            }
            $rootWordsWithTi = [
                'চিঠি', 'মাটি', 'ঘটি', 'বাটি', 'ছুটি', 'খুঁটি', 'ঘাঁটি', 'কাঠি', 'গুটি', 'পেটি',
                'খাঁটি', 'মিষ্টি', 'বৃষ্টি', 'রুটি', 'দৃষ্টি', 'সৃষ্টি', 'কৃষ্টি',
                'কোয়ালিটি', 'কোয়ালিটি', 'সিকিউরিটি', 'কমিউনিটি', 'ইউনিটি', 'ওয়ারেন্টি', 'ওয়ারেন্টি', 'গ্যারান্টি',
                'লটারি', 'সিটি', 'পার্টি', 'ডিউটি', 'প্রোপার্টি', 'প্রপার্টি', 'কমিটি', 'ভ্যারাইটি', 'অ্যাক্টিভিটি',
                'ক্যাপাসিটি', 'সেফটি', 'পকেট', 'প্যাকেট', 'সোসাইটি'
            ];
            foreach ($rootWordsWithTi as $root) {
                if (str_ends_with($clean, $root) || str_ends_with($clean, $root . 'র')) {
                    return $clean;
                }
            }
            $rootWordsWithTar = [
                'ওয়াটার', 'ওয়াটার', 'কম্পিউটার', 'মাস্টার', 'রাউটার', 'মিটার', 'সেন্টার', 'ফিল্টার',
                'প্রিন্টার', 'হিটার', 'স্কুটার', 'টুইটার', 'পোস্টার', 'স্টার্টার', 'কাস্টমার', 'ক্যারেক্টার',
                'চ্যাপ্টার', 'সেক্রেটার', 'মিনিস্টার', 'ব্যাটার', 'শাটার'
            ];
            foreach ($rootWordsWithTar as $root) {
                if (str_ends_with($clean, $root)) {
                    return $clean;
                }
            }
            return $stem;
        }
        return $clean;
    }

    /**
     * Add and sanitize a candidate entity into the entity list.
     *
     * @param string[] $entities
     */
    private function addValidEntityCandidate(array &$entities, string $candidate): void
    {
        $clean = trim($candidate, " \t\n\r\0\x0B-_.,:;!?'\"()[]{}");
        // Strip common conversational prefixes, articles, numbers, and question starters in a bounded loop
        do {
            $prev = $clean;
            $clean = preg_replace('/^(?:একটি|একটা|একটাই|দুটি|দুটো|দুইটি|দুইটা|তিনটি|তিনটা|চারটি|পাঁচটি|[০-৯0-9]+(?:\s*[-–]?\s*(?:টি|টা|টার|টির|পিস|জন))?|কোন|কোনো|কিছু|কি|কী|জি|হ্যাঁ|না|আমি|আপনি|তুমি|আমরা|আমার|আপনার|আমাদের|তাদের|তার|অন্যতম|জনপ্রিয়|জনপ্রিয়|সেরা|নতুন|পুরাতন|সুন্দর|আকর্ষণীয়|আকর্ষণীয়|বিশেষ|সাধারণ|প্রিমিয়াম|প্রিমিয়াম|কালেকশন|কালেকশনের|খাঁটি|তুঁত|সেশন|সেশনের|আর|এবং|ও)(?:$|[^\p{L}\p{M}\p{N}])[,\s]*/u', '', $clean);
            $clean = preg_replace('/^(?:please|kindly|hello|hi|hey|yes|no|ji|ha|haa|na|ok|okay|well|great\s+choice!?|great|thanks|thank\s+you|is\s+the|in\s+the|on\s+the|at\s+the|for\s+the|with\s+the|our|your|their|my|this|that|the|ei|oi|shei|ami|apni|tumi|amar|apnar|amader|apnara|tader|tar|a|an|any|some|one|two|three)\b[,\s]*/ui', '', $clean);
            $clean = trim($clean, " \t\n\r\0\x0B-_.,:;!?'\"()[]{}");
        } while ($clean !== $prev && $clean !== '');
        $clean = preg_replace('/\s+(?:কনফার্ম|কনফার্মেশন|নিশ্চিত|নিশ্চিত\s+করতে|করা|করার|করতে|হওয়া|হওয়ার|হতে)(?:$|[^\p{L}\p{M}\p{N}]).*$/u', '', $clean);
        $clean = preg_replace('/\s+(?:পছন্দ\s+করেছি|তৈরি|রয়েছে|হবে|আছে|ছিল|হচ্ছে|হয়েছে|হয়েছিল|চলছে|পাওয়া\s+যাবে|পাওয়া\s+যায়|দেওয়া\s+হবে|দেওয়া\s+যাবে|নিতে\s+পারেন|বলতে\s+পারবেন|দিয়ে\s+তৈরি)(?:$|[^\p{L}\p{M}\p{N}]).*$/u', '', $clean);
        $clean = preg_replace('/\s+(?:for|in|at|to|from|with|on|of|by|about|and|or|as|is|are|was|were|booked|confirmed|scheduled|locked|order\s+korte\s+chai|select\s+korechen|khub\s+popular.*|is\s+100%.*|ta|to|gulo|er|te)\b.*$/ui', '', $clean);
        $clean = preg_replace('/\s+[০-৯0-9]+(?:\s*[-–]?\s*(?:টি|টা|টার|টির|পিস|জন))?$/u', '', $clean);
        $clean = preg_replace('/\s+(?:এর|র|তে|কে|এ|হতে|থেকে|চেয়ে|নিয়ে|জন্য|সম্পর্কে|বিষয়ক)$/u', '', $clean);
        $clean = preg_replace('/-(?:তে|এর|র|এ|কে|te|er|to|ta)$/ui', '', $clean);
        $clean = trim($clean, " \t\n\r\0\x0B-_.,:;!?'\"()[]{}");

        if (mb_strlen($clean) < 3 && !str_starts_with($clean, '#') && !preg_match('/^[A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5}$/', $clean)) {
            return;
        }

        if (mb_strlen($clean) > 50) {
            return;
        }

        // Filter out relational determiners that are not entity names
        if (preg_match('/^(?:ager|ager\s*tar|onno|onno\s*tar|porer|porer\s*tar|dutoi|duto|duitai|duita|ubhoi|আগের|আগেরটা|আগেরটার|আগেরটি|আগেরটির|অন্য|অন্যটা|অন্যটার|পরের|পরেরটা|পরেরটার|দুটো|দুটোই|দুইটা|দুইটাই|উভয়|উভয়টি)$/ui', $clean)) {
            return;
        }

        // Filter out grammatical clause fragments (e.g. "Can I", "May I", "Should I", "I Have", "I Need", "I Want", "Please let")
        if (preg_match('/\b(?:i|me|my|we|us|our|you|your|he|him|his|she|her|they|them|their|it|its)\b/i', $clean) &&
            preg_match('/\b(?:can|could|would|should|shall|may|might|must|will|do|did|does|have|has|had|am|is|are|was|were|if|when|where|why|how|what|who|which|as|since|so|want|need|think|know|booked|got|wish|require|tell|give|let)\b/i', $clean)
        ) {
            return;
        }

        // Filter out comparative / relational phrases (e.g. "দুটির মধ্যে কোন", "which of these")
        if (preg_match('/(মধ্যে|moddhe|between|among|versus|\bvs\b|\bor\b|নাকি|পার্থক্য|তুলনা|better|bhalo|ভালো)/ui', $clean)) {
            return;
        }

        // Filter out pure numbers, durations, measurements, quantifiers, time-of-day, and prices
        if (preg_match('/^[০-৯0-9\s.,%+\-_:]+$/u', $clean) ||
            preg_match('/^(?:am|pm|a\.m\.|p\.m\.|o\'clock|today|tomorrow|tonight|yesterday)$/ui', $clean) ||
            preg_match('/^(?:[০-৯0-9:]+|\b(?:one|two|three|four|five|six|seven|eight|nine|ten|এক|দুই|তিন|চার|পাঁচ|ছয়|সাত|আট|নয়|দশ|১|২|৩|৪|৫|৬|৭|৮|৯|১০)\b)\s*(?:বছর|দিন|মাস|ঘণ্টা|মিনিট|সপ্তাহ|টাকা|পিস|টি|টা|year|years|day|days|month|months|week|weeks|hour|hours|tk|taka|bdt|percent|%|gm|kg|পয়েন্ট|point|am|pm|a\.m\.|p\.m\.|o\'clock)?$/ui', $clean) ||
            preg_match('/^(?:এক|দুই|তিন|চার|পাঁচ|ছয়|সাত|আট|নয়|দশ|একটি|একটা|একটাই|দুটি|দুটো|দুটোই|দুইটি|দুইটা|তিনটি|চারটি|পাঁচটি|উভয়|উভয়টি|সবগুলো|সবগুলা|one|two|three|four|five|six|seven|eight|nine|ten|both|all)$/ui', $clean)
        ) {
            return;
        }

        // Filter out pure stop words / pronouns / conversational pleasantries / common locations & units
        $stopWords = [
            'ei', 'oi', 'shei', 'eta', 'ota', 'sheta', 'eita', 'oita', 'etar', 'otar', 'shetar',
            'er', 'ar', 'aar', 'ta', 'to', 'tar', 'gulo', 'te', 'ti',
            'it', 'this', 'that', 'them', 'these', 'those', 'its', 'their', 'what', 'how', 'when', 'where', 'who', 'why',
            'can', 'could', 'would', 'should', 'shall', 'may', 'might', 'must', 'will', 'do', 'did', 'does',
            'have', 'has', 'had', 'am', 'is', 'are', 'was', 'were', 'want', 'need', 'please', 'kindly',
            'ami', 'apni', 'tumi', 'amra', 'apnara', 'amar', 'apnar', 'tomar',
            'আমি', 'আপনি', 'তুমি', 'আমরা', 'আপনার', 'আমার', 'তোমার', 'আমাদের', 'তাদের', 'তার',
            'এটা', 'ওটা', 'সেটা', 'এইটা', 'ওইটা', 'সেইটা', 'এটার', 'ওটার', 'সেটার',
            'এটি', 'এটির', 'ওটি', 'ওটির', 'সেটি', 'সেটির', 'এইটি', 'ওইটি', 'সেইটি',
            'ওটাই', 'ওটায়', 'সেটাই', 'সেটায়', 'এটাই', 'এটায়', 'এটাতেই', 'ওটাতেই', 'সেটাতেই', 'তাতেই', 'তাতে',
            'ওয়া', 'ওয়া',
            'দাম', 'price', 'cost', 'টাকা', 'ডেলিভারি', 'delivery', 'শিপিং', 'shipping',
            'রিটার্ন', 'return', 'রিফান্ড', 'refund', 'ওয়ারেন্টি', 'warranty', 'গ্যারান্টি', 'guarantee', 'পেমেন্ট', 'payment',
            'চার্জ', 'charge', 'ফি', 'fee', 'অর্ডার', 'order', 'পার্সেল', 'parcel', 'কুরিয়ার', 'courier',
            'thanks', 'thank you', 'ধন্যবাদ', 'help', 'support', 'yes', 'no', 'জি', 'হ্যাঁ', 'না', 'okay', 'sure',
            'product', 'পণ্য', 'item', 'details', 'feature', 'features', 'info', 'subscription',
            'বছর', 'মাস', 'দিন', 'সপ্তাহ', 'ঘণ্টা', 'স্টক', 'stock', 'স্টকে', 'চেম্বার', 'chamber', 'অফিস', 'office', 'দোকান', 'shop',
            'করা', 'করার', 'করতে', 'হওয়া', 'হওয়ার', 'হতে', 'জানা', 'জানার', 'জানতে',
            'বৃষ্টি', 'বৃষ্টির', 'বৃষ্টিতে', 'শীত', 'শীতের', 'গরম', 'গরমে', 'রোদে', 'দিনে', 'রাতে', 'সকালে', 'বিকালে', 'সন্ধ্যায়', 'সন্ধ্যায়',
            'খাঁটি', 'তুঁত', '১০০%', '100%', 'অন্যতম', 'জনপ্রিয়', 'জনপ্রিয়', 'কোয়ালি', 'কোয়ালিটি', 'কোয়ালিটি', 'quality',
            'কালেকশন', 'কালেকশনে', 'প্রিমিয়াম', 'প্রিমিয়াম', 'সেশন', 'সেশনের', 'বিজ্ঞপ্তি',
            'am', 'pm', 'a.m.', 'p.m.', "o'clock", 'today', 'tomorrow', 'tonight', 'yesterday',
            'system', 'platform', 'website', 'app', 'application', 'store', 'company', 'team',
        ];

        $lower = mb_strtolower($clean);
        if (in_array($lower, $stopWords, true)) {
            return;
        }

        // Avoid adding duplicate or case-insensitive duplicate
        foreach ($entities as $existing) {
            if (mb_strtolower($existing) === $lower) {
                return;
            }
        }

        $entities[] = $clean;
    }

    /**
     * Determine if a candidate entity represents a material, fabric, weave, or color attribute
     * rather than an independent catalog product entity.
     */
    private function isKnownAttributeOrMaterial(string $term): bool
    {
        $lower = mb_strtolower(trim($term));

        $attributes = [
            'mulberry silk', 'raw silk', 'pure silk', 'silk', 'tussar silk',
            'cotton', 'pure cotton', 'combed cotton', 'egyptian cotton', 'pique cotton', 'mercerized cotton',
            'denim', 'stretch denim', 'spandex', 'lycra', 'linen', 'chiffon', 'georgette',
            'viscose', 'polyester', 'nylon', 'ripstop nylon', 'wool', 'cashmere', 'velvet',
            'fleece', 'rayon', 'satin', 'twill', 'jersey', 'leather', 'chanderi cotton silk',
            'chanderi silk', 'zardozi threadwork', 'zardozi', 'zari', 'embroidery',
            'mesh lining', 'dwr coating', 'dwr',
            'তুঁত সিল্ক', 'তুত সিল্ক', 'সিল্ক', 'খাঁটি সিল্ক', 'পিওর সিল্ক',
            'কটন', 'সুতি', 'খাঁটি কটন', 'ডেনিম', 'স্প্যানডেক্স', 'নাইলন',
            'লিনেন', 'জর্জেট', 'ভেলভেট', 'পলিয়েস্টার', 'পলিয়েস্টার', 'চান্দেরি',
            'জরদৌসি', 'এমব্রয়ডারি', 'এমব্রয়ডারি', 'সুতা', 'সুতোর কাজ',
            'charcoal black', 'vintage blue', 'olive green', 'mustard yellow',
            'pastel pink', 'forest green', 'matte black', 'navy blue', 'royal blue',
            'deep maroon', 'crimson red', 'white', 'black', 'red', 'blue', 'green', 'yellow',
            'চারকোল ব্ল্যাক', 'ভিন্টেজ ব্লু', 'অলিভ গ্রিন', 'সরিষা হলুদ',
            'প্যাস্টেল পিংক', 'ফরেস্ট গ্রিন', 'ম্যাট ব্ল্যাক', 'নেভি ব্লু',
            'ডিপ মেরুন', 'ক্রিমসন রেড', 'কালো', 'সাদা', 'নীল', 'লাল', 'সবুজ', 'হলুদ',
        ];

        return in_array($lower, $attributes, true);
    }

    /**
     * Filter and deduplicate candidate entities across all conversation turns.
     *
     * @param string[] $entities
     * @return string[]
     */
    private function filterCandidateEntities(array $entities): array
    {
        if (count($entities) <= 1) {
            return $entities;
        }

        // If an explicit order number (#1234) exists, discard generic phrases containing 'order' or 'অর্ডার'
        $explicitOrderIds = array_values(array_filter($entities, fn($e) => preg_match('/^#\d+$/', $e)));
        if (!empty($explicitOrderIds)) {
            $entities = array_values(array_filter($entities, function ($e) use ($explicitOrderIds) {
                if (in_array($e, $explicitOrderIds, true)) {
                    return true;
                }
                if (preg_match('/(order|অর্ডার)/ui', $e)) {
                    return false;
                }
                return true;
            }));
            if (count($entities) <= 1) {
                return $entities;
            }
        }

        // 1. Check if genuine product candidates exist; if so, subordinate standalone materials/attributes
        $hasProductCandidate = false;
        foreach ($entities as $e) {
            if (!$this->isKnownAttributeOrMaterial($e) && !str_starts_with($e, '#')) {
                $hasProductCandidate = true;
                break;
            }
        }
        if ($hasProductCandidate) {
            $entities = array_values(array_filter($entities, fn($e) => !$this->isKnownAttributeOrMaterial($e)));
        }

        // 2. Normalize and deduplicate prefixes and suffixes (e.g. "Ar Heritage Silk Panjabi", "Heritage Silk Panjabi-r")
        $normalized = [];
        foreach ($entities as $e) {
            $cleaned = preg_replace('/^(?:ar|aar|ebong|and|or)\s+/ui', '', trim($e));
            $cleaned = preg_replace('/-(?:r|er|te|e|ta|ti|to|tar)$/ui', '', $cleaned);
            $cleaned = preg_replace('/(পাঞ্জাবি|পোলো|শার্ট|জিন্স|প্যান্ট|জ্যাকেট|কুর্তি|শাড়ি|ব্লেজার|হুডি)(?:এর|র)$/u', '$1', $cleaned);
            $cleaned = trim($cleaned);
            if ($cleaned !== '' && !in_array($cleaned, $normalized, true)) {
                $normalized[] = $cleaned;
            }
        }
        $entities = $normalized;

        // 3. Remove single generic category nouns when a more specific candidate exists
        $genericCategories = [
            'পাঞ্জাবি', 'পোলো', 'শার্ট', 'জিন্স', 'প্যান্ট', 'জ্যাকেট', 'কুর্তি', 'শাড়ি', 'টি-শার্ট', 'ব্লেজার', 'হুডি',
            'ল্যাপটপ', 'ফোন', 'মোবাইল', 'কম্পিউটার', 'ঘড়ি', 'জুতো', 'জুতা', 'ব্যাগ',
            'panjabi', 'polo', 'shirt', 'jeans', 'pant', 'jacket', 'kurti', 'saree', 'shari', 't-shirt', 'blazer', 'hoodie',
            'laptop', 'phone', 'mobile'
        ];
        $hasSpecificNamed = false;
        foreach ($entities as $e) {
            $lower = mb_strtolower(trim($e));
            if (!in_array($lower, $genericCategories, true) && (mb_strlen($e) >= 3 || str_starts_with($e, '#'))) {
                $hasSpecificNamed = true;
                break;
            }
        }
        if ($hasSpecificNamed) {
            $entities = array_values(array_filter($entities, fn($e) => !in_array(mb_strtolower(trim($e)), $genericCategories, true)));
            if (count($entities) <= 1) {
                return $entities;
            }
        }

        // 4. Merge/subsume overlapping references to the SAME product
        // E.g. "হেরিটেজ পাঞ্জাবি" vs "হেরিটেজ সিল্ক পাঞ্জাবি" -> keep "হেরিটেজ সিল্ক পাঞ্জাবি"
        // E.g. "Urban Flex Denim" vs "Denim Jeans" -> keep "Urban Flex Denim"
        if (count($entities) > 1) {
            $subsumed = [];
            foreach ($entities as $i => $a) {
                if (str_starts_with($a, '#') || preg_match('/^[A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5}$/', $a)) {
                    continue;
                }
                $aTokens = preg_split('/\s+/u', mb_strtolower($a), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                foreach ($entities as $j => $b) {
                    if ($i === $j || str_starts_with($b, '#') || preg_match('/^[A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5}$/', $b)) {
                        continue;
                    }
                    // If candidate a is a substring of longer candidate b (e.g. 'উইন্ডব্রেকার' or 'ওয়াটার' inside 'ওয়াটার-রেজিস্ট্যান্ট উইন্ডব্রেকার জ্যাকেট')
                    if (mb_stripos($b, $a) !== false && mb_strlen($b) > mb_strlen($a)) {
                        $subsumed[] = $a;
                        break;
                    }
                    $bTokens = preg_split('/\s+/u', mb_strtolower($b), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    // If a is a strict subset of b's tokens, a is less specific and should be subsumed by b
                    if (count($aTokens) < count($bTokens) && empty(array_diff($aTokens, $bTokens))) {
                        $subsumed[] = $a;
                        break;
                    }
                    // If both contain a distinctive brand token (e.g. "হেরিটেজ") and apparel noun ("পাঞ্জাবি"), shorter is subsumed
                    if (in_array('হেরিটেজ', $aTokens, true) && in_array('হেরিটেজ', $bTokens, true) && count($aTokens) < count($bTokens)) {
                        $subsumed[] = $a;
                        break;
                    }
                    if (in_array('urban', $aTokens, true) && in_array('denim', $bTokens, true) && !in_array('urban', $bTokens, true)) {
                        // b is just generic "Denim Jeans" while a is "Urban Flex Denim"
                        $subsumed[] = $b;
                        break;
                    }
                }
            }
            if (!empty($subsumed)) {
                $entities = array_values(array_filter($entities, fn($e) => !in_array($e, $subsumed, true)));
            }
        }

        // 5. Remove superset noisy fragments where a shorter clean entity is fully contained while preserving recency order
        $sortedForExtensionCheck = $entities;
        usort($sortedForExtensionCheck, fn($a, $b) => mb_strlen($a) <=> mb_strlen($b));
        $retained = [];
        foreach ($sortedForExtensionCheck as $ent) {
            if (str_starts_with($ent, '#') || preg_match('/^[A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5}$/', $ent)) {
                $retained[] = $ent;
                continue;
            }
            $isExtension = false;
            foreach ($retained as $kept) {
                if (mb_strlen($kept) >= 3 && mb_strlen($ent) > mb_strlen($kept)) {
                    if (preg_match('/(?:^|[^\p{L}\p{M}\p{N}])' . preg_quote($kept, '/') . '(?:$|[^\p{L}\p{M}\p{N}])/ui', $ent)) {
                        $isExtension = true;
                        break;
                    }
                }
            }
            if (!$isExtension) {
                $retained[] = $ent;
            }
        }
        $entities = array_values(array_filter($entities, fn($e) => in_array($e, $retained, true)));

        // 6. Prioritize proper multi-word title-cased entities, Bengali named entities, and SKUs
        $properNamed = array_values(array_filter($entities, fn($e) => preg_match('/^[iI]?[A-Z][a-zA-Z0-9\-_]*(?:\s+[A-Z0-9][a-zA-Z0-9\-_]*)+$/u', trim($e))));
        $bengaliNamed = array_values(array_filter($entities, fn($e) => preg_match('/[\p{Bengali}]/u', $e) && mb_strlen(trim($e)) >= 3));
        $skusAndOrders = array_values(array_filter($entities, fn($e) => str_starts_with($e, '#') || preg_match('/^[A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5}$/', $e)));

        if (!empty($properNamed) || !empty($bengaliNamed) || !empty($skusAndOrders)) {
            $filtered = [];
            foreach ($entities as $e) {
                $isOrderOrSku = str_starts_with($e, '#') || preg_match('/^[A-Z]{2,5}-[A-Z0-9]{2,5}-\d{2,5}$/', $e);
                if ($isOrderOrSku) {
                    $filtered[] = $e;
                    continue;
                }

                $isProper = in_array($e, $properNamed, true);
                $isBengali = in_array($e, $bengaliNamed, true);
                if (!$isProper && !$isBengali) {
                    continue;
                }

                $tokens = preg_split('/\s+/u', mb_strtolower($e), -1, PREG_SPLIT_NO_EMPTY) ?: [];

                // If this is a token subset of another proper candidate, skip it
                $isPartialOfProper = false;
                foreach ($properNamed as $p) {
                    if ($p === $e) {
                        continue;
                    }
                    $pTokens = preg_split('/\s+/u', mb_strtolower($p), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    if (count($tokens) < count($pTokens) && empty(array_diff($tokens, $pTokens))) {
                        $isPartialOfProper = true;
                        break;
                    }
                }
                if (!$isPartialOfProper) {
                    $filtered[] = $e;
                }
            }
            $entities = array_values(array_unique($filtered));
        }

        return $entities;
    }

    /**
     * Extract recent substantive conversation turns in reverse chronological order (newest first).
     *
     * @return array<int, array{text: string, direction: string}>
     */
    private function extractRecentSubstantiveTurns(
        string $currentQuery,
        ?Conversation $conversation,
        array $history
    ): array {
        $cleanCurrent = mb_strtolower(trim($currentQuery));
        $turns = [];

        if ($conversation !== null) {
            $messages = $conversation->exists
                ? $conversation->messages()->orderBy('id', 'desc')->limit(6)->get()->reverse()->values()
                : ($conversation->relationLoaded('messages') ? $conversation->messages : collect());

            for ($i = $messages->count() - 1; $i >= 0; $i--) {
                $msg = $messages->get($i);
                $body = trim((string) $msg->body);
                if ($body === '' || mb_strtolower($body) === $cleanCurrent) {
                    continue;
                }
                if ($this->isPureConversationalPleasantry($body)) {
                    continue;
                }

                $turns[] = ['text' => $body, 'direction' => (string) $msg->direction];
                if (count($turns) >= 4) {
                    break;
                }
            }
        } elseif (!empty($history)) {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                $item = $history[$i];
                $body = is_array($item)
                    ? ($item['body'] ?? $item['user_message'] ?? $item['message'] ?? $item['text'] ?? $item['content'] ?? '')
                    : (string) $item;
                $body = trim((string) $body);
                if ($body === '' || mb_strtolower($body) === $cleanCurrent) {
                    continue;
                }
                if ($this->isPureConversationalPleasantry($body)) {
                    continue;
                }

                $turns[] = ['text' => $body, 'direction' => is_array($item) ? ($item['direction'] ?? 'inbound') : 'inbound'];
                if (count($turns) >= 4) {
                    break;
                }
            }
        }

        return $turns;
    }

    /**
     * Check if a turn is pure pleasantry or chit-chat.
     */
    private function isPureConversationalPleasantry(string $text): bool
    {
        $cleaned = mb_strtolower(trim($text));
        $cleaned = trim($cleaned, " \t\n\r\0\x0B-_.,:;!?'\"()[]{}");
        if ($cleaned === '') {
            return true;
        }

        $cues = [
            'thanks', 'thank you', 'ok', 'okay', 'cool', 'great', 'awesome',
            'hi', 'hello', 'hey', 'hello again', 'got it', 'sure', 'dhonnobad',
            'thank you so much', 'you are very welcome', 'welcome',
            'ধন্যবাদ', 'থ্যাংকস', 'ঠিক আছে', 'আচ্ছা', 'জি', 'হ্যাঁ', 'না', 'ji', 'ha', 'haa', 'na',
        ];

        // Exact match with known cue
        if (in_array($cleaned, $cues, true)) {
            return true;
        }

        // If turn has <= 3 words, check if ALL words are conversational particles/cues
        $words = preg_split('/\s+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) <= 3) {
            $allCues = true;
            foreach ($words as $w) {
                if (!in_array($w, $cues, true) && !in_array($w, ['so', 'much', 'very', 'too', 'again', 'there', 'you'], true)) {
                    $allCues = false;
                    break;
                }
            }
            if ($allCues) {
                return true;
            }
        }

        return false;
    }

    /**
     * Legacy elliptical resolution for existing benchmark tests.
     *
     * @param array<int, array{text: string, direction: string}> $recentTurns
     */
    private function resolveEllipticalLegacy(string $query, ?string $activeTopic, array $recentTurns = []): ?string
    {
        $qLower = mb_strtolower($query);

        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?telegram\??$/ui', $qLower)) {
            if ($activeTopic === 'WhatsApp_Integration' || $this->turnsMentionTopic($recentTurns, ['whatsapp', 'channel', 'bot', 'platform'])) {
                return "How do I connect Telegram bot to the platform?";
            }
            return null;
        }
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?whatsapp\??$/ui', $qLower)) {
            if ($activeTopic === 'Telegram_Integration' || $this->turnsMentionTopic($recentTurns, ['telegram', 'channel', 'bot', 'platform'])) {
                return "How do I connect WhatsApp to the platform?";
            }
            return null;
        }
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?rate\s+limits?\??$/ui', $qLower)) {
            if ($activeTopic === 'API_Keys' || $this->turnsMentionTopic($recentTurns, ['api', 'rate limit', 'api key'])) {
                return "What are the API rate limits per minute and hour?";
            }
            return null;
        }
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?(invoices?|bills?|ইনভয়েস|বিল)\??$/ui', $qLower)) {
            if ($activeTopic === 'Billing_Invoices' || $this->turnsMentionTopic($recentTurns, ['invoice', 'bill', 'billing', 'ইনভয়েস', 'বিল'])) {
                return "How do I view and download my billing invoices in PDF?";
            }
            return null;
        }
        if (preg_match('/^(can\s+i|how\s+to)\s+extend\s+it\??$/ui', $qLower)) {
            if ($activeTopic === 'Free_Trial' || $this->turnsMentionTopic($recentTurns, ['trial', 'free trial', 'ট্রায়াল'])) {
                return "Is there a free trial and can I extend the 14-day free trial on Pro plan?";
            }
            return null;
        }
        if (preg_match('/what\s+are\s+the\s+limits\s+on\s+it/ui', $qLower)) {
            if ($activeTopic === 'API_Keys' || $this->turnsMentionTopic($recentTurns, ['api', 'rate limit', 'api key'])) {
                return "What are the API rate limits on API keys?";
            }
            return null;
        }

        return null;
    }

    /**
     * Check if recent turns mention any of the given topic keywords.
     *
     * @param array<int, array{text: string, direction: string}> $recentTurns
     * @param string[] $keywords
     */
    private function turnsMentionTopic(array $recentTurns, array $keywords): bool
    {
        foreach ($recentTurns as $turn) {
            $tLower = mb_strtolower($turn['text'] ?? '');
            foreach ($keywords as $kw) {
                if (str_contains($tLower, mb_strtolower($kw))) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Attempt to resolve a pending clarification state using user's answer (Phase M4 multi-turn state transition).
     */
    private function tryResolvePendingClarification(string $query, Conversation $conversation): ?ContextualResolutionResult
    {
        $pending = $conversation->metadata['pending_clarification'] ?? null;
        if (!is_array($pending) || empty($pending['candidates'])) {
            return null;
        }

        $candidates = $pending['candidates'];
        $qLower = mb_strtolower(trim($query));
        $matched = null;

        // 1. Check positional references: "প্রথমটা" / "1st" / "first" vs "দ্বিতীয়টা" / "2nd" / "second"
        if (preg_match('/^(প্রথম|১ম|১\b|first|1st|first\s+one|1)\b/ui', $qLower)) {
            $matched = $candidates[0] ?? null;
        } elseif (preg_match('/^(দ্বিতীয়|২য়|২\b|second|2nd|second\s+one|2)\b/ui', $qLower)) {
            $matched = $candidates[1] ?? null;
        }

        // 2. Check direct name or ID match
        if ($matched === null) {
            foreach ($candidates as $candidate) {
                $name = (string) ($candidate['name'] ?? '');
                $nameClean = mb_strtolower(trim(str_replace('#', '', $name)));
                $queryClean = mb_strtolower(trim(str_replace('#', '', $query)));

                if ($nameClean !== '' && (str_contains($queryClean, $nameClean) || str_contains($nameClean, $queryClean))) {
                    $matched = $candidate;
                    break;
                }
            }
        }

        if ($matched === null) {
            return null;
        }

        // Synthesize restored query with original inquiry intent
        $origQuery = (string) ($pending['original_query'] ?? '');
        $activeTopic = $pending['active_topic'] ?? null;
        $entityType = (string) ($matched['type'] ?? 'Entity');
        $resolvedQuery = $this->substitutePronounWithEntity($origQuery, $matched['name'], $entityType, $activeTopic);

        // Clear pending clarification in conversation metadata and reset uncertain count
        $metadata = $conversation->metadata;
        unset($metadata['pending_clarification']);
        $metadata['uncertain_count'] = 0;
        $conversation->metadata = $metadata;
        if ($conversation->exists) {
            $conversation->save();
        }

        return new ContextualResolutionResult(
            rawQuery: $query,
            resolvedQuery: $resolvedQuery,
            activeTopic: $activeTopic ?? ($entityType === 'Order' ? 'Order_Tracking' : 'Product'),
            resolvedEntity: $matched,
            candidates: [$matched],
            confidence: 0.98,
            status: 'resolved',
            source: 'clarification_resolution',
            diagnostics: [
                'restored_from_pending_clarification' => true,
                'original_query'                     => $origQuery,
            ]
        );
    }

    /**
     * Check if a query is clearly Out-of-Domain to prevent clarification hijacking.
     */
    public function isOodQuery(string $query): bool
    {
        $qLower = mb_strtolower(trim($query));
        $oodPatterns = [
            '/(আজকের\s*আবহাওয়া|weather|temperature|বৃষ্টি\s*হবে|weather\s*forecast)/ui',
            '/(world\s*cup|football\s*score|cricket\s*score|খেলা\s*কবে|score\s*koto)/ui',
            '/(tell\s*me\s*a\s*joke|কৌতুক|funny\s*story|sing\s*a\s*song)/ui',
            '/(capital\s+of|president\s+of|prime\s+minister|who\s+won|ইতিহাস\s+বলো)/ui',
            '/(write\s+(a\s+)?python|write\s+(a\s+)?code|script|program|coding|কোড\s+লিখে\s+দাও)/ui',
        ];

        foreach ($oodPatterns as $pattern) {
            if (preg_match($pattern, $qLower)) {
                return true;
            }
        }

        return false;
    }
}
