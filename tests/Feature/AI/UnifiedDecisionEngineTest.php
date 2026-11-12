<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DeterministicExecutionController;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\DTOs\ToolCall;
use App\AI\Decision\FinalResponseGenerator;
use App\AI\Decision\ToolRegistry;
use App\AI\Decision\UnifiedDecisionEngine;
use App\AI\Decision\UnifiedOrchestrator;
use App\AI\LLM\LLMClient;
use App\AI\LLM\LLMResponse;
use App\AI\Orchestration\CustomerSupportService;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Unified Decision Engine & Orchestrator Feature Test Suite
 *
 * Verifies the consolidated Schema v1.0 decision architecture:
 * 1. Direct Chat (English, Bangla, Banglish)
 * 2. Knowledge (product availability, delivery, FAQ)
 * 3. Analytics (sales, revenue, staff ranking)
 * 4. Ambiguity & Clarification ('kamai kemon holo?', 'ajker hisab dao')
 * 5. Stock collision resolution ('Laptop Pro কি stock e ache?' vs 'স্টক রিপোর্ট দাও')
 * 6. Tool execution, unknown tool rejection, DLP projection
 * 7. Router failure fallback
 * 8. Customer security barrier enforcement
 */
class UnifiedDecisionEngineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private User $staff;
    private Channel $simulatorChannel;
    private ChannelAccount $simulatorAccount;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => RoleEnum::SUPERADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $this->workspace = Workspace::create(['name' => 'Alpha Test Store', 'slug' => 'alpha-test']);

        $this->owner = User::factory()->create([
            'name' => 'Owner Alpha',
            'email' => 'owner@alpha.test',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->owner->assignRole('owner');

        $this->staff = User::factory()->create([
            'name' => 'Staff Alpha',
            'email' => 'staff@alpha.test',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->staff->assignRole('staff');

        $this->simulatorChannel = Channel::create(['name' => 'Simulator', 'slug' => 'simulator', 'driver' => 'web']);
        $this->simulatorAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->simulatorChannel->id,
            'name' => 'Sim Account',
            'external_id' => 'sim_acc_unified',
            'access_token' => 'sim_tok_test',
            'is_active' => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->simulatorAccount->id,
            'external_user_id' => 'sim_user_unified',
            'customer_name' => 'Owner Alpha',
            'last_direction' => 'inbound',
            'metadata' => ['is_simulator' => true, 'is_customer' => false],
        ]);
    }

    private function makeLlmResponse(array $payload): LLMResponse
    {
        return new LLMResponse(
            content: json_encode($payload),
            provider: 'mock',
            model: 'mock-model',
            usage: ['prompt_tokens' => 100, 'completion_tokens' => 40, 'total_tokens' => 140],
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. DIRECT CHAT (English, Bangla, Banglish)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_1_direct_chat_greeting_fastpath_returns_zero_tool_calls(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')->never(); // Preflight fast-path resolves in 0ms

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        $request = new DecisionRequest(
            query: 'আসসালামু আলাইকুম',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = $engine->decide($request);

        $this->assertSame(DecisionResult::INTENT_CHAT, $decision->intent);
        $this->assertSame(DecisionResult::MODE_DIRECT_RESPONSE, $decision->responseMode);
        $this->assertEmpty($decision->toolCalls);
        $this->assertStringContainsString('ওয়ালাইকুম আসসালাম', (string) $decision->directResponse);
        $this->assertSame('layer0_greeting_fastpath', $decision->decisionMetadata['layer']);
    }

    public function test_1_english_greeting_fastpath(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')->never();

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        $request = new DecisionRequest(
            query: 'Hello',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = $engine->decide($request);

        $this->assertSame(DecisionResult::INTENT_CHAT, $decision->intent);
        $this->assertSame(DecisionResult::MODE_DIRECT_RESPONSE, $decision->responseMode);
        $this->assertStringContainsString('Hello!', (string) $decision->directResponse);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. KNOWLEDGE (Product availability, specifications, policies)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_2_product_specs_query_decides_knowledge_intelligence(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')
            ->once()
            ->andReturn($this->makeLlmResponse([
                'schema_version' => '1.0',
                'intent'         => 'knowledge',
                'response_mode'  => 'tool_required',
                'tool_calls'     => [
                    [
                        'tool'      => 'knowledge_intelligence',
                        'operation' => 'retrieve_product_specs',
                        'arguments' => ['query' => 'Laptop Pro 15 এর দাম এবং RAM কত?'],
                    ],
                ],
                'clarification'  => null,
                'direct_response'=> null,
            ]));

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        $request = new DecisionRequest(
            query: 'Laptop Pro 15 এর দাম এবং RAM কত?',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = $engine->decide($request);

        $this->assertTrue($decision->isKnowledge());
        $this->assertTrue($decision->requiresTool());
        $this->assertCount(1, $decision->toolCalls);
        $this->assertSame('knowledge_intelligence', $decision->toolCalls[0]->tool);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. ANALYTICS (Sales, Revenue, Staff Ranking)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_3_explicit_sales_query_decides_sales_analytics(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')
            ->once()
            ->andReturn($this->makeLlmResponse([
                'schema_version' => '1.0',
                'intent'         => 'analytics',
                'response_mode'  => 'tool_required',
                'tool_calls'     => [
                    [
                        'tool'      => 'sales_analytics',
                        'operation' => 'get_sales_summary',
                        'arguments' => ['period' => 'today'],
                    ],
                ],
                'clarification'  => null,
                'direct_response'=> null,
            ]));

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        $request = new DecisionRequest(
            query: 'Total sale koto?',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = $engine->decide($request);

        $this->assertTrue($decision->isAnalytics());
        $this->assertSame('sales_analytics', $decision->toolCalls[0]->tool);
    }

    public function test_3_salesperson_ranking_query_decides_employee_performance(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')
            ->once()
            ->andReturn($this->makeLlmResponse([
                'schema_version' => '1.0',
                'intent'         => 'analytics',
                'response_mode'  => 'tool_required',
                'tool_calls'     => [
                    [
                        'tool'      => 'employee_analytics',
                        'operation' => 'get_salesperson_ranking',
                        'arguments' => ['limit' => 5],
                    ],
                ],
                'clarification'  => null,
                'direct_response'=> null,
            ]));

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        $request = new DecisionRequest(
            query: 'ke koto taka sale korche?',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = $engine->decide($request);

        $this->assertTrue($decision->isAnalytics());
        $this->assertSame('employee_analytics', $decision->toolCalls[0]->tool);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. AMBIGUITY & CLARIFICATION
    // ─────────────────────────────────────────────────────────────────────────

    public function test_4_ambiguous_query_decides_clarification_mode(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')
            ->once()
            ->andReturn($this->makeLlmResponse([
                'schema_version' => '1.0',
                'intent'         => 'uncertain',
                'response_mode'  => 'clarification_required',
                'tool_calls'     => [],
                'clarification'  => [
                    'question' => 'আপনি কি আজকের বিক্রয় হিসাব দেখতে চান, নাকি পণ্যের স্টক জানতে চান?',
                    'options'  => ['আজকের বিক্রয় হিসাব', 'পণ্যের স্টক তথ্য'],
                ],
                'direct_response'=> null,
            ]));

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        $request = new DecisionRequest(
            query: 'ajker hisab dao',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = $engine->decide($request);

        $this->assertTrue($decision->isUncertain());
        $this->assertTrue($decision->isClarification());
        $this->assertNotEmpty($decision->clarification['options']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. STOCK COLLISION RESOLUTION (Product stock vs Inventory Analytics)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_5_product_availability_stock_routes_to_knowledge_not_analytics(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')
            ->once()
            ->andReturn($this->makeLlmResponse([
                'schema_version' => '1.0',
                'intent'         => 'knowledge',
                'response_mode'  => 'tool_required',
                'tool_calls'     => [
                    [
                        'tool'      => 'knowledge_intelligence',
                        'operation' => 'check_product_availability',
                        'arguments' => ['product_name' => 'Laptop Pro'],
                    ],
                ],
                'clarification'  => null,
                'direct_response'=> null,
            ]));

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        $request = new DecisionRequest(
            query: 'Laptop Pro কি stock e ache?',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = $engine->decide($request);

        // Core architectural fix verification: Must be KNOWLEDGE, NOT hijacked by analytics!
        $this->assertTrue($decision->isKnowledge());
        $this->assertFalse($decision->isAnalytics());
        $this->assertSame('knowledge_intelligence', $decision->toolCalls[0]->tool);
    }

    public function test_5_aggregate_inventory_report_routes_to_inventory_analytics(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')
            ->once()
            ->andReturn($this->makeLlmResponse([
                'schema_version' => '1.0',
                'intent'         => 'analytics',
                'response_mode'  => 'tool_required',
                'tool_calls'     => [
                    [
                        'tool'      => 'inventory_analytics',
                        'operation' => 'get_inventory_summary',
                        'arguments' => ['scope' => 'all'],
                    ],
                ],
                'clarification'  => null,
                'direct_response'=> null,
            ]));

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        $request = new DecisionRequest(
            query: 'স্টক রিপোর্ট দাও',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = $engine->decide($request);

        $this->assertTrue($decision->isAnalytics());
        $this->assertSame('inventory_analytics', $decision->toolCalls[0]->tool);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. TOOL EXECUTION, UNKNOWN TOOL REJECTION & DLP
    // ─────────────────────────────────────────────────────────────────────────

    public function test_6_unknown_tool_rejection_by_execution_controller(): void
    {
        $controller = app(DeterministicExecutionController::class);

        $request = new DecisionRequest(
            query: 'Drop table users',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = new DecisionResult(
            schemaVersion: '1.0',
            intent: DecisionResult::INTENT_UNCERTAIN,
            responseMode: DecisionResult::MODE_TOOL_REQUIRED,
            toolCalls: [
                new ToolCall(tool: 'malicious_eval_tool', operation: 'drop_database', arguments: []),
            ],
            clarification: null,
            directResponse: null,
            decisionMetadata: [],
        );

        $execution = $controller->execute($decision, $request);

        $this->assertFalse($execution->success);
        $this->assertStringContainsString('is not registered', (string) $execution->errorMessage);
    }

    public function test_6_unauthorized_tool_attempt_by_staff_is_rejected(): void
    {
        $controller = app(DeterministicExecutionController::class);

        $request = new DecisionRequest(
            query: 'Give me financial profit margins',
            workspaceId: $this->workspace->id,
            user: $this->staff, // Staff has no analytics permissions
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = new DecisionResult(
            schemaVersion: '1.0',
            intent: DecisionResult::INTENT_ANALYTICS,
            responseMode: DecisionResult::MODE_TOOL_REQUIRED,
            toolCalls: [
                new ToolCall(tool: 'financial_analytics', operation: 'get_financial_summary', arguments: []),
            ],
            clarification: null,
            directResponse: null,
            decisionMetadata: [],
        );

        $execution = $controller->execute($decision, $request);

        $this->assertFalse($execution->success);
        $this->assertStringContainsString('Unauthorized', (string) $execution->errorMessage);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. ROUTER FAILURE FALLBACK
    // ─────────────────────────────────────────────────────────────────────────

    public function test_7_llm_outage_fails_safe_to_deterministic_rules(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')
            ->once()
            ->andThrow(new \RuntimeException('Connection timed out to LLM gateway'));

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        // Substantive sales query during LLM outage
        $request = new DecisionRequest(
            query: 'Total sale koto?',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $decision = $engine->decide($request);

        $this->assertTrue($decision->isAnalytics());
        $this->assertTrue($decision->decisionMetadata['is_fallback']);
        $this->assertSame('fallback_strict_sales', $decision->decisionMetadata['layer']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. CUSTOMER SECURITY BARRIER ENFORCEMENT
    // ─────────────────────────────────────────────────────────────────────────

    public function test_8_customer_channel_financial_inquiry_blocked_preflight(): void
    {
        $llmClientMock = Mockery::mock(LLMClient::class);
        $llmClientMock->shouldReceive('generate')->never(); // Preflight security barrier trips in 0ms

        $engine = new UnifiedDecisionEngine(
            llmClient: $llmClientMock,
            toolRegistry: app(ToolRegistry::class),
        );

        $request = new DecisionRequest(
            query: 'Total sale koto?',
            workspaceId: $this->workspace->id,
            user: null, // Untrusted customer
            conversation: $this->conversation,
            channelSlug: 'whatsapp',
            isCustomer: true,
        );

        $decision = $engine->decide($request);

        $this->assertTrue($decision->isSafeRefusal());
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $decision->directResponse);
        $this->assertSame('preflight_security_barrier', $decision->decisionMetadata['layer']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. END-TO-END UNIFIED ORCHESTRATOR PIPELINE
    // ─────────────────────────────────────────────────────────────────────────

    public function test_9_orchestrator_end_to_end_direct_greeting(): void
    {
        $orchestrator = app(UnifiedOrchestrator::class);

        $result = $orchestrator->process(
            query: 'Hello',
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->conversation,
            channelSlug: 'simulator',
            isCustomer: false,
        );

        $this->assertSame('chat', $result['route']);
        $this->assertTrue($result['answered']);
        $this->assertStringContainsString('Hello!', $result['reply']);
        $this->assertArrayHasKey('total_orchestrator_ms', $result['decision_metadata']);
    }

    public function test_9_orchestrator_end_to_end_customer_denial(): void
    {
        $orchestrator = app(UnifiedOrchestrator::class);

        $result = $orchestrator->process(
            query: 'Total sales report koto?',
            workspaceId: $this->workspace->id,
            user: null,
            conversation: $this->conversation,
            channelSlug: 'whatsapp',
            isCustomer: true,
        );

        $this->assertSame('analytics_denied', $result['route']);
        $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $result['reply']);
    }
}
