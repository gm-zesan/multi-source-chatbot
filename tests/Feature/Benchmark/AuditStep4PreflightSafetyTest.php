<?php

declare(strict_types=1);

namespace Tests\Feature\Benchmark;

use App\Models\Workspace;
use App\Services\Retrieval\RetrievalClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditStep4PreflightSafetyTest extends TestCase
{
    use RefreshDatabase;
    private function withSimulatedDisposableConnection(callable $callback): void
    {
        $tempDb = sys_get_temp_dir() . '/chatbot_test_disposable';
        touch($tempDb);

        config(['database.connections.mysql_disposable' => [
            'driver' => 'sqlite',
            'database' => $tempDb,
            'prefix' => '',
        ]]);
        $originalDefault = config('database.default');
        config(['database.default' => 'mysql_disposable']);
        DB::setDefaultConnection('mysql_disposable');

        $this->artisan('migrate', ['--database' => 'mysql_disposable']);

        try {
            $callback();
        } finally {
            config(['database.default' => $originalDefault]);
            DB::setDefaultConnection($originalDefault);
            DB::purge('mysql_disposable');
            if (file_exists($tempDb)) {
                @unlink($tempDb);
            }
        }
    }

    public function test_dry_run_executes_cleanly_without_writes_or_external_calls(): void
    {
        $this->artisan('debug:audit-step4-generation', [
            '--dry-run' => true,
            '--limit' => 4,
        ])
            ->expectsOutputToContain('PREFLIGHT VALIDATION & ISOLATION CHECK (DRY-RUN)')
            ->expectsOutputToContain('Target Database Schema & Table Counts')
            ->expectsOutputToContain('LLM Provider Configuration')
            ->expectsOutputToContain('PREFLIGHT COMPLETE: Zero database writes executed, zero external API calls dispatched.')
            ->assertExitCode(0);

        // Strict verification: zero records created across tables
        $this->assertSame(0, DB::table('workspaces')->count());
        $this->assertSame(0, DB::table('channels')->count());
        $this->assertSame(0, DB::table('channel_accounts')->count());
        $this->assertSame(0, DB::table('conversations')->count());
        $this->assertSame(0, DB::table('messages')->count());
    }

    public function test_dry_run_supports_explicit_output_destination(): void
    {
        $this->artisan('debug:audit-step4-generation', [
            '--dry-run' => true,
            '--output' => 'tests/Datasets/step4_generation_candidate_report.json',
        ])
            ->expectsOutputToContain('tests/Datasets/step4_generation_candidate_report.json')
            ->expectsOutputToContain('Baseline Safe: YES')
            ->assertExitCode(0);
    }

    public function test_hard_blocks_attempt_to_overwrite_baseline_report(): void
    {
        $this->artisan('debug:audit-step4-generation', [
            '--output' => 'tests/Datasets/step4_generation_baseline_report.json',
        ])
            ->expectsOutputToContain('CRITICAL SAFETY BLOCK: Overwriting the committed baseline report')
            ->assertExitCode(1);
    }

    public function test_refuses_live_run_against_chatbot_core_fail_closed(): void
    {
        $tempCoreDb = sys_get_temp_dir() . '/chatbot_core';
        touch($tempCoreDb);

        config(['database.connections.core_sim' => [
            'driver' => 'sqlite',
            'database' => $tempCoreDb,
            'prefix' => '',
        ]]);
        $originalDefault = config('database.default');
        config(['database.default' => 'core_sim']);

        try {
            $this->artisan('debug:audit-step4-generation', [
                '--limit' => 1,
            ])
                ->expectsOutputToContain('CRITICAL SAFETY BLOCK: Refusing to execute live benchmark against unapproved or non-disposable database [chatbot_core]')
                ->assertExitCode(1);
        } finally {
            config(['database.default' => $originalDefault]);
            if (file_exists($tempCoreDb)) {
                @unlink($tempCoreDb);
            }
        }
    }

    public function test_unapproved_database_name_containing_test_is_rejected(): void
    {
        $tempTestDb = sys_get_temp_dir() . '/unapproved_test_db';
        touch($tempTestDb);

        config(['database.connections.unapproved_sim' => [
            'driver' => 'sqlite',
            'database' => $tempTestDb,
            'prefix' => '',
        ]]);
        $originalDefault = config('database.default');
        config(['database.default' => 'unapproved_sim']);

        try {
            $this->artisan('debug:audit-step4-generation', [
                '--limit' => 1,
            ])
                ->expectsOutputToContain('CRITICAL SAFETY BLOCK: Refusing to execute live benchmark against unapproved or non-disposable database [unapproved_test_db]')
                ->assertExitCode(1);
        } finally {
            config(['database.default' => $originalDefault]);
            if (file_exists($tempTestDb)) {
                @unlink($tempTestDb);
            }
        }
    }

    public function test_sqlite_memory_database_is_approved_disposable(): void
    {
        $this->artisan('debug:audit-step4-generation', [
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Connected Database: :memory: (Disposable / Isolated)')
            ->assertExitCode(0);
    }

    public function test_chatbot_test_disposable_database_is_approved_disposable(): void
    {
        $tempDb = sys_get_temp_dir() . '/chatbot_test_disposable';
        touch($tempDb);

        config(['database.connections.disposable_sim' => [
            'driver' => 'sqlite',
            'database' => $tempDb,
            'prefix' => '',
        ]]);
        $originalDefault = config('database.default');
        config(['database.default' => 'disposable_sim']);
        DB::setDefaultConnection('disposable_sim');

        try {
            $this->artisan('debug:audit-step4-generation', [
                '--dry-run' => true,
            ])
                ->expectsOutputToContain('Connected Database: chatbot_test_disposable (Disposable / Isolated)')
                ->assertExitCode(0);
        } finally {
            config(['database.default' => $originalDefault]);
            DB::setDefaultConnection($originalDefault);
            DB::purge('disposable_sim');
            if (file_exists($tempDb)) {
                @unlink($tempDb);
            }
        }
    }

    public function test_retrieval_service_offline_in_preflight_reports_warning_cleanly(): void
    {
        $ws = Workspace::create(['name' => 'Preflight Offline WS', 'slug' => 'preflight-offline-ws']);

        Http::fake([
            '*/health*' => Http::response(['status' => 'error'], 503),
            '*/api/v1/health*' => Http::response(['status' => 'error'], 503),
        ]);

        $this->artisan('debug:audit-step4-generation', [
            '--dry-run' => true,
            '--workspace' => $ws->id,
        ])
            ->expectsOutputToContain('Retrieval Service Readiness')
            ->expectsOutputToContain('Retrieval Service: OFFLINE (Unreachable: HTTP 503)')
            ->assertExitCode(0);
    }

    public function test_retrieval_service_offline_in_live_run_fails_closed(): void
    {
        $this->withSimulatedDisposableConnection(function () {
            $ws = Workspace::create(['name' => 'Live Offline WS', 'slug' => 'live-offline-ws']);

            Http::fake([
                '*/health*' => Http::response(['status' => 'error'], 503),
                '*/api/v1/health*' => Http::response(['status' => 'error'], 503),
            ]);

            $this->artisan('debug:audit-step4-generation', [
                '--workspace' => $ws->id,
                '--limit' => 1,
            ])
                ->expectsOutputToContain('CRITICAL SAFETY BLOCK: Python retrieval service at')
                ->assertExitCode(1);
        });
    }

    public function test_retrieval_service_becoming_unavailable_during_execution_aborts_benchmark(): void
    {
        $this->withSimulatedDisposableConnection(function () {
            $ws = Workspace::create(['name' => 'Mid-Run Outage WS', 'slug' => 'mid-run-outage-ws']);
            $candidateExportPath = 'tests/Datasets/step4_generation_test_outage_report.json';

            // Preflight health succeeds, but search fails during turn execution
            Http::fake([
                '*/health*' => Http::response(['status' => 'ok'], 200),
                '*/api/v1/search*' => Http::response(['error' => 'Service Unavailable'], 503),
            ]);

            $this->artisan('debug:audit-step4-generation', [
                '--workspace' => $ws->id,
                '--limit' => 1,
                '--output' => $candidateExportPath,
                '--export' => true,
            ])
                ->expectsOutputToContain('CRITICAL INFRASTRUCTURE FAILURE: Retrieval service failed during scenario')
                ->assertExitCode(1);

            // Assert no corrupted report was exported
            $fullPath = base_path($candidateExportPath);
            $this->assertFileDoesNotExist($fullPath);
        });
    }

    public function test_legitimate_zero_hit_ood_query_is_not_classified_as_infrastructure_failure(): void
    {
        /** @var RetrievalClient $client */
        $client = app(RetrievalClient::class);

        // Python service returns 200 OK with 0 results for an OOD question
        Http::fake([
            '*/api/v1/search*' => Http::response([
                'results' => [],
                'telemetry' => [
                    'request_id' => 'req_ood_123',
                    'workspace_id' => 1,
                    'first_pass_score' => 0.12,
                    'returned_faq_ids' => [],
                ],
            ], 200),
        ]);

        $results = $client->search('Can I order a pepperoni pizza from your clothing showroom?', 1);

        // Assert results are legitimately empty (zero hits)
        $this->assertCount(0, $results);

        // Assert it was NOT classified as an infrastructure fallback/failure
        $this->assertFalse($client->wasLastSearchFallback());
        $this->assertNull($client->getLastSearchError());
        $this->assertNotEmpty($client->getLastTelemetry());
        $this->assertSame('req_ood_123', $client->getLastTelemetry()['request_id']);
    }

    public function test_retrieval_client_rejects_malformed_json_response_as_infrastructure_failure(): void
    {
        /** @var RetrievalClient $client */
        $client = app(RetrievalClient::class);

        // Gateway returns 200 OK with HTML or plain text instead of JSON
        Http::fake([
            '*/api/v1/search*' => Http::response('<html><body>Service Temporarily Degraded</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $results = $client->search('What is your delivery coverage?', 1);

        $this->assertCount(0, $results);
        $this->assertTrue($client->wasLastSearchFallback());
        $this->assertStringContainsString('Malformed retrieval response', (string) $client->getLastSearchError());
    }

    public function test_retrieval_client_rejects_http_200_missing_results_key_as_infrastructure_failure(): void
    {
        /** @var RetrievalClient $client */
        $client = app(RetrievalClient::class);

        // API returns 200 OK but payload is missing 'results' array (e.g. error payload or empty JSON)
        Http::fake([
            '*/api/v1/search*' => Http::response(['status' => 'partial_outage', 'detail' => 'Index rebuild in progress'], 200),
        ]);

        $results = $client->search('What is your exchange policy?', 1);

        $this->assertCount(0, $results);
        $this->assertTrue($client->wasLastSearchFallback());
        $this->assertStringContainsString('Malformed retrieval response', (string) $client->getLastSearchError());
    }

    public function test_infrastructure_failures_do_not_produce_misleading_grounding_reports(): void
    {
        $this->withSimulatedDisposableConnection(function () {
            $ws = Workspace::create(['name' => 'No False Abstention WS', 'slug' => 'no-false-abstention-ws']);
            $candidateExportPath = 'tests/Datasets/step4_generation_test_false_abstention.json';

            // Retrieval fails with network/timeout error
            Http::fake([
                '*/health*' => Http::response(['status' => 'ok'], 200),
                '*/api/v1/search*' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Connection refused to Python AI service');
                },
            ]);

            $this->artisan('debug:audit-step4-generation', [
                '--workspace' => $ws->id,
                '--limit' => 1,
                '--output' => $candidateExportPath,
                '--export' => true,
            ])
                ->expectsOutputToContain('CRITICAL INFRASTRUCTURE FAILURE: Retrieval service failed')
                ->assertExitCode(1);

            // Strict verification: Aborted run did not write misleading report file
            $this->assertFileDoesNotExist(base_path($candidateExportPath));
        });
    }

    public function test_explicit_workspace_selection_valid_in_preflight(): void
    {
        $ws = Workspace::create(['name' => 'Explicit Benchmark WS', 'slug' => 'explicit-bench-ws']);

        $this->artisan('debug:audit-step4-generation', [
            '--dry-run' => true,
            '--workspace' => $ws->id,
        ])
            ->expectsOutputToContain('Explicit Binding')
            ->expectsOutputToContain("Found (ID: {$ws->id}, Name: '{$ws->name}')")
            ->assertExitCode(0);

        // Confirm dry-run created zero additional records
        $this->assertSame(1, DB::table('workspaces')->count());
        $this->assertSame(0, DB::table('channels')->count());
        $this->assertSame(0, DB::table('channel_accounts')->count());
        $this->assertSame(0, DB::table('conversations')->count());
        $this->assertSame(0, DB::table('messages')->count());
    }

    public function test_explicit_workspace_missing_in_preflight_reports_warning_cleanly(): void
    {
        $this->artisan('debug:audit-step4-generation', [
            '--dry-run' => true,
            '--workspace' => 99999,
        ])
            ->expectsOutputToContain('Explicit Binding')
            ->expectsOutputToContain('Specified workspace [99999] does not exist in active database')
            ->assertExitCode(0);
    }

    public function test_explicit_workspace_missing_fails_closed(): void
    {
        $this->withSimulatedDisposableConnection(function () {
            $this->artisan('debug:audit-step4-generation', [
                '--workspace' => 99999,
                '--limit' => 1,
            ])
                ->expectsOutputToContain('CRITICAL SAFETY BLOCK: Specified workspace [99999] does not exist in active database')
                ->assertExitCode(1);
        });
    }

    public function test_invalid_workspace_option_format_fails_closed(): void
    {
        $this->withSimulatedDisposableConnection(function () {
            $this->artisan('debug:audit-step4-generation', [
                '--workspace' => 'invalid_ws',
                '--limit' => 1,
            ])
                ->expectsOutputToContain('CRITICAL SAFETY BLOCK: Invalid --workspace argument [invalid_ws]')
                ->assertExitCode(1);
        });
    }

    public function test_missing_workspace_in_empty_database_fails_closed_without_auto_create(): void
    {
        $this->withSimulatedDisposableConnection(function () {
            // Ensure database has 0 workspaces
            $this->assertSame(0, DB::table('workspaces')->count());

            $this->artisan('debug:audit-step4-generation', [
                '--limit' => 1,
            ])
                ->expectsOutputToContain('CRITICAL SAFETY BLOCK: No workspace found in active database')
                ->assertExitCode(1);

            // Assert no workspace was auto-created
            $this->assertSame(0, DB::table('workspaces')->count());
        });
    }

    public function test_refuses_live_run_against_sqlite_memory_fail_closed(): void
    {
        $this->artisan('debug:audit-step4-generation', [
            '--limit' => 1,
        ])
            ->expectsOutputToContain('CRITICAL SAFETY BLOCK: Refusing to execute live benchmark against unapproved or non-disposable database [:memory:]')
            ->assertExitCode(1);
    }

    public function test_refuses_live_run_when_connection_is_not_mysql_disposable(): void
    {
        $tempDb = sys_get_temp_dir() . '/chatbot_test_disposable';
        touch($tempDb);

        config(['database.connections.standard_mysql_sim' => [
            'driver' => 'sqlite',
            'database' => $tempDb,
            'prefix' => '',
        ]]);
        $originalDefault = config('database.default');
        config(['database.default' => 'standard_mysql_sim']);
        DB::setDefaultConnection('standard_mysql_sim');

        try {
            $this->artisan('debug:audit-step4-generation', [
                '--limit' => 1,
            ])
                ->expectsOutputToContain('Live benchmark execution requires active connection [mysql_disposable] to MySQL database [chatbot_test_disposable]')
                ->assertExitCode(1);
        } finally {
            config(['database.default' => $originalDefault]);
            DB::setDefaultConnection($originalDefault);
            DB::purge('standard_mysql_sim');
            if (file_exists($tempDb)) {
                @unlink($tempDb);
            }
        }
    }

    public function test_preflight_database_error_fails_closed_cleanly_without_uncaught_stack_trace(): void
    {
        config(['database.connections.broken_db_sim' => [
            'driver' => 'sqlite',
            'database' => '/non_existent_dir_safety_test/broken_file.sqlite',
            'prefix' => '',
        ]]);
        $originalDefault = config('database.default');
        config(['database.default' => 'broken_db_sim']);
        DB::setDefaultConnection('broken_db_sim');

        try {
            $this->artisan('debug:audit-step4-generation', [
                '--dry-run' => true,
                '--workspace' => 1,
            ])
                ->expectsOutputToContain('CRITICAL DATABASE ERROR: Unable to connect to or query active database')
                ->assertExitCode(1);
        } finally {
            config(['database.default' => $originalDefault]);
            DB::setDefaultConnection($originalDefault);
            DB::purge('broken_db_sim');
        }
    }

    public function test_benchmark_guard_inspects_exact_support_service_retrieval_dependency(): void
    {
        $this->withSimulatedDisposableConnection(function () {
            $ws = Workspace::create(['name' => 'Dep Check WS', 'slug' => 'dep-check-ws']);

            $customClient = new RetrievalClient('http://127.0.0.1:8001');
            $customFaq = new \App\Services\FAQ\FAQSearch($customClient);
            $supportService = new \App\Services\AI\CustomerSupportService(
                faqSearch: $customFaq,
                conversationService: app(\App\Services\Chat\ConversationService::class),
            );

            $this->app->instance(\App\Services\AI\CustomerSupportService::class, $supportService);

            Http::fake([
                '*/health*' => Http::response(['status' => 'ok'], 200),
                '*/api/v1/search*' => Http::response(['error' => 'Simulated Outage'], 503),
            ]);

            $this->artisan('debug:audit-step4-generation', [
                '--workspace' => $ws->id,
                '--limit' => 1,
            ])
                ->expectsOutputToContain('CRITICAL INFRASTRUCTURE FAILURE: Retrieval service failed during scenario')
                ->assertExitCode(1);

            $this->assertTrue($supportService->getFAQSearch()->wasLastSearchFallback());
        });
    }
}

