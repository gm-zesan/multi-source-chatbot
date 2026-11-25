<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AITelemetryRecorded;
use App\Models\FAQ;
use App\Models\KnowledgeSearchLog;
use App\Models\UnansweredQuestion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordAITelemetryListener
{
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
                'channel'           => $telemetry['channel'] ?? ($event->conversation?->channelAccount?->channel?->slug ?? 'direct_api'),
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
            $rawTopHit = $telemetry['answerability_decision']['top_hit'] ?? null;
            $topHitId = null;
            $topHitKeyword = null;
            $topHitSemantic = null;
            $topHitFinal = null;

            if ($rawTopHit instanceof \App\Services\FAQ\FAQSearchResult) {
                $topHitId = (string) $rawTopHit->faq->id;
                $topHitKeyword = $rawTopHit->keywordScore;
                $topHitSemantic = $rawTopHit->semanticScore;
                $topHitFinal = $rawTopHit->finalScore;
            } elseif (is_array($rawTopHit)) {
                $topHitId = (string) ($rawTopHit['faq_id'] ?? $rawTopHit['id'] ?? null);
                $topHitKeyword = $rawTopHit['keyword_score'] ?? null;
                $topHitSemantic = $rawTopHit['semantic_score'] ?? null;
                $topHitFinal = $rawTopHit['score'] ?? $rawTopHit['final_score'] ?? null;
            }

            $candidateFaqId = $telemetry['matched_faq_id'] ?? $topHitId ?? null;

            $matchedFaqId = null;
            if ($candidateFaqId !== null && is_string($candidateFaqId) && Str::isUuid($candidateFaqId)) {
                if (FAQ::where('id', $candidateFaqId)->exists()) {
                    $matchedFaqId = $candidateFaqId;
                }
            }

            $keywordScore = (float) ($telemetry['keyword_score'] ?? $topHitKeyword ?? 0.0);
            $semanticScore = (float) ($telemetry['semantic_score'] ?? $topHitSemantic ?? 0.0);
            $finalScore = (float) ($telemetry['confidence'] ?? $topHitFinal ?? $telemetry['final_score'] ?? 0.0);

            $answerSource = (string) ($telemetry['answer_source'] ?? ($route === 'knowledge' ? 'knowledge_base' : $route));
            $normalizedQuery = mb_substr(trim($event->query), 0, 1000);

            $turnId = $telemetry['turn_id'] ?? null;
            if ($turnId !== null && is_string($turnId) && $turnId !== '') {
                $dedupKey = "ai_telemetry_turn_claimed_{$turnId}";
                // Atomically claim turn processing to prevent double-logging from multiple dispatchers
                if (! Cache::add($dedupKey, true, now()->addMinutes(5))) {
                    // Turn was already logged; update message_id if outboundMessage is now provided
                    if ($event->outboundMessage) {
                        $logId = Cache::get("ai_telemetry_log_for_turn_{$turnId}");
                        if ($logId) {
                            KnowledgeSearchLog::where('id', $logId)->whereNull('message_id')->update(['message_id' => $event->outboundMessage->id]);
                        }
                    }
                    return;
                }
            } else {
                // Fallback for legacy dispatches lacking turn_id:
                // Only deduplicate within the exact same conversation context to prevent cross-customer suppression
                if ($event->conversation) {
                    $existing = KnowledgeSearchLog::where('workspace_id', $event->workspaceId)
                        ->where('conversation_id', $event->conversation->id)
                        ->where('customer_query', $normalizedQuery)
                        ->where('created_at', '>=', now()->subSeconds(2))
                        ->first();
                    if ($existing) {
                        if ($event->outboundMessage && ! $existing->message_id) {
                            $existing->update(['message_id' => $event->outboundMessage->id]);
                        }
                        return;
                    }
                }
            }

            $log = KnowledgeSearchLog::create([
                'workspace_id'     => $event->workspaceId,
                'conversation_id'  => $event->conversation?->id,
                'message_id'       => $event->outboundMessage?->id,
                'customer_query'   => $normalizedQuery,
                'matched_faq_id'   => $matchedFaqId,
                'keyword_score'    => min(1.0, max(0.0, round($keywordScore, 4))),
                'semantic_score'   => min(1.0, max(0.0, round($semanticScore, 4))),
                'final_score'      => min(1.0, max(0.0, round($finalScore, 4))),
                'response_time_ms' => (int) round($latencyMs),
                'answer_source'    => mb_substr($answerSource, 0, 50),
                'created_at'       => now(),
            ]);

            if ($turnId !== null && is_string($turnId) && $turnId !== '') {
                Cache::put("ai_telemetry_log_for_turn_{$turnId}", $log->id, now()->addMinutes(5));
            }
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
            $finalScore = (float) ($telemetry['final_score'] ?? $telemetry['confidence'] ?? 1.0);

            if ($answered === false) {
                $isUnanswered = true;
            } elseif (in_array($route, ['unanswerable', 'uncertain', 'ood'], true)) {
                $isUnanswered = true;
            } elseif (in_array($gateStatus, ['unanswerable', 'ambiguous'], true)) {
                $isUnanswered = true;
            } else {
                // Domain Abstention Detection:
                // When retrieval confidence is low (< 0.35) and the assistant generates an explicit abstention,
                // the question represents missing knowledge and must be tracked for merchant triage.
                if ($finalScore < 0.35) {
                    $reply = mb_strtolower((string) $event->reply);
                    $abstentionPhrases = [
                        'নিশ্চিত করা যাচ্ছে না',
                        'তথ্য নেই',
                        'স্টকে নেই',
                        'স্টকে আছে কি না',
                        'দুঃখিত',
                        'প্রদত্ত তথ্যে নেই',
                        'আমাদের জানা নেই',
                        'সাপোর্ট টিমের সাথে যোগাযোগ',
                        'পাওয়া যাবে না',
                        'out of stock',
                        'cannot confirm',
                    ];
                    foreach ($abstentionPhrases as $phrase) {
                        if (str_contains($reply, $phrase)) {
                            $isUnanswered = true;
                            break;
                        }
                    }
                }
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
                    'notes'               => "Route: {$route} | Gate: " . ($gateStatus !== '' ? $gateStatus : 'none') . ($finalScore < 0.35 ? " | Score: {$finalScore}" : ''),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('[RecordAITelemetryListener] Failed to track UnansweredQuestion', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
