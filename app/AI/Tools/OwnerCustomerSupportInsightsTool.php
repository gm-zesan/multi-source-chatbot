<?php

declare(strict_types=1);

namespace App\AI\Tools;

use App\AI\DTOs\AgentContext;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\AgentExecutionTimeoutException;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Stringable;

/**
 * OwnerCustomerSupportInsightsTool (owner_support_insights).
 *
 * Governed customer support oversight tool for business owners.
 * Reports on conversation load, ticket resolution backlogs, human agent handoff rates,
 * and support channel distribution without exposing private customer dialogue content.
 */
class OwnerCustomerSupportInsightsTool extends AbstractGovernedTool
{
    public function name(): string
    {
        return 'owner_support_insights';
    }

    public function description(): Stringable|string
    {
        return 'Executive overview of customer support operations: conversation volumes, open vs closed ticket backlog, human handoff rates, and channel distribution without customer conversation content.';
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function getRequiredPermissions(): array
    {
        return [];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'start_date' => $schema->string()
                ->description("Analysis start date in 'YYYY-MM-DD' format. Optional; defaults to 30 days ago."),
            'end_date' => $schema->string()
                ->description("Analysis end date in 'YYYY-MM-DD' format. Optional; defaults to today."),
        ];
    }

    public function execute(array $arguments, AgentContext $context): ToolResult
    {
        // 1. SLA Deadline Guard
        $remaining = $context->getRemainingSeconds();
        if ($remaining < 0.5) {
            throw new AgentExecutionTimeoutException(
                message: "Tool [{$this->name()}] execution aborted: remaining SLA budget ({$remaining}s) is below 0.5s threshold.",
                timeoutSeconds: $context->timeoutSeconds,
                elapsedSeconds: $context->timeoutSeconds - $remaining,
            );
        }

        $workspaceId = $context->getWorkspaceId();

        // 2. Parse Date Filters
        try {
            $parsedStart = !empty($arguments['start_date'])
                ? Carbon::parse((string) $arguments['start_date'])
                : Carbon::today()->subDays(29);

            $parsedEnd = !empty($arguments['end_date'])
                ? Carbon::parse((string) $arguments['end_date'])
                : Carbon::today();
        } catch (\Throwable) {
            return ToolResult::failure("Invalid date format provided. Expected format is 'YYYY-MM-DD'.");
        }

        if ($parsedStart->gt($parsedEnd)) {
            return ToolResult::failure("'start_date' cannot be after 'end_date'.");
        }

        $startDate = $parsedStart->startOfDay()->toDateTimeString();
        $endDate = $parsedEnd->endOfDay()->toDateTimeString();

        // 3. Query Scoped Conversations via Channel Accounts
        $baseQuery = DB::table('conversations')
            ->join('channel_accounts', 'conversations.channel_account_id', '=', 'channel_accounts.id')
            ->where('channel_accounts.workspace_id', $workspaceId)
            ->whereBetween('conversations.created_at', [$startDate, $endDate]);

        $totalConversations = (int) (clone $baseQuery)->count('conversations.id');

        $openConversations = (int) (clone $baseQuery)
            ->where('conversations.status', 'open')
            ->count('conversations.id');

        $closedConversations = (int) (clone $baseQuery)
            ->where('conversations.status', 'closed')
            ->count('conversations.id');

        // Human handoff detection (supports SQLite and MySQL portable string matching)
        $handoffCount = (int) (clone $baseQuery)
            ->where(function ($q) {
                $q->where('conversations.metadata', 'LIKE', '%"handoff_to_human":true%')
                    ->orWhere('conversations.metadata', 'LIKE', '%"handoff_to_human": true%')
                    ->orWhere('conversations.metadata', 'LIKE', '%"handoff_to_human":1%');
            })
            ->count('conversations.id');

        $handoffRate = $totalConversations > 0
            ? round($handoffCount / $totalConversations, 4)
            : 0.0;

        // Channel Distribution
        $channelDistribution = (clone $baseQuery)
            ->select([
                'channel_accounts.name as channel_name',
                DB::raw('COUNT(conversations.id) as conversation_count'),
            ])
            ->groupBy('channel_accounts.name')
            ->orderBy('conversation_count', 'desc')
            ->get()
            ->map(function ($row) use ($totalConversations) {
                $count = (int) $row->conversation_count;
                $share = $totalConversations > 0 ? round($count / $totalConversations, 4) : 0.0;
                return [
                    'channel' => (string) $row->channel_name,
                    'count'   => $count,
                    'share'   => $share,
                ];
            })
            ->all();

        return ToolResult::success([
            'start_date'           => $startDate,
            'end_date'             => $endDate,
            'total_conversations'  => $totalConversations,
            'open_conversations'   => $openConversations,
            'closed_conversations' => $closedConversations,
            'handoff_count'        => $handoffCount,
            'handoff_rate'         => $handoffRate,
            'channel_distribution' => $channelDistribution,
        ], [
            'execution_time_ms' => $context->getElapsedMs(),
            'tool_name'         => $this->name(),
        ]);
    }
}

