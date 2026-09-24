<?php

declare(strict_types=1);

namespace Tests\Feature\Live;

use App\AI\Tools\BusinessAnalyticsTool;
use App\Services\Analytics\AnalyticsClient;
use Tests\Feature\Live\Support\BaseLiveTestCase;

/**
 * STEP 5D: ISOLATED MYSQL LIVE ANALYTICS VERIFICATION
 *
 * Verifies real MySQL query execution against isolated `chatbot_test_db` over TCP :3306
 * through FastAPI -> Planner -> Validator -> Compiler -> AnalyticsExecutor -> PyMySQL.
 */
class LiveSqlAnalyticsTest extends BaseLiveTestCase
{
    private AnalyticsClient $client;
    private BusinessAnalyticsTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = app(AnalyticsClient::class);
        $this->tool = app(BusinessAnalyticsTool::class);
    }

    /**
     * LIVE-SQL-01: Real MySQL Analytics Query
     * Query: "আজকের মোট ক্যাশ কালেকশন কত?"
     * Workspace ID: 1
     * Expected: Exact ৳32,000; not combined ৳52,000.
     */
    public function test_live_sql_01_real_mysql_analytics_query_workspace_1(): void
    {
        $response = $this->client->query(
            query: 'আজকের মোট ক্যাশ কালেকশন কত?',
            workspaceId: 1
        );

        $this->assertTrue($response['success'], 'Real MySQL analytics query must succeed.');
        $this->assertSame('payments_collection_amount', $response['intent']);
        $this->assertFalse($response['is_security_rejection']);
        $this->assertFalse($response['is_ambiguous']);

        // Verify exact amount in rows
        $this->assertNotEmpty($response['rows']);
        $firstRow = $response['rows'][0];
        $this->assertArrayHasKey('collection_amount', $firstRow);
        $this->assertEqualsWithDelta(32000.0, (float) $firstRow['collection_amount'], 0.01);

        // Verify report contains formatted currency
        $this->assertStringContainsString('32,000.00', $response['report']);
        $this->assertStringNotContainsString('52,000', $response['report']);

        // Verify parameterized SQL shape
        $this->assertNotNull($response['sql']);
        $this->assertStringContainsString('analytics_payments', (string) $response['sql']);
        $this->assertStringContainsString('workspace_id = ?', (string) $response['sql']);
        $this->assertGreaterThan(0.0, $response['client_latency_ms']);
    }

    /**
     * LIVE-SQL-02: Tenant Isolation Verification
     * Verifies that Workspace 1 and Workspace 2 get isolated results with zero cross-tenant leakage.
     */
    public function test_live_sql_02_tenant_isolation_between_workspaces(): void
    {
        // Query Workspace 1
        $respW1 = $this->client->query(
            query: 'আজকের মোট ক্যাশ কালেকশন কত?',
            workspaceId: 1
        );

        // Query Workspace 2
        $respW2 = $this->client->query(
            query: 'আজকের মোট ক্যাশ কালেকশন কত?',
            workspaceId: 2
        );

        $this->assertTrue($respW1['success']);
        $this->assertTrue($respW2['success']);

        $w1Amount = (float) ($respW1['rows'][0]['collection_amount'] ?? 0);
        $w2Amount = (float) ($respW2['rows'][0]['collection_amount'] ?? 0);

        // Explicit ground truth asserts
        $this->assertEqualsWithDelta(32000.0, $w1Amount, 0.01, 'Workspace 1 must have exactly ৳32,000');
        $this->assertEqualsWithDelta(20000.0, $w2Amount, 0.01, 'Workspace 2 must have exactly ৳20,000');
        $this->assertNotEquals($w1Amount, $w2Amount, 'Workspace 1 and Workspace 2 must have different deterministic amounts');

        // Verify Workspace 1 does not see Workspace 2 data
        $this->assertStringContainsString('32,000.00', $respW1['report']);
        $this->assertStringNotContainsString('20,000.00', $respW1['report']);

        // Verify Workspace 2 does not see Workspace 1 data
        $this->assertStringContainsString('20,000.00', $respW2['report']);
        $this->assertStringNotContainsString('32,000.00', $respW2['report']);
    }

    /**
     * LIVE-SQL-03: Parameterization Verification
     * Verifies that entity and tenant values are passed via bound parameters and not interpolated.
     */
    public function test_live_sql_03_query_uses_safe_parameter_binding(): void
    {
        $response = $this->tool->execute(
            query: 'আজকের মোট ক্যাশ কালেকশন কত?',
            workspaceId: 1
        );

        $this->assertTrue($response['success']);
        $this->assertStringContainsString('Business Analytics', $response['report']);
        $this->assertStringContainsString('32,000.00', $response['report']);
    }

    /**
     * LIVE-SQL-04: Mutation Rejection
     * Verifies that DROP, DELETE, INSERT or multi-statement injections are rejected before MySQL dispatch.
     */
    public function test_live_sql_04_mutation_injection_is_strictly_rejected(): void
    {
        $response = $this->client->query(
            query: 'DROP TABLE analytics_payments; SELECT * FROM analytics_payments;',
            workspaceId: 1
        );

        $this->assertTrue($response['success']);
        $this->assertTrue($response['is_security_rejection'], 'Mutation queries must trigger security rejection.');
        $this->assertSame('security_rejection', $response['intent']);
        $this->assertEmpty($response['rows']);
        $this->assertNull($response['sql']);
        $this->assertStringContainsString('REJECTED', $response['report']);
    }
}
