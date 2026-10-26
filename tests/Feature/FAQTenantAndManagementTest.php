<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permissions\FAQPermission;
use App\Enums\RoleEnum;
use App\Models\FAQ;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FAQTenantAndManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceA;
    protected Workspace $workspaceB;
    protected User $tenantAdminA;
    protected User $tenantAdminB;
    protected User $superAdmin;
    protected FAQ $faqA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->workspaceA = Workspace::create(['name' => 'Workspace A', 'slug' => 'workspace-a']);
        $this->workspaceB = Workspace::create(['name' => 'Workspace B', 'slug' => 'workspace-b']);

        // Roles
        $adminRoleA = Role::create([
            'name'         => 'admin-a',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceA->id,
        ]);
        $adminRoleA->givePermissionTo([
            FAQPermission::VIEW->value,
            FAQPermission::CREATE->value,
            FAQPermission::UPDATE->value,
            FAQPermission::DELETE->value,
        ]);

        $adminRoleB = Role::create([
            'name'         => 'admin-b',
            'guard_name'   => 'web',
            'workspace_id' => $this->workspaceB->id,
        ]);
        $adminRoleB->givePermissionTo([
            FAQPermission::VIEW->value,
            FAQPermission::CREATE->value,
            FAQPermission::UPDATE->value,
            FAQPermission::DELETE->value,
        ]);

        $superRole = Role::create([
            'name'         => RoleEnum::SUPERADMIN->value,
            'guard_name'   => 'web',
            'workspace_id' => null,
        ]);
        $superRole->givePermissionTo(Permission::all());

        // Users
        $this->tenantAdminA = User::factory()->create(['workspace_id' => $this->workspaceA->id]);
        $this->tenantAdminA->assignRole($adminRoleA);

        $this->tenantAdminB = User::factory()->create(['workspace_id' => $this->workspaceB->id]);
        $this->tenantAdminB->assignRole($adminRoleB);

        $this->superAdmin = User::factory()->create(['workspace_id' => null]);
        $this->superAdmin->assignRole($superRole);

        $this->faqA = FAQ::create([
            'workspace_id'     => $this->workspaceA->id,
            'question'         => 'Alpha Question 1?',
            'answer'           => 'Alpha Answer 1',
            'document_type'    => 'faq',
            'priority'         => 1,
            'is_active'        => true,
            'searchable_text'  => 'Alpha Question 1? Alpha Answer 1',
        ]);
    }

    public function test_same_workspace_user_can_access_and_edit_faq(): void
    {
        $response = $this->actingAs($this->tenantAdminA)->get(route('faqs.edit', $this->faqA));
        $response->assertOk();
        $response->assertSee('Alpha Question 1?');
    }

    public function test_cross_workspace_user_is_forbidden_from_editing_faq(): void
    {
        $response = $this->actingAs($this->tenantAdminB)->get(route('faqs.edit', $this->faqA));
        $response->assertStatus(403);
    }

    public function test_superadmin_can_access_faq_across_workspaces(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('faqs.edit', $this->faqA));
        $response->assertOk();
    }

    public function test_cross_workspace_toggle_active_is_forbidden(): void
    {
        $response = $this->actingAs($this->tenantAdminB)->post(route('faqs.toggle-active', $this->faqA));
        $response->assertStatus(403);
    }

    public function test_faq_datatables_only_returns_own_workspace_records_for_tenant_admin(): void
    {
        // Add record in Workspace B
        FAQ::create([
            'workspace_id'     => $this->workspaceB->id,
            'question'         => 'Beta Question 2?',
            'answer'           => 'Beta Answer 2',
            'document_type'    => 'faq',
            'is_active'        => true,
            'searchable_text'  => 'Beta Question 2? Beta Answer 2',
        ]);

        $response = $this->actingAs($this->tenantAdminA)->getJson(route('faqs.index'), [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $response->assertOk();
        $json = $response->json();
        $questions = array_column($json['data'] ?? [], 'question');
        $this->assertContains('Alpha Question 1?', $questions);
        $this->assertNotContains('Beta Question 2?', $questions);
    }
}
