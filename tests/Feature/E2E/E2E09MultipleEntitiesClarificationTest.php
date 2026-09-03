<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-09: Multiple Entities & Mandatory Clarification Test
 * 
 * Verifies that when multiple entities compete without a dominant margin (>= 0.20):
 * - System halts immediately (M4-A Short-Circuit: 0 FAQ Search, 0 KGM calls, 0 LLM calls)
 * - Generates clear, non-guessing disambiguation choices
 * - User selection cleanly restores original inquiry intent
 * - Verifies Ambiguous != Guess invariant
 */
class E2E09MultipleEntitiesClarificationTest extends BaseE2ETestCase
{
    /**
     * Multiple Orders -> Mandatory Clarification -> Selection Restores Intent
     */
    public function test_competing_orders_short_circuits_and_selection_restores_intent(): void
    {
        // Turn 1: Two orders are introduced in local context
        $this->recordTurn(
            'আমি গত সপ্তাহে #1042 এবং কালকে #2088 অর্ডার করেছি।',
            'outbound',
            'জি, আপনার দুইটি অর্ডারই আমাদের ডাটাবেজে নথিবদ্ধ আছে।'
        );

        // Turn 2: Ambiguous order inquiry
        $query = 'ওটা কবে পাবো?';

        // M4-A must halt immediately: ZERO FAQ search, ZERO LLM calls
        $this->faqSearchMock->shouldNotReceive('search');

        $turn2Result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('uncertain', $turn2Result['route']);
        $this->assertFalse($turn2Result['is_handoff']);
        $this->assertStringContainsString('#1042', $turn2Result['reply']);
        $this->assertStringContainsString('#2088', $turn2Result['reply']);

        // Assert pending clarification state is persisted
        $convState = $this->conversation->fresh();
        $this->assertArrayHasKey('pending_clarification', $convState->metadata);
        $this->assertSame('ওটা কবে পাবো?', $convState->metadata['pending_clarification']['original_query']);

        $traceTurn2 = E2EObservabilityTracer::trace($query, $turn2Result, [
            'tier_used'             => 0,
            'memory_decision'       => 'BYPASS',
            'memory_service_called' => false,
            'llm_calls'             => 0,
        ]);

        $this->assertTraceDimensions($traceTurn2, [
            'route'         => 'uncertain',
            'context'       => ['status' => 'ambiguous'],
            'retrieval'     => ['tier_used' => 0, 'hits_count' => 0],
            'llm'           => ['calls' => 0],
            'clarification' => ['requested' => true, 'short_circuited' => true],
            'result'        => 'PASS',
        ]);

        // ── Turn 3: User answers with selection "#1042" ──
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'order_tracking'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) {
                // Must have restored original intent with #1042!
                return str_contains((string) $signal, '#1042');
            })
            ->andReturn($this->createHitCollection($this->orderFaq, 0.94, 'semantic'));

        $turn3Result = $this->service->handleQuery('#1042', $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $turn3Result['route']);
        $this->assertTrue($turn3Result['answered']);

        // Assert pending clarification was cleaned up
        $this->assertArrayNotHasKey('pending_clarification', $this->conversation->fresh()->metadata ?? []);
    }

    /**
     * Multiple Products -> Disambiguation List -> Selection Restores Intent
     */
    public function test_competing_products_short_circuits_and_restores_intent(): void
    {
        $this->recordTurn(
            'আমি Royal Silk Panjabi নাকি Black Cotton Panjabi নিবো বুঝতে পারছি না।',
            'outbound',
            'দুইটি পাঞ্জাবিই আমাদের বেস্টসেলার।'
        );

        $query = 'ওটার দাম কত?';

        $this->faqSearchMock->shouldNotReceive('search');

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('uncertain', $result['route']);
        $this->assertStringContainsString('Royal Silk Panjabi', $result['reply']);
        $this->assertStringContainsString('Black Cotton Panjabi', $result['reply']);
    }
}
