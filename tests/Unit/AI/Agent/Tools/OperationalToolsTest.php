<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Agent\Tools;

use App\Enums\DocumentLifecycleStatus;
use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Tools\CustomerInsightsTool;
use App\AI\Tools\InventoryAnalyticsTool;
use App\AI\Tools\OwnerCustomerSupportInsightsTool;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class OperationalToolsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $ownerUser;
    private User $staffUser;
    private AgentContext $ownerContext;
    private AgentContext $staffContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Operational WS', 'slug' => 'operational-ws']);

        $this->ownerUser = User::create([
            'name'         => 'Owner User',
            'email'        => 'owner.op@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);

        $this->staffUser = User::create([
            'name'         => 'Staff User',
            'email'        => 'staff.op@example.com',
            'password'     => bcrypt('secret'),
            'workspace_id' => $this->workspace->id,
        ]);

        $ownerScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->ownerUser->id,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins'],
        );
        $this->ownerContext = AgentContext::fromServerControlledContext($ownerScc, user: $this->ownerUser);

        $staffScc = new ServerControlledContext(
            workspaceId: $this->workspace->id,
            userId: $this->staffUser->id,
            userRole: 'member',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [],
        );
        $this->staffContext = AgentContext::fromServerControlledContext($staffScc, user: $this->staffUser);
    }

    public function test_inventory_analytics_fallback_when_no_sheet_ingested(): void
    {
        // Create 2 products in catalog without stock records
        AnalyticsProduct::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Product A',
            'sku'          => 'SKU-A',
            'unit_price'   => 50.00,
            'cost_price'   => 30.00,
            'is_active'    => true,
        ]);

        $tool = new InventoryAnalyticsTool();
        $this->assertSame('inventory_analytics', $tool->name());
        $this->assertTrue($tool->isReadOnly());

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request());
        });

        $this->assertInstanceOf(ToolResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('no_inventory_data_ingested', $result->data['status']);
        $this->assertSame(1, $result->data['catalog_sku_count']);
        $this->assertSame([], $result->data['items']);
    }

    public function test_inventory_analytics_with_structured_records_and_dlp(): void
    {
        $doc = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Warehouse Inventory Sheet',
            'original_filename' => 'inventory.xlsx',
            'file_path'         => '/tmp/fake.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'content_hash'      => 'hash123',
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
            'is_active'         => true,
        ]);

        // Item 1: In stock (50 units @ $20 cost = $1000 valuation)
        StructuredRecord::create([
            'workspace_id'          => $this->workspace->id,
            'document_id'           => $doc->id,
            'row_index'             => 1,
            'record_identifier'     => 'SKU-101',
            'canonical_entity_name' => 'Wireless Mouse',
            'canonical_category'    => 'Accessories',
            'canonical_quantity'    => 50,
            'canonical_amount'      => 20.00,
            'raw_values'            => ['SKU' => 'SKU-101', 'Stock' => 50, 'Cost' => 20],
        ]);

        // Item 2: Low stock (4 units @ $100 cost = $400 valuation)
        StructuredRecord::create([
            'workspace_id'          => $this->workspace->id,
            'document_id'           => $doc->id,
            'row_index'             => 2,
            'record_identifier'     => 'SKU-102',
            'canonical_entity_name' => 'Mechanical Keyboard',
            'canonical_category'    => 'Accessories',
            'canonical_quantity'    => 4,
            'canonical_amount'      => 100.00,
            'raw_values'            => ['SKU' => 'SKU-102', 'Stock' => 4, 'Cost' => 100],
        ]);

        // Item 3: Out of stock (0 units)
        StructuredRecord::create([
            'workspace_id'          => $this->workspace->id,
            'document_id'           => $doc->id,
            'row_index'             => 3,
            'record_identifier'     => 'SKU-103',
            'canonical_entity_name' => 'Gaming Headset',
            'canonical_category'    => 'Accessories',
            'canonical_quantity'    => 0,
            'canonical_amount'      => 80.00,
            'raw_values'            => ['SKU' => 'SKU-103', 'Stock' => 0, 'Cost' => 80],
        ]);

        $tool = new InventoryAnalyticsTool();

        // 1. Owner execution: sees total valuation & item unit costs
        $ownerResult = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request(['low_stock_threshold' => 10]));
        });

        $this->assertTrue($ownerResult->success);
        $this->assertSame(3, $ownerResult->data['total_skus']);
        $this->assertSame(54.0, (float) $ownerResult->data['total_stock_units']);
        $this->assertSame(1, $ownerResult->data['low_stock_count']);
        $this->assertSame(1, $ownerResult->data['out_of_stock_count']);
        $this->assertSame(1400.00, $ownerResult->data['total_inventory_cost']); // 50*20 + 4*100 = 1400

        // 2. Staff execution: valuation is scrubbed by DLP policy
        $staffResult = AgentContext::withContext($this->staffContext, function () use ($tool) {
            return $tool->handle(new Request(['low_stock_threshold' => 10]));
        });

        $this->assertTrue($staffResult->success);
        $this->assertSame(3, $staffResult->data['total_skus']);
        $this->assertSame(1, $staffResult->data['low_stock_count']);
        $this->assertArrayNotHasKey('total_inventory_cost', $staffResult->data);
        $this->assertArrayNotHasKey('unit_cost', $staffResult->data['items'][0]);
        $this->assertArrayNotHasKey('inventory_cost', $staffResult->data['items'][0]);
    }

    public function test_customer_insights_tool_cohorts_and_zero_pii(): void
    {
        $salesperson = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspace->id,
            'name'          => 'Seller Rep',
            'employee_code' => 'REP-01',
            'is_active'     => true,
        ]);

        // Create 2 customers with orders
        $cust1 = AnalyticsCustomer::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Secret VIP Customer',
            'phone'        => '01999999999',
            'email'        => 'vip@secret.com',
            'is_active'    => true,
        ]);

        $cust2 = AnalyticsCustomer::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'One-Time Customer',
            'phone'        => '01888888888',
            'email'        => 'regular@secret.com',
            'is_active'    => true,
        ]);

        // Cust 1 has 2 orders totaling $6000 (VIP)
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $cust1->id,
            'salesperson_id' => $salesperson->id,
            'order_number'   => 'ORD-VIP-1',
            'total_amount'   => 3000.00,
            'discount'       => 0.00,
            'net_amount'     => 3000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::today()->toDateString(),
        ]);
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $cust1->id,
            'salesperson_id' => $salesperson->id,
            'order_number'   => 'ORD-VIP-2',
            'total_amount'   => 3000.00,
            'discount'       => 0.00,
            'net_amount'     => 3000.00,
            'status'         => 'completed',
            'order_date'     => Carbon::today()->toDateString(),
        ]);

        // Cust 2 has 1 order totaling $500 (Entry)
        AnalyticsOrder::create([
            'workspace_id'   => $this->workspace->id,
            'customer_id'    => $cust2->id,
            'salesperson_id' => $salesperson->id,
            'order_number'   => 'ORD-ENT-1',
            'total_amount'   => 500.00,
            'discount'       => 0.00,
            'net_amount'     => 500.00,
            'status'         => 'completed',
            'order_date'     => Carbon::today()->toDateString(),
        ]);

        $tool = new CustomerInsightsTool();
        $this->assertSame('customer_insights', $tool->name());
        $this->assertTrue($tool->isReadOnly());

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request());
        });

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->data['total_registered_customers']);
        $this->assertSame(2, $result->data['ordering_customers_count']);
        $this->assertSame(1, $result->data['repeat_buyer_count']);
        $this->assertSame(0.5, $result->data['repeat_buyer_ratio']);
        $this->assertSame(6500.00, $result->data['total_revenue']);
        $this->assertSame(1, $result->data['tier_distribution']['vip']['count']);
        $this->assertSame(1, $result->data['tier_distribution']['entry']['count']);

        // Strict Zero PII Verification
        $serialized = json_encode($result->data);
        $this->assertStringNotContainsString('Secret VIP Customer', $serialized);
        $this->assertStringNotContainsString('01999999999', $serialized);
        $this->assertStringNotContainsString('vip@secret.com', $serialized);
    }

    public function test_owner_customer_support_insights_tool_metrics(): void
    {
        $channel = Channel::create(['name' => 'Web Chat', 'slug' => 'web-chat']);
        $channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Main Support Chat',
            'external_id'  => 'web_ext_1',
            'access_token' => 'token_secret',
            'is_active'    => true,
        ]);

        // 1. Open conversation with human handoff
        Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'cust_100',
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'metadata'           => ['handoff_to_human' => true, 'handoff_reason' => '3_uncertain_turns'],
        ]);

        // 2. Closed conversation without handoff
        Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'cust_101',
            'status'             => 'closed',
            'last_direction'     => 'outbound',
            'metadata'           => ['resolved' => true],
        ]);

        $tool = new OwnerCustomerSupportInsightsTool();
        $this->assertSame('owner_support_insights', $tool->name());
        $this->assertTrue($tool->isReadOnly());

        $result = AgentContext::withContext($this->ownerContext, function () use ($tool) {
            return $tool->handle(new Request());
        });

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->data['total_conversations']);
        $this->assertSame(1, $result->data['open_conversations']);
        $this->assertSame(1, $result->data['closed_conversations']);
        $this->assertSame(1, $result->data['handoff_count']);
        $this->assertSame(0.5, $result->data['handoff_rate']);
        $this->assertCount(1, $result->data['channel_distribution']);
        $this->assertSame('Main Support Chat', $result->data['channel_distribution'][0]['channel']);
        $this->assertSame(2, $result->data['channel_distribution'][0]['count']);
    }
}
