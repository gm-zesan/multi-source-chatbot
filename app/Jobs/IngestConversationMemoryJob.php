<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\Memory\ConversationMemoryClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IngestConversationMemoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly Conversation $conversation,
    ) {
        $this->connection = 'database';
        $this->queue = 'memory';
    }

    /**
     * Execute the job.
     */
    public function handle(ConversationMemoryClient $memoryClient): void
    {
        try {
            $this->conversation->loadMissing(['account.channel', 'messages']);

            // 1. Resolve Workspace ID
            $workspaceId = (int) ($this->conversation->account?->workspace_id 
                ?? $this->conversation->workspace_id 
                ?? 1);

            // 2. Resolve Customer ID (prefer external_user_id, fallback to conversation scoped ID)
            $customerId = (string) ($this->conversation->external_user_id 
                ?? $this->conversation->contact_id 
                ?? "cust_conv_{$this->conversation->id}");

            // 3. Resolve Channel
            $channel = (string) ($this->conversation->account?->channel?->slug 
                ?? $this->conversation->channel 
                ?? 'web');

            // 4. Retrieve recent message turns in chronological order
            $messages = $this->conversation->messages()
                ->latest('id')
                ->take(6)
                ->get()
                ->reverse()
                ->map(fn ($msg) => [
                    'direction' => $msg->direction,
                    'body'      => (string) $msg->body,
                    'timestamp' => $msg->created_at?->toIso8601String(),
                ])
                ->values()
                ->toArray();

            if (empty($messages)) {
                return;
            }

            // 5. Ingest into Conversation Graph Memory (Graphiti + Neo4j)
            $result = $memoryClient->ingest(
                workspaceId: $workspaceId,
                customerId: $customerId,
                conversationId: (string) $this->conversation->id,
                channel: $channel,
                messages: $messages,
            );

            Log::info('[IngestConversationMemoryJob] Successfully ingested conversation into Graph Memory', [
                'conversation_id'   => $this->conversation->id,
                'customer_id'       => $customerId,
                'edges_created'     => $result['edges_created'] ?? 0,
                'entities_count'    => $result['entities_extracted'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[IngestConversationMemoryJob] Failed to ingest conversation memory', [
                'conversation_id' => $this->conversation->id,
                'error'           => $e->getMessage(),
            ]);
            // Non-critical: Do not rethrow to avoid blocking queues
        }
    }
}
