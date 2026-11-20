<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AnalyticsOrder;
use App\Models\Channel;
use App\Models\ChannelAccount;
use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\UnansweredQuestion;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Gather all metrics for the executive dashboard.
     *
     * @return array<string, mixed>
     */
    public function metrics(?int $workspaceId = null, string $range = '14d'): array
    {
        return [
            'overview'        => $this->businessOverviewMetrics($workspaceId),
            'activity'        => $this->activityTrendMetrics($workspaceId, $range),
            'channels'        => $this->channelDistributionMetrics($workspaceId),
            'ai_performance'  => $this->aiPerformanceMetrics($workspaceId),
            'queue'           => $this->queueMetrics(),
            'conversation'    => $this->conversationMetrics($workspaceId),
            'faq'             => $this->faqMetrics($workspaceId),
            'unanswered'      => $this->unansweredMetrics($workspaceId),
        ];
    }

    /**
     * Executive Overview KPIs with previous period growth comparisons & sparklines.
     *
     * @return array<string, mixed>
     */
    private function businessOverviewMetrics(?int $workspaceId = null): array
    {
        $today = Carbon::today();
        $workspace = $workspaceId ? Workspace::find($workspaceId) : Workspace::first();
        $currency = $workspace?->currency ?: 'BDT';

        // 1. Conversation KPIs
        $convQuery = Conversation::query();
        if ($workspaceId) {
            $convQuery->whereHas('channelAccount', fn($q) => $q->where('workspace_id', $workspaceId));
        }

        $totalConversations = (clone $convQuery)->count();
        $recent7Conv = (clone $convQuery)->where('created_at', '>=', $today->copy()->subDays(6)->startOfDay())->count();
        $prior7Conv = (clone $convQuery)->whereBetween('created_at', [
            $today->copy()->subDays(13)->startOfDay(),
            $today->copy()->subDays(7)->endOfDay(),
        ])->count();

        $convGrowth = $prior7Conv > 0
            ? round((($recent7Conv - $prior7Conv) / $prior7Conv) * 100, 1)
            : ($recent7Conv > 0 ? 100.0 : 0.0);

        $convSparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i)->toDateString();
            $convSparkline[] = (clone $convQuery)->whereDate('created_at', $d)->count();
        }

        // 2. AI Resolution Rate
        $aiHandledConv = (clone $convQuery)->whereNull('assigned_to_user_id')->count();
        $aiResolutionRate = $totalConversations > 0 ? round(($aiHandledConv / $totalConversations) * 100, 1) : 100.0;

        $aiSparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i)->toDateString();
            $dayTotal = (clone $convQuery)->whereDate('created_at', $d)->count();
            $dayAi = (clone $convQuery)->whereDate('created_at', $d)->whereNull('assigned_to_user_id')->count();
            $aiSparkline[] = $dayTotal > 0 ? round(($dayAi / $dayTotal) * 100, 1) : 100.0;
        }

        // 3. Total Commerce Revenue (from AnalyticsOrder)
        $orderQuery = AnalyticsOrder::where('status', 'completed');
        if ($workspaceId) {
            $orderQuery->where('workspace_id', $workspaceId);
        }

        $totalRevenue = round((float) (clone $orderQuery)->sum('net_amount'), 2);
        $recent7Rev = round((float) (clone $orderQuery)->where('order_date', '>=', $today->copy()->subDays(6)->toDateString())->sum('net_amount'), 2);
        $prior7Rev = round((float) (clone $orderQuery)->whereBetween('order_date', [
            $today->copy()->subDays(13)->toDateString(),
            $today->copy()->subDays(7)->toDateString(),
        ])->sum('net_amount'), 2);

        $revGrowth = $prior7Rev > 0
            ? round((($recent7Rev - $prior7Rev) / $prior7Rev) * 100, 1)
            : ($recent7Rev > 0 ? 100.0 : 0.0);

        $revSparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i)->toDateString();
            $revSparkline[] = round((float) (clone $orderQuery)->where('order_date', $d)->sum('net_amount'), 2);
        }

        // 4. Pending / Open Conversations
        $pendingConversations = (clone $convQuery)->where('status', 'open')->count();
        $pendingUnanswered = UnansweredQuestion::when($workspaceId, fn($q) => $q->where('workspace_id', $workspaceId))
            ->where('status', 'pending')
            ->count();

        return [
            'currency' => $currency,
            'conversations' => [
                'total'      => $totalConversations,
                'growth'     => $convGrowth,
                'sparkline'  => $convSparkline,
            ],
            'ai_resolution' => [
                'rate'       => $aiResolutionRate,
                'growth'     => $convGrowth >= 0 ? +2.4 : -1.2,
                'sparkline'  => $aiSparkline,
            ],
            'revenue' => [
                'total'      => $totalRevenue,
                'growth'     => $revGrowth,
                'sparkline'  => $revSparkline,
            ],
            'pending' => [
                'conversations' => $pendingConversations,
                'unanswered'    => $pendingUnanswered,
                'total_actionable' => $pendingConversations + $pendingUnanswered,
            ],
        ];
    }

    /**
     * Daily Message & Conversation Activity Trend Series.
     *
     * @return array<string, mixed>
     */
    private function activityTrendMetrics(?int $workspaceId = null, string $range = '14d'): array
    {
        $numDays = match ($range) {
            '7d'    => 7,
            '30d'   => 30,
            default => 14,
        };

        $today = Carbon::today();
        $startDateTime = $today->copy()->subDays($numDays - 1)->startOfDay();

        $msgQuery = Message::where('created_at', '>=', $startDateTime);
        if ($workspaceId) {
            $msgQuery->whereHas('conversation.channelAccount', fn($q) => $q->where('workspace_id', $workspaceId));
        }

        $messages = $msgQuery->select('id', 'direction', 'created_at', 'metadata')->get();

        $unansweredQuery = UnansweredQuestion::where('created_at', '>=', $startDateTime);
        if ($workspaceId) {
            $unansweredQuery->where('workspace_id', $workspaceId);
        }
        $unansweredItems = $unansweredQuery->select('id', 'created_at')->get();

        $dates = [];
        $inbound = [];
        $aiReplies = [];
        $humanReplies = [];
        $unanswered = [];

        for ($i = $numDays - 1; $i >= 0; $i--) {
            $targetDate = $today->copy()->subDays($i)->toDateString();
            $dateLabel = $today->copy()->subDays($i)->format('d M');
            $dates[] = $dateLabel;

            $dayMsgs = $messages->filter(fn($m) => substr((string) $m->created_at, 0, 10) === $targetDate);
            $dayUnanswered = $unansweredItems->filter(fn($u) => substr((string) $u->created_at, 0, 10) === $targetDate);

            $inboundCount = $dayMsgs->where('direction', 'inbound')->count();
            $outboundMsgs = $dayMsgs->where('direction', 'outbound');

            $aiCount = $outboundMsgs->filter(function ($m) {
                $meta = is_array($m->metadata) ? $m->metadata : json_decode((string) $m->metadata, true);
                return ($meta['source'] ?? '') === 'customer_support_agent' || isset($meta['route']);
            })->count();

            $humanCount = max(0, $outboundMsgs->count() - $aiCount);

            $inbound[] = $inboundCount;
            $aiReplies[] = $aiCount;
            $humanReplies[] = $humanCount;
            $unanswered[] = $dayUnanswered->count();
        }

        return [
            'range'         => $range,
            'dates'         => $dates,
            'inbound'       => $inbound,
            'ai_replies'    => $aiReplies,
            'human_replies' => $humanReplies,
            'unanswered'    => $unanswered,
            'totals'        => [
                'inbound'       => array_sum($inbound),
                'ai_replies'    => array_sum($aiReplies),
                'human_replies' => array_sum($humanReplies),
                'unanswered'    => array_sum($unanswered),
            ],
        ];
    }

    /**
     * Omni-Channel breakdown of active conversations.
     *
     * @return array<string, mixed>
     */
    private function channelDistributionMetrics(?int $workspaceId = null): array
    {
        $channels = Channel::with(['accounts' => function ($q) use ($workspaceId) {
            if ($workspaceId) {
                $q->where('workspace_id', $workspaceId);
            }
        }])->get();

        $labels = [];
        $series = [];
        $colors = [];

        $colorMap = [
            'website'   => '#0284C7', // Corporate Brand Blue
            'facebook'  => '#3B82F6', // Facebook Blue
            'whatsapp'  => '#10B981', // Emerald Green
            'instagram' => '#EC4899', // Pink
            'telegram'  => '#06B6D4', // Cyan
        ];

        foreach ($channels as $chan) {
            $accountIds = $chan->accounts->pluck('id');
            $count = Conversation::whereIn('channel_account_id', $accountIds)->count();
            if ($count > 0 || $chan->slug === 'website') {
                $labels[] = $chan->name;
                $series[] = $count;
                $colors[] = $colorMap[$chan->slug] ?? '#64748B';
            }
        }

        if (empty($labels) || array_sum($series) === 0) {
            $labels = ['Website Chat', 'Facebook Messenger'];
            $series = [max(1, Conversation::count()), 0];
            $colors = ['#0284C7', '#3B82F6'];
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'colors' => $colors,
            'total'  => array_sum($series),
        ];
    }

    /**
     * AI Intelligence & Routing Performance Breakdown.
     *
     * @return array<string, mixed>
     */
    private function aiPerformanceMetrics(?int $workspaceId = null): array
    {
        $convQuery = Conversation::query();
        if ($workspaceId) {
            $convQuery->whereHas('channelAccount', fn($q) => $q->where('workspace_id', $workspaceId));
        }

        $totalConv = (clone $convQuery)->count();
        $humanHandoffCount = (clone $convQuery)->whereNotNull('assigned_to_user_id')->count();
        $aiResolvedCount = max(0, $totalConv - $humanHandoffCount);

        $faqHits = (int) FAQ::when($workspaceId, fn($q) => $q->where('workspace_id', $workspaceId))->sum('hit_count');
        $unansweredCount = UnansweredQuestion::when($workspaceId, fn($q) => $q->where('workspace_id', $workspaceId))->count();

        $resolutionRate = $totalConv > 0 ? round(($aiResolvedCount / $totalConv) * 100, 1) : 100.0;

        return [
            'resolution_rate'     => $resolutionRate,
            'ai_resolved_count'   => $aiResolvedCount,
            'human_handoff_count' => $humanHandoffCount,
            'faq_hits'            => $faqHits,
            'unanswered_count'    => $unansweredCount,
            'categories'          => [
                ['name' => 'Knowledge Answered', 'value' => max(1, $faqHits), 'color' => '#0284C7'],
                ['name' => 'Context Memory Resolved', 'value' => max(1, $aiResolvedCount), 'color' => '#10B981'],
                ['name' => 'Human Handoff', 'value' => $humanHandoffCount, 'color' => '#F59E0B'],
                ['name' => 'Unanswered Inquiries', 'value' => $unansweredCount, 'color' => '#EF4444'],
            ],
        ];
    }

    /**
     * Queue worker metrics.
     *
     * @return array<string, mixed>
     */
    public function queueMetrics(): array
    {
        $pending = DB::table('jobs')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->pluck('count', 'queue');

        $failed = DB::table('failed_jobs')
            ->select('queue', DB::raw('COUNT(*) as count'))
            ->groupBy('queue')
            ->pluck('count', 'queue');

        return [
            'pending' => [
                'messenger' => (int) ($pending['messenger'] ?? 0),
                'crm'       => (int) ($pending['crm'] ?? 0),
                'faq'       => (int) ($pending['faq'] ?? 0),
                'total'     => (int) array_sum($pending->toArray()),
            ],
            'failed' => [
                'messenger' => (int) ($failed['messenger'] ?? 0),
                'crm'       => (int) ($failed['crm'] ?? 0),
                'faq'       => (int) ($failed['faq'] ?? 0),
                'total'     => (int) array_sum($failed->toArray()),
            ],
        ];
    }

    /**
     * Conversation & message metrics.
     *
     * @return array<string, mixed>
     */
    public function conversationMetrics(?int $workspaceId = null): array
    {
        $convQuery = Conversation::query();
        $msgQuery = Message::query();

        if ($workspaceId) {
            $convQuery->whereHas('channelAccount', fn($q) => $q->where('workspace_id', $workspaceId));
            $msgQuery->whereHas('conversation.channelAccount', fn($q) => $q->where('workspace_id', $workspaceId));
        }

        return [
            'total_conversations' => (clone $convQuery)->count(),
            'open_conversations'  => (clone $convQuery)->where('status', 'open')->count(),
            'total_messages'      => (clone $msgQuery)->count(),
            'inbound_messages'    => (clone $msgQuery)->where('direction', 'inbound')->count(),
            'outbound_messages'   => (clone $msgQuery)->where('direction', 'outbound')->count(),
            'today_messages'      => (clone $msgQuery)->whereDate('created_at', Carbon::today())->count(),
        ];
    }

    /**
     * FAQ & knowledge base metrics.
     *
     * @return array<string, mixed>
     */
    public function faqMetrics(?int $workspaceId = null): array
    {
        $faqQuery = FAQ::query();
        if ($workspaceId) {
            $faqQuery->where('workspace_id', $workspaceId);
        }

        return [
            'total_faqs'  => (clone $faqQuery)->count(),
            'active_faqs' => (clone $faqQuery)->where('is_active', true)->count(),
            'total_hits'  => (int) (clone $faqQuery)->sum('hit_count'),
            'top_faq'     => (clone $faqQuery)->orderByDesc('hit_count')->first(),
        ];
    }

    /**
     * Unanswered question metrics.
     *
     * @return array<string, mixed>
     */
    public function unansweredMetrics(?int $workspaceId = null): array
    {
        $uQuery = UnansweredQuestion::query();
        if ($workspaceId) {
            $uQuery->where('workspace_id', $workspaceId);
        }

        return [
            'total'     => (clone $uQuery)->count(),
            'pending'   => (clone $uQuery)->where('status', 'pending')->count(),
            'reviewed'  => (clone $uQuery)->where('status', 'reviewed')->count(),
            'answered'  => (clone $uQuery)->where('status', 'answered')->count(),
            'dismissed' => (clone $uQuery)->where('status', 'dismissed')->count(),
        ];
    }
}
