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
            'workspace_id' => $maliciousWorkspace->id // malicious injection attempt
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
}
