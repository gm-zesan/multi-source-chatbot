@extends('layouts.public')

@section('title', 'Business Intelligence & Analytics — Entrepreneurs Automation')
@section('meta_description', 'Gain complete visibility over your customer operations. Real-time resolution metrics, demand clustering, token economics, and executive analytics.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-purple-500/10 text-purple-500 border border-purple-500/30">
            <i class="ri-bar-chart-box-line"></i> Owner-First Telemetry
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            Actionable Business Intelligence, Not Just Chat Logs.
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            Understand exactly what products customers are searching for, peak inquiry windows, response latency, and cost per resolution with complete mathematical precision.
        </p>
    </div>

    {{-- Core Analytics Pillars --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
            <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-lg font-bold">
                <i class="ri-line-chart-line"></i>
            </div>
            <h3 class="text-base font-bold text-light-text dark:text-dark-text">Demand Clustering</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Discover unfulfilled inventory demand. See which products users asked for that were out of stock or missing from your catalog.
            </p>
        </div>

        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
            <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-lg font-bold">
                <i class="ri-speed-up-line"></i>
            </div>
            <h3 class="text-base font-bold text-light-text dark:text-dark-text">Resolution Latency</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Track sub-second round-trip latency across LLM generation, vector retrieval, and webhook delivery. Benchmark human vs AI resolution speed.
            </p>
        </div>

        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-lg font-bold">
                <i class="ri-wallet-3-line"></i>
            </div>
            <h3 class="text-base font-bold text-light-text dark:text-dark-text">Token Economy & ROI</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Complete transparency over prompt tokens, completion tokens, and real-time cost calculation in both USD and BDT with zero hidden markups.
            </p>
        </div>
    </div>

    {{-- ==================== LIVE DATA VISUALIZATION DASHBOARD ==================== --}}
    <div class="p-6 sm:p-10 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-8">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-light-border dark:border-dark-border gap-3">
            <div class="flex items-center gap-2">
                <i class="ri-dashboard-3-line text-purple-500 text-lg"></i>
                <div>
                    <h2 class="text-sm font-bold text-light-text dark:text-dark-text">Executive Performance Overview</h2>
                    <p class="text-3xs text-light-muted dark:text-dark-muted font-mono">Live Ingestion & Multi-Source Routing Metrics</p>
                </div>
            </div>
            <div class="flex items-center gap-2 font-mono text-3xs">
                <span class="px-2.5 py-1 rounded-md bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border text-light-muted dark:text-dark-muted">
                    Timeframe: Last 30 Days
                </span>
                <span class="px-2.5 py-1 rounded-md bg-emerald-500/10 text-emerald-500 font-semibold border border-emerald-500/20">
                    Telemetry: 99.98% Live
                </span>
            </div>
        </div>

        {{-- 4 Top KPI Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 font-mono">
            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                <div class="text-3xs text-light-muted dark:text-dark-muted uppercase font-bold">Total Interactions</div>
                <div class="text-xl sm:text-2xl font-bold text-light-text dark:text-dark-text mt-1">42,890</div>
                <div class="text-3xs text-emerald-500 mt-1 flex items-center gap-1">
                    <i class="ri-arrow-up-line"></i> +28.4% vs last mo
                </div>
            </div>

            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                <div class="text-3xs text-light-muted dark:text-dark-muted uppercase font-bold">Autonomous Resolution</div>
                <div class="text-xl sm:text-2xl font-bold text-emerald-500 mt-1">94.6%</div>
                <div class="text-3xs text-light-muted dark:text-dark-muted mt-1">5.4% human handoff</div>
            </div>

            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                <div class="text-3xs text-light-muted dark:text-dark-muted uppercase font-bold">Average Latency</div>
                <div class="text-xl sm:text-2xl font-bold text-brand-500 mt-1">218ms</div>
                <div class="text-3xs text-emerald-500 mt-1">Sub-second response</div>
            </div>

            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                <div class="text-3xs text-light-muted dark:text-dark-muted uppercase font-bold">Avg Cost / Resolution</div>
                <div class="text-xl sm:text-2xl font-bold text-purple-500 mt-1">$0.00031</div>
                <div class="text-3xs text-light-muted dark:text-dark-muted mt-1">~৳0.038 per inquiry</div>
            </div>
        </div>

        {{-- Visual Chart Section 1: 7-Day Resolution Volume Trend (SVG Area Chart) --}}
        <div class="p-5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold font-sans text-light-text dark:text-dark-text">7-Day Interaction Volume & Autonomous Velocity</h3>
                    <p class="text-3xs text-light-muted dark:text-dark-muted font-mono">Daily volume distribution across all connected stores</p>
                </div>
                <div class="flex items-center gap-3 text-3xs font-mono">
                    <span class="flex items-center gap-1.5 text-light-text dark:text-dark-text">
                        <span class="w-2.5 h-1.5 rounded-xs bg-brand-500 inline-block"></span> Total Conversations
                    </span>
                    <span class="flex items-center gap-1.5 text-light-text dark:text-dark-text">
                        <span class="w-2.5 h-1.5 rounded-xs bg-emerald-500 inline-block"></span> Autonomous (AI)
                    </span>
                </div>
            </div>

            {{-- Pure SVG Area Chart --}}
            <div class="w-full h-48 relative">
                <svg class="w-full h-full" viewBox="0 0 700 180" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#0284c7" stop-opacity="0.35"/>
                            <stop offset="100%" stop-color="#0284c7" stop-opacity="0.0"/>
                        </linearGradient>
                    </defs>

                    {{-- Grid Lines --}}
                    <line x1="0" y1="30" x2="700" y2="30" stroke="currentColor" class="text-light-border/40 dark:text-dark-border/40" stroke-dasharray="3 3"/>
                    <line x1="0" y1="80" x2="700" y2="80" stroke="currentColor" class="text-light-border/40 dark:text-dark-border/40" stroke-dasharray="3 3"/>
                    <line x1="0" y1="130" x2="700" y2="130" stroke="currentColor" class="text-light-border/40 dark:text-dark-border/40" stroke-dasharray="3 3"/>

                    {{-- Area Fill --}}
                    <polygon points="0,120 100,105 200,85 300,90 400,60 500,45 600,50 700,35 700,180 0,180" fill="url(#areaGrad)"/>

                    {{-- Total Line --}}
                    <polyline points="0,120 100,105 200,85 300,90 400,60 500,45 600,50 700,35" fill="none" stroke="#0284c7" stroke-width="2.5" stroke-linecap="round"/>

                    {{-- Autonomous Green Line --}}
                    <polyline points="0,135 100,120 200,100 300,105 400,75 500,58 600,62 700,48" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-dasharray="2 2"/>
                </svg>

                {{-- X Axis Labels --}}
                <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted pt-2 border-t border-light-border/60 dark:border-dark-border/60">
                    <span>Mon (4.2k)</span>
                    <span>Tue (4.8k)</span>
                    <span>Wed (5.6k)</span>
                    <span>Thu (5.4k)</span>
                    <span>Fri (6.9k)</span>
                    <span>Sat (7.8k)</span>
                    <span>Sun (8.1k)</span>
                </div>
            </div>
        </div>

        {{-- Visual Chart Section 2: Two Columns (Channel Breakdown + Latency Distribution) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            {{-- Channels Breakdown --}}
            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-3">
                <div class="flex justify-between items-center">
                    <h3 class="text-xs font-bold text-light-text dark:text-dark-text font-sans">Channel Distribution</h3>
                    <span class="text-3xs font-mono text-light-muted dark:text-dark-muted">Volume by Endpoint</span>
                </div>
                <div class="space-y-2 text-2xs font-mono">
                    <div>
                        <div class="flex justify-between text-light-muted dark:text-dark-muted mb-1">
                            <span>Facebook Messenger</span>
                            <span class="font-bold text-light-text dark:text-dark-text">58.4% (25,047)</span>
                        </div>
                        <div class="w-full h-2 rounded bg-light-surface dark:bg-dark-surface overflow-hidden">
                            <div class="h-full bg-blue-500 rounded" style="width: 58.4%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-light-muted dark:text-dark-muted mb-1">
                            <span>Web Chat Widget</span>
                            <span class="font-bold text-light-text dark:text-dark-text">32.2% (13,810)</span>
                        </div>
                        <div class="w-full h-2 rounded bg-light-surface dark:bg-dark-surface overflow-hidden">
                            <div class="h-full bg-brand-500 rounded" style="width: 32.2%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-light-muted dark:text-dark-muted mb-1">
                            <span>REST API & Integrations</span>
                            <span class="font-bold text-light-text dark:text-dark-text">9.4% (4,033)</span>
                        </div>
                        <div class="w-full h-2 rounded bg-light-surface dark:bg-dark-surface overflow-hidden">
                            <div class="h-full bg-purple-500 rounded" style="width: 9.4%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- P95 Latency Breakdown --}}
            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-3">
                <div class="flex justify-between items-center">
                    <h3 class="text-xs font-bold text-light-text dark:text-dark-text font-sans">Latency Stage Breakdown</h3>
                    <span class="text-3xs font-mono text-emerald-500 font-bold">Avg 218ms Total</span>
                </div>
                <div class="space-y-2 text-2xs font-mono">
                    <div>
                        <div class="flex justify-between text-light-muted dark:text-dark-muted mb-1">
                            <span>1. Lexical & Vector Search</span>
                            <span class="font-bold text-emerald-500">38ms</span>
                        </div>
                        <div class="w-full h-2 rounded bg-light-surface dark:bg-dark-surface overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded" style="width: 18%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-light-muted dark:text-dark-muted mb-1">
                            <span>2. Model Inference & Grounding</span>
                            <span class="font-bold text-sky-500">156ms</span>
                        </div>
                        <div class="w-full h-2 rounded bg-light-surface dark:bg-dark-surface overflow-hidden">
                            <div class="h-full bg-sky-500 rounded" style="width: 72%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-light-muted dark:text-dark-muted mb-1">
                            <span>3. Webhook Delivery & Dispatch</span>
                            <span class="font-bold text-purple-500">24ms</span>
                        </div>
                        <div class="w-full h-2 rounded bg-light-surface dark:bg-dark-surface overflow-hidden">
                            <div class="h-full bg-purple-500 rounded" style="width: 10%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- CTA --}}
    <div class="text-center pt-8">
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
            <span>Unlock Executive Intelligence</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

</div>
@endsection
