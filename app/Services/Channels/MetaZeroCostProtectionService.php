<?php

declare(strict_types=1);

namespace App\Services\Channels;

use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Models\WorkspaceUsageQuota;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MetaZeroCostProtectionService
{
    /**
     * Inspect outbound message intent against Meta pricing policies.
     * Guarantees that no credit card charges are incurred when zero_charge_protection is active.
     *
     * @return array{allowed: bool, status: string, reason: string}
     */
    public function checkOutbound(
        ChannelAccount $account,
        Conversation $conversation,
        string $message,
        string $category = 'service'
    ): array {
        $workspaceId = (int) $account->workspace_id;
        $channelSlug = strtolower((string) ($account->channel?->slug ?: 'whatsapp'));
        $quota = WorkspaceUsageQuota::currentForWorkspace($workspaceId);

        // ── 1. WhatsApp Zero-Charge Enforcement ──────────────────────
        if ($channelSlug === 'whatsapp') {
            // Block marketing broadcast templates if Zero-Charge protection is enabled
            if ($category === 'marketing' && $quota->zero_charge_protection) {
                return [
                    'allowed' => false,
                    'status'  => 'blocked_marketing_fee',
                    'reason'  => 'Marketing templates carry a Meta fee ($0.0732/message) and are blocked under Strict Zero-Charge Free Mode.',
                ];
            }

            // Check if monthly free 1,000 tier is exhausted
            if ($quota->isWhatsAppQuotaExhausted()) {
                if ($quota->zero_charge_protection) {
                    $this->notifyExhausted($workspaceId, $quota, 'whatsapp');

                    return [
                        'allowed' => false,
                        'status'  => 'blocked_quota_exhausted',
                        'reason'  => 'আপনার এই মাসের WhatsApp ফ্রি ১,০০০টি মেসেজের কোটা শেষ হয়েছে। অতিরিক্ত চার্জ এড়াতে স্বয়ংক্রিয় মেসেজ স্থগিত রাখা হয়েছে (পেইড সার্ভিস শীঘ্রই আসছে)।',
                    ];
                }
            }

            // Check if warning threshold (>= 80%) is reached
            if ($quota->isWhatsAppQuotaNearLimit() && ! $quota->quota_warning_sent_at) {
                $this->notifyNearLimit($workspaceId, $quota, 'whatsapp');
            }
        }

        // ── 2. Facebook Messenger 24-Hour Policy Window ─────────────
        if ($channelSlug === 'facebook') {
            $lastInbound = $conversation->last_message_at ?? $conversation->updated_at;
            if ($lastInbound && $lastInbound->lt(Carbon::now()->subHours(24))) {
                if ($quota->zero_charge_protection) {
                    return [
                        'allowed' => false,
                        'status'  => 'blocked_messenger_24h_window',
                        'reason'  => 'Messenger 24-hour free customer service window has expired. Outbound message stopped to prevent policy violation.',
                    ];
                }
            }
        }

        return [
            'allowed' => true,
            'status'  => 'allowed_free',
            'reason'  => 'Message is within free tier zero-charge policy boundaries.',
        ];
    }

    /**
     * Record successful outbound message and track free tier consumption.
     */
    public function recordOutboundSent(int $workspaceId, string $channelSlug): void
    {
        $quota = WorkspaceUsageQuota::currentForWorkspace($workspaceId);
        $channel = strtolower($channelSlug);

        if ($channel === 'whatsapp') {
            $quota->increment('whatsapp_messages_sent');
            $quota->refresh();

            if ($quota->isWhatsAppQuotaExhausted() && ! $quota->quota_exhausted_sent_at) {
                $this->notifyExhausted($workspaceId, $quota, 'whatsapp');
            } elseif ($quota->isWhatsAppQuotaNearLimit() && ! $quota->quota_warning_sent_at) {
                $this->notifyNearLimit($workspaceId, $quota, 'whatsapp');
            }
        } elseif ($channel === 'facebook') {
            $quota->increment('messenger_messages_sent');
        }
    }

    /**
     * Dispatch notification to business owner when free tier quota is exhausted.
     */
    public function notifyExhausted(int $workspaceId, WorkspaceUsageQuota $quota, string $channel = 'whatsapp'): void
    {
        if ($quota->quota_exhausted_sent_at) {
            return;
        }

        $quota->quota_exhausted_sent_at = Carbon::now();
        $quota->save();

        $channelName = $channel === 'whatsapp' ? 'WhatsApp Business' : 'Facebook Messenger';
        $notice = "⚠️ [Quota Alert] আপনার {$channelName}-এর এই মাসের ১,০০০টি সম্পূর্ণ ফ্রি মেসেজ কোটা শেষ হয়েছে! আপনার ক্রেডিট কার্ডে মেটার কোনো অতিরিক্ত চার্জ যেন না কাটে, সেজন্য স্বয়ংক্রিয় মেসেজ প্রেরণ সুরক্ষিতভাবে সাময়িক স্থগিত রাখা হয়েছে। (পেইড মেসেজিং প্যাক সার্ভিস শীঘ্রই আসছে - Coming Soon)";

        Log::warning('[MetaZeroCostProtection] Quota Exhausted Alert Dispatched to Merchant', [
            'workspace_id'    => $workspaceId,
            'channel'         => $channel,
            'sent_count'      => $quota->whatsapp_messages_sent,
            'free_limit'      => $quota->whatsapp_free_limit,
            'notice'          => $notice,
        ]);

        $this->storeWorkspaceNotification($workspaceId, 'quota_exhausted', $notice);
    }

    /**
     * Dispatch warning notification when 80% of free tier is used.
     */
    public function notifyNearLimit(int $workspaceId, WorkspaceUsageQuota $quota, string $channel = 'whatsapp'): void
    {
        if ($quota->quota_warning_sent_at) {
            return;
        }

        $quota->quota_warning_sent_at = Carbon::now();
        $quota->save();

        $percent = $quota->whatsAppUsagePercentage();
        $remaining = $quota->whatsAppRemaining();
        $notice = "🔔 [Quota Warning] আপনার WhatsApp-এর ১,০০০টি ফ্রি মেসেজ কোটার {$percent}% ({$quota->whatsapp_messages_sent}টি) ব্যবহৃত হয়েছে। আর মাত্র {$remaining}টি মেসেজ বাকি রয়েছে। কোটা পূর্ণ হলে চার্জ প্রতিরোধ গার্ড স্বয়ংক্রিয়ভাবে সক্রিয় হবে।";

        Log::info('[MetaZeroCostProtection] 80% Quota Warning Alert Dispatched to Merchant', [
            'workspace_id'    => $workspaceId,
            'channel'         => $channel,
            'sent_count'      => $quota->whatsapp_messages_sent,
            'remaining'       => $remaining,
            'notice'          => $notice,
        ]);

        $this->storeWorkspaceNotification($workspaceId, 'quota_warning', $notice);
    }

    /**
     * Store in-app / dashboard notification for the merchant workspace.
     */
    protected function storeWorkspaceNotification(int $workspaceId, string $type, string $message): void
    {
        $workspace = Workspace::find($workspaceId);
        if (! $workspace) {
            return;
        }

        $settings = $workspace->settings ?? [];
        $notifications = $settings['channel_quota_notifications'] ?? [];
        $notifications[] = [
            'id'         => uniqid('ntf_'),
            'type'       => $type,
            'message'    => $message,
            'created_at' => Carbon::now()->toIso8601String(),
            'read'       => false,
        ];

        // Keep last 10 notifications
        $settings['channel_quota_notifications'] = array_slice($notifications, -10);
        $workspace->settings = $settings;
        $workspace->save();
    }

    /**
     * Get live quota telemetry for merchant dashboard display.
     *
     * @return array<string, mixed>
     */
    public function getQuotaTelemetry(int $workspaceId): array
    {
        $quota = WorkspaceUsageQuota::currentForWorkspace($workspaceId);
        $workspace = Workspace::find($workspaceId);
        $notifications = $workspace?->settings['channel_quota_notifications'] ?? [];

        return [
            'period'                  => $quota->period_year_month,
            'zero_charge_protection'  => (bool) $quota->zero_charge_protection,
            'whatsapp' => [
                'sent'               => $quota->whatsapp_messages_sent,
                'limit'              => $quota->whatsapp_free_limit,
                'remaining'          => $quota->whatsAppRemaining(),
                'percentage'         => $quota->whatsAppUsagePercentage(),
                'is_exhausted'       => $quota->isWhatsAppQuotaExhausted(),
                'is_near_limit'      => $quota->isWhatsAppQuotaNearLimit(),
            ],
            'messenger' => [
                'sent'               => $quota->messenger_messages_sent,
                'window_policy'      => '24-hour Customer Service Window (100% Free)',
            ],
            'recent_notifications'   => array_reverse($notifications),
        ];
    }
}
