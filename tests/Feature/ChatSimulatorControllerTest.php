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
}
