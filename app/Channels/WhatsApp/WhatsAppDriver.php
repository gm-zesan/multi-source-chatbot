<?php

declare(strict_types=1);

namespace App\Channels\WhatsApp;

use App\Channels\Contracts\ChannelDriver;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppDriver implements ChannelDriver
{
    /**
     * Verify WhatsApp Cloud API Webhook handshake.
     */
    public function verifyWebhook(Request $request): Response
    {
        $waVerifyToken = config('services.whatsapp.verify_token');
        $fbVerifyToken = config('services.facebook.verify_token');

        $all = $request->all();
        $mode = $request->input('hub.mode') ?? $request->input('hub_mode') ?? $request->query('hub.mode') ?? $request->query('hub_mode') ?? ($all['hub.mode'] ?? $all['hub_mode'] ?? null);
        $token = $request->input('hub.verify_token') ?? $request->input('hub_verify_token') ?? $request->query('hub.verify_token') ?? $request->query('hub_verify_token') ?? ($all['hub.verify_token'] ?? $all['hub_verify_token'] ?? null);
        $challenge = $request->input('hub.challenge') ?? $request->input('hub_challenge') ?? $request->query('hub.challenge') ?? $request->query('hub_challenge') ?? ($all['hub.challenge'] ?? $all['hub_challenge'] ?? null);

        $isTokenValid = (! empty($waVerifyToken) && $token === $waVerifyToken)
            || (! empty($fbVerifyToken) && $token === $fbVerifyToken);

        if ($mode === 'subscribe' && $isTokenValid) {
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
                'recipient_type'    => 'individual',
                'to'                => $conversation->external_user_id,
                'type'              => 'text',
                'text'              => [
                    'preview_url' => false,
                    'body'        => $message,
                ],
            ]);

        $json = $response->json() ?? [];
        if (! $response->successful()) {
            Log::warning('[WhatsAppDriver] Outbound message failed', [
                'phone_number_id' => $phoneNumberId,
                'recipient'       => $conversation->external_user_id,
                'status'          => $response->status(),
                'response'        => $json,
            ]);
        }

        return $json ?: ['status' => $response->status()];
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
        $senderPhone = $message['from'] ?? null;
        if (! $senderPhone) {
            return null;
        }

        $customerName = $value['contacts'][0]['profile']['name'] ?? null;
        $type = $message['type'] ?? 'text';
        $text = '';
        $attachments = [];

        switch ($type) {
            case 'text':
                $text = trim((string) ($message['text']['body'] ?? ''));
                break;

            case 'interactive':
                $interactive = $message['interactive'] ?? [];
                if (isset($interactive['button_reply']['title'])) {
                    $text = trim((string) $interactive['button_reply']['title']);
                } elseif (isset($interactive['list_reply']['title'])) {
                    $text = trim((string) $interactive['list_reply']['title']);
                } else {
                    $text = '[Interactive Selection]';
                }
                break;

            case 'button':
                $text = trim((string) ($message['button']['text'] ?? ''));
                break;

            case 'image':
                $text = trim((string) ($message['image']['caption'] ?? '[Attachment: Image]'));
                $attachments[] = [
                    'type'    => 'image',
                    'payload' => $message['image'] ?? [],
                ];
                break;

            case 'audio':
            case 'voice':
                $text = '[Attachment: Audio]';
                $attachments[] = [
                    'type'    => 'audio',
                    'payload' => $message['audio'] ?? $message['voice'] ?? [],
                ];
                break;

            case 'video':
                $text = trim((string) ($message['video']['caption'] ?? '[Attachment: Video]'));
                $attachments[] = [
                    'type'    => 'video',
                    'payload' => $message['video'] ?? [],
                ];
                break;

            case 'document':
                $docName = $message['document']['filename'] ?? 'document';
                $text = "[Attachment: Document ({$docName})]";
                $attachments[] = [
                    'type'    => 'file',
                    'payload' => $message['document'] ?? [],
                ];
                break;

            case 'location':
                $loc = $message['location'] ?? [];
                $locName = $loc['name'] ?? "{$loc['latitude']}, {$loc['longitude']}";
                $text = "[Location: {$locName}]";
                $attachments[] = [
                    'type'    => 'location',
                    'payload' => $loc,
                ];
                break;

            default:
                $text = trim((string) ($message['text']['body'] ?? ''));
                break;
        }

        if ($text === '' && empty($attachments)) {
            return null;
        }

        return [
            'external_user_id'    => (string) $senderPhone,
            'external_message_id' => $message['id'] ?? null,
            'text'                => $text,
            'type'                => $type,
            'customer_name'       => $customerName,
            'attachments'         => $attachments,
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
            'name'  => $externalUserId,
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
                'status'            => 'read',
                'message_id'        => $lastMessageId,
            ]);

        return $res->successful();
    }

    public function getProfile(ChannelAccount $account, string $externalUserId): array
    {
        return $this->getUserProfile($account, $externalUserId);
    }
}
