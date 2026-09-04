<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Retrieval\RetrievalClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncLexiconToEmbeddingServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 15;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $workspaceId = 0,
    ) {
        $this->queue = 'default';
    }

    /**
     * Execute the job.
     */
    public function handle(RetrievalClient $retrievalClient): void
    {
        Log::info('[SyncLexiconJob] Dispatched atomic lexicon reload', [
            'workspace_id' => $this->workspaceId,
        ]);

        $result = $retrievalClient->reloadLexicon($this->workspaceId);

        if (! $result['ok']) {
            Log::warning('[SyncLexiconJob] Lexicon reload failed', [
                'workspace_id' => $this->workspaceId,
                'error'        => $result['error'] ?? 'Unknown error',
            ]);
            // Allow retry if attempts remain
            throw new \RuntimeException('Failed to reload lexicon: ' . ($result['error'] ?? 'Unknown'));
        }

        Log::info('[SyncLexiconJob] Lexicon reloaded successfully on embedding service', [
            'workspace_id'      => $this->workspaceId,
            'snapshot_version'  => $result['snapshot_version'] ?? null,
            'global_version'    => $result['global_version'] ?? null,
            'workspace_version' => $result['workspace_version'] ?? null,
        ]);
    }
}
