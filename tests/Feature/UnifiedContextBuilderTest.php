<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\ConversationalSupportAgent;
use App\AI\Agents\KnowledgeSupportAgent;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\FAQ\FAQSearchResult;
use App\Services\Memory\ConversationMemoryClient;
use App\Services\Memory\ConversationMemoryService;
use App\Services\Memory\MemoryRelevanceGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnifiedContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Unified Context Workspace',
            'slug' => 'unified-context-workspace',
        ]);

        $channel = Channel::firstOrCreate(
            ['slug' => 'web'],
            ['name' => 'Web Chat', 'driver' => 'web', 'is_active' => true]
        );

        $account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Web Widget',
            'external_id'  => 'web_widget_1',
            'access_token' => 'token_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_unified_777',
            'status'             => 'open',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_knowledge_agent_instructions_incorporate_unified_context_hierarchy(): void
    {
        $faq = new FAQ([
            'question' => 'How can I change my payment method?',
            'answer'   => 'Go to Settings > Billing and select your preferred method.',
        ]);
        $hit = new FAQSearchResult($faq, 0.92, 0.95, 0.88, 'hybrid');
        $hits = new \Illuminate\Database\Eloquent\Collection([$hit]);

        $memoryContext = "Customer Historical Preferences:\n- Preferred Payment: bKash (confirmed in #1042)\n- Preferred Size: XL";

        $agent = new KnowledgeSupportAgent(
            conversation: $this->conversation,
            retrievedKnowledge: $hits,
            memoryContext: $memoryContext,
        );

        $instructions = (string) $agent->instructions();

        // Must contain both KB documents and Graph Memory
        $this->assertStringContainsString('Retrieved Knowledge Base Documents:', $instructions);
        $this->assertStringContainsString('Go to Settings > Billing', $instructions);
        $this->assertStringContainsString('Customer Conversation Graph Memory', $instructions);
        $this->assertStringContainsString('- Preferred Payment: bKash', $instructions);
        $this->assertStringContainsString('- Preferred Size: XL', $instructions);

        // Must explicitly enforce Conflict Resolution Rule
        $this->assertStringContainsString('Context Hierarchy & Conflict Resolution:', $instructions);
        $this->assertStringContainsString('Official Knowledge Base Documents: Highest authority', $instructions);
    }

    public function test_conversational_agent_instructions_incorporate_memory_context(): void
    {
        $memoryContext = "Customer Historical Preferences:\n- Preferred Color: Navy Blue\n- Preferred Size: L";

        $agent = new ConversationalSupportAgent(
            conversation: $this->conversation,
            memoryContext: $memoryContext,
        );

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('Customer Conversation Graph Memory (Known Historical Preferences):', $instructions);
        $this->assertStringContainsString('Preferred Color: Navy Blue', $instructions);
    }

    public function test_customer_support_service_injects_graph_memory_into_knowledge_reply(): void
    {
        Http::fake([
            '*/memory/search' => Http::response([
                'success'                  => true,
                'customer_id'              => 'user_unified_777',
                'has_memories'             => true,
                'memories_count'           => 1,
                'memories'                 => [
                    [
                        'type'       => 'preference',
                        'subject'    => 'Customer',
                        'relation'   => 'PREFERS_SIZE',
                        'object'     => 'XL',
                        'status'     => 'current',
                        'confidence' => 0.95,
                    ]
                ],
                'formatted_memory_context' => "Customer Historical Preferences:\n- Preferred Size: XL",
                'latency_ms'               => 1.5,
            ], 200),
            '*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Apnar size XL er panjabi stock e ache! bKash e payment korte paren.',
                        ]
                    ]
                ]
            ], 200),
        ]);

        $this->conversation->messages()->create([
            'direction' => 'outbound',
            'type'      => 'text',
            'body'      => 'We have Premium Silk Panjabi in stock.',
        ]);

        $service = app(CustomerSupportService::class);
        $result = $service->handleQuery(
            query: 'Do you have this in my preferred size?',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
        );

        $this->assertNotNull($result['memory_context']);
        $this->assertStringContainsStringIgnoringCase('Prefers size: XL', $result['memory_context']);
    }
}
