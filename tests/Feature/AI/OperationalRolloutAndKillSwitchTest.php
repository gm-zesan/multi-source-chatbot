<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Decision\UnifiedOrchestrator;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalRolloutAndKillSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private UnifiedOrchestrator $orchestrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name'       => 'Canary Test Merchant',
            'slug'       => 'canary-test-merchant',
            'currency'   => 'BDT',
            'is_active'  => true,
            'settings'   => [
                'ai_canary_enabled' => true,
                'ai_kill_switch'    => false,
                'fallback_message'  => 'আমাদের কাস্টমার কেয়ার টিম দ্রুত আপনার সাথে যোগাযোগ করবে।',
            ],
        ]);

        config(['ai.rollout_mode' => 'limited_canary']);
        config(['ai.canary_workspaces' => [$this->workspace->id]]);

        $this->orchestrator = app(UnifiedOrchestrator::class);
    }

    public function test_engine_version_constant_and_config_are_sealed_at_v2_4_rc1(): void
    {
        $this->assertSame('v2.4-RC1', UnifiedOrchestrator::ENGINE_VERSION);
        $this->assertSame('v2.4-RC1', config('ai.engine_version'));
    }

    public function test_kill_switch_instantly_halts_ai_with_zero_llm_execution(): void
    {
        // Engage kill switch for workspace
        $this->workspace->setAiKillSwitch(true);
        $this->assertTrue($this->workspace->isAiKillSwitchActive());
        $this->assertFalse($this->workspace->isAiRolloutEnabled());

        $tStart = microtime(true);
        $response = $this->orchestrator->process(
            query: 'আপনাদের রিটার্ন পলিসি কি?',
            workspaceId: $this->workspace->id,
            channelSlug: 'simulator',
            isCustomer: true
        );
        $elapsedMs = (microtime(true) - $tStart) * 1000;

        // Verify instant execution (< 100ms) with zero LLM/Typesense execution
        $this->assertLessThan(100.0, $elapsedMs, 'Kill switch must halt execution immediately');

        // Verify safe handoff contract
        $this->assertSame('human_handoff', $response['route']);
        $this->assertTrue($response['is_handoff']);
        $this->assertFalse($response['answered']);
        $this->assertSame('আমাদের কাস্টমার কেয়ার টিম দ্রুত আপনার সাথে যোগাযোগ করবে।', $response['reply']);
        $this->assertSame('v2.4-RC1', $response['decision_metadata']['engine_version']);
        $this->assertTrue($response['decision_metadata']['kill_switch_active']);
        $this->assertSame('workspace_kill_switch_active', $response['decision_metadata']['halt_reason']);
    }

    public function test_workspace_not_in_canary_rollout_is_safely_halted(): void
    {
        config(['ai.rollout_mode' => 'limited_canary']);
        config(['ai.canary_workspaces' => []]);

        $nonCanaryWorkspace = Workspace::create([
            'name'       => 'Non Canary Merchant',
            'slug'       => 'non-canary-merchant',
            'currency'   => 'BDT',
            'is_active'  => true,
            'settings'   => [
                'ai_canary_enabled' => false,
                'ai_kill_switch'    => false,
            ],
        ]);

        $this->assertFalse($nonCanaryWorkspace->isAiRolloutEnabled());

        $response = $this->orchestrator->process(
            query: 'আমার অর্ডার স্ট্যাটাস জানতে চাই',
            workspaceId: $nonCanaryWorkspace->id,
            channelSlug: 'whatsapp',
            isCustomer: true
        );

        $this->assertSame('human_handoff', $response['route']);
        $this->assertTrue($response['is_handoff']);
        $this->assertFalse($response['answered']);
        $this->assertSame('workspace_not_in_canary_rollout', $response['decision_metadata']['halt_reason']);
    }

    public function test_workspace_in_canary_rollout_proceeds_normally(): void
    {
        config(['ai.rollout_mode' => 'limited_canary']);
        $this->workspace->enableAiCanary(true);

        $this->assertTrue($this->workspace->isAiRolloutEnabled());

        // Process a direct chat greeting (runs fully through Layer 1-5)
        $response = $this->orchestrator->process(
            query: 'হাই কেমন আছেন',
            workspaceId: $this->workspace->id,
            channelSlug: 'simulator',
            isCustomer: true
        );

        $this->assertNotSame('human_handoff', $response['route']);
        $this->assertFalse($response['is_handoff']);
        $this->assertSame('v2.4-RC1', $response['decision_metadata']['engine_version']);
    }

    public function test_global_disabled_mode_halts_all_workspaces(): void
    {
        config(['ai.rollout_mode' => 'disabled']);

        $response = $this->orchestrator->process(
            query: 'হ্যালো',
            workspaceId: $this->workspace->id,
            channelSlug: 'simulator',
            isCustomer: true
        );

        $this->assertSame('human_handoff', $response['route']);
        $this->assertTrue($response['is_handoff']);
        $this->assertSame('ai_engine_globally_disabled', $response['decision_metadata']['halt_reason']);
    }

    public function test_missing_tenant_context_fails_closed_in_canary_mode(): void
    {
        config(['ai.rollout_mode' => 'limited_canary']);

        $response = $this->orchestrator->process(
            query: 'হ্যালো',
            workspaceId: 0, // Missing or invalid workspace
            channelSlug: 'simulator',
            isCustomer: true
        );

        $this->assertSame('human_handoff', $response['route']);
        $this->assertTrue($response['is_handoff']);
        $this->assertFalse($response['answered']);
        $this->assertSame('missing_tenant_context', $response['decision_metadata']['halt_reason']);
    }

    public function test_unknown_workspace_id_fails_closed_in_canary_mode(): void
    {
        config(['ai.rollout_mode' => 'limited_canary']);

        $response = $this->orchestrator->process(
            query: 'হ্যালো',
            workspaceId: 999999, // Nonexistent workspace
            channelSlug: 'simulator',
            isCustomer: true
        );

        $this->assertSame('human_handoff', $response['route']);
        $this->assertTrue($response['is_handoff']);
        $this->assertFalse($response['answered']);
        $this->assertSame('unknown_workspace_unauthorized', $response['decision_metadata']['halt_reason']);
    }

    public function test_workspace_kill_switch_is_honored_even_in_unrestricted_rollout_mode(): void
    {
        config(['ai.rollout_mode' => 'all']);

        $this->workspace->setAiKillSwitch(true);

        $response = $this->orchestrator->process(
            query: 'হ্যালো',
            workspaceId: $this->workspace->id,
            channelSlug: 'simulator',
            isCustomer: true
        );

        $this->assertSame('human_handoff', $response['route']);
        $this->assertTrue($response['is_handoff']);
        $this->assertFalse($response['answered']);
        $this->assertSame('workspace_kill_switch_active', $response['decision_metadata']['halt_reason']);
    }

    public function test_feedback_and_investigation_commands_trace_conversation(): void
    {
        $channel = Channel::create(['name' => 'Web Widget', 'slug' => 'web_widget', 'driver' => 'web']);
        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Default Web Account',
            'external_id'  => 'web_acc_1',
            'access_token' => 'web_token_123',
            'is_active'    => true,
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'customer_name'      => 'Canary Customer',
            'external_user_id'   => 'user_123',
            'last_direction'     => 'inbound',
        ]);

        $inbound = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'অর্ডার রিটার্ন করতে কতদিন সময় পাবো?',
            'status'          => 'delivered',
        ]);

        $outbound = Message::create([
            'conversation_id' => $conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'পণ্য গ্রহণের ৭ দিনের মধ্যে আপনি রিটার্ন রিকোয়েস্ট করতে পারবেন।',
            'status'          => 'sent',
            'metadata'        => [
                'model'      => 'deepseek-chat',
                'route'      => 'knowledge',
                'confidence' => 0.98,
            ],
        ]);

        // Record quality feedback on outbound message
        $this->artisan('ai:feedback', [
            'message_id' => (string) $outbound->id,
            'rating'     => 'positive',
            '--comment'  => 'Accurate 7-day return policy answer',
            '--agent'    => 'qa_auditor',
        ])
            ->expectsOutputToContain('Successfully recorded AI feedback for Message')
            ->assertExitCode(0);

        // Run investigation command
        $this->artisan('ai:investigate', [
            'identifier' => (string) $conversation->id,
        ])
            ->expectsOutputToContain('ENGINE v2.4-RC1 — EVIDENCE TRACE')
            ->expectsOutputToContain('Canary Customer')
            ->expectsOutputToContain('FEEDBACK: [positive]')
            ->assertExitCode(0);
    }
}
