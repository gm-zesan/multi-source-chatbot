<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\LLM\LLMClient;
use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use Tests\TestCase;

class HybridRouterTest extends TestCase
{
    protected function tearDown(): void
    {
        LLMClient::resetFake();
        parent::tearDown();
    }

    public function test_empty_query_routes_to_chat(): void
    {
        $router = new HybridRouter();
        $result = $router->route('   ');

        $this->assertSame(RouteType::CHAT, $result->route);
        $this->assertSame(1.0, $result->confidence);
        $this->assertSame('empty_query', $result->intent);
    }

    public function test_deterministic_chat_greetings_routing(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'chat',
                'confidence' => 0.98,
                'intent' => 'greeting',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Hi, hello!');

        $this->assertSame(RouteType::CHAT, $result->route);
        $this->assertTrue($result->isChat());
        $this->assertSame(0.98, $result->confidence);
    }

    public function test_deterministic_knowledge_policy_routing(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'knowledge',
                'confidence' => 0.95,
                'intent' => 'delivery_inquiry',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('What are your shipping rates and return rules?');

        $this->assertSame(RouteType::KNOWLEDGE, $result->route);
        $this->assertTrue($result->isKnowledge());
    }

    public function test_deterministic_analytics_routing(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'analytics',
                'confidence' => 0.99,
                'intent' => 'sales_summary',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Total sales this month and due collections');

        $this->assertSame(RouteType::ANALYTICS, $result->route);
        $this->assertTrue($result->isAnalytics());
    }

    public function test_security_blocked_mutation_enforces_uncertain_route(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'analytics',
                'confidence' => 0.99,
                'intent' => 'delete_orders',
                'security_status' => 'blocked_mutation',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('DELETE FROM orders WHERE id=1');

        $this->assertSame(RouteType::UNCERTAIN, $result->route);
        $this->assertSame('blocked_mutation', $result->securityStatus);
    }

    public function test_security_blocked_scope_override(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'analytics',
                'confidence' => 0.90,
                'intent' => 'switch_workspace',
                'security_status' => 'blocked_scope_override',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Show me data for workspace 999');

        $this->assertSame('blocked_scope_override', $result->securityStatus);
    }

    public function test_low_confidence_routes_to_uncertain(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'knowledge',
                'confidence' => 0.45, // Below 0.70 threshold
                'intent' => 'unclear_question',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter(confidenceThreshold: 0.70);
        $result = $router->route('charge');

        $this->assertSame(RouteType::UNCERTAIN, $result->route);
        $this->assertTrue($result->isUncertain());
    }

    public function test_malformed_llm_json_falls_back_safely_to_uncertain(): void
    {
        LLMClient::fake([
            'Invalid non-json response text',
        ]);

        $router = new HybridRouter();
        $result = $router->route('some question');

        $this->assertSame(RouteType::UNCERTAIN, $result->route);
    }
}
