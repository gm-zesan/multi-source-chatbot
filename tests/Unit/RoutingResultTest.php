<?php

namespace Tests\Unit;

use App\DTO\RoutingResult;
use PHPUnit\Framework\TestCase;

class RoutingResultTest extends TestCase
{
    /** @test */
    public function it_creates_a_default_routing_result(): void
    {
        $result = new RoutingResult();

        $this->assertNull($result->sourceId);
        $this->assertNull($result->table);
        $this->assertNull($result->queryType);
        $this->assertEquals(0.0, $result->confidence);
        $this->assertEquals(0.0, $result->matchedDbScore);
        $this->assertEquals(0.0, $result->matchedTableScore);
        $this->assertEmpty($result->metadata);
        $this->assertFalse($result->usedFallback);
    }

    /** @test */
    public function it_creates_a_routing_result_with_values(): void
    {
        $result = new RoutingResult(
            sourceId:         'db_01',
            table:            'customers',
            queryType:        'select',
            confidence:       0.85,
            matchedDbScore:   0.82,
            matchedTableScore: 0.91,
            metadata:         ['level' => 'semantic'],
            usedFallback:     false,
        );

        $this->assertEquals('db_01', $result->sourceId);
        $this->assertEquals('customers', $result->table);
        $this->assertEquals('select', $result->queryType);
        $this->assertEquals(0.85, $result->confidence);
        $this->assertEquals(0.82, $result->matchedDbScore);
        $this->assertEquals(0.91, $result->matchedTableScore);
        $this->assertEquals(['level' => 'semantic'], $result->metadata);
        $this->assertFalse($result->usedFallback);
    }

    /** @test */
    public function it_converts_to_array(): void
    {
        $result = new RoutingResult(
            sourceId:         'db_02',
            table:            'products',
            confidence:       0.72,
            matchedDbScore:   0.65,
            matchedTableScore: 0.78,
        );

        $array = $result->toArray();

        $this->assertEquals('db_02', $array['source_id']);
        $this->assertEquals('products', $array['table']);
        $this->assertEquals(0.72, $array['confidence']);
        $this->assertEquals(0.65, $array['matched_db_score']);
        $this->assertEquals(0.78, $array['matched_table_score']);
        $this->assertFalse($array['used_fallback']);
    }

    /** @test */
    public function it_determines_actionable_when_above_threshold(): void
    {
        $result = new RoutingResult(
            sourceId:  'db_01',
            table:     'customers',
            confidence: 0.85,
        );

        $this->assertTrue($result->isActionable(0.6));
        $this->assertTrue($result->isActionable(0.5));
        $this->assertFalse($result->isActionable(0.9));
    }

    /** @test */
    public function it_is_not_actionable_without_table_or_source(): void
    {
        $result = new RoutingResult(confidence: 0.95);

        $this->assertFalse($result->isActionable(0.5));
    }

    /** @test */
    public function it_uses_default_threshold(): void
    {
        $result = new RoutingResult(
            sourceId:  'db_01',
            table:     'customers',
            confidence: 0.6,
        );

        $this->assertTrue($result->isActionable());
    }

    /** @test */
    public function it_generates_routing_description(): void
    {
        $result = new RoutingResult(
            sourceId:     'db_01',
            table:        'customers',
            queryType:    'select',
            confidence:   0.85,
            usedFallback: false,
        );

        $desc = $result->routingDescription();
        $this->assertStringContainsString('source:db_01', $desc);
        $this->assertStringContainsString('table:customers', $desc);
        $this->assertStringContainsString('type:select', $desc);
        $this->assertStringContainsString('semantic', $desc);
        $this->assertStringContainsString('85.0%', $desc);
    }

    /** @test */
    public function it_generates_fallback_description(): void
    {
        $result = new RoutingResult(
            sourceId:     'db_03',
            table:        'sales',
            queryType:    'select',
            confidence:   0.70,
            usedFallback: true,
        );

        $desc = $result->routingDescription();
        $this->assertStringContainsString('keyword_fallback', $desc);
    }
}
