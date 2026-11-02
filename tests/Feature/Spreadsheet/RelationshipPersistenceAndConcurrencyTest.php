<?php

declare(strict_types=1);

namespace Tests\Feature\Spreadsheet;

use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Spreadsheet\Relationships\CrossSheetRelationshipService;
use App\Services\Spreadsheet\Relationships\DTOs\DiscoveredRelationshipEdge;
use App\Services\Spreadsheet\Relationships\DTOs\SpreadsheetRelationshipGraph;
use App\Services\Spreadsheet\Security\Exceptions\TenantScopeViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RelationshipPersistenceAndConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private CrossSheetRelationshipService $service;
    private Workspace $workspace;
    private User $owner;
    private KnowledgeDocument $document;
    private SpreadsheetImportJob $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->service = new CrossSheetRelationshipService();

        $this->workspace = Workspace::create(['name' => 'Test Corp', 'slug' => 'test-corp']);
        $this->owner = User::factory()->create(['workspace_id' => $this->workspace->id]);
        $this->owner->assignRole(\App\Enums\RoleEnum::ADMIN->value);
        $this->owner->givePermissionTo(\App\Enums\Permissions\KnowledgeDocumentPermission::VIEW->value);
        $this->owner->givePermissionTo(\App\Enums\Permissions\KnowledgeDocumentPermission::UPDATE->value);

        $this->document = KnowledgeDocument::create([
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Test Workbook',
            'original_filename' => 'workbook.xlsx',
            'file_path'         => 'documents/workbook.xlsx',
            'content_hash'      => hash('sha256', 'test_workbook'),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'lifecycle_status'  => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'uploaded_by'       => $this->owner->id,
        ]);

        $this->job = SpreadsheetImportJob::create([
            'workspace_id'       => $this->workspace->id,
            'document_id'        => $this->document->id,
            'sheet_name'         => 'Sheet1',
            'status'             => 'completed',
            'generation_id'      => 'gen-token-001',
            'schema_version'     => '2.1.0',
            'header_row_index'   => 0,
            'total_rows'         => 10,
            'processed_rows'     => 10,
            'detected_schema'    => [
                'columns'            => ['col_000' => ['header' => 'id']],
                'unrelated_metadata' => 'should_be_preserved',
                'custom_categories'  => ['entity_name'],
            ],
            'source_descriptors' => [],
        ]);
    }

    /**
     * 1. JSON Persistence preserves unrelated detected_schema keys.
     */
    public function test_json_persistence_preserves_unrelated_schema_metadata(): void
    {
        $multiSheetData = [
            'S1' => [
                'descriptors' => ['col_0' => ['original_header' => 'user_id', 'physical_type' => 'integer']],
                'rows' => [['col_0' => 1], ['col_0' => 2]],
            ],
            'S2' => [
                'descriptors' => ['col_0' => ['original_header' => 'user_id', 'physical_type' => 'integer']],
                'rows' => [['col_0' => 1], ['col_0' => 2]],
            ],
        ];

        $graph = $this->service->discoverRelationships($this->job, $multiSheetData, 'gen-token-001');

        $this->job->refresh();
        $schema = $this->job->detected_schema;

        $this->assertEquals('should_be_preserved', $schema['unrelated_metadata'] ?? null);
        $this->assertEquals(['entity_name'], $schema['custom_categories'] ?? null);
        $this->assertArrayHasKey('relationships', $schema);
        $this->assertEquals('gen-token-001', $schema['relationships_generation_id']);
    }

    /**
     * 2. Load graph gracefully handles null, missing, or malformed metadata.
     */
    public function test_load_graph_handles_null_or_malformed_metadata(): void
    {
        // Null detected_schema
        $this->job->update(['detected_schema' => null]);
        $graph1 = $this->service->loadGraph($this->job);
        $this->assertEmpty($graph1->edges);

        // Missing relationships key
        $this->job->update(['detected_schema' => ['some_key' => 'val']]);
        $graph2 = $this->service->loadGraph($this->job);
        $this->assertEmpty($graph2->edges);

        // Malformed array in relationships
        $this->job->update(['detected_schema' => ['relationships' => ['edges' => 'not_an_array']]]);
        $graph3 = $this->service->loadGraph($this->job);
        $this->assertEmpty($graph3->edges);
    }

    /**
     * 3. Stale worker cannot overwrite newer generation.
     */
    public function test_generation_fencing_prevents_stale_worker_writes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Stale generation fencing token');

        // Worker has old token 'gen-stale-000', while job has 'gen-token-001'
        $this->service->discoverRelationships(
            $this->job,
            ['S1' => ['rows' => []], 'S2' => ['rows' => []]],
            'gen-stale-000'
        );
    }

    /**
     * 4. Owner rejection is preserved across re-discovery.
     */
    public function test_owner_rejection_is_preserved_across_drift_evaluation(): void
    {
        $data = [
            'Users' => [
                'descriptors' => ['col_0' => ['original_header' => 'user_id', 'physical_type' => 'integer']],
                'rows' => [['col_0' => 1], ['col_0' => 2]],
            ],
            'Logs' => [
                'descriptors' => [
                    'col_0' => ['original_header' => 'log_id', 'physical_type' => 'integer'],
                    'col_1' => ['original_header' => 'user_id', 'physical_type' => 'integer'],
                ],
                'rows' => [['col_0' => 101, 'col_1' => 1], ['col_0' => 102, 'col_1' => 2]],
            ],
        ];

        $graph = $this->service->discoverRelationships($this->job, $data);
        $edgeId = array_key_first($graph->edges);

        // Owner explicitly rejects this candidate
        $this->service->rejectRelationship($this->job, $edgeId, (int) $this->owner->id, (int) $this->workspace->id, 'False correlation');

        // New re-import with same or updated data
        $driftGraph = $this->service->evaluateDrift($this->job, $data);

        // Rejection must be preserved
        $this->assertEquals(DiscoveredRelationshipEdge::STATE_REJECTED, $driftGraph->edges[$edgeId]->state);
        $this->assertEquals('False correlation', $driftGraph->edges[$edgeId]->rejectionReason);
    }
}
