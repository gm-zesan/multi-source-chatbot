<?php

declare(strict_types=1);

namespace App\Listeners;

use App\AI\Decision\UnifiedOrchestrator;
use App\Events\AITelemetryRecorded;
use App\Events\IncomingMessageReceived;
use App\AI\Orchestration\CustomerSupportService;
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
        private readonly CustomerSupportService $customerSupportService,
        private readonly UnifiedOrchestrator $orchestrator,
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
            $event->account->loadMissing('channel');
            $channelSlug = $event->account->channel?->slug ?? 'web';

            $result = $this->orchestrator->process(
                query: $event->message->body,
                workspaceId: (int) $event->account->workspace_id,
                user: null, // Untrusted customer channel
                conversation: $event->conversation,
                channelSlug: $channelSlug,
                isCustomer: true,
            );

            $replyText = $result['reply'] ?? '';

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

                $savedMessage = $this->customerSupportService->saveOutboundReply(
                    conversation: $event->conversation,
                    replyText: $replyText,
                    deliveryResponse: $deliveryResponse,
                );

                // Automated Order & Lead Extraction
                try {
                    app(\App\Services\Commerce\OrderCaptureService::class)->extractAndCapture(
                        conversation: $event->conversation,
                        userMessage: $event->message->body,
                        aiResponse: $replyText,
                    );
                } catch (\Throwable $eOrder) {
                    Log::warning('[FAQ Listener] Order capture extraction non-fatal failure: ' . $eOrder->getMessage());
                }

                // Automated AI Lead Classification & Executive Summary
                try {
                    app(\App\Services\AI\LeadIntelligenceService::class)->classifyAndTagConversation(
                        conversation: $event->conversation,
                        force: true,
                    );
                } catch (\Throwable $eIntelligence) {
                    Log::warning('[FAQ Listener] AI Lead Intelligence non-fatal failure: ' . $eIntelligence->getMessage());
                }

                // Telemetry Observer Dispatch (Decoupled & Non-blocking)
                try {
                    event(new AITelemetryRecorded(
                        conversation: $event->conversation,
                        outboundMessage: $savedMessage,
                        query: $event->message->body,
                        reply: $replyText,
                        telemetry: [
                            'route'                  => $result['route'] ?? 'knowledge',
                            'confidence'             => $result['confidence'] ?? 1.0,
                            'answered'               => $result['answered'] ?? false,
                            'total_time_ms'          => $result['routing_telemetry']['total_e2e_ms'] ?? null,
                            'answerability_decision' => $result['answerability_decision'] ?? null,
                            'routing_telemetry'      => $result['routing_telemetry'] ?? [],
                            'llm_usage'              => $result['raw_llm_response'] ?? [],
                            'provider'               => config('ai.default', 'deepseek'),
                            'model'                  => config('ai.default_model', 'deepseek-chat'),
                        ],
                        workspaceId: (int) $event->account->workspace_id,
                    ));
                } catch (\Throwable $telemetryEx) {
                    Log::warning('[FAQ Listener] Telemetry dispatch absorbed: ' . $telemetryEx->getMessage());
                }

                // Dispatch asynchronous Graph Memory Ingestion (Non-blocking, port 8002)
                \App\Jobs\IngestConversationMemoryJob::dispatch($event->conversation);

                Log::info('[FAQ Listener] AI Customer Support Agent response saved, memory ingestion queued', [
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

