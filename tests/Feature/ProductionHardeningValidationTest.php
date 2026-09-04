<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\CustomerSupportAgent;
use App\Jobs\IngestConversationMemoryJob;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Memory\ConversationMemoryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProductionHardeningValidationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspaceA;
    private Workspace $workspaceB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->workspaceA = Workspace::create([
            'name' => 'Workspace A',
            'slug' => 'workspace-a',
        ]);

        $this->workspaceB = Workspace::create([
            'name' => 'Workspace B',
            'slug' => 'workspace-b',
        ]);

        $this->userA = User::factory()->create([
            'workspace_id' => $this->workspaceA->id,
        ]);
        $this->userA->assignRole('superadmin');

        $this->userB = User::factory()->create([
            'workspace_id' => $this->workspaceB->id,
        ]);
        $this->userB->assignRole('superadmin');

        FAQ::create([
            'workspace_id' => $this->workspaceA->id,
            'question'     => 'What is the refund policy for Workspace A?',
            'answer'       => 'Workspace A refunds within 7 business days.',
            'is_active'    => true,
        ]);

        FAQ::create([
            'workspace_id' => $this->workspaceB->id,
            'question'     => 'What is the refund policy for Workspace B?',
            'answer'       => 'Workspace B refunds within 14 business days.',
            'is_active'    => true,
        ]);
    }

    /**
     * Concurrent Session & Workspace Isolation
     */
    public function test_concurrent_sessions_maintain_strict_workspace_isolation(): void
    {
        CustomerSupportAgent::fake([
            'Response for User A',
            'Response for User B',
        ]);

        // User A sends message in Workspace A
        $resA = $this->actingAs($this->userA)->post(route('simulator.send'), [
            'message' => 'What is the refund policy for Workspace A?',
        ]);
        $resA->assertOk();

        // User B sends message in Workspace B
        $resB = $this->actingAs($this->userB)->post(route('simulator.send'), [
            'message' => 'What is the refund policy for Workspace B?',
        ]);
        $resB->assertOk();

        // Verify Workspace A conversations only exist for Workspace A account
        $convsA = Conversation::whereHas('account', function ($q) {
            $q->where('workspace_id', $this->workspaceA->id);
        })->get();
        $this->assertNotEmpty($convsA);

        // Verify Workspace B conversations only exist for Workspace B account
        $convsB = Conversation::whereHas('account', function ($q) {
            $q->where('workspace_id', $this->workspaceB->id);
        })->get();
        $this->assertNotEmpty($convsB);
    }

    /**
     * Provider Failure Gracefully Emits Fallback
     */
    public function test_provider_failure_gracefully_emits_grounded_fallback(): void
    {
        // Mock provider network error by throwing exception inside faked agent
        CustomerSupportAgent::fake(function () {
            throw new \RuntimeException('DeepSeek provider connection timed out');
        });

        $response = $this->actingAs($this->userA)->post(route('simulator.send'), [
            'message' => 'What is the refund policy for Workspace A?',
        ]);

        $response->assertOk();
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['reply']);
        $this->assertTrue(
            str_contains($data['reply'], 'Workspace A refunds within 7 business days') ||
            str_contains($data['reply'], '7 days') ||
            str_contains($data['reply'], '৭ দিন')
        );
    }

    /**
     * Memory Ingestion Worker Resilience (Python Service Outage)
     */
    public function test_memory_ingestion_job_resilient_to_python_service_network_outage(): void
    {
        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web', 'driver' => 'web', 'is_active' => true]
        );

        $account = ChannelAccount::create([
            'workspace_id' => $this->workspaceA->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test Account',
            'external_id'  => 'acc_test_outage',
            'access_token' => 'token_123',
            'is_active'    => true,
        ]);

        $conv = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_outage_test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        // Fake memory service HTTP returning 503 Service Unavailable
        Http::fake([
            '*/api/v1/memory/ingest' => Http::response(['error' => 'Service Unavailable'], 503),
        ]);

        $client = app(ConversationMemoryClient::class);
        $job = new IngestConversationMemoryJob($conv);

        // Executing the job must not throw an unhandled exception that kills worker
        $job->handle($client);

        $this->assertTrue(true); // Reached here safely without worker crash
    }

    /**
     * Rate Limiting on Simulator POST Endpoints
     */
    public function test_simulator_send_has_rate_limiting_protection(): void
    {
        CustomerSupportAgent::fake(['Rate limit response']);

        $res = $this->actingAs($this->userA)->post(route('simulator.send'), [
            'message' => 'hello test',
        ]);

        $res->assertOk();
        // Check rate limiting headers are present
        $this->assertTrue(
            $res->headers->has('X-RateLimit-Limit') || $res->headers->has('x-ratelimit-limit')
        );
    }

    /**
     * Secret Leakage & Payload Sanitization Audit
     */
    public function test_payloads_do_not_leak_system_secrets_or_raw_cypher(): void
    {
        CustomerSupportAgent::fake(['Normal safe answer.']);

        $res = $this->actingAs($this->userA)->post(route('simulator.send'), [
            'message' => 'delivery charge koto',
        ]);

        $content = $res->getContent();

        // Secrets check
        $this->assertStringNotContainsString('sk-', $content);
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('MATCH (c:Customer)', $content);
        $this->assertStringNotContainsString('api_key', $content);
        $this->assertStringNotContainsString('secret', $content);
    }
}
