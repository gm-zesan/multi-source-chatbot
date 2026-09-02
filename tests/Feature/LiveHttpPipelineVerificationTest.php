<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\CustomerSupportAgent;
use App\Enums\FaqLifecycleStatus;
use App\Events\AITelemetryRecorded;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LiveHttpPipelineVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;
    private FAQCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Main Workspace', 'slug' => 'main']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name'         => 'Zesan Admin',
            'email'        => 'zesan@gmail.com',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->admin->assignRole('superadmin');

        $this->category = FAQCategory::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Order & Delivery',
            'slug'         => 'order-delivery',
            'is_active'    => true,
        ]);
    }

    /**
     * 1. Test FAQ Index Page renders with Lifecycle Badges, DataTables and Retry button.
     */
    public function test_faq_index_page_renders_lifecycle_badges_and_retry_actions(): void
    {
        // Create FAQs in different lifecycle states
        FAQ::create([
            'workspace_id'     => $this->workspace->id,
            'category_id'      => $this->category->id,
            'question'         => 'What is the standard return window?',
            'answer'           => 'Items can be returned within 7 days with receipt.',
            'lifecycle_status' => FaqLifecycleStatus::ACTIVE,
            'is_active'        => true,
        ]);

        FAQ::create([
            'workspace_id'     => $this->workspace->id,
            'category_id'      => $this->category->id,
            'question'         => 'Failed sync policy test question?',
            'answer'           => 'This item failed vector sync.',
            'lifecycle_status' => FaqLifecycleStatus::SYNC_FAILED,
            'is_active'        => false,
            'sync_error'       => 'Connection to Typesense host failed',
        ]);

        // 1. Initial Page Load
        $response = $this->actingAs($this->admin)->get('/dashboard/faqs');

        $response->assertOk()
            ->assertViewIs('admin.faqs.index')
            ->assertSee('table-card', false)
            ->assertSee('data-table', false)
            ->assertSee('FAQs', false);

        // 2. DataTables AJAX Load
        $ajaxResponse = $this->actingAs($this->admin)->getJson('/dashboard/faqs', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        $ajaxResponse->assertOk()
            ->assertJsonStructure(['data']);

        $jsonData = json_encode($ajaxResponse->json('data'));
        $this->assertStringContainsString('Active', $jsonData);
        $this->assertStringContainsString('Sync Failed', $jsonData);
        $this->assertStringContainsString('"has_failed":true', $jsonData);
    }

    /**
     * 2. Test Live Chat Simulator Page HTML Layout has Left Chat & Right Decision Trace.
     */
    public function test_simulator_page_renders_left_chat_and_right_turn_decision_trace(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard/simulator');

        $response->assertOk()
            ->assertViewIs('admin.simulator')
            ->assertSee('chat-card', false)
            ->assertSee('turnDecisionTraceCard', false)
            ->assertSee('Turn Decision Trace', false)
            ->assertSee('traceTurnBadge', false)
            ->assertSee('traceRouteBadge', false)
            ->assertSee('traceMemoryBadge', false)
            ->assertSee('traceContextSignal', false)
            ->assertSee('traceRetrievalSummary', false)
            ->assertSee('traceGateBadge', false)
            ->assertSee('latRouter', false)
            ->assertSee('latRetrieval', false)
            ->assertSee('latLlm', false)
            ->assertSee('latTotal', false);
    }

    /**
     * 3. Test Multi-Turn Simulator API returns Turn Decision Trace, latencies, and scrubs secrets.
     */
    public function test_simulator_multiturn_flow_returns_complete_decision_trace(): void
    {
        CustomerSupportAgent::fake([
            'পণ্য ফেরত দেওয়ার জন্য ক্রয় রসিদসহ ৭ দিনের মধ্যে আবেদন করতে হবে।',
            'অর্ডার ট্র্যাক করতে ইনভয়েসে থাকা ট্র্যাকিং কোডটি ব্যবহার করুন।',
        ]);

        // Turn 1
        $turn1Response = $this->actingAs($this->admin)->postJson('/dashboard/simulator/send', [
            'message' => 'রিটার্ন কত দিনে করতে হবে?',
        ]);

        $turn1Response->assertOk()
            ->assertJson([
                'success' => true,
                'query'   => 'রিটার্ন কত দিনে করতে হবে?',
                'reply'   => 'পণ্য ফেরত দেওয়ার জন্য ক্রয় রসিদসহ ৭ দিনের মধ্যে আবেদন করতে হবে।',
            ])
            ->assertJsonStructure([
                'decision_trace' => [
                    'query',
                    'route',
                    'route_confidence',
                    'memory_decision',
                    'contextual_signal',
                    'retrieval_summary' => [
                        'hits_count',
                        'top_score',
                        'top_doc_type',
                        'top_question',
                    ],
                    'answerability_status',
                    'answerability_score',
                    'grounded_hit_count',
                    'llm_generation' => [
                        'provider',
                        'model',
                        'status',
                    ],
                    'latency_breakdown' => [
                        'router_ms',
                        'retrieval_ms',
                        'llm_ms',
                        'total_ms',
                    ],
                ],
            ]);

        // Security assertion: no API keys or raw prompts in JSON
        $rawJson = json_encode($turn1Response->json());
        $this->assertStringNotContainsString('sk-', $rawJson);
        $this->assertStringNotContainsString('api_key', $rawJson);
        $this->assertStringNotContainsString('You are an expert E-Commerce', $rawJson);

        // Turn 2
        $turn2Response = $this->actingAs($this->admin)->postJson('/dashboard/simulator/send', [
            'message' => 'আমার অর্ডারের ডেলিভারি স্ট্যাটাস কিভাবে চেক করব?',
        ]);

        $turn2Response->assertOk()
            ->assertJson([
                'success' => true,
                'query'   => 'আমার অর্ডারের ডেলিভারি স্ট্যাটাস কিভাবে চেক করব?',
                'reply'   => 'অর্ডার ট্র্যাক করতে ইনভয়েসে থাকা ট্র্যাকিং কোডটি ব্যবহার করুন।',
            ]);

        $this->assertNotEmpty($turn2Response->json('decision_trace.latency_breakdown'));
    }

    /**
     * 4. Test Observability Dashboard renders Exact FAQ Table design, percentiles, and KPI cards.
     */
    public function test_observability_dashboard_renders_faq_table_and_metrics(): void
    {
        $channel = Channel::create(['slug' => 'web', 'name' => 'Web Chat', 'is_active' => true]);
        $account = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $this->workspace->id,
            'name'         => 'Web Account',
            'external_id'  => 'ext_web',
            'access_token' => 'tok_web',
        ]);

        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_1',
            'customer_name'      => 'Karim Hossain',
            'status'             => 'open',
            'last_direction'     => 'outbound',
        ]);

        // Seed 3 outbound messages with telemetry metadata
        foreach ([145.0, 260.0, 480.0] as $lat) {
            $conversation->messages()->create([
                'direction' => 'outbound',
                'type'      => 'text',
                'body'      => "Response delivered in {$lat}ms",
                'metadata'  => [
                    'route'                  => 'knowledge',
                    'total_time_ms'          => $lat,
                    'answerability_decision' => [
                        'status' => 'confident',
                    ],
                ],
            ]);
        }

        $response = $this->actingAs($this->admin)->get('/dashboard/observability');

        $response->assertOk()
            ->assertViewIs('admin.observability.index')
            ->assertSee('table-card', false)
            ->assertSee('table-header', false)
            ->assertSee('data-table', false)
            ->assertSee('Total AI Replies', false)
            ->assertSee('P50 Response Time', false)
            ->assertSee('P95 Tail Latency', false)
            ->assertSee('Inspect Trace', false)
            ->assertSee('Karim Hossain', false);
    }
}
