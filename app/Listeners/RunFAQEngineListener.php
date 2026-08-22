<?php

declare(strict_types=1);

namespace App\Listeners;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Events\IncomingMessageReceived;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Support\ChannelManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RunFAQEngineListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue connection to use.
     */
    public string $connection = 'database';

    /**
     * The queue name to use.
     */
    public string $queue = 'faq';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Maximum number of allowed exceptions.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new listener instance.
     */
    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly FAQSearch $faqSearch,
    ) {
        $this->connection = 'database';
        $this->queue = 'faq';
    }

    /**
     * The backoff strategy.
     *
     * @return int[]
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Handle the event.
     */
    public function handle(IncomingMessageReceived $event): void
    {
        Log::info('[FAQ Listener] Starting AI Customer Support Agent', [
            'conversation_id' => $event->conversation->id,
            'message_id'      => $event->message->id,
        ]);

        try {
            $retrievalTool = new KnowledgeRetrievalTool(
                faqSearch: $this->faqSearch,
                workspaceId: $event->account->workspace_id,
            );

            $agent = new CustomerSupportAgent(
                conversation: $event->conversation,
                retrievalTool: $retrievalTool,
            );

            $provider = config('ai.default', 'openrouter');
            $model = config('ai.default_model', 'deepseek/deepseek-chat');

            $response = $agent->prompt($event->message->body, provider: $provider, model: $model);
            $replyText = (string) $response;

            if (trim($replyText) !== '') {
                $deliveryResponse = [];
                $event->account->loadMissing('channel');
                if ($event->account->channel) {
                    try {
                        $driver = ChannelManager::driver($event->account->channel->slug);
                        $deliveryResponse = $driver->send($event->account, $event->conversation, $replyText) ?? [];
                    } catch (\Throwable $sendError) {
                        Log::warning('[FAQ Listener] Failed to deliver outbound message to channel', [
                            'conversation_id' => $event->conversation->id,
                            'channel'         => $event->account->channel->slug,
                            'error'           => $sendError->getMessage(),
                        ]);
                    }
                }

                $this->conversationService->saveOutgoing(
                    conversation: $event->conversation,
                    message: $replyText,
                    response: array_merge($deliveryResponse, [
                        'source'     => 'customer_support_agent',
                        'provider'   => $provider,
                        'model'      => $model,
                        'usage'      => $response->usage ?? null,
                    ]),
                );

                Log::info('[FAQ Listener] AI Customer Support Agent response saved and sent', [
                    'conversation_id' => $event->conversation->id,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[FAQ Listener] AI Customer Support Agent failed', [
                'conversation_id' => $event->conversation->id,
                'error'           => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(IncomingMessageReceived $event, \Throwable $exception): void
    {
        Log::error('[FAQ Listener] Permanently failed after retries', [
            'conversation_id' => $event->conversation->id,
            'error'           => $exception->getMessage(),
        ]);
    }
}
