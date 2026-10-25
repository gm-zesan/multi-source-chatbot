<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permissions\ConversationPermission;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConversationControllerTenantTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace1;
    private Workspace $workspace2;
    private User $adminWs1;
    private User $superAdmin;
    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace1 = Workspace::create(['name' => 'Tenant 1', 'slug' => 'tenant-1']);
        $this->workspace2 = Workspace::create(['name' => 'Tenant 2', 'slug' => 'tenant-2']);

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $permUpdate = Permission::firstOrCreate(
            ['name' => ConversationPermission::UPDATE->value, 'guard_name' => 'web'],
            ['display_name' => 'Update Conversation', 'module' => 'conversation']
        );
        $permView = Permission::firstOrCreate(
            ['name' => ConversationPermission::VIEW->value, 'guard_name' => 'web'],
            ['display_name' => 'View Conversation', 'module' => 'conversation']
        );
        $adminRole->givePermissionTo([$permUpdate, $permView]);

        $this->adminWs1 = User::factory()->create([
            'name'         => 'Admin Tenant 1',
            'email'        => 'admin_t1@example.com',
            'workspace_id' => $this->workspace1->id,
        ]);
        $this->adminWs1->assignRole(RoleEnum::ADMIN->value);

        $this->superAdmin = User::factory()->create([
            'name'         => 'Super Admin',
            'email'        => 'super@example.com',
            'workspace_id' => $this->workspace1->id,
        ]);
        $this->superAdmin->assignRole(RoleEnum::SUPERADMIN->value);

        $this->channel = Channel::create([
            'name'      => 'Web Widget',
            'slug'      => 'web',
            'driver'    => 'web',
            'is_active' => true,
        ]);
    }

    public function test_ai_reply_fails_closed_when_conversation_lacks_valid_channel_account_workspace(): void
    {
        $conversation = new Conversation(['id' => 999]);
        $conversation->setRelation('channelAccount', null);

        $mockSupportService = $this->createMock(CustomerSupportService::class);
        $mockSupportService->expects($this->never())->method('handleQuery');

        $controller = new \App\Http\Controllers\ConversationController(
            $this->createMock(\App\Services\Chat\ConversationService::class),
            $this->createMock(\App\Services\FAQ\FAQSearch::class),
            $mockSupportService,
        );

        $this->actingAs($this->superAdmin);
        $response = $controller->aiReply($conversation);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertTrue(session()->has('error'));
        $this->assertStringContainsString('conversation has no valid workspace association', session('error'));
    }

    public function test_cross_tenant_controller_access_is_rejected_with_403(): void
    {
        // Conversation belonging to Workspace 2
        $accountWs2 = ChannelAccount::create([
            'channel_id'   => $this->channel->id,
            'workspace_id' => $this->workspace2->id,
            'external_id'  => 'acc_ws2',
            'name'         => 'WS2 Account',
            'access_token' => 'tok',
            'is_active'    => true,
        ]);

        $conversationWs2 = Conversation::create([
            'channel_account_id' => $accountWs2->id,
            'external_user_id'   => 'user_ws2',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $mockSupportService = $this->createMock(CustomerSupportService::class);
        $mockSupportService->expects($this->never())->method('handleQuery');
        $this->app->instance(CustomerSupportService::class, $mockSupportService);

        // Tenant 1 Admin attempts to trigger AI reply on Tenant 2 Conversation
        $response = $this->actingAs($this->adminWs1)
            ->post("/dashboard/conversations/{$conversationWs2->id}/ai-reply");

        $response->assertForbidden();
    }

    public function test_ai_reply_succeeds_and_passes_correct_workspace_id_when_authorized(): void
    {
        // Conversation belonging to Workspace 1
        $accountWs1 = ChannelAccount::create([
            'channel_id'   => $this->channel->id,
            'workspace_id' => $this->workspace1->id,
            'external_id'  => 'acc_ws1',
            'name'         => 'WS1 Account',
            'access_token' => 'tok',
            'is_active'    => true,
        ]);

        $conversationWs1 = Conversation::create([
            'channel_account_id' => $accountWs1->id,
            'external_user_id'   => 'user_ws1',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        Message::create([
            'conversation_id' => $conversationWs1->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Where is my order?',
        ]);

        $mockSupportService = $this->createMock(CustomerSupportService::class);
        $mockSupportService->expects($this->once())
            ->method('handleQuery')
            ->with(
                $this->identicalTo('Where is my order?'),
                $this->identicalTo((int) $this->workspace1->id),
                $this->callback(fn ($conv) => $conv->id === $conversationWs1->id),
            )
            ->willReturn([
                'reply'                  => 'Your order is in transit.',
                'route'                  => 'knowledge',
                'confidence'             => 1.0,
                'answered'               => true,
                'routing_telemetry'      => ['total_e2e_ms' => 45.0],
                'answerability_decision' => null,
            ]);

        $mockSupportService->expects($this->once())
            ->method('saveOutboundReply')
            ->willReturn(new Message());

        $this->app->instance(CustomerSupportService::class, $mockSupportService);

        $response = $this->actingAs($this->adminWs1)
            ->from('/dashboard/conversations')
            ->post("/dashboard/conversations/{$conversationWs1->id}/ai-reply");

        $response->assertRedirect('/dashboard/conversations');
        $response->assertSessionHas('success');
    }

    public function test_superadmin_uses_conversation_workspace_and_never_falls_back_to_workspace_1(): void
    {
        // Conversation belonging to Workspace 2
        $accountWs2 = ChannelAccount::create([
            'channel_id'   => $this->channel->id,
            'workspace_id' => $this->workspace2->id,
            'external_id'  => 'acc_ws2_super',
            'name'         => 'WS2 Super Account',
            'access_token' => 'tok',
            'is_active'    => true,
        ]);

        $conversationWs2 = Conversation::create([
            'channel_account_id' => $accountWs2->id,
            'external_user_id'   => 'user_ws2_super',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        Message::create([
            'conversation_id' => $conversationWs2->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Order info for Tenant 2',
        ]);

        $mockSupportService = $this->createMock(CustomerSupportService::class);
        // Assert that workspace 2 is explicitly passed, NEVER workspace 1
        $mockSupportService->expects($this->once())
            ->method('handleQuery')
            ->with(
                $this->identicalTo('Order info for Tenant 2'),
                $this->identicalTo((int) $this->workspace2->id),
                $this->callback(fn ($conv) => $conv->id === $conversationWs2->id),
            )
            ->willReturn([
                'reply'                  => 'Tenant 2 order details.',
                'route'                  => 'knowledge',
                'confidence'             => 1.0,
                'answered'               => true,
                'routing_telemetry'      => ['total_e2e_ms' => 50.0],
                'answerability_decision' => null,
            ]);

        $mockSupportService->expects($this->once())
            ->method('saveOutboundReply')
            ->willReturn(new Message());

        $this->app->instance(CustomerSupportService::class, $mockSupportService);

        // SuperAdmin (who has workspace_id = 1) triggers AI reply for Tenant 2 conversation
        $response = $this->actingAs($this->superAdmin)
            ->from('/dashboard/conversations')
            ->post("/dashboard/conversations/{$conversationWs2->id}/ai-reply");

        $response->assertRedirect('/dashboard/conversations');
        $response->assertSessionHas('success');
    }
}
