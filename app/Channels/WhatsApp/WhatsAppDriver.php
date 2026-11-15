<?php

declare(strict_types=1);

namespace App\Channels\WhatsApp;

use App\Channels\Contracts\ChannelDriver;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppDriver implements ChannelDriver
{
    /**
     * Verify WhatsApp Cloud API Webhook handshake.
     */
    public function verifyWebhook(Request $request): Response
    {
        $verifyToken = config('services.whatsapp.verify_token') ?: config('services.facebook.verify_token');

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
     * Send outbound message via WhatsApp Cloud API.
     */
    public function send(ChannelAccount $account, Conversation $conversation, string $message): array
    {
        $phoneNumberId = $account->settings['phone_number_id'] ?? $account->external_id;

        $response = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $conversation->external_user_id,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ]);

        return $response->json() ?? ['status' => $response->status()];
    }

    /**
     * Parse inbound WhatsApp Cloud API webhook payload.
     */
    public function parseWebhook(array $payload): ?array
    {
        $value = $payload['entry'][0]['changes'][0]['value'] ?? null;
        if (! $value || empty($value['messages'])) {
            return null;
        }

        $message = $value['messages'][0];
        if (! isset($message['text']['body'])) {
            return null; // Ignore non-text for basic messaging
        }

        $senderPhone = $message['from'];
        $customerName = $value['contacts'][0]['profile']['name'] ?? null;

        return [
            'external_user_id' => $senderPhone,
            'external_message_id' => $message['id'] ?? null,
            'text' => $message['text']['body'],
            'customer_name' => $customerName,
            'attachments' => [],
        ];
    }

    /**
     * Extract WhatsApp Phone Number ID from webhook payload.
     */
    public function extractAccountId(array $payload): string
    {
        return (string) ($payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? '');
    }

    public function getUserProfile(ChannelAccount $account, string $externalUserId): array
    {
        return [
            'name' => $externalUserId,
            'phone' => $externalUserId,
        ];
    }

    public function markAsRead(ChannelAccount $account, Conversation $conversation): bool
    {
        $phoneNumberId = $account->settings['phone_number_id'] ?? $account->external_id;
        $lastMessageId = $conversation->metadata['last_external_message_id'] ?? null;

        if (! $lastMessageId) {
            return false;
        }

        $res = Http::withToken($account->access_token)
            ->post("https://graph.facebook.com/v20.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $lastMessageId,
            ]);

        return $res->successful();
    }

    public function getProfile(ChannelAccount $account, string $externalUserId): array
    {
        return $this->getUserProfile($account, $externalUserId);
    }
}
