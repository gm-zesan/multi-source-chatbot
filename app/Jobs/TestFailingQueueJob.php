<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TestFailingQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 10;

    public function __construct(public readonly string $marker)
    {
        $this->queue = 'default';
    }

    public function handle(): void
    {
        throw new \RuntimeException("Deliberate simulated worker crash for marker: {$this->marker}");
    }
}
