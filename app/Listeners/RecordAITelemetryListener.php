<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AITelemetryRecorded;
use App\Models\FAQ;
use App\Models\KnowledgeSearchLog;
use App\Models\UnansweredQuestion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordAITelemetryListener implements ShouldQueue
{
    /**
     * The name of the queue on which the event should be processed.
     *
     * @var string|null
     */
    public $queue = 'telemetry';

    /**
     * Handle the telemetry event as a decoupled observer.
     * Invariant: Telemetry failure must never affect or break conversational responses.
     */
    public function handle(AITelemetryRecorded $event): void
    {
        try {
            $telemetry = $event->telemetry;
            $workspaceId = $event->workspaceId;
            $route = strtolower((string) ($telemetry['route'] ?? 'unknown'));
            $gateStatus = strtolower((string) ($telemetry['answerability_decision']['status'] ?? 'bypassed'));
            $latencyMs = (float) ($telemetry['total_time_ms'] ?? 0.0);

            // 1. Live Structured Logging
            Log::info('[AITelemetry] Observability event received', [
                'workspace_id'      => $workspaceId,
                'conversation_id'   => $event->conversation?->id,
                'message_id'        => $event->outboundMessage?->id,
                'query_len'         => mb_strlen($event->query),
                'route'             => $route,
                'gate'              => $gateStatus,
                'latency_ms'        => $latencyMs,
                'prompt_tokens'     => $telemetry['llm_usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $telemetry['llm_usage']['completion_tokens'] ?? 0,
                'total_tokens'      => $telemetry['llm_usage']['total_tokens'] ?? 0,
                'provider'          => $telemetry['provider'] ?? config('ai.default', 'deepseek'),
                'model'             => $telemetry['model'] ?? config('ai.default_model', 'deepseek-chat'),
            ]);

            if ($workspaceId <= 0) {
                return;
            }

            // 2. Knowledge Search Log Persistence
            $this->persistKnowledgeSearchLog($event, $telemetry, $route, $latencyMs);

            // 3. Unanswered Question Tracking
            $this->trackUnansweredQuestion($event, $telemetry, $route, $gateStatus);
        } catch (\Throwable $e) {
            // Observer pattern: absorb any logging/storage failure safely
            Log::warning('[RecordAITelemetryListener] Telemetry recording failed silently', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record audit log for knowledge base search query.
     *
     * @param array<string, mixed> $telemetry
     */
    private function persistKnowledgeSearchLog(
        AITelemetryRecorded $event,
        array $telemetry,
        string $route,
        float $latencyMs
    ): void {
        try {
            $candidateFaqId = $telemetry['matched_faq_id']
                ?? $telemetry['answerability_decision']['top_hit']['faq_id']
                ?? null;

            $matchedFaqId = null;
            if ($candidateFaqId !== null && is_string($candidateFaqId) && Str::isUuid($candidateFaqId)) {
                if (FAQ::where('id', $candidateFaqId)->exists()) {
                    $matchedFaqId = $candidateFaqId;
                }
            }

            $topHit = $telemetry['answerability_decision']['top_hit'] ?? [];
            $keywordScore = (float) ($telemetry['keyword_score'] ?? $topHit['keyword_score'] ?? 0.0);
            $semanticScore = (float) ($telemetry['semantic_score'] ?? $topHit['semantic_score'] ?? 0.0);
            $finalScore = (float) ($telemetry['confidence'] ?? $topHit['score'] ?? $telemetry['final_score'] ?? 0.0);

            $answerSource = (string) ($telemetry['answer_source'] ?? ($route === 'knowledge' ? 'knowledge_base' : $route));

            KnowledgeSearchLog::create([
                'workspace_id'     => $event->workspaceId,
                'conversation_id'  => $event->conversation?->id,
                'message_id'       => $event->outboundMessage?->id,
                'customer_query'   => mb_substr($event->query, 0, 1000),
                'matched_faq_id'   => $matchedFaqId,
                'keyword_score'    => min(1.0, max(0.0, round($keywordScore, 4))),
                'semantic_score'   => min(1.0, max(0.0, round($semanticScore, 4))),
                'final_score'      => min(1.0, max(0.0, round($finalScore, 4))),
                'response_time_ms' => (int) round($latencyMs),
                'answer_source'    => mb_substr($answerSource, 0, 50),
                'created_at'       => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[RecordAITelemetryListener] Failed to persist KnowledgeSearchLog', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detect and record unanswered/uncertain customer questions for dashboard triage.
     *
     * @param array<string, mixed> $telemetry
     */
    private function trackUnansweredQuestion(
        AITelemetryRecorded $event,
        array $telemetry,
        string $route,
        string $gateStatus
    ): void {
        try {
            $isUnanswered = false;
            $answered = $telemetry['answered'] ?? null;

            if ($answered === false) {
                $isUnanswered = true;
            } elseif (in_array($route, ['unanswerable', 'uncertain', 'ood'], true)) {
                $isUnanswered = true;
            } elseif (in_array($gateStatus, ['unanswerable', 'ambiguous'], true)) {
                $isUnanswered = true;
            }

            if (! $isUnanswered) {
                return;
            }

            $cleanQuery = trim($event->query);
            $cleanLower = mb_strtolower($cleanQuery);
            $commonGreetings = ['hi', 'hello', 'hey', 'হাই', 'হ্যালো', 'সালাম', 'আসসালামু আলাইকুম', 'assalamu alaikum'];
            if (in_array($cleanLower, $commonGreetings, true) || mb_strlen($cleanQuery) < 3) {
                return;
            }

            $normalized = mb_substr(mb_strtolower((string) preg_replace('/\s+/u', ' ', $cleanQuery)), 0, 500);

            $existing = UnansweredQuestion::where('workspace_id', $event->workspaceId)
                ->where('status', 'pending')
                ->where('normalized_question', $normalized)
                ->first();

            if ($existing) {
                $existing->increment('occurrence_count');
                if ($event->conversation) {
                    $existing->update([
                        'conversation_id' => $event->conversation->id,
                        'updated_at'      => now(),
                    ]);
                }
            } else {
                UnansweredQuestion::create([
                    'workspace_id'        => $event->workspaceId,
                    'conversation_id'     => $event->conversation?->id,
                    'original_question'   => mb_substr($event->query, 0, 1000),
                    'normalized_question' => $normalized,
                    'occurrence_count'    => 1,
                    'status'              => 'pending',
                    'notes'               => "Route: {$route} | Gate: " . ($gateStatus !== '' ? $gateStatus : 'none'),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[RecordAITelemetryListener] Failed to track UnansweredQuestion', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
