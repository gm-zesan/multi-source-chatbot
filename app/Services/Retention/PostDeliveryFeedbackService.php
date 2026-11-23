<?php

declare(strict_types=1);

namespace App\Services\Retention;

use App\Jobs\SendPostDeliveryFeedbackJob;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\OrderFeedback;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Models\WorkspaceWallet;
use App\Models\WorkspaceWalletTransaction;
use App\Support\ChannelManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostDeliveryFeedbackService
{
    /**
     * Dispatch an automated CSAT feedback request for a delivered order.
     */
    public function dispatchFeedbackRequest(WorkspaceOrder $order): ?OrderFeedback
    {
        if ($order->status !== 'delivered') {
            return null;
        }

        // Idempotency: only one feedback per order
        $existing = OrderFeedback::where('workspace_order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        $feedback = OrderFeedback::create([
            'workspace_id'          => $order->workspace_id,
            'workspace_order_id'    => $order->id,
            'conversation_id'       => $order->conversation_id,
            'customer_name'         => $order->customer_name,
            'customer_phone'        => $order->customer_phone,
            'channel'               => $order->channel ?: 'whatsapp',
            'status'                => 'requested',
            'feedback_requested_at' => now(),
            'metadata'              => [
                'order_number' => $order->order_number,
                'order_amount' => $order->order_amount,
            ],
        ]);

        SendPostDeliveryFeedbackJob::dispatch($feedback->id)->onQueue('retention');

        Log::info('[PostDeliveryFeedback] Feedback request scheduled for order', [
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'feedback_id'  => $feedback->id,
        ]);

        return $feedback;
    }

    /**
     * Send the actual feedback request message to the customer across their channel.
     */
    public function sendFeedbackRequest(OrderFeedback $feedback): bool
    {
        $order = $feedback->order;
        if (! $order) {
            return false;
        }

        if ($feedback->status === 'received') {
            return true;
        }

        // Resolve conversation
        $conversation = $feedback->conversation ?: $order->conversation;
        if (! $conversation && ! empty($order->customer_phone)) {
            $conversation = Conversation::whereHas('channelAccount', function ($q) use ($order) {
                $q->where('workspace_id', $order->workspace_id);
            })->where(function ($q) use ($order) {
                $q->where('customer_phone', $order->customer_phone)
                  ->orWhere('external_user_id', $order->customer_phone);
            })->latest()->first();
        }

        if (! $conversation || ! $conversation->channelAccount) {
            Log::info('[PostDeliveryFeedback] No reachable active conversation found for order feedback', [
                'feedback_id'  => $feedback->id,
                'order_number' => $order->order_number,
            ]);
            return false;
        }

        $customerName = trim((string) ($feedback->customer_name ?: $conversation->customer_name ?: 'গ্রাহক'));
        $orderNumber = $order->order_number;

        $feedbackMessage = "আসসালামু আলাইকুম {$customerName}! আপনার অর্ডার (#{$orderNumber}) সফলভাবে ডেলিভারি সম্পন্ন হয়েছে।\n\n"
            . "আমাদের পণ্য ও ডেলিভারি সেবা আপনার কেমন লেগেছে? ১ থেকে ৫ এর মধ্যে রেটিং দিন (যেমন: 5 = চমৎকার, 1 = অসন্তুষ্ট)।\n\n"
            . "আপনার মূল্যবান মতামত আমাদের সেবাকে আরো উন্নত করতে সাহায্য করবে। ⭐";

        $account = $conversation->channelAccount;
        $channelSlug = $account->channel?->slug ?: 'whatsapp';

        try {
            $driver = ChannelManager::driver($channelSlug);
            $sendResult = $driver->send($account, $conversation, $feedbackMessage);

            $msg = Message::create([
                'conversation_id'     => $conversation->id,
                'direction'           => 'outbound',
                'type'                => 'text',
                'body'                => $feedbackMessage,
                'external_message_id' => $sendResult['messages'][0]['id'] ?? ('csat_' . uniqid()),
                'metadata'            => [
                    'type'         => 'post_delivery_csat_request',
                    'feedback_id'  => $feedback->id,
                    'order_number' => $orderNumber,
                ],
            ]);

            $feedback->update([
                'conversation_id' => $conversation->id,
                'metadata'        => array_merge($feedback->metadata ?? [], [
                    'outbound_message_id' => $msg->id,
                    'sent_at'             => now()->toIso8601String(),
                ]),
            ]);

            // Deduct usage fee (BDT 0.40) if wallet exists
            $this->deductRetentionCredit($order->workspace_id, $orderNumber);

            return true;
        } catch (\Throwable $e) {
            Log::error('[PostDeliveryFeedback] Failed to send feedback request message', [
                'feedback_id' => $feedback->id,
                'error'       => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check incoming customer message for feedback rating/review and record it.
     */
    public function recordFeedbackFromMessage(Conversation $conversation, string $messageText): ?OrderFeedback
    {
        // Find open requested feedback for this conversation or customer
        $feedback = OrderFeedback::where('status', 'requested')
            ->where(function ($q) use ($conversation) {
                $q->where('conversation_id', $conversation->id);
                if (! empty($conversation->customer_phone)) {
                    $q->orWhere('customer_phone', $conversation->customer_phone);
                }
                if (! empty($conversation->external_user_id)) {
                    $q->orWhere('customer_phone', $conversation->external_user_id);
                }
            })
            ->latest()
            ->first();

        if (! $feedback) {
            return null;
        }

        $rating = $this->parseRating($messageText);
        if ($rating === null) {
            return null;
        }

        $feedback->update([
            'rating'               => $rating,
            'review_text'          => trim($messageText),
            'status'               => 'received',
            'feedback_received_at' => now(),
            'metadata'             => array_merge($feedback->metadata ?? [], [
                'parsed_rating'        => $rating,
                'raw_customer_reply'   => $messageText,
            ]),
        ]);

        // Send a warm gratitude confirmation
        try {
            $account = $conversation->channelAccount;
            if ($account) {
                $replyText = $rating >= 4
                    ? "আপনার চমৎকার ৫-স্টার মতামতের জন্য অসংখ্য ধন্যবাদ! ⭐ আপনাদের সেবায় আমরা সর্বদা নিবেদিত।"
                    : "আপনার মতামতের জন্য ধন্যবাদ। আমরা আপনার পরামর্শ অনুযায়ী আমাদের সেবার মান আরও উন্নত করার জন্য সর্বোচ্চ সচেষ্ট থাকব।";

                $driver = ChannelManager::driver($account->channel?->slug ?: 'whatsapp');
                $driver->send($account, $conversation, $replyText);

                Message::create([
                    'conversation_id' => $conversation->id,
                    'direction'       => 'outbound',
                    'type'            => 'text',
                    'body'            => $replyText,
                    'metadata'        => [
                        'type'        => 'post_delivery_csat_gratitude',
                        'feedback_id' => $feedback->id,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[PostDeliveryFeedback] Could not send gratitude reply', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('[PostDeliveryFeedback] CSAT feedback captured successfully', [
            'feedback_id' => $feedback->id,
            'rating'      => $rating,
            'order_id'    => $feedback->workspace_order_id,
        ]);

        return $feedback;
    }

    /**
     * Parse 1-5 rating from user text using digits or NLP sentiment.
     */
    public function parseRating(string $text): ?int
    {
        $text = trim($text);

        // 1. Direct Arabic or Bengali digits
        if (preg_match('/(?:^|\s)([1-5])(?:\s*(?:star|স্টার|রেটিং|star|stars)?)(?:\s|$)/iu', $text, $m)) {
            return (int) $m[1];
        }

        $bengaliDigits = ['১' => 1, '২' => 2, '৩' => 3, '৪' => 4, '৫' => 5];
        if (preg_match('/(?:^|\s)([১-৫])(?:\s*(?:star|স্টার|রেটিং)?)(?:\s|$)/iu', $text, $m)) {
            return $bengaliDigits[$m[1]] ?? null;
        }

        // 2. High praise / 5 Stars sentiment
        if (preg_match('/(?:অসাধারণ|দারুণ|অনেক\s*ভালো|খুব\s*সুন্দর|চমৎকার|সেরা|সন্তুষ্ট|excellent|great|awesome|perfect|love it)/iu', $text)) {
            return 5;
        }

        // 3. Good / 4 Stars sentiment
        if (preg_match('/(?:ভালো|পছন্দ\s*হয়েছে|ভালোই|good|nice)/iu', $text)) {
            return 4;
        }

        // 4. Neutral / 3 Stars sentiment
        if (preg_match('/(?:চলবে|মোটামুটি|average|okay|ok)/iu', $text)) {
            return 3;
        }

        // 5. Critical / 1-2 Stars sentiment
        if (preg_match('/(?:নষ্ট|খারাপ|বাজে|ভাঙা|ভেঙ্গে|ভুয়া|বাজে\s*সার্ভিস|terrible|worst|damaged|fake)/iu', $text)) {
            return 1;
        }

        if (preg_match('/(?:পছন্দ\s*হয়নি|দেরি\s*হয়েছে|not\s*satisfied|poor)/iu', $text)) {
            return 2;
        }

        return null;
    }

    /**
     * Compute comprehensive CSAT metrics for the workspace.
     */
    public function getCSATStats(int $workspaceId): array
    {
        $totalRequested = OrderFeedback::where('workspace_id', $workspaceId)->count();
        $received = OrderFeedback::where('workspace_id', $workspaceId)->where('status', 'received')->get();
        $totalReceived = $received->count();

        $avgScore = $totalReceived > 0 ? round((float) $received->avg('rating'), 2) : 5.0;
        $responseRate = $totalRequested > 0 ? round(($totalReceived / $totalRequested) * 100, 1) : 0.0;

        $distribution = [
            5 => $received->where('rating', 5)->count(),
            4 => $received->where('rating', 4)->count(),
            3 => $received->where('rating', 3)->count(),
            2 => $received->where('rating', 2)->count(),
            1 => $received->where('rating', 1)->count(),
        ];

        $positiveCount = $distribution[5] + $distribution[4];
        $csatPercent = $totalReceived > 0 ? round(($positiveCount / $totalReceived) * 100, 1) : 100.0;

        return [
            'total_requested'  => $totalRequested,
            'total_received'   => $totalReceived,
            'average_rating'   => $avgScore,
            'csat_percentage'  => $csatPercent,
            'response_rate'    => $responseRate,
            'distribution'     => $distribution,
        ];
    }

    private function deductRetentionCredit(int $workspaceId, string $orderNumber): void
    {
        try {
            $wallet = WorkspaceWallet::where('workspace_id', $workspaceId)->first();
            if ($wallet && (float) $wallet->balance >= 0.40) {
                DB::transaction(function () use ($wallet, $orderNumber) {
                    $wallet->decrement('balance', 0.40);
                    WorkspaceWalletTransaction::create([
                        'workspace_wallet_id' => $wallet->id,
                        'type'                => 'debit',
                        'amount'              => 0.40,
                        'balance_after'       => $wallet->balance,
                        'description'         => "CSAT Post-delivery feedback message for Order #{$orderNumber}",
                        'reference_id'        => "csat_retention_{$orderNumber}",
                    ]);
                });
            }
        } catch (\Throwable) {
            // Fail open on billing so retention communication is not blocked
        }
    }
}
