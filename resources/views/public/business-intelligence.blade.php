@extends('layouts.public')

@section('title', 'Business Intelligence & Analytics — Entrepreneurs Automation')
@section('meta_description', 'Actionable Business Intelligence, Not Just Chat Logs. Gain complete visibility over your customer operations. Real-time resolution metrics, demand clustering, token economics, and executive analytics.')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

        {{-- Hero --}}
        <div class="text-center space-y-4 max-w-4xl mx-auto">
            <div
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-purple-500/10 text-purple-500 border border-purple-500/30">
                <i class="ri-bar-chart-box-line"></i> {{ __('public.bi.badge') }}
            </div>
            <h1
                class="text-3xl sm:text-5xl font-bold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28] text-light-text dark:text-dark-text">
                {!! __('public.bi.title') !!}
            </h1>
            <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                {{ __('public.bi.desc') }}
            </p>
        </div>

        {{-- Core Analytics Pillars --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div
                    class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-lg font-bold">
                    <i class="ri-line-chart-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">{{ __('public.bi.p1_title') }}</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.bi.p1_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div
                    class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-lg font-bold">
                    <i class="ri-speed-up-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">{{ __('public.bi.p2_title') }}</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.bi.p2_desc') }}
                </p>
            </div>

            <div
                class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div
                    class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-lg font-bold">
                    <i class="ri-wallet-3-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">{{ __('public.bi.p3_title') }}</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.bi.p3_desc') }}
                </p>
            </div>
        </div>

        {{-- ==================== LIVE DATA VISUALIZATION DASHBOARD ==================== --}}
        <div
            class="p-6 sm:p-10 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-8">
            <div
                class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-light-border dark:border-dark-border gap-3">
                <div class="flex items-center gap-2">
                    <i class="ri-dashboard-3-line text-purple-500 text-lg"></i>
                    <div>
                        <h2 class="text-sm font-bold text-light-text dark:text-dark-text">
                            {{ __('public.bi.overview_title') }}</h2>
                        <p class="text-3xs text-light-muted dark:text-dark-muted font-mono">
                            {{ __('public.bi.overview_sub') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 font-mono text-3xs">
                    <span
                        class="px-2.5 py-1 rounded-md bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border text-light-muted dark:text-dark-muted">
                        {{ __('public.bi.timeframe') }}
                    </span>
                    <span
                        class="px-2.5 py-1 rounded-md bg-emerald-500/10 text-emerald-500 font-semibold border border-emerald-500/20">
                        {{ __('public.bi.telemetry_live') }}
                    </span>
                </div>
            </div>

            {{-- 4 Top KPI Cards --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 font-mono">
                <div
                    class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                    <div class="text-3xs text-light-muted dark:text-dark-muted uppercase font-bold">
                        {{ __('public.bi.total_interactions') }}</div>
                    <div class="text-xl sm:text-2xl font-bold text-light-text dark:text-dark-text mt-1">42,890</div>
                    <div class="text-3xs text-emerald-500 mt-1 flex items-center gap-1">
                        <i class="ri-arrow-up-line"></i> {{ __('public.bi.vs_last_mo') }}
                    </div>
                </div>

                <div
                    class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                    <div class="text-3xs text-light-muted dark:text-dark-muted uppercase font-bold">
                        {{ __('public.bi.auto_resolution') }}</div>
                    <div class="text-xl sm:text-2xl font-bold text-emerald-500 mt-1">94.6%</div>
                    <div class="text-3xs text-light-muted dark:text-dark-muted mt-1">{{ __('public.bi.human_handoff') }}
                    </div>
                </div>

                <div
                    class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                    <div class="text-3xs text-light-muted dark:text-dark-muted uppercase font-bold">
                        {{ __('public.bi.avg_latency') }}</div>
                    <div class="text-xl sm:text-2xl font-bold text-brand-500 mt-1">218ms</div>
                    <div class="text-3xs text-emerald-500 mt-1">{{ __('public.bi.sub_second') }}</div>
                </div>

                <div
                    class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                    <div class="text-3xs text-light-muted dark:text-dark-muted uppercase font-bold">
                        {{ __('public.bi.avg_cost') }}</div>
                    <div class="text-xl sm:text-2xl font-bold text-purple-500 mt-1">$0.00031</div>
                    <div class="text-3xs text-light-muted dark:text-dark-muted mt-1">{{ __('public.bi.cost_sub') }}</div>
                </div>
            </div>

            {{-- Visual Chart Section 1: 7-Day Resolution Volume Trend (SVG Area Chart) --}}
            <div
                class="p-5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-bold font-sans text-light-text dark:text-dark-text">
                            {{ __('public.bi.chart_title') }}</h3>
                        <p class="text-3xs text-light-muted dark:text-dark-muted font-mono">{{ __('public.bi.chart_sub') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3 text-3xs font-mono">
                        <span class="flex items-center gap-1.5 text-light-text dark:text-dark-text">
                            <span class="w-2.5 h-1.5 rounded-xs bg-brand-500 inline-block"></span>
                            {{ __('public.bi.legend_total') }}
                        </span>
                        <span class="flex items-center gap-1.5 text-light-text dark:text-dark-text">
                            <span class="w-2.5 h-1.5 rounded-xs bg-emerald-500 inline-block"></span>
                            {{ __('public.bi.legend_ai') }}
                        </span>
                    </div>
                </div>

                {{-- Pure SVG Area Chart --}}
                <div class="w-full h-48 relative">
                    <svg class="w-full h-full" viewBox="0 0 700 180" preserveAspectRatio="none">
                        <defs>
                            <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#0284c7" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="#0284c7" stop-opacity="0.0" />
                            </linearGradient>
                        </defs>

                        {{-- Grid Lines --}}
                        <line x1="0" y1="30" x2="700" y2="30" stroke="currentColor"
                            class="text-light-border/40 dark:text-dark-border/40" stroke-dasharray="3 3" />
                        <line x1="0" y1="80" x2="700" y2="80" stroke="currentColor"
                            class="text-light-border/40 dark:text-dark-border/40" stroke-dasharray="3 3" />
                        <line x1="0" y1="130" x2="700" y2="130" stroke="currentColor"
                            class="text-light-border/40 dark:text-dark-border/40" stroke-dasharray="3 3" />

                        {{-- Area Fill --}}
                        <polygon points="0,120 100,105 200,85 300,90 400,60 500,45 600,50 700,35 700,180 0,180"
                            fill="url(#areaGrad)" />

                        {{-- Total Line --}}
                        <polyline points="0,120 100,105 200,85 300,90 400,60 500,45 600,50 700,35" fill="none"
                            stroke="#0284c7" stroke-width="2.5" stroke-linecap="round" />

                        {{-- Autonomous Green Line --}}
                        <polyline points="0,135 100,120 200,100 300,105 400,75 500,58 600,62 700,48" fill="none"
                            stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-dasharray="2 2" />
                    </svg>

                    {{-- X Axis Labels --}}
                    <div
                        class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted pt-2 border-t border-light-border/60 dark:border-dark-border/60">
                        <span>Mon (4.2k)</span>
                        <span>Tue (4.8k)</span>
                        <span>Wed (5.6k)</span>
                        <span>Thu (6.1k)</span>
                        <span>Fri (7.4k)</span>
                        <span>Sat (8.1k)</span>
                        <span>Sun (6.6k)</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- CTA --}}
        <div class="text-center pt-8">
            <a href="{{ route('register') }}"
                class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
                <span>{{ __('public.home.get_started_btn') }}</span>
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>

    </div>
@endsection