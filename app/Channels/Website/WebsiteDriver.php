<?php

declare(strict_types=1);

namespace App\Channels\Website;

use App\Channels\Contracts\ChannelDriver;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class WebsiteDriver implements ChannelDriver
{
    /**
     * Webhook verification for Web Chat Widget.
     */
    public function verifyWebhook(Request $request): Response
    {
        return response()->json(['status' => 'active', 'channel' => 'website_widget']);
    }

    /**
     * Send message to Web Widget visitor (stored and delivered via polling or websocket).
     */
    public function send(ChannelAccount $account, Conversation $conversation, string $message): array
    {
        $messageId = 'web_msg_' . Str::uuid();

        return [
            'success'             => true,
            'message_id'          => $messageId,
            'recipient_id'        => $conversation->external_user_id,
            'delivered'           => true,
            'timestamp'           => now()->toIso8601String(),
        ];
    }

    /**
     * Parse inbound Web Widget message payload.
     */
    public function parseWebhook(array $payload): ?array
    {
        $text = $payload['text'] ?? $payload['message'] ?? null;
        $externalUserId = $payload['visitor_id'] ?? $payload['session_id'] ?? $payload['sender_id'] ?? null;

        if (! $text || ! $externalUserId) {
            return null;
        }

        return [
            'external_user_id'    => (string) $externalUserId,
            'external_message_id' => (string) ($payload['message_id'] ?? ('msg_' . Str::uuid())),
            'text'                => (string) $text,
            'customer_name'       => $payload['visitor_name'] ?? 'Web Visitor',
            'attachments'         => $payload['attachments'] ?? [],
        ];
    }

    /**
     * Extract Channel Account ID from Web Widget request.
     */
    public function extractAccountId(array $payload): string
    {
        return (string) ($payload['channel_account_id'] ?? $payload['account_id'] ?? $payload['widget_key'] ?? '');
    }

    public function getUserProfile(ChannelAccount $account, string $externalUserId): array
    {
        return [
            'name' => 'Web Visitor (' . substr($externalUserId, 0, 8) . ')',
        ];
    }

    public function markAsRead(ChannelAccount $account, Conversation $conversation): bool
    {
        return true;
    }

    public function getProfile(ChannelAccount $account, string $externalUserId): array
    {
        return $this->getUserProfile($account, $externalUserId);
    }
}
