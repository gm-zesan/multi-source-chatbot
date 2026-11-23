<?php

declare(strict_types=1);

namespace App\Services\Retention;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WorkspaceOrder;
use App\Services\Billing\WorkspaceWalletService;
use App\Support\ChannelManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbandonedLeadRecoveryService
{
    /**
     * Tags that indicate buying intent, incomplete checkout, or price inquiries.
     */
    public const ELIGIBLE_TAGS = [
        'needs_phone',
        'hot_lead',
        'price_inquiry',
        'lead',
        'incomplete_order',
        'cart_abandoned',
    ];

    public function __construct(
        private readonly WorkspaceWalletService $walletService,
    ) {}

    /**
     * Find all conversations eligible for an automated abandoned lead follow-up.
     *
     * Rules:
     * 1. Status is 'open' and AI is not paused.
     * 2. Inactivity window: At least 2 hours have passed since last message (customer abandoned).
     * 3. Meta 24-Hour Rule: Must be within 23 hours of the last message (1-hour buffer).
     * 4. Customer has not placed an order in this conversation.
     * 5. Customer has not already received an abandoned lead follow-up in this session.
     * 6. Tagged with purchase intent or price inquiry.
     *
     * @return Collection<int, Conversation>
     */
    public function findEligibleConversations(?int $workspaceId = null): Collection
    {
        $twoHoursAgo = Carbon::now()->subHours(2);
        $twentyThreeHoursAgo = Carbon::now()->subHours(23);

        $query = Conversation::query()
            ->with(['channelAccount.channel', 'channelAccount.workspace'])
            ->where('status', 'open')
            ->where('last_message_at', '<=', $twoHoursAgo)
            ->where('last_message_at', '>=', $twentyThreeHoursAgo)
            ->where(function ($q) {
                // Must not have already received an automated follow-up
                $q->whereNull('metadata->abandoned_lead_followed_up')
                  ->orWhere('metadata->abandoned_lead_followed_up', false);
            })
            ->where(function ($q) {
                // Check if any eligible tag is present
                foreach (self::ELIGIBLE_TAGS as $tag) {
                    $q->orWhereJsonContains('ai_tags', $tag);
                }
            })
            ->whereDoesntHave('orders', function ($q) {
                $q->whereNotIn('status', ['cancelled']);
            });

        if ($workspaceId !== null) {
            $query->whereHas('channelAccount', function ($q) use ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            });
        }

        return $query->get()->filter(function (Conversation $conv) {
            // Guard: AI must not be manually paused
            return ! $conv->isAiPaused();
        });
    }

    /**
     * Send an automated recovery follow-up to the customer.
     *
     * @return array{success: bool, reason?: string, message_id?: int}
     */
    public function sendFollowUp(Conversation $conversation): array
    {
        $conversation->loadMissing(['channelAccount.channel', 'channelAccount.workspace']);
        $account = $conversation->channelAccount;
        $workspace = $account?->workspace;
        $channelSlug = $account?->channel?->slug ?? 'web';

        if (! $account) {
            return ['success' => false, 'reason' => 'channel_account_missing'];
        }

        // 1. Strict Meta 24-Hour Policy Safeguard
        if ($conversation->last_message_at === null || $conversation->last_message_at->lt(Carbon::now()->subHours(23))) {
            Log::warning('[AbandonedLeadRecovery] Skipping follow-up: Outside 23-hour window for Meta compliance', [
                'conversation_id' => $conversation->id,
                'last_message_at' => $conversation->last_message_at,
            ]);
            return ['success' => false, 'reason' => 'meta_24h_window_expired'];
        }

        // 2. Prevent Duplicate Automated Spamming
        if (! empty($conversation->metadata['abandoned_lead_followed_up'])) {
            return ['success' => false, 'reason' => 'already_followed_up'];
        }

        // 3. Ensure no active order was placed in the interim
        $hasOrder = WorkspaceOrder::where('conversation_id', $conversation->id)
            ->whereNotIn('status', ['cancelled'])
            ->exists();
        if ($hasOrder) {
            return ['success' => false, 'reason' => 'order_already_placed'];
        }

        // 4. Generate Polite Bengali Follow-Up Copy
        $followUpText = $this->craftFollowUpMessage($conversation);

        // 5. Send message via Channel Driver
        $driverResponse = [];
        try {
            $driver = ChannelManager::driver($channelSlug);
            $driverResponse = $driver->send($account, $conversation, $followUpText) ?? [];
        } catch (\Throwable $e) {
            Log::error('[AbandonedLeadRecovery] Failed to send outbound follow-up message', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
            return ['success' => false, 'reason' => 'channel_delivery_failed: ' . $e->getMessage()];
        }

        // 6. Persist Outbound Message and Update Conversation State in Transaction
        $message = DB::transaction(function () use ($conversation, $followUpText, $driverResponse) {
            $msg = Message::create([
                'conversation_id'     => $conversation->id,
                'external_message_id' => $driverResponse['message_id'] ?? null,
                'direction'           => 'outbound',
                'type'                => 'text',
                'body'                => $followUpText,
                'metadata'            => [
                    'automated_by'     => 'retention_scheduler',
                    'reason'           => 'abandoned_lead_recovery',
                    'driver_response'  => $driverResponse,
                ],
            ]);

            $metadata = $conversation->metadata ?? [];
            $metadata['abandoned_lead_followed_up'] = true;
            $metadata['abandoned_lead_followed_up_at'] = Carbon::now()->toIso8601String();

            $conversation->update([
                'last_message'    => $followUpText,
                'last_message_at' => Carbon::now(),
                'last_direction'  => 'outbound',
                'metadata'        => $metadata,
            ]);

            return $msg;
        });

        // 7. Deduct wallet usage if workspace has active metered billing
        if ($workspace) {
            try {
                $this->walletService->deductMessageUsage($workspace->id);
            } catch (\Throwable $e) {
                Log::warning('[AbandonedLeadRecovery] Wallet deduction failed (non-blocking)', [
                    'workspace_id' => $workspace->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        Log::info('[AbandonedLeadRecovery] Successfully sent abandoned lead follow-up', [
            'conversation_id' => $conversation->id,
            'message_id'      => $message->id,
            'channel'         => $channelSlug,
        ]);

        return [
            'success'    => true,
            'message_id' => $message->id,
        ];
    }

    /**
     * Craft a polite, context-aware Bengali follow-up message.
     */
    public function craftFollowUpMessage(Conversation $conversation): string
    {
        $customerName = null;
        if (! empty($conversation->customer_name) && ! str_starts_with($conversation->customer_name, 'Visitor_')) {
            $customerName = trim($conversation->customer_name);
        }

        $greeting = $customerName ? "হ্যালো {$customerName}," : "হ্যালো,";

        // Check if there is a specific product mentioned in metadata or notes
        $product = $conversation->metadata['discussed_product'] ?? null;

        if ($product) {
            return "{$greeting} আপনি '{$product}' সম্পর্কে জানতে চেয়েছিলেন। পণ্যটি কি অর্ডার করতে চাচ্ছেন? স্টক সীমিত, সাইজ বা ডেলিভারি নিয়ে কোনো প্রশ্ন থাকলে নির্দ্বিধায় আমাদের জানান। আমরা সাহায্য করতে প্রস্তুত!";
        }

        return "{$greeting} আপনি আমাদের যে পণ্যটি সম্পর্কে জানতে চেয়েছিলেন, সেটির অর্ডার কি কনফার্ম করতে চাচ্ছেন? স্টক সীমিত রয়েছে। আপনার কোনো তথ্য বা সাইজ নিয়ে প্রশ্ন থাকলে আমাদের জানাতে পারেন—আমরা সাহায্য করতে প্রস্তুত!";
    }
}
