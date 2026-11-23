<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\IncomingMessageReceived;
use App\Services\Retention\PostDeliveryFeedbackService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessPostDeliveryFeedbackListener implements ShouldQueue
{
    use InteractsWithQueue;

    public string $connection = 'database';
    public string $queue = 'retention';
    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        private readonly PostDeliveryFeedbackService $feedbackService,
    ) {
        $this->connection = 'database';
        $this->queue = 'retention';
    }

    public function handle(IncomingMessageReceived $event): void
    {
        $text = (string) ($event->message->body ?? '');
        if ($text === '') {
            return;
        }

        $feedback = $this->feedbackService->recordFeedbackFromMessage($event->conversation, $text);

        if ($feedback) {
            Log::info('[ProcessPostDeliveryFeedbackListener] Recorded CSAT feedback from incoming message', [
                'feedback_id'     => $feedback->id,
                'conversation_id' => $event->conversation->id,
                'rating'          => $feedback->rating,
            ]);
        }
    }

    public function failed(IncomingMessageReceived $event, \Throwable $exception): void
    {
        Log::error('[ProcessPostDeliveryFeedbackListener] Listener failed', [
            'conversation_id' => $event->conversation->id,
            'error'           => $exception->getMessage(),
        ]);
    }
}
