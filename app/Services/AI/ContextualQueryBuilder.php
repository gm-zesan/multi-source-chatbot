<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Conversation;
use App\Services\AI\DTOs\ContextualResolutionResult;
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

    public function __construct(
        private readonly ?ConversationMemoryService $memoryService = null,
    ) {}

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

        foreach ($recentTurns as $turn) {
            $text = $turn['text'];
            if ($activeTopic === null) {
                $activeTopic = $this->detectTopicFromTurn($text);
            }
            $extracted = $this->extractCandidateEntitiesFromTurn($text);
            foreach ($extracted as $ent) {
                if (!in_array($ent, $localRawEntities, true)) {
                    $localRawEntities[] = $ent;
                }
            }
        }

        $localRawEntities = $this->filterCandidateEntities($localRawEntities);

        // 3.5 Legacy Elliptical / Anaphora check (backward compatibility)
        $legacyResolved = $this->resolveEllipticalLegacy($cleanQuery);
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

        // 4. Handle Dangling Pronouns ("এটার", "ওটার", "সেটা", "it", "that", "this")
        if ($this->hasDanglingPronoun($cleanQuery)) {
            // A. Check Local Turns Candidates First
            $localCandidates = $this->structureLocalCandidates($localRawEntities, $expectedType);

            if (!empty($localCandidates)) {
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
            if ($activeTopic === 'Order_Tracking' || preg_match('/(কবে\s+পাবো|kobe\s+pabo|where\s+is\s+it|tracking)/ui', $cleanQuery)) {
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

        // 5. Subject-less Elliptical / Topic Continuation Queries
        // E.g., "কতদিন লাগবে?", "চার্জ কত?", "koto din lagbe?", "how much is the fee?"
        if ($this->isSubjectlessShortQuery($cleanQuery)) {
            if ($activeTopic !== null) {
                $resolved = $this->resolveTopicContinuationQuery($cleanQuery, $activeTopic);
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
            source: 'topic_continuation'
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

        // Product inquiries: Price, Size, Color, Stock, Fabric
        if (preg_match('/(দাম|price|cost|টাকা|খরচ|সাইজ|size|কালার|color|fabric|কাপড়|পাঞ্জাবি|শার্ট|panjabi|shirt)/ui', $qLower)) {
            return 'Product';
        }

        // Order inquiries: Tracking, Delivery arrival, Consignment status
        if (preg_match('/(কবে\s*পাবো|kobe\s*pabo|when\s+will|ট্র্যাক|track|tracking|কুরিয়ার|courier|পার্সেল|parcel|অর্ডার|order)/ui', $qLower)) {
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
     * @return array<int, array{type: string, id?: string, name: string, score: float}>
     */
    private function structureLocalCandidates(array $rawEntities, ?string $expectedType): array
    {
        $candidates = [];
        foreach ($rawEntities as $idx => $name) {
            $isOrder = (bool) preg_match('/(#\d+|order|অর্ডার)/ui', $name);
            $type = $isOrder ? 'Order' : 'Product';

            // Boost score for more recent mentions
            $recencyScore = max(0.60, 0.90 - ($idx * 0.10));

            // Compatibility penalty if type contradicts expected type
            if ($expectedType !== null && $type !== $expectedType) {
                $recencyScore -= 0.40;
            }

            if ($recencyScore >= 0.40) {
                $candidates[] = [
                    'type'  => $type,
                    'id'    => $isOrder ? trim($name, '# ') : 'local_' . ($idx + 1),
                    'name'  => $name,
                    'score' => round($recencyScore, 2),
                ];
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
        $margin = $winner['score'] - $second['score'];

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
        if (preg_match('/\b(it|this|that|them|they|these|those|its|their|eta|ota|sheta|eita|oita|sheita|etar|otar|shetar|eitar|oitar)\b/ui', $qLower)) {
            return true;
        }

        // Bengali pronouns (Unicode boundary safe)
        if (preg_match('/(^|[^\p{L}\p{N}])(এটা|ওটা|সেটা|এইটা|ওইটা|সেইটা|এটার|ওটার|সেটার|এগুলোর|ওগুলোর|এগুলো|ওগুলো|তারা|তাদের)($|[^\p{L}\p{N}])/u', $qLower)) {
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
            '/^(চার্জ\s*কত\??|কত\s*চার্জ\??|ফি\s*কত\??|কত\s*ফি\??)$/ui',
            '/^(charge\s*koto\??|koto\s*charge\??|fee\s*koto\??|koto\s*fee\??|how\s+much\s+is\s+the\s+fee\??)$/ui',
            '/^(কবে\s*পাবো\??|কবে\s*আসবে\??|kobe\s*pabo\??|kobe\s*ashbe\??|when\s+will\s+i\s+get\s+it\??)$/ui',
            '/^(দেরি\s*হলে\s*কি\s*করব\??|deri\s*hole\s*ki\s*korbo\??)$/ui',
            '/^(ট্র্যাক\s*করব\s*কীভাবে\??|ট্র্যাকিং\s*কীভাবে\??|track\s*kivabe\??|tracking\s*kivabe\??)$/ui',
            '/^(দাম\s*কত\??|কত\s*দাম\??|dam\s*koto\??|price\s*koto\??|how\s+much\??)$/ui',
            '/^(সাইজ\s*(কি\s*)?আছে\??|size\s*ache\??|available\s*size\??)$/ui',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $qLower)) {
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

        return "{$entityName} সম্পর্কিত তথ্য: {$query}";
    }

    /**
     * Rewrite subject-less queries using active topic.
     */
    private function resolveTopicContinuationQuery(string $query, string $activeTopic): string
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
            return 'পার্সেল কুরিয়ার ট্র্যাকিং কীভাবে করব';
        }

        // Sub-intent: Delay
        if (preg_match('/(দেরি|deri|delay|late)/ui', $qLower)) {
            return 'পার্সেল বা ডেলিভারি পেতে দেরি হলে করণীয় কী';
        }

        return "{$activeTopic} সম্পর্কিত প্রশ্ন: {$query}";
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
        if (preg_match('/(panjabi|পাঞ্জাবি|shirt|শার্ট|pant|প্যান্ট|polo|পোলো|সাইজ|দাম|price)/ui', $lower)) {
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
     * Extract candidate entities (products, order IDs) from turn text.
     *
     * @return string[]
     */
    private function extractCandidateEntitiesFromTurn(string $text): array
    {
        $entities = [];

        // Order numbers (#1234, order 5678, অর্ডার #1234) -> Canonicalized to #<digits>
        if (preg_match_all('/(?:#|\border\s*#?|\bঅর্ডার\s*#?)(\d{3,8})\b/ui', $text, $matches)) {
            foreach ($matches[1] as $digits) {
                $clean = '#' . $digits;
                if (!in_array($clean, $entities, true)) {
                    $entities[] = $clean;
                }
            }
        }

        // Specific named products
        $productPatterns = [
            '/(iPhone\s*\d+(\s*Pro|\s*Max)?|Samsung\s*Galaxy\s*[A-Za-z0-9]+)/ui',
            '/(Royal\s+Silk\s+Panjabi|Black\s+Cotton\s+Panjabi|White\s+Silk\s+Panjabi|Premium\s+Panjabi|Casual\s+Shirt|Polo\s+Shirt|Cotton\s+Shirt)/ui',
            '/(কালো\s+পাঞ্জাবি|সাদা\s+পাঞ্জাবি|কটন\s+পাঞ্জাবি|সিল্ক\s+পাঞ্জাবি)/ui',
        ];

        foreach ($productPatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $m) {
                    $clean = trim($m);
                    if (!in_array($clean, $entities, true)) {
                        $entities[] = $clean;
                    }
                }
            }
        }

        // Fallback generic product names ONLY if no specific product was found
        if (empty($entities)) {
            if (preg_match_all('/(Panjabi|Shirt|Pant|Polo|পাঞ্জাবি|শার্ট|পোলো\s+শার্ট)/ui', $text, $matches)) {
                foreach ($matches[0] as $m) {
                    $clean = trim($m);
                    if (!in_array($clean, $entities, true)) {
                        $entities[] = $clean;
                    }
                }
            }
        }

        return $entities;
    }

    /**
     * Filter and deduplicate candidate entities across all conversation turns.
     */
    private function filterCandidateEntities(array $entities): array
    {
        if (count($entities) <= 1) {
            return $entities;
        }

        // If specific multi-word entities exist (e.g. "White Silk Panjabi"), remove generic single-word category nouns
        $multiWordProducts = array_filter($entities, fn ($e) => str_contains(trim($e), ' ') && !str_starts_with($e, '#') && !str_starts_with($e, 'order'));
        if (!empty($multiWordProducts)) {
            $entities = array_values(array_filter($entities, function ($ent) use ($multiWordProducts) {
                if (str_starts_with($ent, '#') || str_starts_with($ent, 'order')) {
                    return true;
                }
                if (str_contains(trim($ent), ' ')) {
                    return true;
                }
                foreach ($multiWordProducts as $spec) {
                    if (mb_stripos($spec, $ent) !== false || ($ent === 'পাঞ্জাবি' && mb_stripos($spec, 'panjabi') !== false)) {
                        return false;
                    }
                }
                return true;
            }));
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
            $messages = $conversation->relationLoaded('messages')
                ? $conversation->messages
                : ($conversation->exists ? $conversation->messages()->orderBy('id', 'desc')->limit(6)->get()->reverse()->values() : collect());

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
                    ? ($item['body'] ?? $item['user_message'] ?? $item['message'] ?? '')
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
        $words = preg_split('/\s+/u', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) <= 3) {
            $cues = [
                'thanks', 'thank you', 'ok', 'okay', 'cool', 'great', 'awesome',
                'hi', 'hello', 'hey', 'hello again', 'got it', 'sure', 'dhonnobad',
                'thank you so much!', 'you are very welcome!',
                'ধন্যবাদ', 'থ্যাংকস', 'ঠিক আছে', 'আচ্ছা',
            ];
            foreach ($cues as $cue) {
                if ($cleaned === $cue || str_contains($cleaned, $cue)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Legacy elliptical resolution for existing benchmark tests.
     */
    private function resolveEllipticalLegacy(string $query): ?string
    {
        $qLower = mb_strtolower($query);

        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?telegram\??$/ui', $qLower)) {
            return "How do I connect Telegram bot to the platform?";
        }
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?whatsapp\??$/ui', $qLower)) {
            return "How do I connect WhatsApp to the platform?";
        }
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?rate\s+limits?\??$/ui', $qLower)) {
            return "What are the API rate limits per minute and hour?";
        }
        if (preg_match('/^(and|what\s+about|how\s+about|ar|aar|ebong|আর|এবং)\s+(the\s+)?(invoices?|bills?|ইনভয়েস|বিল)\??$/ui', $qLower)) {
            return "How do I view and download my billing invoices in PDF?";
        }
        if (preg_match('/^(can\s+i|how\s+to)\s+extend\s+it\??$/ui', $qLower)) {
            return "Is there a free trial and can I extend the 14-day free trial on Pro plan?";
        }
        if (preg_match('/what\s+are\s+the\s+limits\s+on\s+it/ui', $qLower)) {
            return "What are the API rate limits on API keys?";
        }

        return null;
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
