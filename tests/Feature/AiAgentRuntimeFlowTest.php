<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\FAQ;
use App\Models\FAQCategory;
use App\Models\User;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Gateway\FakeTextGateway;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\TextResponse;
use Laravel\Ai\Tools\Request as ToolRequest;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiAgentRuntimeFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;
    private FAQ $faq;

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

        $category = FAQCategory::create([
            'workspace_id' => $this->workspace->id,
            'name' => 'Account & Billing',
            'slug' => 'account-billing',
            'is_active' => true,
        ]);

        $this->faq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'faq_category_id' => $category->id,
            'question' => 'How do I update my payment method?',
            'answer' => 'Go to Settings > Billing > Payment Methods. You can add a new credit card or PayPal account.',
            'priority' => 100,
            'is_active' => true,
            'is_searchable' => true,
        ]);
    }

    /**
     * TEST 1 & 2: Direct FAQ Question & Grounded Response Verification
     */
    public function test_runtime_faq_retrieval_and_agent_grounding(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->once())
            ->method('search')
            ->with('How do I update my payment method?', 5, $this->workspace->id)
            ->willReturn(new EloquentCollection([
                new FAQSearchResult(
                    faq: $this->faq,
                    keywordScore: 0.95,
                    semanticScore: 0.98,
                    finalScore: 0.98,
                    matchType: 'hybrid',
                ),
            ]));

        $tool = new KnowledgeRetrievalTool($faqSearchMock, $this->workspace->id);

        // Verify tool handles request and formats grounded context
        $toolResult = (string) $tool->handle(new ToolRequest(['query' => 'How do I update my payment method?']));

        $this->assertStringContainsString('Article #1:', $toolResult);
        $this->assertStringContainsString('Question: How do I update my payment method?', $toolResult);
        $this->assertStringContainsString('Go to Settings > Billing > Payment Methods', $toolResult);

        // Verify agent executes with grounded response
        CustomerSupportAgent::fake([
            'To update your payment method, please navigate to Settings > Billing > Payment Methods where you can add a credit card or PayPal.',
        ]);

        $agent = new CustomerSupportAgent(
            conversation: null,
            retrievalTool: $tool,
        );

        $response = (string) $agent->prompt('How do I update my payment method?');

        $this->assertStringContainsString('Settings > Billing > Payment Methods', $response);
        CustomerSupportAgent::assertPrompted('How do I update my payment method?');
    }

    /**
     * TEST 3: Unknown Question (No Hallucination)
     */
    public function test_runtime_unknown_question_graceful_fallback(): void
    {
        $faqSearchMock = $this->createMock(FAQSearch::class);
        $faqSearchMock->expects($this->once())
            ->method('search')
            ->willReturn(new EloquentCollection([]));

        $tool = new KnowledgeRetrievalTool($faqSearchMock, $this->workspace->id);
        $toolResult = (string) $tool->handle(new ToolRequest(['query' => 'Can I pay in cryptocurrency?']));

        $this->assertStringContainsString('No relevant knowledge base articles or FAQs found', $toolResult);

        CustomerSupportAgent::fake([
            'I do not have specific information about cryptocurrency payments in our knowledge base. Would you like me to connect you with a human support specialist?',
        ]);

        $agent = new CustomerSupportAgent(
            conversation: null,
            retrievalTool: $tool,
        );

        $response = (string) $agent->prompt('Can I pay in cryptocurrency?');

        $this->assertStringContainsString('human support specialist', $response);
    }

    /**
     * TEST 4: CRM Entity Extraction in Simulator
     */
    public function test_runtime_simulator_crm_extraction_with_ai_response(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'http://127.0.0.1:8002/*' => \Illuminate\Support\Facades\Http::response([
                'status'  => 'healthy',
                'results' => [],
            ], 200),
        ]);

        CustomerSupportAgent::fake([
            'Thank you, your email and phone have been noted. Our office hours are 9 AM - 5 PM.',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/dashboard/simulator/send', [
            'message' => 'Contact me at support-test@example.com or +8801712345678. What are your hours?',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'reply'   => 'Thank you, your email and phone have been noted. Our office hours are 9 AM - 5 PM.',
                'pipeline_diagnostics' => [
                    'crm_extracted' => [
                        'has_data' => true,
                        'db_saved' => true,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('crm_contact_emails', [
            'email' => 'support-test@example.com',
        ]);
        $this->assertDatabaseHas('crm_contact_phones', [
            'phone' => '+8801712345678',
        ]);
    }

    /**
     * TEST 5: Fallback on Provider Failure
     */
    public function test_runtime_provider_failure_gracefully_handled(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'http://127.0.0.1:8001/*' => \Illuminate\Support\Facades\Http::response([
                'vector' => array_fill(0, 768, 0.05),
                'dimensions' => 768,
                'model' => 'paraphrase-multilingual-mpnet-base-v2',
            ], 200),
            '*' => \Illuminate\Support\Facades\Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $response = $this->actingAs($this->admin)->postJson('/dashboard/simulator/send', [
            'message' => 'How do I update my payment method?',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'query',
                'reply',
                'answered',
                'confidence',
                'pipeline_diagnostics',
            ]);

        // When LLM provider fails (missing key), the reply should contain a helpful fallback
        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertNotEmpty($data['reply']);
    }
}
