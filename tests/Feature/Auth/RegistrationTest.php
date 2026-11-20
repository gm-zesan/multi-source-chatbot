<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleEnum;
use App\Models\ChannelAccount;
use App\Models\FAQ;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceSubscription;
use App\Models\WorkspaceUsageQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertSee('business_name');
        $response->assertSee('Business / Company Name');
    }

    public function test_registration_requires_business_name(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['business_name']);
        $this->assertGuest();
    }

    public function test_new_businessman_registration_provisions_isolated_workspace_and_admin_role(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Rahim Ahmed',
            'business_name'         => 'Rahim Fashion',
            'email'                 => 'rahim@fashion.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        /** @var User $user */
        $user = User::where('email', 'rahim@fashion.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->workspace_id);

        // Verify Workspace creation
        $workspace = Workspace::find($user->workspace_id);
        $this->assertNotNull($workspace);
        $this->assertSame('Rahim Fashion', $workspace->name);
        $this->assertTrue($workspace->is_active);

        // Verify User Role is Admin (Workspace Owner) and NOT SuperAdmin
        $this->assertTrue($user->hasRole(RoleEnum::ADMIN->value));
        $this->assertFalse($user->isSuperAdmin());

        // Verify Starter SaaS Subscription and Quota
        $subscription = WorkspaceSubscription::where('workspace_id', $workspace->id)->first();
        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);

        $quota = WorkspaceUsageQuota::where('workspace_id', $workspace->id)->first();
        $this->assertNotNull($quota);

        // Verify Web Widget Channel Account
        $widgetAccount = ChannelAccount::where('workspace_id', $workspace->id)->first();
        $this->assertNotNull($widgetAccount);
        $this->assertStringContainsString('Rahim Fashion', $widgetAccount->name);

        // Verify Starter FAQs
        $faqsCount = FAQ::where('workspace_id', $workspace->id)->count();
        $this->assertGreaterThanOrEqual(1, $faqsCount);
    }

    public function test_workspace_owner_is_forbidden_from_superadmin_workspaces_directory(): void
    {
        $this->post('/register', [
            'name'                  => 'Karim Khan',
            'business_name'         => 'Khan Traders',
            'email'                 => 'karim@traders.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::where('email', 'karim@traders.com')->first();
        $this->actingAs($user);

        // Accessing SuperAdmin multi-workspace directory must fail with 403 Forbidden
        $response = $this->get(route('workspaces.index'));
        $response->assertStatus(403);
    }
}
