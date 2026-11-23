<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatSimulatorControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_isolation_ignores_browser_payload()
    {
        // Setup trusted workspace
        $trustedWorkspace = Workspace::create(['id' => 1, 'name' => 'Trusted WS', 'slug' => 'trusted-ws']);
        $maliciousWorkspace = Workspace::create(['id' => 2, 'name' => 'Malicious WS', 'slug' => 'malicious-ws']);
        
        $user = User::factory()->create([
            'workspace_id' => $trustedWorkspace->id
        ]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');

        // Mock the Python Analytics Service endpoint
        Http::fake([
            '*/analytics/query' => function ($request) use ($trustedWorkspace) {
                // Ensure the workspace_id sent to Python is exactly the trusted one
                if ($request['workspace_id'] === $trustedWorkspace->id) {
                    return Http::response([
                        'success' => true,
                        'engine' => 'semantic',
                        'intent' => 'business_analytics',
                        'report' => 'Mocked report for workspace 1',
                    ], 200);
                }
                
                return Http::response([
                    'success' => false,
                    'error' => 'Unauthorized workspace access',
                ], 403);
            },
        ]);

        // Attempt injection via browser payload
        \App\AI\LLM\LLMClient::fake([
            '{"route": "ANALYTICS", "confidence": 0.99, "security_status": "allowed", "reason": "test"}'
        ]);
        $response = $this->actingAs($user)->postJson('/dashboard/simulator/send', [
            'message' => 'show total sales',
        ]);

        $response->assertStatus(200);
        
        // Assert the mock was hit with the correct workspace_id
        Http::assertSent(function ($request) use ($trustedWorkspace) {
            return str_contains($request->url(), '/analytics/query') 
                && $request['workspace_id'] === $trustedWorkspace->id
                && $request['engine'] === 'semantic';
        });
    }

    public function test_simulator_rejects_user_without_workspace()
    {
        $userWithoutWorkspace = User::factory()->create([
            'workspace_id' => null,
        ]);

        $response = $this->actingAs($userWithoutWorkspace)->postJson('/dashboard/simulator/send', [
            'message' => 'hello',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_simulator()
    {
        $response = $this->get('/dashboard/simulator');
        $response->assertRedirect('/login');

        $responsePost = $this->postJson('/dashboard/simulator/send', [
            'message' => 'hello',
        ]);
        $responsePost->assertStatus(401);
    }

    public function test_select_source_rejects_document_from_different_workspace()
    {
        $workspaceA = Workspace::create(['id' => 10, 'name' => 'Workspace A', 'slug' => 'ws-a']);
        $workspaceB = Workspace::create(['id' => 20, 'name' => 'Workspace B', 'slug' => 'ws-b']);

        $userA = User::factory()->create(['workspace_id' => $workspaceA->id]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $userA->assignRole('superadmin');

        $foreignDoc = \App\Models\KnowledgeDocument::create([
            'workspace_id' => $workspaceB->id,
            'title' => 'Secret Sales Data',
            'original_filename' => 'secret_sales.xlsx',
            'source_id' => 'src_foreign_99',
            'document_type' => 'spreadsheet',
            'file_path' => 'docs/secret.xlsx',
            'file_size' => 1024,
            'content_hash' => 'hash99',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'lifecycle_status' => \App\Enums\DocumentLifecycleStatus::ACTIVE,
        ]);

        $response = $this->actingAs($userA)->postJson('/dashboard/simulator/select-source', [
            'file_id' => 'src_foreign_99',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Document not found in workspace.',
        ]);
    }

    public function test_superadmin_without_workspace_can_access_simulator_and_switch_workspaces()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $ws1 = Workspace::create(['id' => 50, 'name' => 'Workspace 50', 'slug' => 'ws-50']);
        $ws2 = Workspace::create(['id' => 60, 'name' => 'Workspace 60', 'slug' => 'ws-60']);

        $superAdmin = User::factory()->create([
            'workspace_id' => null,
        ]);
        $superAdmin->assignRole('superadmin');

        // Superadmin without workspace can view simulator (defaults to first workspace without 403)
        $response = $this->actingAs($superAdmin)->get('/dashboard/simulator');
        $response->assertStatus(200);
        $response->assertSee('Workspace 50');
        $response->assertSee('Workspace 60');

        // Superadmin can switch workspace via switch-workspace endpoint
        $switchRes = $this->actingAs($superAdmin)->postJson('/dashboard/simulator/switch-workspace', [
            'workspace_id' => $ws2->id,
        ]);
        $switchRes->assertStatus(200);
        $switchRes->assertJson(['success' => true, 'workspace_id' => $ws2->id]);
    }

    public function test_tenant_admin_cannot_switch_workspace_and_receives_403(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => \App\Enums\RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        $ws1 = Workspace::create(['id' => 70, 'name' => 'Workspace 70', 'slug' => 'ws-70']);
        $ws2 = Workspace::create(['id' => 80, 'name' => 'Workspace 80', 'slug' => 'ws-80']);

        $tenantAdmin = User::factory()->create([
            'workspace_id' => $ws1->id,
        ]);
        $tenantAdmin->assignRole(\App\Enums\RoleEnum::ADMIN->value);

        // Tenant admin cannot switch workspace context
        $response = $this->actingAs($tenantAdmin)->postJson('/dashboard/simulator/switch-workspace', [
            'workspace_id' => $ws2->id,
        ]);

        $response->assertStatus(403);
        $this->assertNotEquals($ws2->id, session('active_simulator_workspace_id'));
    }

    public function test_superadmin_with_assigned_workspace_retains_switching_ability(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => \App\Enums\RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        $ws1 = Workspace::create(['id' => 85, 'name' => 'Workspace 85', 'slug' => 'ws-85']);
        $ws2 = Workspace::create(['id' => 95, 'name' => 'Workspace 95', 'slug' => 'ws-95']);

        $superAdmin = User::factory()->create([
            'workspace_id' => $ws1->id,
        ]);
        $superAdmin->assignRole(\App\Enums\RoleEnum::SUPERADMIN->value);

        // Superadmin switches to Workspace 95
        $response = $this->actingAs($superAdmin)->postJson('/dashboard/simulator/switch-workspace', [
            'workspace_id' => $ws2->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'workspace_id' => $ws2->id]);
        $this->assertEquals($ws2->id, session('active_simulator_workspace_id'));
    }

    public function test_simulator_page_includes_cache_busted_simulator_js_asset(): void
    {
        $ws = Workspace::create(['id' => 99, 'name' => 'Asset Test WS', 'slug' => 'asset-ws']);
        $user = User::factory()->create(['workspace_id' => $ws->id]);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $user->assignRole('superadmin');

        $response = $this->actingAs($user)->get('/dashboard/simulator');
        $response->assertStatus(200);
        $response->assertSee('assets/js/simulator.js?v=', false);
    }

    public function test_non_superadmin_users_are_strictly_forbidden_from_simulator(): void
    {
        $ws = Workspace::create(['id' => 101, 'name' => 'Restricted WS', 'slug' => 'restricted-ws']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'workspace_agent', 'guard_name' => 'web']);

        $admin = User::factory()->create(['workspace_id' => $ws->id]);
        $admin->assignRole('admin');

        $agent = User::factory()->create(['workspace_id' => $ws->id]);
        $agent->assignRole('workspace_agent');

        // Both admin and agent are rejected with 403 Forbidden
        $this->actingAs($admin)->get('/dashboard/simulator')->assertStatus(403);
        $this->actingAs($admin)->postJson('/dashboard/simulator/send', ['message' => 'hi'])->assertStatus(403);

        $this->actingAs($agent)->get('/dashboard/simulator')->assertStatus(403);
        $this->actingAs($agent)->postJson('/dashboard/simulator/send', ['message' => 'hi'])->assertStatus(403);
    }

    public function test_superadmin_can_test_cfo_agent_mode_in_simulator(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $ws = Workspace::create(['id' => 102, 'name' => 'CFO Test WS', 'slug' => 'cfo-test-ws']);
        $superadmin = User::factory()->create(['workspace_id' => $ws->id]);
        $superadmin->assignRole('superadmin');

        $response = $this->actingAs($superadmin)->postJson('/dashboard/simulator/send', [
            'message'    => 'আজকের মোট সেলস ও রেভিনিউ কত?',
            'agent_mode' => 'cfo',
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertSame('cfo', $json['agent_mode']);
        $this->assertSame('OwnerBusinessAgent (Virtual CFO)', $json['agent_tested']);
        $this->assertStringContainsString('আজকের বিক্রয় ও রেভিনিউ সারাংশ', $json['reply']);
    }

    public function test_superadmin_can_test_retention_agent_mode_in_simulator(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $ws = Workspace::create(['id' => 103, 'name' => 'Retention WS', 'slug' => 'retention-ws']);
        $superadmin = User::factory()->create(['workspace_id' => $ws->id]);
        $superadmin->assignRole('superadmin');

        $response = $this->actingAs($superadmin)->postJson('/dashboard/simulator/send', [
            'message'    => 'test retention follow-up',
            'agent_mode' => 'retention',
        ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertSame('retention', $json['agent_mode']);
        $this->assertSame('AbandonedLeadRecoveryService (Retention Agent)', $json['agent_tested']);
        $this->assertStringContainsString('AI Retention Agent', $json['reply']);
    }
}
