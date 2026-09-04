<?php

declare(strict_types=1);

namespace Tests\Feature\E2E\Support;

use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\Workspace;
use App\Services\AI\CustomerSupportService;
use App\Services\Chat\ConversationService;
use App\Services\FAQ\FAQSearch;
use App\Services\FAQ\FAQSearchResult;
use App\Services\Memory\ConversationMemoryClient;
use App\Services\Memory\ConversationMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

abstract class BaseE2ETestCase extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected ChannelAccount $account;
    protected Conversation $conversation;
    protected CustomerSupportService $service;

    protected FAQ $deliveryFaq;
    protected FAQ $returnFaq;
    protected FAQ $orderFaq;
    protected FAQ $codFaq;

    protected Mockery\MockInterface $faqSearchMock;
    protected Mockery\MockInterface $routerMock;
    protected Mockery\MockInterface $memoryClientMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'E2E Commerce WS', 'slug' => 'e2e-commerce-ws']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web', 'driver' => 'web']);
        $this->account = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Storefront Web E2E',
            'external_id'  => 'acc_storefront_e2e',
            'access_token' => 'tok_storefront_e2e',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->account->id,
            'external_user_id'   => 'e2e_customer_tanvir',
            'status'             => 'active',
            'customer_name'      => 'Tanvir Ahmed',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);

        // Seed Core FAQ Fixtures
        $this->deliveryFaq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'ডেলিভারি চার্জ কত?',
            'answer'       => 'ঢাকার ভেতরে ডেলিভারি চার্জ ৬০ টাকা, ঢাকার বাইরে ১২০ টাকা।',
            'is_active'    => true,
        ]);

        $this->returnFaq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'পণ্য রিটার্ন বা এক্সচেঞ্জ করার নিয়ম কী?',
            'answer'       => 'পণ্য হাতে পাওয়ার ৭ দিনের মধ্যে অক্ষত অবস্থায় রিটার্ন বা এক্সচেঞ্জ করা যায়।',
            'is_active'    => true,
        ]);

        $this->orderFaq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'আমার অর্ডার স্ট্যাটাস কীভাবে জানবো?',
            'answer'       => 'অর্ডার আইডি দিয়ে আমাদের মেসেজ দিলে আমরা বর্তমান ট্র্যাকিং স্ট্যাটাস জানিয়ে দেব।',
            'is_active'    => true,
        ]);

        $this->codFaq = FAQ::create([
            'workspace_id' => $this->workspace->id,
            'question'     => 'ক্যাশ অন ডেলিভারি (COD) পেমেন্ট সুবিধা আছে কি?',
            'answer'       => 'হ্যাঁ, সারাদেশে ক্যাশ অন ডেলিভারি সুবিধা রয়েছে। পণ্য হাতে পেয়ে মূল্য পরিশোধ করতে পারবেন।',
            'is_active'    => true,
        ]);

        $this->faqSearchMock = Mockery::mock(FAQSearch::class);
        $this->routerMock = Mockery::mock(HybridRouter::class);
        $this->memoryClientMock = Mockery::mock(ConversationMemoryClient::class);

        $this->routerMock->shouldReceive('route')
            ->byDefault()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.92, 'faq_inquiry'));

        $this->faqSearchMock->shouldReceive('getLastTelemetry')
            ->byDefault()
            ->andReturn(['tier_executed' => 1]);

        $this->app->instance(FAQSearch::class, $this->faqSearchMock);
        $this->app->instance(HybridRouter::class, $this->routerMock);
        $this->app->instance(ConversationMemoryClient::class, $this->memoryClientMock);

        $this->service = new CustomerSupportService(
            faqSearch: $this->faqSearchMock,
            conversationService: app(ConversationService::class),
            router: $this->routerMock,
            memoryService: app(ConversationMemoryService::class),
        );
    }

    /**
     * Helper to create mock FAQ search hit collection.
     */
    protected function createHitCollection(FAQ $faq, float $score = 0.88, string $matchType = 'lexicon'): \Illuminate\Database\Eloquent\Collection
    {
        $hit = new FAQSearchResult(
            faq: $faq,
            keywordScore: $score,
            semanticScore: $score,
            finalScore: $score,
            matchType: $matchType,
        );

        return new \Illuminate\Database\Eloquent\Collection([$hit]);
    }

    /**
     * Record a prior turn on the conversation.
     */
    protected function recordTurn(string $userText, string $botDir, string $botText): void
    {
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => 'inbound',
            'type'            => 'text',
            'body'            => $userText,
        ]);
        Message::create([
            'conversation_id' => $this->conversation->id,
            'direction'       => $botDir,
            'type'            => 'text',
            'body'            => $botText,
        ]);
    }

    /**
     * Assert that the structured observability trace satisfies the contract.
     */
    protected function assertTraceDimensions(array $trace, array $expected): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $trace, "Trace missing expected key [{$key}]");
            if (is_array($value)) {
                foreach ($value as $subKey => $subVal) {
                    $this->assertArrayHasKey($subKey, $trace[$key], "Trace sub-key [{$key}.{$subKey}] missing");
                    $this->assertSame($subVal, $trace[$key][$subKey], "Trace mismatch at [{$key}.{$subKey}]");
                }
            } else {
                $this->assertSame($value, $trace[$key], "Trace mismatch at [{$key}]");
            }
        }
    }
}
