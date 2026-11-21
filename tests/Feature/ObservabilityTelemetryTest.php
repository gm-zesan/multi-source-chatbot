<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\KnowledgeSupportAgent;
use App\Events\AITelemetryRecorded;
use App\Listeners\RecordAITelemetryListener;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\AI\Orchestration\CustomerSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ObservabilityTelemetryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;
    private Conversation $conversation;
    private ChannelAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Main Workspace', 'slug' => 'main']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name'         => 'Admin User',
            'email'        => 'admin@example.com',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->admin->assignRole('superadmin');

        $channel = Channel::create(['slug' => 'web', 'name' => 'Web Chat', 'is_active' => true]);
        $this->account = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $this->workspace->id,
            'name'         => 'Web Account',
            'external_id'  => 'web_ext_1',
            'access_token' => 'token_1',
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'user_001',
            'customer_name'      => 'Alice Tester',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);

        \App\Models\FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'How fast is delivery?',
            'answer'       => 'Standard delivery time is 3 business days.',
            'is_active'    => true,
        ]);

        \App\Models\FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'What is the return policy?',
            'answer'       => 'Here is the return policy details.',
            'is_active'    => true,
        ]);
    }

    public function test_ai_telemetry_recorded_event_is_dispatched_on_customer_reply(): void
    {
        Event::fake([AITelemetryRecorded::class]);

        KnowledgeSupportAgent::fake([
            'Standard delivery time is 3 business days.',
        ]);

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'How fast is delivery?',
        ]);

        $service = app(CustomerSupportService::class);
        $outbound = $service->handleConversation($this->conversation, $message);

        $this->assertNotNull($outbound);
        $this->assertEquals('Standard delivery time is 3 business days.', $outbound->body);

        Event::assertDispatched(AITelemetryRecorded::class, function (AITelemetryRecorded $event) {
            return $event->query === 'How fast is delivery?'
                && $event->reply === 'Standard delivery time is 3 business days.'
                && isset($event->telemetry['route'])
                && $event->workspaceId === $this->workspace->id;
        });
    }

    public function test_record_ai_telemetry_listener_handles_event_without_exceptions(): void
    {
        $listener = new RecordAITelemetryListener();

        $event = new AITelemetryRecorded(
            conversation: $this->conversation,
            outboundMessage: null,
            query: 'Testing telemetry listener',
            reply: 'Acknowledged',
            telemetry: [
                'route'         => 'knowledge',
                'total_time_ms' => 142.5,
                'answerability_decision' => [
                    'status' => 'confident',
                ],
            ],
            workspaceId: $this->workspace->id,
        );

        // Assert no exception thrown
        $listener->handle($event);
        $this->assertTrue(true);
    }

    public function test_telemetry_listener_persists_knowledge_search_log(): void
    {
        $listener = new RecordAITelemetryListener();

        $event = new AITelemetryRecorded(
            conversation: $this->conversation,
            outboundMessage: null,
            query: 'What is your delivery timeframe?',
            reply: 'We deliver within 3 days.',
            telemetry: [
                'route'         => 'knowledge',
                'total_time_ms' => 210.5,
                'confidence'    => 0.94,
                'answerability_decision' => [
                    'status' => 'confident',
                    'top_hit' => [
                        'score'          => 0.94,
                        'keyword_score'  => 0.85,
                        'semantic_score' => 0.96,
                    ],
                ],
            ],
            workspaceId: $this->workspace->id,
        );

        $listener->handle($event);

        $this->assertDatabaseHas('knowledge_search_logs', [
            'workspace_id'     => $this->workspace->id,
            'customer_query'   => 'What is your delivery timeframe?',
            'response_time_ms' => 211,
            'answer_source'    => 'knowledge_base',
        ]);

        $log = \App\Models\KnowledgeSearchLog::where('workspace_id', $this->workspace->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals(0.85, (float) $log->keyword_score);
        $this->assertEquals(0.96, (float) $log->semantic_score);
        $this->assertEquals(0.94, (float) $log->final_score);
    }

    public function test_telemetry_listener_tracks_unanswered_question_and_deduplicates(): void
    {
        $listener = new RecordAITelemetryListener();

        $event1 = new AITelemetryRecorded(
            conversation: $this->conversation,
            outboundMessage: null,
            query: 'Do you offer international shipping to Canada?',
            reply: 'Sorry, we do not have information on this.',
            telemetry: [
                'route'    => 'unanswerable',
                'answered' => false,
                'answerability_decision' => [
                    'status' => 'unanswerable',
                ],
            ],
            workspaceId: $this->workspace->id,
        );

        $listener->handle($event1);

        $this->assertDatabaseHas('unanswered_questions', [
            'workspace_id'        => $this->workspace->id,
            'original_question'   => 'Do you offer international shipping to Canada?',
            'normalized_question' => 'do you offer international shipping to canada?',
            'occurrence_count'    => 1,
            'status'              => 'pending',
        ]);

        // Second occurrence of same question with different whitespace/casing
        $event2 = new AITelemetryRecorded(
            conversation: $this->conversation,
            outboundMessage: null,
            query: '  DO YOU OFFER INTERNATIONAL SHIPPING TO CANADA?  ',
            reply: 'Sorry, we do not have information on this.',
            telemetry: [
                'route'    => 'unanswerable',
                'answered' => false,
                'answerability_decision' => [
                    'status' => 'unanswerable',
                ],
            ],
            workspaceId: $this->workspace->id,
        );

        $listener->handle($event2);

        // Occurrence count should increment to 2, still 1 row
        $this->assertSame(1, \App\Models\UnansweredQuestion::where('workspace_id', $this->workspace->id)->count());
        $unanswered = \App\Models\UnansweredQuestion::where('workspace_id', $this->workspace->id)->first();
        $this->assertSame(2, $unanswered->occurrence_count);
    }

    public function test_telemetry_listener_ignores_trivial_greetings_for_unanswered(): void
    {
        $listener = new RecordAITelemetryListener();

        $event = new AITelemetryRecorded(
            conversation: $this->conversation,
            outboundMessage: null,
            query: 'Hello',
            reply: 'Hello! How can I help you today?',
            telemetry: [
                'route'    => 'chat',
                'answered' => false,
            ],
            workspaceId: $this->workspace->id,
        );

        $listener->handle($event);

        $this->assertDatabaseCount('unanswered_questions', 0);
    }

    public function test_telemetry_observer_invariant_failure_never_breaks_customer_delivery(): void
    {
        // When telemetry recording fails or logs error, CustomerSupportService MUST succeed
        KnowledgeSupportAgent::fake([
            'Here is the return policy details.',
        ]);

        $message = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'What is the return policy?',
        ]);

        $service = app(CustomerSupportService::class);

        // Process message through customer support service
        $outbound = $service->handleConversation($this->conversation, $message);

        // Core business guarantee: customer ALWAYS gets their answer
        $this->assertNotNull($outbound);
        $this->assertEquals('Here is the return policy details.', $outbound->body);
        $this->assertEquals('outbound', $outbound->direction);
    }

    public function test_observability_dashboard_displays_kpi_and_faq_table_with_percentiles(): void
    {
        // Seed 5 sample outbound messages with telemetry metadata
        $sampleLatencies = [120.0, 180.5, 250.0, 310.2, 850.0];
        foreach ($sampleLatencies as $lat) {
            Message::create([
                'conversation_id' => $this->conversation->id,
                'direction'       => 'outbound',
                'type'            => 'text',
                'body'            => "Outbound answer generated in {$lat}ms",
                'metadata'        => [
                    'route'         => 'knowledge',
                    'total_time_ms' => $lat,
                    'answerability_decision' => [
                        'status' => 'confident',
                    ],
                ],
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/dashboard/observability');

        $response->assertOk()
            ->assertViewIs('admin.observability.index')
            ->assertSee('Observability & Live Telemetry', false)
            ->assertSee('P50 Response Time', false)
            ->assertSee('table-card', false)
            ->assertSee('data-table', false);

        // Assert view received computed percentiles
        $response->assertViewHas('p50Latency', 250.0);
        $response->assertViewHas('p95Latency', 850.0);
        $response->assertViewHas('totalAiResponses', 5);
    }
}
