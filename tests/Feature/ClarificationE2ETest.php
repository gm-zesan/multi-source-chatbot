<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class ClarificationE2ETest extends TestCase
{
    use RefreshDatabase;

    private DecisionEngineInterface $engine;
    private Conversation $conversation;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(DecisionEngineInterface::class);

        $this->workspace = Workspace::create(['name' => 'Clarify WS', 'slug' => 'clarify-ws']);
        $channel = Channel::create(['name' => 'Web', 'slug' => 'web-clarify', 'is_active' => true]);
        $channelAccount = ChannelAccount::create([
            'channel_id'   => $channel->id,
            'workspace_id' => $this->workspace->id,
            'external_id'  => 'acc_clarify_test',
            'name'         => 'Clarify Account',
            'access_token' => 'tok',
            'is_active'    => true,
        ]);

        $this->conversation = Conversation::create([
            'channel_account_id' => $channelAccount->id,
            'external_user_id'   => 'user_clarify_test',
            'status'             => 'open',
            'last_direction'     => 'inbound',
            'metadata'           => [],
        ]);
    }

    /**
     * Test A: Ambiguity Generation (Route -> UNCERTAIN, schema mapped).
     */
    public function test_ambiguity_generation_on_uncertain_route(): void
    {
        $routingResult = new RoutingResult(
            route: RouteType::UNCERTAIN,
            confidence: 0.9,
            intent: 'uncertain',
            ambiguityType: 'AMOUNT_AMBIGUOUS'
        );
        
        // Pass it through ClarificationManager to test state persistence
        $clarificationManager = app(\App\AI\Context\ClarificationManager::class);
        $jsonResponse = $clarificationManager->handleAnalyticAmbiguity($this->conversation, 'Rahim er taka koto?', $routingResult);
        
        $this->conversation->refresh();
        $this->assertNotNull($this->conversation->metadata['pending_clarification']);
        $this->assertSame('AMOUNT_AMBIGUOUS', $this->conversation->metadata['pending_clarification']['ambiguity_type']);
        $this->assertStringContainsString('মোট কেনাকাটা', $jsonResponse);
    }

    /**
     * Test B: Numeric Resolution ("3" -> customer_outstanding_due).
     */
    public function test_numeric_resolution(): void
    {
        $this->setupPendingClarification();

        // Send "3"
        $request = new DecisionRequest(
            query: '3',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
            isCustomer: false
        );
        $decision = $this->engine->decide($request);
        
        $this->assertSame('analytics', $decision->toRouteType()->value);
        $this->assertSame('customer_outstanding_due', $decision->decisionMetadata['resolved_intent'] ?? null);
        
        // Ensure state is cleared
        $this->conversation->refresh();
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->metadata ?? []);
    }

    /**
     * Test C: Exact Option ID Resolution ("due" -> customer_outstanding_due).
     */
    public function test_option_id_resolution(): void
    {
        $this->setupPendingClarification();

        // Send "due"
        $request = new DecisionRequest(
            query: 'due',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
            isCustomer: false
        );
        $decision = $this->engine->decide($request);
        
        $this->assertSame('analytics', $decision->toRouteType()->value);
        $this->assertSame('customer_outstanding_due', $decision->decisionMetadata['resolved_intent'] ?? null);
    }

    /**
     * Test D: Anti-Tampering (User types semantic_value directly -> must fail resolution and route normally).
     */
    public function test_anti_tampering_resolution(): void
    {
        $this->setupPendingClarification();

        // Send "customer_outstanding_due" directly which is the semantic_value, but not the ID/label
        $request = new DecisionRequest(
            query: 'customer_outstanding_due',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
            isCustomer: false
        );
        $decision = $this->engine->decide($request);
        
        // Must NOT resolve through layer0_pending_clarification
        $this->assertNotSame('layer0_pending_clarification', $decision->decisionMetadata['layer'] ?? null);
        
        // Ensure state is cleared because Layer 0 failed to resolve
        $this->conversation->refresh();
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->metadata ?? []);
    }

    /**
     * Test E: Expiry (Advance carbon time by 15 mins -> state cleared, routes normally).
     */
    public function test_state_expiry(): void
    {
        $this->setupPendingClarification();

        Carbon::setTestNow(now()->addMinutes(15));

        $request = new DecisionRequest(
            query: '3',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
            isCustomer: false
        );
        $decision = $this->engine->decide($request);
        
        $this->assertNotSame('layer0_pending_clarification', $decision->decisionMetadata['layer'] ?? null);
        
        $this->conversation->refresh();
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->metadata ?? []);
        
        Carbon::setTestNow();
    }

    /**
     * Test F: Unrelated Query (User asks a different question -> state cleared, routes normally).
     */
    public function test_unrelated_query_clears_state(): void
    {
        $this->setupPendingClarification();

        $request = new DecisionRequest(
            query: 'What is todays sales?',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
            isCustomer: false
        );
        $decision = $this->engine->decide($request);
        
        $this->assertSame('analytics', $decision->toRouteType()->value);
        
        $this->conversation->refresh();
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->metadata ?? []);
    }

    /**
     * G. Replay Prevention: Once consumed, the clarification cannot be reused.
     */
    public function test_clarification_cannot_be_replayed()
    {
        $this->setupPendingClarification();

        // Turn 1: Valid resolution
        $request1 = new DecisionRequest(
            query: '3',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
            isCustomer: false
        );
        $decision1 = $this->engine->decide($request1);
        $this->assertSame('analytics', $decision1->toRouteType()->value);
        $this->assertSame('customer_outstanding_due', $decision1->decisionMetadata['resolved_intent'] ?? null);

        // Verify state is consumed
        $this->conversation->refresh();
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->metadata ?? []);

        // Turn 2: Try to reply '3' again
        $request2 = new DecisionRequest(
            query: '3',
            workspaceId: $this->workspace->id,
            conversation: $this->conversation,
            isCustomer: false
        );
        $decision2 = $this->engine->decide($request2);
        
        // Should not be layer0_pending_clarification
        $this->assertNotSame('layer0_pending_clarification', $decision2->decisionMetadata['layer'] ?? null);
    }

    private function setupPendingClarification(): void
    {
        $this->conversation->metadata = [
            'pending_clarification' => [
                'original_query' => 'Rahim er taka koto?',
                'ambiguity_type' => 'AMOUNT_AMBIGUOUS',
                'options' => [
                    ['id' => 'total_sales', 'label' => 'মোট কেনাকাটা', 'semantic_value' => 'customer_total_sales'],
                    ['id' => 'total_payment', 'label' => 'মোট পেমেন্ট', 'semantic_value' => 'customer_total_payments'],
                    ['id' => 'due', 'label' => 'বাকি টাকা', 'semantic_value' => 'customer_outstanding_due'],
                ],
                'created_at' => now()->toIso8601String(),
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ]
        ];
        $this->conversation->save();
    }
}
