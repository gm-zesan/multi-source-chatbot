<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\Conversation;
use App\Services\AI\DTOs\ContextualResolutionResult;

/**
 * Phase M4: Interactive Context Clarification & Answerability Guard
 *
 * Implements strict short-circuiting on context ambiguity:
 * 1. M2 Ambiguous/Unresolved -> NO FAQ search, NO KGM search, NO LLM call, NO fabrication -> Clarification Question -> STOP.
 * 2. User-Safe Candidate Display: Strips internal graph metadata (embeddings, internal node IDs, cypher scores).
 * 3. Multi-turn State Transition: Persists pending clarification in conversation metadata so subsequent user turn
 *    restores original inquiry intent.
 * 4. Human Handoff Safeguard: 3 consecutive uncertain turns trigger human agent handoff.
 */
class ClarificationManager
{
    public const MAX_CONSECUTIVE_UNCERTAIN_TURNS = 3;

    /**
     * Short-circuit handling for contextual ambiguity.
     * Guaranteed: Zero FAQ retrieval, zero KGM retrieval, zero LLM calls, zero hallucinations.
     *
     * @return array<string, mixed> Structured response matching CustomerSupportService contract
     */
    public function handleAmbiguity(
        ?Conversation $conversation,
        string $rawQuery,
        ContextualResolutionResult $contextResult,
        int $workspaceId
    ): array {
        $metadata = $conversation?->metadata ?? [];
        $prevCount = (int) ($metadata['uncertain_count'] ?? 0);
        $consecutiveCount = $prevCount + 1;

        // 3 consecutive uncertain turns -> Graceful fallback (transfer to human disabled per user instruction)
        if ($consecutiveCount >= self::MAX_CONSECUTIVE_UNCERTAIN_TURNS) {
            if ($conversation !== null) {
                $metadata['uncertain_count'] = 0;
                unset($metadata['pending_clarification']);
                $conversation->metadata = $metadata;
                if ($conversation->exists) {
                    $conversation->save();
                }
            }

            return [
                'reply'                   => "আমি বিষয়টি সঠিকভাবে বুঝতে পারিনি। অনুগ্রহ করে পণ্য বা অর্ডারের বিস্তারিত তথ্য লিখে পুনরায় জানাবেন কি?",
                'route'                   => 'uncertain',
                'confidence'              => 0.0,
                'suggestions'             => [],
                'sources'                 => [],
                'is_handoff'              => false, // Transfer to human disabled per user request
                'memory_context'          => null,
                'business_context'        => null,
                'retrieval_hits'          => new \Illuminate\Database\Eloquent\Collection(),
                'top_hit'                 => null,
                'answered'                => false,
                'answerability_decision'  => [
                    'status'           => 'UNANSWERABLE',
                    'confidence_score' => 0.0,
                    'reasons'          => ['rule' => 'max_uncertain_turns_graceful_fallback'],
                ],
                'raw_llm_response'        => [
                    'provider'                 => 'deterministic_m4_fallback',
                    'model'                    => 'none',
                    'raw_reply_text'           => "আমি বিষয়টি সঠিকভাবে বুঝতে পারিনি। অনুগ্রহ করে পণ্য বা অর্ডারের বিস্তারিত তথ্য লিখে পুনরায় জানাবেন কি?",
                    'grounded_documents_count' => 0,
                    'grounded_faq_questions'   => [],
                ],
                'routing_telemetry'       => [
                    'route'           => 'uncertain',
                    'short_circuited' => true,
                    'phase'           => 'M4_max_uncertain_fallback',
                ],
                'lexicon_telemetry'       => [],
            ];
        }

        // Build user-safe clarification question
        $clarificationText = $this->buildClarificationQuestion($contextResult);
        $safeCandidates = $this->sanitizeCandidates($contextResult->candidates);

        // Persist pending clarification state in conversation metadata
        if ($conversation !== null) {
            $metadata['uncertain_count'] = $consecutiveCount;
            $metadata['pending_clarification'] = [
                'original_query'    => $rawQuery,
                'active_topic'      => $contextResult->activeTopic,
                'expected_type'     => $this->inferExpectedType($contextResult),
                'candidates'        => $safeCandidates,
                'consecutive_count' => $consecutiveCount,
                'created_at'        => now()->toIso8601String(),
            ];
            $conversation->metadata = $metadata;
            if ($conversation->exists) {
                $conversation->save();
            }
        }

        $suggestions = array_map(fn ($c) => $c['name'], $safeCandidates);

        return [
            'reply'                   => $clarificationText,
            'route'                   => 'uncertain',
            'confidence'              => $contextResult->confidence,
            'suggestions'             => $suggestions,
            'sources'                 => [],
            'is_handoff'              => false,
            'memory_context'          => null,
            'business_context'        => null,
            'retrieval_hits'          => new \Illuminate\Database\Eloquent\Collection(),
            'top_hit'                 => null,
            'answered'                => false,
            'answerability_decision'  => [
                'status'           => 'AMBIGUOUS',
                'confidence_score' => $contextResult->confidence,
                'reasons'          => [
                    'rule'       => 'm4_context_clarification_short_circuit',
                    'candidates' => $safeCandidates,
                ],
            ],
            'raw_llm_response'        => [
                'provider'                 => 'deterministic_m4_clarifier',
                'model'                    => 'none',
                'raw_reply_text'           => $clarificationText,
                'grounded_documents_count' => 0,
                'grounded_faq_questions'   => [],
            ],
            'routing_telemetry'       => [
                'route'           => 'uncertain',
                'short_circuited' => true,
                'phase'           => 'M4_context_clarification',
            ],
            'lexicon_telemetry'       => [],
        ];
    }

    /**
     * Build natural, polite user-facing clarification question without leaking internal graph metadata.
     */
    public function buildClarificationQuestion(ContextualResolutionResult $contextResult): string
    {
        $safeCandidates = $this->sanitizeCandidates($contextResult->candidates);

        if (count($safeCandidates) >= 2) {
            $first = $safeCandidates[0]['name'];
            $second = $safeCandidates[1]['name'];
            $type = $safeCandidates[0]['type'] ?? 'Entity';

            if ($type === 'Order' || str_starts_with($first, '#')) {
                return "আপনি কোন অর্ডারটি সম্পর্কে জানতে চাচ্ছেন—{$first} নাকি {$second}?";
            }

            if ($type === 'Product') {
                return "আপনি কোন পণ্যটি সম্পর্কে জানতে চাচ্ছেন—{$first} নাকি {$second}?";
            }

            return "আপনি কোন বিষয়টি সম্পর্কে জানতে চাচ্ছেন—{$first} নাকি {$second}?";
        }

        // Generic safe clarification for unresolved anaphora
        $topic = $contextResult->activeTopic;
        if ($topic === 'Order_Tracking' || preg_match('/(পার্সেল|অর্ডার|parcel|order|package|কুরিয়ার|courier)/ui', $contextResult->rawQuery)) {
            return "আপনি কোন অর্ডারটি সম্পর্কে জানতে চাচ্ছেন অনুগ্রহ করে অর্ডার নম্বরটি জানাবেন কি?";
        }

        if ($topic === 'Product' || $topic === 'Product_Inquiry') {
            return "আপনি কোন পণ্যটি সম্পর্কে জানতে চাচ্ছেন অনুগ্রহ করে পণ্যের নামটি জানাবেন কি?";
        }

        return "আপনি কোন পণ্য বা অর্ডারটি সম্পর্কে জানতে চাচ্ছেন অনুগ্রহ করে বিস্তারিত জানাবেন কি?";
    }

    /**
     * Sanitize candidate list to strictly prevent private/internal graph metadata leakage.
     * Retains ONLY safe user-facing display fields ('type', 'id', 'name').
     *
     * @param array<int, array> $candidates
     * @return array<int, array{type: string, id: string, name: string}>
     */
    public function sanitizeCandidates(array $candidates): array
    {
        $sanitized = [];
        foreach ($candidates as $candidate) {
            $name = trim((string) ($candidate['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $id = trim((string) ($candidate['id'] ?? $name));
            // Prevent leakage of internal database or UUID identifiers
            if (str_starts_with($id, 'node_') || str_starts_with($id, 'neo4j_') || mb_strlen($id) > 20) {
                $id = $name;
            }

            $sanitized[] = [
                'type' => (string) ($candidate['type'] ?? 'Entity'),
                'id'   => $id,
                'name' => $name,
            ];
        }

        return $sanitized;
    }

    /**
     * Infer expected entity type from ContextualResolutionResult.
     */
    private function inferExpectedType(ContextualResolutionResult $contextResult): string
    {
        if ($contextResult->resolvedEntity !== null && !empty($contextResult->resolvedEntity['type'])) {
            return $contextResult->resolvedEntity['type'];
        }

        if (!empty($contextResult->candidates[0]['type'])) {
            return $contextResult->candidates[0]['type'];
        }

        return match ($contextResult->activeTopic) {
            'Order_Tracking' => 'Order',
            'Product', 'Product_Inquiry' => 'Product',
            default => 'Entity',
        };
    }
}
