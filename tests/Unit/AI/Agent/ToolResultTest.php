<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Agent;

use App\AI\DTOs\ToolResult;
use PHPUnit\Framework\TestCase;
use Stringable;

class ToolResultTest extends TestCase
{
    public function test_success_factory_and_properties(): void
    {
        $result = ToolResult::success(
            data: ['sales_total' => 150000, 'orders_count' => 120],
            metadata: ['cached' => true]
        );

        $this->assertTrue($result->success);
        $this->assertSame(['sales_total' => 150000, 'orders_count' => 120], $result->data);
        $this->assertNull($result->error);
        $this->assertSame(['cached' => true], $result->metadata);
    }

    public function test_failure_factory_and_properties(): void
    {
        $result = ToolResult::failure(
            error: 'Database connection failed',
            metadata: ['retryable' => false]
        );

        $this->assertFalse($result->success);
        $this->assertEmpty($result->data);
        $this->assertSame('Database connection failed', $result->error);
        $this->assertSame(['retryable' => false], $result->metadata);
    }

    public function test_implements_stringable_for_laravel_ai_sdk_compatibility(): void
    {
        $result = ToolResult::success(['status' => 'ok']);
        $this->assertInstanceOf(Stringable::class, $result);
    }

    public function test_to_response_string_prioritizes_report_key(): void
    {
        $result = ToolResult::success([
            'report' => "📊 **রিপোর্ট**: মোট বিক্রয় ১,৫০,০০০ টাকা।",
            'raw_sql' => 'SELECT SUM(amount) FROM orders',
        ]);

        $this->assertSame("📊 **রিপোর্ট**: মোট বিক্রয় ১,৫০,০০০ টাকা।", $result->toResponseString());
        $this->assertSame("📊 **রিপোর্ট**: মোট বিক্রয় ১,৫০,০০০ টাকা।", (string) $result);
    }

    public function test_to_response_string_formats_json_when_no_report(): void
    {
        $result = ToolResult::success([
            'category' => 'প্রোডাক্ট',
            'count' => 45,
        ]);

        $this->assertSame('{"category":"প্রোডাক্ট","count":45}', (string) $result);
    }

    public function test_failure_formats_safe_error_string(): void
    {
        $result = ToolResult::failure('Invalid query syntax');
        $this->assertSame('Tool execution failed: Invalid query syntax', (string) $result);
    }

    public function test_empty_success_formats_graceful_string(): void
    {
        $result = ToolResult::success([]);
        $this->assertSame('Tool executed successfully with no returned data.', (string) $result);
    }

    public function test_to_array_and_json_serialize(): void
    {
        $result = ToolResult::success(['total' => 500], ['source' => 'sqlite']);
        $expected = [
            'success'  => true,
            'data'     => ['total' => 500],
            'error'    => null,
            'metadata' => ['source' => 'sqlite'],
        ];

        $this->assertSame($expected, $result->toArray());
        $this->assertSame($expected, $result->jsonSerialize());
        $this->assertSame(json_encode($expected), json_encode($result));
    }
}

