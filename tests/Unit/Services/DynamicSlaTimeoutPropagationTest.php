<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\AI\DTOs\AgentContext;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Retrieval\RetrievalClient;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicSlaTimeoutPropagationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        AgentContext::clear();
        parent::tearDown();
    }

    public function test_analytics_client_uses_default_timeout_when_no_agent_context_active(): void
    {
        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8001', timeout: 25);
        $this->assertEquals(25.0, $client->effectiveTimeout());
    }

    public function test_analytics_client_bounds_timeout_to_remaining_sla_budget(): void
    {
        $workspace = Workspace::create(['name' => 'Timeout WS', 'slug' => 'timeout-ws-' . uniqid()]);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'timeout-' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);

        $context = AgentContext::forUser(
            user: $user,
            workspaceId: $workspace->id,
            timeoutSeconds: 3.5,
        );

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8001', timeout: 30);

        AgentContext::withContext($context, function () use ($client) {
            $effective = $client->effectiveTimeout();
            $this->assertLessThanOrEqual(3.5, $effective);
            $this->assertGreaterThanOrEqual(0.1, $effective);
        });

        // After withContext, ambient context is cleared
        $this->assertEquals(30.0, $client->effectiveTimeout());
    }

    public function test_retrieval_client_bounds_timeout_to_remaining_sla_budget(): void
    {
        $workspace = Workspace::create(['name' => 'Retrieval WS', 'slug' => 'retrieval-ws-' . uniqid()]);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'retrieval-' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);

        $context = AgentContext::forUser(
            user: $user,
            workspaceId: $workspace->id,
            timeoutSeconds: 2.0,
        );

        $client = new RetrievalClient(baseUrl: 'http://127.0.0.1:8002', timeout: 15);

        AgentContext::withContext($context, function () use ($client) {
            $effective = $client->effectiveTimeout();
            $this->assertLessThanOrEqual(2.0, $effective);
            $this->assertGreaterThanOrEqual(0.1, $effective);
        });

        $this->assertEquals(15.0, $client->effectiveTimeout());
    }

    public function test_timeout_never_drops_below_floor_even_if_sla_budget_exhausted(): void
    {
        $scc = ServerControlledContext::forSystem(1, ServerControlledContext::PURPOSE_ANALYTICS_REPORTING);
        // Create context with 0.001s timeout
        $context = new AgentContext(serverControlledContext: $scc, timeoutSeconds: 0.001);

        // Sleep 5ms to guarantee expiration
        usleep(5000);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8001', timeout: 30);

        AgentContext::withContext($context, function () use ($client) {
            $effective = $client->effectiveTimeout();
            $this->assertEquals(0.1, $effective);
        });
    }

    public function test_http_pending_request_bounds_timeout_and_connect_timeout(): void
    {
        $workspace = Workspace::create(['name' => 'Pending WS', 'slug' => 'pending-ws-' . uniqid()]);
        $user = User::create([
            'name' => 'Owner',
            'email' => 'pending-' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
            'workspace_id' => $workspace->id,
            'role' => 'owner',
        ]);

        $context = AgentContext::forUser(
            user: $user,
            workspaceId: $workspace->id,
            timeoutSeconds: 1.5,
        );

        $client = new class(baseUrl: 'http://127.0.0.1:8001', timeout: 30) extends AnalyticsClient {
            public function exposeHttp(): \Illuminate\Http\Client\PendingRequest
            {
                return $this->http();
            }
        };

        AgentContext::withContext($context, function () use ($client) {
            $req = $client->exposeHttp();
            $options = $req->getOptions();
            $this->assertLessThanOrEqual(1.5, $options['timeout']);
            $this->assertLessThanOrEqual(1.5, $options['connect_timeout']);
            $this->assertGreaterThanOrEqual(0.1, $options['timeout']);
            $this->assertGreaterThanOrEqual(0.1, $options['connect_timeout']);
        });
    }
}
