<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\Models\AnalyticsOrder;
use App\Models\WorkspaceOrder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Stringable;

/**
 * CustomerOrderStatusTool (customer_order_status).
 *
 * Safe, read-only customer order lookup tool.
 * Accessible from inbound public channels (Facebook Messenger, WhatsApp, Web Widget)
 * allowing customers to check fulfillment, delivery status, and order details.
 *
 * Privacy Invariant: NEVER exposes cost prices, salesperson identities,
 * internal notes, or business profit margins to the customer.
 */
class CustomerOrderStatusTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'customer_order_status';
    }

    public function description(): Stringable|string
    {
        return 'Customer order status and delivery tracking tool: look up order fulfillment progress, status, and items by order number or customer phone number.';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRequiredPermissions(): array
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'order_number' => $schema->string()
                ->description('The order reference code (e.g. ORD-1001, #1001, or 1001).'),
            'phone' => $schema->string()
                ->description('Optional customer contact phone number for verification.'),
        ];
    }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        // 1. SLA Deadline Guard
        $remaining = $context->getRemainingSeconds();
        if ($remaining < 0.5) {
            throw new AgentExecutionTimeoutException(
                message: "Tool [{$this->name()}] execution aborted: remaining SLA budget ({$remaining}s) is below 0.5s threshold.",
                timeoutSeconds: $context->timeoutSeconds,
                elapsedSeconds: $context->timeoutSeconds - $remaining,
            );
        }

        $workspaceId = $context->getWorkspaceId();
        $orderNumberInput = trim((string) ($arguments['order_number'] ?? ''));
        $phoneInput = trim((string) ($arguments['phone'] ?? ''));

        // 1.1 Rate-Limiting & Enumeration Guard
        $rateKey = "order_lookup_fail_{$workspaceId}_" . ($context->conversation?->id ?? 'global');
        $failures = (int) \Illuminate\Support\Facades\Cache::get($rateKey, 0);
        if ($failures >= 5) {
            return ToolResult::failure('অতিরিক্ত ব্যর্থ চেষ্টার কারণে অর্ডার অনুসন্ধান সাময়িকভাবে স্থগিত করা হয়েছে। অনুগ্রহ করে ১৫ মিনিট পর আবার চেষ্টা করুন (Rate Limit Exceeded)।');
        }

        if (empty($orderNumberInput) && empty($phoneInput)) {
            return ToolResult::failure('অনুগ্রহ করে আপনার অর্ডার নম্বর (Order Number) অথবা ফোন নম্বর প্রদান করুন।');
        }

        // Clean up common prefixes from order numbers like '#', 'ORD-', etc.
        $cleanOrderNum = ltrim($orderNumberInput, '#');

        // 2. Query Live Workspace Orders First (Chatbot / Online Orders)
        $wsQuery = WorkspaceOrder::where('workspace_id', $workspaceId);

        if (! empty($cleanOrderNum)) {
            $wsQuery->where(function ($q) use ($cleanOrderNum, $orderNumberInput) {
                $q->where('order_number', $orderNumberInput)
                    ->orWhere('order_number', $cleanOrderNum)
                    ->orWhere('order_number', 'LIKE', "%{$cleanOrderNum}%");
            });
        }

        if (! empty($phoneInput)) {
            $cleanPhone = preg_replace('/[^\d]/', '', $phoneInput);
            $wsQuery->where(function ($q) use ($phoneInput, $cleanPhone) {
                $q->where('customer_phone', 'LIKE', "%{$phoneInput}%");
                if (strlen($cleanPhone) >= 10) {
                    $q->orWhere('customer_phone', 'LIKE', "%{$cleanPhone}%");
                }
            });
        }

        $wsOrder = $wsQuery->latest('captured_at')->first();

        if ($wsOrder) {
            $isCustomer = $context->isCustomer();
            $isSameConversation = ($context->conversation !== null && $wsOrder->conversation_id !== null && ((int) $wsOrder->conversation_id === (int) $context->conversation->id));

            // IDOR Protection: Enforce customer ownership verification
            if ($isCustomer && $context->conversation !== null && $wsOrder->conversation_id !== null) {
                if (! $isSameConversation) {
                    $cleanProvided = preg_replace('/[^\d]/', '', $phoneInput ?: ($context->conversation?->customer_phone ?? ''));
                    $cleanOrderPhone = preg_replace('/[^\d]/', '', (string) $wsOrder->customer_phone);

                    $isPhoneVerified = (strlen($cleanProvided) >= 10 && strlen($cleanOrderPhone) >= 10 && substr($cleanProvided, -10) === substr($cleanOrderPhone, -10));

                    if (! $isPhoneVerified) {
                        \Illuminate\Support\Facades\Cache::put($rateKey, $failures + 1, now()->addMinutes(15));
                        return ToolResult::failure('নিরাপত্তার স্বার্থে অর্ডারের সাথে নিবন্ধিত মোবাইল নম্বরটি প্রদান করুন (Security Verification Required)।');
                    }
                }
            }

            // Zero PII Leakage on Cross-Session Lookups: Redact specific street address entirely
            $deliveryAddress = (string) ($wsOrder->delivery_address ?? '');
            if ($isCustomer) {
                if (! $isSameConversation && ! empty($deliveryAddress)) {
                    $deliveryAddress = 'সংরক্ষিত (নিরাপত্তাজনিত কারণে সম্পূর্ণ ঠিকানা গোপন রাখা হয়েছে)';
                } elseif (! empty($deliveryAddress)) {
                    $deliveryAddress = preg_replace('/(?:\b(?:house|flat|road|holding|sector|block|বাসা|রোড|বাড়ি|হোল্ডিং)\s*[:#\d\-\/a-zA-Z]+)/iu', '***', $deliveryAddress);
                }
            }

            $customerName = (string) ($wsOrder->customer_name ?? '');
            if ($isCustomer && ! $isSameConversation && ! empty($customerName)) {
                $customerName = mb_substr($customerName, 0, 3) . '***';
            }

            $maskedPhone = (string) ($wsOrder->customer_phone ?? '');
            if ($isCustomer && ! empty($maskedPhone)) {
                $cleanPhone = preg_replace('/[^\d]/', '', $maskedPhone);
                $maskedPhone = strlen($cleanPhone) >= 8 ? substr($cleanPhone, 0, 3) . '****' . substr($cleanPhone, -4) : '***';
            }

            \Illuminate\Support\Facades\Cache::forget($rateKey);

            $statusLabel = match (strtolower((string) $wsOrder->status)) {
                'completed', 'delivered' => 'Delivered (ডেলিভার সম্পন্ন)',
                'shipped' => 'Shipped (কুরিয়ারে হস্তান্তর করা হয়েছে)',
                'confirmed' => 'Confirmed (অর্ডার নিশ্চিত করা হয়েছে)',
                'processing' => 'Processing (প্যাকেজিং ও প্রসেসিং চলছে)',
                'pending' => 'Pending (অর্ডার প্রক্রিয়াধীন রয়েছে)',
                'cancelled' => 'Cancelled (বাতিল করা হয়েছে)',
                default => ucfirst((string) $wsOrder->status),
            };

            $amount = (float) ($wsOrder->order_amount ?? 0);
            $safeItems = [
                [
                    'product_name' => $wsOrder->product_summary ?: 'Standard Item',
                    'quantity'     => 1,
                    'unit_price'   => $amount,
                    'subtotal'     => $amount,
                ],
            ];

            $lines = [
                "📦 অর্ডার স্ট্যাটাস (#{$wsOrder->order_number}):",
                "- বর্তমান স্ট্যাটাস: {$statusLabel}",
                "- পণ্য: " . ($wsOrder->product_summary ?: 'স্ট্যান্ডার্ড আইটেম'),
            ];

            if ($amount > 0) {
                $lines[] = "- মোট মূল্য: ৳" . number_format($amount, 2);
            }
            if (! empty($wsOrder->courier_provider)) {
                $lines[] = "- কুরিয়ার সার্ভিস: " . ucfirst((string) $wsOrder->courier_provider);
            }
            if (! empty($wsOrder->courier_tracking_code)) {
                $lines[] = "- ট্র্যাকিং কোড: {$wsOrder->courier_tracking_code}";
            }
            if (! empty($wsOrder->courier_status)) {
                $lines[] = "- কুরিয়ার আপডেট: {$wsOrder->courier_status}";
            }
            if (! empty($deliveryAddress)) {
                $lines[] = "- ডেলিভারি ঠিকানা: {$deliveryAddress}";
            }
            $lines[] = "- আনুমানিক সময়: সাধারণত অর্ডারের ২-৩ কার্যদিবসের মধ্যে ডেলিভারি সম্পন্ন হয়।";
            $reportStr = implode("\n", $lines);

            return ToolResult::success([
                'found'             => true,
                'source'            => 'live_workspace_order',
                'order_number'      => (string) $wsOrder->order_number,
                'status'            => $statusLabel,
                'raw_status'        => (string) $wsOrder->status,
                'order_date'        => (string) ($wsOrder->captured_at?->toDateString() ?? now()->toDateString()),
                'total_amount'      => $amount,
                'discount'          => 0.0,
                'net_amount'        => $amount,
                'items'             => $safeItems,
                'customer_name'     => (string) ($wsOrder->customer_name ?? ''),
                'customer_phone'    => $isCustomer ? $maskedPhone : (string) ($wsOrder->customer_phone ?? ''),
                'delivery_address'  => $deliveryAddress,
                'courier_provider'  => $wsOrder->courier_provider,
                'courier_tracking_code' => $wsOrder->courier_tracking_code,
                'courier_status'    => $wsOrder->courier_status,
                'report'            => $reportStr,
                'delivery_estimate' => 'সাধারণত অর্ডারের ২-৩ কার্যদিবসের মধ্যে ডেলিভারি সম্পন্ন হয়।',
            ], [
                'execution_time_ms' => $context->getElapsedMs(),
                'tool_name'         => $this->name(),
            ]);
        }

        // 3. Fallback: Query Historical/Spreadsheet Ingested Orders (AnalyticsOrder)
        $query = AnalyticsOrder::where('workspace_id', $workspaceId)
            ->with(['items.product', 'customer']);

        if (! empty($cleanOrderNum)) {
            $query->where(function ($q) use ($cleanOrderNum, $orderNumberInput) {
                $q->where('order_number', $orderNumberInput)
                    ->orWhere('order_number', $cleanOrderNum)
                    ->orWhere('order_number', 'LIKE', "%{$cleanOrderNum}%");
            });
        }

        if (! empty($phoneInput)) {
            $query->whereHas('customer', function ($q) use ($phoneInput) {
                $q->where('phone', 'LIKE', "%{$phoneInput}%");
            });
        }

        $order = $query->latest('order_date')->first();

        if (! $order) {
            \Illuminate\Support\Facades\Cache::put($rateKey, $failures + 1, now()->addMinutes(15));
            $notFoundMsg = "দুঃখিত, '{$orderNumberInput}' নম্বরের কোনো অর্ডার খুঁজে পাওয়া যায়নি। অনুগ্রহ করে সঠিক অর্ডার নম্বরটি চেক করুন অথবা আমাদের হেল্পলাইনে যোগাযোগ করুন।";
            return ToolResult::success([
                'found'   => false,
                'report'  => $notFoundMsg,
                'message' => $notFoundMsg,
            ], [
                'execution_time_ms' => $context->getElapsedMs(),
                'tool_name'         => $this->name(),
            ]);
        }

        // Map items safely without exposing cost_price or internal profit
        $safeItems = [];
        if ($order->items) {
            foreach ($order->items as $item) {
                $safeItems[] = [
                    'product_name' => $item->product?->name ?? 'Standard Item',
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                ];
            }
        }

        $statusLabel = match (strtolower((string) $order->status)) {
            'completed', 'delivered' => 'Delivered (ডেলিভার সম্পন্ন)',
            'shipped' => 'Shipped (কুরিয়ারে হস্তান্তর করা হয়েছে)',
            'processing' => 'Processing (প্যাকেজিং ও প্রসেসিং চলছে)',
            'pending' => 'Pending (অর্ডার নিশ্চিতকরণের অপেক্ষায়)',
            'cancelled' => 'Cancelled (বাতিল করা হয়েছে)',
            default => ucfirst((string) $order->status),
        };

        $reportLines = [
            "📦 অর্ডার স্ট্যাটাস (#{$order->order_number}):",
            "- বর্তমান স্ট্যাটাস: {$statusLabel}",
        ];
        if ($order->order_date) {
            $reportLines[] = "- অর্ডারের তারিখ: " . $order->order_date->toDateString();
        }
        $reportLines[] = "- মোট মূল্য: ৳" . number_format((float) $order->net_amount, 2);
        if (! empty($safeItems)) {
            $itemNames = array_column($safeItems, 'product_name');
            $reportLines[] = "- পণ্য: " . implode(', ', $itemNames);
        }
        $reportLines[] = "- আনুমানিক সময়: সাধারণত অর্ডারের ২-৩ কার্যদিবসের মধ্যে ডেলিভারি সম্পন্ন হয়।";
        $reportStr = implode("\n", $reportLines);

        return ToolResult::success([
            'found' => true,
            'source' => 'analytics_order',
            'order_number' => (string) $order->order_number,
            'status' => $statusLabel,
            'raw_status' => (string) $order->status,
            'order_date' => (string) $order->order_date?->toDateString(),
            'total_amount' => (float) $order->total_amount,
            'discount' => (float) $order->discount,
            'net_amount' => (float) $order->net_amount,
            'items' => $safeItems,
            'report' => $reportStr,
            'delivery_estimate' => 'সাধারণত অর্ডারের ২-৩ কার্যদিবসের মধ্যে ডেলিভারি সম্পন্ন হয়।',
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name' => $this->name(),
        ]);
    }
}
