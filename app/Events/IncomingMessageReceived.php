<?php

namespace App\Events;

use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class IncomingMessageReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly Conversation $conversation,
        public readonly Message $message,
        public readonly ChannelAccount $account,
        public readonly array $rawPayload,
    ) {}
}
