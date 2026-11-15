<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use App\Models\AnalyticsOrder;
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

        if (empty($orderNumberInput) && empty($phoneInput)) {
            return ToolResult::failure('অনুগ্রহ করে আপনার অর্ডার নম্বর (Order Number) অথবা ফোন নম্বর প্রদান করুন।');
        }

        // Clean up common prefixes from order numbers like '#', 'ORD-', etc.
        $cleanOrderNum = ltrim($orderNumberInput, '#');

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
            return ToolResult::success([
                'found'   => false,
                'message' => "দুঃখিত, '{$orderNumberInput}' নম্বরের কোনো অর্ডার খুঁজে পাওয়া যায়নি। অনুগ্রহ করে সঠিক অর্ডার নম্বরটি চেক করুন অথবা আমাদের হেল্পলাইনে যোগাযোগ করুন।",
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
                    'quantity'     => (int) $item->quantity,
                    'unit_price'   => (float) $item->unit_price,
                    'subtotal'     => (float) $item->subtotal,
                ];
            }
        }

        $statusLabel = match (strtolower((string) $order->status)) {
            'completed', 'delivered' => 'Delivered (ডেলিভার সম্পন্ন)',
            'shipped'                => 'Shipped (কুরিয়ারে হস্তান্তর করা হয়েছে)',
            'processing'             => 'Processing (প্যাকেজিং ও প্রসেসিং চলছে)',
            'pending'                => 'Pending (অর্ডার নিশ্চিতকরণের অপেক্ষায়)',
            'cancelled'              => 'Cancelled (বাতিল করা হয়েছে)',
            default                  => ucfirst((string) $order->status),
        };

        return ToolResult::success([
            'found'             => true,
            'order_number'      => (string) $order->order_number,
            'status'            => $statusLabel,
            'raw_status'        => (string) $order->status,
            'order_date'        => (string) $order->order_date?->toDateString(),
            'total_amount'      => (float) $order->total_amount,
            'discount'          => (float) $order->discount,
            'net_amount'        => (float) $order->net_amount,
            'items'             => $safeItems,
            'delivery_estimate' => 'সাধারণত অর্ডারের ২-৩ কার্যদিবসের মধ্যে ডেলিভারি সম্পন্ন হয়।',
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }
}
