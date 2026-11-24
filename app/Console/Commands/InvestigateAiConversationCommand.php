<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\KnowledgeSearchLog;
use App\Models\Message;
use App\Models\OrderFeedback;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InvestigateAiConversationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:investigate
                            {identifier : Conversation ID, Message ID, or KnowledgeSearchLog ID}
                            {--type=auto : Search type: auto, conversation, message, or log}
                            {--json : Output machine-readable structured telemetry JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Investigate AI conversation turns, retrieval evidence, telemetry traces, and root causes for failed customer answers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $identifier = (string) $this->argument('identifier');
        $type = (string) $this->option('type');
        $asJson = (bool) $this->option('json');

        $conversation = null;
        $targetMessage = null;
        $targetLog = null;

        // 1. Resolve Target
        if ($type === 'message' || ($type === 'auto' && is_numeric($identifier) && Message::where('id', $identifier)->exists())) {
            $targetMessage = Message::find($identifier);
            $conversation = $targetMessage?->conversation;
        } elseif ($type === 'log' || ($type === 'auto' && is_numeric($identifier) && KnowledgeSearchLog::where('id', $identifier)->exists() && ! Conversation::where('id', $identifier)->exists())) {
            $targetLog = KnowledgeSearchLog::find($identifier);
            if ($targetLog?->conversation_id) {
                $conversation = Conversation::find($targetLog->conversation_id);
            }
        } else {
            $conversation = Conversation::find($identifier);
            if (! $conversation && Str::isUuid($identifier)) {
                $conversation = Conversation::where('uuid', $identifier)->first();
            }
        }

        if (! $conversation && ! $targetLog && ! $targetMessage) {
            $this->error("No AI conversation, message, or search log found for identifier: {$identifier}");
            return self::FAILURE;
        }

        $report = $this->buildInvestigationReport($conversation, $targetMessage, $targetLog);

        if ($asJson) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->renderConsoleReport($report);
        return self::SUCCESS;
    }

    /**
     * Construct structured investigation telemetry report.
     *
     * @return array<string, mixed>
     */
    protected function buildInvestigationReport(?Conversation $conversation, ?Message $targetMessage, ?KnowledgeSearchLog $targetLog): array
    {
        $workspace = $conversation?->workspace ?? ($targetLog ? Workspace::find($targetLog->workspace_id) : null);

        $report = [
            'investigation_time' => now()->toIso8601String(),
            'engine_version'     => config('ai.engine_version', 'v2.4-RC1'),
            'workspace' => [
                'id'                 => $workspace?->id,
                'name'               => $workspace?->name,
                'kill_switch_active' => $workspace?->isAiKillSwitchActive() ?? false,
                'rollout_enabled'    => $workspace?->isAiRolloutEnabled() ?? false,
                'bot_name'           => $workspace?->getAiBotName(),
                'persona_tone'       => $workspace?->getAiPersonaTone(),
            ],
            'conversation' => $conversation ? [
                'id'            => $conversation->id,
                'channel'       => $conversation->channel_type ?? 'unknown',
                'customer_name' => $conversation->customer_name ?? 'Guest Customer',
                'created_at'    => $conversation->created_at?->toIso8601String(),
                'updated_at'    => $conversation->updated_at?->toIso8601String(),
            ] : null,
            'turns' => [],
            'retrieval_logs' => [],
            'unanswered_questions' => [],
            'customer_feedbacks' => [],
            'diagnostics' => [],
        ];

        // Process Conversation Messages
        if ($conversation) {
            $messages = $conversation->messages()->orderBy('id')->get();
            foreach ($messages as $msg) {
                $report['turns'][] = [
                    'message_id' => $msg->id,
                    'direction'  => $msg->direction,
                    'is_user'    => $msg->is_from_user,
                    'body'       => $msg->body,
                    'status'     => $msg->status,
                    'blocked'    => $msg->isBlocked(),
                    'blocked_reason' => $msg->getBlockedReason(),
                    'metadata'   => $msg->metadata,
                    'timestamp'  => $msg->created_at?->toIso8601String(),
                ];
            }

            // Retrieve Knowledge Search Logs
            $searchLogs = KnowledgeSearchLog::where('conversation_id', $conversation->id)->orderBy('id')->get();
            foreach ($searchLogs as $log) {
                $report['retrieval_logs'][] = [
                    'log_id'           => $log->id,
                    'query'            => $log->customer_query,
                    'matched_faq_id'   => $log->matched_faq_id,
                    'keyword_score'    => (float) $log->keyword_score,
                    'semantic_score'   => (float) $log->semantic_score,
                    'final_score'      => (float) $log->final_score,
                    'response_time_ms' => $log->response_time_ms,
                    'answer_source'    => $log->answer_source,
                    'timestamp'        => $log->created_at?->toIso8601String(),
                ];
            }

            // Unanswered Questions
            $unanswered = UnansweredQuestion::where('conversation_id', $conversation->id)->get();
            foreach ($unanswered as $u) {
                $report['unanswered_questions'][] = [
                    'id'               => $u->id,
                    'question'         => $u->original_question,
                    'occurrence_count' => $u->occurrence_count,
                    'status'           => $u->status,
                    'notes'            => $u->notes,
                ];
            }

            // Order CSAT Feedbacks
            $feedbacks = OrderFeedback::where('conversation_id', $conversation->id)->get();
            foreach ($feedbacks as $fb) {
                $report['customer_feedbacks'][] = [
                    'id'          => $fb->id,
                    'rating'      => $fb->rating,
                    'review_text' => $fb->review_text,
                    'status'      => $fb->status,
                    'received_at' => $fb->feedback_received_at?->toIso8601String(),
                ];
            }
        } elseif ($targetLog) {
            $report['retrieval_logs'][] = [
                'log_id'           => $targetLog->id,
                'query'            => $targetLog->customer_query,
                'matched_faq_id'   => $targetLog->matched_faq_id,
                'keyword_score'    => (float) $targetLog->keyword_score,
                'semantic_score'   => (float) $targetLog->semantic_score,
                'final_score'      => (float) $targetLog->final_score,
                'response_time_ms' => $targetLog->response_time_ms,
                'answer_source'    => $targetLog->answer_source,
                'timestamp'        => $targetLog->created_at?->toIso8601String(),
            ];
        }

        // Diagnostic Synthesis
        $report['diagnostics'] = $this->synthesizeDiagnostics($report);

        return $report;
    }

    /**
     * Synthesize automated diagnostic rating.
     *
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    protected function synthesizeDiagnostics(array $report): array
    {
        $hasKillSwitch = $report['workspace']['kill_switch_active'] ?? false;
        $rolloutActive = $report['workspace']['rollout_enabled'] ?? false;
        $unansweredCount = count($report['unanswered_questions']);
        $retrievalCount = count($report['retrieval_logs']);

        $retrievalStatus = 'NO_RETRIEVAL_CALLED';
        $avgScore = 0.0;
        if ($retrievalCount > 0) {
            $scores = array_column($report['retrieval_logs'], 'final_score');
            $avgScore = round(array_sum($scores) / count($scores), 4);
            $retrievalStatus = $avgScore >= 0.70 ? 'STRONG_GROUNDING' : ($avgScore >= 0.55 ? 'ACCEPTABLE_GROUNDING' : 'WEAK_CONFIDENCE');
        }

        $negativeFeedback = false;
        foreach ($report['turns'] as $turn) {
            if (isset($turn['metadata']['ai_feedback']['rating']) && in_array($turn['metadata']['ai_feedback']['rating'], ['dislike', 'negative', 'wrong', 'thumbs_down'], true)) {
                $negativeFeedback = true;
                break;
            }
        }

        $verdict = 'HEALTHY';
        $recommendedAction = 'No action required.';

        if ($hasKillSwitch) {
            $verdict = 'KILL_SWITCH_ACTIVE';
            $recommendedAction = 'Workspace has disabled AI automation. Answers are routed to human support.';
        } elseif (! $rolloutActive) {
            $verdict = 'ROLLOUT_RESTRICTED';
            $recommendedAction = 'Workspace is not enrolled in current limited canary rollout phase.';
        } elseif ($negativeFeedback) {
            $verdict = 'NEGATIVE_CUSTOMER_FEEDBACK';
            $recommendedAction = 'Review specific AI turn answer with domain expert; update FAQ knowledge base or catalog record.';
        } elseif ($unansweredCount > 0) {
            $verdict = 'UNANSWERED_DEFICIT';
            $recommendedAction = 'Query was safely abstained. Review UnansweredQuestion backlog and ingest corresponding business policy.';
        } elseif ($retrievalCount > 0 && $avgScore < 0.55) {
            $verdict = 'LOW_RETRIEVAL_CONFIDENCE';
            $recommendedAction = 'Add targeted synonyms or expand document chunking for customer terminology.';
        }

        return [
            'verdict'                 => $verdict,
            'retrieval_quality'       => $retrievalStatus,
            'average_retrieval_score' => $avgScore,
            'negative_feedback_found' => $negativeFeedback,
            'unanswered_count'        => $unansweredCount,
            'recommended_action'      => $recommendedAction,
        ];
    }

    /**
     * Render human-readable console report.
     *
     * @param array<string, mixed> $report
     */
    protected function renderConsoleReport(array $report): void
    {
        $this->line('');
        $this->info('================================================================================');
        $this->info("  ENGINE v2.4-RC1 — EVIDENCE TRACE & FAILED-ANSWER INVESTIGATION");
        $this->info('================================================================================');

        $ws = $report['workspace'];
        $this->line("<comment>Workspace:</comment> [ID: {$ws['id']}] {$ws['name']} | Bot: {$ws['bot_name']} ({$ws['persona_tone']})");
        $this->line("<comment>Rollout Status:</comment> " . ($ws['rollout_enabled'] ? '<info>CANARY ACTIVE</info>' : '<fg=yellow>CANARY INACTIVE</fg=yellow>') . " | Kill Switch: " . ($ws['kill_switch_active'] ? '<fg=red>ENGAGED (HALTED)</fg=red>' : '<info>DISENGAGED (NORMAL)</info>'));

        if ($report['conversation']) {
            $conv = $report['conversation'];
            $this->line("<comment>Conversation:</comment> #{$conv['id']} [{$conv['channel']}] Customer: {$conv['customer_name']}");
        }

        $diag = $report['diagnostics'];
        $this->line('');
        $this->line("<comment>Diagnostic Verdict:</comment> <options=bold>{$diag['verdict']}</> (Retrieval: {$diag['retrieval_quality']}, Avg Score: {$diag['average_retrieval_score']})");
        $this->line("<comment>Recommended Action:</comment> {$diag['recommended_action']}");

        // Render Turns
        if (! empty($report['turns'])) {
            $this->line('');
            $this->info('--------------------------------------------------------------------------------');
            $this->info('  DIALOGUE TURNS & TRACES');
            $this->info('--------------------------------------------------------------------------------');

            foreach ($report['turns'] as $turn) {
                $dirTag = $turn['is_user'] ? '<fg=cyan>[CUSTOMER]</fg=cyan>' : '<fg=green>[AI ASSISTANT]</fg=green>';
                $time = substr((string) ($turn['timestamp'] ?? ''), 11, 8);
                $this->line("{$dirTag} <comment>[Msg #{$turn['message_id']} @ {$time}]</comment>");
                $this->line("  " . trim((string) $turn['body']));

                if ($turn['blocked']) {
                    $this->error("  * BLOCKED: " . ($turn['blocked_reason'] ?? 'Zero-charge protection guard active'));
                }

                if (! empty($turn['metadata']['ai_feedback'])) {
                    $fb = $turn['metadata']['ai_feedback'];
                    $ratingColor = ($fb['rating'] ?? '') === 'positive' ? 'info' : 'error';
                    $this->line("  <{$ratingColor}>* FEEDBACK: [{$fb['rating']}] " . ($fb['comment'] ?? '') . "</{$ratingColor}>");
                }
                $this->line('');
            }
        }

        // Render Retrieval Evidence
        if (! empty($report['retrieval_logs'])) {
            $this->info('--------------------------------------------------------------------------------');
            $this->info('  RETRIEVAL EVIDENCE (Typesense / Hybrid / BSOT)');
            $this->info('--------------------------------------------------------------------------------');

            $rows = [];
            foreach ($report['retrieval_logs'] as $log) {
                $rows[] = [
                    $log['log_id'],
                    mb_substr((string) $log['query'], 0, 35) . '...',
                    $log['keyword_score'],
                    $log['semantic_score'],
                    $log['final_score'],
                    "{$log['response_time_ms']} ms",
                    $log['answer_source'],
                ];
            }

            $this->table(
                ['Log ID', 'Query', 'Keyword', 'Semantic', 'Final', 'Latency', 'Source'],
                $rows
            );
        }

        // Render Unanswered Backlog
        if (! empty($report['unanswered_questions'])) {
            $this->info('--------------------------------------------------------------------------------');
            $this->info('  UNANSWERED / ABSTAINED TRIAGE BACKLOG');
            $this->info('--------------------------------------------------------------------------------');

            $uRows = [];
            foreach ($report['unanswered_questions'] as $u) {
                $uRows[] = [$u['id'], $u['question'], $u['occurrence_count'], $u['status'], $u['notes']];
            }
            $this->table(['ID', 'Question', 'Count', 'Status', 'Notes'], $uRows);
        }

        $this->line('');
    }
}
