<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AgentContext;
use App\AI\Tools\SalesAnalyticsTool;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsPayment;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Analytics\OrderAnalyticsProjectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BIOrderLifecycleProjectionTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name'      => 'BI Projection Test Boutique',
            'slug'      => 'bi-proj-test-boutique',
            'currency'  => 'BDT',
            'is_active' => true,
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Test Owner',
            'email'        => 'owner@bi-proj.test',
        ]);
        $this->owner->assignRole('owner');
    }

    public function test_workspace_order_canonical_projection_through_full_delivery_and_cancellation_lifecycle(): void
    {
        $projectionService = app(OrderAnalyticsProjectionService::class);
        $today = Carbon::today()->toDateString();

        // 1. Initial State: Order Captured via Chat (Pending)
        $wsOrder = WorkspaceOrder::create([
            'workspace_id'     => $this->workspace->id,
            'order_number'     => 'ORD-PROJ-TEST-101',
            'channel'          => 'facebook',
            'customer_name'    => 'Sadia Rahman',
            'customer_phone'   => '01711002233',
            'delivery_address' => 'House 12, Road 4, Banani, Dhaka',
            'product_summary'  => 'Silk Jamdani Sari',
            'order_amount'     => 4500.00,
            'delivery_fee'     => 70.00,
            'status'           => 'pending',
            'captured_at'      => Carbon::today(),
        ]);

        $analyticsOrder = $projectionService->project($wsOrder);

        $this->assertSame('pending', $analyticsOrder->status);
        $this->assertEquals(4570.00, (float) $analyticsOrder->net_amount);
        $this->assertSame(0, AnalyticsPayment::where('order_id', $analyticsOrder->id)->count());

        // BI Query: Pending order must NOT be in completed revenue
        $tool = new SalesAnalyticsTool();
        $context = AgentContext::forUser(workspaceId: $this->workspace->id, user: $this->owner);
        $biReportPending = $tool->execute(['period' => 'today'], $context);
        $this->assertEquals(0.00, (float) ($biReportPending->data['total_revenue'] ?? 0.00));

        // 2. Courier Updates Status to 'delivered'
        $wsOrder->status = 'delivered';
        $wsOrder->courier_tracking_code = 'ST-TRK-9988';
        $wsOrder->save();

        $updatedAnalyticsOrder = $projectionService->project($wsOrder);

        $this->assertSame('completed', $updatedAnalyticsOrder->status);
        // COD Delivery fulfills the sale, but merchant cash is held by courier until settlement
        $summary = $projectionService->getFinancialSummary($this->workspace->id);
        $this->assertEquals(4570.00, $summary['delivered_revenue']);
        $this->assertEquals(0.00, $summary['net_cash_collected']);
        $this->assertEquals(4570.00, $summary['courier_cod_receivable']);

        // Courier settlement remittance received from Steadfast
        $payment = $projectionService->recordPayment($wsOrder, 4570.00, 'courier_settlement', 'ST-TRK-9988');
        $this->assertSame(1, AnalyticsPayment::where('order_id', $updatedAnalyticsOrder->id)->where('amount', '>', 0)->count());
        $this->assertEquals(4570.00, (float) $payment->amount);
        $this->assertSame('ST-TRK-9988', $payment->transaction_ref);

        // BI Query: Delivered order MUST be included in completed revenue
        $biReportDelivered = $tool->execute(['period' => 'today'], $context);
        $this->assertEquals(4570.00, (float) ($biReportDelivered->data['total_revenue'] ?? 0.00));
        $this->assertSame(1, (int) ($biReportDelivered->data['total_orders'] ?? 0));

        // 3. Idempotency Check: Calling project() again must NOT double count
        $projectionService->project($wsOrder);
        $this->assertSame(1, AnalyticsOrder::where('workspace_id', $this->workspace->id)->where('order_number', 'ORD-PROJ-TEST-101')->count());
        $this->assertSame(1, AnalyticsPayment::where('order_id', $updatedAnalyticsOrder->id)->where('amount', '>', 0)->count());

        // 4. Order Cancelled / Returned after payment
        $wsOrder->status = 'cancelled';
        $wsOrder->save();

        $cancelledAnalyticsOrder = $projectionService->project($wsOrder);

        $this->assertSame('returned', $cancelledAnalyticsOrder->status);
        // Accounting Rule: Historical payment is NEVER deleted; compensating refund entry is appended
        $this->assertSame(2, AnalyticsPayment::where('order_id', $cancelledAnalyticsOrder->id)->count(), 'Original payment + compensating refund must both be preserved');
        $summaryAfterCancel = $projectionService->getFinancialSummary($this->workspace->id);
        $this->assertEquals(0.00, $summaryAfterCancel['net_cash_collected'], 'Net cash collected must be 0 after compensating refund');
        $this->assertEquals(0.00, $summaryAfterCancel['delivered_revenue'], 'Delivered revenue must be 0 for cancelled order');

        // BI Query: Cancelled order must be excluded from completed revenue
        $biReportCancelled = $tool->execute(['period' => 'today'], $context);
        $this->assertEquals(0.00, (float) ($biReportCancelled->data['total_revenue'] ?? 0.00));
    }
}
