<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\Commerce\OrderCaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceOrdersAndAiCustomizationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private ChannelAccount $account;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);

        $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => \App\Enums\Permissions\ConversationPermission::UPDATE->value, 'guard_name' => 'web'],
            ['display_name' => 'Update Conversation', 'module' => 'conversation']
        );
        $adminRole->givePermissionTo($perm);

        $this->workspace = Workspace::create([
            'name'     => 'Ananya Fashion Hub',
            'slug'     => 'ananya-fashion',
            'currency' => 'BDT',
            'settings' => [
                'ai_bot_name'                => 'Ananya Smart Assistant',
                'ai_persona_tone'            => 'friendly_banglish',
                'custom_sales_prompt'        => 'ঢাকার ভিতরে ডেলিভারি ৬০ টাকা, বাইরে ১২০ টাকা। ৩ দিনে পৌঁছাবে।',
                'fallback_message'           => 'আমাদের সাপোর্ট টিম শীঘ্রই আপনার সাথে যোগাযোগ করবে।',
                'order_auto_capture_enabled' => true,
            ],
        ]);

        $this->owner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email'        => 'owner@ananya.test',
        ]);
        $this->owner->assignRole($adminRole);

        $channel = Channel::firstOrCreate(
            ['slug' => 'facebook'],
            ['name' => 'Facebook Messenger', 'icon' => 'ri-facebook-circle-fill', 'is_active' => true]
        );

        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Ananya Page',
            'display_name' => 'Ananya Fashion Official',
            'external_id'  => 'fb_page_1001',
            'access_token' => 'dummy_token_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_fb_888',
            'customer_name'      => 'Kazi Arman',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_workspace_ai_settings_are_stored_and_retrieved(): void
    {
        $this->assertEquals('Ananya Smart Assistant', $this->workspace->getAiBotName());
        $this->assertEquals('friendly_banglish', $this->workspace->getAiPersonaTone());
        $this->assertStringContainsString('ঢাকার ভিতরে ডেলিভারি ৬০ টাকা', (string) $this->workspace->getCustomSalesPrompt());
        $this->assertTrue($this->workspace->isOrderAutoCaptureEnabled());
        $this->assertStringContainsString('Friendly, warm, and natural', $this->workspace->getAiToneInstructions());
    }

    public function test_owner_can_update_workspace_ai_settings(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('workspace.settings.update'), [
                'name'     => 'Ananya Fashion Updated',
                'currency' => 'BDT',
                'settings' => [
                    'ai_bot_name'                => 'Ananya AI Bot 2.0',
                    'ai_persona_tone'            => 'formal_bengali',
                    'custom_sales_prompt'        => 'ডেলিভারি চার্জ ৮০ টাকা।',
                    'order_auto_capture_enabled' => '1',
                ],
            ]);

        $response->assertRedirect(route('workspace.settings.edit'));
        $this->workspace->refresh();

        $this->assertEquals('Ananya AI Bot 2.0', $this->workspace->getAiBotName());
        $this->assertEquals('formal_bengali', $this->workspace->getAiPersonaTone());
        $this->assertStringContainsString('Formal, respectful', $this->workspace->getAiToneInstructions());
    }

    public function test_order_capture_service_extracts_and_creates_order(): void
    {
        $service = app(OrderCaptureService::class);

        $userText = "আমি এই নীল জামদানি শাড়িটা নিতে চাই। নাম: তানজিলা আহমেদ, ফোন: 01799112233, ঠিকানা: রোড ৫, সেক্টর ৪, উত্তরা, ঢাকা।";
        $aiText = "ধন্যবাদ তানজিলা আপু! আপনার অর্ডারটি গ্রহণ করা হয়েছে।";

        $order = $service->extractAndCapture($this->conversation, $userText, $aiText);

        $this->assertNotNull($order);
        $this->assertInstanceOf(WorkspaceOrder::class, $order);
        $this->assertEquals($this->workspace->id, $order->workspace_id);
        $this->assertEquals('01799112233', $order->customer_phone);
        $this->assertEquals('তানজিলা আহমেদ', $order->customer_name);
        $this->assertStringContainsString('উত্তরা', (string) $order->delivery_address);
        $this->assertEquals('pending', $order->status);
        $this->assertStringStartsWith('ORD-', $order->order_number);

        // Verify phone formatter & WhatsApp URL
        $this->assertEquals('8801799112233', $order->getCleanPhone());
        $this->assertStringContainsString('wa.me/8801799112233', (string) $order->getWhatsAppUrl());
    }

    public function test_owner_can_view_orders_index_and_update_status(): void
    {
        $order = WorkspaceOrder::create([
            'workspace_id'     => $this->workspace->id,
            'conversation_id'  => $this->conversation->id,
            'order_number'     => 'ORD-260101-TEST',
            'channel'          => 'facebook',
            'customer_name'    => 'Rahim Uddin',
            'customer_phone'   => '01811223344',
            'delivery_address' => 'Mirpur 10, Dhaka',
            'product_summary'  => 'Silk Panjabi (1x)',
            'status'           => 'pending',
            'captured_at'      => now(),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('orders.index'));

        $response->assertOk();
        $response->assertSee('ORD-260101-TEST');
        $response->assertSee('Rahim Uddin');
        $response->assertSee('01811223344');

        // Update status to confirmed
        $updateResponse = $this->actingAs($this->owner)
            ->post(route('orders.update-status', $order), [
                'status'      => 'confirmed',
                'admin_notes' => 'Call verified by agent',
            ]);

        $updateResponse->assertRedirect();
        $order->refresh();
        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals('Call verified by agent', $order->admin_notes);
    }

    public function test_human_agent_can_pause_and_resume_ai(): void
    {
        $this->assertFalse($this->conversation->isAiPaused());

        // Pause AI
        $this->actingAs($this->owner)
            ->post(route('conversations.toggle-ai', $this->conversation), [
                'duration' => '1_hour',
            ]);

        $this->conversation->refresh();
        $this->assertTrue($this->conversation->isAiPaused());

        // Resume AI
        $this->actingAs($this->owner)
            ->post(route('conversations.toggle-ai', $this->conversation));

        $this->conversation->refresh();
        $this->assertFalse($this->conversation->isAiPaused());
    }

    public function test_channel_diagnostic_ping_works(): void
    {
        $response = $this->actingAs($this->owner)
            ->post(route('channels.ping', $this->account));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }
}
