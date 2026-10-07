<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use App\AI\LLM\LLMClient;
use App\AI\Routing\HybridRouter;
use App\AI\Routing\RouteType;
use Tests\TestCase;

class SemanticRoutingDecouplingTest extends TestCase
{
    protected function tearDown(): void
    {
        LLMClient::resetFake();
        parent::tearDown();
    }

    public function test_product_price_query_routes_to_knowledge(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'knowledge',
                'confidence' => 0.95,
                'intent' => 'product_price_inquiry',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Laptop Pro 15 এর দাম কত?');

        $this->assertSame(RouteType::KNOWLEDGE, $result->route);
        $this->assertTrue($result->isKnowledge());
    }

    public function test_product_color_query_routes_to_knowledge(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'knowledge',
                'confidence' => 0.95,
                'intent' => 'product_color_inquiry',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Laptop Pro 15 কোন color আছে?');

        $this->assertSame(RouteType::KNOWLEDGE, $result->route);
        $this->assertTrue($result->isKnowledge());
    }

    public function test_product_specs_query_routes_to_knowledge(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'knowledge',
                'confidence' => 0.95,
                'intent' => 'product_specification_inquiry',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Laptop Pro 15 এর RAM/specification কী?');

        $this->assertSame(RouteType::KNOWLEDGE, $result->route);
        $this->assertTrue($result->isKnowledge());
    }

    public function test_total_sales_today_routes_to_analytics(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'analytics',
                'confidence' => 0.98,
                'intent' => 'sales_aggregation',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('আজকে মোট কত sales হয়েছে?');

        $this->assertSame(RouteType::ANALYTICS, $result->route);
        $this->assertTrue($result->isAnalytics());
    }

    public function test_salesperson_performance_routes_to_analytics(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'analytics',
                'confidence' => 0.98,
                'intent' => 'salesperson_performance',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Hasan কত sales করেছে?');

        $this->assertSame(RouteType::ANALYTICS, $result->route);
        $this->assertTrue($result->isAnalytics());
    }

    public function test_customer_due_routes_to_analytics(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'analytics',
                'confidence' => 0.98,
                'intent' => 'customer_due_inquiry',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('Rahim-এর কত টাকা due?');

        $this->assertSame(RouteType::ANALYTICS, $result->route);
        $this->assertTrue($result->isAnalytics());
    }

    public function test_purchase_intent_routes_to_chat_without_blocked_mutation(): void
    {
        LLMClient::fake([
            json_encode([
                'route' => 'chat',
                'confidence' => 0.95,
                'intent' => 'purchase_intent_lead',
                'security_status' => 'allowed',
            ]),
        ]);

        $router = new HybridRouter();
        $result = $router->route('এই Laptop Pro 15 টা নিতে চাই');

        $this->assertSame(RouteType::CHAT, $result->route);
        $this->assertTrue($result->isChat());
        $this->assertSame('allowed', $result->securityStatus);
    }
}
