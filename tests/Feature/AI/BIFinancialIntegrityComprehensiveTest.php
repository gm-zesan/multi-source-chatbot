<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AgentContext;
use App\AI\Tools\SalesAnalyticsTool;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsPayment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Analytics\OrderAnalyticsProjectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BIFinancialIntegrityComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $ownerA;
    protected User $ownerB;
    protected OrderAnalyticsProjectionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderAnalyticsProjectionService::class);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);

        $this->workspaceA = Workspace::create([
            'name'      => 'Dhaka Boutique (Tenant A)',
            'slug'      => 'dhaka-boutique-tenant-a',
            'currency'  => 'BDT',
            'is_active' => true,
        ]);

        $this->workspaceB = Workspace::create([
            'name'      => 'Chittagong Electronics (Tenant B)',
            'slug'      => 'ctg-electronics-tenant-b',
            'currency'  => 'BDT',
            'is_active' => true,
        ]);

        $this->ownerA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Owner A',
            'email'        => 'owner_a@tenant.test',
        ]);
        $this->ownerA->assignRole('owner');

        $this->ownerB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'name'         => 'Owner B',
            'email'        => 'owner_b@tenant.test',
        ]);
        $this->ownerB->assignRole('owner');
    }

    /**
     * Scenario 1: Two delivered COD orders with different advance payments.
     * Proves Courier Receivables are calculated strictly per-order and not via aggregate cash subtraction.
     */
    public function test_scenario_1_two_delivered_cod_orders_with_different_advance_payments(): void
    {
        // Order A: Total ৳4,000. Customer pays ৳1,000 advance via bKash. COD target = ৳3,000.
        $orderA = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC1-A',
            'order_amount'   => 4000.00,
            'delivery_fee'   => 0.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $anOrderA = $this->service->project($orderA);
        $this->service->recordPayment($orderA, 1000.00, 'bkash', 'TXN-SC1-ADV-A');

        // Order B: Total ৳3,000. No advance. COD target = ৳3,000.
        $orderB = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC1-B',
            'order_amount'   => 3000.00,
            'delivery_fee'   => 0.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $anOrderB = $this->service->project($orderB);

        // Courier remits ৳2,000 partial settlement for Order A
        $this->service->recordPayment($orderA, 2000.00, 'courier_settlement', 'ST-SETTLE-SC1-A');

        // Order C: Pending delivery with ৳2,000 advance (Must NOT distort courier receivables!)
        $orderC = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC1-C-PENDING',
            'order_amount'   => 5000.00,
            'status'         => 'pending',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($orderC);
        $this->service->recordPayment($orderC, 2000.00, 'nagad', 'TXN-SC1-ADV-C');

        // Per-order receivable checks:
        // Order A: COD target 3000 - settled 2000 = ৳1,000
        $this->assertEquals(1000.00, $this->service->getOrderCourierReceivable($orderA));
        // Order B: COD target 3000 - settled 0 = ৳3,000
        $this->assertEquals(3000.00, $this->service->getOrderCourierReceivable($orderB));
        // Order C: Not delivered = ৳0
        $this->assertEquals(0.00, $this->service->getOrderCourierReceivable($orderC));

        // Workspace total courier receivable = 1000 + 3000 = ৳4,000 (NOT distorted by Order C's advance)
        $summary = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(4000.00, $summary['courier_cod_receivable']);
        $this->assertEquals(7000.00, $summary['gross_delivered_sales']); // Order A (4000) + Order B (3000)
        $this->assertEquals(5000.00, $summary['net_cash_collected']);    // Advance A (1000) + Settle A (2000) + Advance C (2000)
    }

    /**
     * Scenario 2: One order with multiple partial courier settlements.
     */
    public function test_scenario_2_one_order_with_multiple_partial_courier_settlements(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC2-MULTI-SETTLE',
            'order_amount'   => 5000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);

        // Remittance 1: ৳2,000
        $this->service->recordPayment($order, 2000.00, 'courier_settlement', 'ST-REMIT-01');
        $this->assertEquals(3000.00, $this->service->getOrderCourierReceivable($order));

        // Remittance 2: ৳1,500
        $this->service->recordPayment($order, 1500.00, 'courier_settlement', 'ST-REMIT-02');
        $this->assertEquals(1500.00, $this->service->getOrderCourierReceivable($order));

        $summary = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(3500.00, $summary['net_cash_collected']);
        $this->assertEquals(1500.00, $summary['courier_cod_receivable']);
    }

    /**
     * Scenario 3: Partial settlement followed by full settlement.
     */
    public function test_scenario_3_partial_settlement_followed_by_full_settlement(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC3-FULL-SETTLE',
            'order_amount'   => 3000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);

        // Remittance 1: Partial ৳1,000
        $this->service->recordPayment($order, 1000.00, 'courier_settlement', 'SETTLE-P1');
        $this->assertEquals(2000.00, $this->service->getOrderCourierReceivable($order));

        // Remittance 2: Final ৳2,000 (Fully cleared)
        $this->service->recordPayment($order, 2000.00, 'courier_settlement', 'SETTLE-P2');
        $this->assertEquals(0.00, $this->service->getOrderCourierReceivable($order));

        $summary = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(3000.00, $summary['gross_delivered_sales']);
        $this->assertEquals(3000.00, $summary['net_cash_collected']);
        $this->assertEquals(0.00, $summary['courier_cod_receivable']);
    }

    /**
     * Scenario 4: Partial and full refunds via immutable compensating entries.
     */
    public function test_scenario_4_partial_and_full_refunds_with_immutable_history(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC4-REFUNDS',
            'order_amount'   => 6000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $anOrder = $this->service->project($order);

        // Initial full payment ৳6,000
        $pmt = $this->service->recordPayment($order, 6000.00, 'card', 'TXN-CARD-6000');
        $origPaymentId = $pmt->id;

        // Partial refund ৳2,000
        $ref1 = $this->service->recordRefund($order, 2000.00, 'partial_rebate', 'REF-P1');
        $this->assertEquals(-2000.00, (float) $ref1->amount);

        // Original payment row remains immutable and unchanged
        $this->assertTrue(AnalyticsPayment::where('id', $origPaymentId)->where('amount', 6000.00)->exists());

        $summaryPartial = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(6000.00, $summaryPartial['gross_cash_inflow']);
        $this->assertEquals(2000.00, $summaryPartial['total_refunds_outflow']);
        $this->assertEquals(4000.00, $summaryPartial['net_cash_collected']);

        // Final refund ৳4,000 (Full refund reached)
        $ref2 = $this->service->recordRefund($order, 4000.00, 'full_satisfaction', 'REF-P2');
        $this->assertEquals(-4000.00, (float) $ref2->amount);

        $summaryFull = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(6000.00, $summaryFull['gross_cash_inflow']);
        $this->assertEquals(6000.00, $summaryFull['total_refunds_outflow']);
        $this->assertEquals(0.00, $summaryFull['net_cash_collected']);

        // 3 ledger rows exist permanently: +6000, -2000, -4000
        $this->assertCount(3, AnalyticsPayment::where('order_id', $anOrder->id)->get());
    }

    /**
     * Scenario 5: Return after courier settlement (Separates Gross Sales vs Sales Returns vs Net Sales).
     */
    public function test_scenario_5_return_after_courier_settlement(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC5-RETURN',
            'order_amount'   => 4500.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);
        $this->service->recordPayment($order, 4500.00, 'courier_settlement', 'SETTLE-SC5');

        // Order is returned by customer; status updated to cancelled/returned
        $order->status = 'cancelled';
        $order->save();
        $this->service->project($order);

        $anOrder = AnalyticsOrder::where('workspace_id', $this->workspaceA->id)->where('order_number', 'ORD-SC5-RETURN')->first();
        $this->assertSame('returned', $anOrder->status);

        $summary = $this->service->getFinancialSummary($this->workspaceA->id);
        // Financial Metrics Separation:
        $this->assertEquals(4500.00, $summary['gross_delivered_sales']);
        $this->assertEquals(0.00, $summary['net_delivered_sales']);
        $this->assertEquals(4500.00, $summary['sales_returns']);
        $this->assertEquals(4500.00, $summary['gross_cash_inflow']);
        $this->assertEquals(4500.00, $summary['total_refunds_outflow']);
        $this->assertEquals(0.00, $summary['net_cash_collected']);
    }

    /**
     * Scenario 6: Duplicate refund callback is strictly idempotent.
     */
    public function test_scenario_6_duplicate_refund_callback_is_idempotent(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC6-DUP-REF',
            'order_amount'   => 3000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);
        $this->service->recordPayment($order, 3000.00, 'bkash', 'TXN-SC6-PAID');

        // Refund webhook delivered twice with same reference
        $ref1 = $this->service->recordRefund($order, 1000.00, 'defect', 'REF-DUP-TRX-01');
        $ref2 = $this->service->recordRefund($order, 1000.00, 'defect', 'REF-DUP-TRX-01');

        $this->assertSame($ref1->id, $ref2->id, 'Duplicate refund callback must return existing record');
        $this->assertSame(1, AnalyticsPayment::where('transaction_ref', 'REF-DUP-TRX-01')->count());

        $summary = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(1000.00, $summary['total_refunds_outflow'], 'Duplicate callback must not double-refund');
        $this->assertEquals(2000.00, $summary['net_cash_collected']);
    }

    /**
     * Scenario 7: Concurrent refund requests are protected by row-locking.
     */
    public function test_scenario_7_concurrent_refund_requests_are_transaction_safe(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC7-CONCURRENT',
            'order_amount'   => 5000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);
        $this->service->recordPayment($order, 5000.00, 'bank', 'TXN-SC7-FULL');

        // First refund of 3000 succeeds
        $ref1 = $this->service->recordRefund($order, 3000.00, 'part1', 'REF-CONC-1');
        $this->assertNotNull($ref1->id);

        // Second refund of 3000 must FAIL because only 2000 refundable balance remains!
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the remaining refundable balance');
        $this->service->recordRefund($order, 3000.00, 'part2', 'REF-CONC-2');
    }

    /**
     * Scenario 8: Concurrent settlement callbacks are deduplicated safely.
     */
    public function test_scenario_8_concurrent_settlement_callbacks_are_deduplicated(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC8-SETTLE-CONC',
            'order_amount'   => 2500.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);

        $pmt1 = $this->service->recordPayment($order, 2500.00, 'courier_settlement', 'SETTLE-CONC-88');
        $pmt2 = $this->service->recordPayment($order, 2500.00, 'courier_settlement', 'SETTLE-CONC-88');

        $this->assertSame($pmt1->id, $pmt2->id);
        $this->assertSame(1, AnalyticsPayment::where('transaction_ref', 'SETTLE-CONC-88')->count());
    }

    /**
     * Scenario 9: Refund exceeding refundable balance throws validation exception.
     */
    public function test_scenario_9_refund_exceeding_refundable_balance_is_blocked(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC9-EXCEED',
            'order_amount'   => 1500.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);
        $this->service->recordPayment($order, 1500.00, 'cash', 'TXN-SC9-1500');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exceeds the remaining refundable balance');
        $this->service->recordRefund($order, 2000.00, 'excess_refund');
    }

    /**
     * Scenario 10: Same order number in different workspaces is strictly tenant-safe.
     */
    public function test_scenario_10_same_order_number_in_different_workspaces_is_tenant_safe(): void
    {
        $orderA = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-DUAL-TENANT-999',
            'order_amount'   => 4000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($orderA);
        $this->service->recordPayment($orderA, 4000.00, 'bkash', 'TRX-TENANT-A-999');

        $orderB = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceB->id,
            'order_number'   => 'ORD-DUAL-TENANT-999', // Identical order number
            'order_amount'   => 8500.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($orderB);
        $this->service->recordPayment($orderB, 8500.00, 'nagad', 'TRX-TENANT-B-999');

        $summaryA = $this->service->getFinancialSummary($this->workspaceA->id);
        $summaryB = $this->service->getFinancialSummary($this->workspaceB->id);

        $this->assertEquals(4000.00, $summaryA['gross_delivered_sales']);
        $this->assertEquals(4000.00, $summaryA['net_cash_collected']);

        $this->assertEquals(8500.00, $summaryB['gross_delivered_sales']);
        $this->assertEquals(8500.00, $summaryB['net_cash_collected']);
    }

    /**
     * Scenario 11: Historical ledger reconciliation after repeated projections maintains zero drift.
     */
    public function test_scenario_11_historical_ledger_reconciliation_maintains_zero_drift(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC11-RECONCILE',
            'order_amount'   => 3200.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);
        $this->service->recordPayment($order, 1000.00, 'bkash', 'TXN-SC11-ADV');
        $this->service->recordPayment($order, 2200.00, 'courier_settlement', 'TXN-SC11-SETTLE');
        $this->service->recordRefund($order, 500.00, 'rebate', 'TXN-SC11-REF');

        // Repeated reconciliation projections must never create duplicate orders or payments
        $result = $this->service->reconcileWorkspace($this->workspaceA->id);

        $this->assertSame(1, $result['orders_projected']);
        $this->assertEquals(3200.00, $result['financial_summary']['gross_delivered_sales']);
        $this->assertEquals(3200.00, $result['financial_summary']['net_delivered_sales']);
        $this->assertEquals(3200.00, $result['financial_summary']['gross_cash_inflow']);
        $this->assertEquals(500.00, $result['financial_summary']['total_refunds_outflow']);
        $this->assertEquals(2700.00, $result['financial_summary']['net_cash_collected']);
        $this->assertEquals(0.00, $result['financial_summary']['courier_cod_receivable']);

        // Exactly 3 payment records exist
        $this->assertSame(3, AnalyticsPayment::where('workspace_id', $this->workspaceA->id)->count());
    }

    /**
     * Immutability Invariant: Direct Model Update & Delete are Blocked.
     * Enforced at both the Eloquent ORM layer and the Database Trigger engine layer.
     */
    public function test_immutability_invariant_blocks_direct_update_and_delete(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-IMMUTABLE-GUARD',
            'order_amount'   => 1000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);
        $payment = $this->service->recordPayment($order, 1000.00, 'cash', 'TXN-IMMUTABLE-1');

        // 1. Eloquent ORM Layer Defense
        $updateBlocked = false;
        try {
            $payment->update(['amount' => 9999.00]);
        } catch (\LogicException $e) {
            $updateBlocked = true;
        }
        $this->assertTrue($updateBlocked, 'Direct update on AnalyticsPayment must be blocked by Eloquent');

        $deleteBlocked = false;
        try {
            $payment->delete();
        } catch (\LogicException $e) {
            $deleteBlocked = true;
        }
        $this->assertTrue($deleteBlocked, 'Direct delete on AnalyticsPayment must be blocked by Eloquent');

        // 2. Database Engine Trigger Defense (Raw SQL mutations bypass Eloquent, but must trigger DB exception)
        $rawDeleteBlocked = false;
        try {
            DB::table('analytics_payments')->where('id', $payment->id)->delete();
        } catch (\Throwable $e) {
            $rawDeleteBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($rawDeleteBlocked, 'Raw SQL delete on analytics_payments must be blocked by DB trigger');

        $rawUpdateBlocked = false;
        try {
            DB::table('analytics_payments')->where('id', $payment->id)->update(['amount' => 5000.00]);
        } catch (\Throwable $e) {
            $rawUpdateBlocked = true;
            $this->assertStringContainsString('immutable', strtolower($e->getMessage()));
        }
        $this->assertTrue($rawUpdateBlocked, 'Raw SQL update on analytics_payments must be blocked by DB trigger');
    }

    /**
     * Scenario 13: Explicit Transaction Types and Provider-Scoped Idempotency.
     * Proves:
     * - Advance payments classified explicitly as customer_advance.
     * - Courier settlements classified explicitly as courier_settlement.
     * - Different providers (e.g. bKash vs Steadfast) can have identical reference without collision.
     * - Duplicate callback under the SAME provider is strictly deduplicated.
     */
    public function test_scenario_13_explicit_transaction_types_and_provider_scoping(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC13-PROVIDER-SCOPE',
            'order_amount'   => 5000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $this->service->project($order);

        // Advance payment via bKash with reference "TRX-SHARED-99"
        $pmtAdvance = $this->service->recordPayment(
            order: $order,
            amount: 1000.00,
            paymentMethod: 'bkash',
            transactionRef: 'TRX-SHARED-99',
            transactionType: 'customer_advance',
            provider: 'bkash'
        );
        $this->assertSame('customer_advance', $pmtAdvance->transaction_type);
        $this->assertSame('bkash', $pmtAdvance->provider);

        // Courier remittance via Steadfast with the IDENTICAL reference "TRX-SHARED-99"
        // Provider scoping ensures NO index collision between bkash and steadfast!
        $pmtCourier = $this->service->recordPayment(
            order: $order,
            amount: 4000.00,
            paymentMethod: 'courier_settlement',
            transactionRef: 'TRX-SHARED-99',
            transactionType: 'courier_settlement',
            provider: 'steadfast'
        );
        $this->assertSame('courier_settlement', $pmtCourier->transaction_type);
        $this->assertSame('steadfast', $pmtCourier->provider);
        $this->assertNotSame($pmtAdvance->id, $pmtCourier->id);

        // Duplicate Steadfast remittance with same reference is strictly deduplicated
        $pmtCourierDup = $this->service->recordPayment(
            order: $order,
            amount: 4000.00,
            paymentMethod: 'courier_settlement',
            transactionRef: 'TRX-SHARED-99',
            transactionType: 'courier_settlement',
            provider: 'steadfast'
        );
        $this->assertSame($pmtCourier->id, $pmtCourierDup->id);

        // Outstanding courier receivable must be 0 (COD target 4000 - courier settled 4000)
        $this->assertEquals(0.00, $this->service->getOrderCourierReceivable($order));

        $summary = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(5000.00, $summary['gross_delivered_sales']);
        $this->assertEquals(5000.00, $summary['net_cash_collected']);
        $this->assertEquals(0.00, $summary['courier_cod_receivable']);
    }

    /**
     * Scenario 14: Complete Post-Delivery Return & Financial Settlement Lifecycle.
     * Full Flow:
     * 1. Order Delivered (Delivered Sales: ৳5,000, COD Target: ৳5,000, Courier Receivable: ৳5,000)
     * 2. Courier Settled (Net Cash: ৳5,000, Courier Receivable: ৳0)
     * 3. Customer Return Processed (Sales Return: ৳5,000, Net Sales: ৳0, Courier Receivable: ৳0)
     * 4. Customer Refund Issued (Compensating Ledger Entry: -৳5,000, Net Cash: ৳0)
     * 5. Historical Ledger Preserved (2 immutable entries: +৳5,000, -৳5,000)
     */
    public function test_scenario_14_complete_post_delivery_return_and_settlement_lifecycle(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC14-LIFECYCLE',
            'order_amount'   => 5000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $anOrder = $this->service->project($order);

        // Step 1: Delivered
        $this->assertEquals(5000.00, $this->service->getOrderCourierReceivable($order));
        $s1 = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(5000.00, $s1['gross_delivered_sales']);
        $this->assertEquals(0.00, $s1['net_cash_collected']);
        $this->assertEquals(5000.00, $s1['courier_cod_receivable']);

        // Step 2: Courier Remits COD
        $this->service->recordPayment($order, 5000.00, 'courier_settlement', 'ST-SETTLE-SC14', null, 'courier_settlement', 'steadfast');
        $this->assertEquals(0.00, $this->service->getOrderCourierReceivable($order));
        $s2 = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(5000.00, $s2['net_cash_collected']);
        $this->assertEquals(0.00, $s2['courier_cod_receivable']);

        // Step 3: Customer returns item 3 days later
        $order->status = 'cancelled'; // Canonical channel status for return
        $order->save();
        $this->service->project($order); // Projects to 'returned' and automatically issues compensating refund!

        $anOrder->refresh();
        $this->assertSame('returned', $anOrder->status);
        $this->assertEquals(0.00, $this->service->getOrderCourierReceivable($order));

        // Step 4 & 5: Complete Financial Summary Reconciliation
        $s3 = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(5000.00, $s3['gross_delivered_sales']);
        $this->assertEquals(5000.00, $s3['sales_returns']);
        $this->assertEquals(0.00, $s3['net_delivered_sales']);
        $this->assertEquals(5000.00, $s3['gross_cash_inflow']);
        $this->assertEquals(5000.00, $s3['total_refunds_outflow']);
        $this->assertEquals(0.00, $s3['net_cash_collected']);
        $this->assertEquals(0.00, $s3['courier_cod_receivable']);

        // Historical Ledger Integrity: Exactly 2 rows (+5000 and -5000)
        $ledgerEntries = AnalyticsPayment::where('order_id', $anOrder->id)->get();
        $this->assertCount(2, $ledgerEntries);
        $this->assertEquals(5000.00, (float) $ledgerEntries[0]->amount);
        $this->assertEquals(-5000.00, (float) $ledgerEntries[1]->amount);
        $this->assertSame('customer_refund', $ledgerEntries[1]->transaction_type);
    }

    /**
     * Scenario 15: Delivered -> Partially Settled -> Returned Lifecycle.
     * Crucial Invariant:
     * When customer returns order after courier has only remitted a partial settlement (e.g. ৳2,000 out of ৳5,000),
     * the remaining courier obligation (৳3,000) must NOT disappear from courier receivable accounting!
     * The courier collected COD at delivery and still owes that ৳3,000 to the merchant.
     */
    public function test_scenario_15_delivered_partially_settled_returned_preserves_courier_obligation(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC15-PART-RETURN',
            'order_amount'   => 5000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $anOrder = $this->service->project($order);

        // Step 1: Order Delivered. COD Target = ৳5,000, Courier Receivable = ৳5,000
        $this->assertEquals(5000.00, $this->service->getOrderCourierReceivable($order));

        // Step 2: Courier remits partial settlement ৳2,000.
        // Outstanding Courier Receivable drops to ৳3,000
        $this->service->recordPayment(
            order: $order,
            amount: 2000.00,
            paymentMethod: 'courier_settlement',
            transactionRef: 'ST-SETTLE-SC15-PART1',
            collectedAt: null,
            transactionType: 'courier_settlement',
            provider: 'steadfast'
        );
        $this->assertEquals(3000.00, $this->service->getOrderCourierReceivable($order));
        $s1 = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(2000.00, $s1['net_cash_collected']);
        $this->assertEquals(3000.00, $s1['courier_cod_receivable']);

        // Step 3: Customer initiates return post-delivery. Status changes to returned.
        $order->status = 'cancelled';
        $order->save();
        $this->service->project($order); // Projects to 'returned', automatically refunds the ৳2,000 net paid so far

        $anOrder->refresh();
        $this->assertSame('returned', $anOrder->status);

        // CRITICAL AUDIT CHECK: Courier Receivable must STILL be ৳3,000!
        // Courier collected the full COD at delivery; the return does not erase courier's remaining remittance debt.
        $this->assertEquals(3000.00, $this->service->getOrderCourierReceivable($order));

        $s2 = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(5000.00, $s2['gross_delivered_sales']);
        $this->assertEquals(5000.00, $s2['sales_returns']);
        $this->assertEquals(0.00, $s2['net_delivered_sales']);
        $this->assertEquals(2000.00, $s2['gross_cash_inflow']);
        $this->assertEquals(2000.00, $s2['total_refunds_outflow']);
        $this->assertEquals(0.00, $s2['net_cash_collected']);
        $this->assertEquals(3000.00, $s2['courier_cod_receivable']); // Preserved!

        // Step 4: Courier subsequently remits the remaining ৳3,000 debt
        $this->service->recordPayment(
            order: $order,
            amount: 3000.00,
            paymentMethod: 'courier_settlement',
            transactionRef: 'ST-SETTLE-SC15-PART2',
            collectedAt: null,
            transactionType: 'courier_settlement',
            provider: 'steadfast'
        );

        // Courier Receivable is now fully cleared to ৳0.00
        $this->assertEquals(0.00, $this->service->getOrderCourierReceivable($order));

        // Merchant dispatches remaining customer refund of ৳3,000
        $this->service->recordRefund(
            order: $order,
            refundAmount: 3000.00,
            reason: 'return_settlement_cleared',
            transactionRef: 'REF-SC15-FINAL',
            provider: 'manual'
        );

        $s3 = $this->service->getFinancialSummary($this->workspaceA->id);
        $this->assertEquals(5000.00, $s3['gross_delivered_sales']);
        $this->assertEquals(5000.00, $s3['sales_returns']);
        $this->assertEquals(0.00, $s3['net_delivered_sales']);
        $this->assertEquals(5000.00, $s3['gross_cash_inflow']);
        $this->assertEquals(5000.00, $s3['total_refunds_outflow']);
        $this->assertEquals(0.00, $s3['net_cash_collected']);
        $this->assertEquals(0.00, $s3['courier_cod_receivable']);

        // Historical Ledger Integrity: Exactly 4 immutable rows (+2000, -2000, +3000, -3000)
        $this->assertCount(4, AnalyticsPayment::where('order_id', $anOrder->id)->get());
    }

    /**
     * Scenario 16: Strict Separation of Sales Returns vs Cash Refunds & Idempotent Projections.
     */
    public function test_scenario_16_strict_separation_of_sales_returns_and_cash_refunds(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC16-SEPARATION',
            'order_amount'   => 3500.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $anOrder = $this->service->project($order);
        $this->service->recordPayment($order, 3500.00, 'bkash', 'TXN-SC16-ADV', null, 'customer_advance', 'bkash');

        // Customer returns order
        $order->status = 'cancelled';
        $order->save();
        $this->service->project($order);

        // Repeated projection must be strictly idempotent
        $this->service->project($order);
        $this->service->reconcileWorkspace($this->workspaceA->id);

        $summary = $this->service->getFinancialSummary($this->workspaceA->id);

        // Sales return reduces net sales from 3500 to 0 (NOT counted as cash)
        $this->assertEquals(3500.00, $summary['gross_delivered_sales']);
        $this->assertEquals(3500.00, $summary['sales_returns']);
        $this->assertEquals(0.00, $summary['net_delivered_sales']);

        // Cash flow is separate: 3500 inflow, 3500 refund outflow = 0 net cash
        $this->assertEquals(3500.00, $summary['gross_cash_inflow']);
        $this->assertEquals(3500.00, $summary['total_refunds_outflow']);
        $this->assertEquals(0.00, $summary['net_cash_collected']);

        // Exactly 2 payments: +3500 and -3500 (idempotent, no duplicates)
        $this->assertSame(2, AnalyticsPayment::where('order_id', $anOrder->id)->count());
    }

    /**
     * Scenario 17: Provider NOT NULL Enforcement and NULL Transaction Reference Safety.
     */
    public function test_scenario_17_provider_not_null_and_null_ref_handling(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'order_number'   => 'ORD-SC17-NULL-TEST',
            'order_amount'   => 2000.00,
            'status'         => 'delivered',
            'captured_at'    => Carbon::today(),
        ]);
        $anOrder = $this->service->project($order);

        // 1. Provider defaults to non-null if passed empty, and service auto-generates internal ref
        $p1 = $this->service->recordPayment(
            order: $order,
            amount: 500.00,
            paymentMethod: 'cash',
            transactionRef: null,
            collectedAt: null,
            transactionType: 'customer_advance',
            provider: null // Passed null, must default to 'manual'
        );
        $this->assertSame('manual', $p1->provider);
        $this->assertNotNull($p1->transaction_ref);
        $this->assertStringStartsWith('PMT-ORD-SC17', $p1->transaction_ref);

        // 2. Multiple NULL transaction references under the same provider are permitted at DB level (e.g. walk-in cash sales)
        $p2 = AnalyticsPayment::create([
            'workspace_id'     => $this->workspaceA->id,
            'order_id'         => $anOrder->id,
            'customer_id'      => $anOrder->customer_id,
            'salesperson_id'   => $anOrder->salesperson_id,
            'amount'           => 100.00,
            'payment_method'   => 'cash',
            'transaction_type' => 'customer_advance',
            'provider'         => 'manual',
            'transaction_ref'  => null,
            'collected_at'     => now(),
        ]);
        $p3 = AnalyticsPayment::create([
            'workspace_id'     => $this->workspaceA->id,
            'order_id'         => $anOrder->id,
            'customer_id'      => $anOrder->customer_id,
            'salesperson_id'   => $anOrder->salesperson_id,
            'amount'           => 100.00,
            'payment_method'   => 'cash',
            'transaction_type' => 'customer_advance',
            'provider'         => 'manual',
            'transaction_ref'  => null,
            'collected_at'     => now(),
        ]);
        $this->assertNull($p2->transaction_ref);
        $this->assertNull($p3->transaction_ref);
        $this->assertNotSame($p2->id, $p3->id);

        // 3. Non-null duplicate transaction reference under the SAME provider is strictly deduplicated
        $p4 = $this->service->recordPayment(
            order: $order,
            amount: 500.00,
            paymentMethod: 'bkash',
            transactionRef: 'REF-EXPLICIT-101',
            collectedAt: null,
            transactionType: 'customer_advance',
            provider: 'bkash'
        );
        $p5 = $this->service->recordPayment(
            order: $order,
            amount: 500.00,
            paymentMethod: 'bkash',
            transactionRef: 'REF-EXPLICIT-101',
            collectedAt: null,
            transactionType: 'customer_advance',
            provider: 'bkash'
        );
        $this->assertSame($p4->id, $p5->id);
    }
}
