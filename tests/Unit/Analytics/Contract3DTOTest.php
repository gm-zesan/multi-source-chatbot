<?php

declare(strict_types=1);

namespace Tests\Unit\Analytics;

use App\Services\Analytics\DTOs\Contract3\AnalyticsExecutionResponse;
use App\Services\Analytics\DTOs\Contract3\ExecutionAuthorizationGrant;
use App\Services\Analytics\DTOs\Contract3\GrantSigner;
use App\Services\Analytics\DTOs\Contract3\PreparedQueryArtifact;
use App\Services\Analytics\DTOs\Contract3\SemanticQueryPlan;
use PHPUnit\Framework\TestCase;

class Contract3DTOTest extends TestCase
{
    public function test_semantic_query_plan_serialization(): void
    {
        $plan = new SemanticQueryPlan(
            domain: 'sales',
            measures: [['name' => 'amount', 'aggregation' => 'sum', 'alias' => 'total_sales']],
            dimensions: [['name' => 'customer_name', 'alias' => 'customer']],
            filters: [['field' => 'status', 'operator' => '=', 'value' => 'completed']],
            groupBy: ['customer_name'],
            orderBy: [['field' => 'amount', 'direction' => 'desc']],
            limit: 20,
        );

        $array = $plan->toArray();
        $this->assertSame('sales', $array['domain']);
        $this->assertCount(1, $array['measures']);
        $this->assertSame('completed', $array['filters'][0]['value']);

        $restored = SemanticQueryPlan::fromArray($array);
        $this->assertSame($plan->domain, $restored->domain);
        $this->assertSame($plan->limit, $restored->limit);
        $this->assertSame('total_sales', $restored->measures[0]['alias']);
    }

    public function test_prepared_query_artifact_serialization(): void
    {
        $artifact = new PreparedQueryArtifact(
            contractVersion: '3.0.0',
            requestId: 'req_123',
            workspaceId: 5,
            datasetId: 'ds_orders',
            generationId: 'gen_001',
            queryFingerprint: str_repeat('a', 64),
            schemaFingerprint: str_repeat('b', 64),
            sqlTemplate: 'SELECT SUM(amount) FROM orders WHERE status = ?',
            params: ['completed'],
            referencedTables: ['orders'],
            referencedColumns: ['amount', 'status'],
            confirmedEdgeIds: ['edge_1'],
        );

        $array = $artifact->toArray();
        $this->assertSame('3.0.0', $array['contract_version']);
        $this->assertSame(5, $array['workspace_id']);
        $this->assertSame(['completed'], $array['params']);

        $restored = PreparedQueryArtifact::fromArray($array);
        $this->assertSame($artifact->queryFingerprint, $restored->queryFingerprint);
        $this->assertSame($artifact->sqlTemplate, $restored->sqlTemplate);
        $this->assertSame($artifact->confirmedEdgeIds, $restored->confirmedEdgeIds);
    }

    public function test_grant_signer_hmac_symmetry_with_python(): void
    {
        $secret = 'test_shared_secret_key_12345';
        $workspaceId = 42;
        $fingerprint = str_repeat('f', 64);
        $generationId = 'gen_xyz_987';
        $expiresAt = '2026-11-02T12:00:00Z';
        $nonce = 'unique_nonce_abc123';

        // Expected message: "42:ffff...:gen_xyz_987:2026-11-02T12:00:00Z:unique_nonce_abc123"
        $expectedSignature = hash_hmac(
            'sha256',
            "{$workspaceId}:{$fingerprint}:{$generationId}:{$expiresAt}:{$nonce}",
            $secret
        );

        $actualSignature = GrantSigner::sign(
            workspaceId: $workspaceId,
            queryFingerprint: $fingerprint,
            generationId: $generationId,
            expiresAt: $expiresAt,
            nonce: $nonce,
            secret: $secret
        );

        $this->assertSame($expectedSignature, $actualSignature);
        $this->assertTrue(GrantSigner::verify(
            signature: $actualSignature,
            workspaceId: $workspaceId,
            queryFingerprint: $fingerprint,
            generationId: $generationId,
            expiresAt: $expiresAt,
            nonce: $nonce,
            secret: $secret
        ));

        // Wrong secret verification fails
        $this->assertFalse(GrantSigner::verify(
            signature: $actualSignature,
            workspaceId: $workspaceId,
            queryFingerprint: $fingerprint,
            generationId: $generationId,
            expiresAt: $expiresAt,
            nonce: $nonce,
            secret: 'wrong_secret'
        ));
    }

    public function test_execution_authorization_grant_expiry(): void
    {
        $grant = new ExecutionAuthorizationGrant(
            grantId: 'grant_1',
            workspaceId: 1,
            queryFingerprint: str_repeat('a', 64),
            generationId: 'gen_1',
            authorizedAt: gmdate('c', time() - 100),
            expiresAt: gmdate('c', time() - 10), // Expired 10 seconds ago
            nonce: 'nonce_1',
            signature: 'sig_1',
        );

        $this->assertTrue($grant->isExpired());

        $freshGrant = new ExecutionAuthorizationGrant(
            grantId: 'grant_2',
            workspaceId: 1,
            queryFingerprint: str_repeat('a', 64),
            generationId: 'gen_1',
            authorizedAt: gmdate('c'),
            expiresAt: gmdate('c', time() + 60), // Fresh for 60 seconds
            nonce: 'nonce_2',
            signature: 'sig_2',
        );

        $this->assertFalse($freshGrant->isExpired());
    }

    public function test_analytics_execution_response_deserialization(): void
    {
        $data = [
            'success' => true,
            'contract_version' => '3.0.0',
            'request_id' => 'req_001',
            'workspace_id' => 7,
            'dataset_id' => 'ds_sales',
            'generation_id' => 'gen_007',
            'rows' => [
                ['product' => 'Oil', 'total_amount' => 1500.0],
            ],
            'columns' => ['product', 'total_amount'],
            'row_count' => 1,
            'report' => 'Report content',
            'latency_ms' => 4.25,
        ];

        $resp = AnalyticsExecutionResponse::fromArray($data);
        $this->assertTrue($resp->success);
        $this->assertSame(7, $resp->workspaceId);
        $this->assertSame(1, $resp->rowCount);
        $this->assertSame(1500.0, $resp->rows[0]['total_amount']);
        $this->assertSame(4.25, $resp->latencyMs);
    }
}

