<?php

declare(strict_types=1);

namespace App\Services\Commerce;

use App\Models\Conversation;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderCaptureService
{
    /**
     * Inspect conversation turn and extract structured order/lead if contact info is present.
     */
    public function extractAndCapture(
        Conversation $conversation,
        string $userMessage,
        ?string $aiResponse = null
    ): ?WorkspaceOrder {
        $conversation->loadMissing('channelAccount.workspace');
        $workspace = $conversation->channelAccount?->workspace;

        if (! $workspace || ! $workspace->isOrderAutoCaptureEnabled()) {
            return null;
        }

        // Prioritize extracting directly from customer user message
        $extracted = $this->parseDetails($userMessage, $conversation);

        // If essential fields are missing from user message, supplement from aiResponse
        if ((empty($extracted['phone']) || empty($extracted['product'])) && !empty($aiResponse)) {
            $extractedAi = $this->parseDetails($aiResponse, $conversation);
            $extracted['phone'] = $extracted['phone'] ?: $extractedAi['phone'];
            $extracted['name'] = $extracted['name'] ?: $extractedAi['name'];
            $extracted['address'] = $extracted['address'] ?: $extractedAi['address'];
            $extracted['product'] = $extracted['product'] ?: $extractedAi['product'];
            $extracted['amount'] = $extracted['amount'] ?: $extractedAi['amount'];
        }

        if (empty($extracted['phone'])) {
            $hasPurchaseIntent = (bool) preg_match('/(?:নিতে\s*চাই|কিনতে\s*চাই|অর্ডার\s*করব|অর্ডার\s*করতে\s*চাই|অর্ডার\s*দিতে\s*চাই|অর্ডার|order|buy|purchase)/iu', $userMessage);
            if ($hasPurchaseIntent || ! empty($extracted['product']) || ! empty($extracted['address'])) {
                $tags = is_array($conversation->ai_tags) ? $conversation->ai_tags : (json_decode($conversation->ai_tags ?? '[]', true) ?: []);
                $tags = array_values(array_unique(array_merge($tags, ['lead', 'hot_lead', 'needs_phone'])));
                $conversation->update([
                    'ai_tags'  => $tags,
                    'priority' => 'high',
                ]);
            }
            return null;
        }

        // Avoid re-capturing duplicate pending orders for the same phone within 1 hour in the same conversation
        $existing = WorkspaceOrder::where('workspace_id', $workspace->id)
            ->where('conversation_id', $conversation->id)
            ->where('customer_phone', $extracted['phone'])
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->first();

        if ($existing) {
            // Update address or notes if newly provided
            if (empty($existing->delivery_address) && ! empty($extracted['address'])) {
                $existing->delivery_address = $extracted['address'];
                $existing->save();
            }
            return $existing;
        }

        $channelSlug = $conversation->channelAccount?->channel?->slug ?? 'web';

        $order = WorkspaceOrder::create([
            'workspace_id'     => $workspace->id,
            'conversation_id'  => $conversation->id,
            'order_number'     => 'ORD-' . Carbon::now()->format('ymd') . '-' . Str::upper(Str::random(4)),
            'channel'          => $channelSlug,
            'customer_name'    => $extracted['name'] ?: ($conversation->customer_name ?: 'Customer'),
            'customer_phone'   => $extracted['phone'],
            'delivery_address' => $extracted['address'] ?? null,
            'product_summary'  => $extracted['product'] ?? 'General Order Inquiry',
            'order_amount'     => $extracted['amount'] ?? null,
            'status'           => 'pending',
            'customer_notes'   => Str::limit($userMessage, 255),
            'captured_at'      => Carbon::now(),
        ]);

        Log::info('[OrderCaptureService] Captured new customer order/lead', [
            'workspace_id'    => $workspace->id,
            'order_id'        => $order->id,
            'order_number'    => $order->order_number,
            'phone'           => $order->customer_phone,
            'customer_name'   => $order->customer_name,
        ]);

        return $order;
    }

    /**
     * Parse phone, name, address, and product details from text.
     *
     * @return array{phone: ?string, name: ?string, address: ?string, product: ?string, amount: ?float}
     */
    public function parseDetails(string $text, ?Conversation $conversation = null): array
    {
        $phone = null;
        $name = null;
        $address = null;
        $product = null;
        $amount = null;

        // 1. Phone Number Detection (BD Mobile: 013-019)
        if (preg_match('/(?:\+?880|0)?(1[3-9]\d{8})\b/', $text, $matches)) {
            $phone = '0' . $matches[1];
        }

        // 2. Name Detection
        if (preg_match('/(?:নাম|Name|নামঃ|Name:)\s*[:=\-]?\s*([^\n,\r]+)/iu', $text, $matches)) {
            $name = trim($matches[1]);
        } elseif ($conversation?->customer_name && ! str_starts_with($conversation->customer_name, 'Visitor_')) {
            $name = $conversation->customer_name;
        }

        // 3. Address Detection
        if (preg_match('/(?:ঠিকানা|Address|ঠিকানাঃ|Address:)\s*[:=\-]?\s*([^\n\r]+)/iu', $text, $matches)) {
            $address = trim($matches[1]);
        } elseif (preg_match('/(?:Road|House|Sector|Block|Mirpur|Dhanmondi|Gulshan|Uttara|Dhaka|Chittagong|Khulna|Sylhet|Rajshahi|Barisal|Rangpur|রোড|বাড়ি|বাসা|সেক্টর|মিরপুর|ধানমন্ডি|গুলশান|উত্তরা|ঢাকা|চট্টগ্রাম|খুলনা|সিলেট|থানা|জেলা)\b[^\n\r]*/iu', $text, $matches)) {
            $address = trim($matches[0]);
        } elseif ($conversation?->relationLoaded('messages')) {
            foreach ($conversation->messages as $m) {
                if ($m->direction === 'inbound') {
                    if (preg_match('/(?:ঠিকানা|Address|ঠিকানাঃ|Address:)\s*[:=\-]?\s*([^\n\r]+)/iu', $m->body, $matches)) {
                        $address = trim($matches[1]);
                        break;
                    } elseif (preg_match('/(?:Road|House|Sector|Block|Mirpur|Dhanmondi|Gulshan|Uttara|Dhaka|Chittagong|Khulna|Sylhet|Rajshahi|Barisal|Rangpur|রোড|বাড়ি|বাসা|সেক্টর|মিরপুর|ধানমন্ডি|গুলশান|উত্তরা|ঢাকা|চট্টগ্রাম|খুলনা|সিলেট|থানা|জেলা)\b[^\n\r]*/iu', $m->body, $matches)) {
                        $address = trim($matches[0]);
                        break;
                    }
                }
            }
        }

        // 4. Product Detection
        if (preg_match('/(?:^|[^\p{L}\p{N}])(?:পণ্য|প্রোডাক্ট|Product|Item|আইটেম)\s*[:=\-]\s*([^\n,\r]+)/iu', $text, $matches)) {
            $product = trim($matches[1]);
        } elseif (preg_match('/(?:আমি\s+)?(\d+\s*(?:টি|টা|পিস|pcs|pieces?)?\s*[\p{L}\p{N}\s\-]+?)\s*(?:নিতে\s*চাই|অর্ডার\s*করব|অর্ডার\s*করতে\s*চাই|কিনতে\s*চাই|অর্ডার\s*দিতে\s*চাই|order\s*korte\s*chai)/iu', $text, $matches)) {
            $product = trim($matches[1]);
        } elseif ($conversation?->relationLoaded('messages')) {
            foreach ($conversation->messages as $m) {
                if ($m->direction === 'inbound') {
                    if (preg_match('/(?:^|[^\p{L}\p{N}])(?:পণ্য|প্রোডাক্ট|Product|Item|আইটেম)\s*[:=\-]\s*([^\n,\r]+)/iu', $m->body, $matches)) {
                        $product = trim($matches[1]);
                        break;
                    } elseif (preg_match('/(?:আমি\s+)?(\d+\s*(?:টি|টা|পিস|pcs|pieces?)?\s*[\p{L}\p{N}\s\-]+?)\s*(?:নিতে\s*চাই|অর্ডার\s*করব|অর্ডার\s*করতে\s*চাই|কিনতে\s*চাই|অর্ডার\s*দিতে\s*চাই|order\s*korte\s*chai)/iu', $m->body, $matches)) {
                        $product = trim($matches[1]);
                        break;
                    }
                }
            }
        }

        // 5. Amount Detection (e.g. ৳500 or 500 taka)
        if (preg_match('/(?:৳|Tk\.?|BDT)\s*([0-9,]+(?:\.[0-9]{2})?)/iu', $text, $matches)) {
            $amount = (float) str_replace(',', '', $matches[1]);
        }

        return [
            'phone'   => $phone,
            'name'    => $name,
            'address' => $address,
            'product' => $product,
            'amount'  => $amount,
        ];
    }
}
