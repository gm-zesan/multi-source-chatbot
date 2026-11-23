<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Channels\Facebook\FacebookDriver;
use App\Channels\WhatsApp\WhatsAppDriver;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUsageQuota;
use App\Services\Channels\MetaZeroCostProtectionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MetaZeroChargeProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Channel $whatsappChannel;
    private Channel $facebookChannel;
    private ChannelAccount $whatsappAccount;
    private ChannelAccount $facebookAccount;
    private Conversation $whatsappConversation;
    private Conversation $facebookConversation;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->workspace = Workspace::create([
            'id'   => 1,
            'name' => 'Zero Charge Test Store',
            'slug' => 'zero-charge-test-store',
        ]);

        $this->owner = User::factory()->create([
            'workspace_id' => $this->workspace->id,
        ]);
        $this->owner->assignRole('admin');

        $this->whatsappChannel = Channel::firstOrCreate(
            ['slug' => 'whatsapp'],
            ['name' => 'WhatsApp', 'driver' => 'whatsapp', 'is_active' => true]
        );

        $this->facebookChannel = Channel::firstOrCreate(
            ['slug' => 'facebook'],
            ['name' => 'Facebook Messenger', 'driver' => 'facebook', 'is_active' => true]
        );

        $this->whatsappAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->whatsappChannel->id,
            'name'         => 'Test WA Account',
            'external_id'  => 'wa_phone_test_123',
            'access_token' => 'test_wa_access_token',
            'is_active'    => true,
            'settings'     => ['phone_number_id' => 'wa_phone_test_123'],
        ]);

        $this->facebookAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $this->facebookChannel->id,
            'name'         => 'Test FB Page',
            'external_id'  => 'fb_page_test_123',
            'access_token' => 'test_fb_access_token',
            'is_active'    => true,
        ]);

        $this->whatsappConversation = Conversation::create([
            'workspace_id'       => $this->workspace->id,
            'channel_account_id' => $this->whatsappAccount->id,
            'external_user_id'   => '8801700000000',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'last_message_at'    => now(),
        ]);

        $this->facebookConversation = Conversation::create([
            'workspace_id'       => $this->workspace->id,
            'channel_account_id' => $this->facebookAccount->id,
            'external_user_id'   => 'fb_psid_999888',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'last_message_at'    => now(),
        ]);
    }

    public function test_whatsapp_outbound_increments_quota_when_within_1000_limit(): void
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/wa_phone_test_123/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'messages'          => [['id' => 'wamid.HBgL...']],
            ], 200),
        ]);

        $quota = WorkspaceUsageQuota::currentForWorkspace($this->workspace->id);
        $this->assertSame(0, $quota->whatsapp_messages_sent);

        $driver = new WhatsAppDriver();
        $response = $driver->send($this->whatsappAccount, $this->whatsappConversation, 'Hello Customer, order confirmed!');

        $this->assertArrayHasKey('messages', $response);

        $quota->refresh();
        $this->assertSame(1, $quota->whatsapp_messages_sent);
        $this->assertSame(999, $quota->whatsAppRemaining());
    }

    public function test_whatsapp_outbound_is_strictly_blocked_when_free_quota_is_exhausted(): void
    {
        Http::fake();

        // Simulate quota exhausted
        $quota = WorkspaceUsageQuota::currentForWorkspace($this->workspace->id);
        $quota->whatsapp_messages_sent = 1000;
        $quota->whatsapp_free_limit = 1000;
        $quota->zero_charge_protection = true;
        $quota->save();

        $driver = new WhatsAppDriver();
        $response = $driver->send($this->whatsappAccount, $this->whatsappConversation, 'Should not send this message!');

        // Verify Graph API was NEVER hit
        Http::assertNothingSent();

        // Verify blocked response
        $this->assertTrue($response['blocked'] ?? false);
        $this->assertSame('blocked_quota_exhausted', $response['status']);

        // Verify businessman was notified
        $quota->refresh();
        $this->assertNotNull($quota->quota_exhausted_sent_at);

        $this->workspace->refresh();
        $notifications = $this->workspace->settings['channel_quota_notifications'] ?? [];
        $this->assertNotEmpty($notifications);
        $this->assertSame('quota_exhausted', $notifications[0]['type']);
        $this->assertStringContainsString('১,০০০টি সম্পূর্ণ ফ্রি মেসেজ কোটা শেষ হয়েছে', $notifications[0]['message']);
    }

    public function test_whatsapp_marketing_templates_are_blocked_under_zero_charge_mode(): void
    {
        $service = app(MetaZeroCostProtectionService::class);
        $check = $service->checkOutbound(
            $this->whatsappAccount,
            $this->whatsappConversation,
            'Promo offer 50% discount!',
            'marketing'
        );

        $this->assertFalse($check['allowed']);
        $this->assertSame('blocked_marketing_fee', $check['status']);
        $this->assertStringContainsString('Marketing templates carry a Meta fee', $check['reason']);
    }

    public function test_facebook_messenger_blocks_replies_outside_24h_free_window(): void
    {
        Http::fake();

        // Set conversation last message to 26 hours ago
        $this->facebookConversation->last_message_at = Carbon::now()->subHours(26);
        $this->facebookConversation->save();

        $driver = new FacebookDriver();
        $response = $driver->send($this->facebookAccount, $this->facebookConversation, 'Delayed reply');

        Http::assertNothingSent();

        $this->assertTrue($response['blocked'] ?? false);
        $this->assertSame('blocked_messenger_24h_window', $response['status']);
    }

    public function test_near_limit_80_percent_triggers_warning_notification(): void
    {
        Http::fake([
            'https://graph.facebook.com/v20.0/wa_phone_test_123/messages' => Http::response(['success' => true], 200),
        ]);

        $quota = WorkspaceUsageQuota::currentForWorkspace($this->workspace->id);
        $quota->whatsapp_messages_sent = 799;
        $quota->save();

        $driver = new WhatsAppDriver();
        $driver->send($this->whatsappAccount, $this->whatsappConversation, 'Testing threshold message');

        $quota->refresh();
        $this->assertSame(800, $quota->whatsapp_messages_sent);
        $this->assertNotNull($quota->quota_warning_sent_at);

        $this->workspace->refresh();
        $notifications = $this->workspace->settings['channel_quota_notifications'] ?? [];
        $this->assertNotEmpty($notifications);
        $this->assertSame('quota_warning', $notifications[0]['type']);
        $this->assertStringContainsString('80%', $notifications[0]['message']);
    }

    public function test_channels_and_dashboard_display_quota_monitor_and_alert_banners(): void
    {
        // 1. When under quota
        $response = $this->actingAs($this->owner)->get('/dashboard/channels');
        $response->assertStatus(200);
        $response->assertSee('Zero-Charge Protection Active', false);
        $response->assertSee('Meta WhatsApp & Messenger Free Quota Monitor', false);

        // 2. When exhausted
        $quota = WorkspaceUsageQuota::currentForWorkspace($this->workspace->id);
        $quota->whatsapp_messages_sent = 1000;
        $quota->save();

        $responseExhausted = $this->actingAs($this->owner)->get('/dashboard/channels');
        $responseExhausted->assertStatus(200);
        $responseExhausted->assertSee('এই মাসের WhatsApp ফ্রি ১,০০০টি মেসেজের কোটা শেষ হয়েছে', false);
        $responseExhausted->assertSee('পেইড মেসেজ প্যাক (Coming Soon)', false);
        $responseExhausted->assertSee('paidServiceComingSoonModal', false);

        // Dashboard also reflects warning banner and Coming Soon trigger
        $dashResp = $this->actingAs($this->owner)->get('/dashboard');
        $dashResp->assertStatus(200);
        $dashResp->assertSee('আপনার হোয়াটসঅ্যাপের এই মাসের ১,০০০টি সম্পূর্ণ ফ্রি মেসেজ কোটা শেষ হয়েছে', false);
        $dashResp->assertSee('পেইড সার্ভিস (Coming Soon)', false);
        $dashResp->assertSee('paidServiceComingSoonModal', false);
    }
}
