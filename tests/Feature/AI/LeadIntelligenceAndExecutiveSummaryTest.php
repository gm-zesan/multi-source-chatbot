<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Enums\LeadTagEnum;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOrder;
use App\Services\AI\LeadIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadIntelligenceAndExecutiveSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected User $adminUser;
    protected ChannelAccount $channelAccount;
    protected LeadIntelligenceService $leadService;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => \App\Enums\Permissions\ConversationPermission::VIEW->value, 'guard_name' => 'web'],
            ['display_name' => 'View Conversation', 'module' => 'conversation']
        );
        \Spatie\Permission\Models\Permission::firstOrCreate(
            ['name' => \App\Enums\Permissions\ConversationPermission::UPDATE->value, 'guard_name' => 'web'],
            ['display_name' => 'Update Conversation', 'module' => 'conversation']
        );

        $superadmin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $admin = \Spatie\Permission\Models\Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::all());
        $superadmin->givePermissionTo(\Spatie\Permission\Models\Permission::all());

        $this->workspace = Workspace::create([
            'name'      => 'Aarong Test Hub',
            'slug'      => 'aarong-test-hub',
            'currency'  => 'BDT',
            'is_active' => true,
            'settings'  => [
                'ai_tone' => 'friendly_bangla',
            ],
        ]);

        $this->adminUser = User::factory()->create([
            'name'         => 'Store Owner',
            'email'        => 'owner@aarong.com',
            'workspace_id' => $this->workspace->id,
            'is_active'    => true,
        ]);
        $this->adminUser->assignRole(RoleEnum::ADMIN->value);

        $channel = Channel::firstOrCreate(
            ['slug' => 'website'],
            ['name' => 'Website', 'is_active' => true]
        );

        $this->channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Aarong Web Widget',
            'external_id'  => 'widget_' . uniqid(),
            'access_token' => 'token_' . uniqid(),
            'is_active'    => true,
        ]);

        $this->leadService = app(LeadIntelligenceService::class);
    }

    public function test_it_classifies_hot_lead_with_high_purchase_intent_and_generates_summary(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_101',
            'customer_name'      => 'Tanvir Ahmed',
            'last_direction'     => 'inbound',
            'last_message'       => 'এই ব্ল্যাক পাঞ্জাবি সাইজ ৪২ কি স্টকে আছে? আজকেই অর্ডার করব, খুব জরুরি।',
            'last_message_at'    => now(),
            'status'             => 'open',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'এই ব্ল্যাক পাঞ্জাবি সাইজ ৪২ কি স্টকে আছে? আজকেই অর্ডার করব, খুব জরুরি।',
        ]);

        $result = $this->leadService->classifyAndTagConversation($conversation, force: true);

        $this->assertContains(LeadTagEnum::HOT_LEAD->value, $result['tags']);
        $this->assertNotEmpty($result['summary']);
        $this->assertStringContainsString('Tanvir Ahmed', $result['summary']);

        $conversation->refresh();
        $this->assertTrue($conversation->hasAiTag(LeadTagEnum::HOT_LEAD->value));
        $this->assertNotNull($conversation->ai_summary);
        $this->assertNotNull($conversation->ai_summary_generated_at);
    }

    public function test_it_detects_order_placed_when_order_record_exists(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_102',
            'customer_name'      => 'Sadia Islam',
            'last_direction'     => 'inbound',
            'last_message'       => 'আমার ঠিকানা: হাউস ১২, ধানমন্ডি। ফোন: 01711223344। ক্যাশ অন ডেলিভারিতে পাঠান।',
            'last_message_at'    => now(),
            'status'             => 'open',
        ]);

        WorkspaceOrder::create([
            'workspace_id'    => $this->workspace->id,
            'conversation_id' => $conversation->id,
            'order_number'    => 'ORD-2026-TEST',
            'customer_name'   => 'Sadia Islam',
            'customer_phone'  => '01711223344',
            'delivery_address'=> 'House 12, Dhanmondi, Dhaka',
            'total_amount'    => 1850.00,
            'status'          => 'pending',
            'source'          => 'ai_chat',
        ]);

        $result = $this->leadService->classifyAndTagConversation($conversation, force: true);

        $this->assertContains(LeadTagEnum::ORDER_PLACED->value, $result['tags']);
        $this->assertStringContainsString('Sadia Islam', $result['summary']);
        $this->assertStringContainsString('অর্ডার', $result['summary']);
    }

    public function test_it_detects_complaints_and_human_escalation(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_103',
            'customer_name'      => 'Rafiqul Karim',
            'last_direction'     => 'inbound',
            'last_message'       => '৫ দিন পার হয়ে গেছে পার্সেল এখনও আসে নাই! বাজে সার্ভিস, কারো সাথে কথা বলতে চাই, লাইভ এজেন্ট দেন!',
            'last_message_at'    => now(),
            'status'             => 'open',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => '৫ দিন পার হয়ে গেছে পার্সেল এখনও আসে নাই! বাজে সার্ভিস, কারো সাথে কথা বলতে চাই, লাইভ এজেন্ট দেন!',
        ]);

        $result = $this->leadService->classifyAndTagConversation($conversation, force: true);

        $this->assertContains(LeadTagEnum::COMPLAINT->value, $result['tags']);
        $this->assertContains(LeadTagEnum::HUMAN_REQUESTED->value, $result['tags']);
    }

    public function test_it_generates_daily_executive_summary_for_workspace(): void
    {
        // Conversation 1: Hot Lead
        $conv1 = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_1',
            'customer_name'      => 'User One',
            'last_direction'     => 'inbound',
            'last_message'       => 'দাম কত এবং নিতে চাই',
            'last_message_at'    => now(),
            'ai_tags'            => [LeadTagEnum::HOT_LEAD->value, LeadTagEnum::PRICE_INQUIRY->value],
            'ai_summary'         => 'User One inquired about price and wants to buy.',
            'status'             => 'open',
        ]);

        // Conversation 2: Complaint
        $conv2 = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_2',
            'customer_name'      => 'User Two',
            'last_direction'     => 'inbound',
            'last_message'       => 'প্রোডাক্ট ড্যামেজ এসেছে',
            'last_message_at'    => now(),
            'ai_tags'            => [LeadTagEnum::COMPLAINT->value],
            'ai_summary'         => 'User Two reported a damaged product issue.',
            'status'             => 'open',
        ]);

        $dailySummary = $this->leadService->generateDailyExecutiveSummary($this->workspace->id);

        $this->assertEquals(2, $dailySummary['total_conversations']);
        $this->assertEquals(1, $dailySummary['hot_leads_count']);
        $this->assertEquals(1, $dailySummary['complaints_count']);
        $this->assertEquals(1, $dailySummary['price_inquiries_count']);
        $this->assertNotEmpty($dailySummary['headline']);
        $this->assertNotEmpty($dailySummary['bullets']);
        $this->assertNotEmpty($dailySummary['recommendation']);
    }

    public function test_it_filters_conversations_by_tag_in_inbox(): void
    {
        $convHot = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_hot',
            'customer_name'      => 'Hot Buyer',
            'last_direction'     => 'inbound',
            'last_message'       => 'Buying now',
            'last_message_at'    => now(),
            'ai_tags'            => [LeadTagEnum::HOT_LEAD->value],
            'status'             => 'open',
        ]);

        $convNormal = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_norm',
            'customer_name'      => 'Normal Visitor',
            'last_direction'     => 'inbound',
            'last_message'       => 'General question',
            'last_message_at'    => now(),
            'ai_tags'            => [LeadTagEnum::GENERAL_FAQ->value],
            'status'             => 'open',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('conversations.index', ['tag' => 'hot_lead']));

        $response->assertStatus(200);
        $response->assertSee('Hot Buyer');
        $response->assertDontSee('Normal Visitor');
    }

    public function test_it_regenerates_intelligence_endpoint(): void
    {
        $conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_regen',
            'customer_name'      => 'Regen Customer',
            'last_direction'     => 'inbound',
            'last_message'       => 'ডেলিভারি চার্জ কত ভাইয়া?',
            'last_message_at'    => now(),
            'status'             => 'open',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'ডেলিভারি চার্জ কত ভাইয়া?',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->postJson(route('conversations.regenerate-intelligence', $conversation));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $conversation->refresh();
        $this->assertTrue($conversation->hasAiTag(LeadTagEnum::PRICE_INQUIRY->value));
        $this->assertNotNull($conversation->ai_summary);
    }
}
