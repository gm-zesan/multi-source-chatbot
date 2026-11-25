<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\KnowledgeSearchLog;
use App\Models\Message;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CanaryStatusReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:canary-status
                            {--json : Output structured JSON telemetry for monitoring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily operational health and telemetry report for active Canary workspaces';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $asJson = (bool) $this->option('json');
        $rolloutMode = config('ai.rollout_mode', 'limited_canary');
        $canaryWorkspaces = (array) config('ai.canary_workspaces', []);
        $dailyLimit = (int) config('ai.canary_daily_limit', 100);
        $todayUtc = now('UTC')->format('Y-m-d');
        $todayLocal = now()->format('Y-m-d');
        $startOfDayUtc = now('UTC')->startOfDay();
        $endOfDayUtc = now('UTC')->endOfDay();

        $workspaceReports = [];
        $overallStatus = 'HEALTHY_CANARY_ACTIVE';
        $hasMismatch = false;
        $hasKillSwitch = false;
        $hasDrift = false;
        $activeWorkspacesCount = 0;

        $cache = $this->getCacheStore();
        $wsLimit = (int) config('ai.canary_max_concurrent_per_workspace', 5);
        $globalLimit = (int) config('ai.canary_max_concurrent_global', 10);
        $globalInFlight = (int) $cache->get('ai_in_flight_global', 0);

        foreach ($canaryWorkspaces as $wsId) {
            $ws = Workspace::find((int) $wsId);
            if (! $ws) {
                continue;
            }

            // Authoritative Database search logs for today UTC [startOfDay, endOfDay]
            $todayLogs = KnowledgeSearchLog::where('workspace_id', $ws->id)
                ->whereBetween('created_at', [$startOfDayUtc, $endOfDayUtc])
                ->get();
            $todayLogCount = $todayLogs->count();

            // Read UTC key first, fall back to local date key if needed
            $trafficKeyUtc = "ai_canary_traffic_{$ws->id}_{$todayUtc}";
            $trafficKeyLocal = "ai_canary_traffic_{$ws->id}_{$todayLocal}";
            $cachedUsage = $cache->get($trafficKeyUtc) ?? $cache->get($trafficKeyLocal);
            if ($cachedUsage === null) {
                // Cold-start key in cache from authoritative DB count
                $cache->add($trafficKeyUtc, $todayLogCount, now('UTC')->endOfDay());
                $currentUsage = $todayLogCount;
            } else {
                $currentUsage = (int) $cachedUsage;
            }

            // In-flight active concurrency
            $activeInFlight = (int) $cache->get("ai_in_flight_ws_{$ws->id}", 0);

            // Rolling 24h search logs [now - 24h, now]
            $rollingLogs = KnowledgeSearchLog::where('workspace_id', $ws->id)
                ->where('created_at', '>=', now()->subHours(24))
                ->get();
            $rollingLogCount = $rollingLogs->count();

            // Calculate metric averages from the active sample pool
            $sampleLogs = $rollingLogCount > 0 ? $rollingLogs : $todayLogs;
            $sampleCount = $sampleLogs->count();
            $avgScore = $sampleCount > 0 ? round((float) $sampleLogs->avg('final_score'), 4) : null;
            $avgLatency = $sampleCount > 0 ? round((float) $sampleLogs->avg('response_time_ms'), 2) : null;

            // Unanswered questions in last 24h & today UTC
            $unansweredCount24h = UnansweredQuestion::where('workspace_id', $ws->id)
                ->where('created_at', '>=', now()->subHours(24))
                ->count();
            $unansweredCountToday = UnansweredQuestion::where('workspace_id', $ws->id)
                ->whereBetween('created_at', [$startOfDayUtc, $endOfDayUtc])
                ->count();

            $unansweredRatio24h = $rollingLogCount > 0 ? round(($unansweredCount24h / $rollingLogCount) * 100, 1) : 0.0;
            $unansweredRatioToday = $todayLogCount > 0 ? round(($unansweredCountToday / $todayLogCount) * 100, 1) : 0.0;

            $killSwitchActive = $ws->isAiKillSwitchActive();

            // Reconcile Daily Quota Counter vs Today UTC Database Records
            // Invariant: Daily quota operates strictly on UTC calendar date. Comparing against 24h rolling
            // would create false drift alarms across the UTC midnight boundary.
            if ($killSwitchActive) {
                $wsStatus = 'KILL_SWITCH_ACTIVE';
                $hasKillSwitch = true;
            } elseif ($currentUsage > 0 && $todayLogCount === 0 && $rollingLogCount === 0) {
                // Quota was consumed but zero telemetry logs were persisted
                $wsStatus = 'CRITICAL_TELEMETRY_MISMATCH';
                $hasMismatch = true;
            } elseif ($currentUsage === 0 && $todayLogCount === 0 && $rollingLogCount === 0) {
                $wsStatus = 'IDLE_NO_PILOT_TRAFFIC';
            } elseif ($currentUsage > 0 && abs($currentUsage - $todayLogCount) > 5) {
                $wsStatus = 'WARNING_TELEMETRY_DRIFT';
                $hasDrift = true;
                $activeWorkspacesCount++;
            } else {
                $wsStatus = 'HEALTHY_ACTIVE';
                $activeWorkspacesCount++;
            }

            $sampleStatus = match (true) {
                $sampleCount >= 5 => 'STATISTICALLY_VALID',
                $sampleCount > 0  => 'LIMITED_SAMPLE',
                default           => 'INSUFFICIENT_SAMPLES',
            };

            $workspaceReports[] = [
                'workspace_id'              => $ws->id,
                'name'                      => $ws->name,
                'is_active'                 => $ws->is_active,
                'kill_switch'               => $killSwitchActive,
                'health_status'             => $wsStatus,
                'sample_status'             => $sampleStatus,
                'telemetry_discrepancy'     => ($currentUsage > 0 && $todayLogCount === 0),
                'daily_usage'               => $currentUsage,
                'daily_limit'               => $dailyLimit,
                'remaining_quota'           => max(0, $dailyLimit - $currentUsage),
                'in_flight_active'          => $activeInFlight,
                'concurrency_limit'         => $wsLimit,
                'queries_today_utc'         => $todayLogCount,
                'queries_24h'               => $rollingLogCount,
                'avg_score_24h'             => $avgScore,
                'avg_latency_ms'            => $avgLatency,
                'unanswered_24h'            => $unansweredCount24h,
                'unanswered_today_utc'      => $unansweredCountToday,
                'unanswered_ratio_24h_pct'  => $unansweredRatio24h,
                'unanswered_ratio_today_pct'=> $unansweredRatioToday,
                'metric_semantics'          => [
                    'score_definition'       => 'mean_knowledge_retrieval_final_score',
                    'latency_definition'     => 'mean_internal_engine_ms',
                    'time_boundaries'        => [
                        'quota_window'       => 'UTC Calendar Day [' . $todayUtc . ' 00:00:00 UTC to ' . $todayUtc . ' 23:59:59 UTC]',
                        'today_utc_window'   => '[' . $startOfDayUtc->toIso8601String() . ' to ' . $endOfDayUtc->toIso8601String() . ']',
                        'rolling_24h_window' => '[' . now()->subHours(24)->toIso8601String() . ' to ' . now()->toIso8601String() . ']',
                    ],
                ],
            ];
        }

        // Aggregate overall status
        if ($hasMismatch) {
            $overallStatus = 'CRITICAL_TELEMETRY_MISMATCH';
        } elseif ($hasKillSwitch) {
            $overallStatus = 'WARNING_KILL_SWITCH_ACTIVE';
        } elseif ($hasDrift) {
            $overallStatus = 'WARNING_TELEMETRY_DRIFT';
        } elseif ($activeWorkspacesCount === 0) {
            $overallStatus = 'IDLE_NO_PILOT_TRAFFIC';
        } else {
            $overallStatus = 'HEALTHY_CANARY_ACTIVE';
        }

        $report = [
            'report_timestamp'       => now()->toIso8601String(),
            'engine_version'         => config('ai.engine_version', 'v2.4-RC1'),
            'rollout_mode'           => $rolloutMode,
            'canary_limit_daily'     => $dailyLimit,
            'max_concurrent_ws'      => $wsLimit,
            'max_concurrent_global'  => $globalLimit,
            'in_flight_global'       => $globalInFlight,
            'overall_status'         => $overallStatus,
            'canary_workspaces'      => $workspaceReports,
        ];

        if ($asJson) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->renderReport($report);
        return self::SUCCESS;
    }

    private function renderReport(array $report): void
    {
        $statusColors = [
            'CRITICAL_TELEMETRY_MISMATCH' => '<fg=white;bg=red;options=bold> CRITICAL_TELEMETRY_MISMATCH </>',
            'WARNING_KILL_SWITCH_ACTIVE'  => '<fg=black;bg=yellow;options=bold> WARNING_KILL_SWITCH_ACTIVE </>',
            'WARNING_TELEMETRY_DRIFT'     => '<fg=black;bg=yellow;options=bold> WARNING_TELEMETRY_DRIFT </>',
            'IDLE_NO_PILOT_TRAFFIC'       => '<fg=black;bg=cyan;options=bold> IDLE_NO_PILOT_TRAFFIC </>',
            'HEALTHY_CANARY_ACTIVE'       => '<fg=black;bg=green;options=bold> HEALTHY_CANARY_ACTIVE </>',
        ];

        $statusBadge = $statusColors[$report['overall_status']] ?? "<options=bold>{$report['overall_status']}</>";

        $this->line('');
        $this->info('========================================================================================');
        $this->info('  ENGINE v2.4-RC1 — DAILY CANARY TELEMETRY & OBSERVATION REPORT');
        $this->info('========================================================================================');
        $this->line("<comment>Engine Version:</comment> {$report['engine_version']} (FROZEN)");
        $this->line("<comment>Rollout Mode:</comment>   <info>{$report['rollout_mode']}</info>");
        $this->line("<comment>Daily Cap / WS:</comment> {$report['canary_limit_daily']} queries/day");
        $this->line("<comment>Host In-Flight:</comment> {$report['in_flight_global']} / {$report['max_concurrent_global']} active slots");
        $this->line("<comment>Overall Status:</comment> {$statusBadge}");
        $this->line('----------------------------------------------------------------------------------------');

        $rows = [];
        foreach ($report['canary_workspaces'] as $w) {
            $ksLabel = $w['kill_switch'] ? '<fg=red;options=bold>ENGAGED</>' : '<fg=green>DISENGAGED</>';
            $quotaLabel = "{$w['daily_usage']} / {$w['daily_limit']}";
            $inFlightLabel = "{$w['in_flight_active']} / {$w['concurrency_limit']}";
            $unansweredLabel = "{$w['unanswered_24h']} ({$w['unanswered_ratio_24h_pct']}%)";

            $stateBadge = match ($w['health_status']) {
                'HEALTHY_ACTIVE'              => '<fg=green>HEALTHY</>',
                'IDLE_NO_PILOT_TRAFFIC'       => '<fg=cyan>IDLE</>',
                'CRITICAL_TELEMETRY_MISMATCH' => '<fg=red;options=bold>MISMATCH</>',
                'WARNING_TELEMETRY_DRIFT'     => '<fg=yellow>DRIFT</>',
                'KILL_SWITCH_ACTIVE'          => '<fg=red>KILL_SWITCH</>',
                default                       => $w['health_status'],
            };

            $scoreDisplay = match (true) {
                $w['avg_score_24h'] !== null            => (string) $w['avg_score_24h'],
                $w['health_status'] === 'CRITICAL_TELEMETRY_MISMATCH' => '<fg=red>MISMATCH</>',
                default                                 => '<fg=yellow>N/A (Idle)</>',
            };

            $latencyDisplay = match (true) {
                $w['avg_latency_ms'] !== null           => "{$w['avg_latency_ms']} ms",
                $w['health_status'] === 'CRITICAL_TELEMETRY_MISMATCH' => '<fg=red>MISMATCH</>',
                default                                 => '<fg=yellow>N/A (Idle)</>',
            };

            $sampleBadge = match ($w['sample_status']) {
                'STATISTICALLY_VALID' => '<fg=green>VALID (n>=5)</>',
                'LIMITED_SAMPLE'      => '<fg=yellow>LIMITED (n<5)</>',
                default               => '<fg=gray>INSUFFICIENT</>',
            };

            $rows[] = [
                $w['workspace_id'],
                $w['name'],
                $stateBadge,
                $ksLabel,
                $quotaLabel,
                $inFlightLabel,
                $w['queries_today_utc'],
                $w['queries_24h'],
                $sampleBadge,
                $scoreDisplay,
                $latencyDisplay,
                $unansweredLabel,
            ];
        }

        $this->table(
            ['WS ID', 'Merchant Name', 'Health State', 'Kill Switch', 'Today Quota', 'In-Flight', 'Today DB', '24h Rolling', 'Sample Status', 'Avg Score', 'Avg Latency', 'Unanswered (Ratio)'],
            $rows
        );
        $this->line('<comment>Forensic Metric Semantics & Time Boundaries:</comment>');
        $this->line('  - <info>Today Quota</info>:        Atomic Redis/Cache usage vs daily limit for calendar day [00:00:00 UTC - 23:59:59 UTC]');
        $this->line('  - <info>In-Flight</info>:          Active simultaneous requests vs workspace concurrency cap');
        $this->line('  - <info>Today DB</info>:           Authoritative KnowledgeSearchLog count within same calendar day [00:00:00 UTC - 23:59:59 UTC]');
        $this->line('  - <info>24h Rolling</info>:        Audit log records within the moving window [now - 24 hours, now]');
        $this->line('  - <info>Avg Score</info>:          Mean knowledge vector/hybrid relevance score [0.0000 - 1.0000]');
        $this->line('  - <info>Avg Latency</info>:        Mean internal orchestrator processing time in ms');
        $this->line('  - <info>Unanswered (Ratio)</info>: Unanswered questions in 24h & percentage of rolling traffic');
        $this->line('');
    }

    /**
     * Resolve appropriate cache store for canary telemetry.
     */
    private function getCacheStore(): \Illuminate\Contracts\Cache\Repository
    {
        $preferred = config('ai.cache_store');
        if ($preferred) {
            return Cache::store($preferred);
        }

        if (app()->environment('testing')) {
            return Cache::store();
        }

        try {
            if (config('database.redis.default') && extension_loaded('redis')) {
                return Cache::store('redis');
            }
        } catch (\Throwable $e) {}

        return Cache::store();
    }
}
