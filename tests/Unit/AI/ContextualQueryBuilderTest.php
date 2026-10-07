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

    public function test_resolves_laptop_entity_dynamically_without_hardcoded_vocabulary(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'ThinkPad X1 laptopটার warranty কতদিন?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'আমাদের ThinkPad X1 ল্যাপটপে ১ বছরের অফিসিয়াল ওয়ারেন্টি রয়েছে।',
        ]);

        $query = 'ওটার দাম কত?';
        $res = $this->builder->resolveContext($query, $this->conversation);

        $this->assertSame('resolved', $res->status);
        $this->assertNotNull($res->resolvedQuery);
        $this->assertStringContainsString('ThinkPad X1', $res->resolvedQuery);
    }

    public function test_resolves_restaurant_food_combo_entity_dynamically(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Burger Combo-তে কী কী আইটেম আছে?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'আমাদের Burger Combo-তে ১টি চিজবার্গার, ফ্রেঞ্চ ফ্রাই ও কোল্ড ড্রিংক রয়েছে।',
        ]);

        $query = 'ওটার দাম কত?';
        $res = $this->builder->resolveContext($query, $this->conversation);

        $this->assertSame('resolved', $res->status);
        $this->assertNotNull($res->resolvedQuery);
        $this->assertStringContainsString('Burger Combo', $res->resolvedQuery);
    }

    public function test_resolves_doctor_service_entity_dynamically(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Doctor A কখন চেম্বারে বসেন?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Doctor A রবি ও মঙ্গলবার বিকাল ৫টায় চেম্বারে বসেন।',
        ]);

        $query = 'ওনার consultation fee কত?';
        $res = $this->builder->resolveContext($query, $this->conversation);

        $this->assertSame('resolved', $res->status);
        $this->assertNotNull($res->resolvedQuery);
        $this->assertStringContainsString('Doctor A', $res->resolvedQuery);
    }

    public function test_resolves_phone_entity_dynamically(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'iPhone 15 Pro কি স্টকে এভেইলেবল আছে?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'জি, iPhone 15 Pro আমাদের স্টকে পাওয়া যাবে।',
        ]);

        $query = 'এটার দাম কত?';
        $res = $this->builder->resolveContext($query, $this->conversation);

        $this->assertSame('resolved', $res->status);
        $this->assertNotNull($res->resolvedQuery);
        $this->assertStringContainsString('iPhone 15 Pro', $res->resolvedQuery);
    }

    public function test_resolves_panjabi_clothing_entity_dynamically(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Royal Silk Panjabiটার ফেব্রিক কেমন?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'আমাদের Royal Silk Panjabi প্রিমিয়াম র সিল্কে তৈরি।',
        ]);

        $query = 'সেটার সাইজ কি কি আছে?';
        $res = $this->builder->resolveContext($query, $this->conversation);

        $this->assertSame('resolved', $res->status);
        $this->assertNotNull($res->resolvedQuery);
        $this->assertStringContainsString('Royal Silk Panjabi', $res->resolvedQuery);
    }

    public function test_banglish_pronouns_etar_otar_shetar_resolution(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Gaming Chair ta delivery charge koto?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Gaming Chair delivery charge dhakar vithore 100 taka.',
        ]);

        $query = 'otar price koto?';
        $res = $this->builder->resolveContext($query, $this->conversation);

        $this->assertSame('resolved', $res->status);
        $this->assertNotNull($res->resolvedQuery);
        $this->assertStringContainsString('Gaming Chair', $res->resolvedQuery);
    }

    public function test_english_pronouns_this_that_it_resolution(): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => 'Is the Premium Subscription available on annual billing?',
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'outbound',
            'type'            => 'text',
            'body'            => 'Yes, Premium Subscription has a 20% discount on annual billing.',
        ]);

        $query = 'How much is that one?';
        $res = $this->builder->resolveContext($query, $this->conversation);

        $this->assertSame('resolved', $res->status);
        $this->assertNotNull($res->resolvedQuery);
        $this->assertStringContainsString('Premium Subscription', $res->resolvedQuery);
    }

    public function test_multiple_competing_candidates_triggers_ambiguity(): void
    {
        $res = $this->builder->resolveContext('ওটার দাম কত?', null, [
            ['body' => 'আমি ThinkPad X1 আর MacBook Pro দুটিই পছন্দ করেছি।', 'direction' => 'inbound'],
        ]);

        $this->assertSame('ambiguous', $res->status);
        $this->assertNull($res->resolvedQuery);
        $this->assertTrue($res->needsClarification());
    }

    public function test_winner_margin_guard_enforced(): void
    {
        // One distinct winner
        $res = $this->builder->resolveContext('ওটার দাম কত?', null, [
            ['body' => 'আমি ThinkPad X1 ল্যাপটপটি কিনতে চাচ্ছি।', 'direction' => 'inbound'],
        ]);

        $this->assertSame('resolved', $res->status);
        $this->assertNotNull($res->resolvedQuery);
        $this->assertGreaterThanOrEqual(0.70, $res->confidence);
    }
}
