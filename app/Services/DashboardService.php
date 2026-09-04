<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;
use App\Models\FAQ;
use App\Models\Message;
use App\Models\UnansweredQuestion;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Gather all metrics for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function metrics(): array
    {
        return [
            'queue'       => $this->queueMetrics(),
            'conversation' => $this->conversationMetrics(),
            'faq'         => $this->faqMetrics(),
            'unanswered'  => $this->unansweredMetrics(),
        ];
    }

    /**
     * Queue worker metrics.
     */
    private function queueMetrics(): array
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
     */
    private function conversationMetrics(): array
    {
        return [
            'total_conversations'  => Conversation::count(),
            'open_conversations'   => Conversation::where('status', 'open')->count(),
            'total_messages'       => Message::count(),
            'inbound_messages'     => Message::where('direction', 'inbound')->count(),
            'outbound_messages'    => Message::where('direction', 'outbound')->count(),
            'today_messages'       => Message::whereDate('created_at', today())->count(),
        ];
    }

    /**
     * FAQ & knowledge base metrics.
     */
    private function faqMetrics(): array
    {
        return [
            'total_faqs'   => FAQ::count(),
            'active_faqs'  => FAQ::where('is_active', true)->count(),
            'total_hits'   => (int) FAQ::sum('hit_count'),
            'top_faq'      => FAQ::orderByDesc('hit_count')->first(),
        ];
    }

    /**
     * Unanswered question metrics.
     */
    private function unansweredMetrics(): array
    {
        return [
            'total'         => UnansweredQuestion::count(),
            'pending'       => UnansweredQuestion::where('status', 'pending')->count(),
            'reviewed'      => UnansweredQuestion::where('status', 'reviewed')->count(),
            'answered'      => UnansweredQuestion::where('status', 'answered')->count(),
            'dismissed'     => UnansweredQuestion::where('status', 'dismissed')->count(),
        ];
    }
}
