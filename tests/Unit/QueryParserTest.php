<?php

namespace Tests\Unit;

use App\Services\QueryParser;
use PHPUnit\Framework\TestCase;

class QueryParserTest extends TestCase
{
    public function test_it_extracts_intent_without_source_detection(): void
    {
        $parser = new QueryParser();

        $intent = $parser->parse('show customers limit 5 where status = active order by name desc');

        $this->assertSame('select', $intent['action']);
        $this->assertSame('customers', $intent['table']);
        $this->assertSame(5, $intent['limit']);
        $this->assertSame(['name'], $intent['columns']);
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
        $this->assertArrayNotHasKey('source', $intent);
    }

    public function test_it_detects_product_and_sales_tables(): void
    {
        $parser = new QueryParser();

        $productIntent = $parser->parse('show products');
        $salesIntent = $parser->parse('show sales');
        $productColumnIntent = $parser->parse('show product name price');
        $salesColumnIntent = $parser->parse('show sales total amount order date');
        $productWhereIntent = $parser->parse('show product where id = 5');

        $this->assertSame('products', $productIntent['table']);
        $this->assertSame('sales', $salesIntent['table']);
        $this->assertSame(['product_name', 'price'], $productColumnIntent['columns']);
        $this->assertSame(['total_amount', 'order_date'], $salesColumnIntent['columns']);
        $this->assertSame(['*'], $productWhereIntent['columns']);
        $this->assertSame([
            [
                'column' => 'id',
                'operator' => '=',
                'value' => '5',
            ],
        ], $productWhereIntent['filters']);
    }
}
