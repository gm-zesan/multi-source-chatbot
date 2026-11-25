<?php

declare(strict_types=1);

namespace App\AI\Decision;

use App\AI\Decision\Contracts\DecisionEngineInterface;
use App\AI\Decision\DTOs\DecisionRequest;
use App\AI\Decision\DTOs\DecisionResult;
use App\AI\Decision\DTOs\ExecutionResponse;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Memory\ConversationMemoryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Master Unified AI Orchestrator.
 *
 * Implements the streamlined 5-layer pipeline:
 * User Request -> Preflight Security -> Unified Decision Engine -> Policy Validation & Deterministic Execution -> Final Response
 */
class UnifiedOrchestrator
{
    /**
     * Frozen production engine release version.
     */
    public const ENGINE_VERSION = 'v2.4-RC1';

    public function __construct(
        private readonly DecisionEngineInterface $decisionEngine,
        private readonly DeterministicExecutionController $executionController,
        private readonly FinalResponseGenerator $responseGenerator,
        private readonly ?ConversationMemoryService $memoryService = null,
    ) {}

    /**
     * Process an incoming turn end-to-end.
     *
     * @param string $query Natural language user request
     * @param int $workspaceId Trusted workspace identifier
     * @param User|null $user Authenticated user
     * @param Conversation|null $conversation Active conversation context
     * @param string $channelSlug Entry channel (e.g. 'simulator', 'whatsapp', 'facebook', 'web_widget')
     * @param bool|null $isCustomer Explicit channel boundary override
     * @return array<string, mixed> Final structured response matching application contracts
     */
    public function process(
        string $query,
        int $workspaceId,
        ?User $user = null,
        ?Conversation $conversation = null,
        string $channelSlug = 'simulator',
        ?bool $isCustomer = null,
    ): array {
        $tStart = microtime(true);
        $turnId = (string) \Illuminate\Support\Str::uuid();

        // ── Operational Rollout & Emergency Kill Switch Evaluation ──────────
        if ($conversation !== null && $conversation->isAiPaused()) {
            Log::info("[UnifiedOrchestrator] AI processing halted: Conversation AI is paused by human staff (conv_id: {$conversation->id})");
            return $this->buildHandoffResponse($workspaceId, 'conversation_ai_paused', $tStart);
        }

        $rolloutMode = config('ai.rollout_mode', 'limited_canary');
        if ($rolloutMode === 'disabled') {
            Log::warning('[UnifiedOrchestrator] AI processing blocked: AI engine globally disabled');
            return $this->buildHandoffResponse($workspaceId, 'ai_engine_globally_disabled', $tStart);
        }

        if ($rolloutMode === 'limited_canary') {
            if ($workspaceId <= 0) {
                Log::warning("[UnifiedOrchestrator] AI processing blocked: Missing or invalid tenant context ({$workspaceId})");
                return $this->buildHandoffResponse($workspaceId, 'missing_tenant_context', $tStart);
            }

            try {
                $workspace = Workspace::find($workspaceId);
                if ($workspace === null || ! $workspace->isAiRolloutEnabled()) {
                    $reason = $workspace?->isAiKillSwitchActive()
                        ? 'workspace_kill_switch_active'
                        : ($workspace === null ? 'unknown_workspace_unauthorized' : 'workspace_not_in_canary_rollout');

                    Log::info("[UnifiedOrchestrator] AI processing halted for workspace {$workspaceId}: {$reason}");
                    return $this->buildHandoffResponse($workspaceId, $reason, $tStart, $workspace);
                }

                // Daily Canary Traffic Guardrail (Default: 100 queries/workspace/day) - Atomic Enforcement
                $dailyLimit = (int) config('ai.canary_daily_limit', 100);

                if ($dailyLimit > 0) {
                    $todayUtc = now('UTC')->format('Y-m-d');
                    $trafficKey = "ai_canary_traffic_{$workspaceId}_{$todayUtc}";

                    try {
                        if (! $this->cacheHas($trafficKey)) {
                            $todayDbCount = \App\Models\KnowledgeSearchLog::where('workspace_id', $workspaceId)
                                ->whereBetween('created_at', [now('UTC')->startOfDay(), now('UTC')->endOfDay()])
                                ->count();
                            $this->cacheAdd($trafficKey, $todayDbCount, now('UTC')->endOfDay());
                        }
                        [$quotaAllowed, $currentUsage] = $this->atomicAcquireDailyQuota($trafficKey, $dailyLimit);

                        if (! $quotaAllowed) {
                            Log::warning("[UnifiedOrchestrator] AI processing halted for workspace {$workspaceId}: canary_daily_quota_exceeded ({$currentUsage}/{$dailyLimit})");
                            return $this->buildHandoffResponse($workspaceId, 'canary_daily_quota_exceeded', $tStart, $workspace);
                        }
                        $acquiredDailyQuota = true;
                    } catch (\Throwable $cacheErr) {
                        Log::error("[UnifiedOrchestrator] Cache failure during atomic quota check: " . $cacheErr->getMessage());
                        return $this->buildHandoffResponse($workspaceId, 'canary_quota_cache_failure', $tStart, $workspace);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('[UnifiedOrchestrator] Rollout evaluation failure, failing closed: ' . $e->getMessage());
                return $this->buildHandoffResponse($workspaceId, 'tenant_verification_failure', $tStart);
            }
        } elseif ($workspaceId > 0) {
            // General rollout mode ('all'): still strictly enforce per-workspace emergency kill switch
            try {
                $workspace = Workspace::find($workspaceId);
                if ($workspace !== null && $workspace->isAiKillSwitchActive()) {
                    Log::info("[UnifiedOrchestrator] AI processing halted for workspace {$workspaceId}: workspace_kill_switch_active");
                    return $this->buildHandoffResponse($workspaceId, 'workspace_kill_switch_active', $tStart, $workspace);
                }
            } catch (\Throwable $e) {
                Log::debug('[UnifiedOrchestrator] Kill switch check bypassed: ' . $e->getMessage());
            }
        }

        // ── In-Flight Concurrency Guardrails (Enforced in Production Code) ──
        $globalLimit = (int) config('ai.canary_max_concurrent_global', 10);
        $wsLimit = (int) config('ai.canary_max_concurrent_per_workspace', 5);
        $acquiredGlobalSlot = false;
        $acquiredWsSlot = false;
        $globalConcurrencyKey = 'ai_in_flight_global';
        $wsConcurrencyKey = "ai_in_flight_ws_{$workspaceId}";

        if ($globalLimit > 0) {
            try {
                [$globalAllowed, $currentGlobal] = $this->atomicAcquireConcurrencySlot($globalConcurrencyKey, $globalLimit, 60, $turnId);
                if (! $globalAllowed) {
                    if (isset($acquiredDailyQuota) && $acquiredDailyQuota && isset($trafficKey)) {
                        $this->atomicRollbackDailyQuota($trafficKey);
                        $acquiredDailyQuota = false;
                    }
                    Log::warning("[UnifiedOrchestrator] AI processing halted: canary_global_concurrency_exceeded ({$currentGlobal}/{$globalLimit})");
                    return $this->buildHandoffResponse($workspaceId, 'canary_global_concurrency_exceeded', $tStart, $workspace ?? null);
                }
                $acquiredGlobalSlot = true;
            } catch (\Throwable $cacheErr) {
                if (isset($acquiredDailyQuota) && $acquiredDailyQuota && isset($trafficKey)) {
                    $this->atomicRollbackDailyQuota($trafficKey);
                    $acquiredDailyQuota = false;
                }
                Log::error("[UnifiedOrchestrator] Cache failure during global concurrency check: " . $cacheErr->getMessage());
                return $this->buildHandoffResponse($workspaceId, 'canary_concurrency_cache_failure', $tStart, $workspace ?? null);
            }
        }

        if ($wsLimit > 0 && $workspaceId > 0) {
            try {
                [$wsAllowed, $currentWs] = $this->atomicAcquireConcurrencySlot($wsConcurrencyKey, $wsLimit, 60, $turnId);
                if (! $wsAllowed) {
                    if ($acquiredGlobalSlot) {
                        $this->atomicReleaseConcurrencySlot($globalConcurrencyKey, $turnId);
                        $acquiredGlobalSlot = false;
                    }
                    if (isset($acquiredDailyQuota) && $acquiredDailyQuota && isset($trafficKey)) {
                        $this->atomicRollbackDailyQuota($trafficKey);
                        $acquiredDailyQuota = false;
                    }
                    Log::warning("[UnifiedOrchestrator] AI processing halted for workspace {$workspaceId}: canary_workspace_concurrency_exceeded ({$currentWs}/{$wsLimit})");
                    return $this->buildHandoffResponse($workspaceId, 'canary_workspace_concurrency_exceeded', $tStart, $workspace ?? null);
                }
                $acquiredWsSlot = true;
            } catch (\Throwable $cacheErr) {
                if ($acquiredGlobalSlot) {
                    $this->atomicReleaseConcurrencySlot($globalConcurrencyKey, $turnId);
                    $acquiredGlobalSlot = false;
                }
                if (isset($acquiredDailyQuota) && $acquiredDailyQuota && isset($trafficKey)) {
                    $this->atomicRollbackDailyQuota($trafficKey);
                    $acquiredDailyQuota = false;
                }
                Log::error("[UnifiedOrchestrator] Cache failure during workspace concurrency check: " . $cacheErr->getMessage());
                return $this->buildHandoffResponse($workspaceId, 'canary_concurrency_cache_failure', $tStart, $workspace ?? null);
            }
        }

        try {
            // ── Layer 1: Preflight & Security Context ────────────────────────────
            $isCustomerChannel = $isCustomer ?? $this->resolveCustomerChannel($user, $channelSlug, $conversation);
            $compactContext = $this->buildCompactContext($conversation, $query, $workspaceId);

            $request = new DecisionRequest(
                query: $query,
                workspaceId: $workspaceId,
                user: $user,
                conversation: $conversation,
                channelSlug: $channelSlug,
                isCustomer: $isCustomerChannel,
                compactContext: $compactContext,
            );

            // ── Layer 2: Unified Decision Engine (Single Structured Decision) ───
            $decision = $this->decisionEngine->decide($request);

            // ── Layer 3 & 4: Policy Validation & Deterministic Tool Execution ───
            $execution = $this->executionController->execute($decision, $request);

            // ── Layer 5: Final Grounded Response Generation ─────────────────────
            $finalOutput = $this->responseGenerator->generate($request, $decision, $execution);

            $totalMs = round((microtime(true) - $tStart) * 1000, 2);

            // Structured diagnostics and telemetry
            $finalOutput['decision_metadata'] = array_merge(
                ['engine_version' => self::ENGINE_VERSION],
                $decision->decisionMetadata,
                $execution->telemetry,
                [
                    'total_orchestrator_ms' => $totalMs,
                    'turn_id'               => $turnId,
                ]
            );

            if (isset($finalOutput['latency_breakdown'])) {
                $finalOutput['latency_breakdown']['total_ms'] = $totalMs;
                $finalOutput['latency_breakdown']['total_e2e_ms'] = $totalMs;
            }

            if (isset($finalOutput['routing_telemetry'])) {
                $finalOutput['routing_telemetry']['total_e2e_ms'] = $totalMs;
            }

            // ── Dispatch Decoupled Telemetry Observer ────────────────────────────
            if ($workspaceId > 0) {
                try {
                    $rawTopHit = $finalOutput['top_hit'] ?? null;
                    $matchedFaqId = null;
                    $keywordScore = 0.0;
                    $semanticScore = 0.0;
                    $finalScore = (float) ($finalOutput['confidence'] ?? 0.0);

                    if ($rawTopHit instanceof \App\Services\FAQ\FAQSearchResult) {
                        $matchedFaqId = (string) $rawTopHit->faq->id;
                        $keywordScore = (float) ($rawTopHit->keywordScore ?? 0.0);
                        $semanticScore = (float) ($rawTopHit->semanticScore ?? 0.0);
                        $finalScore = (float) $rawTopHit->finalScore;
                    } elseif (is_array($rawTopHit)) {
                        $matchedFaqId = (string) ($rawTopHit['faq_id'] ?? $rawTopHit['id'] ?? null);
                        $keywordScore = (float) ($rawTopHit['keyword_score'] ?? 0.0);
                        $semanticScore = (float) ($rawTopHit['semantic_score'] ?? 0.0);
                        $finalScore = (float) ($rawTopHit['final_score'] ?? $finalScore);
                    }

                    event(new \App\Events\AITelemetryRecorded(
                        conversation: $conversation,
                        outboundMessage: null,
                        query: $query,
                        reply: (string) ($finalOutput['reply'] ?? ''),
                        telemetry: [
                            'turn_id'                => $turnId,
                            'route'                  => $finalOutput['route'] ?? 'knowledge',
                            'confidence'             => $finalScore,
                            'final_score'            => $finalScore,
                            'keyword_score'          => $keywordScore,
                            'semantic_score'         => $semanticScore,
                            'answered'               => $finalOutput['answered'] ?? false,
                            'total_time_ms'          => $totalMs,
                            'answerability_decision' => $finalOutput['answerability_decision'] ?? null,
                            'routing_telemetry'      => $finalOutput['routing_telemetry'] ?? [],
                            'llm_usage'              => [
                                'prompt_tokens'     => $finalOutput['raw_llm_response']['prompt_tokens'] ?? $finalOutput['decision_metadata']['prompt_tokens'] ?? 0,
                                'completion_tokens' => $finalOutput['raw_llm_response']['completion_tokens'] ?? $finalOutput['decision_metadata']['completion_tokens'] ?? 0,
                                'total_tokens'      => $finalOutput['raw_llm_response']['total_tokens'] ?? $finalOutput['decision_metadata']['total_tokens'] ?? 0,
                            ],
                            'provider'               => $finalOutput['decision_metadata']['provider'] ?? config('ai.default', 'deepseek'),
                            'model'                  => $finalOutput['decision_metadata']['model'] ?? config('ai.default_model', 'deepseek-chat'),
                            'matched_faq_id'         => $matchedFaqId,
                            'channel'                => $channelSlug,
                        ],
                        workspaceId: $workspaceId,
                    ));
                } catch (\Throwable $e) {
                    Log::warning('[UnifiedOrchestrator] Telemetry dispatch failed: ' . $e->getMessage());
                }
            }

            return $finalOutput;
        } finally {
            if ($acquiredWsSlot) {
                $acquiredWsSlot = false;
                $this->atomicReleaseConcurrencySlot($wsConcurrencyKey, $turnId);
            }
            if ($acquiredGlobalSlot) {
                $acquiredGlobalSlot = false;
                $this->atomicReleaseConcurrencySlot($globalConcurrencyKey, $turnId);
            }
        }
    }

    /**
     * Resolve whether channel is an untrusted public customer channel.
     */
    protected function resolveCustomerChannel(?User $user, string $channelSlug, ?Conversation $conversation): bool
    {
        $publicSlugs = ['facebook', 'messenger', 'whatsapp', 'instagram', 'telegram', 'web', 'website', 'web_widget', 'widget', 'public'];
        if (in_array(mb_strtolower($channelSlug), $publicSlugs, true)) {
            return true;
        }

        // Administrative channels (simulator, admin, dashboard, console)
        if ($user !== null) {
            $isOwnerOrManager = $user->hasRole('superadmin')
                || $user->hasRole('admin')
                || $user->hasRole('owner')
                || $user->hasRole('manager')
                || $user->can('view_analytics');

            if ($isOwnerOrManager) {
                return false;
            }
        }

        // Staff without analytics permissions in simulator is treated with customer security barriers
        return true;
    }

    /**
     * Extract compact conversation context (short-term dialogue turns + long-term graph memory).
     */
    protected function buildCompactContext(?Conversation $conversation, ?string $query = null, ?int $workspaceId = null): ?string
    {
        if ($conversation === null || !$conversation->exists) {
            return null;
        }

        $lines = [];

        // 1. Long-Term Graph Memory (Neo4j: Past preferences, orders, issues)
        if ($query !== null && $workspaceId !== null) {
            try {
                $memSvc = $this->memoryService ?? app(ConversationMemoryService::class);
                $graphMemory = $memSvc->retrieveContext(
                    conversation: $conversation,
                    query: $query,
                    workspaceId: $workspaceId,
                );
                if (!empty($graphMemory)) {
                    $lines[] = "[Customer Profile & Past Preferences]\n" . trim((string) $graphMemory);
                }
            } catch (\Throwable $e) {
                Log::debug('[UnifiedOrchestrator] Graph memory retrieval bypassed: ' . $e->getMessage());
            }
        }

        // 2. Short-Term Dialogue Context (recent substantive turns across 6-8 dialogue rounds)
        $messagesLimit = (int) config('ai.memory.max_messages', 16);
        $messages = $conversation->messages()
            ->latest('id')
            ->take($messagesLimit)
            ->get()
            ->reverse();

        if (!$messages->isEmpty()) {
            $dialogueLines = [];
            foreach ($messages as $msg) {
                $speaker = $msg->is_from_user ? 'User' : 'Assistant';
                $text = trim(strip_tags((string) ($msg->body ?? '')));
                if (mb_strlen($text) > 400) {
                    $text = mb_substr($text, 0, 400) . '...';
                }
                if ($text !== '') {
                    $dialogueLines[] = "{$speaker}: {$text}";
                }
            }
            if (!empty($dialogueLines)) {
                $lines[] = "[Recent Dialogue Turns]\n" . implode("\n", $dialogueLines);
            }
        }

        return !empty($lines) ? implode("\n\n", $lines) : null;
    }

    /**
     * Cache abstraction supporting Redis in production and standard Cache in tests.
     */
    protected function shouldUseRedis(): bool
    {
        if (config('ai.cache_store') === 'redis') {
            return true;
        }
        if (app()->environment('testing')) {
            return false;
        }
        return (bool) (config('database.redis.default') && extension_loaded('redis'));
    }

    protected function cacheHas(string $key): bool
    {
        if ($this->shouldUseRedis()) {
            try {
                return Cache::store('redis')->has($key);
            } catch (\Throwable) {}
        }
        return Cache::has($key);
    }

    protected function cacheAdd(string $key, mixed $value, mixed $ttl = null): bool
    {
        if ($this->shouldUseRedis()) {
            try {
                return Cache::store('redis')->add($key, $value, $ttl);
            } catch (\Throwable) {}
        }
        return Cache::add($key, $value, $ttl);
    }

    protected function cacheIncrement(string $key, int $value = 1): int
    {
        if ($this->shouldUseRedis()) {
            try {
                return (int) Cache::store('redis')->increment($key, $value);
            } catch (\Throwable) {}
        }
        return (int) Cache::increment($key, $value);
    }

    protected function cacheDecrement(string $key, int $value = 1): int
    {
        if ($this->shouldUseRedis()) {
            try {
                return (int) Cache::store('redis')->decrement($key, $value);
            } catch (\Throwable) {}
        }
        return (int) Cache::decrement($key, $value);
    }

    protected function cachePut(string $key, mixed $value, mixed $ttl = null): bool
    {
        if ($this->shouldUseRedis()) {
            try {
                return Cache::store('redis')->put($key, $value, $ttl);
            } catch (\Throwable) {}
        }
        return Cache::put($key, $value, $ttl);
    }

    /**
     * Atomically acquire daily quota slot. Returns [bool $allowed, int $currentUsage].
     * Never increments counter past $dailyLimit.
     *
     * @return array{0: bool, 1: int}
     */
    protected function atomicAcquireDailyQuota(string $trafficKey, int $dailyLimit): array
    {
        if ($dailyLimit <= 0) {
            return [true, 0];
        }

        if ($this->shouldUseRedis()) {
            try {
                $script = <<<'LUA'
local key = KEYS[1]
local limit = tonumber(ARGV[1])
local current = tonumber(redis.call('get', key) or '0')
if current >= limit then
    return {0, current}
else
    local next_val = redis.call('incr', key)
    return {1, next_val}
end
LUA;
                $res = Redis::connection()->eval($script, 1, $trafficKey, $dailyLimit);
                return [(bool) ($res[0] ?? 0), (int) ($res[1] ?? 0)];
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        // Cache fallback (for tests and non-Redis environments)
        $current = (int) Cache::get($trafficKey, 0);
        if ($current >= $dailyLimit) {
            return [false, $current];
        }
        $newVal = (int) Cache::increment($trafficKey);
        if ($newVal > $dailyLimit) {
            Cache::decrement($trafficKey);
            return [false, $dailyLimit];
        }
        return [true, $newVal];
    }

    /**
     * Atomically rollback daily quota if downstream execution was rejected or failed.
     * Guarantees counter never falls below 0.
     */
    protected function atomicRollbackDailyQuota(string $trafficKey): void
    {
        if ($this->shouldUseRedis()) {
            try {
                $script = <<<'LUA'
local key = KEYS[1]
local current = tonumber(redis.call('get', key) or '0')
if current > 0 then
    local next_val = redis.call('decr', key)
    if next_val < 0 then
        redis.call('set', key, 0)
        return 0
    end
    return next_val
else
    redis.call('set', key, 0)
    return 0
end
LUA;
                Redis::connection()->eval($script, 1, $trafficKey);
                return;
            } catch (\Throwable) {}
        }

        try {
            $cur = (int) Cache::get($trafficKey, 0);
            if ($cur > 0) {
                $rem = (int) Cache::decrement($trafficKey);
                if ($rem < 0) {
                    Cache::put($trafficKey, 0, now('UTC')->endOfDay());
                }
            }
        } catch (\Throwable) {}
    }

    /**
     * Atomically acquire in-flight concurrency slot with optional lease ownership.
     *
     * @return array{0: bool, 1: int}
     */
    protected function atomicAcquireConcurrencySlot(string $key, int $limit, int $ttl = 60, ?string $leaseId = null): array
    {
        if ($limit <= 0) {
            return [true, 0];
        }

        $leaseKey = $leaseId !== null ? "{$key}:lease:{$leaseId}" : null;

        if ($this->shouldUseRedis()) {
            try {
                if ($leaseKey !== null) {
                    $script = <<<'LUA'
local counter_key = KEYS[1]
local lease_key = KEYS[2]
local limit = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2])

local current = tonumber(redis.call('get', counter_key) or '0')
if current >= limit then
    return {0, current}
else
    local next_val = redis.call('incr', counter_key)
    redis.call('expire', counter_key, ttl)
    redis.call('set', lease_key, '1', 'EX', ttl)
    return {1, next_val}
end
LUA;
                    $res = Redis::connection()->eval($script, 2, $key, $leaseKey, $limit, $ttl);
                    return [(bool) ($res[0] ?? 0), (int) ($res[1] ?? 0)];
                }

                $script = <<<'LUA'
local key = KEYS[1]
local limit = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2])
local current = tonumber(redis.call('get', key) or '0')
if current >= limit then
    return {0, current}
else
    local next_val = redis.call('incr', key)
    redis.call('expire', key, ttl)
    return {1, next_val}
end
LUA;
                $res = Redis::connection()->eval($script, 1, $key, $limit, $ttl);
                return [(bool) ($res[0] ?? 0), (int) ($res[1] ?? 0)];
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        // Cache fallback (for tests and non-Redis environments)
        Cache::add($key, 0, $ttl);
        $newVal = (int) Cache::increment($key);
        if ($newVal > $limit) {
            Cache::decrement($key);
            return [false, $limit];
        }
        if ($leaseKey !== null) {
            Cache::put($leaseKey, '1', $ttl);
        }
        return [true, $newVal];
    }

    /**
     * Atomically release in-flight concurrency slot with lease ownership protection.
     * Guarantees counter never falls below 0, stale expired leases cannot steal slots,
     * and duplicate releases are completely idempotent.
     */
    protected function atomicReleaseConcurrencySlot(string $key, ?string $leaseId = null): void
    {
        $leaseKey = $leaseId !== null ? "{$key}:lease:{$leaseId}" : null;

        if ($this->shouldUseRedis()) {
            try {
                if ($leaseKey !== null) {
                    $script = <<<'LUA'
local counter_key = KEYS[1]
local lease_key = KEYS[2]

local lease_existed = redis.call('del', lease_key)
if lease_existed == 1 then
    local current = tonumber(redis.call('get', counter_key) or '0')
    if current > 0 then
        local next_val = redis.call('decr', counter_key)
        if next_val < 0 then
            redis.call('set', counter_key, 0)
            return 0
        end
        return next_val
    else
        redis.call('set', counter_key, 0)
        return 0
    end
else
    local current = tonumber(redis.call('get', counter_key) or '0')
    return current
end
LUA;
                    Redis::connection()->eval($script, 2, $key, $leaseKey);
                    return;
                }

                $script = <<<'LUA'
local key = KEYS[1]
local current = tonumber(redis.call('get', key) or '0')
if current > 0 then
    local next_val = redis.call('decr', key)
    if next_val < 0 then
        redis.call('set', key, 0)
        return 0
    end
    return next_val
else
    redis.call('set', key, 0)
    return 0
end
LUA;
                Redis::connection()->eval($script, 1, $key);
                return;
            } catch (\Throwable) {}
        }

        try {
            if ($leaseKey !== null) {
                if (! Cache::has($leaseKey)) {
                    return; // Stale or duplicate release, ignore
                }
                Cache::forget($leaseKey);
            }
            $cur = (int) Cache::get($key, 0);
            if ($cur > 0) {
                $rem = (int) Cache::decrement($key);
                if ($rem < 0) {
                    Cache::put($key, 0, 60);
                }
            }
        } catch (\Throwable) {}
    }

    /**
     * Synthesize immediate safe handoff response when rollout is inactive or kill switch is engaged.
     *
     * @return array<string, mixed>
     */
    protected function buildHandoffResponse(int $workspaceId, string $reason, float $tStart, ?Workspace $workspace = null): array
    {
        $elapsed = round((microtime(true) - $tStart) * 1000, 2);
        $fallbackMessage = $workspace?->getFallbackMessage()
            ?? 'আমাদের AI সহকারী সাময়িকভাবে স্থগিত আছে। আমাদের সাপোর্ট টিম দ্রুত আপনার সাথে যোগাযোগ করবে।';

        return [
            'reply'                  => $fallbackMessage,
            'route'                  => 'human_handoff',
            'confidence'             => 1.0,
            'answered'               => false,
            'is_owner_pipeline'      => false,
            'suggestions'            => [],
            'sources'                => [],
            'is_handoff'             => true,
            'memory_context'         => null,
            'business_context'       => null,
            'retrieval_hits'         => new Collection(),
            'top_hit'                => null,
            'answerability_decision' => [
                'status'           => 'HANDOFF_KILL_SWITCH',
                'confidence_score' => 0.0,
                'grounded_count'   => 0,
                'reasons'          => [$reason],
            ],
            'follow_up_decision'     => null,
            'raw_llm_response'       => [
                'provider'                 => config('ai.default', 'deepseek'),
                'model'                    => config('ai.default_model', 'deepseek-chat'),
                'prompt_tokens'            => 0,
                'completion_tokens'        => 0,
                'total_tokens'             => 0,
                'router_tokens'            => [],
                'agent_tokens'             => [],
                'grounded_documents_count' => 0,
                'grounded_faq_questions'   => [],
            ],
            'routing_telemetry'      => [
                'route'             => 'human_handoff',
                'confidence'        => 1.0,
                'intent'            => 'human_handoff',
                'router_latency_ms' => 0.0,
                'total_e2e_ms'      => $elapsed,
                'signals'           => [
                    'contextual_signal' => 'HANDOFF_KILL_SWITCH',
                ],
            ],
            'lexicon_telemetry'      => [
                'canonical_concepts'  => [],
                'expansion_triggered' => false,
                'expanded_query'      => null,
                'reranker_applied'    => false,
                'reranker_reason'     => null,
                'first_pass_score'    => null,
                'tier_executed'       => null,
            ],
            'latency_breakdown'      => [
                'router_ms'              => 0.0,
                'context_resolution_ms'  => 0.0,
                'clarification_ms'       => 0.0,
                'memory_gate_ms'         => 0.0,
                'memory_retrieval_ms'    => 0.0,
                'business_context_ms'    => 0.0,
                'knowledge_retrieval_ms' => 0.0,
                'retrieval_ms'           => 0.0,
                'answerability_ms'       => 0.0,
                'llm_generation_ms'      => 0.0,
                'llm_ms'                 => 0.0,
                'total_ms'               => $elapsed,
                'total_e2e_ms'           => $elapsed,
            ],
            'decision_metadata'      => [
                'engine_version'     => self::ENGINE_VERSION,
                'kill_switch_active' => $workspace?->isAiKillSwitchActive() ?? ($reason === 'ai_engine_globally_disabled'),
                'rollout_enabled'    => false,
                'halt_reason'        => $reason,
                'total_orchestrator_ms' => $elapsed,
            ],
        ];
    }
}
