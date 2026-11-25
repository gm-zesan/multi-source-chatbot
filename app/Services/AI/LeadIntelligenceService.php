<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Enums\LeadTagEnum;
use App\Models\Conversation;
use App\Models\WorkspaceOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LeadIntelligenceService
{
    /**
     * Classify conversation intent, assign smart lead tags, and generate an executive summary.
     *
     * @return array{tags: array<string>, summary: string, tag_objects: array}
     */
    public function classifyAndTagConversation(Conversation $conversation, bool $force = false): array
    {
        // Don't re-analyze if analyzed within the last 5 minutes unless forced
        if (! $force && $conversation->ai_summary_generated_at && $conversation->ai_summary_generated_at->diffInMinutes(now()) < 5 && ! empty($conversation->ai_tags)) {
            return [
                'tags'        => (array) $conversation->ai_tags,
                'summary'     => (string) $conversation->ai_summary,
                'tag_objects' => $conversation->getAiTagObjects(),
            ];
        }

        // 1. Gather conversation context
        $messages = $conversation->messages()->latest('id')->take(10)->get()->reverse();
        $inboundMessages = $messages->where('direction', 'inbound');
        $allText = $messages->pluck('body')->implode(' ');
        $customerText = $inboundMessages->pluck('body')->implode(' ');

        $detectedTags = [];

        // 2. Check for Orders
        $hasExistingOrders = $conversation->orders()->exists();
        if ($hasExistingOrders) {
            $detectedTags[] = LeadTagEnum::ORDER_PLACED->value;
        }

        // 3. Keyword / Pattern Detection in Customer Text
        $lowerCustomerText = mb_strtolower($customerText . ' ' . ($conversation->last_message ?? ''));
        $lowerAllText = mb_strtolower($allText);

        // A. Complaints & Returns
        $complaintPatterns = [
            'নষ্ট', 'ভাঙা', 'খারাপ', 'দেরি', 'আসে নাই', 'এখনও আসে নাই', 'সমস্যা', 'বাজে', 'রিটার্ন', 'এক্সচেঞ্জ',
            'বাটপার', 'প্রতারক', 'অভিযোগ', 'ভুল প্রোডাক্ট', 'damaged', 'broken', 'wrong item', 'refund', 'return',
            'complaint', 'delay', 'worst', 'scam', 'terrible', 'fake',
        ];
        if ($this->matchesAny($lowerCustomerText, $complaintPatterns)) {
            $detectedTags[] = LeadTagEnum::COMPLAINT->value;
        }

        // B. Human Escalation Requests
        $humanPatterns = [
            'human', 'agent', 'এজেন্ট', 'মানুষের', 'কথা বলতে চাই', 'কারো সাথে কথা', 'admin', 'অ্যাডমিন',
            'owner', 'মালিক', 'call me', 'ফোন দেন', 'নাম্বার দিন', 'কথা বলব', 'talk to person', 'representative',
        ];
        if ($this->matchesAny($lowerCustomerText, $humanPatterns)) {
            $detectedTags[] = LeadTagEnum::HUMAN_REQUESTED->value;
        }

        // C. Order Placed (if not already detected via relation)
        if (! in_array(LeadTagEnum::ORDER_PLACED->value, $detectedTags, true)) {
            $orderPatterns = [
                'অর্ডার কনফার্ম', 'অর্ডার করতে চাই', 'অর্ডার নিবেন', 'ক্যাশ অন ডেলিভারি', 'ঠিকানা:', 'address:',
                'phone:', 'নাম্বার:', 'অর্ডার ফাইনাল', 'confirm order', 'book now', 'send to my address',
            ];
            if ($this->matchesAny($lowerCustomerText, $orderPatterns) && (preg_match('/(01[3-9]\d{8})/', $lowerCustomerText) || str_contains($lowerCustomerText, 'ঠিকানা'))) {
                $detectedTags[] = LeadTagEnum::ORDER_PLACED->value;
            }
        }

        // D. Hot Lead (Purchase Intent / Sizing / Availability)
        $hotLeadPatterns = [
            'নিতে চাই', 'কেনার', 'অর্ডার করব', 'বুকিং', 'সাইজ', 'size', 'কালার', 'color', 'available', 'হবে কি',
            'স্টক', 'urgent', 'আজকেই', 'জরুরি', 'কবে পাব', 'পাঠাতে পারবেন', 'interested', 'buy', 'purchase',
            'অর্ডার কিভাবে করব', 'পছন্দ হয়েছে', 'want to buy', 'in stock',
        ];
        if ($this->matchesAny($lowerCustomerText, $hotLeadPatterns)) {
            $detectedTags[] = LeadTagEnum::HOT_LEAD->value;
        }

        // E. Price Inquiry
        $pricePatterns = [
            'দাম কত', 'কত টাকা', 'price', 'টাকা', 'rate', 'cost', 'খরচ', 'discount', 'অফার', 'কম রাখা',
            'কমানো যাবে', 'delivery charge', 'ডেলিভারি চার্জ', 'শিপিং চার্জ', 'how much', 'offer', 'charge',
        ];
        if ($this->matchesAny($lowerCustomerText, $pricePatterns)) {
            $detectedTags[] = LeadTagEnum::PRICE_INQUIRY->value;
        }

        // F. Fallback if no specific tags found
        if (empty($detectedTags)) {
            $detectedTags[] = LeadTagEnum::GENERAL_FAQ->value;
        }

        // Prioritize order_placed, complaint, hot_lead at the beginning
        $priorityOrder = [
            LeadTagEnum::ORDER_PLACED->value,
            LeadTagEnum::HOT_LEAD->value,
            LeadTagEnum::COMPLAINT->value,
            LeadTagEnum::HUMAN_REQUESTED->value,
            LeadTagEnum::PRICE_INQUIRY->value,
            LeadTagEnum::GENERAL_FAQ->value,
        ];
        usort($detectedTags, function ($a, $b) use ($priorityOrder) {
            $posA = array_search($a, $priorityOrder, true);
            $posB = array_search($b, $priorityOrder, true);
            return ($posA === false ? 99 : $posA) <=> ($posB === false ? 99 : $posB);
        });
        $detectedTags = array_values(array_unique($detectedTags));

        // 4. Generate 1-2 Sentence AI Executive Summary
        $summary = $this->generateConversationSummary($conversation, $detectedTags, $lowerCustomerText);

        // 5. Persist to Conversation with Manager Handoff Evidence
        $fillData = [
            'ai_tags'                 => $detectedTags,
            'ai_summary'              => $summary,
            'ai_summary_generated_at' => now(),
        ];

        $isEscalated = in_array(LeadTagEnum::COMPLAINT->value, $detectedTags, true)
            || in_array(LeadTagEnum::HUMAN_REQUESTED->value, $detectedTags, true);

        if ($isEscalated) {
            $fillData['priority'] = 'urgent';

            $reason = in_array(LeadTagEnum::COMPLAINT->value, $detectedTags, true)
                ? 'customer_complaint'
                : 'human_requested';

            $metadata = $conversation->metadata ?? [];
            $metadata['handoff_to_human'] = true;
            $metadata['escalation_level'] = 'manager';
            $metadata['escalated_at'] = now()->toIso8601String();
            $metadata['escalation_reason'] = $reason;
            $metadata['manager_notification_status'] = 'dispatched';
            $fillData['metadata'] = $metadata;

            $assignedManager = null;
            if ($conversation->assigned_to_user_id === null) {
                $workspace = $conversation->channelAccount?->workspace;
                if ($workspace) {
                    try {
                        $assignedManager = $workspace->users()
                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['owner', 'manager', 'admin', 'superadmin']))
                            ->first() ?? $workspace->users()->first();

                        if ($assignedManager) {
                            $fillData['assigned_to_user_id'] = $assignedManager->id;
                        }
                    } catch (\Throwable $eAssign) {
                        Log::warning('[LeadIntelligenceService] Manager auto-assignment bypassed: ' . $eAssign->getMessage());
                    }
                }
            } else {
                $assignedManager = $conversation->assignedTo;
            }

            // Dispatch formal Escalation Event
            try {
                event(new \App\Events\ConversationEscalatedToHuman(
                    conversation: $conversation,
                    manager: $assignedManager,
                    reason: $reason,
                    priority: 'urgent'
                ));
            } catch (\Throwable $eEvent) {
                Log::warning('[LeadIntelligenceService] Escalation event dispatch non-fatal failure: ' . $eEvent->getMessage());
            }
        }

        $conversation->forceFill($fillData)->save();

        return [
            'tags'        => $detectedTags,
            'summary'     => $summary,
            'tag_objects' => $conversation->getAiTagObjects(),
        ];
    }

    /**
     * Synthesize a concise 1-2 sentence executive briefing for a single conversation.
     *
     * @param array<string> $tags
     */
    protected function generateConversationSummary(Conversation $conversation, array $tags, string $customerText): string
    {
        $customerName = $conversation->customer_name ?: 'Visitor';
        $isBengali = (bool) preg_match('/[\x{0980}-\x{09FF}]/u', $customerText);

        if (in_array(LeadTagEnum::ORDER_PLACED->value, $tags, true)) {
            $latestOrder = $conversation->orders()->latest('id')->first();
            $orderInfo = $latestOrder ? " (৳" . number_format((float) $latestOrder->total_amount, 2) . ")" : "";

            return $isBengali
                ? "{$customerName} সফলভাবে ক্যাশ অন ডেলিভারিতে অর্ডার নিশ্চিত করেছেন{$orderInfo}। ডেলিভারি তথ্য প্রস্তুত রয়েছে।"
                : "{$customerName} confirmed a Cash on Delivery order{$orderInfo}. Delivery details captured and ready for dispatch.";
        }

        if (in_array(LeadTagEnum::COMPLAINT->value, $tags, true)) {
            return $isBengali
                ? "{$customerName} ডেলিভারি বিলম্ব বা প্রোডাক্ট সংক্রান্ত অভিযোগ জানিয়েছেন। জরুরি ভিত্তিতে সাপোর্ট এজেন্টের দৃষ্টি প্রয়োজন।"
                : "{$customerName} submitted an issue regarding delivery delay or product condition. High-priority agent resolution recommended.";
        }

        if (in_array(LeadTagEnum::HUMAN_REQUESTED->value, $tags, true)) {
            return $isBengali
                ? "{$customerName} সরাসরি স্টোর প্রতিনিধির সাথে যোগাযোগের অনুরোধ করেছেন। চ্যাটটি লাইভ এজেন্টের অপেক্ষায় রয়েছে।"
                : "{$customerName} explicitly requested human staff support. Awaiting live representative takeover.";
        }

        if (in_array(LeadTagEnum::HOT_LEAD->value, $tags, true)) {
            return $isBengali
                ? "{$customerName} প্রোডাক্টের সাইজ, স্টক ও কেনার বিষয়ে সক্রিয়ভাবে আলোচনা করছেন। বিক্রয় রূপান্তরের প্রবল সম্ভাবনা রয়েছে।"
                : "{$customerName} is actively inquiring about product specifications, sizing, and purchase. High conversion opportunity.";
        }

        if (in_array(LeadTagEnum::PRICE_INQUIRY->value, $tags, true)) {
            return $isBengali
                ? "{$customerName} প্রোডাক্টের মূল্য, ডিসকাউন্ট অফার এবং ডেলিভারি চার্জ জানতে চেয়েছেন।"
                : "{$customerName} inquired about item pricing, running promotional discounts, and delivery charges.";
        }

        return $isBengali
            ? "{$customerName} স্টোরের সাধারণ তথ্য বা প্রোডাক্ট ক্যাটালগ সম্পর্কে প্রাথমিক অনুসন্ধান করেছেন।"
            : "{$customerName} submitted a general inquiry regarding store catalog or operational hours.";
    }

    /**
     * Generate Workspace-level Daily Executive Summary digest.
     *
     * @return array{
     *     date: string,
     *     total_conversations: int,
     *     hot_leads_count: int,
     *     orders_placed_count: int,
     *     price_inquiries_count: int,
     *     complaints_count: int,
     *     human_requested_count: int,
     *     orders_count: int,
     *     orders_revenue: float,
     *     headline: string,
     *     bullets: array<string>,
     *     recommendation: string,
     * }
     */
    public function generateDailyExecutiveSummary(int $workspaceId, ?Carbon $date = null): array
    {
        $targetDate = $date ?? Carbon::today();

        // 1. Get all active conversations for today in this workspace
        $conversations = Conversation::whereHas('channelAccount', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where(function ($q) use ($targetDate) {
                $q->whereDate('last_message_at', $targetDate)
                  ->orWhereDate('created_at', $targetDate);
            })
            ->get();

        $totalConversations = $conversations->count();

        // 2. Count tag frequencies
        $hotLeadsCount = 0;
        $ordersPlacedCount = 0;
        $priceInquiriesCount = 0;
        $complaintsCount = 0;
        $humanRequestedCount = 0;

        foreach ($conversations as $conv) {
            $tags = (array) ($conv->ai_tags ?? []);
            if (in_array(LeadTagEnum::HOT_LEAD->value, $tags, true)) {
                $hotLeadsCount++;
            }
            if (in_array(LeadTagEnum::ORDER_PLACED->value, $tags, true)) {
                $ordersPlacedCount++;
            }
            if (in_array(LeadTagEnum::PRICE_INQUIRY->value, $tags, true)) {
                $priceInquiriesCount++;
            }
            if (in_array(LeadTagEnum::COMPLAINT->value, $tags, true)) {
                $complaintsCount++;
            }
            if (in_array(LeadTagEnum::HUMAN_REQUESTED->value, $tags, true)) {
                $humanRequestedCount++;
            }
        }

        // 3. Orders & Revenue from WorkspaceOrder
        $todayOrders = WorkspaceOrder::where('workspace_id', $workspaceId)
            ->whereDate('created_at', $targetDate)
            ->get();

        $ordersCount = $todayOrders->count();
        $ordersRevenue = (float) $todayOrders->sum('total_amount');

        // 4. Headline
        if ($totalConversations === 0) {
            $headline = "আজ এখনো কোনো কাস্টমার কনভারসেশন শুরু হয়নি। আপনার AI সহকারী ২৪/৭ প্রস্তুত রয়েছে।";
        } elseif ($ordersPlacedCount > 0 || $ordersCount > 0) {
            $headline = "আজ {$totalConversations}টি কাস্টমার কনভারসেশনের মধ্য থেকে " . max($ordersPlacedCount, $ordersCount) . "টি সফল অর্ডার এবং {$hotLeadsCount}টি হট লিড সনাক্ত হয়েছে!";
        } elseif ($hotLeadsCount > 0) {
            $headline = "আজকের এনগেজমেন্টে {$hotLeadsCount}টি হাই-ইনটেন্ট হট লিড রয়েছে, যা দ্রুত সেলসে রূপান্তরযোগ্য!";
        } else {
            $headline = "আজ {$totalConversations}টি কাস্টমার অনুসন্ধান স্বয়ংক্রিয়ভাবে প্রসেস করা হয়েছে।";
        }

        // 5. Key Bullets
        $bullets = [];
        if ($hotLeadsCount > 0) {
            $bullets[] = "🔥 {$hotLeadsCount} জন কাস্টমার কেনাকাটা করার তীব্র আগ্রহ প্রকাশ করেছেন।";
        }
        if ($ordersPlacedCount > 0 || $ordersCount > 0) {
            $bullets[] = "📦 " . max($ordersPlacedCount, $ordersCount) . "টি অর্ডার চূড়ান্ত হয়েছে (মোট আনুমানিক মূল্য: ৳" . number_format($ordersRevenue, 2) . ")।";
        }
        if ($priceInquiriesCount > 0) {
            $bullets[] = "💰 {$priceInquiriesCount} জন কাস্টমার প্রডাক্টের দাম ও ডেলিভারি চার্জ জানতে চেয়েছেন।";
        }
        if ($complaintsCount > 0) {
            $bullets[] = "⚠️ {$complaintsCount}টি সার্ভিস/ডেলিভারি অভিযোগ এসেছে — দ্রুত ফলো-আপ নিশ্চিত করুন।";
        }
        if ($humanRequestedCount > 0) {
            $bullets[] = "👤 {$humanRequestedCount} জন কাস্টমার মানুষের সহায়তা চেয়েছেন।";
        }

        if (empty($bullets) && $totalConversations > 0) {
            $bullets[] = "ℹ️ কাস্টমাররা সাধারণ এফএকিউ এবং স্টোর ইনফরমেশন সংক্রান্ত তথ্য জানতে চেয়েছেন।";
        }

        // 6. Actionable Recommendation
        if ($complaintsCount > 0) {
            $recommendation = "অভিযোগকৃত চ্যাটগুলোতে তাৎক্ষণিক রিপ্লাই দিয়ে কাস্টমার স্যাটিসফ্যাকশন নিশ্চিত করুন।";
        } elseif ($hotLeadsCount > 0) {
            $recommendation = "হট লিড কাস্টমারদের ইনবক্সে স্পেশাল অফার বা স্টক রিমাইন্ডার দিয়ে অর্ডার ক্লোজ করুন।";
        } else {
            $recommendation = "চ্যানেল প্রচার বাড়াতে সোশ্যাল মিডিয়া বা ওয়েবসাইটে উইজেট শেয়ার করুন।";
        }

        return [
            'date'                  => $targetDate->format('Y-m-d'),
            'date_formatted'        => $targetDate->format('d M, Y'),
            'total_conversations'   => $totalConversations,
            'hot_leads_count'       => $hotLeadsCount,
            'orders_placed_count'   => max($ordersPlacedCount, $ordersCount),
            'price_inquiries_count' => $priceInquiriesCount,
            'complaints_count'      => $complaintsCount,
            'human_requested_count' => $humanRequestedCount,
            'orders_count'          => max($ordersPlacedCount, $ordersCount),
            'orders_revenue'        => $ordersRevenue,
            'headline'              => $headline,
            'bullets'               => $bullets,
            'recommendation'        => $recommendation,
        ];
    }

    protected function matchesAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
