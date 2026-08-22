<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\CustomerSupportAgent;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::create(['name' => 'Main Workspace', 'slug' => 'main']);
        $channel = Channel::create(['slug' => 'messenger', 'name' => 'Facebook Messenger', 'is_active' => true]);
        
        $this->account = ChannelAccount::create([
            'channel_id' => $channel->id,
            'workspace_id' => $workspace->id,
            'name' => 'Facebook Main',
            'external_id' => 'fb_page_001',
            'access_token' => 'page_access_token',
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id' => 'cust_user_456',
            'customer_name' => 'Jane Smith',
            'status' => 'open',
            'last_direction' => 'inbound',
            'last_message' => 'What are your store hours?',
            'last_message_at' => now(),
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'What are your store hours?',
            'status' => 'received',
        ]);

        // Setup admin user with required permissions
        Permission::firstOrCreate(
            ['name' => 'view conversation', 'guard_name' => 'web'],
            ['display_name' => 'View Conversation', 'module' => 'conversations']
        );
        Permission::firstOrCreate(
            ['name' => 'update conversation', 'guard_name' => 'web'],
            ['display_name' => 'Update Conversation', 'module' => 'conversations']
        );

        $role = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $role->givePermissionTo(['view conversation', 'update conversation']);

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'workspace_id' => $workspace->id,
        ]);
        $this->admin->assignRole('superadmin');
    }

    public function test_admin_can_view_conversations_index_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard/conversations');

        $response->assertOk()
            ->assertViewIs('admin.conversations.index')
            ->assertSee('Jane Smith')
            ->assertSee('What are your store hours?');
    }

    public function test_admin_can_view_conversation_show_thread(): void
    {
        $response = $this->actingAs($this->admin)->get("/dashboard/conversations/{$this->conversation->id}");

        $response->assertOk()
            ->assertViewIs('admin.conversations.show')
            ->assertSee('Jane Smith')
            ->assertSee('What are your store hours?')
            ->assertSee('Generate AI Reply');
    }

    public function test_admin_can_trigger_ai_reply_for_conversation(): void
    {
        // Fake Laravel AI Agent LLM response
        CustomerSupportAgent::fake([
            'We are open Monday to Friday from 9 AM to 6 PM.',
        ]);

        $response = $this->actingAs($this->admin)
            ->post("/dashboard/conversations/{$this->conversation->id}/ai-reply");

        $response->assertRedirect()
            ->assertSessionHas('success', 'AI response generated successfully.');

        // Verify outbound message was created with AI metadata
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'body' => 'We are open Monday to Friday from 9 AM to 6 PM.',
        ]);

        $this->assertSame(2, $this->conversation->fresh()->messages()->count());
    }
}
