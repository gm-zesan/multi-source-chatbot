<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AITelemetryRecorded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class RecordAITelemetryListener implements ShouldQueue
{
    /**
     * The name of the queue on which the event should be processed.
     *
     * @var string|null
     */
    public $queue = 'telemetry';

    /**
     * Handle the telemetry event as a decoupled observer.
     * Invariant: Telemetry failure must never affect or break conversational responses.
     */
    public function handle(AITelemetryRecorded $event): void
    {
        try {
            Log::info('[AITelemetry] Observability event received', [
                'workspace_id'    => $event->workspaceId,
                'conversation_id' => $event->conversation?->id,
                'message_id'      => $event->outboundMessage?->id,
                'query_len'       => mb_strlen($event->query),
                'route'           => $event->telemetry['route'] ?? 'unknown',
                'gate'            => $event->telemetry['answerability_decision']['status'] ?? 'bypassed',
                'latency_ms'      => $event->telemetry['total_time_ms'] ?? 0.0,
                'provider'        => $event->telemetry['provider'] ?? config('ai.default', 'deepseek'),
            ]);
        } catch (\Throwable $e) {
            // Observer pattern: absorb any logging/storage failure safely
            Log::warning('[RecordAITelemetryListener] Telemetry recording failed silently', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
