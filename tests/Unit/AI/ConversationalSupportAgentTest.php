<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\Agents\ConversationalSupportAgent;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationalSupportAgentTest extends TestCase
{
    use RefreshDatabase;

    private Conversation $conversation;

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
            'external_user_id' => 'cust_888',
            'customer_name' => 'Alice',
            'status' => 'open',
            'last_direction' => 'inbound',
        ]);
    }

    public function test_agent_instructions_contain_purchase_intent_and_lead_collection_rules(): void
    {
        $agent = new ConversationalSupportAgent(conversation: $this->conversation);
        $instructions = (string) $agent->instructions();

        $this->assertStringContainsString('Purchase Intent & Lead Collection', $instructions);
        $this->assertStringContainsString('নাম, ফোন নম্বর এবং ডেলিভারি ঠিকানা', $instructions);
        $this->assertStringContainsString('ধন্যবাদ। আপনার তথ্য পেয়েছি', $instructions);
    }

    public function test_agent_loads_messages_from_conversation(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'type' => 'text',
            'body' => 'এই Laptop Pro 15 টা নিতে চাই',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => 'অবশ্যই। এটি নিতে এগিয়ে যেতে আপনার নাম, ফোন নম্বর এবং ডেলিভারি ঠিকানা দিন।',
        ]);

        $agent = new ConversationalSupportAgent(conversation: $this->conversation);
        $messages = iterator_to_array($agent->messages());

        $this->assertCount(2, $messages);
        $this->assertSame('এই Laptop Pro 15 টা নিতে চাই', $messages[0]->content);
        $this->assertSame('অবশ্যই। এটি নিতে এগিয়ে যেতে আপনার নাম, ফোন নম্বর এবং ডেলিভারি ঠিকানা দিন।', $messages[1]->content);
    }
}
