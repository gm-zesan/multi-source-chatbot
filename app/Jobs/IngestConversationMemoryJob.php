<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\Memory\ConversationMemoryClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IngestConversationMemoryJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
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
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 60;

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
     * The unique ID of the job to prevent duplicate dispatches in queue.
     */
    public function uniqueId(): string
    {
        return (string) $this->conversation->id;
    }

    /**
     * Execute the job.
     */
    public function handle(ConversationMemoryClient $memoryClient): void
    {
        $lockKey = "memory_ingest:conv:{$this->conversation->id}";
        $lock = Cache::lock($lockKey, 60);

        // Concurrency Guard: If another worker is actively ingesting for this conversation,
        // release job back to queue with delay to serialize execution.
        if (! $lock->get()) {
            Log::info('[IngestConversationMemoryJob] Active ingestion lock detected for conversation, releasing back to queue', [
                'conversation_id' => $this->conversation->id,
            ]);

            if (isset($this->job)) {
                $this->release(3);
            }
            return;
        }

        try {
            $this->conversation->refresh();
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

            // 4. Retrieve uningested message turns in chronological order (Watermark Guard)
            $metadata = (array) ($this->conversation->metadata ?? []);
            $lastIngestedId = (int) ($metadata['last_memory_ingested_message_id'] ?? 0);

            $msgQuery = $this->conversation->messages();
            if ($lastIngestedId > 0) {
                $messageModels = $msgQuery->where('id', '>', $lastIngestedId)
                    ->oldest('id')
                    ->take(10)
                    ->get();
            } else {
                // Initial run: take up to last 6 messages in chronological order
                $messageModels = $msgQuery->latest('id')
                    ->take(6)
                    ->get()
                    ->reverse()
                    ->values();
            }

            if ($messageModels->isEmpty()) {
                Log::debug('[IngestConversationMemoryJob] No new messages to ingest since watermark', [
                    'conversation_id' => $this->conversation->id,
                    'last_ingested_id' => $lastIngestedId,
                ]);
                return;
            }

            $messages = $messageModels->map(function ($msg) {
                $body = (string) $msg->body;
                $metadata = (array) ($msg->metadata ?? []);

                // Clarification Ingestion Safety: If this turn was a clarification selection,
                // use the contextualized full query to avoid extracting broken/isolated facts into Neo4j!
                if (!empty($metadata['contextual_intent'])) {
                    $body = (string) $metadata['contextual_intent'];
                }

                return [
                    'direction' => $msg->direction,
                    'body' => $body,
                    'timestamp' => $msg->created_at?->toIso8601String(),
                ];
            })->values()->toArray();

            // 5. Ingest into Conversation Graph Memory (Graphiti + Neo4j)
            $result = $memoryClient->ingest(
                workspaceId: $workspaceId,
                customerId: $customerId,
                conversationId: (string) $this->conversation->id,
                channel: $channel,
                messages: $messages,
            );

            $isSuccess = ($result['success'] ?? null) === true
                || (!isset($result['error']) && (!empty($result['edges_created']) || !empty($result['entities_extracted'])));

            if ($isSuccess) {
                $newWatermark = (int) $messageModels->max('id');
                $this->conversation->refresh();
                $metadata = (array) ($this->conversation->metadata ?? []);
                $metadata['last_memory_ingested_message_id'] = max(
                    $newWatermark,
                    (int) ($metadata['last_memory_ingested_message_id'] ?? 0)
                );

                // Record Background Memory Ingestion LLM Usage if tokens were consumed
                if (!empty($result['llm_usage']) && (($result['llm_usage']['prompt_tokens'] ?? 0) + ($result['llm_usage']['completion_tokens'] ?? 0) > 0)) {
                    $usage = $result['llm_usage'];
                    if (!isset($metadata['llm_usage_history'])) {
                        $metadata['llm_usage_history'] = [];
                    }
                    $metadata['llm_usage_history'][] = [
                        'provider' => $usage['provider'] ?? 'deepseek',
                        'model' => $usage['model'] ?? 'deepseek-chat',
                        'status' => 'INGESTED (Memory)',
                        'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                        'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                        'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
                    ];

                    // Also update the latest outbound Message record with memory_tokens breakdown
                    $lastOutbound = $this->conversation->messages()
                        ->where('direction', 'outbound')
                        ->latest('id')
                        ->first();

                    if ($lastOutbound) {
                        $msgMeta = (array) ($lastOutbound->metadata ?? []);
                        $msgUsage = $msgMeta['llm_usage'] ?? [];

                        $memPrompt = (int) ($usage['prompt_tokens'] ?? 0);
                        $memCompletion = (int) ($usage['completion_tokens'] ?? 0);
                        $memTotal = (int) ($usage['total_tokens'] ?? 0);

                        $msgUsage['memory_tokens'] = [
                            'prompt_tokens' => $memPrompt,
                            'completion_tokens' => $memCompletion,
                            'total_tokens' => $memTotal,
                        ];

                        $msgUsage['prompt_tokens'] = ((int) ($msgUsage['prompt_tokens'] ?? 0)) + $memPrompt;
                        $msgUsage['completion_tokens'] = ((int) ($msgUsage['completion_tokens'] ?? 0)) + $memCompletion;
                        $msgUsage['total_tokens'] = ((int) ($msgUsage['total_tokens'] ?? 0)) + $memTotal;

                        $msgMeta['llm_usage'] = $msgUsage;
                        $lastOutbound->update(['metadata' => $msgMeta]);
                    }
                }

                $this->conversation->update(['metadata' => $metadata]);

                Log::info('[IngestConversationMemoryJob] Successfully ingested conversation into Graph Memory', [
                    'conversation_id' => $this->conversation->id,
                    'customer_id' => $customerId,
                    'edges_created' => $result['edges_created'] ?? 0,
                    'entities_count' => $result['entities_extracted'] ?? 0,
                    'llm_tokens' => $result['llm_usage']['total_tokens'] ?? 0,
                    'watermark_id' => $newWatermark,
                ]);
            } else {
                Log::warning('[IngestConversationMemoryJob] Ingestion returned unsuccessful response, watermark not advanced', [
                    'conversation_id' => $this->conversation->id,
                    'result' => $result,
                ]);

                if (isset($this->job) && $this->attempts() < $this->tries) {
                    $this->release(10);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[IngestConversationMemoryJob] Failed to ingest conversation memory', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
            ]);

            if (isset($this->job) && $this->attempts() < $this->tries) {
                $this->release(10);
            }
        } finally {
            $lock->release();
        }
    }
}
