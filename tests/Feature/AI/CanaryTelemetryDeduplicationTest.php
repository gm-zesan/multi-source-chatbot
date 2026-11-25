<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Events\AITelemetryRecorded;
use App\Listeners\RecordAITelemetryListener;
use App\Models\Conversation;
use App\Models\KnowledgeSearchLog;
use App\Models\Message;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class CanaryTelemetryDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private RecordAITelemetryListener $listener;
    private \App\Models\ChannelAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name'      => 'Telemetry Audit Merchant',
            'slug'      => 'telemetry-audit-merchant',
            'currency'  => 'BDT',
            'is_active' => true,
        ]);

        $channel = \App\Models\Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $this->account = \App\Models\ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test Account',
            'external_id'  => 'web_ext_01',
            'access_token' => 'dummy_token',
            'is_active'    => true,
        ]);

        $this->listener = app(RecordAITelemetryListener::class);
    }

    public function test_independent_customers_asking_same_query_are_both_logged(): void
    {
        $turnIdCustomerA = (string) Str::uuid();
        $turnIdCustomerB = (string) Str::uuid();
        $queryText = 'ডেলিভারি চার্জ কত?';

        // Customer A sends query
        $eventA = new AITelemetryRecorded(
            conversation: null,
            outboundMessage: null,
            query: $queryText,
            reply: 'ঢাকা ৬০ টাকা, বাইরে ১২০ টাকা',
            telemetry: [
                'turn_id'        => $turnIdCustomerA,
                'route'          => 'knowledge',
                'confidence'     => 0.95,
                'final_score'    => 0.95,
                'total_time_ms'  => 150.0,
                'answered'       => true,
                'matched_faq_id' => null,
            ],
            workspaceId: $this->workspace->id,
        );

        // Customer B sends identical query 10ms later
        $eventB = new AITelemetryRecorded(
            conversation: null,
            outboundMessage: null,
            query: $queryText,
            reply: 'ঢাকা ৬০ টাকা, বাইরে ১২০ টাকা',
            telemetry: [
                'turn_id'        => $turnIdCustomerB,
                'route'          => 'knowledge',
                'confidence'     => 0.95,
                'final_score'    => 0.95,
                'total_time_ms'  => 160.0,
                'answered'       => true,
                'matched_faq_id' => null,
            ],
            workspaceId: $this->workspace->id,
        );

        $this->listener->handle($eventA);
        $this->listener->handle($eventB);

        // Assert both independent customer requests are persisted without suppression
        $logged = KnowledgeSearchLog::where('workspace_id', $this->workspace->id)
            ->where('customer_query', $queryText)
            ->get();

        $this->assertCount(2, $logged, 'Both independent customer requests must be logged without suppression');
    }

    public function test_duplicate_events_for_same_turn_are_deduplicated_and_update_message_id(): void
    {
        $turnId = (string) Str::uuid();
        $queryText = 'রিটার্ন পলিসি কী?';

        // Event 1 from UnifiedOrchestrator (outboundMessage is null)
        $eventOrchestrator = new AITelemetryRecorded(
            conversation: null,
            outboundMessage: null,
            query: $queryText,
            reply: '৭ দিনের মধ্যে রিপ্লেসমেন্ট',
            telemetry: [
                'turn_id'        => $turnId,
                'route'          => 'knowledge',
                'confidence'     => 0.92,
                'final_score'    => 0.92,
                'total_time_ms'  => 210.0,
                'answered'       => true,
                'matched_faq_id' => null,
            ],
            workspaceId: $this->workspace->id,
        );

        $this->listener->handle($eventOrchestrator);

        $this->assertSame(1, KnowledgeSearchLog::where('workspace_id', $this->workspace->id)->count());
        $firstLog = KnowledgeSearchLog::first();
        $this->assertNull($firstLog->message_id);

        // Create an outbound message
        $conv = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'cust_101',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
        $outboundMsg = Message::create([
            'conversation_id' => $conv->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => '৭ দিনের মধ্যে রিপ্লেসমেন্ট',
            'status'          => 'delivered',
        ]);

        // Event 2 from CustomerSupportService for the same turn (now with outboundMessage)
        $eventService = new AITelemetryRecorded(
            conversation: $conv,
            outboundMessage: $outboundMsg,
            query: $queryText,
            reply: '৭ দিনের মধ্যে রিপ্লেসমেন্ট',
            telemetry: [
                'turn_id'        => $turnId, // Same turn ID
                'route'          => 'knowledge',
                'confidence'     => 0.92,
                'final_score'    => 0.92,
                'total_time_ms'  => 210.0,
                'answered'       => true,
                'matched_faq_id' => null,
            ],
            workspaceId: $this->workspace->id,
        );

        $this->listener->handle($eventService);

        // Assert exactly 1 log exists (not duplicated), and message_id was updated
        $this->assertSame(1, KnowledgeSearchLog::where('workspace_id', $this->workspace->id)->count());
        $updatedLog = KnowledgeSearchLog::first();
        $this->assertSame($outboundMsg->id, $updatedLog->message_id);
    }

    public function test_low_score_knowledge_abstention_is_tracked_in_unanswered_questions(): void
    {
        $turnId = (string) Str::uuid();
        $queryText = 'আপনাদের কাছে কি আইফোন ১৬ প্রো ম্যাক্স পাওয়া যাবে?';
        $abstainReply = 'আপনার জিজ্ঞাসিত iPhone 16 Pro Max বর্তমানে আমাদের স্টকে আছে কি না, তা প্রদত্ত তথ্যে নিশ্চিত করা যাচ্ছে না।';

        $event = new AITelemetryRecorded(
            conversation: null,
            outboundMessage: null,
            query: $queryText,
            reply: $abstainReply,
            telemetry: [
                'turn_id'                => $turnId,
                'route'                  => 'knowledge',
                'confidence'             => 0.2574,
                'final_score'            => 0.2574,
                'total_time_ms'          => 350.0,
                'answered'               => true,
                'answerability_decision' => ['status' => 'ANSWERABLE_GROUNDED'],
                'matched_faq_id'         => null,
            ],
            workspaceId: $this->workspace->id,
        );

        $this->listener->handle($event);

        $unanswered = UnansweredQuestion::where('workspace_id', $this->workspace->id)->first();
        $this->assertNotNull($unanswered, 'Low confidence retrieval with abstention reply must be tracked in UnansweredQuestion');
        $this->assertSame($queryText, $unanswered->original_question);
        $this->assertStringContainsString('0.2574', $unanswered->notes);
    }

    public function test_high_score_knowledge_answer_is_not_tracked_in_unanswered_questions(): void
    {
        $turnId = (string) Str::uuid();
        $queryText = 'ডেলিভারি চার্জ কত?';
        $answerReply = 'ঢাকা সিটির ভেতরে ডেলিভারি চার্জ ৬০ টাকা এবং ঢাকার বাইরে ১২০ টাকা।';

        $event = new AITelemetryRecorded(
            conversation: null,
            outboundMessage: null,
            query: $queryText,
            reply: $answerReply,
            telemetry: [
                'turn_id'        => $turnId,
                'route'          => 'knowledge',
                'confidence'     => 0.9174,
                'final_score'    => 0.9174,
                'total_time_ms'  => 120.0,
                'answered'       => true,
                'matched_faq_id' => null,
            ],
            workspaceId: $this->workspace->id,
        );

        $this->listener->handle($event);

        $this->assertSame(0, UnansweredQuestion::where('workspace_id', $this->workspace->id)->count());
    }

    public function test_cache_failure_during_canary_quota_check_fails_closed(): void
    {
        config(['ai.rollout_mode' => 'limited_canary']);
        config(['ai.canary_workspaces' => [$this->workspace->id]]);
        config(['ai.canary_daily_limit' => 100]);

        $this->workspace->settings = ['ai_canary_enabled' => true, 'ai_kill_switch' => false];
        $this->workspace->save();

        // Simulate cache service exception
        Cache::shouldReceive('add')
            ->andThrow(new \RuntimeException('Redis connection timeout'));

        $orchestrator = app(\App\AI\Decision\UnifiedOrchestrator::class);
        $result = $orchestrator->process('হ্যালো', $this->workspace->id);

        $this->assertSame('human_handoff', $result['route']);
        $this->assertFalse($result['answered']);
        $this->assertSame('canary_quota_cache_failure', $result['decision_metadata']['halt_reason']);
    }

    public function test_multi_channel_ingress_telemetry_recording(): void
    {
        // 1. Messenger Channel
        $fbChannel = \App\Models\Channel::firstOrCreate(['slug' => 'facebook'], ['name' => 'Facebook', 'driver' => 'facebook']);
        $fbAccount = \App\Models\ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $fbChannel->id,
            'name'         => 'FB Page',
            'external_id'  => 'fb_page_001',
            'access_token' => 'dummy_fb_token',
            'is_active'    => true,
        ]);
        $fbConv = Conversation::create([
            'channel_account_id' => $fbAccount->id,
            'external_user_id'   => 'fb_user_1',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $fbTurnId = (string) Str::uuid();
        $this->listener->handle(new AITelemetryRecorded(
            conversation: $fbConv,
            outboundMessage: null,
            query: 'ডেলিভারি চার্জ কত?',
            reply: 'ঢাকা ৬০ টাকা',
            telemetry: [
                'turn_id'       => $fbTurnId,
                'channel'       => 'facebook',
                'route'         => 'knowledge',
                'confidence'    => 0.95,
                'final_score'   => 0.95,
                'total_time_ms' => 180.0,
                'answered'      => true,
            ],
            workspaceId: $this->workspace->id,
        ));

        // 2. Web Widget Channel
        $webTurnId = (string) Str::uuid();
        $webConv = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'web_user_1',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
        $this->listener->handle(new AITelemetryRecorded(
            conversation: $webConv,
            outboundMessage: null,
            query: 'রিটার্ন পলিসি কী?',
            reply: '৭ দিনের মধ্যে রিপ্লেসমেন্ট',
            telemetry: [
                'turn_id'       => $webTurnId,
                'channel'       => 'web_widget',
                'route'         => 'knowledge',
                'confidence'    => 0.88,
                'final_score'   => 0.88,
                'total_time_ms' => 220.0,
                'answered'      => true,
            ],
            workspaceId: $this->workspace->id,
        ));

        // 3. Direct API Call
        $apiTurnId = (string) Str::uuid();
        $this->listener->handle(new AITelemetryRecorded(
            conversation: null,
            outboundMessage: null,
            query: 'অফিস কোথায়?',
            reply: 'বনানী, ঢাকা',
            telemetry: [
                'turn_id'       => $apiTurnId,
                'channel'       => 'direct_api',
                'route'         => 'knowledge',
                'confidence'    => 0.92,
                'final_score'   => 0.92,
                'total_time_ms' => 150.0,
                'answered'      => true,
            ],
            workspaceId: $this->workspace->id,
        ));

        // Verify all 3 channels persisted separate audit logs
        $logs = KnowledgeSearchLog::where('workspace_id', $this->workspace->id)->get();
        $this->assertCount(3, $logs);
        $this->assertTrue($logs->contains('customer_query', 'ডেলিভারি চার্জ কত?'));
        $this->assertTrue($logs->contains('customer_query', 'রিটার্ন পলিসি কী?'));
        $this->assertTrue($logs->contains('customer_query', 'অফিস কোথায়?'));
    }

    public function test_multi_turn_sequential_conversations_log_every_turn_independently(): void
    {
        $conv = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'cust_multi_turn',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $turns = [
            ['query' => 'ডেলিভারি চার্জ কত?', 'reply' => 'ঢাকা ৬০ টাকা', 'score' => 0.95],
            ['query' => 'ক্যাশ অন ডেলিভারি কি সম্ভব?', 'reply' => 'হ্যাঁ, ক্যাশ অন ডেলিভারি রয়েছে', 'score' => 0.91],
            ['query' => 'কত দিনে পাব?', 'reply' => '২-৩ কার্যদিবস', 'score' => 0.89],
        ];

        foreach ($turns as $turn) {
            $turnId = (string) Str::uuid();
            $this->listener->handle(new AITelemetryRecorded(
                conversation: $conv,
                outboundMessage: null,
                query: $turn['query'],
                reply: $turn['reply'],
                telemetry: [
                    'turn_id'       => $turnId,
                    'channel'       => 'web_widget',
                    'route'         => 'knowledge',
                    'confidence'    => $turn['score'],
                    'final_score'   => $turn['score'],
                    'total_time_ms' => 170.0,
                    'answered'      => true,
                ],
                workspaceId: $this->workspace->id,
            ));
        }

        $logs = KnowledgeSearchLog::where('workspace_id', $this->workspace->id)
            ->where('conversation_id', $conv->id)
            ->get();

        $this->assertCount(3, $logs, 'Every turn in a multi-turn conversation must produce an independent search log');
    }

    public function test_rapid_customer_retries_with_identical_query_are_both_logged_across_turns(): void
    {
        $conv = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'cust_retry_user',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        $queryText = 'ডেলিভারি চার্জ কত?';

        // Turn 1
        $turn1Id = (string) Str::uuid();
        $this->listener->handle(new AITelemetryRecorded(
            conversation: $conv,
            outboundMessage: null,
            query: $queryText,
            reply: 'ঢাকা ৬০ টাকা',
            telemetry: [
                'turn_id'       => $turn1Id,
                'channel'       => 'web_widget',
                'route'         => 'knowledge',
                'confidence'    => 0.95,
                'final_score'   => 0.95,
                'total_time_ms' => 150.0,
                'answered'      => true,
            ],
            workspaceId: $this->workspace->id,
        ));

        // Turn 2 (Customer immediately clicks send again or retries)
        $turn2Id = (string) Str::uuid();
        $this->listener->handle(new AITelemetryRecorded(
            conversation: $conv,
            outboundMessage: null,
            query: $queryText,
            reply: 'ঢাকা ৬০ টাকা',
            telemetry: [
                'turn_id'       => $turn2Id,
                'channel'       => 'web_widget',
                'route'         => 'knowledge',
                'confidence'    => 0.95,
                'final_score'   => 0.95,
                'total_time_ms' => 140.0,
                'answered'      => true,
            ],
            workspaceId: $this->workspace->id,
        ));

        $logs = KnowledgeSearchLog::where('workspace_id', $this->workspace->id)
            ->where('conversation_id', $conv->id)
            ->where('customer_query', $queryText)
            ->get();

        $this->assertCount(2, $logs, 'Rapid customer retries with distinct turn IDs must both be preserved in the audit log');
    }
}
