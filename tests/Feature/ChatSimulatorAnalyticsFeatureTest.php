<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatSimulatorAnalyticsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Main Workspace', 'slug' => 'main']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->admin->assignRole('superadmin');
    }

    public function test_simulator_send_dispatches_analytics_query_end_to_end(): void
    {
        \App\AI\LLM\LLMClient::fake([
            '{"route": "ANALYTICS", "confidence": 0.99, "reason": "test"}'
        ]);

        Http::fake([
            '*/analytics/query' => Http::response([
                'success' => true,
                'engine' => 'semantic',
                'intent' => 'business_analytics',
                'report' => '### Business Analytics Mock\n\nTotal cashin is $500',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('simulator.send'), [
                'message' => 'Aj koto cashin hoise?',
            ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals('analytics', $json['route']);
        $this->assertArrayHasKey('reply', $json);
        $this->assertStringContainsString('Business Analytics', $json['reply']);
        $this->assertStringNotContainsString('Service Unavailable', $json['reply']);
    }
}
