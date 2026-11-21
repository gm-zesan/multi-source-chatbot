<?php

declare(strict_types=1);

namespace Tests\Feature\Commerce;

use App\Enums\RoleEnum;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DynamicSubscriptionPlansTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $superadmin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        $this->workspace = Workspace::create([
            'name'     => 'Commerce Main',
            'slug'     => 'commerce-main',
            'currency' => 'BDT',
        ]);

        $this->superadmin = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email'        => 'superadmin@example.com',
        ]);
        $this->superadmin->assignRole(RoleEnum::SUPERADMIN->value);

        $this->regularUser = User::factory()->create([
            'workspace_id' => $this->workspace->id,
            'email'        => 'merchant@example.com',
        ]);
        $this->regularUser->assignRole(RoleEnum::ADMIN->value);
    }

    public function test_superadmin_can_view_commercial_dashboard_with_plans(): void
    {
        $plan = SubscriptionPlan::create([
            'name'                      => 'Starter Growth',
            'slug'                      => 'starter-growth',
            'price_monthly'             => 999.00,
            'price_yearly'              => 9990.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 1500,
            'max_knowledge_docs'        => 15,
            'max_staff_seats'           => 5,
            'features'                  => ['Omnichannel Bot', 'Automated Lead Extraction'],
            'is_active'                 => true,
        ]);

        $response = $this->actingAs($this->superadmin)->get(route('admin.commercial.index'));

        $response->assertStatus(200);
        $response->assertSee('Starter Growth');
        $response->assertSee('starter-growth');
        $response->assertSee('New Subscription Plan');
    }

    public function test_superadmin_can_create_new_subscription_plan(): void
    {
        $payload = [
            'name'                      => 'Pro Commerce Plan',
            'slug'                      => 'pro-commerce',
            'price_monthly'             => 2499.00,
            'price_yearly'              => 24990.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 5000,
            'max_knowledge_docs'        => 30,
            'max_staff_seats'           => 10,
            'features'                  => "Omnichannel AI\nAutomated Orders\nExcel Training",
            'is_active'                 => '1',
        ];

        $response = $this->actingAs($this->superadmin)
            ->post(route('admin.commercial.plans.store'), $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('subscription_plans', [
            'name'                      => 'Pro Commerce Plan',
            'slug'                      => 'pro-commerce',
            'price_monthly'             => 2499.00,
            'max_conversations_monthly' => 5000,
            'is_active'                 => 1,
        ]);

        $plan = SubscriptionPlan::where('slug', 'pro-commerce')->first();
        $this->assertNotNull($plan);
        $this->assertContains('Omnichannel AI', $plan->features);
        $this->assertContains('Automated Orders', $plan->features);
    }

    public function test_superadmin_can_update_subscription_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'name'                      => 'Old Plan Name',
            'slug'                      => 'old-plan',
            'price_monthly'             => 500.00,
            'price_yearly'              => 5000.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 500,
            'max_knowledge_docs'        => 5,
            'max_staff_seats'           => 2,
            'features'                  => ['Feature 1'],
            'is_active'                 => true,
        ]);

        $updatePayload = [
            'name'                      => 'Updated Plan Name',
            'slug'                      => 'updated-plan',
            'price_monthly'             => 750.00,
            'price_yearly'              => 7500.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 800,
            'max_knowledge_docs'        => 10,
            'max_staff_seats'           => 4,
            'features'                  => 'Feature 1, Feature 2, Feature 3',
            'is_active'                 => '1',
        ];

        $response = $this->actingAs($this->superadmin)
            ->put(route('admin.commercial.plans.update', $plan), $updatePayload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $plan->refresh();
        $this->assertEquals('Updated Plan Name', $plan->name);
        $this->assertEquals(750.00, (float) $plan->price_monthly);
        $this->assertEquals(800, $plan->max_conversations_monthly);
        $this->assertCount(3, $plan->features);
    }

    public function test_superadmin_can_toggle_plan_status(): void
    {
        $plan = SubscriptionPlan::create([
            'name'                      => 'Toggle Test Plan',
            'slug'                      => 'toggle-test',
            'price_monthly'             => 100.00,
            'price_yearly'              => 1000.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 100,
            'max_knowledge_docs'        => 1,
            'max_staff_seats'           => 1,
            'is_active'                 => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('admin.commercial.plans.toggle', $plan));

        $response->assertRedirect();
        $plan->refresh();
        $this->assertFalse($plan->is_active);

        // Toggle back
        $this->actingAs($this->superadmin)
            ->post(route('admin.commercial.plans.toggle', $plan));
        $plan->refresh();
        $this->assertTrue($plan->is_active);
    }

    public function test_superadmin_cannot_delete_plan_with_active_subscribers(): void
    {
        $plan = SubscriptionPlan::create([
            'name'                      => 'Protected Plan',
            'slug'                      => 'protected-plan',
            'price_monthly'             => 200.00,
            'price_yearly'              => 2000.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 200,
            'max_knowledge_docs'        => 2,
            'max_staff_seats'           => 1,
            'is_active'                 => true,
        ]);

        WorkspaceSubscription::create([
            'workspace_id'    => $this->workspace->id,
            'plan_id'         => $plan->id,
            'status'          => 'active',
            'billing_cycle'   => 'monthly',
            'starts_at'       => now(),
            'payment_gateway' => 'manual',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->delete(route('admin.commercial.plans.destroy', $plan));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id]);
    }

    public function test_superadmin_can_delete_unused_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'name'                      => 'Unused Plan',
            'slug'                      => 'unused-plan',
            'price_monthly'             => 300.00,
            'price_yearly'              => 3000.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 300,
            'max_knowledge_docs'        => 3,
            'max_staff_seats'           => 1,
            'is_active'                 => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->delete(route('admin.commercial.plans.destroy', $plan));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('subscription_plans', ['id' => $plan->id]);
    }

    public function test_regular_admin_is_forbidden_from_managing_plans(): void
    {
        $response = $this->actingAs($this->regularUser)
            ->post(route('admin.commercial.plans.store'), [
                'name'                      => 'Hacker Plan',
                'price_monthly'             => 0,
                'max_conversations_monthly' => -1,
                'max_knowledge_docs'        => 100,
                'max_staff_seats'           => 100,
            ]);

        $response->assertStatus(403);
    }

    public function test_public_pricing_renders_dynamic_plans(): void
    {
        SubscriptionPlan::create([
            'name'                      => 'VIP Mega Tier',
            'slug'                      => 'vip-mega-tier',
            'price_monthly'             => 8500.00,
            'price_yearly'              => 85000.00,
            'currency'                  => 'BDT',
            'max_conversations_monthly' => 25000,
            'max_knowledge_docs'        => 50,
            'max_staff_seats'           => 15,
            'features'                  => ['Dedicated Account Manager', 'Custom CRM Sync'],
            'is_active'                 => true,
        ]);

        $response = $this->get(route('pricing'));

        $response->assertStatus(200);
        $response->assertSee('VIP Mega Tier');
        $response->assertSee('৳ 8,500.00');
        $response->assertSee('Dedicated Account Manager');
        $response->assertSee('Custom CRM Sync');
    }

    public function test_pricing_and_home_and_billing_update_dynamically(): void
    {
        // 1. Seed custom rate (e.g. 0.35/msg and 75 BDT bonus)
        SubscriptionPlan::create([
            'name'                      => 'Metered Billing',
            'slug'                      => 'metered-billing',
            'badge'                     => 'Standard Metered',
            'price_monthly'             => 0.35,
            'price_yearly'              => 0.00,
            'currency'                  => 'BDT',
            'billing_period_text'       => '/ AI reply',
            'price_note'                => 'Zero monthly commitment',
            'max_conversations_monthly' => -1,
            'max_knowledge_docs'        => 10,
            'max_staff_seats'           => 3,
            'features'                  => ['Fixed ৳0.35 BDT per response'],
            'is_featured'               => false,
            'sort_order'                => 1,
            'is_active'                 => true,
        ]);

        SubscriptionPlan::create([
            'name'                      => 'Starter Bonus',
            'slug'                      => 'starter-bonus',
            'badge'                     => 'Free Trial',
            'price_monthly'             => 70.00,
            'price_yearly'              => 0.00,
            'currency'                  => 'BDT',
            'billing_period_text'       => 'Free Credit',
            'price_note'                => '200 Complimentary AI Messages',
            'max_conversations_monthly' => 200,
            'max_knowledge_docs'        => 5,
            'max_staff_seats'           => 2,
            'features'                  => ['200 Full AI Messages included free'],
            'is_featured'               => true,
            'sort_order'                => 2,
            'is_active'                 => true,
        ]);

        // Home page reflects the dynamic rate & bonus
        $homeRes = $this->get(route('home'));
        $homeRes->assertStatus(200);
        $homeRes->assertSee('৳ 0.35');
        $homeRes->assertSee('৳ 70.00');

        // Pricing page reflects the dynamic rate & bonus
        $pricingRes = $this->get(route('pricing'));
        $pricingRes->assertStatus(200);
        $pricingRes->assertSee('৳ 0.35');
        $pricingRes->assertSee('৳ 70.00');

        // Billing portal for owner reflects their wallet rate
        $wallet = (new \App\Services\Billing\WorkspaceWalletService())->getOrCreateWallet($this->workspace);
        $this->assertEquals(0.35, (float) $wallet->rate_per_message);
        $this->assertEquals(70.00, (float) $wallet->balance);

        $billingRes = $this->actingAs($this->superadmin)->get(route('billing.index', ['workspace_id' => $this->workspace->id]));
        $billingRes->assertStatus(200);
        $billingRes->assertSee('৳0.35 / msg');
    }
}
