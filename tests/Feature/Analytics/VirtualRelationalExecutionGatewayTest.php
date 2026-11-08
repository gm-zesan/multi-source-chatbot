<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Models\Conversation;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Models\User;
use App\AI\Orchestration\CustomerSupportService;
use App\AI\Orchestration\OwnerBusinessAssistant;
use App\Services\Analytics\AnalyticsClient;
use App\Services\Analytics\DTOs\Contract3\AnalyticsExecutionResponse;
use App\Services\Analytics\DTOs\Contract3\ExecutionAuthorizationGrant;
use App\Services\Analytics\DTOs\Contract3\GrantSigner;
use App\Services\Analytics\DTOs\Contract3\PreparedQueryArtifact;
use App\Services\Analytics\DTOs\Contract3\SemanticQueryPlan;
use App\Services\Retrieval\RetrievalClient;
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;
use App\Services\Spreadsheet\Relationships\DTOs\SpreadsheetRelationshipGraph;
use App\Services\Spreadsheet\Security\AnalyticsAstAuthorizer;
use App\Services\Spreadsheet\Security\ServerControlledContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VirtualRelationalExecutionGatewayTest extends TestCase
{
    use RefreshDatabase;

    private int $workspaceId = 77;
    private string $secretKey = 'test_gateway_key_secret';

    public function test_analytics_client_prepare_virtual_query_dispatches_contract_3_payload(): void
    {
        Http::fake([
            '*/analytics/v3/prepare' => Http::response([
                'contract_version' => '3.0.0',
                'request_id' => 'req_test_01',
                'workspace_id' => $this->workspaceId,
                'dataset_id' => 'doc_123',
                'generation_id' => 'gen_abc',
                'query_fingerprint' => str_repeat('a', 64),
                'schema_fingerprint' => str_repeat('b', 64),
                'sql_template' => 'SELECT SUM(amount) FROM orders WHERE status = ?',
                'params' => ['delivered'],
                'referenced_tables' => ['orders'],
                'referenced_columns' => ['amount', 'status'],
                'confirmed_edge_ids' => [],
                'compiler_version' => '3.0.0',
                'status' => 'prepared',
                'created_at' => gmdate('c'),
            ], 200),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8001', apiKey: 'test_key');
        $plan = new SemanticQueryPlan(
            domain: 'sales',
            measures: [['name' => 'amount', 'aggregation' => 'sum']],
            filters: [['field' => 'status', 'operator' => '=', 'value' => 'delivered']],
        );

        $artifact = $client->prepareVirtualQuery(
            plan: $plan,
            workspaceId: $this->workspaceId,
            datasetId: 'doc_123',
            generationId: 'gen_abc'
        );

        $this->assertInstanceOf(PreparedQueryArtifact::class, $artifact);
        $this->assertSame('3.0.0', $artifact->contractVersion);
        $this->assertSame($this->workspaceId, $artifact->workspaceId);
        $this->assertSame('SELECT SUM(amount) FROM orders WHERE status = ?', $artifact->sqlTemplate);
        $this->assertSame(['delivered'], $artifact->params);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/analytics/v3/prepare')
                && $request['contract_version'] === '3.0.0'
                && $request['workspace_id'] === $this->workspaceId
                && $request['query_plan']['domain'] === 'sales';
        });
    }

    public function test_analytics_client_execute_virtual_query_with_signed_grant(): void
    {
        $fingerprint = str_repeat('f', 64);
        $generationId = 'gen_xyz';

        Http::fake([
            '*/analytics/v3/execute' => Http::response([
                'success' => true,
                'contract_version' => '3.0.0',
                'request_id' => 'req_exec_01',
                'workspace_id' => $this->workspaceId,
                'dataset_id' => 'doc_123',
                'generation_id' => $generationId,
                'rows' => [['total_sales' => 12500.0]],
                'columns' => ['total_sales'],
                'row_count' => 1,
                'report' => "📊 **ব্যবসায়িক অ্যানালিটিক্স রিপোর্ট**\n• total_sales: ৳12,500.00",
                'latency_ms' => 2.5,
            ], 200),
        ]);

        $client = new AnalyticsClient(baseUrl: 'http://127.0.0.1:8001', apiKey: 'test_key');

        $artifact = new PreparedQueryArtifact(
            contractVersion: '3.0.0',
            requestId: 'req_exec_01',
            workspaceId: $this->workspaceId,
            datasetId: 'doc_123',
            generationId: $generationId,
            queryFingerprint: $fingerprint,
            schemaFingerprint: str_repeat('s', 64),
            sqlTemplate: 'SELECT SUM(amount) AS total_sales FROM orders',
            params: [],
        );

        $grant = GrantSigner::issue(
            workspaceId: $this->workspaceId,
            queryFingerprint: $fingerprint,
            generationId: $generationId,
            secret: $this->secretKey
        );

        $response = $client->executeVirtualQuery($artifact, $grant);

        $this->assertInstanceOf(AnalyticsExecutionResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertCount(1, $response->rows);
        $this->assertStringContainsString('৳12,500.00', $response->report);

        Http::assertSent(function ($request) use ($grant) {
            return str_contains($request->url(), '/analytics/v3/execute')
                && $request['contract_version'] === '3.0.0'
                && $request['authorization_grant']['signature'] === $grant->signature
                && $request['authorization_grant']['nonce'] === $grant->nonce;
        });
    }

    public function test_owner_business_assistant_end_to_end_gateway_flow(): void
    {
        config(['analytics.secret_key' => $this->secretKey]);

        $fingerprint = str_repeat('9', 64);
        $generationId = 'gen_e2e_001';

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $workspace = \App\Models\Workspace::create([
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
        ]);
        $wsId = (int) $workspace->id;

        // Setup mock HTTP responses for Candidate Plan -> Prepare -> Execute
        Http::fake([
            '*/analytics/v3/plan-candidate' => Http::response([
                'domain' => 'sales',
                'measures' => [['name' => 'amount', 'aggregation' => 'sum', 'alias' => 'total_sales']],
                'dimensions' => [],
                'filters' => [],
                'group_by' => [],
                'order_by' => [],
                'limit' => 50,
            ], 200),

            '*/analytics/v3/prepare' => Http::response([
                'contract_version' => '3.0.0',
                'request_id' => 'req_e2e',
                'workspace_id' => $wsId,
                'dataset_id' => 'doc_orders_01',
                'generation_id' => $generationId,
                'query_fingerprint' => $fingerprint,
                'schema_fingerprint' => str_repeat('s', 64),
                'sql_template' => 'SELECT SUM(amount) AS total_sales FROM tbl_orders',
                'params' => [],
                'referenced_tables' => ['tbl_orders'],
                'referenced_columns' => ['amount'],
                'confirmed_edge_ids' => [],
                'compiler_version' => '3.0.0',
                'status' => 'prepared',
                'created_at' => gmdate('c'),
            ], 200),

            '*/analytics/v3/execute' => Http::response([
                'success' => true,
                'contract_version' => '3.0.0',
                'request_id' => 'req_e2e',
                'workspace_id' => $wsId,
                'dataset_id' => 'doc_orders_01',
                'generation_id' => $generationId,
                'rows' => [['total_sales' => 85000.0]],
                'columns' => ['total_sales'],
                'row_count' => 1,
                'report' => "📊 **ব্যবসায়িক অ্যানালিটিক্স রিপোর্ট** (`orders.xlsx`)\n• **total_sales**: ৳85,000.00",
                'latency_ms' => 3.12,
            ], 200),
        ]);

        $owner = User::factory()->create([
            'workspace_id' => $wsId,
        ]);
        $owner->assignRole(\App\Enums\RoleEnum::ADMIN->value);

        $doc = KnowledgeDocument::create([
            'id'                => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id'      => $wsId,
            'title'             => 'Orders Spreadsheet',
            'original_filename' => 'orders.xlsx',
            'file_path'         => 'documents/orders.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 12345,
            'content_hash'      => str_repeat('c', 64),
            'active_content_hash' => str_repeat('c', 64),
            'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE->value,
            'chunk_count'       => 1,
            'version'           => 1,
            'is_active'         => true,
        ]);

        $job = SpreadsheetImportJob::create([
            'workspace_id'  => $wsId,
            'document_id'   => (string) $doc->id,
            'generation_id' => $generationId,
            'status'        => 'completed',
            'file_path'     => 'documents/orders.xlsx',
            'total_rows'    => 100,
        ]);

        StructuredRecord::create([
            'workspace_id'   => $wsId,
            'document_id'    => (string) $doc->id,
            'sheet_name'     => 'Orders',
            'row_index'      => 1,
            'payload'        => ['amount' => 85000],
            'record_hash'    => str_repeat('h', 64),
            'generation_id'  => $generationId,
        ]);

        $channel = \App\Models\Channel::firstOrCreate(['slug' => 'simulator'], ['name' => 'Simulator', 'type' => 'simulator']);
        $account = \App\Models\ChannelAccount::firstOrCreate(
            ['workspace_id' => $wsId, 'external_id' => 'sim_test_acc'],
            ['name' => 'Simulator Account', 'channel_id' => $channel->id, 'access_token' => 'sim_tok', 'is_active' => true]
        );

        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'sim_owner_conv_001',
            'status'             => 'active',
            'customer_name'      => 'Owner User',
            'last_direction'     => 'inbound',
            'metadata'           => ['is_simulator' => true, 'is_customer' => false, 'role' => 'owner'],
        ]);

        $assistant = app(OwnerBusinessAssistant::class);
        $result = $assistant->handleOwnerQuery(
            query: 'আমাদের মোট সেলস কত?',
            workspaceId: $wsId,
            conversation: $conversation,
            user: $owner
        );

        $this->assertTrue($result['answered']);
        $this->assertSame('analytics', $result['route']);
        $this->assertTrue($result['is_owner_pipeline']);
        $this->assertStringContainsString('৳85,000.00', $result['reply']);
        $this->assertSame('3.0.0', $result['contract_version'] ?? null);
        $this->assertSame($fingerprint, $result['query_fingerprint'] ?? null);
    }

    public function test_gateway_graceful_fallback_when_python_execution_fails(): void
    {
        config(['analytics.secret_key' => $this->secretKey]);

        // Mock prepare succeeding, but execute failing with 500
        Http::fake([
            '*/analytics/v3/plan-candidate' => Http::response([
                'domain' => 'sales',
                'measures' => [['name' => 'amount', 'aggregation' => 'sum']],
            ], 200),

            '*/analytics/v3/prepare' => Http::response([
                'contract_version' => '3.0.0',
                'request_id' => 'req_fail_test',
                'workspace_id' => $this->workspaceId,
                'dataset_id' => 'doc_fail',
                'generation_id' => 'gen_fail',
                'query_fingerprint' => str_repeat('1', 64),
                'schema_fingerprint' => str_repeat('2', 64),
                'sql_template' => 'SELECT SUM(amount) FROM orders',
                'params' => [],
                'referenced_tables' => ['orders'],
                'referenced_columns' => ['amount'],
                'confirmed_edge_ids' => [],
                'compiler_version' => '3.0.0',
                'status' => 'prepared',
                'created_at' => gmdate('c'),
            ], 200),

            '*/analytics/v3/execute' => Http::response([
                'detail' => 'Database timeout or execution error',
            ], 500),
        ]);

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $workspace = \App\Models\Workspace::create([
            'name' => 'Fallback Workspace',
            'slug' => 'fallback-workspace',
        ]);
        $wsId = (int) $workspace->id;

        $owner = User::factory()->create(['workspace_id' => $wsId]);
        $owner->assignRole(\App\Enums\RoleEnum::ADMIN->value);

        $doc = KnowledgeDocument::create([
            'id'                => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id'      => $wsId,
            'title'             => 'Fallback Doc',
            'original_filename' => 'fallback.xlsx',
            'file_path'         => 'documents/fallback.xlsx',
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1000,
            'content_hash'      => str_repeat('a', 64),
            'active_content_hash' => str_repeat('a', 64),
            'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE->value,
            'chunk_count'       => 1,
            'version'           => 1,
            'is_active'         => true,
        ]);

        $job = SpreadsheetImportJob::create([
            'workspace_id'  => $wsId,
            'document_id'   => (string) $doc->id,
            'generation_id' => 'gen_fail',
            'status'        => 'completed',
            'file_path'     => 'documents/fallback.xlsx',
            'total_rows'    => 10,
        ]);

        StructuredRecord::create([
            'workspace_id'   => $wsId,
            'document_id'    => (string) $doc->id,
            'sheet_name'     => 'Orders',
            'row_index'      => 1,
            'payload'        => ['amount' => 1000],
            'record_hash'    => str_repeat('z', 64),
            'generation_id'  => 'gen_fail',
        ]);

        $channel = \App\Models\Channel::firstOrCreate(['slug' => 'simulator'], ['name' => 'Simulator', 'type' => 'simulator']);
        $account = \App\Models\ChannelAccount::firstOrCreate(
            ['workspace_id' => $wsId, 'external_id' => 'sim_fallback_acc'],
            ['name' => 'Simulator Account', 'channel_id' => $channel->id, 'access_token' => 'sim_tok', 'is_active' => true]
        );

        $conversation = Conversation::create([
            'channel_account_id' => $account->id,
            'external_user_id'   => 'sim_fallback_001',
            'status'             => 'active',
            'customer_name'      => 'Owner User',
            'last_direction'     => 'inbound',
            'metadata'           => ['is_simulator' => true, 'is_customer' => false, 'role' => 'owner'],
        ]);

        $assistant = app(OwnerBusinessAssistant::class);
        $result = $assistant->handleOwnerQuery(
            query: 'আমাদের মোট সেলস কত?',
            workspaceId: $wsId,
            conversation: $conversation,
            user: $owner
        );

        // Assistant must fall back gracefully without failing or returning customer denial message
        $this->assertTrue($result['answered']);
        $this->assertSame('analytics', $result['route']);
        $this->assertTrue($result['is_owner_pipeline']);
        $this->assertStringContainsString('ব্যবসায়িক অ্যানালিটিক্স ইনসাইট', $result['reply']);
    }
}
