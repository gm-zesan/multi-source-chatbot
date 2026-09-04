<?php

declare(strict_types=1);

namespace Tests\Feature\E2E;

use App\AI\Agents\CustomerSupportAgent;
use App\AI\Routing\RouteType;
use App\AI\Routing\RoutingResult;
use Tests\Feature\E2E\Support\BaseE2ETestCase;
use Tests\Feature\E2E\Support\E2EObservabilityTracer;

/**
 * E2E-08: Latest Order, Pronoun, and Cross-Session Context Resolution Test
 * 
 * Verifies:
 * - Implicit latest order retrieval when user asks "amar parcel ta kothay?" without mentioning #1042
 * - Local turn anaphora pronoun resolution ("ওটা কবে পাবো?")
 * - Cross-session entity resolution via KGM when local conversation turns are unavailable
 */
class E2E08LatestOrderPronounContextTest extends BaseE2ETestCase
{
    /**
     * Section 8: Implicit Latest Order Tracking (Zero clarification requested)
     */
    public function test_implicit_latest_order_resolved_from_kgm_without_asking_clarification(): void
    {
        $query = 'amar parcel ta kothay?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.94, 'order_tracking'));

        // Neo4j returns the single latest active order #1042
        $this->memoryClientMock->shouldReceive('search')
            ->once()
            ->andReturn([
                'has_memories'            => true,
                'memories'                => [
                    [
                        'subject'    => 'customer',
                        'predicate'  => 'has_latest_order',
                        'object'     => '#1042',
                        'status'     => 'In Transit',
                    ],
                ],
                'formatted_memory_context' => "Customer Orders:\n- Latest Order #1042 (In Transit, Est. Delivery Tomorrow)",
            ]);

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->orderFaq, 0.90, 'semantic'));

        CustomerSupportAgent::fake([
            'আপনার লেটেস্ট অর্ডার #1042 বর্তমানে ইন-ট্রানজিটে রয়েছে এবং আগামীকাল ডেলিভারি হবে।',
        ]);

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertFalse($result['is_handoff']);
        $this->assertStringContainsString('#1042', $result['reply']);
        $this->assertStringContainsString('আগামীকাল', $result['reply']);

        $trace = E2EObservabilityTracer::trace($query, $result, [
            'language'              => 'banglish',
            'active_topic'          => 'Order_Tracking',
            'entity'                => ['type' => 'Order', 'id' => '1042'],
            'memory_decision'       => 'RETRIEVE',
            'memory_service_called' => true,
            'memory_found'          => true,
            'tier_used'             => 1,
        ]);

        $this->assertTraceDimensions($trace, [
            'route'         => 'knowledge',
            'context'       => ['status' => 'resolved'],
            'clarification' => ['requested' => false],
            'result'        => 'PASS',
        ]);
    }

    /**
     * Section 11: Local Turn Pronoun Resolution ("ওটা কবে পাবো?")
     */
    public function test_local_turn_pronoun_resolves_to_recent_order(): void
    {
        $this->recordTurn(
            'আমি #1042 অর্ডার করেছি।',
            'outbound',
            'জি, আপনার অর্ডার #1042 কনফার্ম করা হয়েছে।'
        );

        $query = 'ওটা কবে পাবো?';

        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.95, 'order_tracking'));

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->withArgs(function ($q, $perPage, $wsId, $conv, $signal) {
                return str_contains((string) $signal, '#1042');
            })
            ->andReturn($this->createHitCollection($this->orderFaq, 0.92, 'semantic'));

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertFalse($result['is_handoff']);
    }

    /**
     * Section 11: Cross-Session KGM Resolution (Local turns unavailable)
     */
    public function test_cross_session_pronoun_resolves_via_kgm_when_local_turns_absent(): void
    {
        // Conversation has NO prior turns in this session!
        $this->assertSame(0, $this->conversation->messages()->count());

        $query = 'ওটার ডেলিভারি কবে?';

        // M2 will see pronoun without local turns, but KGM has the single ordered product
        $this->routerMock->shouldReceive('route')
            ->once()
            ->andReturn(new RoutingResult(RouteType::KNOWLEDGE, 0.92, 'delivery_inquiry'));

        $this->memoryClientMock->shouldReceive('search')
            ->atLeast()->once()
            ->andReturn([
                'has_memories'            => true,
                'memories'                => [
                    [
                        'subject'   => 'customer',
                        'predicate' => 'ordered_product',
                        'object'    => 'Black Cotton Panjabi',
                    ],
                ],
                'formatted_memory_context' => "Recent Purchases:\n- Black Cotton Panjabi (Order #1042)",
            ]);

        $this->faqSearchMock->shouldReceive('search')
            ->once()
            ->andReturn($this->createHitCollection($this->orderFaq, 0.89, 'semantic'));

        CustomerSupportAgent::fake([
            'আপনার Black Cotton Panjabi এর ডেলিভারি ২ কর্মদিবসের মধ্যে সম্পন্ন হবে।',
        ]);

        $result = $this->service->handleQuery($query, $this->workspace->id, $this->conversation);

        $this->assertSame('knowledge', $result['route']);
        $this->assertStringContainsString('Black Cotton Panjabi', $result['reply']);
    }
}
