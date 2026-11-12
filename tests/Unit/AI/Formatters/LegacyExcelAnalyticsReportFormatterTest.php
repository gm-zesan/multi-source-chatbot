<?php

declare(strict_types=1);

namespace Tests\Unit\AI\Formatters;

use App\AI\Formatters\LegacyExcelAnalyticsReportFormatter;
use App\Services\Analytics\DTOs\Contract3\AnalyticsExecutionResponse;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;
use Tests\TestCase;

class LegacyExcelAnalyticsReportFormatterTest extends TestCase
{
    private LegacyExcelAnalyticsReportFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new LegacyExcelAnalyticsReportFormatter();
    }

    public function test_format_execution_with_complete_metadata_and_report(): void
    {
        $edge = new DiscoveredRelationshipEdge(
            id: 'edge-1',
            sourceSheet: 'Orders',
            sourceColumnIds: ['col_1'],
            sourceHeaders: ['customer_id'],
            targetSheet: 'Customers',
            targetColumnIds: ['col_2'],
            targetHeaders: ['id'],
            cardinality: '1:N',
            isJunctionTable: false,
            inclusionRatio: 1.0,
            headerSimilarity: 0.9,
            entityAlignment: 0.9,
            typeCompatibility: 1.0,
            confidence: 0.95,
            state: 'CONFIRMED'
        );

        $response = new AnalyticsExecutionResponse(
            success: true,
            contractVersion: '3.0.0',
            requestId: 'req-1',
            workspaceId: 1,
            datasetId: 'ds-1',
            generationId: 'gen-1',
            rows: [],
            columns: [],
            rowCount: 0,
            report: 'আজকের মোট অর্ডার সংখ্যা ১২০টি এবং গড় ভ্যালু ১,৫০০ টাকা।',
            latencyMs: 15.0
        );

        $result = $this->formatter->formatExecution(
            query: 'আজকের মোট বিক্রি কত?',
            filename: 'sales_2026.xlsx',
            recordCount: 5200,
            activeEdges: [$edge],
            executionResponse: $response
        );

        $this->assertStringContainsString('📊 **ব্যবসায়িক অ্যানালিটিক্স ইনসাইট** (sales_2026.xlsx)', $result);
        $this->assertStringContainsString('• মোট প্রসেসকৃত রেকর্ড: 5,200 টি।', $result);
        $this->assertStringContainsString('• সক্রিয় ক্রস-শিট সম্পর্ক (1 টি):', $result);
        $this->assertStringContainsString('Orders ↔ Customers (1:N, কনফিডেন্স: 95%)', $result);
        $this->assertStringContainsString('আজকের মোট অর্ডার সংখ্যা ১২০টি এবং গড় ভ্যালু ১,৫০০ টাকা।', $result);
        $this->assertStringContainsString('• বিক্রয় ও রেভিনিউ সারসংক্ষেপ', $result);
    }

    public function test_format_execution_with_tabular_rows_when_report_is_empty(): void
    {
        $response = new AnalyticsExecutionResponse(
            success: true,
            contractVersion: '3.0.0',
            requestId: 'req-2',
            workspaceId: 1,
            datasetId: 'ds-2',
            generationId: 'gen-2',
            rows: [['total_revenue' => 45000.00]],
            columns: ['total_revenue'],
            rowCount: 1,
            report: '',
            latencyMs: 10.0
        );

        $result = $this->formatter->formatExecution(
            query: 'Total profit margin?',
            filename: 'finance.xlsx',
            recordCount: 300,
            activeEdges: [],
            executionResponse: $response
        );

        $this->assertStringContainsString('📊 **ব্যবসায়িক অ্যানালিটিক্স ইনসাইট** (finance.xlsx)', $result);
        $this->assertStringContainsString('• **Total Revenue**: 45,000.00', $result);
        $this->assertStringContainsString('• মুনাফা ও মার্জিন বিশ্লেষণ', $result);
    }

    public function test_format_no_spreadsheet_returns_safe_bengali_guidance(): void
    {
        $message = $this->formatter->formatNoSpreadsheet();

        $this->assertStringContainsString('No active spreadsheet datasets found', $message);
        $this->assertStringContainsString('ব্যবসায়িক অ্যানালিটিক্স রিপোর্ট: বর্তমানে আপনার ওয়ার্কস্পেসে সক্রিয় কোনো স্প্রেডশিট ডেটাসেট নেই', $message);
    }

    public function test_format_empty_result_returns_clear_bengali_message(): void
    {
        $message = $this->formatter->formatEmptyResult('inventory.csv');

        $this->assertSame(
            "ব্যবসায়িক অ্যানালিটিক্স রিপোর্ট: আপনার স্প্রেডশিট ডেটাসেট 'inventory.csv'-এ অনুরোধকৃত প্রশ্নের জন্য কোনো রেকর্ড পাওয়া যায়নি।",
            $message
        );
    }

    public function test_format_error_sanitizes_sql_and_security_tokens(): void
    {
        $rawError = 'Failed to execute SELECT secret_token, amount FROM users WHERE hmac=abcdef0123456789abcdef0123456789';
        $sanitized = $this->formatter->formatError($rawError);

        $this->assertStringNotContainsString('SELECT', $sanitized);
        $this->assertStringNotContainsString('secret_token', $sanitized);
        $this->assertStringNotContainsString('abcdef0123456789abcdef0123456789', $sanitized);
        $this->assertStringContainsString('ব্যবসায়িক অ্যানালিটিক্স সিস্টেম', $sanitized);
    }

    public function test_topic_insight_resolves_employee_and_general_queries(): void
    {
        $employeeResult = $this->formatter->formatExecution(
            query: 'কর্মচারীদের বেতন তালিকা দিন',
            filename: 'hr.xlsx',
        );
        $this->assertStringContainsString('• কর্মী ও বেতন সংক্রান্ত প্রতিবেদন', $employeeResult);

        $generalResult = $this->formatter->formatExecution(
            query: 'সাধারণ অবস্থা কেমন?',
            filename: 'overview.xlsx',
        );
        $this->assertStringContainsString('• ব্যবসায়িক ডেটা সারাংশ', $generalResult);
    }
}
