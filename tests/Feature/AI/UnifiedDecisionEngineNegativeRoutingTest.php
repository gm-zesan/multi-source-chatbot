<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\Agents\KnowledgeSupportAgent;
use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DeterministicExecutionController;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\ToolRegistry;
use App\AI\Decision\UnifiedDecisionEngine;
use App\AI\Decision\UnifiedOrchestrator;
use App\AI\DTOs\ToolResult;
use App\AI\Orchestration\CustomerSupportService;
use App\AI\Tools\EmployeeAnalyticsTool;
use App\AI\Tools\FinancialAnalyticsTool;
use App\AI\Tools\InventoryAnalyticsTool;
use App\AI\Tools\KnowledgeIntelligenceTool;
use App\AI\Tools\LegacyExcelAnalyticsTool;
use App\AI\Tools\SalesAnalyticsTool;
use App\Enums\RoleEnum;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Dedicated Negative Routing Regression Suite for UnifiedDecisionEngine.
 *
 * Verifies that:
 * 1. Product stock, price, delivery, and store policy queries NEVER leak into ANALYTICS.
 * 2. Customer channels attempting financial/workforce queries receive SAFE_REFUSAL.
 * 3. Authenticated owners querying sales metrics route to ANALYTICS.
 * 4. Out-of-Domain queries route to OUT_OF_DOMAIN / INTENT_OOD.
 * 5. Ambiguous queries without prior dialogue context require CLARIFICATION_REQUIRED.
 */
class UnifiedDecisionEngineNegativeRoutingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private Conversation $customerConversation;
    private Conversation $ownerConversation;
    private DecisionEngineInterface $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Negative Routing Test WS',
            'slug' => 'neg-routing-' . Str::random(6),
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => RoleEnum::ADMIN->value, 'guard_name' => 'web']);
        $this->owner = User::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Store Owner',
            'email'        => 'owner-' . Str::random(6) . '@neg.test',
            'password'     => bcrypt('secret'),
        ]);
        $this->owner->assignRole(RoleEnum::ADMIN->value);

        // Customer Web Channel Account
        $webChannel = Channel::firstOrCreate(['slug' => 'web'], ['name' => 'Web', 'driver' => 'web']);
        $custAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $webChannel->id,
            'name'         => 'Customer Web Desk',
            'external_id'  => 'acc_cust_web',
            'access_token' => 'tok_cust_web',
            'is_active'    => true,
        ]);

        $this->customerConversation = Conversation::create([
            'channel_account_id' => $custAccount->id,
            'external_user_id'   => 'user_public_cust',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'metadata'           => ['is_customer' => true],
        ]);

        // Owner Simulator Channel Account
        $simChannel = Channel::firstOrCreate(['slug' => 'simulator'], ['name' => 'Simulator', 'driver' => 'web']);
        $ownerAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $simChannel->id,
            'name'         => 'Owner Simulator',
            'external_id'  => 'acc_owner_sim',
            'access_token' => 'tok_owner_sim',
            'is_active'    => true,
        ]);

        $this->ownerConversation = Conversation::create([
            'channel_account_id' => $ownerAccount->id,
            'external_user_id'   => 'user_owner_admin',
            'status'             => 'active',
            'last_direction'     => 'inbound',
            'metadata'           => ['role' => 'owner', 'is_simulator' => true],
        ]);

        $this->engine = app(DecisionEngineInterface::class);
    }

    private function makeCustomerRequest(string $query, ?string $context = null): DecisionRequest
    {
        return new DecisionRequest(
            query: $query,
            workspaceId: $this->workspace->id,
            user: null,
            conversation: $this->customerConversation,
            channelSlug: 'web',
            isCustomer: true,
            compactContext: $context,
        );
    }

    private function makeOwnerRequest(string $query, ?string $context = null): DecisionRequest
    {
        return new DecisionRequest(
            query: $query,
            workspaceId: $this->workspace->id,
            user: $this->owner,
            conversation: $this->ownerConversation,
            channelSlug: 'simulator',
            isCustomer: false,
            compactContext: $context,
        );
    }

    public function test_product_stock_availability_never_routes_to_analytics(): void
    {
        $queries = [
            'Laptop Pro কি stock e ache?',
            'iPhone 15 এর স্টক আছে কি?',
            'Hasan-এর laptop available আছে?',
            'Royal Silk Panjabi কি available আছে?',
            'এই প্রোডাক্টটি স্টকে আছে?',
            'Is this item available in stock?',
        ];

        foreach ($queries as $query) {
            // Test both Customer and Owner requests to ensure zero keyword collision
            foreach ([$this->makeCustomerRequest($query), $this->makeOwnerRequest($query)] as $request) {
                $decision = $this->engine->decide($request);

                $this->assertNotSame(DecisionResult::INTENT_ANALYTICS, $decision->intent, "Failed for: {$query}");
                $this->assertSame(DecisionResult::INTENT_KNOWLEDGE, $decision->intent, "Expected KNOWLEDGE for: {$query}");
                $this->assertSame(DecisionResult::MODE_TOOL_REQUIRED, $decision->responseMode);
                $this->assertNotEmpty($decision->toolCalls);
                $this->assertSame('knowledge_intelligence', $decision->toolCalls[0]->tool);
            }
        }
    }

    public function test_product_price_and_discount_never_routes_to_analytics(): void
    {
        $queries = [
            'iPhone এর দাম কত?',
            'Royal Silk Panjabi এর দাম কত?',
            'Panjabi dam koto?',
            'How much does this cost?',
            'প্রোডাক্টের মূল্য কত?',
            'কোন ডিসকাউন্ট কুপন কোড আছে?',
            'Do you have any discount code?',
        ];

        foreach ($queries as $query) {
            foreach ([$this->makeCustomerRequest($query), $this->makeOwnerRequest($query)] as $request) {
                $decision = $this->engine->decide($request);

                $this->assertNotSame(DecisionResult::INTENT_ANALYTICS, $decision->intent, "Failed for: {$query}");
                $this->assertSame(DecisionResult::INTENT_KNOWLEDGE, $decision->intent, "Expected KNOWLEDGE for: {$query}");
                $this->assertSame(DecisionResult::MODE_TOOL_REQUIRED, $decision->responseMode);
                $this->assertSame('knowledge_intelligence', $decision->toolCalls[0]->tool);
            }
        }
    }

    public function test_delivery_shipping_and_tracking_never_routes_to_analytics(): void
    {
        $queries = [
            'ঢাকার বাইরে ডেলিভারি চার্জ কত?',
            'কবে ডেলিভারি হবে?',
            'আমার order কবে পৌঁছাবে?',
            'আমার পার্সেল কোথায়?',
            'Parcel tracking number kothay pabo?',
            'ডেলিভারি হতে কত দিন সময় লাগবে?',
            'What is the delivery fee?',
        ];

        foreach ($queries as $query) {
            foreach ([$this->makeCustomerRequest($query), $this->makeOwnerRequest($query)] as $request) {
                $decision = $this->engine->decide($request);

                $this->assertNotSame(DecisionResult::INTENT_ANALYTICS, $decision->intent, "Failed for: {$query}");
                $this->assertSame(DecisionResult::INTENT_KNOWLEDGE, $decision->intent, "Expected KNOWLEDGE for: {$query}");
                $this->assertSame(DecisionResult::MODE_TOOL_REQUIRED, $decision->responseMode);
                $this->assertSame('knowledge_intelligence', $decision->toolCalls[0]->tool);
            }
        }
    }

    public function test_returns_and_store_policy_never_routes_to_analytics(): void
    {
        $queries = [
            'আপনাদের রিটার্ন পলিসি কি?',
            'পণ্য ফেরত দেওয়ার নিয়ম কি?',
            'এই পণ্যের warranty কতদিন?',
            'What is your refund policy?',
            'আপনাদের প্রধান অফিস কোথায়?',
            'ক্যাশ অন ডেলিভারি (COD) পেমেন্ট সুবিধা আছে কি?',
        ];

        foreach ($queries as $query) {
            foreach ([$this->makeCustomerRequest($query), $this->makeOwnerRequest($query)] as $request) {
                $decision = $this->engine->decide($request);

                $this->assertNotSame(DecisionResult::INTENT_ANALYTICS, $decision->intent, "Failed for: {$query}");
                $this->assertSame(DecisionResult::INTENT_KNOWLEDGE, $decision->intent, "Expected KNOWLEDGE for: {$query}");
                $this->assertSame(DecisionResult::MODE_TOOL_REQUIRED, $decision->responseMode);
                $this->assertSame('knowledge_intelligence', $decision->toolCalls[0]->tool);
            }
        }
    }

    public function test_customer_channel_financial_query_strictly_refused(): void
    {
        $queries = [
            'তোমাদের আজকের মোট বিক্রি কত?',
            'Total sales report dekhan',
            'Hasan er total sales koto?',
            'Top performing salesperson ke?',
            'দোকানের লাভ কত হয়েছে?',
            'Employee commission summary dao',
        ];

        foreach ($queries as $query) {
            $decision = $this->engine->decide($this->makeCustomerRequest($query));

            $this->assertSame(DecisionResult::INTENT_ANALYTICS, $decision->intent);
            $this->assertSame(DecisionResult::MODE_SAFE_REFUSAL, $decision->responseMode);
            $this->assertEmpty($decision->toolCalls);
            $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $decision->directResponse);
        }
    }

    public function test_authenticated_owner_sales_queries_route_to_analytics(): void
    {
        $queries = [
            'আজকের মোট বিক্রি কত?',
            'Total sales koto?',
            'Top salesperson ke?',
            'আজকে cash collection কত?',
            'Sales summary report dekhao',
        ];

        foreach ($queries as $query) {
            $decision = $this->engine->decide($this->makeOwnerRequest($query));

            $this->assertSame(DecisionResult::INTENT_ANALYTICS, $decision->intent);
            $this->assertSame(DecisionResult::MODE_TOOL_REQUIRED, $decision->responseMode);
            $this->assertNotEmpty($decision->toolCalls);
            $this->assertContains($decision->toolCalls[0]->tool, ['sales_analytics', 'legacy_excel_analytics']);
        }
    }

    public function test_out_of_domain_queries_route_to_out_of_domain(): void
    {
        $queries = [
            'who won the world cup?',
            'আজকে কি নির্বাচন?',
            'How to write python code?',
            'What is the weather in Tokyo?',
            'Recipe for chocolate cake',
        ];

        foreach ($queries as $query) {
            $decision = $this->engine->decide($this->makeCustomerRequest($query));

            $this->assertSame(DecisionResult::INTENT_OOD, $decision->intent, "Failed for: {$query}");
            $this->assertSame(DecisionResult::MODE_OUT_OF_DOMAIN, $decision->responseMode);
            $this->assertEmpty($decision->toolCalls);
            $this->assertStringContainsString('কাস্টমার সাপোর্ট নলেজ বেসের আওতাভুক্ত নয়', $decision->directResponse);
        }
    }

    public function test_ambiguous_queries_without_context_require_clarification(): void
    {
        $queries = [
            'এটার কি হবে?',
            'পাঞ্জাবিটা কেমন?',
            'সেটা কি সম্ভব?',
        ];

        foreach ($queries as $query) {
            $decision = $this->engine->decide($this->makeCustomerRequest($query));

            $this->assertSame(DecisionResult::INTENT_UNCERTAIN, $decision->intent);
            $this->assertSame(DecisionResult::MODE_CLARIFICATION_REQUIRED, $decision->responseMode);
            $this->assertNotNull($decision->clarification);
            $this->assertNotEmpty($decision->clarification['options'] ?? []);
        }
    }

    public function test_conversational_chitchat_routes_to_direct_chat(): void
    {
        $queries = [
            'Hello',
            'Hi',
            'সালামু আলাইকুম',
            'ধন্যবাদ',
            'what can you do',
        ];

        foreach ($queries as $query) {
            $decision = $this->engine->decide($this->makeCustomerRequest($query));

            $this->assertSame(DecisionResult::INTENT_CHAT, $decision->intent);
            $this->assertSame(DecisionResult::MODE_DIRECT_RESPONSE, $decision->responseMode);
            $this->assertNotEmpty($decision->directResponse);
        }
    }

    public function test_contextual_analytics_followup_and_topic_switch_safety(): void
    {
        $priorAnalyticsContext = "User: আজকের মোট বিক্রি কত?\nAssistant: আজকের মোট বিক্রি ১,২৫,০০০ টাকা।";

        // 1. Authorized owner contextual analytics follow-up MUST route to ANALYTICS
        $followupQueries = [
            'আর Karim-এরটা?',
            'গত মাসেরটা কত ছিল?',
        ];
        foreach ($followupQueries as $query) {
            $decision = $this->engine->decide($this->makeOwnerRequest($query, $priorAnalyticsContext));
            $this->assertSame(DecisionResult::INTENT_ANALYTICS, $decision->intent, "Follow-up failed for: {$query}");
            $this->assertSame(DecisionResult::MODE_TOOL_REQUIRED, $decision->responseMode);
            $this->assertContains($decision->toolCalls[0]->tool, ['sales_analytics', 'legacy_excel_analytics']);
        }

        // 2. Customer channel with identical context MUST NEVER unlock analytics
        $custDecision = $this->engine->decide($this->makeCustomerRequest('আর Karim-এরটা?', $priorAnalyticsContext));
        $this->assertNotSame(DecisionResult::INTENT_ANALYTICS, $custDecision->intent);

        // 3. Owner topic switch to product/warranty/delivery after analytics turn MUST NOT collide into ANALYTICS!
        $topicSwitchQueries = [
            'iPhone এর দাম কত?',
            'এই পণ্যের warranty কতদিন?',
            'কবে ডেলিভারি হবে?',
            'Hasan-এর laptop available আছে?',
        ];
        foreach ($topicSwitchQueries as $query) {
            $decision = $this->engine->decide($this->makeOwnerRequest($query, $priorAnalyticsContext));
            $this->assertNotSame(DecisionResult::INTENT_ANALYTICS, $decision->intent, "Collision on topic switch: {$query}");
            $this->assertSame(DecisionResult::INTENT_KNOWLEDGE, $decision->intent, "Expected KNOWLEDGE on topic switch: {$query}");
            $this->assertSame('knowledge_intelligence', $decision->toolCalls[0]->tool);
        }
    }

    public function test_orchestrator_end_to_end_guarantees_zero_analytics_tool_execution_on_negative_queries(): void
    {
        $queries = [
            'Laptop Pro কি stock e ache?',
            'iPhone এর দাম কত?',
            'Hasan-এর laptop available আছে?',
            'কবে ডেলিভারি হবে?',
            'আমার order কবে পৌঁছাবে?',
            'এই পণ্যের warranty কতদিন?',
        ];

        KnowledgeSupportAgent::fake(array_fill(0, count($queries), 'জি, আমাদের স্টোরে পণ্যটি পাওয়া যাচ্ছে এবং বিস্তারিত তথ্য দেওয়া হলো।'));

        $faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'পণ্য ও ডেলিভারি তথ্য',
            'answer'       => 'আমাদের পণ্য স্টকে রয়েছে, ডেলিভারি সময় ২-৩ দিন এবং ১ বছরের অফিসিয়াল ওয়ারেন্টি আছে।',
            'is_active'    => true,
        ]);
        $hitCollection = new \Illuminate\Database\Eloquent\Collection([
            new FAQSearchResult(faq: $faq, keywordScore: 0.92, semanticScore: 0.92, finalScore: 0.92, matchType: 'lexicon'),
        ]);

        // Assert ZERO analytics tools ever execute
        $mockLegacyExcel = Mockery::mock(LegacyExcelAnalyticsTool::class);
        $mockLegacyExcel->shouldReceive('name')->andReturn('legacy_excel_analytics');
        $mockLegacyExcel->shouldReceive('isReadOnly')->andReturn(true);
        $mockLegacyExcel->shouldReceive('requiredPermissions')->andReturn(['view_analytics']);
        $mockLegacyExcel->shouldReceive('getRequiredPermissions')->andReturn(['view_analytics']);
        $mockLegacyExcel->shouldNotReceive('execute');

        $mockSalesAnalytics = Mockery::mock(SalesAnalyticsTool::class);
        $mockSalesAnalytics->shouldReceive('name')->andReturn('sales_analytics');
        $mockSalesAnalytics->shouldReceive('isReadOnly')->andReturn(true);
        $mockSalesAnalytics->shouldReceive('requiredPermissions')->andReturn(['view_analytics']);
        $mockSalesAnalytics->shouldReceive('getRequiredPermissions')->andReturn(['view_analytics']);
        $mockSalesAnalytics->shouldNotReceive('execute');

        $mockInventoryAnalytics = Mockery::mock(InventoryAnalyticsTool::class);
        $mockInventoryAnalytics->shouldReceive('name')->andReturn('inventory_analytics');
        $mockInventoryAnalytics->shouldReceive('isReadOnly')->andReturn(true);
        $mockInventoryAnalytics->shouldReceive('requiredPermissions')->andReturn(['view_analytics']);
        $mockInventoryAnalytics->shouldReceive('getRequiredPermissions')->andReturn(['view_analytics']);
        $mockInventoryAnalytics->shouldNotReceive('execute');

        $mockFinancialAnalytics = Mockery::mock(FinancialAnalyticsTool::class);
        $mockFinancialAnalytics->shouldReceive('name')->andReturn('financial_analytics');
        $mockFinancialAnalytics->shouldReceive('isReadOnly')->andReturn(true);
        $mockFinancialAnalytics->shouldReceive('requiredPermissions')->andReturn(['view_analytics']);
        $mockFinancialAnalytics->shouldReceive('getRequiredPermissions')->andReturn(['view_analytics']);
        $mockFinancialAnalytics->shouldNotReceive('execute');

        $mockEmployeeAnalytics = Mockery::mock(EmployeeAnalyticsTool::class);
        $mockEmployeeAnalytics->shouldReceive('name')->andReturn('employee_analytics');
        $mockEmployeeAnalytics->shouldReceive('isReadOnly')->andReturn(true);
        $mockEmployeeAnalytics->shouldReceive('requiredPermissions')->andReturn(['view_analytics']);
        $mockEmployeeAnalytics->shouldReceive('getRequiredPermissions')->andReturn(['view_analytics']);
        $mockEmployeeAnalytics->shouldNotReceive('execute');

        // Assert KnowledgeIntelligenceTool IS selected and executed exactly once per query
        $mockKnowledgeTool = Mockery::mock(KnowledgeIntelligenceTool::class);
        $mockKnowledgeTool->shouldReceive('name')->andReturn('knowledge_intelligence');
        $mockKnowledgeTool->shouldReceive('isReadOnly')->andReturn(true);
        $mockKnowledgeTool->shouldReceive('requiredPermissions')->andReturn([]);
        $mockKnowledgeTool->shouldReceive('getRequiredPermissions')->andReturn([]);
        $mockKnowledgeTool->shouldReceive('execute')
            ->times(count($queries))
            ->andReturn(ToolResult::success(
                data: ['hits_count' => 1, 'evidence_items' => [['content' => $faq->answer]]],
                metadata: [
                    'retrieval_hits' => $hitCollection,
                    'top_hit'        => $hitCollection->first(),
                    'sources'        => ['FAQ #1'],
                ]
            ));

        $this->app->instance(LegacyExcelAnalyticsTool::class, $mockLegacyExcel);
        $this->app->instance(SalesAnalyticsTool::class, $mockSalesAnalytics);
        $this->app->instance(InventoryAnalyticsTool::class, $mockInventoryAnalytics);
        $this->app->instance(FinancialAnalyticsTool::class, $mockFinancialAnalytics);
        $this->app->instance(EmployeeAnalyticsTool::class, $mockEmployeeAnalytics);
        $this->app->instance(KnowledgeIntelligenceTool::class, $mockKnowledgeTool);

        // Rebuild ToolRegistry & Orchestrator with the spied tool instances
        $this->app->forgetInstance(ToolRegistry::class);
        $this->app->forgetInstance(DeterministicExecutionController::class);
        $this->app->forgetInstance(UnifiedOrchestrator::class);

        $orchestrator = app(UnifiedOrchestrator::class);

        foreach ($queries as $query) {
            // Execute on customer channel and verify zero analytics execution & zero leakage
            $result = $orchestrator->process(
                query: $query,
                workspaceId: $this->workspace->id,
                user: null,
                conversation: $this->customerConversation,
                channelSlug: 'web',
                isCustomer: true,
            );

            $this->assertSame('knowledge', $result['route'], "Orchestrator leaked or misrouted: {$query}");
            $this->assertTrue($result['answered'], "Expected answered=true for: {$query}");
            $this->assertStringNotContainsString(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $result['reply']);
        }
    }

    public function test_orchestrator_blocks_customer_analytics_queries_with_zero_tool_execution(): void
    {
        $mockLegacyExcel = Mockery::mock(LegacyExcelAnalyticsTool::class);
        $mockLegacyExcel->shouldReceive('name')->andReturn('legacy_excel_analytics');
        $mockLegacyExcel->shouldReceive('isReadOnly')->andReturn(true);
        $mockLegacyExcel->shouldReceive('getRequiredPermissions')->andReturn(['view_analytics']);
        $mockLegacyExcel->shouldNotReceive('execute');

        $mockSalesAnalytics = Mockery::mock(SalesAnalyticsTool::class);
        $mockSalesAnalytics->shouldReceive('name')->andReturn('sales_analytics');
        $mockSalesAnalytics->shouldReceive('isReadOnly')->andReturn(true);
        $mockSalesAnalytics->shouldReceive('getRequiredPermissions')->andReturn(['view_analytics']);
        $mockSalesAnalytics->shouldNotReceive('execute');

        $mockKnowledgeTool = Mockery::mock(KnowledgeIntelligenceTool::class);
        $mockKnowledgeTool->shouldReceive('name')->andReturn('knowledge_intelligence');
        $mockKnowledgeTool->shouldReceive('isReadOnly')->andReturn(true);
        $mockKnowledgeTool->shouldReceive('getRequiredPermissions')->andReturn([]);
        $mockKnowledgeTool->shouldNotReceive('execute');

        $this->app->instance(LegacyExcelAnalyticsTool::class, $mockLegacyExcel);
        $this->app->instance(SalesAnalyticsTool::class, $mockSalesAnalytics);
        $this->app->instance(KnowledgeIntelligenceTool::class, $mockKnowledgeTool);

        $this->app->forgetInstance(ToolRegistry::class);
        $this->app->forgetInstance(DeterministicExecutionController::class);
        $this->app->forgetInstance(UnifiedOrchestrator::class);

        $orchestrator = app(UnifiedOrchestrator::class);

        foreach (['তোমাদের আজকের মোট বিক্রি কত?', 'Hasan er total sales koto?'] as $query) {
            $result = $orchestrator->process(
                query: $query,
                workspaceId: $this->workspace->id,
                user: null,
                conversation: $this->customerConversation,
                channelSlug: 'web',
                isCustomer: true,
            );

            $this->assertSame('analytics_denied', $result['route'], "Customer query was not denied: {$query}");
            $this->assertSame(CustomerSupportService::CUSTOMER_ANALYTICS_DENIAL_MESSAGE, $result['reply']);
        }
    }
}

