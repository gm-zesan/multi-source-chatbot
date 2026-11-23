<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OrderFeedback;
use App\Services\Retention\PostDeliveryFeedbackService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendPostDeliveryFeedbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public readonly int $orderFeedbackId,
    ) {
        $this->connection = 'database';
        $this->queue = 'retention';
    }

    public function handle(PostDeliveryFeedbackService $service): void
    {
        $feedback = OrderFeedback::find($this->orderFeedbackId);

        if (! $feedback) {
            Log::warning('[SendPostDeliveryFeedbackJob] OrderFeedback not found', [
                'feedback_id' => $this->orderFeedbackId,
            ]);
            return;
        }

        $sent = $service->sendFeedbackRequest($feedback);

        Log::info('[SendPostDeliveryFeedbackJob] Feedback request sent result', [
            'feedback_id' => $this->orderFeedbackId,
            'success'     => $sent,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[SendPostDeliveryFeedbackJob] Job failed', [
            'feedback_id' => $this->orderFeedbackId,
            'error'       => $exception->getMessage(),
        ]);
    }
}
