<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Decision\UnifiedOrchestrator;
use App\Models\KnowledgeSearchLog;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CanaryConcurrencyEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private UnifiedOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name'      => 'Concurrency Test Merchant',
            'slug'      => 'concurrency-test-merchant',
            'currency'  => 'BDT',
            'is_active' => true,
            'settings'  => [
                'ai_canary_enabled' => true,
                'ai_kill_switch'    => false,
            ],
        ]);

        config(['ai.rollout_mode' => 'limited_canary']);
        config(['ai.canary_workspaces' => [$this->workspace->id]]);
        config(['ai.canary_daily_limit' => 100]);
        config(['ai.canary_max_concurrent_per_workspace' => 5]);
        config(['ai.canary_max_concurrent_global' => 10]);

        $this->orchestrator = app(UnifiedOrchestrator::class);
    }

    public function test_workspace_concurrency_limit_enforced_when_slots_exhausted(): void
    {
        $wsKey = "ai_in_flight_ws_{$this->workspace->id}";

        // Simulate 5 requests already actively in-flight for this workspace
        Cache::put($wsKey, 5, 60);

        $result = $this->orchestrator->process('ডেলিভারি চার্জ কত?', $this->workspace->id);

        $this->assertSame('human_handoff', $result['route']);
        $this->assertFalse($result['answered']);
        $this->assertSame('canary_workspace_concurrency_exceeded', $result['decision_metadata']['halt_reason']);

        // Assert the counter was decremented back to 5 rather than leaking
        $this->assertSame(5, (int) Cache::get($wsKey));
    }

    public function test_global_concurrency_limit_enforced_when_host_slots_exhausted(): void
    {
        $globalKey = 'ai_in_flight_global';

        // Simulate 10 requests already actively in-flight globally across all merchants
        Cache::put($globalKey, 10, 60);

        $result = $this->orchestrator->process('ডেলিভারি চার্জ কত?', $this->workspace->id);

        $this->assertSame('human_handoff', $result['route']);
        $this->assertFalse($result['answered']);
        $this->assertSame('canary_global_concurrency_exceeded', $result['decision_metadata']['halt_reason']);

        // Assert global counter was restored to 10
        $this->assertSame(10, (int) Cache::get($globalKey));
    }

    public function test_concurrency_slots_properly_released_after_execution(): void
    {
        $wsKey = "ai_in_flight_ws_{$this->workspace->id}";
        $globalKey = 'ai_in_flight_global';

        Cache::put($wsKey, 0, 60);
        Cache::put($globalKey, 0, 60);

        $result = $this->orchestrator->process('ডেলিভারি চার্জ কত?', $this->workspace->id);

        // After completion, both in-flight counters must return to 0
        $this->assertSame(0, (int) Cache::get($wsKey));
        $this->assertSame(0, (int) Cache::get($globalKey));
    }

    public function test_cache_failure_during_concurrency_check_fails_closed(): void
    {
        // Disable daily quota check to isolate concurrency check
        config(['ai.canary_daily_limit' => 0]);

        Cache::shouldReceive('add')->andReturn(true);
        Cache::shouldReceive('increment')
            ->andThrow(new \RuntimeException('Redis connection lost'));

        $result = $this->orchestrator->process('হ্যালো', $this->workspace->id);

        $this->assertSame('human_handoff', $result['route']);
        $this->assertFalse($result['answered']);
        $this->assertSame('canary_concurrency_cache_failure', $result['decision_metadata']['halt_reason']);
    }

    public function test_quota_cold_start_recovers_from_database_records(): void
    {
        $todayUtc = now('UTC')->format('Y-m-d');
        $trafficKey = "ai_canary_traffic_{$this->workspace->id}_{$todayUtc}";

        // Simulate 7 persistent database records recorded today
        for ($i = 0; $i < 7; $i++) {
            KnowledgeSearchLog::create([
                'workspace_id'     => $this->workspace->id,
                'customer_query'   => "Test query {$i}",
                'created_at'       => now('UTC'),
                'response_time_ms' => 100,
                'final_score'      => 0.9,
                'answer_source'    => 'knowledge_base',
            ]);
        }

        // Simulate cache eviction (key does not exist)
        Cache::forget($trafficKey);
        $this->assertFalse(Cache::has($trafficKey));

        // When a request comes in, the counter must initialize from DB count (7) and increment to 8
        $result = $this->orchestrator->process('ডেলিভারি চার্জ কত?', $this->workspace->id);

        $this->assertSame(8, (int) Cache::get($trafficKey));
    }

    public function test_quota_exhaustion_boundary_race_condition(): void
    {
        $todayUtc = now('UTC')->format('Y-m-d');
        $trafficKey = "ai_canary_traffic_{$this->workspace->id}_{$todayUtc}";

        // Set quota to 99 / 100 (exactly 1 remaining slot)
        Cache::put($trafficKey, 99, 3600);

        // First request consumes the 100th slot (allowed)
        $res1 = $this->orchestrator->process('ডেলিভারি চার্জ কত?', $this->workspace->id);
        $this->assertNotSame('canary_daily_quota_exceeded', $res1['decision_metadata']['halt_reason'] ?? null);
        $this->assertSame(100, (int) Cache::get($trafficKey));

        // Subsequent request is immediately blocked (exceeds 100)
        $res2 = $this->orchestrator->process('ডেলিভারি চার্জ কত?', $this->workspace->id);
        $this->assertSame('human_handoff', $res2['route']);
        $this->assertSame('canary_daily_quota_exceeded', $res2['decision_metadata']['halt_reason']);
        // Consumed quota must never exceed daily limit (strictly 100, never 101)
        $this->assertSame(100, (int) Cache::get($trafficKey));
    }

    public function test_concurrency_rejected_request_does_not_consume_quota(): void
    {
        $todayUtc = now('UTC')->format('Y-m-d');
        $trafficKey = "ai_canary_traffic_{$this->workspace->id}_{$todayUtc}";
        $wsKey = "ai_in_flight_ws_{$this->workspace->id}";

        Cache::put($trafficKey, 10, 3600);
        Cache::put($wsKey, 5, 60);

        $res = $this->orchestrator->process('ডেলিভারি চার্জ কত?', $this->workspace->id);

        $this->assertSame('human_handoff', $res['route']);
        $this->assertSame('canary_workspace_concurrency_exceeded', $res['decision_metadata']['halt_reason']);

        // Daily quota must remain untouched at 10
        $this->assertSame(10, (int) Cache::get($trafficKey));
        // Concurrency counter must remain at 5
        $this->assertSame(5, (int) Cache::get($wsKey));
    }

    public function test_third_merchant_experience_when_global_slots_saturated(): void
    {
        // Onboard a 3rd merchant to the canary
        $merchant3 = Workspace::create([
            'name'      => 'Merchant 3 Pilot',
            'slug'      => 'merchant-3-pilot',
            'currency'  => 'BDT',
            'is_active' => true,
            'settings'  => [
                'ai_canary_enabled' => true,
                'ai_kill_switch'    => false,
            ],
        ]);

        config(['ai.canary_workspaces' => [$this->workspace->id, $merchant3->id]]);

        $channel = \App\Models\Channel::firstOrCreate(['slug' => 'website'], ['name' => 'Website', 'is_active' => true]);
        $channelAccount = \App\Models\ChannelAccount::create([
            'workspace_id' => $merchant3->id,
            'channel_id'   => $channel->id,
            'name'         => 'M3 Web Account',
            'external_id'  => 'm3_ext_123',
            'access_token' => 'test_token_123',
            'is_active'    => true,
        ]);
        $conv = \App\Models\Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'buyer_123',
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'customer_name'      => 'Test Buyer',
        ]);
        $inboundMsg = \App\Models\Message::create([
            'conversation_id' => $conv->id,
            'sender_type'     => 'customer',
            'type'            => 'text',
            'body'            => 'আপনার শপ কোথায়?',
            'direction'       => 'inbound',
        ]);

        $globalKey = 'ai_in_flight_global';
        $todayUtc = now('UTC')->format('Y-m-d');
        $m3TrafficKey = "ai_canary_traffic_{$merchant3->id}_{$todayUtc}";
        $m3WsKey = "ai_in_flight_ws_{$merchant3->id}";

        // Simulate 10 slots saturated globally across WS1 and WS2
        Cache::put($globalKey, 10, 60);

        // Merchant 3 customer asks a question
        $res = $this->orchestrator->process(
            query: 'আপনার শপ কোথায়?',
            workspaceId: $merchant3->id,
            conversation: $conv,
            channelSlug: 'website',
            isCustomer: true,
        );

        // 1. Must fail gracefully to human handoff
        $this->assertSame('human_handoff', $res['route']);
        $this->assertFalse($res['answered']);
        $this->assertSame('canary_global_concurrency_exceeded', $res['decision_metadata']['halt_reason']);
        $this->assertNotEmpty($res['reply']);

        // 2. Customer inbound message must be safely preserved in database
        $this->assertDatabaseHas('messages', [
            'id'              => $inboundMsg->id,
            'conversation_id' => $conv->id,
            'body'            => 'আপনার শপ কোথায়?',
        ]);

        // 3. Must NOT charge quota to Merchant 3
        $this->assertSame(0, (int) Cache::get($m3TrafficKey, 0));

        // 4. Must NOT leak in-flight slots
        $this->assertSame(0, (int) Cache::get($m3WsKey, 0));
        $this->assertSame(10, (int) Cache::get($globalKey));
    }

    public function test_duplicate_release_does_not_corrupt_concurrency_counter(): void
    {
        $wsKey = "ai_in_flight_ws_{$this->workspace->id}";
        $leaseId = 'test_lease_duplicate_check';

        // Use reflection to test atomic methods directly
        $methodAcq = new \ReflectionMethod($this->orchestrator, 'atomicAcquireConcurrencySlot');
        $methodAcq->setAccessible(true);
        $methodRel = new \ReflectionMethod($this->orchestrator, 'atomicReleaseConcurrencySlot');
        $methodRel->setAccessible(true);

        Cache::put($wsKey, 0, 60);

        // Acquire slot with leaseId
        [$allowed, $current] = $methodAcq->invoke($this->orchestrator, $wsKey, 5, 60, $leaseId);
        $this->assertTrue($allowed);
        $this->assertSame(1, $current);

        // First release: counter should become 0
        $methodRel->invoke($this->orchestrator, $wsKey, $leaseId);
        $this->assertSame(0, (int) Cache::get($wsKey, 0));

        // Duplicate release: counter must remain 0, never drop to -1
        $methodRel->invoke($this->orchestrator, $wsKey, $leaseId);
        $this->assertSame(0, (int) Cache::get($wsKey, 0));
    }

    public function test_partial_acquisition_rollback_cleans_up_global_and_quota(): void
    {
        $todayUtc = now('UTC')->format('Y-m-d');
        $trafficKey = "ai_canary_traffic_{$this->workspace->id}_{$todayUtc}";
        $globalKey = 'ai_in_flight_global';
        $wsKey = "ai_in_flight_ws_{$this->workspace->id}";

        Cache::put($trafficKey, 5, 3600);
        Cache::put($globalKey, 2, 60);
        // Pre-fill workspace slots to maximum 5
        Cache::put($wsKey, 5, 60);

        // Dispatched request will acquire global slot (global becomes 3), but fail at workspace slot
        $res = $this->orchestrator->process('ডেলিভারি চার্জ কত?', $this->workspace->id);

        $this->assertSame('human_handoff', $res['route']);
        $this->assertSame('canary_workspace_concurrency_exceeded', $res['decision_metadata']['halt_reason']);

        // Rollback guarantees:
        // 1. Daily quota remains 5 (not charged)
        $this->assertSame(5, (int) Cache::get($trafficKey));
        // 2. Global slots rolled back to 2 (not leaked to 3)
        $this->assertSame(2, (int) Cache::get($globalKey));
        // 3. Workspace slots remained 5
        $this->assertSame(5, (int) Cache::get($wsKey));
    }
}
