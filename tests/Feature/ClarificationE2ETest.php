<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Conversation;
use App\AI\Routing\HybridRouter;
use App\AI\Routing\RoutingResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

class ClarificationE2ETest extends TestCase
{
    use RefreshDatabase;

    private HybridRouter $router;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = app(HybridRouter::class);
        $this->conversation = new Conversation([
            'session_id' => 'test-session',
            'user_id' => 1,
            'metadata' => []
        ]);
    }

    /**
     * Test A: Ambiguity Generation (Route -> UNCERTAIN, schema mapped).
     */
    public function test_ambiguity_generation_on_uncertain_route(): void
    {
        // We mock the LLM client to return UNCERTAIN + AMOUNT_AMBIGUOUS
        $llmClientMock = \Mockery::mock(\App\AI\LLM\LLMClient::class);
        $llmClientMock->shouldReceive('generate')->withAnyArgs()->andReturn(new \App\AI\LLM\LLMResponse(
            content: json_encode([
                'route' => 'UNCERTAIN',
                'confidence' => 0.9,
                'reason' => 'ambiguous',
                'security_status' => 'allowed',
                'ambiguity_type' => 'AMOUNT_AMBIGUOUS'
            ]),
            provider: 'test',
            model: 'test'
        ));
        
        $router = new HybridRouter(0.70, $llmClientMock);
        
        $routingResult = $router->route('Rahim er taka koto?', $this->conversation, 1);
        
        $this->assertSame('uncertain', $routingResult->route->value);
        $this->assertSame('AMOUNT_AMBIGUOUS', $routingResult->ambiguityType);
        
        // Pass it through ClarificationManager to test state persistence
        $clarificationManager = app(\App\Services\AI\ClarificationManager::class);
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
        $routingResult = $this->router->route('3', $this->conversation, 1);
        
        $this->assertSame('analytics', $routingResult->route->value);
        $this->assertSame('customer_outstanding_due', $routingResult->intent);
        
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
        $routingResult = $this->router->route('due', $this->conversation, 1);
        
        $this->assertSame('analytics', $routingResult->route->value);
        $this->assertSame('customer_outstanding_due', $routingResult->intent);
    }

    /**
     * Test D: Anti-Tampering (User types semantic_value directly -> must fail resolution and route normally).
     */
    public function test_anti_tampering_resolution(): void
    {
        $this->setupPendingClarification();

        // Mock LLM to return OOD or CHAT for malicious query
        $llmClientMock = \Mockery::mock(\App\AI\LLM\LLMClient::class);
        $llmClientMock->shouldReceive('generate')->withAnyArgs()->andReturn(new \App\AI\LLM\LLMResponse(
            content: json_encode([
                'route' => 'OOD',
                'confidence' => 0.9,
                'reason' => 'tampering attempt',
                'security_status' => 'allowed',
            ]),
            provider: 'test',
            model: 'test'
        ));
        
        $router = new HybridRouter(0.70, $llmClientMock);

        // Send "customer_outstanding_due" directly which is the semantic_value, but not the ID/label
        $routingResult = $router->route('customer_outstanding_due', $this->conversation, 1);
        
        // Must NOT resolve to ANALYTICS intent=customer_outstanding_due. Should route to OOD.
        $this->assertSame('ood', $routingResult->route->value);
        
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

        // Mock LLM for normal route
        $llmClientMock = \Mockery::mock(\App\AI\LLM\LLMClient::class);
        $llmClientMock->shouldReceive('generate')->withAnyArgs()->andReturn(new \App\AI\LLM\LLMResponse(
            content: json_encode([
                'route' => 'CHAT',
                'confidence' => 0.9,
                'reason' => 'normal chat',
                'security_status' => 'allowed',
            ]),
            provider: 'test',
            model: 'test'
        ));
        
        $router = new HybridRouter(0.70, $llmClientMock);

        Carbon::setTestNow(now()->addMinutes(15));

        $routingResult = $router->route('3', $this->conversation, 1);
        
        $this->assertSame('chat', $routingResult->route->value);
        
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

        // Mock LLM for normal route
        $llmClientMock = \Mockery::mock(\App\AI\LLM\LLMClient::class);
        $llmClientMock->shouldReceive('generate')->withAnyArgs()->andReturn(new \App\AI\LLM\LLMResponse(
            content: json_encode([
                'route' => 'ANALYTICS',
                'confidence' => 0.9,
                'reason' => 'new unrelated query',
                'security_status' => 'allowed',
            ]),
            provider: 'test',
            model: 'test'
        ));
        
        $router = new HybridRouter(0.70, $llmClientMock);

        $routingResult = $router->route('What is todays sales?', $this->conversation, 1);
        
        $this->assertSame('analytics', $routingResult->route->value);
        
        $this->conversation->refresh();
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->metadata ?? []);
    }

    /**
     * G. Replay Prevention: Once consumed, the clarification cannot be reused.
     */
    public function test_clarification_cannot_be_replayed()
    {
        $this->setupPendingClarification();
        
        // Setup mock LLM for fallback when it's not a valid clarification anymore
        $llmClientMock = \Mockery::mock(\App\AI\LLM\LLMClient::class);
        $llmClientMock->shouldReceive('generate')->withAnyArgs()->andReturn(new \App\AI\LLM\LLMResponse(
            content: json_encode([
                'route' => 'CHAT',
                'confidence' => 0.9,
                'reason' => 'user just sent a number',
                'security_status' => 'allowed',
            ]),
            provider: 'test',
            model: 'test'
        ));
        $router = new HybridRouter(0.70, $llmClientMock);

        // Turn 1: Valid resolution
        $routingResult1 = $router->route('3', $this->conversation, 1);
        $this->assertSame('analytics', $routingResult1->route->value);
        $this->assertSame('customer_outstanding_due', $routingResult1->intent);

        // Verify state is consumed
        $this->conversation->refresh();
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->metadata ?? []);

        // Turn 2: Try to reply '3' again
        $routingResult2 = $router->route('3', $this->conversation, 1);
        
        // Should hit LLM (CHAT) because Layer 0 no longer has the state
        $this->assertSame('chat', $routingResult2->route->value);
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
    }
}
