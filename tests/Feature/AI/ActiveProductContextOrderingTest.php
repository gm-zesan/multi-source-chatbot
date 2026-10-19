<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Models\AnalyticsProduct;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\AI\ContextualQueryBuilder;
use App\Services\Business\BusinessSourceOfTruthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveProductContextOrderingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);

        $channel = Channel::create([
            'name' => 'Web Chat',
            'slug' => 'web',
            'type' => 'web',
        ]);

        $channelAccount = ChannelAccount::create([
            'workspace_id' => $this->workspace->id,
            'channel_id'   => $channel->id,
            'name'         => 'Default Web Account',
            'external_id'  => 'web-acc-1',
            'access_token' => 'dummy-token',
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'test-customer-order-1',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);

        AnalyticsProduct::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Laptop Pro 15',
            'category'     => 'Electronics',
            'unit_price'   => 85000.00,
            'cost_price'   => 70000.00,
            'is_active'    => true,
        ]);
    }

    public function test_turn1_inquiry_persists_active_product_context(): void
    {
        $businessService = app(BusinessSourceOfTruthService::class);

        $context = $businessService->buildBusinessContext(
            query: 'Laptop Pro 15 er price koto?',
            conversation: $this->conversation,
            workspaceId: $this->workspace->id,
        );

        $this->assertNotNull($context);
        $this->assertStringContainsString('Laptop Pro 15', $context);
        $this->assertStringContainsString('৳85,000.00', $context);

        $this->conversation->refresh();
        $this->assertNotEmpty($this->conversation->metadata['active_product'] ?? null);
        $this->assertSame('Laptop Pro 15', $this->conversation->metadata['active_product']['name']);
        $this->assertEquals(85000.0, $this->conversation->metadata['active_product']['unit_price']);
    }

    public function test_turn2_kivabe_order_korbo_resolves_with_active_product(): void
    {
        $businessService = app(BusinessSourceOfTruthService::class);
        $queryBuilder = app(ContextualQueryBuilder::class);

        // Turn 1
        $businessService->buildBusinessContext(
            query: 'Laptop Pro 15 er price koto?',
            conversation: $this->conversation,
            workspaceId: $this->workspace->id,
        );
        $this->conversation->refresh();

        // Turn 2 Context Resolution
        $resolution = $queryBuilder->resolveContext(
            query: 'kivabe order korbo?',
            conversation: $this->conversation,
        );

        $this->assertSame('Laptop Pro 15 kivabe order korbo?', $resolution->resolvedQuery);
        $this->assertSame('active_product_context', $resolution->source);

        // Turn 2 Business Context
        $context = $businessService->buildBusinessContext(
            query: $resolution->resolvedQuery,
            conversation: $this->conversation,
            workspaceId: $this->workspace->id,
        );

        $this->assertNotNull($context);
        $this->assertStringContainsString('Authoritative Order Placement Process', $context);
        $this->assertStringContainsString('Laptop Pro 15', $context);
        $this->assertStringContainsString('Name, Mobile Phone Number, and complete Delivery Address', $context);
    }

    public function test_turn2_general_order_without_prior_product_guides_customer(): void
    {
        $businessService = app(BusinessSourceOfTruthService::class);

        // Turn with no active product
        $context = $businessService->buildBusinessContext(
            query: 'kivabe order korbo?',
            conversation: $this->conversation,
            workspaceId: $this->workspace->id,
        );

        $this->assertNotNull($context);
        $this->assertStringContainsString('Authoritative Order Placement Process', $context);
        $this->assertStringContainsString('provide the name of the desired product', $context);
    }
}
