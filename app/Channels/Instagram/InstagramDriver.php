<?php

declare(strict_types=1);

namespace App\Channels\Instagram;

use App\Channels\Contracts\ChannelDriver;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class InstagramDriver implements ChannelDriver
{
    /**
     * Verify Instagram Webhook handshake via Meta Graph API.
     */
    public function verifyWebhook(Request $request): Response
    {
        $verifyToken = config('services.instagram.verify_token') ?: config('services.facebook.verify_token');

        $all = $request->all();
        $mode = $all['hub_mode'] ?? $all['hub.mode'] ?? $request->query('hub_mode') ?? $request->query('hub.mode');
        $token = $all['hub_verify_token'] ?? $all['hub.verify_token'] ?? $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $all['hub_challenge'] ?? $all['hub.challenge'] ?? $request->query('hub_challenge') ?? $request->query('hub.challenge');

        if ($mode === 'subscribe' && ! empty($verifyToken) && $token === $verifyToken) {
            return response((string) $challenge, 200);
        }

        return response('Forbidden', 403);
    }

    /**
     * Send outbound message via Instagram Graph API.
     */
    public function send(ChannelAccount $account, Conversation $conversation, string $message): array
    {
        $response = Http::withToken($account->access_token)
            ->post('https://graph.facebook.com/v20.0/me/messages', [
                'recipient' => [
                    'id' => $conversation->external_user_id,
                ],
                'message' => [
                    'text' => $message,
                ],
            ]);

        return $response->json() ?? ['status' => $response->status()];
    }

    /**
     * Parse inbound Instagram webhook payload.
     */
    public function parseWebhook(array $payload): ?array
    {
        $messaging = $payload['entry'][0]['messaging'][0] ?? null;
        if (! $messaging || ! isset($messaging['message'])) {
            return null;
        }

        $message = $messaging['message'];
        if (! empty($message['is_echo']) || ! isset($message['text'])) {
            return null;
        }

        return [
            'external_user_id' => $messaging['sender']['id'],
            'external_message_id' => $message['mid'] ?? null,
            'text' => $message['text'],
            'attachments' => $message['attachments'] ?? [],
        ];
    }

    /**
     * Extract Instagram Account ID from webhook payload.
     */
    public function extractAccountId(array $payload): string
    {
        return (string) ($payload['entry'][0]['id'] ?? '');
    }

    public function getUserProfile(ChannelAccount $account, string $externalUserId): array
    {
        $response = Http::withToken($account->access_token)
            ->get("https://graph.facebook.com/v20.0/{$externalUserId}", [
                'fields' => 'name,username,profile_pic',
            ]);

        return $response->json() ?? ['name' => $externalUserId];
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
