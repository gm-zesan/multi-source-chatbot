@extends('admin.app')

@section('title')
    Executive Dashboard
@endsection

@section('content')
<div class="space-y-4">
    {{-- Hero Welcome Banner --}}
    <div class="app-card p-3 sm:p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="app-badge app-badge-primary">
                    <i class="ri-radar-line"></i> Executive Overview
                </span>
                <span class="text-2xs text-light-muted dark:text-dark-muted">
                    Workspace: <strong class="text-light-text dark:text-dark-text">{{ $workspace->name ?? 'Default' }}</strong>
                </span>
            </div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text">
                Welcome back, {{ Auth::user()->name }}!
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                Real-time omnichannel customer support volume, AI automation efficiency, and commerce telemetry.
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('billing.index') }}" class="app-btn app-btn-secondary !border-brand-500/30 !bg-brand-500/5 hover:!bg-brand-500/10">
                <i class="ri-wallet-3-line text-xs text-brand-500"></i>
                <span>Wallet: <strong>৳{{ number_format($walletBalance ?? 50, 2) }}</strong></span>
            </a>
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

    {{-- AI Daily Executive Summary & Intelligence Digest --}}
    @include('admin.dashboard.partials.daily-executive-summary')

    {{-- Interactive Workspace Launchpad & Onboarding Checklist --}}
    @if(isset($onboarding))
    <div class="app-card overflow-hidden border border-brand-500/20 shadow-sm" id="onboardingWidget">
        <div class="p-3.5 sm:p-4 bg-gradient-to-r from-brand-500/10 via-transparent to-transparent border-b border-light-border dark:border-dark-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xs sm:text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-1.5">
                        <i class="ri-rocket-2-line text-brand-500"></i>
                        <span>Tenant Launchpad & Onboarding Checklist</span>
                    </h2>
                </div>
                <p class="text-2xs text-light-text-secondary dark:text-dark-text-secondary mt-0.5">
                    Follow these {{ $onboarding['total'] ?? 6 }} recommended setup steps to prime your AI assistant for customer deployment.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-2xs font-semibold text-light-text dark:text-dark-text">
                        {{ $onboarding['completed'] }} of {{ $onboarding['total'] }} Complete
                    </div>
                    <div class="w-32 sm:w-40 h-2 rounded-full bg-light-surface dark:bg-dark-surface border border-light-border dark:border-dark-border overflow-hidden mt-1">
                        <div class="h-full rounded-full transition-all duration-500 {{ $onboarding['all_completed'] ? 'bg-emerald-500' : 'bg-brand-500' }}" style="width: {{ $onboarding['percent'] }}%;"></div>
                    </div>
                </div>
                <button type="button" class="app-btn app-btn-secondary !h-7 !px-2 text-2xs text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" onclick="document.getElementById('onboardingStepsContainer').classList.toggle('hidden'); this.querySelector('i').classList.toggle('rotate-180');" title="Toggle Checklist">
                    <i class="ri-arrow-up-s-line transition-transform duration-200"></i>
                </button>
            </div>
        </div>

        <div id="onboardingStepsContainer" class="p-3 sm:p-4 grid grid-cols-1 md:grid-cols-5 gap-2.5">
            @foreach($onboarding['steps'] as $idx => $step)
                <div class="p-3 rounded-xl border {{ $step['completed'] ? 'border-emerald-500/20 bg-emerald-500/5' : 'border-light-border dark:border-dark-border bg-light-canvas/40 dark:bg-dark-canvas/40 hover:border-brand-500/40' }} flex flex-col justify-between transition-all">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-6 h-6 rounded-lg {{ $step['completed'] ? 'bg-emerald-500 text-white' : 'bg-brand-500/15 text-brand-500 font-bold' }} flex items-center justify-center text-xs">
                                @if($step['completed'])
                                    <i class="ri-check-line"></i>
                                @else
                                    {{ $idx + 1 }}
                                @endif
                            </span>
                            @if($step['completed'])
                                <span class="app-badge app-badge-success !text-3xs uppercase">Done</span>
                            @else
                                <span class="app-badge app-badge-warning !text-3xs uppercase">Pending</span>
                            @endif
                        </div>
                        <h3 class="text-xs font-semibold text-light-text dark:text-dark-text leading-snug">
                            {{ $step['title'] }}
                        </h3>
                        <p class="text-3xs text-light-muted dark:text-dark-muted mt-1 leading-relaxed">
                            {{ $step['description'] }}
                        </p>
                    </div>

                    <div class="mt-3 pt-2 border-t border-light-border/60 dark:border-dark-border/60">
                        <a href="{{ $step['action_url'] }}" class="app-btn {{ $step['completed'] ? 'app-btn-outline' : 'app-btn-primary' }} !h-6.5 !px-2 w-full text-3xs flex items-center justify-center gap-1">
                            <span>{{ $step['action_text'] }}</span>
                            <i class="ri-arrow-right-line"></i>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Top 4 Executive KPI Cards with Sparklines & % Growth --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {{-- Total Conversations --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                        Total Conversations
                    </div>
                    <div class="text-xl font-bold tracking-tight text-light-text dark:text-dark-text mt-0.5 tabular-nums">
                        {{ number_format($metrics['overview']['conversations']['total']) }}
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="app-badge {{ $metrics['overview']['conversations']['growth'] >= 0 ? 'app-badge-success' : 'app-badge-warning' }} !text-2xs">
                        {{ $metrics['overview']['conversations']['growth'] >= 0 ? '+' : '' }}{{ $metrics['overview']['conversations']['growth'] }}%
                    </span>
                    <div class="w-7 h-7 rounded bg-brand-500/15 text-brand-500 flex items-center justify-center text-xs">
                        <i class="ri-message-3-line"></i>
                    </div>
                </div>
            </div>
            <div class="h-8 mt-1" id="sparklineConv"></div>
            <div class="flex items-center justify-between pt-1.5 mt-1 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>{{ $metrics['conversation']['open_conversations'] }} active threads</span>
                <span class="text-brand-500 font-medium">Prior 7d: vs prev</span>
            </div>
        </div>

        {{-- AI Resolution Rate --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                        AI Resolution Rate
                    </div>
                    <div class="text-xl font-bold tracking-tight text-emerald-500 mt-0.5 tabular-nums">
                        {{ $metrics['overview']['ai_resolution']['rate'] }}%
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-7 h-7 rounded bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-xs">
                        <i class="ri-robot-line"></i>
                    </div>
                </div>
            </div>
            <div class="h-8 mt-1" id="sparklineAi"></div>
            <div class="flex items-center justify-between pt-1.5 mt-1 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Resolved without human</span>
                <span class="text-emerald-500 font-medium">Automated</span>
            </div>
        </div>

        {{-- Total Commerce Revenue --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                        Commerce Revenue
                    </div>
                    <div class="text-xl font-bold tracking-tight text-sky-500 mt-0.5 tabular-nums">
                        {{ $metrics['overview']['currency'] }} {{ number_format($metrics['overview']['revenue']['total'], 2) }}
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="app-badge {{ $metrics['overview']['revenue']['growth'] >= 0 ? 'app-badge-primary' : 'app-badge-warning' }} !text-2xs">
                        {{ $metrics['overview']['revenue']['growth'] >= 0 ? '+' : '' }}{{ $metrics['overview']['revenue']['growth'] }}%
                    </span>
                    <div class="w-7 h-7 rounded bg-sky-500/15 text-sky-500 flex items-center justify-center text-xs">
                        <i class="ri-money-dollar-circle-line"></i>
                    </div>
                </div>
            </div>
            <div class="h-8 mt-1" id="sparklineRev"></div>
            <div class="flex items-center justify-between pt-1.5 mt-1 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Completed orders</span>
                <span class="text-sky-500 font-medium">Net Sales</span>
            </div>
        </div>

        {{-- Actionable / Pending Support Queue --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">
                        Pending Queue
                    </div>
                    <div class="text-xl font-bold tracking-tight text-amber-500 mt-0.5 tabular-nums">
                        {{ $metrics['overview']['pending']['total_actionable'] }}
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="app-badge {{ $metrics['overview']['pending']['total_actionable'] > 0 ? 'app-badge-warning' : 'app-badge-success' }} !text-2xs">
                        {{ $metrics['overview']['pending']['total_actionable'] > 0 ? 'Needs Attention' : 'All Clear' }}
                    </span>
                    <div class="w-7 h-7 rounded bg-amber-500/15 text-amber-500 flex items-center justify-center text-xs">
                        <i class="ri-time-line"></i>
                    </div>
                </div>
            </div>
            <div class="py-2.5 space-y-1">
                <div class="flex items-center justify-between text-2xs">
                    <span class="text-light-muted dark:text-dark-muted">Open Conversations:</span>
                    <strong class="text-light-text dark:text-dark-text font-bold">{{ $metrics['overview']['pending']['conversations'] }}</strong>
                </div>
                <div class="flex items-center justify-between text-2xs">
                    <span class="text-light-muted dark:text-dark-muted">Unanswered Inquiries:</span>
                    <strong class="text-amber-500 font-bold">{{ $metrics['overview']['pending']['unanswered'] }}</strong>
                </div>
            </div>
            <div class="flex items-center justify-between pt-1.5 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Awaiting human review</span>
                <a href="{{ route('conversations.index') }}" class="text-brand-500 hover:text-brand-400 font-medium">Open Inbox &rarr;</a>
            </div>
        </div>
    </div>

    {{-- Main Visual Charts: Activity Trend & Omni-Channel Share --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        {{-- Left: Conversation Activity Trend Chart --}}
        <div class="lg:col-span-2 app-card flex flex-col justify-between">
            <div class="app-card-header flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <i class="ri-line-chart-line text-brand-500 text-sm"></i>
                    <div>
                        <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Conversation & Bot Activity Trend</h2>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Inbound customer messages vs AI automated replies vs human escalations</div>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <div class="inline-flex p-0.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-2xs" id="activityRangeGroup">
                        <button type="button" class="btn-range px-2 py-0.5 rounded font-medium {{ ($metrics['activity']['range'] ?? '14d') === '7d' ? 'bg-brand-500 text-white font-semibold' : 'text-light-text-secondary dark:text-dark-text-secondary' }}" data-range="7d">7D</button>
                        <button type="button" class="btn-range px-2 py-0.5 rounded font-medium {{ ($metrics['activity']['range'] ?? '14d') === '14d' ? 'bg-brand-500 text-white font-semibold' : 'text-light-text-secondary dark:text-dark-text-secondary' }}" data-range="14d">14D</button>
                        <button type="button" class="btn-range px-2 py-0.5 rounded font-medium {{ ($metrics['activity']['range'] ?? '14d') === '30d' ? 'bg-brand-500 text-white font-semibold' : 'text-light-text-secondary dark:text-dark-text-secondary' }}" data-range="30d">30D</button>
                    </div>
                </div>
            </div>
            <div class="app-card-body p-3">
                <div id="chartConversationActivity" class="w-full h-[260px]"></div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-3 mt-1 border-t border-light-border dark:border-dark-border text-2xs">
                    <div class="p-2 rounded bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                        <div class="text-light-muted dark:text-dark-muted">Total Inbound</div>
                        <div class="text-sm font-bold text-brand-500 mt-0.5 tabular-nums" id="statTotalInbound">{{ number_format($metrics['activity']['totals']['inbound']) }}</div>
                    </div>
                    <div class="p-2 rounded bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                        <div class="text-light-muted dark:text-dark-muted">AI Responses</div>
                        <div class="text-sm font-bold text-emerald-500 mt-0.5 tabular-nums" id="statTotalAi">{{ number_format($metrics['activity']['totals']['ai_replies']) }}</div>
                    </div>
                    <div class="p-2 rounded bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                        <div class="text-light-muted dark:text-dark-muted">Human Agent</div>
                        <div class="text-sm font-bold text-amber-500 mt-0.5 tabular-nums" id="statTotalHuman">{{ number_format($metrics['activity']['totals']['human_replies']) }}</div>
                    </div>
                    <div class="p-2 rounded bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                        <div class="text-light-muted dark:text-dark-muted">Unanswered</div>
                        <div class="text-sm font-bold text-red-500 mt-0.5 tabular-nums" id="statTotalUnanswered">{{ number_format($metrics['activity']['totals']['unanswered']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: Omni-Channel Share & AI Automation Rate --}}
        <div class="app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-2">
                    <i class="ri-pie-chart-line text-brand-500 text-sm"></i>
                    <div>
                        <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Omni-Channel Share</h2>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Active volume by connected platform</div>
                    </div>
                </div>
                <span class="app-badge app-badge-secondary" id="totalChannelThreads">
                    {{ $metrics['channels']['total'] }} Threads
                </span>
            </div>
            <div class="app-card-body p-3 flex flex-col justify-between">
                <div id="chartChannelDistribution" class="w-full h-[200px] flex items-center justify-center"></div>

                <div class="space-y-1.5 pt-3 border-t border-light-border dark:border-dark-border text-xs" id="channelListContainer">
                    @foreach($metrics['channels']['labels'] as $idx => $label)
                        @php
                            $cnt = $metrics['channels']['series'][$idx] ?? 0;
                            $pct = $metrics['channels']['total'] > 0 ? round(($cnt / $metrics['channels']['total']) * 100, 1) : 0;
                            $col = $metrics['channels']['colors'][$idx] ?? '#0284C7';
                        @endphp
                        <div class="flex items-center justify-between py-1">
                            <span class="flex items-center gap-2 text-light-text dark:text-dark-text">
                                <span class="w-2.5 h-1.5 rounded-xs inline-block" style="background-color: {{ $col }};"></span>
                                <span>{{ $label }}</span>
                            </span>
                            <div class="flex items-center gap-2">
                                <span class="text-2xs text-light-muted dark:text-dark-muted font-mono">{{ $pct }}%</span>
                                <strong class="text-2xs text-light-text dark:text-dark-text font-bold">{{ number_format($cnt) }}</strong>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Secondary Row: AI Routing Engine Breakdown & Queues --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        {{-- AI Performance & Resolution Health --}}
        <div class="app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                    <i class="ri-brain-line text-brand-500"></i>
                    <span>AI Engine Routing Breakdown</span>
                </div>
                <span class="app-badge app-badge-success">{{ $metrics['ai_performance']['resolution_rate'] }}% Auto-Resolved</span>
            </div>
            <div class="app-card-body space-y-2.5">
                @foreach($metrics['ai_performance']['categories'] as $cat)
                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-2xs">
                            <span class="text-light-text dark:text-dark-text font-medium">{{ $cat['name'] }}</span>
                            <span class="font-mono text-light-muted dark:text-dark-muted">{{ number_format($cat['value']) }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-light-canvas dark:bg-dark-canvas overflow-hidden border border-light-border dark:border-dark-border">
                            <div class="h-full rounded-full transition-all duration-300" style="background-color: {{ $cat['color'] }}; width: {{ min(100, max(5, $cat['value'])) }}%;"></div>
                        </div>
                    </div>
                @endforeach
                <p class="text-2xs text-light-muted dark:text-dark-muted pt-1">
                    Telemetry measured through HybridRouter, Vector Retrieval Engine, and Conversation Graph Memory.
                </p>
            </div>
        </div>

        {{-- Background Queues & Processing Card --}}
        <div class="lg:col-span-2 app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-2">
                    <i class="ri-hard-drive-3-line text-brand-500 text-sm"></i>
                    <div>
                        <div class="text-xs font-semibold text-light-text dark:text-dark-text">Background Queues & Workers</div>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">High-concurrency workers handling webhooks, CRM extraction, and vector index sync</div>
                    </div>
                </div>
                <a href="{{ route('dashboard') }}" class="app-btn app-btn-secondary !h-7 !px-2 text-2xs">
                    <i class="ri-refresh-line"></i> Refresh Queues
                </a>
            </div>
            <div class="app-card-body space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    {{-- Messenger Queue --}}
                    <div class="p-3 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas space-y-1.5">
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
                    <div class="p-3 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas space-y-1.5">
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
                    <div class="p-3 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-medium text-light-text dark:text-dark-text flex items-center gap-1.5">
                                <i class="ri-brain-line text-sky-500"></i> Vector Indexer
                            </span>
                            <span class="app-badge app-badge-secondary">Typesense</span>
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
    </div>
</div>
@endsection

@push('custom-scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const isDarkMode = () => document.documentElement.classList.contains('dark');

        // Theme palette colors
        const getChartColors = () => ({
            background: 'transparent',
            text: isDarkMode() ? '#94A3B8' : '#64748B',
            border: isDarkMode() ? '#1E293B' : '#E2E8F0',
            grid: isDarkMode() ? '#1E293B' : '#F1F5F9',
            brand: '#0284C7',
            emerald: '#10B981',
            amber: '#F59E0B',
            red: '#EF4444',
            purple: '#8B5CF6'
        });

        // 1. Sparkline Helper
        function renderSparkline(elId, data, color) {
            const el = document.getElementById(elId);
            if (!el || typeof ApexCharts === 'undefined') return;
            const options = {
                series: [{ data: data }],
                chart: {
                    type: 'area',
                    height: 35,
                    sparkline: { enabled: true },
                    animations: { enabled: true }
                },
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.35,
                        opacityTo: 0.0,
                        stops: [0, 90, 100]
                    }
                },
                colors: [color],
                tooltip: { fixed: { enabled: false }, x: { show: false } }
            };
            const chart = new ApexCharts(el, options);
            chart.render();
            return chart;
        }

        renderSparkline('sparklineConv', {!! json_encode($metrics['overview']['conversations']['sparkline'] ?? [0,0,0,0,0,0,0]) !!}, '#0284C7');
        renderSparkline('sparklineAi', {!! json_encode($metrics['overview']['ai_resolution']['sparkline'] ?? [100,100,100,100,100,100,100]) !!}, '#10B981');
        renderSparkline('sparklineRev', {!! json_encode($metrics['overview']['revenue']['sparkline'] ?? [0,0,0,0,0,0,0]) !!}, '#0EA5E9');

        // 2. Main Conversation Activity Multi-Line Trend
        let activityChart = null;
        const initialActivity = {!! json_encode($metrics['activity']) !!};

        function renderActivityChart(data) {
            const chartEl = document.getElementById('chartConversationActivity');
            if (!chartEl || typeof ApexCharts === 'undefined') return;

            const c = getChartColors();
            const options = {
                series: [
                    { name: 'Inbound Customer', data: data.inbound || [] },
                    { name: 'AI Automated Reply', data: data.ai_replies || [] },
                    { name: 'Human Agent Reply', data: data.human_replies || [] }
                ],
                chart: {
                    type: 'area',
                    height: 250,
                    toolbar: { show: false },
                    fontFamily: 'Inter, sans-serif',
                    background: 'transparent'
                },
                colors: [c.brand, c.emerald, c.amber],
                stroke: { curve: 'smooth', width: 2 },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.25,
                        opacityTo: 0.02,
                        stops: [0, 95, 100]
                    }
                },
                dataLabels: { enabled: false },
                xaxis: {
                    categories: data.dates || [],
                    labels: { style: { colors: c.text, fontSize: '11px' } },
                    axisBorder: { color: c.border },
                    axisTicks: { color: c.border }
                },
                yaxis: {
                    labels: {
                        style: { colors: c.text, fontSize: '11px' },
                        formatter: val => Math.round(val)
                    }
                },
                grid: {
                    borderColor: c.grid,
                    strokeDashArray: 3
                },
                legend: {
                    position: 'top',
                    horizontalAlign: 'right',
                    labels: { colors: isDarkMode() ? '#F8FAFC' : '#020617' },
                    fontSize: '11px'
                },
                tooltip: {
                    custom: function({ series, seriesIndex, dataPointIndex, w }) {
                        const date = w.globals.categoryLabels[dataPointIndex] || (data.dates ? data.dates[dataPointIndex] : '');
                        const inbound = series[0] && series[0][dataPointIndex] !== undefined ? series[0][dataPointIndex] : 0;
                        const ai = series[1] && series[1][dataPointIndex] !== undefined ? series[1][dataPointIndex] : 0;
                        const human = series[2] && series[2][dataPointIndex] !== undefined ? series[2][dataPointIndex] : 0;
                        const isDark = isDarkMode();
                        
                        return `
                        <div style="background: ${isDark ? '#0F172A' : '#FFFFFF'}; border: 1px solid ${isDark ? '#1E293B' : '#E2E8F0'}; border-radius: 8px; padding: 10px 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); font-family: Inter, sans-serif; min-width: 175px;">
                            <div style="font-size: 11px; font-weight: 600; color: ${isDark ? '#94A3B8' : '#64748B'}; margin-bottom: 6px; padding-bottom: 4px; border-bottom: 1px solid ${isDark ? '#1E293B' : '#F1F5F9'};">
                                ${date}
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; margin-bottom: 3px;">
                                <span style="display: flex; align-items: center; gap: 5px; color: ${isDark ? '#F8FAFC' : '#020617'};">
                                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #0EA5E9; display: inline-block;"></span> Inbound
                                </span>
                                <strong style="color: #0EA5E9; font-variant-numeric: tabular-nums;">${Number(inbound).toLocaleString()}</strong>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; margin-bottom: 3px;">
                                <span style="display: flex; align-items: center; gap: 5px; color: ${isDark ? '#F8FAFC' : '#020617'};">
                                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #10B981; display: inline-block;"></span> AI Automated
                                </span>
                                <strong style="color: #10B981; font-variant-numeric: tabular-nums;">${Number(ai).toLocaleString()}</strong>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px;">
                                <span style="display: flex; align-items: center; gap: 5px; color: ${isDark ? '#F8FAFC' : '#020617'};">
                                    <span style="width: 7px; height: 7px; border-radius: 50%; background: #F59E0B; display: inline-block;"></span> Human Support
                                </span>
                                <strong style="color: #F59E0B; font-variant-numeric: tabular-nums;">${Number(human).toLocaleString()}</strong>
                            </div>
                        </div>`;
                    }
                }
            };

            if (activityChart) {
                activityChart.updateOptions(options);
            } else {
                activityChart = new ApexCharts(chartEl, options);
                activityChart.render();
            }
        }

        renderActivityChart(initialActivity);

        // 3. Omni-Channel Share Donut Chart
        let channelChart = null;
        const initialChannels = {!! json_encode($metrics['channels']) !!};

        function renderChannelChart(channels) {
            const chartEl = document.getElementById('chartChannelDistribution');
            if (!chartEl || typeof ApexCharts === 'undefined') return;

            const options = {
                series: channels.series || [1],
                labels: channels.labels || ['Website'],
                colors: channels.colors || ['#0284C7', '#3B82F6', '#10B981'],
                chart: {
                    type: 'donut',
                    height: 200,
                    fontFamily: 'Inter, sans-serif',
                    background: 'transparent'
                },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                name: { fontSize: '12px', color: isDarkMode() ? '#94A3B8' : '#64748B' },
                                value: {
                                    fontSize: '16px',
                                    fontWeight: 'bold',
                                    color: isDarkMode() ? '#F8FAFC' : '#020617',
                                    formatter: val => val
                                },
                                total: {
                                    show: true,
                                    label: 'Total',
                                    fontSize: '11px',
                                    color: isDarkMode() ? '#94A3B8' : '#64748B',
                                    formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                legend: { show: false },
                stroke: { show: true, width: 2, colors: [isDarkMode() ? '#0F172A' : '#FFFFFF'] },
                tooltip: {
                    theme: isDarkMode() ? 'dark' : 'light',
                    y: { formatter: val => val + ' threads' }
                }
            };

            if (channelChart) {
                channelChart.updateOptions(options);
            } else {
                channelChart = new ApexCharts(chartEl, options);
                channelChart.render();
            }
        }

        renderChannelChart(initialChannels);

        // 4. Activity Range Filter Buttons (7D / 14D / 30D via AJAX)
        document.querySelectorAll('#activityRangeGroup .btn-range').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('#activityRangeGroup .btn-range').forEach(b => {
                    b.className = 'btn-range px-2 py-0.5 rounded font-medium text-light-text-secondary dark:text-dark-text-secondary';
                });
                this.className = 'btn-range px-2 py-0.5 rounded font-medium bg-brand-500 text-white font-semibold';

                const range = this.getAttribute('data-range');
                fetch('{{ route('dashboard') }}?range=' + range, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(json => {
                    if (json.status === 'success' && json.data.activity) {
                        renderActivityChart(json.data.activity);
                        document.getElementById('statTotalInbound').textContent = Number(json.data.activity.totals.inbound).toLocaleString();
                        document.getElementById('statTotalAi').textContent = Number(json.data.activity.totals.ai_replies).toLocaleString();
                        document.getElementById('statTotalHuman').textContent = Number(json.data.activity.totals.human_replies).toLocaleString();
                        document.getElementById('statTotalUnanswered').textContent = Number(json.data.activity.totals.unanswered).toLocaleString();
                    }
                })
                .catch(err => console.error('Failed to load activity range', err));
            });
        });

        // 5. Dynamic Theme Change Listener (Updates ApexCharts Dark/Light mode on toggle)
        window.addEventListener('agy-theme-changed', function (e) {
            const isDark = e.detail.theme === 'dark';
            const c = getChartColors();
            if (activityChart) {
                activityChart.updateOptions({
                    xaxis: { labels: { style: { colors: c.text } }, axisBorder: { color: c.border }, axisTicks: { color: c.border } },
                    yaxis: { labels: { style: { colors: c.text } } },
                    grid: { borderColor: c.grid },
                    legend: { labels: { colors: isDark ? '#F8FAFC' : '#020617' } },
                    tooltip: { theme: isDark ? 'dark' : 'light' }
                });
            }
            if (channelChart) {
                channelChart.updateOptions({
                    stroke: { colors: [isDark ? '#0F172A' : '#FFFFFF'] },
                    tooltip: { theme: isDark ? 'dark' : 'light' }
                });
            }
        });
    });
</script>
@endpush
