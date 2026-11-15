<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\DTOs\AgentContext;
use App\AI\Tools\CustomerOrderStatusTool;
use App\Enums\Permissions\ConversationPermission;
use App\Enums\RoleEnum;
use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerSupportLifecycleAndToolsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $agentA;
    private User $agentB;
    private ChannelAccount $channelAccountA;
    private Role $adminRole;
    private AgentContext $customerContextA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        foreach ([
            ConversationPermission::VIEW->value,
            ConversationPermission::UPDATE->value,
        ] as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm, 'guard_name' => 'web'],
                ['display_name' => ucwords(str_replace(['.', '_'], ' ', $perm)), 'module' => 'conversation']
            );
        }

        $this->adminRole->givePermissionTo([
            ConversationPermission::VIEW->value,
            ConversationPermission::UPDATE->value,
        ]);

        $this->workspaceA = Workspace::create(['name' => 'Support WS A', 'slug' => 'support-ws-a']);
        $this->workspaceB = Workspace::create(['name' => 'Support WS B', 'slug' => 'support-ws-b']);

        $this->agentA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'agentA@support.test',
        ]);
        $this->agentA->assignRole($this->adminRole);

        $this->agentB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'email'        => 'agentB@support.test',
        ]);
        $this->agentB->assignRole($this->adminRole);

        $channel = Channel::firstOrCreate(['name' => 'Facebook Messenger', 'slug' => 'facebook']);
        $this->channelAccountA = ChannelAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'channel_id'   => $channel->id,
            'name'         => 'Support Page A',
            'external_id'  => 'page-ext-01',
            'access_token' => 'token-abc',
            'is_active'    => true,
        ]);

        $customerScc = new ServerControlledContext(
            workspaceId: $this->workspaceA->id,
            userId: null,
            userRole: 'customer',
            channel: ServerControlledContext::CHANNEL_CUSTOMER_FACEBOOK,
            purpose: ServerControlledContext::PURPOSE_CUSTOMER_SUPPORT,
            grantedPermissions: [],
        );
        $this->customerContextA = AgentContext::fromServerControlledContext($customerScc, user: null);
    }

    public function test_customer_order_status_tool_returns_safe_fulfillment_information(): void
    {
        $customer = AnalyticsCustomer::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Jamal Customer',
            'phone'        => '01811223344',
            'is_active'    => true,
        ]);

        $product = AnalyticsProduct::create([
            'workspace_id' => $this->workspaceA->id,
            'name'         => 'Noise Cancelling Headphones',
            'sku'          => 'HEADPHONE-X1',
            'unit_price'   => 4500.00,
            'cost_price'   => 2800.00, // Sensitive internal cost
            'is_active'    => true,
        ]);

        $salesperson = AnalyticsSalesperson::create([
            'workspace_id'  => $this->workspaceA->id,
            'name'          => 'Internal Agent',
            'employee_code' => 'INT-01',
            'target_amount' => 50000.00,
            'is_active'     => true,
        ]);

        $order = AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceA->id,
            'customer_id'    => $customer->id,
            'salesperson_id' => $salesperson->id,
            'order_number'   => 'ORD-9001',
            'total_amount'   => 4500.00,
            'discount'       => 0.00,
            'net_amount'     => 4500.00,
            'status'         => 'shipped',
            'order_date'     => Carbon::yesterday()->toDateString(),
        ]);

        AnalyticsOrderItem::create([
            'order_id'   => $order->id,
            'product_id' => $product->id,
            'quantity'   => 1,
            'unit_price' => 4500.00,
            'subtotal'   => 4500.00,
        ]);

        $tool = new CustomerOrderStatusTool();
        $result = $tool->execute(['order_number' => 'ORD-9001'], $this->customerContextA);

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['found']);
        $this->assertSame('ORD-9001', $result->data['order_number']);
        $this->assertStringContainsString('Shipped', $result->data['status']);
        $this->assertSame(4500.0, $result->data['net_amount']);
        $this->assertCount(1, $result->data['items']);
        $this->assertSame('Noise Cancelling Headphones', $result->data['items'][0]['product_name']);

        // Sensitive fields MUST NOT be exposed
        $this->assertArrayNotHasKey('cost_price', $result->data['items'][0]);
        $this->assertArrayNotHasKey('salesperson_id', $result->data);
        $this->assertArrayNotHasKey('gross_profit', $result->data);
    }

    public function test_customer_order_status_tool_blocks_cross_workspace_lookups(): void
    {
        // Order belongs to Workspace B
        $orderB = AnalyticsOrder::create([
            'workspace_id'   => $this->workspaceB->id,
            'customer_id'    => AnalyticsCustomer::create(['workspace_id' => $this->workspaceB->id, 'name' => 'Foreign', 'is_active' => true])->id,
            'salesperson_id' => AnalyticsSalesperson::create(['workspace_id' => $this->workspaceB->id, 'name' => 'Foreign Rep', 'employee_code' => 'FOR-02', 'is_active' => true])->id,
            'order_number'   => 'ORD-SECRET-B',
            'total_amount'   => 9900.00,
            'discount'       => 0.00,
            'net_amount'     => 9900.00,
            'status'         => 'completed',
            'order_date'     => Carbon::today()->toDateString(),
        ]);

        $tool = new CustomerOrderStatusTool();
        // Query executed with Workspace A context
        $result = $tool->execute(['order_number' => 'ORD-SECRET-B'], $this->customerContextA);

        $this->assertTrue($result->success);
        $this->assertFalse($result->data['found']); // Must NOT find cross-tenant order
    }

    public function test_agent_can_assign_conversation_within_same_workspace(): void
    {
        $this->actingAs($this->agentA);

        $conversation = Conversation::create([
            'channel_account_id' => $this->channelAccountA->id,
            'external_user_id'   => 'ext-cust-101',
            'customer_name'      => 'Customer One',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $response = $this->post(route('conversations.assign', $conversation), [
            'assigned_to_user_id' => $this->agentA->id,
        ]);

        $response->assertRedirect();
        $conversation->refresh();
        $this->assertSame($this->agentA->id, $conversation->assigned_to_user_id);
    }

    public function test_agent_cannot_assign_conversation_to_user_from_different_workspace(): void
    {
        $this->actingAs($this->agentA);

        $conversation = Conversation::create([
            'channel_account_id' => $this->channelAccountA->id,
            'external_user_id'   => 'ext-cust-102',
            'customer_name'      => 'Customer Two',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // Attempt assigning to Agent B from Workspace B
        $response = $this->post(route('conversations.assign', $conversation), [
            'assigned_to_user_id' => $this->agentB->id,
        ]);

        $response->assertStatus(403);
        $conversation->refresh();
        $this->assertNull($conversation->assigned_to_user_id);
    }

    public function test_conversation_status_update_and_resolution_timestamp(): void
    {
        $this->actingAs($this->agentA);

        $conversation = Conversation::create([
            'channel_account_id' => $this->channelAccountA->id,
            'external_user_id'   => 'ext-cust-103',
            'customer_name'      => 'Customer Three',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $this->assertNull($conversation->resolved_at);

        // Mark as resolved
        $response = $this->post(route('conversations.update-status', $conversation), [
            'status' => 'resolved',
        ]);

        $response->assertRedirect();
        $conversation->refresh();
        $this->assertSame('resolved', $conversation->status);
        $this->assertNotNull($conversation->resolved_at);

        // Reopen conversation
        $this->post(route('conversations.update-status', $conversation), [
            'status' => 'open',
        ]);

        $conversation->refresh();
        $this->assertSame('open', $conversation->status);
        $this->assertNull($conversation->resolved_at);
    }

    public function test_conversations_index_filters_by_status_and_assignment(): void
    {
        $this->actingAs($this->agentA);

        $convOpen = Conversation::create([
            'channel_account_id'  => $this->channelAccountA->id,
            'external_user_id'    => 'cust-open',
            'customer_name'       => 'Open Customer',
            'status'              => 'open',
            'last_direction'      => 'inbound',
            'assigned_to_user_id' => $this->agentA->id,
        ]);

        $convResolved = Conversation::create([
            'channel_account_id'  => $this->channelAccountA->id,
            'external_user_id'    => 'cust-resolved',
            'customer_name'       => 'Resolved Customer',
            'status'              => 'resolved',
            'last_direction'      => 'inbound',
            'assigned_to_user_id' => null,
        ]);

        // Filter status=open
        $responseOpen = $this->get(route('conversations.index', ['status' => 'open']));
        $responseOpen->assertOk();
        $responseOpen->assertSee('Open Customer');
        $responseOpen->assertDontSee('Resolved Customer');

        // Filter assigned_to=me
        $responseMe = $this->get(route('conversations.index', ['assigned_to' => 'me']));
        $responseMe->assertOk();
        $responseMe->assertSee('Open Customer');
        $responseMe->assertDontSee('Resolved Customer');
    }

    public function test_agent_can_view_conversation_details_with_assignment_and_status_ui_controls(): void
    {
        $conversation = Conversation::create([
            'channel_account_id'  => $this->channelAccountA->id,
            'external_user_id'    => 'cust-ui-test',
            'customer_name'       => 'UI Customer',
            'status'              => 'open',
            'priority'            => 'high',
            'last_direction'      => 'inbound',
            'assigned_to_user_id' => $this->agentA->id,
        ]);

        $response = $this->actingAs($this->agentA)->get(route('conversations.show', $conversation));
        $response->assertOk();
        $response->assertSee('UI Customer');
        $response->assertSee('assigned_to_user_id');
        $response->assertSee('Open');
        $response->assertSee('High');
        $response->assertSee($this->agentA->name);
    }
}
