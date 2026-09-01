<?php

declare(strict_types=1);

namespace App\Services\Memory;

use App\Jobs\IngestConversationMemoryJob;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class ConversationMemoryService
{
    public function __construct(
        private readonly ConversationMemoryClient $client,
        private readonly MemoryRelevanceGate $relevanceGate,
    ) {}

    /**
     * Retrieve relevant conversation graph memory context for prompt injection.
     * Returns formatted markdown context if memories pass the relevance gate, or null.
     */
    public function retrieveContext(?Conversation $conversation, string $query, int $workspaceId): ?string
    {
        if ($conversation === null) {
            return null;
        }

        // 1. Check Relevance Gate before issuing network call
        if (! $this->relevanceGate->shouldRetrieve($query, $conversation)) {
            return null;
        }

        // 2. Resolve Customer ID
        $customerId = (string) ($conversation->external_user_id 
            ?? $conversation->contact_id 
            ?? "cust_conv_{$conversation->id}");

        try {
            $result = $this->client->search(
                workspaceId: $workspaceId,
                customerId: $customerId,
                query: $query,
                limit: 3
            );

            if (($result['has_memories'] ?? false) && ! empty($result['formatted_memory_context'])) {
                Log::debug('[ConversationMemoryService] Subgraph context retrieved', [
                    'customer_id'    => $customerId,
                    'memories_count' => $result['memories_count'] ?? 0,
                    'latency_ms'     => $result['latency_ms'] ?? 0,
                ]);
                return (string) $result['formatted_memory_context'];
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
