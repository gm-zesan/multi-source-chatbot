<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsPayment;
use App\Models\AnalyticsSalesperson;
use App\Models\WorkspaceOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderAnalyticsProjectionService
{
    /**
     * Project or reconcile a canonical WorkspaceOrder into AnalyticsOrder.
     * Enforces strict multi-tenant scoping on (workspace_id, order_number).
     *
     * Core Financial Rule: Order delivery fulfills the transaction (Booked/Delivered Sales Revenue),
     * but does NOT automatically record cash collected. COD courier settlements must be explicitly
     * recorded to represent true cash receipt.
     */
    public function project(WorkspaceOrder $order): AnalyticsOrder
    {
        return DB::transaction(function () use ($order) {
            $wsId = (int) $order->workspace_id;

            // 1. Resolve or create AnalyticsCustomer (tenant-scoped)
            $customerName = trim((string) ($order->customer_name ?: 'Valued Customer'));
            $customerPhone = trim((string) ($order->customer_phone ?: 'N/A'));

            $customer = AnalyticsCustomer::firstOrCreate(
                [
                    'workspace_id' => $wsId,
                    'name'         => $customerName,
                ],
                [
                    'phone'     => $customerPhone !== 'N/A' ? $customerPhone : null,
                    'is_active' => true,
                ]
            );

            // 2. Resolve or create system AnalyticsSalesperson for the channel / AI
            $sellerCode = 'SELLER-AI-' . $wsId;
            $salesperson = AnalyticsSalesperson::firstOrCreate(
                [
                    'workspace_id'  => $wsId,
                    'employee_code' => $sellerCode,
                ],
                [
                    'name'      => 'AI Sales Assistant (' . ucfirst($order->channel ?: 'Omnichannel') . ')',
                    'is_active' => true,
                ]
            );

            // 3. Map WorkspaceOrder status to canonical AnalyticsOrder status
            // WorkspaceOrder statuses: 'pending', 'confirmed', 'shipped', 'delivered', 'cancelled'
            $existingAnalytics = AnalyticsOrder::where('workspace_id', $wsId)
                ->where('order_number', $order->order_number)
                ->first();

            $analyticsStatus = match ($order->status) {
                'delivered' => 'completed',
                'cancelled' => ($existingAnalytics && in_array($existingAnalytics->status, ['completed', 'delivered', 'returned'], true))
                    ? 'returned'
                    : 'cancelled',
                'shipped'   => 'processing',
                'confirmed' => 'confirmed',
                default     => 'pending',
            };

            $total = (float) ($order->order_amount ?? 0.00) + (float) ($order->delivery_fee ?? 0.00);
            $orderDate = $order->captured_at ? Carbon::parse($order->captured_at)->toDateString() : Carbon::today()->toDateString();

            // 4. Upsert AnalyticsOrder strictly scoped by (workspace_id, order_number)
            $analyticsOrder = AnalyticsOrder::updateOrCreate(
                [
                    'workspace_id' => $wsId,
                    'order_number' => $order->order_number,
                ],
                [
                    'customer_id'    => $customer->id,
                    'salesperson_id' => $salesperson->id,
                    'total_amount'   => $total,
                    'discount'       => 0.00,
                    'net_amount'     => $total,
                    'status'         => $analyticsStatus,
                    'order_date'     => $orderDate,
                ]
            );

            // 5. Immutable Compensating Refund on Cancellation/Return:
            // If an order is cancelled or returned AFTER payments were recorded, record compensating negative refund entries.
            // Never delete historical payments.
            if (in_array($analyticsStatus, ['cancelled', 'returned'], true)) {
                $netPaid = (float) AnalyticsPayment::where('workspace_id', $wsId)
                    ->where('order_id', $analyticsOrder->id)
                    ->sum('amount');

                if ($netPaid > 0) {
                    $this->recordRefund(
                        order: $analyticsOrder,
                        refundAmount: $netPaid,
                        reason: 'order_cancelled_post_payment',
                        transactionRef: 'REV-CANCEL-' . $order->order_number . '-' . uniqid()
                    );
                }
            }

            Log::info('[OrderAnalyticsProjection] Order projected successfully', [
                'workspace_id' => $wsId,
                'order_number' => $order->order_number,
                'ws_status'    => $order->status,
                'bi_status'    => $analyticsStatus,
                'net_amount'   => $total,
            ]);

            return $analyticsOrder;
        });
    }

    /**
     * Record a verified customer payment or courier remittance (Immutable).
     * Supports multiple partial courier settlements and advance payments per order.
     * Enforces explicit transaction classification and provider scoping.
     *
     * @param WorkspaceOrder|AnalyticsOrder $order
     */
    public function recordPayment(
        WorkspaceOrder|AnalyticsOrder $order,
        float $amount,
        string $paymentMethod = 'courier_settlement',
        ?string $transactionRef = null,
        ?Carbon $collectedAt = null,
        ?string $transactionType = null,
        ?string $provider = null
    ): AnalyticsPayment {
        if ($amount <= 0.0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $analyticsOrder = $order instanceof AnalyticsOrder
            ? $order
            : AnalyticsOrder::where('workspace_id', $order->workspace_id)
                ->where('order_number', $order->order_number)
                ->firstOrFail();

        $wsId = (int) $analyticsOrder->workspace_id;
        $txnRef = $transactionRef ?: ('PMT-' . $analyticsOrder->order_number . '-' . uniqid());

        // Resolve explicit transaction classification
        $resolvedType = $transactionType ?? match (true) {
            $paymentMethod === 'courier_settlement' => 'courier_settlement',
            $paymentMethod === 'refund' || $amount < 0 => 'customer_refund',
            in_array(strtolower($paymentMethod), ['bkash', 'nagad', 'rocket', 'upay', 'card', 'bank', 'cash', 'advance'], true) => 'customer_advance',
            default => 'courier_settlement',
        };

        // Resolve provider
        $resolvedProvider = $provider ?? match (true) {
            $resolvedType === 'courier_settlement' => 'courier',
            in_array(strtolower($paymentMethod), ['bkash', 'nagad', 'rocket', 'upay', 'card', 'bank', 'steadfast', 'pathao', 'redx', 'paperfly'], true) => strtolower($paymentMethod),
            default => 'manual',
        };

        return DB::transaction(function () use ($analyticsOrder, $wsId, $amount, $paymentMethod, $resolvedType, $resolvedProvider, $txnRef, $collectedAt) {
            // Lock order for update to ensure concurrency safety
            AnalyticsOrder::where('id', $analyticsOrder->id)->lockForUpdate()->first();

            // Idempotent: prevent duplicate payment insertion on webhook retries within tenant & provider scope
            $existing = AnalyticsPayment::where('workspace_id', $wsId)
                ->where('provider', $resolvedProvider)
                ->where('transaction_ref', $txnRef)
                ->first();

            if ($existing) {
                return $existing;
            }

            return AnalyticsPayment::create([
                'workspace_id'     => $wsId,
                'order_id'         => $analyticsOrder->id,
                'customer_id'      => $analyticsOrder->customer_id,
                'salesperson_id'   => $analyticsOrder->salesperson_id,
                'amount'           => round($amount, 2),
                'payment_method'   => $paymentMethod,
                'transaction_type' => $resolvedType,
                'provider'         => $resolvedProvider,
                'transaction_ref'  => $txnRef,
                'collected_at'     => $collectedAt ?? now(),
            ]);
        });
    }

    /**
     * Record an immutable compensating refund transaction.
     * Enforces strict concurrency safety via row-locking and guarantees cumulative refunds
     * never exceed the refundable balance.
     *
     * @param WorkspaceOrder|AnalyticsOrder $order
     */
    public function recordRefund(
        WorkspaceOrder|AnalyticsOrder $order,
        float $refundAmount,
        string $reason = 'customer_refund',
        ?string $transactionRef = null,
        string $provider = 'manual'
    ): AnalyticsPayment {
        if ($refundAmount <= 0.0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $analyticsOrder = $order instanceof AnalyticsOrder
            ? $order
            : AnalyticsOrder::where('workspace_id', $order->workspace_id)
                ->where('order_number', $order->order_number)
                ->firstOrFail();

        $wsId = (int) $analyticsOrder->workspace_id;
        $txnRef = $transactionRef ?: ('REFUND-' . $analyticsOrder->order_number . '-' . uniqid());

        return DB::transaction(function () use ($analyticsOrder, $wsId, $refundAmount, $provider, $txnRef) {
            // Acquire exclusive row-lock on the order to prevent concurrent double-refunding races
            AnalyticsOrder::where('id', $analyticsOrder->id)->lockForUpdate()->first();

            // 1. Check idempotency: if this exact refund transaction ref was already processed, return it
            $existing = AnalyticsPayment::where('workspace_id', $wsId)
                ->where('provider', $provider)
                ->where('transaction_ref', $txnRef)
                ->first();

            if ($existing) {
                return $existing;
            }

            // 2. Enforce Refund Over-Limit Guard:
            // Calculate current net payments received for this order
            $currentNetPaid = (float) AnalyticsPayment::where('workspace_id', $wsId)
                ->where('order_id', $analyticsOrder->id)
                ->sum('amount');

            $requested = round($refundAmount, 2);

            if ($requested > $currentNetPaid) {
                throw new \InvalidArgumentException(
                    "Requested refund amount (৳{$requested}) exceeds the remaining refundable balance (৳{$currentNetPaid}) for order #{$analyticsOrder->order_number}."
                );
            }

            // 3. Append compensating negative entry into immutable ledger
            return AnalyticsPayment::create([
                'workspace_id'     => $wsId,
                'order_id'         => $analyticsOrder->id,
                'customer_id'      => $analyticsOrder->customer_id,
                'salesperson_id'   => $analyticsOrder->salesperson_id,
                'amount'           => -$requested, // Negative compensating entry
                'payment_method'   => 'refund',
                'transaction_type' => 'customer_refund',
                'provider'         => $provider,
                'transaction_ref'  => $txnRef,
                'collected_at'     => now(),
            ]);
        });
    }

    /**
     * Calculate order-level outstanding courier COD receivable.
     * Formula: max(0, COD Target - Courier Settlements Remitted)
     * where COD Target = max(0, Total Payable - Customer Advance Payments).
     * Strictly uses explicit transaction_type classifications.
     *
     * Accounting Invariant:
     * If an order was delivered (including post-delivery returns), the courier collected COD
     * at the doorstep and owes any remaining unremitted balance to the merchant.
     * Pre-delivery cancelled orders have zero courier receivable.
     */
    public function getOrderCourierReceivable(WorkspaceOrder|AnalyticsOrder $order): float
    {
        $analyticsOrder = $order instanceof AnalyticsOrder
            ? $order
            : AnalyticsOrder::where('workspace_id', $order->workspace_id)
                ->where('order_number', $order->order_number)
                ->first();

        if (! $analyticsOrder) {
            return 0.00;
        }

        // Undelivered or pre-delivery cancelled orders have 0 courier COD receivables.
        // Completed, delivered, and post-delivery returned orders preserve courier collection obligations.
        if (! in_array($analyticsOrder->status, ['completed', 'delivered', 'returned'], true)) {
            return 0.00;
        }

        $totalPayable = (float) $analyticsOrder->net_amount;

        $payments = AnalyticsPayment::where('workspace_id', $analyticsOrder->workspace_id)
            ->where('order_id', $analyticsOrder->id)
            ->get();

        // 1. Advance payments paid by customer before delivery (reduces courier COD target)
        $advancePaid = (float) $payments->where('transaction_type', 'customer_advance')
            ->where('amount', '>', 0)
            ->sum('amount');

        // Order COD target to be collected by courier
        $codTarget = max(0.0, $totalPayable - $advancePaid);

        // 2. Courier settlements remitted by courier company for delivered COD order
        $courierSettled = (float) $payments->where('transaction_type', 'courier_settlement')
            ->where('amount', '>', 0)
            ->sum('amount');

        return max(0.0, round($codTarget - $courierSettled, 2));
    }

    /**
     * Get authoritative financial breakdown separating:
     * - Gross Delivered Sales
     * - Sales Returns
     * - Net Delivered Sales
     * - Gross Cash Inflows
     * - Total Refunds Outflow
     * - Net Cash Collected
     * - Outstanding Courier Receivables (Calculated per delivered order)
     */
    public function getFinancialSummary(int $workspaceId, ?string $startDate = null, ?string $endDate = null): array
    {
        $ordersQuery = AnalyticsOrder::where('workspace_id', $workspaceId);
        $paymentsQuery = AnalyticsPayment::where('workspace_id', $workspaceId);

        if ($startDate !== null && $endDate !== null) {
            $ordersQuery->whereBetween('order_date', [$startDate, $endDate]);
            $paymentsQuery->whereBetween('collected_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ]);
        }

        // 1. Sales Metrics (Gross Delivered includes all fulfilled deliveries before returns)
        $grossDeliveredSales = round((float) (clone $ordersQuery)->whereIn('status', ['completed', 'delivered', 'returned'])->sum('net_amount'), 2);
        $deliveredOrderCount = (int) (clone $ordersQuery)->whereIn('status', ['completed', 'delivered', 'returned'])->count();

        $salesReturns = round((float) (clone $ordersQuery)->where('status', 'returned')->sum('net_amount'), 2);
        $returnedOrderCount = (int) (clone $ordersQuery)->where('status', 'returned')->count();

        $netDeliveredSales = max(0.0, round($grossDeliveredSales - $salesReturns, 2));

        // 2. Cash Flow Metrics (Strictly filtered by explicit transaction_type classifications)
        $grossCashInflow = round((float) (clone $paymentsQuery)
            ->whereIn('transaction_type', ['customer_advance', 'courier_settlement', 'adjustment'])
            ->where('amount', '>', 0)
            ->sum('amount'), 2);

        $totalRefundsOutflow = abs(round((float) (clone $paymentsQuery)
            ->where('transaction_type', 'customer_refund')
            ->where('amount', '<', 0)
            ->sum('amount'), 2));

        $netCashCollected = round($grossCashInflow - $totalRefundsOutflow, 2);

        // 3. Per-Order Courier Receivables (Accurate per-order calculation including post-delivery returns)
        $deliveredOrders = (clone $ordersQuery)->whereIn('status', ['completed', 'delivered', 'returned'])->get();
        $courierCodReceivable = 0.00;

        foreach ($deliveredOrders as $delOrder) {
            $courierCodReceivable += $this->getOrderCourierReceivable($delOrder);
        }
        $courierCodReceivable = round($courierCodReceivable, 2);

        return [
            'gross_delivered_sales'   => $grossDeliveredSales,
            'delivered_revenue'       => $netDeliveredSales, // Canonical backward-compatible alias
            'delivered_order_count'   => $deliveredOrderCount,
            'sales_returns'           => $salesReturns,
            'returned_order_count'    => $returnedOrderCount,
            'net_delivered_sales'     => $netDeliveredSales,
            'gross_cash_inflow'       => $grossCashInflow,
            'total_refunds_outflow'   => $totalRefundsOutflow,
            'net_cash_collected'      => $netCashCollected,
            'courier_cod_receivable'  => $courierCodReceivable,
        ];
    }

    /**
     * Retroactively reconcile all WorkspaceOrders for a workspace.
     */
    public function reconcileWorkspace(int $workspaceId): array
    {
        $projectedCount = 0;
        WorkspaceOrder::where('workspace_id', $workspaceId)
            ->chunkById(100, function ($orders) use (&$projectedCount) {
                foreach ($orders as $order) {
                    $this->project($order);
                    $projectedCount++;
                }
            });

        return [
            'workspace_id'      => $workspaceId,
            'orders_projected'  => $projectedCount,
            'financial_summary' => $this->getFinancialSummary($workspaceId),
        ];
    }
}
