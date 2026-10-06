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
}
