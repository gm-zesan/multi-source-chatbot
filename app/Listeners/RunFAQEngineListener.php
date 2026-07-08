<?php

namespace App\Listeners;

use App\Events\IncomingMessageReceived;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQAnswerEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class RunFAQEngineListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue connection to use.
     */
    public $connection = 'database';

    /**
     * The queue name to use.
     */
    public $queue = 'faq';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new listener instance.
     */
    public function __construct(
        private readonly FAQAnswerEngine $answerEngine,
        private readonly ConversationService $conversationService,
    ) {}

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
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Handle the event.
     */
    public function handle(IncomingMessageReceived $event): void
    {
        Log::info('[FAQ Listener] Starting FAQ engine', [
            'conversation_id' => $event->conversation->id,
            'message_id'      => $event->message->id,
        ]);

        try {
            $result = $this->answerEngine->answer(
                query: $event->message->body,
                workspaceId: $event->account->workspace_id,
                conversationId: $event->conversation->id,
                messageId: $event->message->id,
            );

            if ($result->answered && $result->getAnswer()) {
                $this->conversationService->saveOutgoing(
                    conversation: $event->conversation,
                    message: $result->getAnswer(),
                    response: [
                        'source'     => 'faq_engine',
                        'faq_id'     => $result->faq?->id,
                        'confidence' => $result->confidence,
                        'match_type' => $result->matchType,
                    ],
                );

                Log::info('[FAQ Listener] Auto reply sent', [
                    'conversation_id' => $event->conversation->id,
                    'faq_id'          => $result->faq?->id,
                    'confidence'      => $result->confidence,
                ]);
            } else {
                Log::info('[FAQ Listener] No answer found, routing to human workflow', [
                    'conversation_id' => $event->conversation->id,
                    'best_confidence' => $result->confidence,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[FAQ Listener] FAQ engine failed', [
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
