<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Export\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConversationExportTenantTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace1;
    private Workspace $workspace2;
    private User $userWs1;
    private User $userWs2;
    private User $superAdmin;
    private Channel $channel;
    private ChannelAccount $accountWs1;
    private ChannelAccount $accountWs2;
    private Conversation $conversationWs1;
    private Conversation $conversationWs2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace1 = Workspace::create(['name' => 'Tenant 1', 'slug' => 'tenant-1']);
        $this->workspace2 = Workspace::create(['name' => 'Tenant 2', 'slug' => 'tenant-2']);

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->userWs1 = User::factory()->create([
            'name'         => 'User Tenant 1',
            'email'        => 'user_t1@example.com',
            'workspace_id' => $this->workspace1->id,
        ]);
        $this->userWs1->assignRole(RoleEnum::ADMIN->value);

        $this->userWs2 = User::factory()->create([
            'name'         => 'User Tenant 2',
            'email'        => 'user_t2@example.com',
            'workspace_id' => $this->workspace2->id,
        ]);
        $this->userWs2->assignRole(RoleEnum::ADMIN->value);

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

        $this->accountWs1 = ChannelAccount::create([
            'channel_id'   => $this->channel->id,
            'workspace_id' => $this->workspace1->id,
            'external_id'  => 'acc_ws1',
            'name'         => 'WS1 Account',
            'access_token' => 'tok1',
            'is_active'    => true,
        ]);

        $this->accountWs2 = ChannelAccount::create([
            'channel_id'   => $this->channel->id,
            'workspace_id' => $this->workspace2->id,
            'external_id'  => 'acc_ws2',
            'name'         => 'WS2 Account',
            'access_token' => 'tok2',
            'is_active'    => true,
        ]);

        $this->conversationWs1 = Conversation::create([
            'channel_account_id' => $this->accountWs1->id,
            'external_user_id'   => 'user_ws1_conv',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        Message::create([
            'conversation_id' => $this->conversationWs1->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Hello from WS1',
        ]);

        $this->conversationWs2 = Conversation::create([
            'channel_account_id' => $this->accountWs2->id,
            'external_user_id'   => 'user_ws2_conv',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        Message::create([
            'conversation_id' => $this->conversationWs2->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Hello from WS2',
        ]);
    }

    public function test_same_workspace_user_is_allowed_to_export_conversation(): void
    {
        $response = $this->actingAs($this->userWs1)
            ->get("/dashboard/export/conversation/{$this->conversationWs1->id}/csv");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_cross_workspace_export_is_denied_with_403_before_generating_export(): void
    {
        // Spy on ExportService to prove no export is generated or streamed
        $mockExportService = $this->createMock(ExportService::class);
        $mockExportService->expects($this->never())->method('exportConversation');
        $this->app->instance(ExportService::class, $mockExportService);

        // Tenant 1 user attempts to export Tenant 2 conversation
        $response = $this->actingAs($this->userWs1)
            ->get("/dashboard/export/conversation/{$this->conversationWs2->id}/csv");

        $response->assertForbidden();
    }

    public function test_conversation_missing_channel_account_fails_closed_with_404(): void
    {
        $mockExportService = $this->createMock(ExportService::class);
        $mockExportService->expects($this->never())->method('exportConversation');

        $controller = new \App\Http\Controllers\ExportController($mockExportService);

        $orphanedConv = new Conversation(['id' => 999]);
        $orphanedConv->setRelation('channelAccount', null);

        $this->actingAs($this->userWs1);

        try {
            $controller->exportConversation(request(), $orphanedConv, 'csv');
            $this->fail('Expected HttpException 404 was not thrown.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
            $this->assertStringContainsString('Conversation has no valid workspace association', $e->getMessage());
        }
    }

    public function test_superadmin_can_export_any_valid_workspace_conversation(): void
    {
        // SuperAdmin (has workspace 1) exports conversation from Workspace 2
        $response = $this->actingAs($this->superAdmin)
            ->get("/dashboard/export/conversation/{$this->conversationWs2->id}/csv");

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }
}
