@extends('layouts.public')

@section('title', 'Entrepreneurs Automation — Your Business. Connected. Intelligent. Automated.')
@section('meta_description', 'Entrepreneurs Automation is an enterprise platform connecting customer conversations, multi-source business knowledge, and intelligent commerce automation.')

@section('content')
<div class="space-y-24 sm:space-y-32 pb-24">

    {{-- ==================== 1. HERO SECTION ==================== --}}
    <section class="relative pt-12 sm:pt-20 lg:pt-24 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-8">
            
            {{-- Announcement Badge (Minted Chamfer Bevel) --}}
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-light-border dark:border-white/[0.1] bg-light-surface/90 dark:bg-dark-surface/90 backdrop-blur-md text-brand-600 dark:text-brand-400 text-xs font-semibold shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08)] hover:border-brand-500/50 transition-colors">
                <i class="ri-sparkling-fill text-brand-500 text-xs"></i>
                <span>{{ __('public.home.announcement_badge') }}</span>
                <span class="hidden sm:inline text-light-border dark:text-dark-border">/</span>
                <span class="hidden sm:inline text-light-text dark:text-dark-text font-normal">{{ __('public.home.announcement_sub') }}</span>
                <i class="ri-arrow-right-line text-xs"></i>
            </div>

            {{-- Main Hero Headline (Metallic Minted Typography) --}}
            <div class="max-w-5xl mx-auto space-y-4 sm:space-y-6">
                <h1 class="text-[1.35rem] xs:text-2xl sm:text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight leading-[1.45] sm:leading-[1.36] md:leading-[1.32] lg:leading-[1.28]">
                    <span class="block bg-gradient-to-b from-slate-900 via-slate-800 to-slate-600 dark:from-white dark:via-slate-100 dark:to-slate-400/80 bg-clip-text text-transparent drop-shadow-sm dark:drop-shadow-[0_2px_14px_rgba(255,255,255,0.08)]">
                        {{ __('public.home.hero_title_1') }}
                    </span>
                    <span class="block bg-gradient-to-r from-brand-500 via-sky-400 to-teal-400 bg-clip-text text-transparent drop-shadow-sm">
                        {{ __('public.home.hero_title_2') }}
                    </span>
                </h1>
                <p class="text-sm sm:text-base md:text-lg text-light-text-secondary dark:text-dark-text-secondary max-w-2xl mx-auto leading-relaxed pt-1">
                    {{ __('public.home.hero_subtitle') }}
                </p>
            </div>

            {{-- Dual CTAs with Button Shimmer --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                <a href="{{ route('register') }}" class="btn-shimmer w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-semibold text-sm shadow-xs hover:shadow-sm transition-all">
                    <span>{{ __('public.home.get_started_btn') }}</span>
                    <i class="ri-arrow-right-line"></i>
                </a>
                <a href="{{ route('platform') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface/80 dark:bg-dark-surface/80 hover:bg-light-elevated dark:hover:bg-dark-elevated text-light-text dark:text-dark-text font-semibold text-sm shadow-xs transition-colors">
                    <i class="ri-compass-3-line text-brand-500"></i>
                    <span>{{ __('public.home.explore_platform_btn') }}</span>
                </a>
            </div>

            {{-- ==================== INTERACTIVE HERO PRODUCT SHOWCASE ==================== --}}
            <div class="pt-8 max-w-6xl mx-auto">
                <div class="chamfer-card spotlight-card relative rounded-2xl border border-light-border dark:border-white/[0.1] bg-light-surface/90 dark:bg-dark-surface/90 backdrop-blur-xl shadow-sm dark:shadow-[0_8px_30px_rgb(0,0,0,0.3)] p-3 sm:p-5 text-left">
                    
                    {{-- Showcase Header & Interactive Module Tabs --}}
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between pb-3 border-b border-light-border dark:border-white/[0.08] mb-4 gap-3">
                        <div class="flex items-center gap-2 text-xs font-mono">
                            <i class="ri-terminal-box-line text-brand-500 text-base"></i>
                            <span class="px-2 py-0.5 rounded bg-brand-500/10 text-brand-500 font-semibold text-3xs uppercase tracking-wider">Console v2.0</span>
                            <span class="text-light-muted dark:text-dark-muted hidden md:inline">entrepreneurs.app/console/unified-operations</span>
                        </div>

                        {{-- Interactive Module Tabs (The 3 Specialized AI Employees) --}}
                        <div class="flex items-center gap-1 p-1 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08] text-xs">
                            <button type="button" onclick="switchHeroTab('sales')" id="tabBtn-sales" class="hero-tab-btn px-3 py-1.5 rounded-lg text-3xs font-semibold bg-brand-600 text-white shadow-xs transition-all flex items-center gap-1.5">
                                <i class="ri-customer-service-2-line text-xs"></i>
                                <span>{{ __('public.home.tab_sales') }}</span>
                            </button>
                            <button type="button" onclick="switchHeroTab('ops')" id="tabBtn-ops" class="hero-tab-btn px-3 py-1.5 rounded-lg text-3xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-all flex items-center gap-1.5">
                                <i class="ri-truck-line text-xs"></i>
                                <span>{{ __('public.home.tab_ops') }}</span>
                            </button>
                            <button type="button" onclick="switchHeroTab('cfo')" id="tabBtn-cfo" class="hero-tab-btn px-3 py-1.5 rounded-lg text-3xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-all flex items-center gap-1.5">
                                <i class="ri-funds-line text-xs"></i>
                                <span>{{ __('public.home.tab_cfo') }}</span>
                            </button>
                        </div>
                    </div>

                    {{-- Live Simulation Query Trigger Pills --}}
                    <div id="sandboxPillsBar" class="mb-4 p-2.5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08] flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-3xs font-mono font-bold uppercase tracking-wider text-light-muted dark:text-dark-muted">{{ __('public.home.sim_title') }}</span>
                            <span class="text-3xs text-light-muted dark:text-dark-muted">{{ __('public.home.sim_desc') }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button type="button" onclick="runSimulationQuery('sales')" class="sim-btn px-2.5 py-1 rounded-lg text-3xs font-medium border border-brand-500/30 bg-brand-500/10 text-brand-600 dark:text-brand-400 hover:bg-brand-500/20 transition-colors">
                                {{ __('public.home.sim_btn_sales') }}
                            </button>
                            <button type="button" onclick="runSimulationQuery('ops')" class="sim-btn px-2.5 py-1 rounded-lg text-3xs font-medium border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-brand-500/50 transition-colors">
                                {{ __('public.home.sim_btn_ops') }}
                            </button>
                            <button type="button" onclick="runSimulationQuery('cfo')" class="sim-btn px-2.5 py-1 rounded-lg text-3xs font-medium border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-brand-500/50 transition-colors">
                                {{ __('public.home.sim_btn_cfo') }}
                            </button>
                        </div>
                    </div>

                    {{-- UI MODE: Tab 1 View: Customer Sales Agent (Front Office) --}}
                    <div id="heroTabView-sales" class="hero-tab-content grid grid-cols-1 md:grid-cols-12 gap-3.5">
                        {{-- Left: Live Dialogue Stream --}}
                        <div class="chamfer-card md:col-span-4 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2 mb-3">
                                    <div class="flex items-center gap-2 text-xs font-semibold">
                                        <i class="ri-message-3-line text-brand-500"></i>
                                        <span>{{ __('public.home.sales_dialogue_title') }}</span>
                                    </div>
                                    <span class="px-1.5 py-0.5 rounded text-3xs font-mono bg-brand-500/15 text-brand-500 font-bold uppercase">{{ __('public.home.sales_office_tag') }}</span>
                                </div>
                                
                                <div class="space-y-2.5 text-xs" id="chatDialogueArea">
                                    <div class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] space-y-1">
                                        <div class="flex items-center justify-between text-3xs text-light-muted dark:text-dark-muted font-mono">
                                            <span class="font-semibold text-light-text dark:text-dark-text">{{ __('public.home.sales_sim_customer') }}</span>
                                            <span>Just now</span>
                                        </div>
                                        <p class="text-light-text-secondary dark:text-dark-text-secondary text-2xs" id="simCustomerQuestion">
                                            "{{ __('public.home.sales_sim_q') }}"
                                        </p>
                                    </div>

                                    <div class="p-2.5 rounded-lg bg-brand-500/10 border border-brand-500/20 space-y-1 transition-all">
                                        <div class="flex items-center justify-between text-3xs text-brand-500 font-semibold">
                                            <span class="flex items-center gap-1"><i class="ri-sparkling-fill text-xs"></i> {{ __('public.home.sales_agent_label') }}</span>
                                            <span class="font-mono text-3xs" id="simLatency">180ms</span>
                                        </div>
                                        <p class="text-light-text dark:text-dark-text text-2xs leading-relaxed" id="simAiAnswer">
                                            "{{ __('public.home.sales_sim_a') }}"
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-light-border dark:border-white/[0.08] flex items-center justify-between text-3xs font-mono text-light-muted dark:text-dark-muted">
                                <span>{{ __('public.home.sales_lang_status') }}</span>
                                <span class="text-emerald-500 font-semibold">{{ __('public.home.sales_active_status') }}</span>
                            </div>
                        </div>

                        {{-- Center: Knowledge Pipeline & Row Discovery --}}
                        <div class="chamfer-card md:col-span-5 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3">
                            <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2">
                                <div class="flex items-center gap-2 text-xs font-semibold">
                                    <i class="ri-database-2-line text-sky-500"></i>
                                    <span>{{ __('public.home.sales_pipeline_title') }}</span>
                                </div>
                                <span class="text-3xs font-mono text-emerald-500 flex items-center gap-1 font-semibold">
                                    <i class="ri-checkbox-circle-fill"></i> {{ __('public.home.sales_hallucination_tag') }}
                                </span>
                            </div>

                            <div class="space-y-2 text-2xs">
                                <div class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] space-y-1.5" id="simMatchedSource">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i class="ri-file-excel-2-line text-emerald-500 text-sm"></i>
                                            <span class="font-mono font-semibold" id="simSourceName">Product_Catalog_Nov2026.xlsx</span>
                                        </div>
                                        <span class="font-mono text-3xs text-emerald-500 font-bold" id="simRowMatch">{{ __('public.home.sales_row_matched') }}</span>
                                    </div>
                                    <div class="p-1.5 rounded bg-light-canvas dark:bg-dark-canvas font-mono text-3xs text-light-muted dark:text-dark-muted truncate" id="simRowData">
                                        {{ __('public.home.sales_row_data') }}
                                    </div>
                                </div>

                                <div class="p-2 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] flex items-center justify-between text-3xs font-mono">
                                    <span class="flex items-center gap-1.5">
                                        <i class="ri-shield-check-line text-brand-500"></i>
                                        <span>{{ __('public.home.sales_column_memory') }}</span>
                                    </span>
                                    <span class="text-emerald-500 font-semibold" id="simConfidence">{{ __('public.home.sales_confidence') }}</span>
                                </div>

                                <div class="p-2.5 rounded-lg bg-light-surface/60 dark:bg-dark-surface/60 border border-dashed border-light-border dark:border-white/[0.08] text-3xs font-mono text-light-muted dark:text-dark-muted space-y-1">
                                    <div class="flex justify-between">
                                        <span>{{ __('public.home.sales_quick_stock_status') }}</span>
                                        <span class="text-emerald-500 font-semibold" id="simRoute">{{ __('public.home.sales_in_stock_badge') }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>{{ __('public.home.sales_token_overhead') }}</span>
                                        <span class="text-light-text dark:text-dark-text font-semibold">142 In / 48 Out</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Right: Telemetry & Micro Sparkline --}}
                        <div class="chamfer-card md:col-span-3 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2 mb-3">
                                    <div class="flex items-center gap-2 text-xs font-semibold">
                                        <i class="ri-bar-chart-2-line text-purple-500"></i>
                                        <span>{{ __('public.home.sales_metrics_title') }}</span>
                                    </div>
                                    <span class="text-3xs font-mono text-purple-500 font-semibold">Sub-Second</span>
                                </div>

                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted uppercase">
                                            <span>{{ __('public.home.sales_latency_label') }}</span>
                                            <span class="text-light-text dark:text-dark-text font-bold">180ms</span>
                                        </div>
                                        <svg class="w-full h-8 mt-1 text-brand-500" viewBox="0 0 100 25" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M0,18 L15,14 L30,16 L45,10 L60,12 L75,8 L90,9 L100,6" stroke-linecap="round"/>
                                        </svg>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted uppercase">
                                            <span>{{ __('public.home.sales_precision_label') }}</span>
                                            <span class="text-emerald-500 font-bold">100.0%</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-light-surface dark:bg-dark-surface rounded-full overflow-hidden mt-1.5">
                                            <div class="h-full bg-emerald-500 rounded-full" style="width: 100%"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted uppercase">
                                            <span>{{ __('public.home.sales_cost_label') }}</span>
                                            <span class="text-brand-500 font-bold font-mono">{{ app()->getLocale() === 'en' ? 'BDT 0.40' : '৳ ০.৪০' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <a href="{{ route('knowledge-engine') }}" class="text-center py-1.5 px-3 rounded-lg border border-light-border dark:border-white/[0.08] text-2xs font-semibold hover:border-brand-500/50 transition-colors">
                                {{ __('public.home.sales_view_engine_btn') }}
                            </a>
                        </div>
                    </div>

                    {{-- UI MODE: Tab 2 View: Operations & Courier Agent (Back Office) --}}
                    <div id="heroTabView-ops" class="hero-tab-content hidden grid grid-cols-1 md:grid-cols-12 gap-3.5">
                        {{-- Left: Inbound Order Extraction --}}
                        <div class="chamfer-card md:col-span-5 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3">
                            <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2">
                                <div class="flex items-center gap-2 text-xs font-semibold">
                                    <i class="ri-file-list-3-line text-amber-500"></i>
                                    <span>{{ __('public.home.ops_intake_title') }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-500 text-3xs font-mono font-bold">{{ __('public.home.ops_captured_badge') }}</span>
                            </div>
                            
                            <div class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] space-y-1.5 text-2xs">
                                <div class="text-3xs font-mono text-light-muted dark:text-dark-muted flex justify-between">
                                    <span>{{ __('public.home.ops_stream_title') }}</span>
                                    <span class="text-emerald-500 font-semibold">{{ __('public.home.ops_parsed_sla') }}</span>
                                </div>
                                <p class="text-light-text-secondary dark:text-dark-text-secondary text-2xs font-sans italic bg-light-canvas dark:bg-dark-canvas p-2 rounded">
                                    "{{ __('public.home.ops_customer_msg') }}"
                                </p>
                                <div class="grid grid-cols-2 gap-2 pt-1 font-mono text-3xs">
                                    <div class="p-1.5 rounded bg-light-canvas dark:bg-dark-canvas">
                                        <span class="text-light-muted dark:text-dark-muted block">{{ __('public.home.ops_customer_name') }}</span>
                                        <span class="font-bold text-light-text dark:text-dark-text">ফারহান আহমেদ</span>
                                    </div>
                                    <div class="p-1.5 rounded bg-light-canvas dark:bg-dark-canvas">
                                        <span class="text-light-muted dark:text-dark-muted block">{{ __('public.home.ops_phone_verified') }}</span>
                                        <span class="font-bold text-emerald-500 font-mono">01711234567</span>
                                    </div>
                                    <div class="col-span-2 p-1.5 rounded bg-light-canvas dark:bg-dark-canvas">
                                        <span class="text-light-muted dark:text-dark-muted block">{{ __('public.home.ops_delivery_address') }}</span>
                                        <span class="font-bold text-light-text dark:text-dark-text">বাড়ি ৪২, রোড ১১, বনানী, ঢাকা</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-2 rounded-lg bg-amber-500/10 border border-amber-500/20 text-3xs text-amber-600 dark:text-amber-400 flex items-center justify-between">
                                <span class="flex items-center gap-1.5"><i class="ri-time-line"></i> {{ __('public.home.ops_lead_recovery') }}</span>
                                <span class="font-bold font-mono">{{ __('public.home.ops_zero_abandoned') }}</span>
                            </div>
                        </div>

                        {{-- Center: Steadfast & Pathao Courier Auto-Dispatch --}}
                        <div class="chamfer-card md:col-span-4 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3">
                            <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2">
                                <div class="flex items-center gap-2 text-xs font-semibold">
                                    <i class="ri-truck-line text-brand-500"></i>
                                    <span>{{ __('public.home.ops_courier_title') }}</span>
                                </div>
                                <span class="text-3xs font-mono text-emerald-500 font-bold">{{ __('public.home.ops_steadfast_live') }}</span>
                            </div>

                            <div class="space-y-2 text-2xs font-mono">
                                <div class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] space-y-1">
                                    <div class="flex justify-between items-center">
                                        <span class="text-light-muted dark:text-dark-muted">{{ __('public.home.ops_consignment_id') }}</span>
                                        <span class="text-brand-500 font-bold">#SF-84920194</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-light-muted dark:text-dark-muted">{{ __('public.home.ops_courier_partner') }}</span>
                                        <span class="text-light-text dark:text-dark-text font-bold">Steadfast API v1</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-light-muted dark:text-dark-muted">{{ __('public.home.ops_cod_amount') }}</span>
                                        <span class="text-emerald-500 font-bold">{{ app()->getLocale() === 'en' ? 'BDT 980' : '৳ ৯৮০' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-light-muted dark:text-dark-muted">{{ __('public.home.ops_courier_status') }}</span>
                                        <span class="px-1.5 py-0.5 rounded text-3xs bg-emerald-500/15 text-emerald-500 font-bold">{{ __('public.home.ops_status_value') }}</span>
                                    </div>
                                </div>

                                <div class="p-2 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] text-3xs space-y-1 text-light-muted dark:text-dark-muted">
                                    <div class="flex justify-between">
                                        <span>{{ __('public.home.ops_webhook_sync') }}</span>
                                        <span class="text-emerald-500 font-semibold">{{ __('public.home.ops_sync_value') }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>{{ __('public.home.ops_manual_typing') }}</span>
                                        <span class="text-rose-500 font-bold line-through">5 Minutes</span>
                                        <span class="text-emerald-500 font-bold">{{ __('public.home.ops_typing_zero') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Right: Operations Metrics --}}
                        <div class="chamfer-card md:col-span-3 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2 mb-3">
                                    <div class="flex items-center gap-2 text-xs font-semibold">
                                        <i class="ri-dashboard-line text-amber-500"></i>
                                        <span>{{ __('public.home.ops_fulfillment_title') }}</span>
                                    </div>
                                    <span class="text-3xs font-mono text-amber-500 font-semibold">Automated</span>
                                </div>

                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted uppercase">
                                            <span>{{ __('public.home.ops_dispatch_label') }}</span>
                                            <span class="text-light-text dark:text-dark-text font-bold">0.4 sec</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-light-surface dark:bg-dark-surface rounded-full overflow-hidden mt-1.5">
                                            <div class="h-full bg-amber-500 rounded-full" style="width: 96%"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted uppercase">
                                            <span>{{ __('public.home.ops_webhook_sla') }}</span>
                                            <span class="text-emerald-500 font-bold">&lt; 50ms</span>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted uppercase">
                                            <span>{{ __('public.home.ops_supported_couriers') }}</span>
                                            <span class="text-brand-500 font-bold font-mono">Steadfast + Pathao</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <a href="{{ route('automation') }}" class="text-center py-1.5 px-3 rounded-lg border border-light-border dark:border-white/[0.08] text-2xs font-semibold hover:border-brand-500/50 transition-colors">
                                {{ __('public.home.ops_explore_btn') }}
                            </a>
                        </div>
                    </div>

                    {{-- UI MODE: Tab 3 View: Business Owner Agent / Virtual CFO --}}
                    <div id="heroTabView-cfo" class="hero-tab-content hidden grid grid-cols-1 md:grid-cols-12 gap-3.5">
                        {{-- Left: Private Owner Dialogue --}}
                        <div class="chamfer-card md:col-span-5 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3">
                            <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2">
                                <div class="flex items-center gap-2 text-xs font-semibold">
                                    <i class="ri-user-star-line text-purple-500"></i>
                                    <span>{{ __('public.home.cfo_portal_title') }}</span>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-purple-500/15 text-purple-500 text-3xs font-mono font-bold">{{ __('public.home.cfo_exec_badge') }}</span>
                            </div>

                            <div class="space-y-2.5 text-xs">
                                <div class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] space-y-1">
                                    <div class="flex items-center justify-between text-3xs text-light-muted dark:text-dark-muted font-mono">
                                        <span class="font-semibold text-light-text dark:text-dark-text">{{ __('public.home.cfo_user_label') }}</span>
                                        <span>{{ __('public.home.cfo_session_label') }}</span>
                                    </div>
                                    <p class="text-light-text-secondary dark:text-dark-text-secondary text-2xs">
                                        "{{ __('public.home.cfo_sim_q') }}"
                                    </p>
                                </div>

                                <div class="p-2.5 rounded-lg bg-purple-500/10 border border-purple-500/20 space-y-1.5">
                                    <div class="flex items-center justify-between text-3xs text-purple-500 font-semibold">
                                        <span class="flex items-center gap-1"><i class="ri-sparkling-fill"></i> {{ __('public.home.cfo_ai_label') }}</span>
                                        <span class="font-mono text-3xs">{{ __('public.home.cfo_cogs_badge') }}</span>
                                    </div>
                                    <p class="text-light-text dark:text-dark-text text-2xs leading-relaxed">
                                        {!! __('public.home.cfo_sim_a') !!}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Center: Financial P&L Breakdown --}}
                        <div class="chamfer-card md:col-span-4 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3">
                            <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2">
                                <div class="flex items-center gap-2 text-xs font-semibold">
                                    <i class="ri-funds-line text-emerald-500"></i>
                                    <span>{{ __('public.home.cfo_profit_title') }}</span>
                                </div>
                                <span class="text-3xs font-mono text-emerald-500 font-bold">{{ __('public.home.cfo_actuals_badge') }}</span>
                            </div>

                            <div class="space-y-2 text-2xs font-mono">
                                <div class="p-2 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] flex justify-between">
                                    <span class="text-light-muted dark:text-dark-muted">{{ __('public.home.cfo_gross_revenue') }}</span>
                                    <span class="font-bold text-light-text dark:text-dark-text">{{ app()->getLocale() === 'en' ? 'BDT 184,200' : '৳ ১৮৪,২০০' }}</span>
                                </div>
                                <div class="p-2 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] flex justify-between">
                                    <span class="text-light-muted dark:text-dark-muted">{{ __('public.home.cfo_cogs') }}</span>
                                    <span class="font-bold text-rose-500">{{ app()->getLocale() === 'en' ? '- BDT 112,400' : '- ৳ ১১২,৪০০' }}</span>
                                </div>
                                <div class="p-2 rounded bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] flex justify-between">
                                    <span class="text-light-muted dark:text-dark-muted">{{ __('public.home.cfo_logistics') }}</span>
                                    <span class="font-bold text-amber-500">{{ app()->getLocale() === 'en' ? '- BDT 16,540' : '- ৳ ১৬,৫৪০' }}</span>
                                </div>
                                <div class="p-2 rounded bg-emerald-500/15 border border-emerald-500/30 flex justify-between font-bold">
                                    <span class="text-emerald-600 dark:text-emerald-400">{{ __('public.home.cfo_net_margin') }}</span>
                                    <span class="text-emerald-600 dark:text-emerald-400">{{ app()->getLocale() === 'en' ? '38.6% (BDT 55,260)' : '৩৮.৬% (৳ ৫৫,২৬০)' }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Right: Inventory Alerts --}}
                        <div class="chamfer-card md:col-span-3 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2 mb-2">
                                    <div class="flex items-center gap-2 text-xs font-semibold">
                                        <i class="ri-alert-line text-rose-500"></i>
                                        <span>{{ __('public.home.cfo_inventory_title') }}</span>
                                    </div>
                                    <span class="text-3xs font-mono text-rose-500 font-semibold">Live Alert</span>
                                </div>

                                <div class="space-y-2 text-2xs">
                                    <div class="p-2 rounded-lg bg-rose-500/10 border border-rose-500/20">
                                        <div class="text-3xs font-bold text-rose-500 uppercase font-mono">{{ __('public.home.cfo_stockout_alert') }}</div>
                                        <div class="text-light-text dark:text-dark-text font-semibold">{{ __('public.home.cfo_stockout_item') }}</div>
                                    </div>
                                    <div class="p-2 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                                        <div class="text-3xs font-bold text-emerald-500 uppercase font-mono">{{ __('public.home.cfo_velocity_alert') }}</div>
                                        <div class="text-light-text dark:text-dark-text font-semibold">{{ __('public.home.cfo_velocity_item') }}</div>
                                    </div>
                                    <div class="p-2 rounded-lg bg-amber-500/10 border border-amber-500/20">
                                        <div class="text-3xs font-bold text-amber-500 uppercase font-mono">{{ __('public.home.cfo_deadstock_alert') }}</div>
                                        <div class="text-light-text dark:text-dark-text font-semibold">{{ __('public.home.cfo_deadstock_item') }}</div>
                                    </div>
                                </div>
                            </div>

                            <a href="{{ route('business-intelligence') }}" class="text-center py-1.5 px-3 rounded-lg border border-light-border dark:border-white/[0.08] text-2xs font-semibold hover:border-purple-500/50 transition-colors">
                                {{ __('public.home.cfo_view_docs_btn') }}
                            </a>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </section>

    {{-- ==================== 2. INTEGRATION ECOSYSTEM STRIP ==================== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="chamfer-card p-6 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface/40 dark:bg-dark-surface/40 backdrop-blur-sm space-y-5 text-center">
            <div class="text-2xs uppercase tracking-wider font-semibold text-light-muted dark:text-dark-muted font-mono">
                Engineered for Modern Commerce Ecosystems
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                {{-- 1. Facebook Messenger (Available) --}}
                <div class="chamfer-card p-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface flex flex-col items-center gap-1.5">
                    <i class="ri-facebook-circle-fill text-blue-500 text-xl"></i>
                    <span class="text-xs font-semibold">Messenger</span>
                    <span class="px-1.5 py-0.2 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">Available</span>
                </div>

                {{-- 2. Web Chat Widget (Available) --}}
                <div class="chamfer-card p-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface flex flex-col items-center gap-1.5">
                    <i class="ri-global-line text-brand-500 text-xl"></i>
                    <span class="text-xs font-semibold">Web Widget</span>
                    <span class="px-1.5 py-0.2 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">Available</span>
                </div>

                {{-- 3. REST API & Webhooks (Available) --}}
                <div class="chamfer-card p-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface flex flex-col items-center gap-1.5">
                    <i class="ri-code-s-slash-line text-purple-500 text-xl"></i>
                    <span class="text-xs font-semibold">REST API</span>
                    <span class="px-1.5 py-0.2 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">Available</span>
                </div>

                {{-- 4. WhatsApp Business (Available) --}}
                <div class="chamfer-card p-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface flex flex-col items-center gap-1.5">
                    <i class="ri-whatsapp-fill text-emerald-500 text-xl"></i>
                    <span class="text-xs font-semibold">WhatsApp</span>
                    <span class="px-1.5 py-0.2 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">Available</span>
                </div>

                {{-- 5. Telegram Bot (Available) --}}
                <div class="chamfer-card p-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface flex flex-col items-center gap-1.5">
                    <i class="ri-telegram-fill text-sky-400 text-xl"></i>
                    <span class="text-xs font-semibold">Telegram</span>
                    <span class="px-1.5 py-0.2 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">Available</span>
                </div>

                {{-- 6. TikTok DMs (Available) --}}
                <div class="chamfer-card p-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface flex flex-col items-center gap-1.5">
                    <i class="ri-tiktok-fill text-pink-500 text-xl"></i>
                    <span class="text-xs font-semibold">TikTok</span>
                    <span class="px-1.5 py-0.2 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">Available</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 3. PLATFORM CORE PILLARS (THE 3 SPECIALIZED AI EMPLOYEES) ==================== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-3 max-w-2xl mx-auto">
            <h2 class="text-2xs font-bold uppercase tracking-wider text-brand-500 font-mono">{{ __('public.home.pillars_badge') }}</h2>
            <p class="text-2xl sm:text-3xl font-extrabold text-light-text dark:text-dark-text tracking-tight">
                {{ __('public.home.pillars_title') }}
            </p>
            <p class="text-xs sm:text-sm text-light-text-secondary dark:text-dark-text-secondary">
                {{ __('public.home.pillars_desc') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            {{-- Pillar 1: Customer Sales Agent --}}
            <a href="{{ route('unified-inbox') }}" class="chamfer-card spotlight-card group p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-brand-500/50 hover:bg-light-elevated dark:hover:bg-dark-elevated transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-lg font-bold group-hover:scale-105 transition-transform">
                        <i class="ri-customer-service-2-line"></i>
                    </div>
                    <div class="space-y-1">
                        <span class="text-3xs font-mono font-bold uppercase text-brand-500">{{ __('public.home.pillar_sales_tag') }}</span>
                        <h3 class="font-bold text-sm text-light-text dark:text-dark-text group-hover:text-brand-500 transition-colors">
                            {{ __('public.home.pillar_sales_title') }}
                        </h3>
                    </div>
                    <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.home.pillar_sales_desc') }}
                    </p>
                </div>
                <div class="flex items-center gap-1 text-3xs font-semibold text-brand-500">
                    <span>{{ __('public.home.pillar_sales_btn') }}</span>
                    <i class="ri-arrow-right-line group-hover:translate-x-1 transition-transform"></i>
                </div>
            </a>

            {{-- Pillar 2: Operations & Courier Agent --}}
            <a href="{{ route('automation') }}" class="chamfer-card spotlight-card group p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-amber-500/50 hover:bg-light-elevated dark:hover:bg-dark-elevated transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-lg font-bold group-hover:scale-105 transition-transform">
                        <i class="ri-truck-line"></i>
                    </div>
                    <div class="space-y-1">
                        <span class="text-3xs font-mono font-bold uppercase text-amber-500">{{ __('public.home.pillar_ops_tag') }}</span>
                        <h3 class="font-bold text-sm text-light-text dark:text-dark-text group-hover:text-amber-500 transition-colors">
                            {{ __('public.home.pillar_ops_title') }}
                        </h3>
                    </div>
                    <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.home.pillar_ops_desc') }}
                    </p>
                </div>
                <div class="flex items-center gap-1 text-3xs font-semibold text-amber-500">
                    <span>{{ __('public.home.pillar_ops_btn') }}</span>
                    <i class="ri-arrow-right-line group-hover:translate-x-1 transition-transform"></i>
                </div>
            </a>

            {{-- Pillar 3: Business Owner Agent / Virtual CFO --}}
            <a href="{{ route('business-intelligence') }}" class="chamfer-card spotlight-card group p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-purple-500/50 hover:bg-light-elevated dark:hover:bg-dark-elevated transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-lg font-bold group-hover:scale-105 transition-transform">
                        <i class="ri-funds-line"></i>
                    </div>
                    <div class="space-y-1">
                        <span class="text-3xs font-mono font-bold uppercase text-purple-500">{{ __('public.home.pillar_cfo_tag') }}</span>
                        <h3 class="font-bold text-sm text-light-text dark:text-dark-text group-hover:text-purple-500 transition-colors">
                            {{ __('public.home.pillar_cfo_title') }}
                        </h3>
                    </div>
                    <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.home.pillar_cfo_desc') }}
                    </p>
                </div>
                <div class="flex items-center gap-1 text-3xs font-semibold text-purple-500">
                    <span>{{ __('public.home.pillar_cfo_btn') }}</span>
                    <i class="ri-arrow-right-line group-hover:translate-x-1 transition-transform"></i>
                </div>
            </a>

            {{-- Pillar 4: Dynamic Spreadsheet Grounding & Quick Stock Toggle --}}
            <a href="{{ route('knowledge-engine') }}" class="chamfer-card spotlight-card group p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-emerald-500/50 hover:bg-light-elevated dark:hover:bg-dark-elevated transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-lg font-bold group-hover:scale-105 transition-transform">
                        <i class="ri-file-excel-2-line"></i>
                    </div>
                    <div class="space-y-1">
                        <span class="text-3xs font-mono font-bold uppercase text-emerald-500">{{ __('public.home.pillar_core_tag') }}</span>
                        <h3 class="font-bold text-sm text-light-text dark:text-dark-text group-hover:text-emerald-500 transition-colors">
                            {{ __('public.home.pillar_core_title') }}
                        </h3>
                    </div>
                    <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        {{ __('public.home.pillar_core_desc') }}
                    </p>
                </div>
                <div class="flex items-center gap-1 text-3xs font-semibold text-emerald-500">
                    <span>{{ __('public.home.pillar_core_btn') }}</span>
                    <i class="ri-arrow-right-line group-hover:translate-x-1 transition-transform"></i>
                </div>
            </a>

        </div>
    </section>

    {{-- ==================== 4. REALISTIC SPREADSHEET GRID & CONNECTED FLOW ==================== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="chamfer-card rounded-2xl border border-light-border dark:border-white/[0.1] bg-light-surface/90 dark:bg-dark-surface/90 p-6 sm:p-10 space-y-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                
                {{-- Left Text Info --}}
                <div class="lg:col-span-5 space-y-4">
                    <span class="text-3xs font-mono font-bold uppercase tracking-wider text-brand-500">{{ __('public.home.sheet_badge') }}</span>
                    <h3 class="text-xl sm:text-2xl font-bold text-light-text dark:text-dark-text tracking-tight">
                        {{ __('public.home.sheet_title') }}
                    </h3>
                    <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                        {{ __('public.home.sheet_desc') }}
                    </p>
                    <div class="space-y-2 pt-2">
                        <div class="flex items-center gap-2 text-xs">
                            <i class="ri-checkbox-circle-fill text-emerald-500"></i>
                            <span>{{ __('public.home.sheet_point_1') }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <i class="ri-checkbox-circle-fill text-emerald-500"></i>
                            <span>{{ __('public.home.sheet_point_2') }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <i class="ri-checkbox-circle-fill text-emerald-500"></i>
                            <span>{{ __('public.home.sheet_point_3') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Right: REALISTIC SPREADSHEET TABLE GRID VISUALIZATION --}}
                <div class="lg:col-span-7 p-4 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3 font-mono text-2xs overflow-hidden">
                    
                    {{-- Sheet Tabs Bar --}}
                    <div class="flex items-center justify-between pb-2 border-b border-light-border dark:border-white/[0.08]">
                        <div class="flex items-center gap-1.5 text-3xs">
                            <span class="px-2.5 py-1 rounded-md bg-emerald-500/15 text-emerald-500 font-bold flex items-center gap-1 border border-emerald-500/30">
                                <i class="ri-file-excel-2-line"></i> Sheet1: Inventory (Active)
                            </span>
                            <span class="px-2 py-1 rounded-md bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted hidden sm:inline">
                                Sheet2: Warranties
                            </span>
                            <span class="px-2 py-1 rounded-md bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted hidden md:inline">
                                Sheet3: Hub_Locations
                            </span>
                        </div>
                        <span class="text-3xs font-mono text-emerald-500 font-bold flex items-center gap-1">
                            <i class="ri-check-line"></i> Live Schema Mapped
                        </span>
                    </div>

                    {{-- Authentic Spreadsheet Table Grid with Scanned Row Highlight --}}
                    <div class="overflow-x-auto rounded-lg border border-light-border dark:border-white/[0.08]">
                        <table class="w-full text-left text-3xs font-mono">
                            <thead class="bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted border-b border-light-border dark:border-white/[0.08]">
                                <tr>
                                    <th class="p-2 w-8 text-center bg-light-elevated/40 dark:bg-dark-elevated/40">#</th>
                                    <th class="p-2">A: SKU</th>
                                    <th class="p-2">B: PRODUCT_NAME</th>
                                    <th class="p-2">C: PRICE_BDT</th>
                                    <th class="p-2">D: STOCK</th>
                                    <th class="p-2">E: HUB</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-light-border/40 dark:divide-white/[0.04] text-light-text dark:text-slate-300">
                                <tr class="hover:bg-light-surface dark:hover:bg-dark-surface/60">
                                    <td class="p-2 text-center text-light-muted dark:text-dark-muted bg-light-elevated/20 dark:bg-dark-elevated/20">146</td>
                                    <td class="p-2">IP15P-128-BK</td>
                                    <td class="p-2 font-sans">iPhone 15 Pro 128GB Black</td>
                                    <td class="p-2">148,000</td>
                                    <td class="p-2 text-rose-500 font-bold">0 (Out)</td>
                                    <td class="p-2">Dhanmondi</td>
                                </tr>
                                <tr class="hover:bg-light-surface dark:hover:bg-dark-surface/60">
                                    <td class="p-2 text-center text-light-muted dark:text-dark-muted bg-light-elevated/20 dark:bg-dark-elevated/20">147</td>
                                    <td class="p-2">IP15P-256-BL</td>
                                    <td class="p-2 font-sans">iPhone 15 Pro 256GB Blue</td>
                                    <td class="p-2">162,000</td>
                                    <td class="p-2 text-amber-500 font-bold">2</td>
                                    <td class="p-2">Banani Hub</td>
                                </tr>
                                {{-- Target Scanned Row Highlighted in Neon Green --}}
                                <tr class="bg-emerald-500/15 border-y-2 border-emerald-500 font-bold text-light-text dark:text-white">
                                    <td class="p-2 text-center text-emerald-500 bg-emerald-500/20">148</td>
                                    <td class="p-2 text-emerald-500">IP15P-256-NT</td>
                                    <td class="p-2 font-sans flex items-center gap-1.5">
                                        <i class="ri-focus-3-line text-emerald-500 animate-spin" style="animation-duration: 4s;"></i>
                                        <span>iPhone 15 Pro Max 256GB Titanium</span>
                                    </td>
                                    <td class="p-2 text-emerald-500">162,000</td>
                                    <td class="p-2 text-emerald-500">4 Available</td>
                                    <td class="p-2 text-emerald-500">Gulshan Hub</td>
                                </tr>
                                <tr class="hover:bg-light-surface dark:hover:bg-dark-surface/60">
                                    <td class="p-2 text-center text-light-muted dark:text-dark-muted bg-light-elevated/20 dark:bg-dark-elevated/20">149</td>
                                    <td class="p-2">IP15P-512-NT</td>
                                    <td class="p-2 font-sans">iPhone 15 Pro 512GB Titanium</td>
                                    <td class="p-2">189,000</td>
                                    <td class="p-2">6</td>
                                    <td class="p-2">Gulshan Hub</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Grounding Scan Confirmation Footer --}}
                    <div class="p-2.5 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-between text-3xs font-mono text-emerald-600 dark:text-emerald-400">
                        <span class="flex items-center gap-1.5">
                            <i class="ri-checkbox-circle-fill"></i>
                            <span>Semantic Match: Inbound query resolved against Row #148 (Score 0.984)</span>
                        </span>
                        <span class="font-bold">Grounded Match</span>
                    </div>

                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 5. INTERACTIVE OPERATIONAL ROI & EFFICIENCY CALCULATOR ==================== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
        <div class="text-center space-y-3 max-w-2xl mx-auto">
            <h2 class="text-2xs font-bold uppercase tracking-wider text-brand-500 font-mono">{{ __('public.home.roi_badge') }}</h2>
            <p class="text-2xl sm:text-3xl font-extrabold text-light-text dark:text-dark-text tracking-tight">
                {{ __('public.home.roi_title') }}
            </p>
            <p class="text-xs sm:text-sm text-light-text-secondary dark:text-dark-text-secondary">
                {{ __('public.home.roi_desc') }}
            </p>
        </div>

        <div class="chamfer-card p-6 sm:p-10 rounded-2xl border border-light-border dark:border-white/[0.1] bg-light-surface/90 dark:bg-dark-surface/90 backdrop-blur-md max-w-4xl mx-auto">
            <div class="space-y-6">
                {{-- Slider Control --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs font-semibold">
                        <label for="monthlyMessagesRange" class="text-light-text dark:text-dark-text">{{ __('public.home.roi_monthly_msgs') }}</label>
                        <span class="px-3 py-1 rounded-lg bg-brand-500/10 text-brand-500 font-mono font-bold text-sm" id="messagesDisplay">
                            5,000 / month
                        </span>
                    </div>
                    <input 
                        type="range" 
                        id="monthlyMessagesRange" 
                        min="1000" 
                        max="50000" 
                        step="500" 
                        value="5000" 
                        class="w-full h-2 bg-light-canvas dark:bg-dark-canvas rounded-lg appearance-none cursor-pointer accent-brand-600"
                        oninput="updateRoiCalculation(this.value)"
                    >
                    <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted">
                        <span>1,000 msgs</span>
                        <span>25,000 msgs</span>
                        <span>50,000+ msgs</span>
                    </div>
                </div>

                {{-- Calculated Metrics Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-light-border dark:border-white/[0.08] text-center">
                    <div class="chamfer-card p-3.5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08]">
                        <div class="text-3xs font-mono uppercase text-light-muted dark:text-dark-muted">{{ __('public.home.roi_hours_saved') }}</div>
                        <div class="text-xl sm:text-2xl font-bold font-mono text-brand-500 mt-1" id="hoursSaved">415 hrs</div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">{{ __('public.home.roi_per_month') }}</div>
                    </div>

                    <div class="chamfer-card p-3.5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08]">
                        <div class="text-3xs font-mono uppercase text-light-muted dark:text-dark-muted">{{ __('public.home.roi_auto_rate') }}</div>
                        <div class="text-xl sm:text-2xl font-bold font-mono text-emerald-500 mt-1">82%</div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">{{ __('public.home.roi_without_rep') }}</div>
                    </div>

                    <div class="chamfer-card p-3.5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08]">
                        <div class="text-3xs font-mono uppercase text-light-muted dark:text-dark-muted">{{ __('public.home.roi_first_response') }}</div>
                        <div class="text-xl sm:text-2xl font-bold font-mono text-sky-500 mt-1">0.38s</div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">{{ __('public.home.roi_vs_manual') }}</div>
                    </div>

                    <div class="chamfer-card p-3.5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08]">
                        <div class="text-3xs font-mono uppercase text-light-muted dark:text-dark-muted">{{ __('public.home.roi_est_savings') }}</div>
                        <div class="text-xl sm:text-2xl font-bold font-mono text-teal-400 mt-1" id="costSavings">$2,250</div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">{{ __('public.home.roi_support_overhead') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 6. ENTERPRISE SECURITY & TRUST ANCHORS ==================== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-3 max-w-2xl mx-auto">
            <h2 class="text-2xs font-bold uppercase tracking-wider text-brand-500 font-mono">{{ __('public.home.sec_badge') }}</h2>
            <p class="text-2xl sm:text-3xl font-extrabold text-light-text dark:text-dark-text tracking-tight">
                {{ __('public.home.sec_title') }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="chamfer-card spotlight-card p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-building-4-line text-brand-500 text-2xl"></i>
                <h3 class="font-bold text-sm text-light-text dark:text-dark-text">{{ __('public.home.sec_c1_title') }}</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.sec_c1_desc') }}
                </p>
            </div>

            <div class="chamfer-card spotlight-card p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-shield-user-line text-sky-500 text-2xl"></i>
                <h3 class="font-bold text-sm text-light-text dark:text-dark-text">{{ __('public.home.sec_c2_title') }}</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.sec_c2_desc') }}
                </p>
            </div>

            <div class="chamfer-card spotlight-card p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-lock-password-line text-emerald-500 text-2xl"></i>
                <h3 class="font-bold text-sm text-light-text dark:text-dark-text">{{ __('public.home.sec_c3_title') }}</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.sec_c3_desc') }}
                </p>
            </div>

            <div class="chamfer-card spotlight-card p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-forbid-2-line text-purple-500 text-2xl"></i>
                <h3 class="font-bold text-sm text-light-text dark:text-dark-text">{{ __('public.home.sec_c4_title') }}</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.sec_c4_desc') }}
                </p>
            </div>
        </div>
    </section>

    {{-- ==================== 6.5 PREPAID METERED PRICING ==================== --}}
    @php
        $mRate = isset($meteredPlan) ? (float)$meteredPlan->price_monthly : 0.40;
        $sBonus = isset($starterPlan) ? (float)$starterPlan->price_monthly : 50.00;
        $sMsgs = isset($starterPlan) && $starterPlan->max_conversations_monthly > 0 
            ? (int)$starterPlan->max_conversations_monthly 
            : (int)floor($sBonus / max(0.01, $mRate));
    @endphp
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
        <div class="text-center space-y-3 max-w-2xl mx-auto">
            <span class="text-3xs font-mono font-bold uppercase tracking-wider text-brand-500">{{ __('public.home.prepaid_badge') }}</span>
            <h2 class="text-2xl sm:text-3xl font-bold text-light-text dark:text-dark-text tracking-tight">
                {{ __('public.home.prepaid_title', ['rate' => number_format($mRate, 2)]) }}
            </h2>
            <p class="text-xs sm:text-sm text-light-text-secondary dark:text-dark-text-secondary">
                {!! __('public.home.prepaid_desc', ['bonus' => number_format($sBonus, 2), 'msgs' => number_format($sMsgs)]) !!}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Feature 1: Metered Rate --}}
            <div class="chamfer-card spotlight-card p-6 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-4">
                <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-xl">
                    <i class="ri-wallet-3-line"></i>
                </div>
                <div>
                    <div class="text-2xs uppercase tracking-wider font-semibold text-brand-500 font-mono">{{ __('public.home.prepaid_c1_badge') }}</div>
                    <h3 class="text-xl font-black text-light-text dark:text-dark-text mt-0.5">{{ __('public.home.prepaid_c1_title', ['rate' => number_format($mRate, 2)]) }} <span class="text-xs font-normal text-light-muted dark:text-dark-muted">{{ __('public.home.prepaid_c1_sub') }}</span></h3>
                </div>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.prepaid_c1_desc') }}
                </p>
                <ul class="text-2xs space-y-1.5 text-light-text dark:text-dark-text pt-2 border-t border-light-border dark:border-white/[0.08]">
                    <li class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500"></i> {{ app()->getLocale() === 'en' ? 'Fixed BDT ' . number_format($mRate, 2) . ' per response' : 'ফিক্সড প্রতি রেসপন্সে মাত্র ৳ ' . number_format($mRate, 2) }}</li>
                    <li class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500"></i> Real-time wallet balance deduction</li>
                    <li class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500"></i> No minimum monthly spend</li>
                </ul>
            </div>

            {{-- Feature 2: Starter Trial --}}
            <div class="chamfer-card spotlight-card p-6 rounded-2xl border-2 border-brand-500 bg-brand-500/5 dark:bg-brand-500/10 space-y-4 relative shadow-sm">
                <div class="absolute -top-3 right-4 px-2.5 py-0.5 rounded-full bg-brand-500 text-white text-3xs font-bold uppercase tracking-wider">
                    Instant Bonus
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl">
                    <i class="ri-gift-line"></i>
                </div>
                <div>
                    <div class="text-2xs uppercase tracking-wider font-semibold text-emerald-500 font-mono">{{ __('public.home.prepaid_c2_badge') }}</div>
                    <h3 class="text-xl font-black text-light-text dark:text-dark-text mt-0.5">{{ __('public.home.prepaid_c2_title', ['bonus' => number_format($sBonus, 2)]) }} <span class="text-xs font-normal text-light-muted dark:text-dark-muted">{{ __('public.home.prepaid_c2_sub') }}</span></h3>
                </div>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.prepaid_c2_desc', ['bonus' => number_format($sBonus, 2), 'msgs' => number_format($sMsgs)]) }}
                </p>
                <ul class="text-2xs space-y-1.5 text-light-text dark:text-dark-text pt-2 border-t border-light-border dark:border-white/[0.08]">
                    <li class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500"></i> {{ number_format($sMsgs) }} Free AI responses</li>
                    <li class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500"></i> No credit card required to sign up</li>
                    <li class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500"></i> Immediate workspace activation</li>
                </ul>
            </div>

            {{-- Feature 3: Recharge Options --}}
            <div class="chamfer-card spotlight-card p-6 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-4">
                <div class="w-10 h-10 rounded-xl bg-pink-500/10 text-pink-500 flex items-center justify-center text-xl">
                    <i class="ri-smartphone-line"></i>
                </div>
                <div>
                    <div class="text-2xs uppercase tracking-wider font-semibold text-pink-500 font-mono">{{ __('public.home.prepaid_c3_badge') }}</div>
                    <h3 class="text-xl font-black text-light-text dark:text-dark-text mt-0.5">{{ __('public.home.prepaid_c3_title') }}</h3>
                </div>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.prepaid_c3_desc') }}
                </p>
                <ul class="text-2xs space-y-1.5 text-light-text dark:text-dark-text pt-2 border-t border-light-border dark:border-white/[0.08]">
                    <li class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500"></i> bKash & Nagad support</li>
                    <li class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500"></i> {{ app()->getLocale() === 'en' ? 'Quick top-up from BDT 500 to BDT 5,000' : '৳ ৫০০ থেকে ৳ ৫,০০০ পর্যন্ত সহজ টপ-আপ' }}</li>
                    <li class="flex items-center gap-1.5"><i class="ri-check-line text-emerald-500"></i> Automated Order & Lead capture included</li>
                </ul>
            </div>
        </div>

        <div class="text-center pt-2">
            <a href="{{ route('register') }}" class="btn-shimmer inline-flex items-center gap-2 px-8 py-3.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-bold text-xs shadow-sm hover:shadow transition-all">
                <span>{{ __('public.home.start_free_btn', ['bonus' => number_format($sBonus, 0)]) }}</span>
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>
    </section>

    {{-- ==================== 7. FREQUENTLY ASKED QUESTIONS ==================== --}}
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div class="text-center space-y-2">
            <span class="text-3xs font-mono font-bold uppercase tracking-wider text-brand-500">{{ __('public.home.faq_badge') }}</span>
            <h2 class="text-2xl font-bold text-light-text dark:text-dark-text">{{ __('public.home.faq_title') }}</h2>
        </div>

        <div class="space-y-3" id="faqAccordion">
            <div class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                <button type="button" class="w-full flex items-center justify-between text-left text-xs font-semibold text-light-text dark:text-dark-text" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('i').classList.toggle('rotate-180');">
                    <span>{{ __('public.home.faq_q1') }}</span>
                    <i class="ri-arrow-down-s-line text-sm transition-transform"></i>
                </button>
                <div class="pt-2 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.faq_a1') }}
                </div>
            </div>

            <div class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                <button type="button" class="w-full flex items-center justify-between text-left text-xs font-semibold text-light-text dark:text-dark-text" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('i').classList.toggle('rotate-180');">
                    <span>{{ __('public.home.faq_q2') }}</span>
                    <i class="ri-arrow-down-s-line text-sm transition-transform"></i>
                </button>
                <div class="pt-2 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.faq_a2') }}
                </div>
            </div>

            <div class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                <button type="button" class="w-full flex items-center justify-between text-left text-xs font-semibold text-light-text dark:text-dark-text" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('i').classList.toggle('rotate-180');">
                    <span>{{ __('public.home.faq_q3') }}</span>
                    <i class="ri-arrow-down-s-line text-sm transition-transform"></i>
                </button>
                <div class="pt-2 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    {{ __('public.home.faq_a3') }}
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 8. FINAL CONVERSION CTA ==================== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="chamfer-card relative rounded-3xl border border-brand-500/30 bg-gradient-to-b from-brand-500/15 via-light-surface to-light-surface dark:via-dark-surface dark:to-dark-surface p-8 sm:p-14 text-center space-y-6 overflow-hidden shadow-sm dark:shadow-[0_8px_30px_rgb(0,0,0,0.3)]">
            <div class="max-w-2xl mx-auto space-y-3">
                <h2 class="text-2xl sm:text-4xl font-extrabold text-light-text dark:text-dark-text tracking-tight">
                    {{ __('public.home.cta_title') }}
                </h2>
                <p class="text-xs sm:text-sm text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                    {{ __('public.home.cta_desc') }}
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                <a href="{{ route('register') }}" class="btn-shimmer w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
                    <span>{{ __('public.home.cta_get_started') }}</span>
                    <i class="ri-arrow-right-line"></i>
                </a>
                <a href="{{ route('contact') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:bg-light-elevated dark:hover:bg-dark-elevated text-light-text dark:text-dark-text font-semibold text-xs shadow-xs transition-colors">
                    <span>{{ __('public.home.cta_contact_sales') }}</span>
                </a>
            </div>
        </div>
    </section>

</div>

@push('custom-styles')
<style>
    /* Linear/Vercel Mouse-Tracking Spotlight Glow */
    .spotlight-card {
        position: relative;
    }
    .spotlight-card::before {
        content: '';
        position: absolute;
        inset: 0;
        border-radius: inherit;
        padding: 1px;
        background: radial-gradient(350px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(2, 132, 199, 0.45), transparent 60%);
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: xor;
        mask-composite: exclude;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .spotlight-card:hover::before {
        opacity: 1;
    }
</style>
@endpush

@push('custom-scripts')
<script>
    // Tab switching for 3-Agent Showcase
    function switchHeroTab(tabName) {
        // Backwards compatibility mappings
        if (tabName === 'grounding') tabName = 'sales';
        if (tabName === 'inbox') tabName = 'ops';
        if (tabName === 'telemetry') tabName = 'cfo';

        document.querySelectorAll('.hero-tab-content').forEach(function(el) {
            el.classList.add('hidden');
        });
        document.querySelectorAll('.hero-tab-btn').forEach(function(btn) {
            btn.classList.remove('bg-brand-600', 'text-white', 'shadow-xs');
            btn.classList.add('text-light-muted', 'dark:text-dark-muted');
        });

        var targetView = document.getElementById('heroTabView-' + tabName);
        var targetBtn = document.getElementById('tabBtn-' + tabName);
        if (targetView) targetView.classList.remove('hidden');
        if (targetBtn) {
            targetBtn.classList.add('bg-brand-600', 'text-white', 'shadow-xs');
            targetBtn.classList.remove('text-light-muted', 'dark:text-dark-muted');
        }
    }

    // Simulation queries for Sandbox (dynamically localized)
    var simulationData = {
        sales: {
            tab: 'sales',
            q: @json(__('public.home.sales_sim_q')),
            a: @json(__('public.home.sales_sim_a')),
            src: "Product_Catalog_Nov2026.xlsx",
            row: @json(__('public.home.sales_row_matched')),
            rowText: @json(__('public.home.sales_row_data')),
            latency: "180ms",
            score: @json(__('public.home.sales_confidence')),
            route: @json(__('public.home.sales_in_stock_badge'))
        },
        stock: {
            tab: 'sales',
            q: @json(__('public.home.sales_sim_q')),
            a: @json(__('public.home.sales_sim_a')),
            src: "Product_Catalog_Nov2026.xlsx",
            row: @json(__('public.home.sales_row_matched')),
            rowText: @json(__('public.home.sales_row_data')),
            latency: "180ms",
            score: @json(__('public.home.sales_confidence')),
            route: @json(__('public.home.sales_in_stock_badge'))
        },
        ops: {
            tab: 'ops'
        },
        courier: {
            tab: 'ops'
        },
        cfo: {
            tab: 'cfo'
        }
    };

    function runSimulationQuery(type) {
        var data = simulationData[type];
        if (!data) return;

        switchHeroTab(data.tab);

        if (data.tab === 'sales' && document.getElementById('simCustomerQuestion')) {
            document.getElementById('simCustomerQuestion').textContent = '"' + data.q + '"';
            document.getElementById('simAiAnswer').textContent = '"' + data.a + '"';
            document.getElementById('simSourceName').textContent = data.src;
            document.getElementById('simRowMatch').textContent = data.row;
            document.getElementById('simRowData').textContent = data.rowText;
            document.getElementById('simLatency').textContent = data.latency;
            document.getElementById('simConfidence').textContent = data.score;
            document.getElementById('simRoute').textContent = data.route;

            // Visual flash effect on answer
            var answerCard = document.getElementById('simAiAnswer').parentElement;
            if (answerCard) {
                answerCard.classList.add('ring-2', 'ring-brand-500');
                setTimeout(function() {
                    answerCard.classList.remove('ring-2', 'ring-brand-500');
                }, 600);
            }
        }
    }

    // ROI Calculator updates
    function updateRoiCalculation(val) {
        var count = parseInt(val, 10);
        document.getElementById('messagesDisplay').textContent = count.toLocaleString() + ' / month';

        // 5 min per repetitive customer ticket = 0.083 hrs
        var hours = Math.round(count * 0.083);
        document.getElementById('hoursSaved').textContent = hours.toLocaleString() + ' hrs';

        // Est savings approx $0.45 per human resolved ticket
        var savings = Math.round(count * 0.45);
        document.getElementById('costSavings').textContent = '$' + savings.toLocaleString();
    }

    // Spotlight cursor tracking for Bento Cards
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.spotlight-card').forEach(function(card) {
            card.addEventListener('mousemove', function(e) {
                var rect = card.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                card.style.setProperty('--mouse-x', x + 'px');
                card.style.setProperty('--mouse-y', y + 'px');
            });
        });
    });
</script>
@endpush
@endsection
