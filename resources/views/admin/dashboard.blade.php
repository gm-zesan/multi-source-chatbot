@extends('admin.app')

@section('title')
    Dashboard
@endsection

@section('content')
<div class="space-y-4">
    {{-- Hero Welcome Banner --}}
    <div class="app-card p-3 sm:p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="app-badge app-badge-primary">
                    <i class="ri-radar-line"></i> Operational
                </span>
                <span class="text-2xs text-light-muted dark:text-dark-muted">Multi-Tenant AI Business Platform</span>
            </div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text">
                Welcome back, {{ Auth::user()->name }}! 👋
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                Real-time omnichannel customer support queue, automated FAQ vector engine, and conversational memory diagnostics.
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('analytics.index') }}" class="app-btn app-btn-secondary">
                <i class="ri-bar-chart-2-line text-xs"></i>
                <span>BI Analytics</span>
            </a>
            <a href="{{ route('widget.manage') }}" class="app-btn app-btn-secondary">
                <i class="ri-code-s-slash-line text-xs"></i>
                <span>Web Widget</span>
            </a>
            <a href="{{ route('simulator.index') }}" class="app-btn app-btn-primary">
                <i class="ri-robot-2-line text-xs"></i>
                <span>Simulator</span>
            </a>
        </div>
    </div>

    {{-- Top 4 KPI Metrics --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {{-- Total Conversations --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Total Conversations</div>
                    <div class="text-xl font-bold tracking-tight text-light-text dark:text-dark-text mt-0.5">
                        {{ number_format($metrics['conversation']['total_conversations']) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-brand-500/15 text-brand-500 flex items-center justify-center text-sm">
                    <i class="ri-message-3-line"></i>
                </div>
            </div>
            <div class="flex items-center gap-1.5 pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs">
                <span class="app-badge app-badge-success">
                    <i class="ri-checkbox-circle-line"></i> {{ $metrics['conversation']['open_conversations'] }} Open
                </span>
                <span class="text-light-muted dark:text-dark-muted">Active support threads</span>
            </div>
        </div>

        {{-- All Messages --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">All Messages</div>
                    <div class="text-xl font-bold tracking-tight text-light-text dark:text-dark-text mt-0.5">
                        {{ number_format($metrics['conversation']['total_messages']) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-sm">
                    <i class="ri-chat-check-line"></i>
                </div>
            </div>
            <div class="flex items-center gap-1.5 pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs">
                <span class="app-badge app-badge-primary">
                    +{{ $metrics['conversation']['today_messages'] }} today
                </span>
                <span class="text-light-muted dark:text-dark-muted">Inbound & outbound</span>
            </div>
        </div>

        {{-- Knowledge Base --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Knowledge Articles</div>
                    <div class="text-xl font-bold tracking-tight text-light-text dark:text-dark-text mt-0.5">
                        {{ number_format($metrics['faq']['total_faqs']) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-sky-500/15 text-sky-500 flex items-center justify-center text-sm">
                    <i class="ri-book-open-line"></i>
                </div>
            </div>
            <div class="flex items-center gap-1.5 pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs">
                <span class="app-badge app-badge-secondary">
                    {{ $metrics['faq']['active_faqs'] }} Active FAQs
                </span>
                <span class="text-light-muted dark:text-dark-muted">{{ number_format($metrics['faq']['total_hits']) }} views</span>
            </div>
        </div>

        {{-- Pending Inquiries --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Pending Inquiries</div>
                    <div class="text-xl font-bold tracking-tight text-amber-500 mt-0.5">
                        {{ number_format($metrics['unanswered']['pending']) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-amber-500/15 text-amber-500 flex items-center justify-center text-sm">
                    <i class="ri-question-mark"></i>
                </div>
            </div>
            <div class="flex items-center gap-1.5 pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs">
                <span class="app-badge app-badge-warning">
                    Review Required
                </span>
                <span class="text-light-muted dark:text-dark-muted">{{ $metrics['unanswered']['total'] }} logged</span>
            </div>
        </div>
    </div>

    {{-- Background Queues & Processing Card --}}
    <div class="app-card">
        <div class="app-card-header">
            <div class="flex items-center gap-2">
                <i class="ri-hard-drive-3-line text-brand-500 text-sm"></i>
                <div>
                    <div class="text-xs font-semibold text-light-text dark:text-dark-text">Background Queues & Real-Time Processing</div>
                    <div class="text-2xs text-light-muted dark:text-dark-muted">High-concurrency workers handling webhooks, CRM extraction, and vector index sync</div>
                </div>
            </div>
            <a href="{{ route('dashboard') }}" class="app-btn app-btn-secondary text-2xs">
                <i class="ri-refresh-line"></i> Refresh Queues
            </a>
        </div>
        <div class="app-card-body space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                {{-- Messenger Queue --}}
                <div class="p-3 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-light-text dark:text-dark-text flex items-center gap-1.5">
                            <i class="ri-messenger-line text-brand-500"></i> Omnichannel Hook
                        </span>
                        <span class="app-badge app-badge-primary">Webhook</span>
                    </div>
                    <div class="flex items-end justify-between pt-1">
                        <div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">Pending Jobs</div>
                            <div class="text-lg font-bold text-light-text dark:text-dark-text">{{ $metrics['queue']['pending']['messenger'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xs text-red-500">Failed Jobs</div>
                            <div class="text-xs font-semibold text-red-500">{{ $metrics['queue']['failed']['messenger'] }}</div>
                        </div>
                    </div>
                </div>

                {{-- CRM Queue --}}
                <div class="p-3 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-light-text dark:text-dark-text flex items-center gap-1.5">
                            <i class="ri-user-search-line text-emerald-500"></i> CRM Extractor
                        </span>
                        <span class="app-badge app-badge-success">Contact Sync</span>
                    </div>
                    <div class="flex items-end justify-between pt-1">
                        <div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">Pending Jobs</div>
                            <div class="text-lg font-bold text-light-text dark:text-dark-text">{{ $metrics['queue']['pending']['crm'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xs text-red-500">Failed Jobs</div>
                            <div class="text-xs font-semibold text-red-500">{{ $metrics['queue']['failed']['crm'] }}</div>
                        </div>
                    </div>
                </div>

                {{-- FAQ Indexer --}}
                <div class="p-3 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas space-y-2">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-light-text dark:text-dark-text flex items-center gap-1.5">
                            <i class="ri-brain-line text-sky-500"></i> Vector Indexer
                        </span>
                        <span class="app-badge app-badge-secondary">Typesense Sync</span>
                    </div>
                    <div class="flex items-end justify-between pt-1">
                        <div>
                            <div class="text-2xs text-light-muted dark:text-dark-muted">Pending Jobs</div>
                            <div class="text-lg font-bold text-light-text dark:text-dark-text">{{ $metrics['queue']['pending']['faq'] }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-2xs text-red-500">Failed Jobs</div>
                            <div class="text-xs font-semibold text-red-500">{{ $metrics['queue']['failed']['faq'] }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <div class="flex items-center gap-1.5">
                    <i class="ri-checkbox-circle-fill text-emerald-500"></i>
                    <span>Overall: <strong class="text-light-text dark:text-dark-text">{{ $metrics['queue']['pending']['total'] }} pending</strong></span>
                    <span>&middot;</span>
                    <span class="{{ $metrics['queue']['failed']['total'] > 0 ? 'text-red-500 font-semibold' : '' }}">{{ $metrics['queue']['failed']['total'] }} failed</span>
                </div>
                <code class="px-1.5 py-0.5 rounded bg-light-elevated dark:bg-dark-elevated text-brand-500 font-mono">queues: faq,default,documents,memory,telemetry</code>
            </div>
        </div>
    </div>

    {{-- Bottom Two Columns: Knowledge Insights & Unanswered Funnel --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        {{-- Knowledge Base & Insights --}}
        <div class="app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                    <i class="ri-book-read-line text-brand-500"></i>
                    <span>Knowledge Base & Insights</span>
                </div>
                <a href="{{ route('faqs.index') }}" class="text-brand-500 hover:text-brand-400 text-xs font-medium">Manage FAQs &rarr;</a>
            </div>
            <div class="app-card-body space-y-3">
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="p-3 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                        <div class="text-lg font-bold text-brand-500">{{ $metrics['faq']['total_faqs'] }}</div>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Total Knowledge Items</div>
                    </div>
                    <div class="p-3 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                        <div class="text-lg font-bold text-sky-500">{{ number_format($metrics['faq']['total_hits']) }}</div>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Customer Answer Views</div>
                    </div>
                </div>

                @if ($metrics['faq']['top_faq'])
                    <div class="p-2.5 rounded border border-brand-500/20 bg-brand-500/10 space-y-1">
                        <div class="flex items-center justify-between text-2xs font-semibold text-brand-500">
                            <span><i class="ri-fire-fill text-amber-500 me-1"></i> Most Frequently Asked</span>
                            <span class="px-1.5 py-0.5 rounded-full bg-brand-500 text-white font-mono">{{ number_format($metrics['faq']['top_faq']->hit_count) }} hits</span>
                        </div>
                        <div class="text-xs font-medium text-light-text dark:text-dark-text truncate">
                            "{{ Str::limit($metrics['faq']['top_faq']->question, 80) }}"
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Unanswered Questions Funnel --}}
        <div class="app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                    <i class="ri-question-answer-line text-amber-500"></i>
                    <span>Unanswered Questions Funnel</span>
                </div>
                <a href="{{ route('faqs.index') }}" class="text-brand-500 hover:text-brand-400 text-xs font-medium">Review &rarr;</a>
            </div>
            <div class="app-card-body space-y-3">
                <div class="grid grid-cols-4 gap-2 text-center">
                    <div class="p-2 rounded border border-amber-500/30 bg-amber-500/10">
                        <div class="text-base font-bold text-amber-500">{{ $metrics['unanswered']['pending'] }}</div>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Pending</div>
                    </div>
                    <div class="p-2 rounded border border-brand-500/30 bg-brand-500/10">
                        <div class="text-base font-bold text-brand-500">{{ $metrics['unanswered']['reviewed'] }}</div>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Reviewed</div>
                    </div>
                    <div class="p-2 rounded border border-emerald-500/30 bg-emerald-500/10">
                        <div class="text-base font-bold text-emerald-500">{{ $metrics['unanswered']['answered'] }}</div>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Resolved</div>
                    </div>
                    <div class="p-2 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas">
                        <div class="text-base font-bold text-light-muted dark:text-dark-muted">{{ $metrics['unanswered']['dismissed'] }}</div>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Dismissed</div>
                    </div>
                </div>

                <p class="text-2xs text-light-muted dark:text-dark-muted">
                    Questions logged when customer inquiries fall outside existing knowledge articles. Reviewing these expands bot coverage.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
