<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\IncomingMessageReceived;
use App\Services\CRM\CRMService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ExtractCRMEntitiesListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue connection to use.
     */
    public string $connection = 'database';

    /**
     * The queue name to use.
     */
    public string $queue = 'crm';

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
        private readonly CRMService $crmService,
    ) {
        $this->connection = 'database';
        $this->queue = 'crm';
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
        Log::info('[CRM Listener] Starting CRM extraction', [
            'conversation_id' => $event->conversation->id,
            'message_id'      => $event->message->id,
        ]);

        try {
            $entities = $this->crmService->extractEntities($event->message->body);
            $this->crmService->sync($event->conversation, $entities);

            Log::info('[CRM Listener] CRM extraction completed', [
                'conversation_id' => $event->conversation->id,
                'entities_found'  => count($entities['contact']['emails'] ?? []) + count($entities['contact']['phones'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('[CRM Listener] CRM extraction failed', [
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
        Log::error('[CRM Listener] Permanently failed after retries', [
            'conversation_id' => $event->conversation->id,
            'error'           => $exception->getMessage(),
        ]);
    }
}
