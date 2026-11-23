<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\Retention\AbandonedLeadRecoveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAbandonedLeadFollowUpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly int $conversationId,
    ) {
        $this->connection = 'database';
        $this->queue = 'retention';
    }

    public function handle(AbandonedLeadRecoveryService $service): void
    {
        $conversation = Conversation::find($this->conversationId);

        if (! $conversation) {
            Log::warning('[SendAbandonedLeadFollowUpJob] Conversation not found', [
                'conversation_id' => $this->conversationId,
            ]);
            return;
        }

        $result = $service->sendFollowUp($conversation);

        Log::info('[SendAbandonedLeadFollowUpJob] Follow-up processed', [
            'conversation_id' => $this->conversationId,
            'result'          => $result,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[SendAbandonedLeadFollowUpJob] Job failed', [
            'conversation_id' => $this->conversationId,
            'error'           => $exception->getMessage(),
        ]);
    }
}
