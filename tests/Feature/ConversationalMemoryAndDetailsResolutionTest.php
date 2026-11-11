<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\Agents\KnowledgeSupportAgent;
use App\AI\Context\ContextualQueryBuilder;
use App\AI\Orchestration\CustomerSupportService;
use App\AI\Routing\HybridRouter;
use App\AI\Routing\RoutingResult;
use App\AI\Routing\RouteType;
use App\Models\AnalyticsProduct;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Services\Business\BusinessSourceOfTruthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationalMemoryAndDetailsResolutionTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private ChannelAccount $channelAccount;
    private Conversation $conversation;
    private CustomerSupportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Demo Workspace', 'slug' => 'demo']);
        $channel = Channel::create(['slug' => 'web', 'name' => 'Web Widget', 'is_active' => true]);

        $this->channelAccount = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $this->workspace->id,
            'name'         => 'Web Widget Account',
            'external_id'  => 'web_001',
            'access_token' => 'token_123',
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $this->channelAccount->id,
            'external_user_id'   => 'user_test_999',
            'status'             => 'active',
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

        $this->service = app(CustomerSupportService::class);
    }

    public function test_raw_json_is_never_leaked_in_agent_output_parser(): void
    {
        $rawOutputWithFollowUp = "আমাদের স্টোরে বর্তমানে Laptop Pro 15 রয়েছে, যার দাম ৳৮৫,০০০।\n\n{\"proposed_follow_up\": \"আপনি কি Laptop Pro 15 সম্পর্কে বিস্তারিত জানতে চান বা অর্ডার করতে চান?\", \"follow_up_type\": \"purchase_interest\"}";

        $parsed = $this->service->parseAgentOutput($rawOutputWithFollowUp);

        $this->assertSame('আমাদের স্টোরে বর্তমানে Laptop Pro 15 রয়েছে, যার দাম ৳৮৫,০০০।', $parsed['answer']);
        $this->assertSame('আপনি কি Laptop Pro 15 সম্পর্কে বিস্তারিত জানতে চান বা অর্ডার করতে চান?', $parsed['proposed_follow_up']);
        $this->assertSame('purchase_interest', $parsed['follow_up_type']);
        $this->assertStringNotContainsString('proposed_follow_up', $parsed['answer']);

        $rawOutputWithNullFollowUp = "আমাদের কাছে শুধু এই মডেলটির নাম ও দাম (৳৮৫,০০০) রয়েছে।\n\n{\"proposed_follow_up\": null, \"follow_up_type\": null}";
        $parsedNull = $this->service->parseAgentOutput($rawOutputWithNullFollowUp);

        $this->assertSame('আমাদের কাছে শুধু এই মডেলটির নাম ও দাম (৳৮৫,০০০) রয়েছে।', $parsedNull['answer']);
        $this->assertNull($parsedNull['proposed_follow_up']);
        $this->assertNull($parsedNull['follow_up_type']);
        $this->assertStringNotContainsString('{', $parsedNull['answer']);
    }

    public function test_elliptical_details_query_is_not_flagged_self_contained(): void
    {
        $builder = app(ContextualQueryBuilder::class);

        $this->assertFalse($builder->isSelfContained('ok. amake details bolo.'));
        $this->assertFalse($builder->isSelfContained('amake details bolo'));
        $this->assertFalse($builder->isSelfContained('details bolo'));
        $this->assertFalse($builder->isSelfContained('বিস্তারিত বলুন'));
        $this->assertFalse($builder->isSelfContained('configuration ki?'));
        $this->assertFalse($builder->isSelfContained('er details bolo'));
    }

    public function test_multi_turn_flow_resolves_laptop_details_without_ood_denial(): void
    {
        // 1. Simulate Turn 1: Product inquiry matches Laptop Pro 15
        $sot = app(BusinessSourceOfTruthService::class);
        $ctxTurn1 = $sot->buildBusinessContext('ki ki laptop ache?', $this->conversation, $this->workspace->id);

        $this->assertNotNull($ctxTurn1);
        $this->assertStringContainsString('Laptop Pro 15', $ctxTurn1);
        $this->assertStringContainsString('85,000', $ctxTurn1);

        // Verify active product was persisted in conversation metadata
        $this->conversation->refresh();
        $this->assertSame('Laptop Pro 15', $this->conversation->metadata['active_product']['name'] ?? null);

        // 2. Simulate Turn 2: "ok. amake details bolo." resolves with active product
        $builder = app(ContextualQueryBuilder::class);
        $res = $builder->resolveContext('ok. amake details bolo.', $this->conversation);

        $this->assertTrue($res->isResolved());
        $this->assertStringContainsString('Laptop Pro 15', (string) $res->resolvedQuery);

        $ctxTurn2 = $sot->buildBusinessContext('ok. amake details bolo.', $this->conversation, $this->workspace->id);
        $this->assertNotNull($ctxTurn2);
        $this->assertStringContainsString('Laptop Pro 15', $ctxTurn2);
        $this->assertStringContainsString('85,000', $ctxTurn2);
        $this->assertStringContainsString('Configuration / Specification Note', $ctxTurn2);
    }

    public function test_missing_category_inquiry_does_not_fall_into_ood_denial(): void
    {
        $sot = app(BusinessSourceOfTruthService::class);
        $ctxPhone = $sot->buildBusinessContext('ki ki phone ache?', $this->conversation, $this->workspace->id);

        $this->assertNotNull($ctxPhone);
        $this->assertStringContainsString('UNAVAILABLE / NOT FOUND', $ctxPhone);
        $this->assertStringContainsString('phone', $ctxPhone);
        $this->assertStringContainsString('Laptop Pro 15', $ctxPhone);
    }
}
