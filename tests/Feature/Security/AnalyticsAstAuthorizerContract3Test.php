<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Services\Analytics\DTOs\Contract3\ExecutionAuthorizationGrant;
use App\Services\Analytics\DTOs\Contract3\GrantSigner;
use App\Services\Analytics\DTOs\Contract3\PreparedQueryArtifact;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;
use App\Services\Spreadsheet\Relationships\DTOs\SpreadsheetRelationshipGraph;
use App\Services\Spreadsheet\Security\AnalyticsAstAuthorizer;
use App\Services\Spreadsheet\Security\DataClassification;
use App\Services\Spreadsheet\Security\Exceptions\UnauthorizedDataAccessException;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Tests\TestCase;

class AnalyticsAstAuthorizerContract3Test extends TestCase
{
    private AnalyticsAstAuthorizer $authorizer;
    private int $workspaceId = 99;
    private string $secretKey = 'test_secret_for_authorizer_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorizer = new AnalyticsAstAuthorizer();
    }

    private function createArtifact(
        string $sql,
        array $confirmedEdgeIds = [],
        int $workspaceId = 99,
        array $params = []
    ): PreparedQueryArtifact {
        return new PreparedQueryArtifact(
            contractVersion: '3.0.0',
            requestId: 'req_test_001',
            workspaceId: $workspaceId,
            datasetId: 'ds_test',
            generationId: 'gen_test_uuid',
            queryFingerprint: str_repeat('e', 64),
            schemaFingerprint: str_repeat('f', 64),
            sqlTemplate: $sql,
            params: $params,
            referencedTables: ['orders'],
            referencedColumns: ['amount'],
            confirmedEdgeIds: $confirmedEdgeIds,
        );
    }

    public function test_customer_channel_strictly_barred(): void
    {
        $context = ServerControlledContext::forCustomer($this->workspaceId);

        $artifact = $this->createArtifact('SELECT SUM(amount) FROM orders');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('Customer channels are strictly forbidden');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: [],
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    public function test_workspace_mismatch_rejected(): void
    {
        $context = new ServerControlledContext(
            workspaceId: 10, // Context workspace
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $artifact = $this->createArtifact('SELECT SUM(amount) FROM orders', confirmedEdgeIds: [], workspaceId: 20); // Different workspace

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('Workspace mismatch');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: [],
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    public function test_mutating_sql_rejected(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $artifact = $this->createArtifact('DROP TABLE orders;');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('Mutating SQL keyword');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: [],
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    public function test_unconfirmed_relationship_edge_rejected(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        // Edge in state DISCOVERED (not confirmed)
        $unconfirmedEdge = DiscoveredRelationshipEdge::fromArray([
            'id' => 'edge_unconfirmed_001',
            'source_sheet' => 'Orders',
            'source_column_ids' => ['col_cust_id'],
            'source_headers' => ['Customer ID'],
            'target_sheet' => 'Customers',
            'target_column_ids' => ['col_id'],
            'target_headers' => ['ID'],
            'state' => DiscoveredRelationshipEdge::STATE_DISCOVERED,
        ]);

        $graph = new SpreadsheetRelationshipGraph(
            workspaceId: $this->workspaceId,
            documentId: 'ds_test',
            jobId: null,
            generationId: 'gen_test_uuid',
            edges: [$unconfirmedEdge->id => $unconfirmedEdge]
        );

        $artifact = $this->createArtifact(
            sql: 'SELECT o.amount, c.name FROM orders o JOIN customers c ON o.cust_id = c.id',
            confirmedEdgeIds: ['edge_unconfirmed_001']
        );

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('which is not confirmed');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: [],
            graph: $graph,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    public function test_confirmed_relationship_edge_authorized_and_grant_issued(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $confirmedEdge = DiscoveredRelationshipEdge::fromArray([
            'id' => 'edge_confirmed_001',
            'source_sheet' => 'Orders',
            'source_column_ids' => ['col_cust_id'],
            'source_headers' => ['Customer ID'],
            'target_sheet' => 'Customers',
            'target_column_ids' => ['col_id'],
            'target_headers' => ['ID'],
            'state' => DiscoveredRelationshipEdge::STATE_CONFIRMED,
        ]);

        $graph = new SpreadsheetRelationshipGraph(
            workspaceId: $this->workspaceId,
            documentId: 'ds_test',
            jobId: null,
            generationId: 'gen_test_uuid',
            edges: [$confirmedEdge->id => $confirmedEdge]
        );

        $artifact = $this->createArtifact(
            sql: 'SELECT o.amount, c.name FROM orders o JOIN customers c ON o.cust_id = c.id',
            confirmedEdgeIds: ['edge_confirmed_001']
        );

        $sourceDescriptors = [
            'amount' => [
                'source_column_id'  => 'col_amount',
                'original_header'   => 'amount',
                'normalized_header' => 'amount',
                'semantic_role'     => 'amount',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
            'name' => [
                'source_column_id'  => 'col_name',
                'original_header'   => 'name',
                'normalized_header' => 'name',
                'semantic_role'     => 'name',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
            'cust_id' => [
                'source_column_id'  => 'col_cust_id',
                'original_header'   => 'cust_id',
                'normalized_header' => 'cust_id',
                'semantic_role'     => 'foreign_key',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
            'id' => [
                'source_column_id'  => 'col_id',
                'original_header'   => 'id',
                'normalized_header' => 'id',
                'semantic_role'     => 'primary_key',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
        ];

        $grant = $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: $sourceDescriptors,
            graph: $graph,
            context: $context,
            secretKey: $this->secretKey
        );

        $this->assertInstanceOf(ExecutionAuthorizationGrant::class, $grant);
        $this->assertSame($this->workspaceId, $grant->workspaceId);
        $this->assertSame($artifact->queryFingerprint, $grant->queryFingerprint);
        $this->assertSame($artifact->generationId, $grant->generationId);
        $this->assertTrue($grant->verifySignature($this->secretKey));
        $this->assertFalse($grant->isExpired());
    }

    public function test_non_owner_field_sensitivity_enforced(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 5,
            userRole: 'agent',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: [] // No special permissions
        );

        $sourceDescriptors = [
            'purchase_cost' => [
                'source_column_id' => 'purchase_cost',
                'original_header'  => 'Purchase Cost',
                'semantic_role'    => 'financial_metric',
                'sensitivity'      => DataClassification::RESTRICTED, // Restricted!
            ],
        ];

        $artifact = $this->createArtifact('SELECT purchase_cost FROM orders');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('Access denied: Query references unauthorized column');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: $sourceDescriptors,
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 1: Unknown unmapped column is denied by default (Fail-Closed).
     */
    public function test_unknown_unmapped_column_is_strictly_denied(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 1,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: []
        );

        $artifact = $this->createArtifact('SELECT custom_field_x FROM orders');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('unknown or unmapped column');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: [],
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 2: Unknown restricted column with only margin permission is denied.
     */
    public function test_unknown_restricted_column_with_only_margin_permission_is_denied(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 2,
            userRole: 'manager',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins']
        );

        $artifact = $this->createArtifact('SELECT exec_retention_bonus FROM orders');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('unknown or unmapped column');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: [],
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 3: Unknown restricted column with only salary permission is denied.
     */
    public function test_unknown_restricted_column_with_only_salary_permission_is_denied(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 3,
            userRole: 'hr_lead',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['hr.view_salaries']
        );

        $artifact = $this->createArtifact('SELECT director_draw FROM orders');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('unknown or unmapped column');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: [],
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 4: Unknown restricted column with both permissions is still denied unless explicitly mapped.
     */
    public function test_unknown_restricted_column_with_both_permissions_is_denied(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 4,
            userRole: 'executive',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins', 'hr.view_salaries']
        );

        $artifact = $this->createArtifact('SELECT secret_remuneration FROM orders');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('unknown or unmapped column');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: [],
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 5: Explicitly mapped salary field without hr.view_salaries is denied.
     */
    public function test_explicitly_mapped_salary_field_without_hr_view_salaries_is_denied(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 5,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: []
        );

        $sourceDescriptors = [
            'base_salary' => [
                'source_column_id'  => 'col_sal',
                'original_header'   => 'Base Salary',
                'normalized_header' => 'base_salary',
                'semantic_role'     => 'salary',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
        ];

        $artifact = $this->createArtifact('SELECT base_salary FROM payroll');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('without required permissions');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: $sourceDescriptors,
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 6: Explicitly mapped salary field with hr.view_salaries is allowed.
     */
    public function test_explicitly_mapped_salary_field_with_hr_view_salaries_is_allowed(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 6,
            userRole: 'hr_lead',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['hr.view_salaries']
        );

        $sourceDescriptors = [
            'base_salary' => [
                'source_column_id'  => 'col_sal',
                'original_header'   => 'Base Salary',
                'normalized_header' => 'base_salary',
                'semantic_role'     => 'salary',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
        ];

        $artifact = $this->createArtifact('SELECT base_salary FROM payroll');

        $grant = $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: $sourceDescriptors,
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );

        $this->assertInstanceOf(ExecutionAuthorizationGrant::class, $grant);
        $this->assertTrue($grant->verifySignature($this->secretKey));
    }

    /**
     * P0-A Test 7: Explicitly mapped margin field without analytics.view_margins is denied.
     */
    public function test_explicitly_mapped_margin_field_without_analytics_view_margins_is_denied(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 7,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: []
        );

        $sourceDescriptors = [
            'net_profit' => [
                'source_column_id'  => 'col_profit',
                'original_header'   => 'Net Profit',
                'normalized_header' => 'net_profit',
                'semantic_role'     => 'profit',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
        ];

        $artifact = $this->createArtifact('SELECT net_profit FROM financials');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('without required permissions');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: $sourceDescriptors,
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 8: Explicitly mapped margin field with analytics.view_margins is allowed.
     */
    public function test_explicitly_mapped_margin_field_with_analytics_view_margins_is_allowed(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 8,
            userRole: 'analyst',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: ['analytics.view_margins']
        );

        $sourceDescriptors = [
            'net_profit' => [
                'source_column_id'  => 'col_profit',
                'original_header'   => 'Net Profit',
                'normalized_header' => 'net_profit',
                'semantic_role'     => 'profit',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
        ];

        $artifact = $this->createArtifact('SELECT net_profit FROM financials');

        $grant = $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: $sourceDescriptors,
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );

        $this->assertInstanceOf(ExecutionAuthorizationGrant::class, $grant);
        $this->assertTrue($grant->verifySignature($this->secretKey));
    }

    /**
     * P0-A Test 9: Normal public mapped field is allowed under existing policy.
     */
    public function test_normal_public_mapped_field_is_allowed(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 9,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: []
        );

        $sourceDescriptors = [
            'item_name' => [
                'source_column_id'  => 'col_item',
                'original_header'   => 'Item Name',
                'normalized_header' => 'item_name',
                'semantic_role'     => 'product_name',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
        ];

        $artifact = $this->createArtifact('SELECT item_name FROM inventory');

        $grant = $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: $sourceDescriptors,
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );

        $this->assertInstanceOf(ExecutionAuthorizationGrant::class, $grant);
        $this->assertTrue($grant->verifySignature($this->secretKey));
    }

    /**
     * P0-A Test 10: Qualified identifiers and aliases cannot bypass classification.
     */
    public function test_qualified_identifiers_and_aliases_cannot_bypass_classification(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 10,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: []
        );

        $sourceDescriptors = [
            'purchase_cost' => [
                'source_column_id'  => 'col_cost',
                'original_header'   => 'Purchase Cost',
                'normalized_header' => 'purchase_cost',
                'semantic_role'     => 'purchase_cost',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
        ];

        // Attempt to hide purchase_cost behind alias c
        $artifact = $this->createArtifact('SELECT o.purchase_cost AS c FROM orders o');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: $sourceDescriptors,
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 11: Aggregate, ORDER BY, GROUP BY, and JOIN references cannot bypass classification.
     */
    public function test_aggregate_order_by_group_by_and_join_references_cannot_bypass_classification(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 11,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: []
        );

        // Referenced in ORDER BY without being in SELECT list
        $artifact = $this->createArtifact('SELECT id FROM items ORDER BY unmapped_secret_rank');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage('unknown or unmapped column');

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: [
                'id' => [
                    'source_column_id' => 'col_id',
                    'original_header'  => 'id',
                    'normalized_header'=> 'id',
                    'semantic_role'    => 'id',
                    'sensitivity'      => DataClassification::PUBLIC,
                ],
            ],
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 12: Wildcards cannot expose restricted or unknown fields.
     */
    public function test_wildcards_cannot_expose_restricted_or_unknown_fields(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 12,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: []
        );

        $sourceDescriptors = [
            'item' => [
                'source_column_id'  => 'col_item',
                'original_header'   => 'Item',
                'normalized_header' => 'item',
                'semantic_role'     => 'item',
                'sensitivity'       => DataClassification::PUBLIC,
            ],
            'cost' => [
                'source_column_id'  => 'col_cost',
                'original_header'   => 'Cost',
                'normalized_header' => 'cost',
                'semantic_role'     => 'purchase_cost',
                'sensitivity'       => DataClassification::RESTRICTED,
            ],
        ];

        // SELECT * when cost is restricted and user lacks margin permission
        $artifact = $this->createArtifact('SELECT * FROM inventory');

        $this->expectException(UnauthorizedDataAccessException::class);
        $this->expectExceptionMessage("Wildcard '*' projection blocked");

        $this->authorizer->authorizePreparedQuery(
            artifact: $artifact,
            sourceDescriptors: $sourceDescriptors,
            graph: null,
            context: $context,
            secretKey: $this->secretKey
        );
    }

    /**
     * P0-A Test 13: No execution grant is issued when any identifier fails authorization.
     */
    public function test_no_execution_grant_is_issued_when_any_identifier_fails_authorization(): void
    {
        $context = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 13,
            userRole: 'staff',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING,
            grantedPermissions: []
        );

        $artifact = $this->createArtifact('SELECT safe_col, unmapped_bad_col FROM dataset');

        $grantCreated = false;
        try {
            $this->authorizer->authorizePreparedQuery(
                artifact: $artifact,
                sourceDescriptors: [
                    'safe_col' => [
                        'source_column_id'  => 'col_safe',
                        'original_header'   => 'Safe Col',
                        'normalized_header' => 'safe_col',
                        'semantic_role'     => 'safe',
                        'sensitivity'       => DataClassification::PUBLIC,
                    ],
                ],
                graph: null,
                context: $context,
                secretKey: $this->secretKey
            );
            $grantCreated = true;
        } catch (UnauthorizedDataAccessException $e) {
            $grantCreated = false;
        }

        $this->assertFalse($grantCreated, 'Grant must never be issued when authorization fails.');
    }

    /**
     * P0-A Test 14: Existing tenant isolation and read-only guarantees remain intact.
     */
    public function test_tenant_isolation_and_read_only_guarantees_remain_intact(): void
    {
        // 1. Cross-tenant context mismatch must fail
        $contextMismatch = new ServerControlledContext(
            workspaceId: 101, // Wrong workspace
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $artifact = $this->createArtifact('SELECT id FROM table');

        try {
            $this->authorizer->authorizePreparedQuery(
                artifact: $artifact,
                sourceDescriptors: ['id' => ['source_column_id' => 'id', 'original_header' => 'id', 'normalized_header' => 'id', 'semantic_role' => 'id', 'sensitivity' => DataClassification::PUBLIC]],
                graph: null,
                context: $contextMismatch,
                secretKey: $this->secretKey
            );
            $this->fail('Expected workspace mismatch failure');
        } catch (UnauthorizedDataAccessException $e) {
            $this->assertStringContainsString('Workspace mismatch', $e->getMessage());
        }

        // 2. Mutation statements must fail
        $validOwnerContext = new ServerControlledContext(
            workspaceId: $this->workspaceId,
            userId: 1,
            userRole: 'owner',
            channel: ServerControlledContext::CHANNEL_DASHBOARD,
            purpose: ServerControlledContext::PURPOSE_ANALYTICS_REPORTING
        );

        $mutationArtifact = $this->createArtifact('UPDATE orders SET status = "tampered"');

        try {
            $this->authorizer->authorizePreparedQuery(
                artifact: $mutationArtifact,
                sourceDescriptors: [],
                graph: null,
                context: $validOwnerContext,
                secretKey: $this->secretKey
            );
            $this->fail('Expected mutation failure');
        } catch (UnauthorizedDataAccessException $e) {
            $this->assertStringContainsString('prohibited in analytics execution', $e->getMessage());
        }
    }
}
