<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Workspace\WorkspaceProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChannelIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook Messenger', 'is_active' => true]);
        Channel::firstOrCreate(['slug' => 'whatsapp'], ['name' => 'WhatsApp Business', 'is_active' => true]);
        Channel::firstOrCreate(['slug' => 'website'], ['name' => 'Storefront Widget', 'is_active' => true]);
        Channel::firstOrCreate(['slug' => 'telegram'], ['name' => 'Telegram Bot', 'is_active' => true]);

        $provisioning = app(WorkspaceProvisioningService::class);
        $this->user = $provisioning->provisionNewTenant('Acme Retail', [
            'name'     => 'Acme Owner',
            'email'    => 'owner@acmeretail.com',
            'password' => bcrypt('password123'),
        ]);

        $this->workspace = $this->user->workspace;
    }

    public function test_channels_index_page_renders_successfully(): void
    {
        $response = $this->actingAs($this->user)->get(route('channels.index'));

        $response->assertOk();
        $response->assertSee('Facebook Messenger');
        $response->assertSee('WhatsApp Business Cloud');
        $response->assertSee('Storefront Web Widget');
    }

    public function test_business_owner_can_connect_facebook_page(): void
    {
        $response = $this->actingAs($this->user)->post(route('channels.store'), [
            'channel_slug' => 'facebook',
            'display_name' => 'Acme Official Store Page',
            'external_id'  => '102938475610293',
            'access_token' => 'EAAB_test_mock_token_12345',
        ]);

        $response->assertSessionHas('success');
        $response->assertRedirect(route('channels.index'));

        $account = ChannelAccount::where('workspace_id', $this->workspace->id)
            ->where('external_id', '102938475610293')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('Acme Official Store Page', $account->display_name);
        $this->assertTrue($account->is_active);
    }

    public function test_business_owner_can_connect_whatsapp_number(): void
    {
        $response = $this->actingAs($this->user)->post(route('channels.store'), [
            'channel_slug' => 'whatsapp',
            'display_name' => 'Acme WhatsApp Support',
            'external_id'  => '998877665544332',
            'access_token' => 'EAAB_wa_mock_token_99999',
        ]);

        $response->assertSessionHas('success');

        $account = ChannelAccount::where('workspace_id', $this->workspace->id)
            ->where('external_id', '998877665544332')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('Acme WhatsApp Support', $account->display_name);
    }

    public function test_business_owner_can_toggle_channel_status(): void
    {
        $channel = Channel::where('slug', 'facebook')->firstOrFail();
        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test Page',
            'display_name' => 'Test Page',
            'external_id'  => '1122334455',
            'access_token' => 'token123',
            'is_active'    => true,
        ]);

        // Toggle to inactive
        $response = $this->actingAs($this->user)->post(route('channels.toggle', $account));
        $response->assertSessionHas('success');

        $this->assertFalse($account->fresh()->is_active);

        // Toggle back to active
        $this->actingAs($this->user)->post(route('channels.toggle', $account));
        $this->assertTrue($account->fresh()->is_active);
    }

    public function test_business_owner_can_disconnect_channel(): void
    {
        $channel = Channel::where('slug', 'facebook')->firstOrFail();
        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'To Delete Page',
            'display_name' => 'To Delete Page',
            'external_id'  => '999888777',
            'access_token' => 'token999',
            'is_active'    => true,
        ]);

        $response = $this->actingAs($this->user)->delete(route('channels.destroy', $account));
        $response->assertSessionHas('success');

        $this->assertNull(ChannelAccount::find($account->id));
    }

    public function test_tenant_isolation_prevents_unauthorized_deletion(): void
    {
        // Another workspace & user
        $provisioning = app(WorkspaceProvisioningService::class);
        $otherUser = $provisioning->provisionNewTenant('Other Org', [
            'name'     => 'Other Owner',
            'email'    => 'owner@other.com',
            'password' => bcrypt('password123'),
        ]);

        $channel = Channel::where('slug', 'facebook')->firstOrFail();
        $otherAccount = ChannelAccount::create([
            'workspace_id' => $otherUser->workspace_id,
            'channel_id'   => $channel->id,
            'name'         => 'Other Page',
            'display_name' => 'Other Page',
            'external_id'  => '555555555',
            'access_token' => 'tokenOther',
            'is_active'    => true,
        ]);

        // Attempt deletion by $this->user from Acme Retail
        $response = $this->actingAs($this->user)->delete(route('channels.destroy', $otherAccount));
        $response->assertStatus(403);

        $this->assertNotNull(ChannelAccount::find($otherAccount->id));
    }
}
