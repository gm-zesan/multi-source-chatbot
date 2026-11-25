<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestQueueMatrixJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 30;

    public function __construct(
        public readonly string $targetQueue,
        public readonly string $marker,
    ) {
        $this->connection = 'database';
        $this->queue = $targetQueue;
    }

    public function handle(): void
    {
        Log::info("[TestQueueMatrixJob] Successfully processed job on queue: {$this->targetQueue} (marker: {$this->marker})");
    }
}
