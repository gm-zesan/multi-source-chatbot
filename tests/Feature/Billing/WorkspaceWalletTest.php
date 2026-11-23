<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceWallet;
use App\Models\WorkspaceWalletTransaction;
use App\Services\Billing\WorkspaceWalletService;
use App\Services\Subscription\SubscriptionQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceWalletTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $merchant;
    private User $superadmin;
    private WorkspaceWalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->workspace = Workspace::create([
            'name' => 'Apex Electronics',
            'slug' => 'apex-electronics',
        ]);

        $this->merchant = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email'        => 'merchant@apex.test',
        ]);
        $this->merchant->assignRole($adminRole);

        $this->superadmin = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email'        => 'superadmin@example.com',
        ]);
        $this->superadmin->assignRole(RoleEnum::SUPERADMIN->value);

        $this->walletService = app(WorkspaceWalletService::class);
    }

    public function test_workspace_gets_automatic_starter_bonus_wallet_on_first_access(): void
    {
        $wallet = $this->walletService->getOrCreateWallet($this->workspace);

        $this->assertNotNull($wallet);
        $this->assertEquals($this->workspace->id, $wallet->workspace_id);
        $this->assertEquals(50.00, (float) $wallet->balance);
        $this->assertEquals(0.40, (float) $wallet->rate_per_message);
        $this->assertFalse($wallet->is_frozen);
        $this->assertEquals(125, $wallet->estimatedRemainingMessages());

        // Verify starter bonus transaction
        $this->assertDatabaseHas('workspace_wallet_transactions', [
            'workspace_id' => $this->workspace->id,
            'type'         => 'credit',
            'category'     => 'bonus',
            'amount'       => 50.00,
        ]);
    }

    public function test_can_afford_message_checks_balance_correctly(): void
    {
        $wallet = $this->walletService->getOrCreateWallet($this->workspace);
        $this->assertTrue($this->walletService->canAffordMessage($this->workspace->id));

        // Drain balance below 0.40
        $wallet->balance = 0.20;
        $wallet->save();

        $this->assertFalse($this->walletService->canAffordMessage($this->workspace->id));
    }

    public function test_deduct_message_usage_deducts_exact_rate_and_records_transaction(): void
    {
        $this->walletService->getOrCreateWallet($this->workspace);

        $result = $this->walletService->deductMessageUsage(
            workspaceId: $this->workspace->id,
            messageRef: 'msg_test_101',
            channel: 'Facebook Messenger'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(0.40, $result['deducted']);
        $this->assertEquals(49.60, $result['balance']);

        // Check DB transaction
        $this->assertDatabaseHas('workspace_wallet_transactions', [
            'workspace_id' => $this->workspace->id,
            'type'         => 'debit',
            'category'     => 'message_usage',
            'amount'       => 0.40,
            'balance_after'=> 49.60,
            'reference_id' => 'msg_test_101',
        ]);
    }

    public function test_wallet_freezes_when_balance_falls_below_message_rate(): void
    {
        $wallet = $this->walletService->getOrCreateWallet($this->workspace);
        $wallet->balance = 0.30; // Less than ৳0.40
        $wallet->save();

        $result = $this->walletService->deductMessageUsage(
            workspaceId: $this->workspace->id,
            messageRef: 'msg_fail_101',
            channel: 'WhatsApp'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient wallet balance', $result['error'] ?? '');

        $wallet->refresh();
        $this->assertTrue($wallet->is_frozen);
    }

    public function test_merchant_can_recharge_wallet_via_gateway(): void
    {
        $wallet = $this->walletService->getOrCreateWallet($this->workspace);
        $wallet->balance = 0.00;
        $wallet->is_frozen = true;
        $wallet->save();

        $tx = $this->walletService->recharge(
            workspace: $this->workspace,
            amount: 500.00,
            gateway: 'bkash',
            trxId: 'BKASH_TRX_999'
        );

        $this->assertEquals(500.00, (float) $tx->amount);
        $this->assertEquals(500.00, (float) $tx->balance_after);

        $wallet->refresh();
        $this->assertEquals(500.00, (float) $wallet->balance);
        $this->assertFalse($wallet->is_frozen);
        $this->assertEquals(1250, $wallet->estimatedRemainingMessages());
    }

    public function test_superadmin_can_manually_adjust_tenant_wallet(): void
    {
        $this->walletService->getOrCreateWallet($this->workspace);

        $tx = $this->walletService->adjustBalance(
            workspace: $this->workspace,
            amount: 250.00,
            reason: 'Promotional Eid Bonus',
            adminUser: $this->superadmin
        );

        $this->assertEquals(250.00, (float) $tx->amount);
        $this->assertEquals(300.00, (float) $tx->balance_after);

        $this->assertDatabaseHas('workspace_wallet_transactions', [
            'workspace_id' => $this->workspace->id,
            'category'     => 'adjustment',
            'amount'       => 250.00,
            'balance_after'=> 300.00,
        ]);
    }

    public function test_merchant_can_view_billing_page(): void
    {
        $response = $this->actingAs($this->merchant)
            ->get(route('billing.index'));

        $response->assertOk();
        $response->assertSeeText('Prepaid Wallet & Metered Usage', false);
        $response->assertSee('50.00'); // Starter bonus balance
        $response->assertSee('125');   // Estimated messages
        $response->assertSee('0.40');  // Rate per message
        $response->assertSee('পেইড মেসেজিং ও স্বয়ংক্রিয় গেটওয়ে রিচার্জ সার্ভিস শীঘ্রই আসছে', false);
        $response->assertSee('Coming Soon', false);
    }

    public function test_recharge_endpoint_validates_and_credits_wallet(): void
    {
        $response = $this->actingAs($this->merchant)
            ->post(route('billing.recharge'), [
                'amount'  => 1000,
                'gateway' => 'nagad',
                'trx_id'  => 'NAGAD_TRX_777',
            ]);

        $response->assertRedirect(route('billing.index'));
        $response->assertSessionHas('success');

        $wallet = WorkspaceWallet::where('workspace_id', $this->workspace->id)->first();
        $this->assertEquals(1050.00, (float) $wallet->balance); // 50 starter + 1000
    }

    public function test_quota_service_automatically_deducts_wallet_balance_on_conversation(): void
    {
        $quotaService = app(SubscriptionQuotaService::class);

        // Record a conversation usage
        $quotaService->recordConversationUsage($this->workspace->id, 'channel_conv_123');

        $wallet = WorkspaceWallet::where('workspace_id', $this->workspace->id)->first();
        $this->assertEquals(49.60, (float) $wallet->balance);

        $this->assertDatabaseHas('workspace_wallet_transactions', [
            'workspace_id' => $this->workspace->id,
            'category'     => 'message_usage',
            'amount'       => 0.40,
        ]);
    }
}
