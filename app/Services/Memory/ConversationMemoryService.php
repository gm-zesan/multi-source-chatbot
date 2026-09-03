<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Jobs\IngestConversationMemoryJob;
use App\Models\Conversation;
use App\Services\AI\DTOs\ContextualResolutionResult;
use Illuminate\Support\Facades\Log;

class ConversationMemoryService
{
    public function __construct(
        private readonly ConversationMemoryClient $client,
        private readonly MemoryRelevanceGate $relevanceGate,
    ) {}

    /**
     * Resolve unified persistent customer identity.
     * Order of precedence:
     * 1. external_user_id (e.g. Facebook PSID, Telegram Chat ID, Web User ID)
     * 2. contact_id (CRM Contact record ID)
     * 3. Ephemeral conversation fallback: cust_conv_{conversation_id}
     */
    public function resolveCustomerId(?Conversation $conversation): ?string
    {
        if ($conversation === null) {
            return null;
        }

        $id = $conversation->external_user_id ?? $conversation->contact_id;
        if (!empty($id)) {
            return (string) $id;
        }

        return $conversation->id ? "cust_conv_{$conversation->id}" : null;
    }

    /**
     * Retrieve relevant conversation graph memory context for prompt injection.
     * Returns formatted markdown context if memories pass the relevance gate, or null.
     */
    public function retrieveContext(
        ?Conversation $conversation,
        string $query,
        int $workspaceId,
        ?ContextualResolutionResult $contextResult = null
    ): ?string {
        $structured = $this->retrieveStructuredMemory($conversation, $query, $workspaceId, $contextResult);
        return $structured['formatted_memory_context'] ?? null;
    }

    /**
     * Retrieve structured separation of short-term dialogue context and long-term persistent facts.
     *
     * @return array{
     *     customer_id: ?string,
     *     short_term: array{recent_turns: array, active_topic: ?string},
     *     long_term: array{active_facts: array, superseded_facts: array},
     *     formatted_memory_context: ?string,
     * }|null
     */
    public function retrieveStructuredMemory(
        ?Conversation $conversation,
        string $query,
        int $workspaceId = 1,
        ?ContextualResolutionResult $contextResult = null
    ): ?array {
        if ($conversation === null) {
            return null;
        }

        // 1. Check Relevance Gate before issuing network call (isolates static FAQ/policies)
        if (! $this->relevanceGate->shouldRetrieve($query, $conversation, $contextResult)) {
            return null;
        }

        // 2. Resolve Customer ID
        $customerId = $this->resolveCustomerId($conversation);
        if ($customerId === null) {
            return null;
        }

        try {
            $result = $this->client->search(
                workspaceId: $workspaceId,
                customerId: $customerId,
                query: $query,
                limit: 5
            );

            if (($result['has_memories'] ?? false) && (!empty($result['memories']) || !empty($result['formatted_memory_context']))) {
                $rawMemories = $result['memories'] ?? [];

                // Apply Conflict Policy: Latest valid fact wins precedence per attribute
                $resolvedMemories = $this->resolveConflictPrecedence($rawMemories);

                // Format long-term context prioritizing active facts
                $formattedContext = !empty($resolvedMemories['formatted_context'])
                    ? $resolvedMemories['formatted_context']
                    : (string) ($result['formatted_memory_context'] ?? '');

                Log::debug('[ConversationMemoryService] Subgraph context retrieved', [
                    'customer_id'    => $customerId,
                    'active_count'   => count($resolvedMemories['active_facts']),
                    'total_count'    => count($rawMemories),
                    'latency_ms'     => $result['latency_ms'] ?? 0,
                ]);

                return [
                    'customer_id'              => $customerId,
                    'short_term'               => $this->getShortTermContext($conversation, 3),
                    'long_term'                => $resolvedMemories,
                    'formatted_memory_context' => $formattedContext,
                ];
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('[ConversationMemoryService] Failed to retrieve context', [
                'customer_id' => $customerId,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Resolve attribute conflicts: Newer facts take precedence over older facts for the same attribute.
     * Historical facts are preserved in 'superseded_facts' for auditability without deletion.
     *
     * @param array<int, array> $memories
     * @return array{active_facts: array, superseded_facts: array, formatted_context: string}
     */
    public function resolveConflictPrecedence(array $memories): array
    {
        if (empty($memories)) {
            return [
                'active_facts'      => [],
                'superseded_facts'  => [],
                'formatted_context' => '',
            ];
        }

        $activeByAttribute = [];
        $superseded = [];

        foreach ($memories as $mem) {
            $relation = (string) ($mem['relation'] ?? $mem['predicate'] ?? $mem['type'] ?? 'fact');
            $status = (string) ($mem['status'] ?? 'current');

            if ($status === 'past' || $status === 'superseded') {
                $superseded[] = $mem;
                continue;
            }

            if (isset($activeByAttribute[$relation])) {
                $existing = $activeByAttribute[$relation];
                $existingTs = $existing['updated_at'] ?? $existing['timestamp'] ?? null;
                $currentTs = $mem['updated_at'] ?? $mem['timestamp'] ?? null;

                if ($currentTs !== null && $existingTs !== null && $currentTs > $existingTs) {
                    $superseded[] = $existing;
                    $activeByAttribute[$relation] = $mem;
                } else {
                    $superseded[] = $mem;
                }
            } else {
                $activeByAttribute[$relation] = $mem;
            }
        }

        $activeFacts = array_values($activeByAttribute);

        // Format clean prompt context from active facts
        $formattedLines = ["Customer Historical Preferences & Established Facts:"];
        foreach ($activeFacts as $fact) {
            $relation = str_replace('_', ' ', strtolower((string) ($fact['relation'] ?? '')));
            $obj = (string) ($fact['object'] ?? '');
            if ($relation && $obj) {
                $formattedLines[] = "- " . ucfirst($relation) . ": " . $obj;
            } elseif (!empty($fact['description'])) {
                $formattedLines[] = "- " . $fact['description'];
            }
        }

        $formattedContext = count($formattedLines) > 1 ? implode("\n", $formattedLines) : '';

        return [
            'active_facts'      => $activeFacts,
            'superseded_facts'  => $superseded,
            'formatted_context' => $formattedContext,
        ];
    }

    /**
     * Extract recent substantive conversation turns (max 3) for short-term context.
     * Skips pure conversational pleasantries.
     *
     * @return array{recent_turns: array, active_topic: ?string}
     */
    public function getShortTermContext(?Conversation $conversation, int $maxTurns = 3): array
    {
        if ($conversation === null) {
            return ['recent_turns' => [], 'active_topic' => null];
        }

        $messages = $conversation->relationLoaded('messages')
            ? $conversation->messages
            : ($conversation->exists ? $conversation->messages()->orderBy('id', 'desc')->limit(6)->get()->reverse()->values() : collect());

        $substantiveTurns = [];
        $activeTopic = null;

        for ($i = $messages->count() - 1; $i >= 0; $i--) {
            if (count($substantiveTurns) >= $maxTurns) {
                break;
            }

            $msg = $messages->get($i);
            $body = trim((string) $msg->body);
            if ($body === '' || mb_strlen($body) < 2) {
                continue;
            }

            if ($activeTopic === null) {
                $activeTopic = $this->detectTopicSnippet($body);
            }

            $substantiveTurns[] = [
                'direction' => $msg->direction,
                'body'      => $body,
                'timestamp' => $msg->created_at?->toIso8601String(),
            ];
        }

        return [
            'recent_turns' => array_reverse($substantiveTurns),
            'active_topic' => $activeTopic,
        ];
    }

    /**
     * Detect domain topic snippet from a turn.
     */
    private function detectTopicSnippet(string $text): ?string
    {
        $lower = mb_strtolower($text);
        if (preg_match('/(delivery|shipping|ডেলিভারি|কুরিয়ার|courier|পার্সেল|parcel)/ui', $lower)) {
            return 'Delivery';
        }
        if (preg_match('/(return|refund|exchange|ফেরত|রিটার্ন|রিফান্ড|বদলানো)/ui', $lower)) {
            return 'Return_Exchange';
        }
        if (preg_match('/(payment|bkash|nagad|card|পেমেন্ট|বিকাশ|নগদ)/ui', $lower)) {
            return 'Payment';
        }
        if (preg_match('/(warranty|guarantee|ওয়ারেন্টি|ভাঙা|নষ্ট|damage)/ui', $lower)) {
            return 'Warranty';
        }
        if (preg_match('/(size|সাইজ|মাপ|color|কালার)/ui', $lower)) {
            return 'Product_Specification';
        }
        return null;
    }

    /**
     * Ingest conversation dialogue asynchronously into Conversation Graph Memory.
     */
    public function ingestConversation(Conversation $conversation): void
    {
        try {
            IngestConversationMemoryJob::dispatch($conversation);
        } catch (\Throwable $e) {
            Log::warning('[ConversationMemoryService] Failed to dispatch ingest job', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}

