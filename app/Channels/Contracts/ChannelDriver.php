<?php

namespace App\Channels\Contracts;

use App\Models\ChannelAccount;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

interface ChannelDriver
{
    /**
     * Verify webhook (GET request)
     */
    public function verifyWebhook(Request $request): Response;

    /**
     * Send message
     */
    public function send(ChannelAccount $account,Conversation $conversation,string $message): array;

    /**
     * Parse webhook payload
     */
    public function parseWebhook(array $payload): array;

    /**
     * Extract account id from webhook payload
     */
    public function extractAccountId(array $payload): string;

    /**
     * Mark message as read
     */
    public function markAsRead(ChannelAccount $account,Conversation $conversation): bool;

    /**
     * Get customer profile
     */
    public function getProfile(ChannelAccount $account,string $externalUserId): array;
}