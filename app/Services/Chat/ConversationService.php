<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Message;

class ConversationService
{
    /**
     * Save outbound reply message and update conversation thread state.
     */
    public function saveOutgoing(Conversation $conversation, string $message, array $response = []): Message
    {
        $msg = Message::create([
            'conversation_id'     => $conversation->id,
            'external_message_id' => $response['message_id'] ?? null,
            'direction'           => 'outbound',
            'type'                => 'text',
            'body'                => $message,
            'metadata'            => $response,
        ]);

        $conversation->update([
            'last_message'    => $message,
            'last_message_at' => now(),
            'last_direction'  => 'outbound',
        ]);

        return $msg;
    }
}

