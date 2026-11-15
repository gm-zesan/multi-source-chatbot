<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permissions\UserPermission;
use App\Enums\RoleEnum;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $superadmin;
    private User $ownerA;
    private User $staffA;
    private User $ownerB;
    private Role $adminRole;
    private Role $superadminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadminRole = Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $this->adminRole = Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);

        foreach ([
            UserPermission::VIEW->value,
            UserPermission::CREATE->value,
            UserPermission::UPDATE->value,
            UserPermission::DELETE->value,
        ] as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm, 'guard_name' => 'web'],
                ['display_name' => ucwords(str_replace(['.', '_'], ' ', $perm)), 'module' => 'user']
            );
        }

        $this->adminRole->givePermissionTo([
            UserPermission::VIEW->value,
            UserPermission::CREATE->value,
            UserPermission::UPDATE->value,
            UserPermission::DELETE->value,
        ]);

        $this->workspaceA = Workspace::create(['name' => 'Workspace A', 'slug' => 'ws-a']);
        $this->workspaceB = Workspace::create(['name' => 'Workspace B', 'slug' => 'ws-b']);

        $this->superadmin = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'super@platform.test',
        ]);
        $this->superadmin->assignRole($this->superadminRole);

        $this->ownerA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'ownerA@workspaceA.test',
        ]);
        $this->ownerA->assignRole($this->adminRole);

        $this->staffA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
            'email'        => 'staffA@workspaceA.test',
        ]);
        $this->staffA->assignRole($this->adminRole);

        $this->ownerB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
            'email'        => 'ownerB@workspaceB.test',
        ]);
        $this->ownerB->assignRole($this->adminRole);
    }

    public function test_workspace_owner_only_sees_users_within_their_own_workspace(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->getJson(route('users.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $data = $response->json('data');

        $emails = array_column($data, 'email');
        $this->assertContains('staffA@workspaceA.test', $emails);
        $this->assertNotContains('ownerB@workspaceB.test', $emails);
    }

    public function test_superadmin_sees_all_users_across_all_workspaces(): void
    {
        $this->actingAs($this->superadmin);

        $response = $this->getJson(route('users.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $data = $response->json('data');

        $emails = array_column($data, 'email');
        $this->assertContains('staffA@workspaceA.test', $emails);
        $this->assertContains('ownerB@workspaceB.test', $emails);
    }

    public function test_workspace_owner_creates_user_within_own_workspace(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->post(route('users.store'), [
            'name'     => 'New Team Agent',
            'email'    => 'newagent@workspaceA.test',
            'password' => 'Secret12345!',
            'role'     => RoleEnum::ADMIN->value,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email'        => 'newagent@workspaceA.test',
            'workspace_id' => $this->workspaceA->id,
        ]);
    }

    public function test_workspace_owner_cannot_privilege_escalate_to_superadmin(): void
    {
        $this->actingAs($this->ownerA);

        $response = $this->post(route('users.store'), [
            'name'     => 'Sneaky User',
            'email'    => 'sneaky@workspaceA.test',
            'password' => 'Secret12345!',
            'role'     => RoleEnum::SUPERADMIN->value,
        ]);

        $response->assertRedirect(route('users.index'));

        $created = User::where('email', 'sneaky@workspaceA.test')->first();
        $this->assertNotNull($created);
        $this->assertFalse($created->hasRole(RoleEnum::SUPERADMIN->value));
        $this->assertTrue($created->hasRole(RoleEnum::ADMIN->value));
    }

    public function test_workspace_owner_cannot_edit_or_delete_user_from_another_workspace(): void
    {
        $this->actingAs($this->ownerA);

        // Edit attempt across tenant boundary -> 403
        $editResponse = $this->get(route('users.edit', $this->ownerB->id));
        $editResponse->assertStatus(403);

        // Update attempt across tenant boundary -> 403
        $updateResponse = $this->put(route('users.update', $this->ownerB->id), [
            'name'  => 'Hacked Name',
            'email' => 'ownerB-hacked@workspaceB.test',
        ]);
        $updateResponse->assertStatus(403);

        // Delete attempt across tenant boundary -> 403
        $deleteResponse = $this->delete(route('users.destroy', $this->ownerB->id));
        $deleteResponse->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $this->ownerB->id]);
    }

    public function test_workspace_owner_cannot_delete_their_own_account(): void
    {
        $this->actingAs($this->ownerA);

        $deleteResponse = $this->delete(route('users.destroy', $this->ownerA->id));
        $deleteResponse->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $this->ownerA->id]);
    }
}
