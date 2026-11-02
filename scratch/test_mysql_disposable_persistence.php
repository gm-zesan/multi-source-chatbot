<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\Workspace;
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;
use App\Services\Spreadsheet\Relationships\DTOs\SpreadsheetRelationshipGraph;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "====================================================\n";
echo "MYSQL DISPOSABLE: PERSISTENCE & CONCURRENCY VERIFICATION\n";
echo "====================================================\n\n";

$disposableConn = 'mysql_disposable';

// 1. Strict Database Guard
$dbName = DB::connection($disposableConn)->selectOne("SELECT DATABASE() as current_db")->current_db;
echo "Connected Database: {$dbName}\n";

if ($dbName !== 'chatbot_test_disposable') {
    echo "CRITICAL: Connected to {$dbName}, expected 'chatbot_test_disposable'! ABORTING!\n";
    exit(1);
}

// 2. Set default connection for models in this test
KnowledgeDocument::resolveConnection($disposableConn);
SpreadsheetImportJob::resolveConnection($disposableConn);
Workspace::resolveConnection($disposableConn);

$relService = new CrossSheetRelationshipService();

// Find or create disposable workspace
$ws = Workspace::on($disposableConn)->first();
if (! $ws) {
    $ws = Workspace::on($disposableConn)->create([
        'name' => 'Disposable Test Workspace',
        'slug' => 'disposable-test-' . Str::random(6),
    ]);
}

$docId = (string) Str::uuid();
$jobId = (string) Str::uuid();
$genId = (string) Str::uuid();

echo "Creating parent KnowledgeDocument on MySQL: {$docId}...\n";
$doc = KnowledgeDocument::on($disposableConn)->create([
    'workspace_id'      => $ws->id,
    'title'             => 'MySQL Test Workbook ' . Str::random(5),
    'original_filename' => 'mysql_test_' . Str::random(5) . '.xlsx',
    'file_path'         => 'documents/mysql_test_' . Str::random(5) . '.xlsx',
    'content_hash'      => hash('sha256', Str::uuid()->toString()),
    'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'file_size'         => 2048,
    'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE,
]);

echo "Creating test SpreadsheetImportJob on MySQL: {$jobId}...\n";
$job = SpreadsheetImportJob::on($disposableConn)->create([
    'id'                 => $jobId,
    'workspace_id'       => $ws->id,
    'document_id'        => $doc->id,
    'sheet_name'         => 'Orders',
    'status'             => 'completed',
    'conflict_mode'      => 'skip',
    'generation_id'      => $genId,
    'schema_version'     => '2.3.0',
    'header_row_index'   => 0,
    'total_rows'         => 100,
    'processed_rows'     => 100,
    'staged_rows'        => 0,
    'failed_rows'        => 0,
    'checkpoint_row'     => 100,
    'detected_schema'    => [
        'columns' => ['col_000' => ['header' => 'order_id']],
        'prior_unrelated_setting' => 'mysql_persistence_intact',
    ],
    'source_descriptors' => [],
]);

try {
    // 3. Test Graph Persistence on Real MySQL JSON Column
    echo "--- 3. Testing Graph Persistence in MySQL JSON column ---\n";
    $edge = new DiscoveredRelationshipEdge(
        id: 'edge_mysql_test_001',
        sourceSheet: 'Orders',
        sourceColumnIds: ['col_001'],
        sourceHeaders: ['customer_id'],
        targetSheet: 'Customers',
        targetColumnIds: ['col_000'],
        targetHeaders: ['id'],
        cardinality: DiscoveredRelationshipEdge::CARDINALITY_ONE_TO_MANY,
        isJunctionTable: false,
        inclusionRatio: 1.0,
        headerSimilarity: 0.95,
        entityAlignment: 0.90,
        typeCompatibility: 1.0,
        confidence: 0.98,
        state: DiscoveredRelationshipEdge::STATE_DISCOVERED,
        evidence: ['MySQL Disposable Verification Test'],
        orphanCount: 0
    );

    $graph = new SpreadsheetRelationshipGraph(
        workspaceId: (int) $ws->id,
        documentId: $docId,
        jobId: $jobId,
        generationId: $genId,
        nodes: ['Orders' => ['row_count' => 100], 'Customers' => ['row_count' => 50]],
        edges: ['edge_mysql_test_001' => $edge],
        ambiguities: [],
        graphVersion: '2.3.0',
        discoveredAt: now()->toISOString()
    );

    $relService->persistGraph($job, $graph);

    // Refresh model from MySQL
    $job->refresh();
    $loadedGraph = $relService->loadGraph($job);

    assert(count($loadedGraph->edges) === 1, "Graph edge count mismatch in MySQL");
    assert(isset($loadedGraph->edges['edge_mysql_test_001']), "Edge ID not found in MySQL");
    assert($loadedGraph->edges['edge_mysql_test_001']->cardinality === '1:N', "Cardinality mismatch");
    assert($job->detected_schema['prior_unrelated_setting'] === 'mysql_persistence_intact', "Unrelated schema setting was corrupted!");
    echo "[PASS] Graph successfully persisted into MySQL JSON column and loaded back with 100% integrity.\n";
    echo "[PASS] Prior unrelated schema metadata preserved in MySQL JSON.\n";

    // 4. Test Concurrency & Generation Fencing on MySQL
    echo PHP_EOL . "--- 4. Testing Generation Fencing on MySQL ---\n";
    $staleGenId = (string) Str::uuid();
    $caughtStale = false;
    try {
        $relService->discoverRelationships($job, ['S1' => [], 'S2' => []], $staleGenId);
    } catch (\InvalidArgumentException $e) {
        if (str_contains($e->getMessage(), 'Stale generation fencing token')) {
            $caughtStale = true;
            echo "[PASS] Stale generation token on MySQL successfully rejected with InvalidArgumentException.\n";
        }
    }
    assert($caughtStale, "Stale token was not caught on MySQL!");

    // 5. Test State Transitions (Confirmation & Rejection) on MySQL
    echo PHP_EOL . "--- 5. Testing Confirmation & Rejection State Transitions on MySQL ---\n";
    $confirmedEdge = $relService->confirmRelationship($job, 'edge_mysql_test_001', 99, (int) $ws->id);
    assert($confirmedEdge->state === DiscoveredRelationshipEdge::STATE_CONFIRMED, "Confirmation failed");
    $job->refresh();
    $reloadedAfterConfirm = $relService->loadGraph($job);
    assert($reloadedAfterConfirm->edges['edge_mysql_test_001']->state === DiscoveredRelationshipEdge::STATE_CONFIRMED);
    echo "[PASS] Relationship confirmed on MySQL and persisted.\n";

    $rejectedEdge = $relService->rejectRelationship($job, 'edge_mysql_test_001', 99, (int) $ws->id, 'Owner rejected on MySQL');
    assert($rejectedEdge->state === DiscoveredRelationshipEdge::STATE_REJECTED, "Rejection failed");
    $job->refresh();
    $reloadedAfterReject = $relService->loadGraph($job);
    assert($reloadedAfterReject->edges['edge_mysql_test_001']->state === DiscoveredRelationshipEdge::STATE_REJECTED);
    assert($reloadedAfterReject->edges['edge_mysql_test_001']->rejectionReason === 'Owner rejected on MySQL');
    echo "[PASS] Relationship rejected on MySQL and persisted.\n";

    // 6. Test Cross-Tenant Access on MySQL
    echo PHP_EOL . "--- 6. Testing Cross-Tenant Guard on MySQL ---\n";
    $caughtCrossTenant = false;
    try {
        $relService->confirmRelationship($job, 'edge_mysql_test_001', 99, 999999); // foreign workspace
    } catch (\App\Services\Spreadsheet\Security\Exceptions\TenantScopeViolationException $e) {
        $caughtCrossTenant = true;
        echo "[PASS] TenantScopeViolationException thrown for foreign workspace on MySQL.\n";
    }
    assert($caughtCrossTenant, "Cross-tenant attack was not blocked on MySQL!");

} finally {
    // Clean up test job and document in disposable DB
    echo PHP_EOL . "Cleaning up test job {$jobId} and document {$docId} from {$dbName}...\n";
    SpreadsheetImportJob::on($disposableConn)->where('id', $jobId)->delete();
    KnowledgeDocument::on($disposableConn)->where('id', $docId)->delete();
    echo "[SUCCESS] Cleanup complete.\n";
}

echo PHP_EOL . "====================================================\n";
echo "MYSQL DISPOSABLE VERIFICATION: ALL 6 TESTS PASSED\n";
echo "====================================================\n";
