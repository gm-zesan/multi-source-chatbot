<?php

namespace App\Channels\WhatsApp;

use App\Channels\Contracts\ChannelDriver;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppDriver implements ChannelDriver
{
    public function verifyWebhook(Request $request): Response
    {
        return response('OK');
    }
    
    public function send(ChannelAccount $account,Conversation $conversation,string $message): array {
        return [];
    }

    public function parseWebhook(array $payload): array{
        return [];
    }

    public function extractAccountId(array $payload): string {
        return '';
    }

    public function markAsRead(ChannelAccount $account,Conversation $conversation): bool {
        return true;
    }

    public function getProfile(ChannelAccount $account,string $externalUserId): array {
        return [];
    }
}