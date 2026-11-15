<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleEnum;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Models\WorkspaceUsageQuota;
use App\Services\Subscription\SubscriptionQuotaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SaaSCommercialAndQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private User $superadmin;
    private User $ownerA;
    private SubscriptionPlan $starterPlan;
    private SubscriptionPlan $proPlan;
    private SubscriptionQuotaService $quotaService;

    protected function setUp(): void
    {
        parent::setUp();

        $superadminRole = Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->workspaceA = Workspace::create(['name' => 'Commercial WS Alpha', 'slug' => 'comm-ws-a']);

        $this->superadmin = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'super@commercial.test',
        ]);
        $this->superadmin->assignRole($superadminRole);

        $this->ownerA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'ownerA@commercial.test',
        ]);
        $this->ownerA->assignRole($adminRole);

        $this->starterPlan = SubscriptionPlan::create([
            'name'                      => 'Starter Tier',
            'slug'                      => 'starter',
            'price_monthly'             => 2500.00,
            'price_yearly'              => 25000.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 500,
            'max_knowledge_docs'        => 5,
            'max_staff_seats'           => 2,
            'features'                  => ['knowledge_base', 'basic_analytics'],
            'is_active'                 => true,
        ]);

        $this->proPlan = SubscriptionPlan::create([
            'name'                      => 'Pro Business Tier',
            'slug'                      => 'pro',
            'price_monthly'             => 8000.00,
            'price_yearly'              => 80000.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 5000,
            'max_knowledge_docs'        => 25,
            'max_staff_seats'           => 10,
            'features'                  => ['knowledge_base', 'basic_analytics', 'spreadsheet_bi', 'whatsapp'],
            'is_active'                 => true,
        ]);

        $this->quotaService = app(SubscriptionQuotaService::class);
    }

    public function test_workspace_can_subscribe_to_plan(): void
    {
        $sub = $this->quotaService->subscribe(
            workspace: $this->workspaceA,
            plan: $this->starterPlan,
            billingCycle: 'monthly',
            gateway: 'bkash',
            gatewaySubId: 'BKASH-SUB-101'
        );

        $this->assertInstanceOf(WorkspaceSubscription::class, $sub);
        $this->assertSame('active', $sub->status);
        $this->assertSame('monthly', $sub->billing_cycle);
        $this->assertSame('bkash', $sub->payment_gateway);
        $this->assertTrue($sub->isActive());

        $activeSub = $this->quotaService->getActiveSubscription($this->workspaceA->id);
        $this->assertNotNull($activeSub);
        $this->assertSame($this->starterPlan->id, $activeSub->plan_id);
    }

    public function test_feature_entitlement_gating(): void
    {
        $this->quotaService->subscribe($this->workspaceA, $this->starterPlan);

        $this->assertTrue($this->quotaService->hasFeature($this->workspaceA->id, 'knowledge_base'));
        $this->assertFalse($this->quotaService->hasFeature($this->workspaceA->id, 'whatsapp')); // Pro only

        // Upgrade to Pro
        $this->quotaService->subscribe($this->workspaceA, $this->proPlan);
        $this->assertTrue($this->quotaService->hasFeature($this->workspaceA->id, 'whatsapp'));
    }

    public function test_usage_quota_tracking_and_limit_enforcement(): void
    {
        $this->quotaService->subscribe($this->workspaceA, $this->starterPlan); // limit: 500

        $period = Carbon::now()->format('Y-m');
        $quota = WorkspaceUsageQuota::currentForWorkspace($this->workspaceA->id);
        $this->assertSame(0, $quota->conversation_count);

        $this->assertTrue($this->quotaService->canCreateConversation($this->workspaceA->id));

        // Record 50 conversations
        for ($i = 0; $i < 50; $i++) {
            $this->quotaService->recordConversationUsage($this->workspaceA->id);
        }

        $quota->refresh();
        $this->assertSame(50, $quota->conversation_count);
        $this->assertTrue($this->quotaService->canCreateConversation($this->workspaceA->id));

        // Simulate quota exhaustion
        $quota->update(['conversation_count' => 500]);
        $this->assertFalse($this->quotaService->canCreateConversation($this->workspaceA->id));
    }

    public function test_billing_webhook_activates_tenant_subscription(): void
    {
        $response = $this->postJson(route('webhooks.billing', ['gateway' => 'bkash']), [
            'workspace_id'  => $this->workspaceA->id,
            'plan_slug'     => 'pro',
            'billing_cycle' => 'yearly',
            'payment_id'    => 'TRX-BKASH-778899',
            'status'        => 'active',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success'      => true,
            'status'       => 'active',
            'workspace_id' => $this->workspaceA->id,
            'plan'         => 'Pro Business Tier',
        ]);

        $this->assertDatabaseHas('workspace_subscriptions', [
            'workspace_id'            => $this->workspaceA->id,
            'plan_id'                 => $this->proPlan->id,
            'status'                  => 'active',
            'billing_cycle'           => 'yearly',
            'payment_gateway'         => 'bkash',
            'gateway_subscription_id' => 'TRX-BKASH-778899',
        ]);
    }

    public function test_superadmin_commercial_dashboard_calculates_mrr(): void
    {
        // Subscribe workspace to Starter (2,500/mo)
        $this->quotaService->subscribe(
            workspace: $this->workspaceA,
            plan: $this->starterPlan,
            billingCycle: 'monthly',
            gateway: 'stripe'
        );

        $this->actingAs($this->superadmin);

        $response = $this->get(route('admin.commercial.index'));
        $response->assertOk();
        $response->assertSee('2,500.00'); // MRR contains 2,500
        $response->assertSee('Commercial WS Alpha');
        $response->assertSee('Starter Tier');
    }

    public function test_regular_owner_cannot_access_superadmin_commercial_dashboard(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->get(route('admin.commercial.index'));
        $response->assertStatus(403);
    }
}
