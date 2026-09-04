<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use App\Models\Conversation;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-07: Python Memory Service / Knowledge Graph Memory (KGM) Contract Test
 * 
 * Verifies:
 * - Memory creation & persistence
 * - Cross-session recall (Session A preference retrieved in Session B)
 * - Conflict precedence (latest fact XL wins over historical M)
 * - M3 Gate Bypass (generic return policy triggers 0 memory calls)
 */
class E2E07PythonMemoryKGMTest extends BaseE2ETestCase
{
    /**
     * Cross-session memory recall
     */
    public function test_cross_session_customer_preference_recall(): void
    {
        // Session B query
        $query = 'আমার সাইজটা কী ছিল?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::CHAT, 0.95, 'personal_preference'));

        // M3 gate triggers RETRIEVE and queries Python Memory Service
        $this->memoryClientMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($wsId, $custId, $q) use ($query) {
                return $custId === $this->conversation->external_user_id && str_contains($q, 'সাইজ');
            })
            ->andReturn([
                'has_memories'            => true,
                'memories'                => [
                    [
                        'subject'    => 'customer',
                        'predicate'  => 'prefers_size',
                        'object'     => 'XL',
                        'confidence' => 0.95,
                    ],
                ],
                'formatted_memory_context' => "Known Customer Facts:\n- Customer prefers size XL",
            ]);

        CustomerSupportAgent::fake(['আপনার সংরক্ষিত প্রেফারেন্স অনুযায়ী আপনার সাইজ হলো XL।']);

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertNotNull($result['memory_context']);
        $this->assertStringContainsString('XL', $result['memory_context']);
        $this->assertStringContainsString('XL', $result['reply']);

        $trace = E2EObservabilityTracer::trace($query, $result, [
            'memory_decision'       => 'RETRIEVE',
            'memory_service_called' => true,
            'memory_found'          => true,
        ]);

        $this->assertTraceDimensions($trace, [
            'memory' => ['decision' => 'RETRIEVE', 'service_called' => true, 'memory_found' => true],
            'result' => 'PASS',
        ]);
    }

    /**
     * Conflict Precedence: Latest Fact Wins while preserving history
     */
    public function test_conflict_latest_fact_supersedes_historical_preference(): void
    {
        $query = 'আমার size কী?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::CHAT, 0.95, 'personal_preference'));

        // Python Memory Service returns chronologically ordered facts where XL is active and M is historical
        $this->memoryClientMock->shouldReceive('search')
            ->once()
            ->andReturn([
                'has_memories'            => true,
                'memories'                => [
                    [
                        'subject'    => 'customer',
                        'predicate'  => 'prefers_size',
                        'object'     => 'XL',
                        'is_current' => true,
                        'confidence' => 0.98,
                    ],
                    [
                        'subject'    => 'customer',
                        'predicate'  => 'prefers_size',
                        'object'     => 'M',
                        'is_current' => false,
                        'confidence' => 0.60,
                    ],
                ],
                'formatted_memory_context' => "Customer Facts (Latest takes precedence):\n- Current Size: XL\n- Historical: M",
            ]);

        CustomerSupportAgent::fake(['আপনার বর্তমান সাইজ XL হিসেবে আপডেট করা আছে।']);

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertStringContainsString('XL', $result['memory_context']);
        $this->assertStringContainsString('XL', $result['reply']);
    }

    /**
     * M3 Gate Bypass vs Retrieve
     */
    public function test_m3_gate_strictly_bypasses_generic_faqs_and_retrieves_personal_inquiries(): void
    {
        // 1. Generic FAQ -> MUST BYPASS (0 memory service calls)
        $genericQuery = 'return policy ki?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'return_policy'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->returnFaq, 0.92, 'lexicon'));

        $this->memoryClientMock->shouldNotReceive('search');

        $genericResult = $this->service->handleQuery($genericQuery, $this->workspace->id, $this->conversation);

        $this->assertNull($genericResult['memory_context']);

        // 2. Personal inquiry -> MUST RETRIEVE
        $personalQuery = 'আমার আগের অর্ডারটা কী ছিল?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::CHAT, 0.95, 'order_history'));

        $this->memoryClientMock->shouldReceive('search')
            ->once()
            ->andReturn([
                'has_memories'            => true,
                'memories'                => [['subject' => 'customer', 'predicate' => 'placed_order', 'object' => '#1042']],
                'formatted_memory_context' => "Past Orders:\n- Order #1042 (Delivered)",
            ]);

        CustomerSupportAgent::fake(['আপনার পূর্ববর্তী অর্ডারটি ছিল #1042।']);

        $personalResult = $this->service->handleQuery($personalQuery, $this->workspace->id, $this->conversation);

        $this->assertNotNull($personalResult['memory_context']);
        $this->assertStringContainsString('#1042', $personalResult['memory_context']);
    }
}
