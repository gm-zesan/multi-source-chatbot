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


    public function send(ChannelAccount $account,Conversation $conversation,string $message): array {
        $response = Http::withToken($account->access_token)->post('https://graph.facebook.com/v25.0/me/messages', 
        [
            'recipient' => [
                'id' => $conversation->external_user_id
            ],
            'message' => [
                'text' => $message
            ]
        ]);
        return $response->json();
    }

    public function parseWebhook(array $payload): array
    {
        $messaging = $payload['entry'][0]['messaging'][0];
        return [
            'external_user_id' =>$messaging['sender']['id'],
            'external_message_id' =>$messaging['message']['mid'] ?? null,
            'text' =>$messaging['message']['text'] ?? '',
            'attachments' =>$messaging['message']['attachments'] ?? [],
        ];
    }

    public function extractAccountId(array $payload): string {
        return $payload['entry'][0]['id'];
    }

    public function markAsRead(ChannelAccount $account,Conversation $conversation): bool {
        return true;
    }

    public function getProfile(ChannelAccount $account,string $externalUserId): array {
        return [];
    }
}