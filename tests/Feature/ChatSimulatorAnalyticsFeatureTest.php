<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ChatSimulatorAnalyticsFeatureTest extends TestCase
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

    public function test_simulator_send_dispatches_analytics_query_end_to_end(): void
    {
        \App\AI\LLM\LLMClient::fake([
            '{"route": "ANALYTICS", "confidence": 0.99, "security_status": "allowed", "reason": "test"}'
        ]);

        Http::fake([
            '*/analytics/query' => Http::response([
                'success' => true,
                'engine' => 'semantic',
                'intent' => 'business_analytics',
                'report' => '### Business Analytics Mock\n\nTotal cashin is $500',
            ], 200),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('simulator.send'), [
                'message' => 'Aj koto cashin hoise?',
            ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals('analytics', $json['route']);
        $this->assertArrayHasKey('reply', $json);
        $this->assertStringContainsString('Business Analytics', $json['reply']);
        $this->assertStringNotContainsString('Service Unavailable', $json['reply']);
    }

    public function test_simulator_owner_business_analytics_with_structured_records_and_diagnostics(): void
    {
        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $owner = User::factory()->create([
            'name' => 'Owner Mahmud',
            'email' => 'mahmud.owner@example.com',
            'workspace_id' => $this->workspace->id,
        ]);
        $owner->assignRole('owner');

        $doc = \App\Models\KnowledgeDocument::create([
            'workspace_id' => $this->workspace->id,
            'title' => 'Annual Sales',
            'original_filename' => 'annual_sales_2026.xlsx',
            'file_path' => 'documents/annual_sales_2026.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'content_hash' => hash('sha256', 'sales_hash'),
            'source_id' => 'src_sales_2026',
            'file_size' => 1024,
            'lifecycle_status' => \App\Enums\DocumentLifecycleStatus::ACTIVE,
        ]);

        $job = \App\Models\SpreadsheetImportJob::create([
            'workspace_id' => $this->workspace->id,
            'document_id' => $doc->id,
            'generation_id' => 'gen_2026',
            'status' => 'completed',
        ]);

        \App\Models\StructuredRecord::create([
            'workspace_id' => $this->workspace->id,
            'document_id' => (string) $doc->id,
            'import_job_id' => $job->id,
            'sheet_name' => 'Sales',
            'row_index' => 1,
            'payload' => ['Product' => 'Laptop', 'Revenue' => 100000],
            'record_hash' => hash('sha256', 'row_1'),
            'generation_id' => (string) $job->generation_id,
        ]);

        $response = $this->actingAs($owner)
            ->postJson(route('simulator.send'), [
                'message' => 'আজকের মোট বিক্রি এবং রেভিনিউ কত?',
            ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['success']);
        $this->assertEquals('analytics', $json['route']);
        $this->assertArrayHasKey('reply', $json);
        $this->assertStringContainsString('ব্যবসায়িক অ্যানালিটিক্স ইনসাইট', $json['reply']);
        $this->assertStringContainsString('বিক্রয় ও রেভিনিউ', $json['reply']);
        $this->assertArrayHasKey('decision_trace', $json);
        $this->assertArrayHasKey('latency_breakdown', $json['decision_trace']);
        $this->assertArrayHasKey('total_ms', $json['decision_trace']['latency_breakdown']);
    }
}
