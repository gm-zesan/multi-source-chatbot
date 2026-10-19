<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatSimulatorExcelFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create(['name' => 'Main Workspace', 'slug' => 'main']);
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'workspace_id' => $this->workspace->id,
        ]);
        $this->admin->assignRole('superadmin');
    }

    public function test_upload_excel_ingests_spreadsheet_and_attaches_to_conversation(): void
    {
        Http::fake([
            '*/analytics/excel/upload' => Http::response([
                'success' => true,
                'message' => "Successfully ingested 'test_data.xlsx' with 2 sheets and 20 rows.",
                'file_id' => 'file_xyz_123',
                'filename' => 'test_data.xlsx',
                'sheets' => ['sales', 'expenses'],
                'total_rows' => 20,
                'schema_summary' => "### Uploaded Excel File: test_data.xlsx",
            ], 200),
        ]);

        $file = UploadedFile::fake()->create('test_data.xlsx', 100);

        $response = $this->actingAs($this->admin)
            ->postJson(route('simulator.upload_excel'), [
                'file' => $file,
            ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertEquals('file_xyz_123', $json['file_id']);
        $this->assertEquals('test_data.xlsx', $json['filename']);
        $this->assertEquals(['sales', 'expenses'], $json['sheets']);
    }

    public function test_clear_excel_removes_active_file(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('simulator.clear_excel'));

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    public function test_select_source_sets_and_clears_active_document(): void
    {
        $doc = \App\Models\KnowledgeDocument::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Financial Report 2024',
            'original_filename' => 'finance_2024.xlsx',
            'file_path' => 'documents/' . $this->workspace->id . '/finance_2024.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content_hash' => hash('sha256', 'finance_2024'),
            'source_id' => 'src_fin_2024',
            'file_size' => 1024,
            'lifecycle_status' => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'metadata' => [
                'sheets' => ['Q1', 'Q2'],
                'total_rows' => 500,
            ],
        ]);

        // Select the document
        $response = $this->actingAs($this->admin)
            ->postJson(route('simulator.select_source'), [
                'file_id' => 'src_fin_2024',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals('src_fin_2024', $json['active_spreadsheet']['file_id']);
        $this->assertEquals('finance_2024.xlsx', $json['active_spreadsheet']['filename']);

        // Clear the document selection
        $clearResponse = $this->actingAs($this->admin)
            ->postJson(route('simulator.select_source'), [
                'file_id' => null,
            ]);

        $clearResponse->assertStatus(200);
        $this->assertNull($clearResponse->json('active_spreadsheet'));
    }

    public function test_select_source_rejects_cross_workspace_document(): void
    {
        $otherWs = Workspace::create(['name' => 'Other Workspace', 'slug' => 'other']);
        \App\Models\KnowledgeDocument::create([
            'workspace_id' => $otherWs->id,
            'title' => 'Other Doc',
            'original_filename' => 'other.xlsx',
            'file_path' => 'documents/' . $otherWs->id . '/other.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content_hash' => hash('sha256', 'other_doc'),
            'source_id' => 'src_other_999',
            'file_size' => 1024,
            'lifecycle_status' => \App\Enums\DocumentLifecycleStatus::ACTIVE,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('simulator.select_source'), [
                'file_id' => 'src_other_999',
            ]);

        $response->assertStatus(404);
        $this->assertFalse($response->json('success'));
    }

    public function test_simulator_index_handles_file_id_query_param(): void
    {
        \App\Models\KnowledgeDocument::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Inventory List',
            'original_filename' => 'inventory.csv',
            'file_path' => 'documents/' . $this->workspace->id . '/inventory.csv',
            'mime_type' => 'text/csv',
            'content_hash' => hash('sha256', 'inv_list'),
            'source_id' => 'src_inv_001',
            'file_size' => 512,
            'lifecycle_status' => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'metadata' => [
                'sheets' => ['inventory'],
                'total_rows' => 150,
            ],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('simulator.index', ['file_id' => 'src_inv_001']));

        $response->assertStatus(200);
        $response->assertViewHas('activeSpreadsheet', function ($active) {
            return ($active['file_id'] ?? null) === 'src_inv_001'
                && ($active['filename'] ?? null) === 'inventory.csv';
        });
        $response->assertViewHas('availableDocuments', function ($docs) {
            return $docs->isNotEmpty();
        });
    }

    public function test_send_accepts_file_id_and_returns_active_spreadsheet_and_suggestions(): void
    {
        \App\Models\KnowledgeDocument::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Sales Data',
            'original_filename' => 'sales.xlsx',
            'file_path' => 'documents/' . $this->workspace->id . '/sales.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content_hash' => hash('sha256', 'sales_data'),
            'source_id' => 'src_sales_77',
            'file_size' => 2048,
            'lifecycle_status' => \App\Enums\DocumentLifecycleStatus::ACTIVE,
            'metadata' => [
                'sheets' => ['Sheet1'],
                'total_rows' => 80,
            ],
        ]);

        \App\AI\LLM\LLMClient::fake([
            '{"route": "ANALYTICS", "confidence": 0.99, "security_status": "allowed", "reason": "query"}'
        ]);

        Http::fake([
            '*/analytics/excel/query' => Http::response([
                'success' => true,
                'intent' => 'analytics',
                'report' => 'Total sales: $10,000',
                'clarification_options' => ['Query for sales.xlsx', 'Query for other.xlsx'],
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('simulator.send'), [
                'message' => 'show total revenue',
                'file_id' => 'src_sales_77',
            ]);

        $response->assertStatus(200);
        $json = $response->json();
        $this->assertTrue($json['success']);
        $this->assertEquals('src_sales_77', $json['active_spreadsheet']['file_id']);
        $this->assertEquals('sales.xlsx', $json['active_spreadsheet']['filename']);
        $this->assertEquals(['Query for sales.xlsx', 'Query for other.xlsx'], $json['suggestions']);
    }
}
