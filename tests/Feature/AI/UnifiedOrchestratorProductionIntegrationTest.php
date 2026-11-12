<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\DTOs\ToolCall;
use App\AI\Decision\UnifiedDecisionEngine;
use App\AI\Decision\UnifiedOrchestrator;
use App\AI\Orchestration\CustomerSupportService;
use App\AI\Routing\RouteType;
use App\Enums\FaqLifecycleStatus;
use App\Enums\Permissions\ConversationPermission;
use App\Enums\RoleEnum;
use App\Events\IncomingMessageReceived;
use App\Listeners\RunFAQEngineListener;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Production Integration Test Suite for Unified Decision Engine & Orchestrator.
 *
 * Verifies end-to-end integration across all 3 primary production entry points:
 * 1. Entry Point A: Chat Simulator (POST /dashboard/simulator/send)
 * 2. Entry Point B: Webhook Engine Listener (RunFAQEngineListener)
 * 3. Entry Point C: Dashboard AI Reply (POST /dashboard/conversations/{conversation}/ai-reply)
 * 4. Resolves product stock vs inventory analytics collision
 * 5. Enforces fail-closed staff RBAC in simulator
 */
class UnifiedOrchestratorProductionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private User $staff;
    private Channel $simulatorChannel;
    private Channel $whatsappChannel;
    private ChannelAccount $simulatorAccount;
    private ChannelAccount $whatsappAccount;
    private Conversation $simulatorConversation;
    private Conversation $whatsappConversation;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & permissions
        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $permUpdate = Permission::firstOrCreate(
            ['name' => ConversationPermission::UPDATE->value, 'guard_name' => 'web'],
            ['display_name' => 'Update Conversation', 'module' => 'conversation']
        );
        $permView = Permission::firstOrCreate(
            ['name' => ConversationPermission::VIEW->value, 'guard_name' => 'web'],
            ['display_name' => 'View Conversation', 'module' => 'conversation']
        );
        $adminRole->givePermissionTo([$permUpdate, $permView]);
        $ownerRole->givePermissionTo([$permUpdate, $permView]);

        $this->workspace = Workspace::create([
            'name' => 'Unified Production Store',
            'slug' => 'unified-store',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'name' => 'Store Owner',
            'email' => 'owner@unified.store',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->owner->assignRole('owner');

        $this->staff = User::factory()->create([
            'name' => 'Store Staff',
            'email' => 'staff@unified.store',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->staff->assignRole('staff');

        // Simulator Channel & Account
        $this->simulatorChannel = Channel::create(['name' => 'Simulator', 'slug' => 'simulator', 'driver' => 'web']);
        $this->simulatorAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->simulatorChannel->id,
            'name'         => 'Sim Account',
            'external_id'  => 'sim_prod_001',
            'access_token' => 'tok_sim',
            'is_active'    => true,
        ]);

        // WhatsApp Customer Channel & Account
        $this->whatsappChannel = Channel::create(['name' => 'WhatsApp', 'slug' => 'whatsapp', 'driver' => 'whatsapp']);
        $this->whatsappAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->whatsappChannel->id,
            'name'         => 'WhatsApp Account',
            'external_id'  => 'wa_prod_001',
            'access_token' => 'tok_wa',
            'is_active'    => true,
        ]);

        $this->simulatorConversation = Conversation::create([
            'channel_account_id' => $this->simulatorAccount->id,
            'external_user_id'   => 'sim_owner_001',
            'customer_name'      => 'Store Owner',
            'last_direction'     => 'inbound',
            'metadata'           => ['is_simulator' => true],
        ]);

        $this->whatsappConversation = Conversation::create([
            'channel_account_id' => $this->whatsappAccount->id,
            'external_user_id'   => 'cust_wa_999',
            'customer_name'      => 'Inbound Customer',
            'last_direction'     => 'inbound',
            'metadata'           => ['is_customer' => true],
        ]);
    }

    /**
     * Test 1: Simulator send (POST /dashboard/simulator/send) executes UnifiedOrchestrator,
     * routes to sales_analytics, and never invokes legacy router.
     */
    public function test_1_simulator_send_executes_unified_orchestrator_without_legacy_router(): void
    {
        $response = $this->actingAs($this->owner)->postJson('/dashboard/simulator/send', [
            'message' => 'Total sale koto?',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'route'   => 'analytics',
                'answered'=> true,
            ]);

        $this->assertSame('ANALYTICS', $response->json('decision_trace.route'));
        $this->assertArrayHasKey('pipeline_diagnostics', $response->json());
    }

    /**
     * Test 2: Webhook listener (RunFAQEngineListener) executes UnifiedOrchestrator,
     * applying customer security barrier for workforce/financial queries.
     */
    public function test_2_webhook_listener_executes_orchestrator_and_blocks_customer_workforce_query(): void
    {
        $inboundMessage = Message::create([
            'conversation_id' => $this->whatsappConversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'কর্মচারীদের বেতন কত?',
        ]);

        $event = new IncomingMessageReceived(
            account: $this->whatsappAccount,
            conversation: $this->whatsappConversation,
            message: $inboundMessage,
            rawPayload: ['text' => 'কর্মচারীদের বেতন কত?']
        );

        $listener = app(RunFAQEngineListener::class);
        $listener->handle($event);

        // Verify outbound message was created and contains the security barrier denial message
        $outbound = Message::where('conversation_id', $this->whatsappConversation->id)
            ->where('direction', 'outbound')
            ->latest('id')
            ->first();

        $this->assertNotNull($outbound);
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $outbound->body);
    }

    /**
     * Test 3: Dashboard AI reply (ConversationController::aiReply) executes UnifiedOrchestrator
     * with server-resolved workspace.
     */
    public function test_3_conversation_controller_ai_reply_executes_unified_orchestrator(): void
    {
        // Add active FAQ for knowledge answer
        FAQ::create([
            'workspace_id'     => $this->workspace->id,
            'question'         => 'What is your shipping policy?',
            'answer'           => 'We deliver across Bangladesh within 48 hours.',
            'lifecycle_status' => FaqLifecycleStatus::ACTIVE,
            'is_active'        => true,
        ]);

        $conv = Conversation::create([
            'channel_account_id' => $this->simulatorAccount->id,
            'external_user_id'   => 'user_sim_test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'What is your shipping policy?',
        ]);

        $response = $this->actingAs($this->owner)
            ->from('/dashboard/conversations')
            ->post("/dashboard/conversations/{$conv->id}/ai-reply");

        $response->assertRedirect('/dashboard/conversations');
        $response->assertSessionHas('success', 'AI response generated successfully.');

        // Outbound reply must be saved in database
        $savedOutbound = Message::where('conversation_id', $conv->id)
            ->where('direction', 'outbound')
            ->first();

        $this->assertNotNull($savedOutbound);
        $this->assertNotEmpty($savedOutbound->body);
    }

    /**
     * Test 4: Product stock inquiry ("Laptop Pro কি stock e ache?") routes to knowledge
     * without static keyword collision with analytics.
     */
    public function test_4_product_stock_query_routes_to_knowledge_without_analytics_collision(): void
    {
        FAQ::create([
            'workspace_id'     => $this->workspace->id,
            'question'         => 'Laptop Pro stock e ache?',
            'answer'           => 'Yes, Laptop Pro is in stock with 15 units available.',
            'lifecycle_status' => FaqLifecycleStatus::ACTIVE,
            'is_active'        => true,
        ]);

        $response = $this->actingAs($this->owner)->postJson('/dashboard/simulator/send', [
            'message' => 'Laptop Pro কি stock e ache?',
        ]);

        $response->assertOk();
        $route = $response->json('route');

        // MUST route to knowledge, NOT analytics!
        $this->assertSame('knowledge', $route);
        $this->assertNotSame('analytics', $route);
    }

    /**
     * Test 5: Staff user in simulator without analytics permission is strictly blocked (Fail-Closed RBAC).
     */
    public function test_5_staff_user_in_simulator_attempting_analytics_is_strictly_blocked(): void
    {
        $response = $this->actingAs($this->staff)->postJson('/dashboard/simulator/send', [
            'message' => 'Total sale koto?',
        ]);

        $response->assertOk();
        $this->assertSame('analytics_denied', $response->json('route'));
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $response->json('reply'));
    }

    /**
     * Test 6: Proves the new orchestration path is invoked even when CustomerSupportService is mocked.
     * Ensures production code never branches on whether a dependency is a mock.
     */
    public function test_6_orchestrator_is_invoked_even_when_customer_support_service_is_mocked(): void
    {
        // Mock CustomerSupportService and assert handleQuery is NEVER called
        $mockCss = Mockery::mock(CustomerSupportService::class);
        $mockCss->shouldReceive('handleQuery')->never();
        $mockCss->shouldReceive('saveOutboundReply')
            ->once()
            ->andReturn(new Message());
        $this->app->instance(CustomerSupportService::class, $mockCss);

        // Mock UnifiedOrchestrator and assert process() IS invoked
        $mockOrchestrator = Mockery::mock(UnifiedOrchestrator::class);
        $mockOrchestrator->shouldReceive('process')
            ->once()
            ->with(
                'Where is my order?',
                (int) $this->workspace->id,
                Mockery::type(User::class),
                Mockery::type(Conversation::class),
                'simulator',
                false
            )
            ->andReturn([
                'reply'                  => 'Your order is on the way.',
                'route'                  => 'knowledge',
                'confidence'             => 1.0,
                'answered'               => true,
                'routing_telemetry'      => ['total_e2e_ms' => 30.0],
                'answerability_decision' => null,
            ]);
        $this->app->instance(UnifiedOrchestrator::class, $mockOrchestrator);

        $conv = Conversation::create([
            'channel_account_id' => $this->simulatorAccount->id,
            'external_user_id'   => 'user_sim_test_6',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        Message::create([
            'conversation_id' => $conv->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Where is my order?',
        ]);

        $response = $this->actingAs($this->owner)
            ->from('/dashboard/conversations')
            ->post("/dashboard/conversations/{$conv->id}/ai-reply");

        $response->assertRedirect('/dashboard/conversations');
        $response->assertSessionHas('success', 'AI response generated successfully.');
    }
}
