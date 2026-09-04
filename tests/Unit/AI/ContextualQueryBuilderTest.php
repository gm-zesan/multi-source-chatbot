<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\ContextualQueryBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContextualQueryBuilderTest extends TestCase
{
    use RefreshDatabase;

    private ContextualQueryBuilder $builder;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ContextualQueryBuilder();

        $workspace = Workspace::create(['name' => 'Test WS', 'slug' => 'test-ws']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $account = ChannelAccount::create([
            'workspace_id' => $workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Test Account',
            'external_id'  => 'acc_123',
            'access_token' => 'tok_123',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'user_ctx_test',
            'status'             => 'active',
            'customer_name'      => 'Test User',
            'last_direction'     => 'inbound',
        ]);
    }

    public function test_self_contained_query_is_not_rewritten(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'How long is the free trial?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Our free trial is 14 days.',
        ]);

        $query = 'How is my data encrypted?';
        $result = $this->builder->buildContextualQuery($query, $this->conversation);

        $this->assertSame($query, $result);
    }

    public function test_anaphora_pronoun_resolution(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Do you offer a free trial on Pro plan?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Yes, we offer a 14-day free trial on the Pro plan.',
        ]);

        $query = 'Can I extend it?';
        $result = $this->builder->buildContextualQuery($query, $this->conversation);

        $this->assertStringContainsString('free trial', mb_strtolower($result));
        $this->assertNotSame($query, $result);
    }

    public function test_elliptical_follow_up_resolution(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'How do I connect WhatsApp to the platform?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'You can connect WhatsApp via Settings > Channels > WhatsApp.',
        ]);

        $query = 'And Telegram?';
        $result = $this->builder->buildContextualQuery($query, $this->conversation);

        $this->assertStringContainsString('telegram', mb_strtolower($result));
        $this->assertStringContainsString('connect', mb_strtolower($result));
    }

    public function test_context_switch_interleaved_greeting_bypassed(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Where can I get my API key?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'You can generate an API key in Settings > API Keys.',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Thank you so much!',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'You are very welcome!',
        ]);

        $query = 'What are the limits on it?';
        $result = $this->builder->buildContextualQuery($query, $this->conversation);

        $this->assertStringContainsString('rate limits', mb_strtolower($result));
        $this->assertStringContainsString('api', mb_strtolower($result));
    }

    public function test_null_conversation_returns_raw_query(): void
    {
        $query = 'Can I extend it?';
        $result = $this->builder->buildContextualQuery($query, null);

        $this->assertSame($query, $result);
    }
}
