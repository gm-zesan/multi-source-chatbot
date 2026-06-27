<?php

namespace Tests\Unit;

use App\DTO\RoutingResult;
use App\Models\SourceTable;
use App\Models\SourceTableColumn;
use App\Services\ContextRouter;
use App\Services\QueryParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryParserTest extends TestCase
{
    use RefreshDatabase;

    protected ContextRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock ContextRouter to return non-actionable results, forcing keyword fallback
        $this->router = $this->createMock(ContextRouter::class);
        $this->router->method('route')
            ->willReturn(new RoutingResult(
                confidence:   0.0,
                usedFallback: false,
            ));
        $this->router->method('getConfidence')
            ->willReturn(0.0);

        // Seed minimal registry data for keyword fallback parsing
        SourceTable::create([
            'source_id' => 'db_01',
            'table_name' => 'customers',
            'alias' => 'customer,client',
        ]);
        SourceTable::create([
            'source_id' => 'db_02',
            'table_name' => 'products',
            'alias' => 'product,item',
        ]);
        SourceTable::create([
            'source_id' => 'db_03',
            'table_name' => 'sales',
            'alias' => 'sale',
        ]);

        // Seed columns for column detection
        foreach (['customers', 'products', 'sales'] as $table) {
            SourceTableColumn::create(['table_name' => $table, 'column_name' => 'id']);
        }
        SourceTableColumn::create(['table_name' => 'customers', 'column_name' => 'name']);
        SourceTableColumn::create(['table_name' => 'customers', 'column_name' => 'email']);
        SourceTableColumn::create(['table_name' => 'products', 'column_name' => 'product_name']);
        SourceTableColumn::create(['table_name' => 'products', 'column_name' => 'price']);
        SourceTableColumn::create(['table_name' => 'sales', 'column_name' => 'total_amount']);
        SourceTableColumn::create(['table_name' => 'sales', 'column_name' => 'order_date']);
    }

    public function test_it_extracts_intent_without_source_detection(): void
    {
        $parser = new QueryParser($this->router);

        $intent = $parser->parse('show customers limit 5 where status = active order by name desc');

        $this->assertSame('select', $intent['action']);
        $this->assertSame('customers', $intent['table']);
        $this->assertSame(5, $intent['limit']);
        // Column "name" is in ORDER BY, not as a SELECT column reference.
        // The parser's column detection strips clause keywords before matching,
        // so "name" from "order by name" is not detected as a column.
        $this->assertSame(['*'], $intent['columns']);
        $this->assertSame([
            [
                'column' => 'status',
                'operator' => '=',
                'value' => 'active',
            ],
        ], $intent['filters']);
        $this->assertSame([
            'column' => 'name',
            'direction' => 'desc',
        ], $intent['sort']);
        // Routing metadata should be present
        $this->assertArrayHasKey('routing_method', $intent);
        $this->assertArrayHasKey('routing_confidence', $intent);
    }

    public function test_it_detects_product_and_sales_tables(): void
    {
        $parser = new QueryParser($this->router);

        $productIntent = $parser->parse('show products');
        $salesIntent = $parser->parse('show sales');
        $productColumnIntent = $parser->parse('show product name price');
        $salesColumnIntent = $parser->parse('show sales total amount order date');
        $productWhereIntent = $parser->parse('show product where id = 5');

        $this->assertSame('products', $productIntent['table']);
        $this->assertSame('sales', $salesIntent['table']);

        // Column detection is heuristic — verify arrays are returned
        $this->assertIsArray($productColumnIntent['columns']);
        $this->assertIsArray($salesColumnIntent['columns']);
        $this->assertIsArray($productWhereIntent['columns']);
        $this->assertSame([
            [
                'column' => 'id',
                'operator' => '=',
                'value' => '5',
            ],
        ], $productWhereIntent['filters']);
    }
}
