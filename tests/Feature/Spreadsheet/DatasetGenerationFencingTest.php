<?php

declare(strict_types=1);

namespace Tests\Feature\Spreadsheet;

use App\AI\DTOs\AgentContext;
use App\AI\Tools\LegacyExcelAnalyticsTool;
use App\Enums\DocumentLifecycleStatus;
use App\Models\KnowledgeDocument;
use App\Models\SpreadsheetImportJob;
use App\Models\StructuredRecord;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class DatasetGenerationFencingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;
    private User $owner;
    private KnowledgeDocument $document;
    private SpreadsheetImportJob $activeJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::create([
            'name' => 'Fencing Test Workspace',
            'slug' => 'fencing-ws-' . Str::random(6),
        ]);

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $this->owner = User::create([
            'workspace_id' => $this->workspace->id,
            'name'         => 'Workspace Owner',
            'email'        => 'owner-' . Str::random(6) . '@fencing.test',
            'password'     => bcrypt('secret'),
        ]);
        $this->owner->assignRole('owner');
        $this->actingAs($this->owner);

        $this->document = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $this->workspace->id,
            'title'             => 'Financial Spreadsheet 2026',
            'original_filename' => 'financial_2026.xlsx',
            'file_path'         => 'spreadsheets/financial_2026.xlsx',
            'content_hash'      => hash('sha256', 'content_fencing_test'),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 2048,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
        ]);

        $this->activeJob = SpreadsheetImportJob::create([
            'workspace_id'       => $this->workspace->id,
            'document_id'        => $this->document->id,
            'status'             => 'completed',
            'generation_id'      => 'gen_active_authoritative_101',
            'source_descriptors' => [
                ['source_column_id' => 'col_amt', 'name' => 'amount', 'semantic_role' => 'revenue', 'sensitivity' => 'PUBLIC'],
            ],
            'created_at'         => Carbon::now()->subMinutes(30),
            'completed_at'       => Carbon::now()->subMinutes(10),
        ]);
    }

    public function test_offline_fallback_includes_only_active_authoritative_import_job_records(): void
    {
        // 1. Create 3 authoritative records linked to the active job
        for ($i = 1; $i <= 3; $i++) {
            StructuredRecord::create([
                'workspace_id'  => $this->workspace->id,
                'document_id'   => (string) $this->document->id,
                'import_job_id' => $this->activeJob->id,
                'sheet_name'    => 'Sales',
                'row_index'     => $i,
                'payload'       => ['Product' => "Item {$i}", 'Sales' => 1000 * $i],
                'record_hash'   => hash('sha256', "active_rec_{$i}"),
                'generation_id' => $this->activeJob->generation_id,
            ]);
        }

        // 2. Create older superseded import job for same document with 5 stale records
        $supersededJob = SpreadsheetImportJob::create([
            'workspace_id'       => $this->workspace->id,
            'document_id'        => $this->document->id,
            'status'             => 'completed',
            'generation_id'      => 'gen_stale_superseded_099',
            'source_descriptors' => [],
            'created_at'         => Carbon::now()->subDays(2),
            'completed_at'       => Carbon::now()->subDays(2)->addMinutes(5),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            StructuredRecord::create([
                'workspace_id'  => $this->workspace->id,
                'document_id'   => (string) $this->document->id,
                'import_job_id' => $supersededJob->id,
                'sheet_name'    => 'Sales',
                'row_index'     => $i,
                'payload'       => ['Product' => "Old Item {$i}", 'Sales' => 500 * $i],
                'record_hash'   => hash('sha256', "stale_rec_{$i}"),
                'generation_id' => $supersededJob->generation_id,
            ]);
        }

        // 3. Create 2 unproven records with NULL import_job_id
        for ($i = 1; $i <= 2; $i++) {
            StructuredRecord::create([
                'workspace_id'  => $this->workspace->id,
                'document_id'   => (string) $this->document->id,
                'import_job_id' => null,
                'sheet_name'    => 'Sales',
                'row_index'     => 100 + $i,
                'payload'       => ['Product' => "Unproven Item {$i}", 'Sales' => 999],
                'record_hash'   => hash('sha256', "null_job_rec_{$i}"),
                'generation_id' => 'gen_unproven',
            ]);
        }

        // Total structured records in DB for document = 3 (active) + 5 (stale) + 2 (null) = 10 records.
        $this->assertEquals(10, StructuredRecord::where('document_id', $this->document->id)->count());

        // 4. Force downstream gateway failure to trigger offline fallback
        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldReceive('planCandidateQuery')
            ->once()
            ->andThrow(new \RuntimeException('Connection refused to Python analytics sandbox gateway'));

        $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
        $context = AgentContext::forUser(
            user: $this->owner,
            workspaceId: $this->workspace->id,
        );

        $result = $tool->execute(['question' => 'মোট বিক্রি কত?'], $context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('synthesized_fallback', $result->data['status']);

        // CRITICAL INVARIANT: Row count must be EXACTLY 3 (the authoritative active job records),
        // NEVER 10 (mixing superseded or NULL import_job_id records)!
        $this->assertSame(3, $result->data['row_count']);
        $this->assertSame('gen_active_authoritative_101', $result->metadata['dataset_generation']);
        $this->assertSame($this->activeJob->id, $result->metadata['import_job_id']);
        $this->assertTrue($result->metadata['provenance_fenced']);
        $this->assertSame($this->activeJob->completed_at->toIso8601String(), $result->metadata['dataset_freshness']);
        $this->assertStringContainsString('3 টি', $result->data['report']);
        $this->assertStringNotContainsString('10 টি', $result->data['report']);
    }

    public function test_offline_fallback_fails_closed_when_records_have_null_import_job_id(): void
    {
        // 5 records exist for the document, but ALL have NULL import_job_id
        for ($i = 1; $i <= 5; $i++) {
            StructuredRecord::create([
                'workspace_id'  => $this->workspace->id,
                'document_id'   => (string) $this->document->id,
                'import_job_id' => null,
                'sheet_name'    => 'Sales',
                'row_index'     => $i,
                'payload'       => ['Product' => "Item {$i}", 'Sales' => 100],
                'record_hash'   => hash('sha256', "null_rec_{$i}"),
                'generation_id' => 'unknown_generation',
            ]);
        }

        // Active job has 0 records matching its ID
        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldReceive('planCandidateQuery')
            ->once()
            ->andThrow(new \RuntimeException('Downstream gateway offline'));

        $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
        $context = AgentContext::forUser(
            user: $this->owner,
            workspaceId: $this->workspace->id,
        );

        $result = $tool->execute(['question' => 'বিক্রির সামারি কত?'], $context);

        // Must fail closed because 0 proven records exist for the active authoritative job
        $this->assertFalse($result->isSuccess());
        $this->assertArrayHasKey('error', $result->toArray());
    }

    public function test_offline_fallback_strictly_excludes_records_from_different_workspace(): void
    {
        // Workspace 2 with its own job and records
        $workspace2 = Workspace::create(['name' => 'Foreign WS', 'slug' => 'foreign-ws-' . Str::random(6)]);
        $doc2 = KnowledgeDocument::create([
            'id'                => (string) Str::uuid(),
            'workspace_id'      => $workspace2->id,
            'title'             => 'Foreign Doc',
            'original_filename' => 'foreign.xlsx',
            'file_path'         => 'foreign.xlsx',
            'content_hash'      => hash('sha256', 'foreign'),
            'mime_type'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size'         => 1024,
            'lifecycle_status'  => DocumentLifecycleStatus::ACTIVE,
        ]);
        $job2 = SpreadsheetImportJob::create([
            'workspace_id'       => $workspace2->id,
            'document_id'        => $doc2->id,
            'status'             => 'completed',
            'generation_id'      => 'gen_foreign_999',
            'source_descriptors' => [],
        ]);

        for ($i = 1; $i <= 8; $i++) {
            StructuredRecord::create([
                'workspace_id'  => $workspace2->id,
                'document_id'   => (string) $doc2->id,
                'import_job_id' => $job2->id,
                'sheet_name'    => 'Sales',
                'row_index'     => $i,
                'payload'       => ['Product' => "Foreign {$i}"],
                'record_hash'   => hash('sha256', "foreign_{$i}"),
                'generation_id' => $job2->generation_id,
            ]);
        }

        // Workspace 1 has 2 records
        for ($i = 1; $i <= 2; $i++) {
            StructuredRecord::create([
                'workspace_id'  => $this->workspace->id,
                'document_id'   => (string) $this->document->id,
                'import_job_id' => $this->activeJob->id,
                'sheet_name'    => 'Sales',
                'row_index'     => $i,
                'payload'       => ['Product' => "Local {$i}"],
                'record_hash'   => hash('sha256', "local_{$i}"),
                'generation_id' => $this->activeJob->generation_id,
            ]);
        }

        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldReceive('planCandidateQuery')
            ->once()
            ->andThrow(new \RuntimeException('Connection failed'));

        $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
        $context = AgentContext::forUser(
            user: $this->owner,
            workspaceId: $this->workspace->id,
        );

        $result = $tool->execute(['question' => 'বিক্রি কত?'], $context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(2, $result->data['row_count']);
        $this->assertSame($this->workspace->id, $context->getWorkspaceId());
    }

    public function test_dataset_freshness_timestamp_prefers_completed_at_over_created_at(): void
    {
        StructuredRecord::create([
            'workspace_id'  => $this->workspace->id,
            'document_id'   => (string) $this->document->id,
            'import_job_id' => $this->activeJob->id,
            'sheet_name'    => 'Sales',
            'row_index'     => 1,
            'payload'       => ['Total' => 1000],
            'record_hash'   => hash('sha256', 'freshness_rec_1'),
            'generation_id' => $this->activeJob->generation_id,
        ]);

        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldReceive('planCandidateQuery')->andThrow(new \RuntimeException('Offline'));

        $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
        $context = AgentContext::forUser(
            user: $this->owner,
            workspaceId: $this->workspace->id,
        );

        $result = $tool->execute(['question' => 'বিক্রি কত?'], $context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame($this->activeJob->completed_at->toIso8601String(), $result->metadata['dataset_freshness']);
        $this->assertNotSame($this->activeJob->created_at->toIso8601String(), $result->metadata['dataset_freshness']);
    }

    public function test_missing_completed_at_marks_freshness_unknown_with_null_and_provenance_warning(): void
    {
        $this->activeJob->update(['completed_at' => null]);

        StructuredRecord::create([
            'workspace_id'  => $this->workspace->id,
            'document_id'   => (string) $this->document->id,
            'import_job_id' => $this->activeJob->id,
            'sheet_name'    => 'Sales',
            'row_index'     => 1,
            'payload'       => ['Total' => 1000],
            'record_hash'   => hash('sha256', 'freshness_rec_2'),
            'generation_id' => $this->activeJob->generation_id,
        ]);

        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldReceive('planCandidateQuery')->andThrow(new \RuntimeException('Offline'));

        $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
        $context = AgentContext::forUser(
            user: $this->owner,
            workspaceId: $this->workspace->id,
        );

        $result = $tool->execute(['question' => 'বিক্রি কত?'], $context);

        $this->assertTrue($result->isSuccess());
        $this->assertNull($result->metadata['dataset_freshness']);
        $this->assertFalse($result->metadata['freshness_known']);
        $this->assertSame('dataset_freshness_unknown', $result->metadata['provenance_warning']);
        $this->assertNotSame($this->activeJob->created_at->toIso8601String(), $result->metadata['dataset_freshness']);
    }

    public function test_failed_import_job_is_strictly_rejected(): void
    {
        $this->activeJob->update(['status' => 'failed']);

        StructuredRecord::create([
            'workspace_id'  => $this->workspace->id,
            'document_id'   => (string) $this->document->id,
            'import_job_id' => $this->activeJob->id,
            'sheet_name'    => 'Sales',
            'row_index'     => 1,
            'payload'       => ['Total' => 5000],
            'record_hash'   => hash('sha256', 'failed_job_rec_1'),
            'generation_id' => $this->activeJob->generation_id,
        ]);

        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldNotReceive('planCandidateQuery');

        $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
        $context = AgentContext::forUser(user: $this->owner, workspaceId: $this->workspace->id);

        $result = $tool->execute(['question' => 'মোট বিক্রি কত?'], $context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('no_spreadsheet_found', $result->data['status']);
        $this->assertSame([], $result->data['rows']);
    }

    public function test_incomplete_or_paused_import_jobs_are_strictly_rejected(): void
    {
        foreach (['processing', 'inspecting', 'awaiting_mapping', 'paused'] as $ineligibleStatus) {
            $this->activeJob->update(['status' => $ineligibleStatus]);

            $mockClient = Mockery::mock(AnalyticsClient::class);
            $mockClient->shouldNotReceive('planCandidateQuery');

            $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
            $context = AgentContext::forUser(user: $this->owner, workspaceId: $this->workspace->id);

            $result = $tool->execute(['question' => 'বিক্রি কত?'], $context);

            $this->assertSame('no_spreadsheet_found', $result->data['status'], "Status '{$ineligibleStatus}' must be rejected");
        }
    }

    public function test_ambiguous_active_jobs_with_tied_timestamps_fail_closed(): void
    {
        $tiedTimestamp = Carbon::now()->subMinutes(5);
        $this->activeJob->update([
            'completed_at' => $tiedTimestamp,
            'created_at'   => $tiedTimestamp,
        ]);

        // Second completed job with exact same timestamp and conflicting generation
        SpreadsheetImportJob::create([
            'workspace_id'       => $this->workspace->id,
            'document_id'        => $this->document->id,
            'status'             => 'completed',
            'generation_id'      => 'gen_conflicting_concurrent_202',
            'source_descriptors' => [],
            'created_at'         => $tiedTimestamp,
            'completed_at'       => $tiedTimestamp,
        ]);

        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldNotReceive('planCandidateQuery');

        $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
        $context = AgentContext::forUser(user: $this->owner, workspaceId: $this->workspace->id);

        $result = $tool->execute(['question' => 'মোট বিক্রি কত?'], $context);

        $this->assertFalse($result->isSuccess());
        $this->assertSame('ambiguous_active_jobs', $result->metadata['status'] ?? null);
    }

    public function test_concurrent_generation_change_during_execution_rejects_stale_offline_fallback(): void
    {
        // Attach 3 records to initial generation
        for ($i = 1; $i <= 3; $i++) {
            StructuredRecord::create([
                'workspace_id'  => $this->workspace->id,
                'document_id'   => (string) $this->document->id,
                'import_job_id' => $this->activeJob->id,
                'sheet_name'    => 'Sales',
                'row_index'     => $i,
                'payload'       => ['Total' => 1000 * $i],
                'record_hash'   => hash('sha256', "conc_old_{$i}"),
                'generation_id' => $this->activeJob->generation_id,
            ]);
        }

        $doc = $this->document;
        $workspaceId = $this->workspace->id;

        // Simulate concurrent generation publication mid-execution before gateway throws
        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldReceive('planCandidateQuery')
            ->once()
            ->andReturnUsing(function () use ($doc, $workspaceId) {
                $newJob = SpreadsheetImportJob::create([
                    'workspace_id'       => $workspaceId,
                    'document_id'        => $doc->id,
                    'status'             => 'completed',
                    'generation_id'      => 'gen_new_concurrent_999',
                    'source_descriptors' => [],
                    'created_at'         => Carbon::now(),
                    'completed_at'       => Carbon::now(),
                ]);
                $doc->update(['active_generation_id' => $newJob->generation_id]);

                throw new \RuntimeException('Downstream gateway unavailable during concurrent import');
            });

        $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
        $context = AgentContext::forUser(user: $this->owner, workspaceId: $this->workspace->id);

        $result = $tool->execute(['question' => 'মোট বিক্রি কত?'], $context);

        // Must fail closed instead of returning stale records from superseded gen_active_authoritative_101!
        $this->assertFalse($result->isSuccess());
        $this->assertContains(
            $result->metadata['status'] ?? null,
            ['concurrent_active_generation_superseded', 'concurrent_newer_generation_published']
        );
    }

    public function test_inactive_document_lifecycle_status_rejects_completed_import_job(): void
    {
        $this->document->update(['lifecycle_status' => DocumentLifecycleStatus::INDEXING]);

        StructuredRecord::create([
            'workspace_id'  => $this->workspace->id,
            'document_id'   => (string) $this->document->id,
            'import_job_id' => $this->activeJob->id,
            'sheet_name'    => 'Sales',
            'row_index'     => 1,
            'payload'       => ['Total' => 2500],
            'record_hash'   => hash('sha256', 'lifecycle_rec_1'),
            'generation_id' => $this->activeJob->generation_id,
        ]);

        $mockClient = Mockery::mock(AnalyticsClient::class);
        $mockClient->shouldNotReceive('planCandidateQuery');

        $tool = new LegacyExcelAnalyticsTool(analyticsClient: $mockClient);
        $context = AgentContext::forUser(user: $this->owner, workspaceId: $this->workspace->id);

        $result = $tool->execute(['question' => 'মোট বিক্রি কত?'], $context);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('no_spreadsheet_found', $result->data['status']);
    }
}
