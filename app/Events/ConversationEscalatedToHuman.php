<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationEscalatedToHuman
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly ?User $manager,
        public readonly string $reason,
        public readonly string $priority = 'urgent'
    ) {}
}
