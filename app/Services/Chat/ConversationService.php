<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    /**
     * Save incoming message — pure persistence only.
     * CRM extraction and FAQ processing are handled by event listeners.
     */
    public function saveIncoming(ChannelAccount $account, array $data): Conversation
    {
        return DB::transaction(function () use ($account, $data) {
            $conversation = Conversation::firstOrCreate(
                [
                    'channel_account_id' => $account->id,
                    'external_user_id'   => $data['external_user_id'],
                ],
                [
                    'customer_name'   => $data['customer_name'],
                    'customer_avatar' => $data['customer_avatar'],
                    'status'          => 'open',
                    'last_direction'  => 'inbound',
                ]
            );

            Message::create([
                'conversation_id'     => $conversation->id,
                'external_message_id' => $data['external_message_id'],
                'direction'           => 'inbound',
                'type'                => 'text',
                'body'                => $data['text'],
                'metadata'            => $data,
            ]);

            $conversation->update([
                'last_message'    => $data['text'],
                'last_message_at' => now(),
                'last_direction'  => 'inbound',
                'unread_count'    => DB::raw('unread_count + 1'),
            ]);

            return $conversation;
        });
    }

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
