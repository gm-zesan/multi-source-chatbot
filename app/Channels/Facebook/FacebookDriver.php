<?php

namespace App\Channels\Facebook;

use App\Channels\Contracts\ChannelDriver;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;

class FacebookDriver implements ChannelDriver
{
    public function verifyWebhook(Request $request): Response {
        if (
            $request->input('hub.mode') === 'subscribe' &&
            $request->input('hub.verify_token') === config('services.facebook.verify_token')
        ) {
            return response($request->input('hub.challenge'), 200);
        }
        return response('Forbidden', 403);
    }


    public function send(ChannelAccount $account, Conversation $conversation, string $message): array
    {
        // ── Meta Zero-Cost Protection Guard ────────────────────────────
        $protection = app(\App\Services\Channels\MetaZeroCostProtectionService::class);
        $check = $protection->checkOutbound($account, $conversation, $message);
        if (! $check['allowed']) {
            \Illuminate\Support\Facades\Log::warning('[FacebookDriver] Outbound blocked by Zero-Charge Protection Guard', [
                'workspace_id' => $account->workspace_id,
                'status'       => $check['status'],
                'reason'       => $check['reason'],
            ]);

            return [
                'status'  => $check['status'],
                'error'   => $check['reason'],
                'blocked' => true,
            ];
        }

        $response = Http::withToken($account->access_token)->post('https://graph.facebook.com/v25.0/me/messages', [
            'recipient' => [
                'id' => $conversation->external_user_id,
            ],
            'message' => [
                'text' => $message,
            ],
        ]);

        if ($response->successful()) {
            $protection->recordOutboundSent((int) $account->workspace_id, 'facebook');
        }

        return $response->json() ?? [];
    }

    public function parseWebhook(array $payload): ?array
    {
        $messaging = $payload['entry'][0]['messaging'][0] ?? null;

        if (!$messaging) {
            return null;
        }

        // Ignore delivery/read/postback/etc.
        if (!isset($messaging['message'])) {
            return null;
        }

        $message = $messaging['message'];

        // Ignore our own messages
        if (!empty($message['is_echo'])) {
            return null;
        }

        $text = $message['text'] ?? '';
        $attachments = $message['attachments'] ?? [];
        $type = 'text';

        if ($text === '' && ! empty($attachments)) {
            $firstType = $attachments[0]['type'] ?? 'image';
            $type = $firstType;
            $text = match ($firstType) {
                'image' => '[Attachment: Image]',
                'file'  => '[Attachment: File]',
                'audio' => '[Attachment: Audio]',
                'video' => '[Attachment: Video]',
                default => '[Attachment]',
            };
        }

        if ($text === '' && empty($attachments)) {
            return null;
        }

        return [
            'external_user_id'    => $messaging['sender']['id'],
            'external_message_id' => $message['mid'] ?? null,
            'text'                => $text,
            'type'                => $type,
            'attachments'         => $attachments,
        ];
    }

    public function extractAccountId(array $payload): string {
        return $payload['entry'][0]['id'];
    }

    public function getUserProfile(ChannelAccount $account,string $psid): array {
        $response = Http::withToken($account->access_token)->get(
            "https://graph.facebook.com/{$psid}",
            [
                'fields' => 'name,profile_pic',
            ]
        );

        if (!$response->successful()) {
            return [
                'name' => null,
                'profile_pic' => null,
            ];
        }

        return [
            'name' => $response->json('name'),
            'profile_pic' => $response->json('profile_pic'),
        ];
    }

    public function markAsRead(ChannelAccount $account,Conversation $conversation): bool {
        return true;
    }

    public function getProfile(ChannelAccount $account,string $externalUserId): array {
        return [];
    }
}
