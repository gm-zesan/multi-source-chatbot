<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AITelemetryRecorded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array<string, mixed> $telemetry
     */
    public function __construct(
        public readonly ?Conversation $conversation,
        public readonly ?Message $outboundMessage,
        public readonly string $query,
        public readonly string $reply,
        public readonly array $telemetry,
        public readonly int $workspaceId,
    ) {}
}
