<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Tools\KnowledgeRetrievalTool;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\FAQ\FAQSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerSupportAgentTest extends TestCase
{
    use RefreshDatabase;

    private Conversation $conversation;
    private KnowledgeRetrievalTool $retrievalTool;

    protected function setUp(): void
    {
        parent::setUp();

        $workspace = Workspace::create(['name' => 'Support Org', 'slug' => 'support-org']);
        $channel = Channel::create(['slug' => 'web', 'name' => 'Web Chat', 'is_active' => true]);
        $account = ChannelAccount::create([
            'channel_id' => $channel->id,
            'workspace_id' => $workspace->id,
            'name' => 'Main Web Chat',
            'external_id' => 'web_001',
            'access_token' => 'token',
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id' => 'cust_777',
            'customer_name' => 'Bob',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);

        $faqSearchMock = $this->createMock(FAQSearch::class);
        $this->retrievalTool = new KnowledgeRetrievalTool($faqSearchMock);
    }

    public function test_agent_instructions_contain_core_rules(): void
    {
        $agent = new CustomerSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('Enterprise Customer Support AI Assistant', $instructions);
        $this->assertStringContainsString('NEVER fabricate, assume, or hallucinate', $instructions);
    }

    public function test_agent_loads_messages_from_conversation(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'Hello',
        ]);

        $agent = new CustomerSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $messages = iterator_to_array($agent->messages());

        $this->assertCount(1, $messages);
        $this->assertSame('Hello', $messages[0]->content);
    }

    public function test_agent_tools_returns_configured_tools(): void
    {
        $agent = new CustomerSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $tools = iterator_to_array($agent->tools());

        $this->assertCount(1, $tools);
        $this->assertSame($this->retrievalTool, $tools[0]);
    }

    public function test_agent_prompt_executes_with_faked_llm_response(): void
    {
        // Fake the Laravel AI SDK Agent response
        CustomerSupportAgent::fake([
            'You can return your item within 30 days of receipt as long as it is in original packaging.',
        ]);

        $agent = new CustomerSupportAgent(
            conversation: $this->conversation,
            retrievalTool: $this->retrievalTool,
        );

        $response = $agent->prompt("What's your return policy?");

        $this->assertStringContainsString('30 days of receipt', (string) $response);
        CustomerSupportAgent::assertPrompted("What's your return policy?");
    }
}
