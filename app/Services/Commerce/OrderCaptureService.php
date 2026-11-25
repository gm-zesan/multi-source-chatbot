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

        // Catalog matching: Validate product against workspace catalog if available
        try {
            $catalogProducts = \App\Models\AnalyticsProduct::where('workspace_id', $workspace->id)
                ->where('is_active', true)
                ->get();

            if ($catalogProducts->isNotEmpty()) {
                $searchHaystack = mb_strtolower($userMessage . ' ' . ($extracted['product'] ?? ''));
                $matchedCatalogProduct = null;

                foreach ($catalogProducts as $catItem) {
                    $catName = mb_strtolower(trim($catItem->name));
                    $catNameBase = trim(preg_replace('/\s*\([^)]*\)/u', '', $catName));
                    $catSku = mb_strtolower(trim((string) $catItem->sku));
                    $extractedClean = trim(preg_replace('/^(?:[0-9০-৯]+|\b(?:one|two|a|an)\b)\s*(?:টা|টি|পিস|pcs|pieces?)\s*/iu', '', mb_strtolower((string) ($extracted['product'] ?? ''))));

                    if (! empty($catSku) && str_contains($searchHaystack, $catSku)) {
                        $matchedCatalogProduct = $catItem;
                        break;
                    }
                    if (str_contains($searchHaystack, $catName) || ($catNameBase !== '' && str_contains($searchHaystack, $catNameBase))) {
                        $matchedCatalogProduct = $catItem;
                        break;
                    }
                    if ($extractedClean !== '' && (str_contains($catName, $extractedClean) || ($catNameBase !== '' && str_contains($extractedClean, $catNameBase)))) {
                        $matchedCatalogProduct = $catItem;
                        break;
                    }
                }

                if ($matchedCatalogProduct !== null) {
                    $sizeSuffix = '';
                    if (! empty($extracted['product']) && preg_match('/(?:সাইজ|size)\s*[:=\-]?\s*([a-zA-Z0-9]+)/iu', $extracted['product'], $mSize)) {
                        $sizeSuffix = ', সাইজ ' . strtoupper($mSize[1]);
                    }
                    $extracted['product'] = $matchedCatalogProduct->name . $sizeSuffix;
                    if (empty($extracted['amount']) || (float) $extracted['amount'] <= 0) {
                        $extracted['amount'] = (float) $matchedCatalogProduct->unit_price;
                    }
                }
            }
        } catch (\Throwable $eCatalog) {
            Log::warning('[OrderCaptureService] Catalog matching bypassed: ' . $eCatalog->getMessage());
        }

        // Idempotency & Retry Deduplication:
        // Compute message fingerprint combining normalized user message, customer phone, and normalized product
        $fingerprintPayload = trim($userMessage) . '|' . ($extracted['phone'] ?? '') . '|' . ($extracted['product'] ?? '');
        $requestFingerprint = hash('sha256', $fingerprintPayload);
        $extractedProductNorm = mb_strtolower(trim((string) ($extracted['product'] ?? '')));

        // Check if an existing pending order matches this customer within 1 hour
        $existing = WorkspaceOrder::where('workspace_id', $workspace->id)
            ->where('customer_phone', $extracted['phone'])
            ->where('status', 'pending')
            ->where('created_at', '>=', Carbon::now()->subHours(1))
            ->where(function ($q) use ($requestFingerprint, $extractedProductNorm, $conversation) {
                $q->where('admin_notes', 'LIKE', "%FP:{$requestFingerprint}%");
                if ($extractedProductNorm !== '' && $extractedProductNorm !== 'general order inquiry') {
                    $q->orWhere(function ($sub) use ($extractedProductNorm) {
                        $sub->whereRaw('LOWER(product_summary) = ?', [$extractedProductNorm])
                            ->orWhere('product_summary', 'LIKE', "%{$extractedProductNorm}%");
                    });
                } else {
                    $q->orWhere('conversation_id', $conversation->id);
                }
            })
            ->first();

        if ($existing) {
            // Update address or notes if newly provided in the retry
            if (! empty($extracted['address'])) {
                $existing->delivery_address = $extracted['address'];
                $existing->save();
            }

            // Ensure lifecycle CRM tags are present
            $tags = is_array($conversation->ai_tags) ? $conversation->ai_tags : (json_decode($conversation->ai_tags ?? '[]', true) ?: []);
            $tags = array_values(array_diff($tags, ['needs_phone']));
            $tags = array_values(array_unique(array_merge($tags, ['lead', 'hot_lead', 'order_placed'])));
            $conversation->update([
                'ai_tags'  => $tags,
                'priority' => 'high',
            ]);

            Log::info('[OrderCaptureService] Deduplicated retry request using fingerprint: existing order returned', [
                'order_id'    => $existing->id,
                'fingerprint' => $requestFingerprint,
                'product'     => $existing->product_summary,
            ]);

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
            'admin_notes'      => "FP:{$requestFingerprint}",
            'captured_at'      => Carbon::now(),
        ]);

        // BI Canonical Projection: Project new order into AnalyticsOrder (idempotent)
        try {
            app(\App\Services\Analytics\OrderAnalyticsProjectionService::class)->project($order);
        } catch (\Throwable $e) {
            Log::warning('[OrderCaptureService] Failed to project to AnalyticsOrder: ' . $e->getMessage());
        }

        // Lifecycle CRM Update: Update conversation tags and customer name
        $tags = is_array($conversation->ai_tags) ? $conversation->ai_tags : (json_decode($conversation->ai_tags ?? '[]', true) ?: []);
        $tags = array_values(array_diff($tags, ['needs_phone']));
        $tags = array_values(array_unique(array_merge($tags, ['lead', 'hot_lead', 'order_placed'])));
        $updateFields = [
            'ai_tags'  => $tags,
            'priority' => 'high',
        ];
        if (! empty($extracted['name']) && (empty($conversation->customer_name) || str_starts_with($conversation->customer_name, 'Visitor_') || $conversation->customer_name === 'Customer')) {
            $updateFields['customer_name'] = $extracted['name'];
        }
        $conversation->update($updateFields);

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
     * Format a structured, polite Bengali order confirmation reply.
     */
    public function formatOrderConfirmationReply(WorkspaceOrder $order): string
    {
        $name = trim((string) $order->customer_name);
        $greeting = (! empty($name) && $name !== 'Customer') ? "ধন্যবাদ {$name} ভাইয়া/আপু!" : "ধন্যবাদ!";

        $lines = [
            "🎉 {$greeting} আপনার অর্ডারটি সফলভাবে গ্রহণ করা হয়েছে।",
            "",
            "📦 অর্ডার নম্বর: #{$order->order_number}",
        ];

        if (! empty($order->product_summary)) {
            $lines[] = "🛍️ প্রোডাক্ট: {$order->product_summary}";
        }

        if (! empty($order->customer_phone)) {
            $lines[] = "📞 ফোন নম্বর: {$order->customer_phone}";
        }

        if (! empty($order->delivery_address)) {
            $lines[] = "📍 ডেলিভারি ঠিকানা: {$order->delivery_address}";
        }

        if ($order->order_amount !== null && (float) $order->order_amount > 0) {
            $lines[] = "💵 মোট মূল্য: ৳" . number_format((float) $order->order_amount, 2);
        }

        $lines[] = "";
        $lines[] = "আমাদের কাস্টমার সাপোর্ট টিম দ্রুত আপনার সাথে ফোনে যোগাযোগ করে অর্ডারটি কনফার্ম করবেন। সাধারণত ২-৩ কার্যদিবসের মধ্যে ডেলিভারি সম্পন্ন হয়। ধন্যবাদ!";

        return implode("\n", $lines);
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

        // 0. Convert Bengali numerals (০-৯) to standard English digits (0-9)
        $bnToEn = ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'];
        $normalizedText = strtr($text, $bnToEn);

        // 1. Phone Number Detection (BD Mobile: 013-019 supporting spaces, hyphens, and +880 prefix)
        if (preg_match('/(?:\+?88\s*0?|0)\s*1[3-9](?:[\s\-]*\d){8}/u', $normalizedText, $m)) {
            $clean = preg_replace('/[^\d]/', '', $m[0]);
            if (str_starts_with($clean, '8801') && strlen($clean) === 13) {
                $phone = '0' . substr($clean, 3);
            } elseif (str_starts_with($clean, '881') && strlen($clean) === 12) {
                $phone = '0' . substr($clean, 2);
            } elseif (str_starts_with($clean, '01') && strlen($clean) === 11) {
                $phone = $clean;
            } elseif (str_starts_with($clean, '1') && strlen($clean) === 10) {
                $phone = '0' . $clean;
            }
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
        } elseif (preg_match('/(?:আমি\s+)?([0-9\d]+\s*(?:টি|টা|পিস|pcs|pieces?)?\s*[^\,\.\n\r]+?)\s*(?:নিতে\s*চাই|অর্ডার\s*করব|অর্ডার\s*করতে\s*চাই|কিনতে\s*চাই|অর্ডার\s*দিতে\s*চাই|order\s*korte\s*chai)/iu', $normalizedText, $matches)) {
            $product = trim($matches[1]);
        } elseif ($conversation?->relationLoaded('messages')) {
            foreach ($conversation->messages as $m) {
                if ($m->direction === 'inbound') {
                    $mNorm = strtr($m->body, $bnToEn);
                    if (preg_match('/(?:^|[^\p{L}\p{N}])(?:পণ্য|প্রোডাক্ট|Product|Item|আইটেম)\s*[:=\-]\s*([^\n,\r]+)/iu', $m->body, $matches)) {
                        $product = trim($matches[1]);
                        break;
                    } elseif (preg_match('/(?:আমি\s+)?([0-9\d]+\s*(?:টি|টা|পিস|pcs|pieces?)?\s*[^\,\.\n\r]+?)\s*(?:নিতে\s*চাই|অর্ডার\s*করব|অর্ডার\s*করতে\s*চাই|কিনতে\s*চাই|অর্ডার\s*দিতে\s*চাই|order\s*korte\s*chai)/iu', $mNorm, $matches)) {
                        $product = trim($matches[1]);
                        break;
                    }
                }
            }
        }

        // If size is mentioned in the text, append it to the product for clarity
        if (! empty($product) && preg_match('/(?:সাইজ|size)\s*[:=\-]?\s*([a-zA-Z0-9]+)/iu', $text, $mSize)) {
            $product .= ', সাইজ ' . strtoupper($mSize[1]);
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
