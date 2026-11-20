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
                <span>Enterprise Platform 2.0</span>
                <span class="hidden sm:inline text-light-border dark:text-dark-border">/</span>
                <span class="hidden sm:inline text-light-text dark:text-dark-text font-normal">Autonomous Multi-Source AI</span>
                <i class="ri-arrow-right-line text-xs"></i>
            </div>

            {{-- Main Hero Headline (Metallic Minted Typography) --}}
            <div class="max-w-4xl mx-auto space-y-4">
                <h1 class="text-[1.35rem] xs:text-2xl sm:text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight leading-[1.18] sm:leading-[1.12]">
                    <span class="block sm:inline bg-gradient-to-b from-slate-900 via-slate-800 to-slate-600 dark:from-white dark:via-slate-100 dark:to-slate-400/80 bg-clip-text text-transparent drop-shadow-sm dark:drop-shadow-[0_2px_14px_rgba(255,255,255,0.08)]">
                        Your Business. Connected.
                    </span>
                    <span class="block sm:inline bg-gradient-to-r from-brand-500 via-sky-400 to-teal-400 bg-clip-text text-transparent drop-shadow-sm">
                        Intelligent. Automated.
                    </span>
                </h1>
                <p class="text-xs sm:text-base text-light-text-secondary dark:text-dark-text-secondary max-w-2xl mx-auto leading-relaxed">
                    Bring customer conversations, multi-source business knowledge, and intelligent analytics together in one unified ecosystem.
                </p>
            </div>

            {{-- Dual CTAs with Button Shimmer --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                <a href="{{ route('register') }}" class="btn-shimmer w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-semibold text-sm shadow-xs hover:shadow-sm transition-all">
                    <span>Get Started</span>
                    <i class="ri-arrow-right-line"></i>
                </a>
                <a href="{{ route('platform') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface/80 dark:bg-dark-surface/80 hover:bg-light-elevated dark:hover:bg-dark-elevated text-light-text dark:text-dark-text font-semibold text-sm shadow-xs transition-colors">
                    <i class="ri-compass-3-line text-brand-500"></i>
                    <span>Explore Platform</span>
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

                        {{-- Interactive Module Tabs --}}
                        <div class="flex items-center gap-1 p-1 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08] text-xs">
                            <button type="button" onclick="switchHeroTab('grounding')" id="tabBtn-grounding" class="hero-tab-btn px-3 py-1 rounded-lg text-3xs font-semibold bg-brand-600 text-white shadow-xs transition-all">
                                Catalog Grounding
                            </button>
                            <button type="button" onclick="switchHeroTab('inbox')" id="tabBtn-inbox" class="hero-tab-btn px-3 py-1 rounded-lg text-3xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-all">
                                Live Takeover
                            </button>
                            <button type="button" onclick="switchHeroTab('telemetry')" id="tabBtn-telemetry" class="hero-tab-btn px-3 py-1 rounded-lg text-3xs font-semibold text-light-muted dark:text-dark-muted hover:text-light-text dark:hover:text-dark-text transition-all">
                                Observability
                            </button>
                        </div>
                    </div>

                    {{-- Live Simulation Query Trigger Pills --}}
                    <div id="sandboxPillsBar" class="mb-4 p-2.5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08] flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-3xs font-mono font-bold uppercase tracking-wider text-light-muted dark:text-dark-muted">Interactive Sandbox:</span>
                            <span class="text-3xs text-light-muted dark:text-dark-muted">Click a question to test live multi-source retrieval</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button type="button" onclick="runSimulationQuery('stock')" class="sim-btn px-2.5 py-1 rounded-lg text-3xs font-medium border border-brand-500/30 bg-brand-500/10 text-brand-600 dark:text-brand-400 hover:bg-brand-500/20 transition-colors">
                                "iPhone 15 Pro stock?"
                            </button>
                            <button type="button" onclick="runSimulationQuery('warranty')" class="sim-btn px-2.5 py-1 rounded-lg text-3xs font-medium border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-brand-500/50 transition-colors">
                                "Warranty policy?"
                            </button>
                            <button type="button" onclick="runSimulationQuery('delivery')" class="sim-btn px-2.5 py-1 rounded-lg text-3xs font-medium border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-brand-500/50 transition-colors">
                                "Express delivery window?"
                            </button>
                        </div>
                    </div>

                    {{-- UI MODE: Tab 1 View: Catalog Grounding (Default) --}}
                    <div id="heroTabView-grounding" class="hero-tab-content grid grid-cols-1 md:grid-cols-12 gap-3.5">
                        {{-- Left: Live Dialogue Stream --}}
                        <div class="chamfer-card md:col-span-4 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2 mb-3">
                                    <div class="flex items-center gap-2 text-xs font-semibold">
                                        <i class="ri-message-3-line text-brand-500"></i>
                                        <span>Customer Dialogue</span>
                                    </div>
                                    <span class="px-1.5 py-0.5 rounded text-3xs font-mono bg-brand-500/15 text-brand-500 font-bold uppercase">Storefront Chat</span>
                                </div>
                                
                                <div class="space-y-2.5 text-xs" id="chatDialogueArea">
                                    <div class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] space-y-1">
                                        <div class="flex items-center justify-between text-3xs text-light-muted dark:text-dark-muted font-mono">
                                            <span class="font-semibold text-light-text dark:text-dark-text">Customer #8412</span>
                                            <span>Just now</span>
                                        </div>
                                        <p class="text-light-text-secondary dark:text-dark-text-secondary text-2xs" id="simCustomerQuestion">
                                            "Do you have the iPhone 15 Pro Max 256GB in Natural Titanium in stock?"
                                        </p>
                                    </div>

                                    <div class="p-2.5 rounded-lg bg-brand-500/10 border border-brand-500/20 space-y-1 transition-all">
                                        <div class="flex items-center justify-between text-3xs text-brand-500 font-semibold">
                                            <span class="flex items-center gap-1"><i class="ri-sparkling-fill text-xs"></i> Grounded AI Response</span>
                                            <span class="font-mono text-3xs" id="simLatency">180ms</span>
                                        </div>
                                        <p class="text-light-text dark:text-dark-text text-2xs leading-relaxed" id="simAiAnswer">
                                            "Yes, we have 4 units of Natural Titanium 256GB available at our Gulshan Hub for ৳162,000 with official warranty."
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-light-border dark:border-white/[0.08] flex items-center justify-between text-3xs font-mono text-light-muted dark:text-dark-muted">
                                <span>Channel: Web Widget</span>
                                <span class="text-emerald-500 font-semibold">Status: Autopilot</span>
                            </div>
                        </div>

                        {{-- Center: Knowledge Pipeline & Row Discovery --}}
                        <div class="chamfer-card md:col-span-5 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3">
                            <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2">
                                <div class="flex items-center gap-2 text-xs font-semibold">
                                    <i class="ri-database-2-line text-sky-500"></i>
                                    <span>Knowledge Grounding Pipeline</span>
                                </div>
                                <span class="text-3xs font-mono text-emerald-500 flex items-center gap-1 font-semibold">
                                    <i class="ri-checkbox-circle-fill"></i> Verified Grounding
                                </span>
                            </div>

                            <div class="space-y-2 text-2xs">
                                <div class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] space-y-1.5" id="simMatchedSource">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i class="ri-file-excel-2-line text-emerald-500 text-sm"></i>
                                            <span class="font-mono font-semibold" id="simSourceName">Product_Catalog_Nov2026.xlsx</span>
                                        </div>
                                        <span class="font-mono text-3xs text-emerald-500 font-bold" id="simRowMatch">Row #148 matched</span>
                                    </div>
                                    <div class="p-1.5 rounded bg-light-canvas dark:bg-dark-canvas font-mono text-3xs text-light-muted dark:text-dark-muted truncate" id="simRowData">
                                        SKU: IP15P-256-NT | Stock: 4 | Hub: Gulshan | Price: 162,000 BDT
                                    </div>
                                </div>

                                <div class="p-2 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] flex items-center justify-between text-3xs font-mono">
                                    <span class="flex items-center gap-1.5">
                                        <i class="ri-shield-check-line text-brand-500"></i>
                                        <span>Hallucination Filter: Passed</span>
                                    </span>
                                    <span class="text-emerald-500 font-semibold" id="simConfidence">Score: 0.984</span>
                                </div>

                                <div class="p-2.5 rounded-lg bg-light-surface/60 dark:bg-dark-surface/60 border border-dashed border-light-border dark:border-white/[0.08] text-3xs font-mono text-light-muted dark:text-dark-muted space-y-1">
                                    <div class="flex justify-between">
                                        <span>Route Strategy:</span>
                                        <span class="text-light-text dark:text-dark-text font-semibold" id="simRoute">Deterministic Catalog Query</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Token Overhead:</span>
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
                                        <span>Execution Metrics</span>
                                    </div>
                                    <span class="text-3xs font-mono text-purple-500 font-semibold">Sub-Second</span>
                                </div>

                                <div class="space-y-3">
                                    <div>
                                        <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted uppercase">
                                            <span>Resolution Latency</span>
                                            <span class="text-light-text dark:text-dark-text font-bold">180ms</span>
                                        </div>
                                        {{-- Inline SVG Micro Sparkline --}}
                                        <svg class="w-full h-8 mt-1 text-brand-500" viewBox="0 0 100 25" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M0,18 L15,14 L30,16 L45,10 L60,12 L75,8 L90,9 L100,6" stroke-linecap="round"/>
                                        </svg>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted uppercase">
                                            <span>Grounding Precision</span>
                                            <span class="text-emerald-500 font-bold">99.8%</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-light-surface dark:bg-dark-surface rounded-full overflow-hidden mt-1.5">
                                            <div class="h-full bg-emerald-500 rounded-full" style="width: 99.8%"></div>
                                        </div>
                                    </div>

                                    <div>
                                        <div class="flex justify-between text-3xs font-mono text-light-muted dark:text-dark-muted uppercase">
                                            <span>Avg Cost / Answer</span>
                                            <span class="text-brand-500 font-bold font-mono">$0.00028</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <a href="{{ route('knowledge-engine') }}" class="text-center py-1.5 px-3 rounded-lg border border-light-border dark:border-white/[0.08] text-2xs font-semibold hover:border-brand-500/50 transition-colors">
                                View Engine Docs &rarr;
                            </a>
                        </div>
                    </div>

                    {{-- UI MODE: Tab 2 View: Live Unified Inbox & Takeover --}}
                    <div id="heroTabView-inbox" class="hero-tab-content hidden grid grid-cols-1 md:grid-cols-12 gap-3.5">
                        <div class="chamfer-card md:col-span-5 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-2">
                            <div class="text-xs font-semibold border-b border-light-border dark:border-white/[0.08] pb-2 flex justify-between">
                                <span>Omnichannel Queue</span>
                                <span class="text-3xs font-mono text-brand-500 font-bold">4 Active</span>
                            </div>
                            <div class="space-y-1.5 text-2xs">
                                <div class="p-2 rounded-lg bg-brand-500/10 border border-brand-500/30 flex justify-between items-center">
                                    <div>
                                        <div class="font-bold text-light-text dark:text-dark-text">Tamim Iqbal</div>
                                        <div class="text-3xs text-light-muted dark:text-dark-muted truncate">"Can I exchange for larger size?"</div>
                                    </div>
                                    <span class="px-1.5 py-0.5 rounded text-3xs bg-amber-500 text-white font-bold">Takeover</span>
                                </div>
                                <div class="p-2 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] flex justify-between items-center">
                                    <div>
                                        <div class="font-bold text-light-text dark:text-dark-text">Farhana Yesmin</div>
                                        <div class="text-3xs text-light-muted dark:text-dark-muted truncate">"Thanks, order received!"</div>
                                    </div>
                                    <span class="px-1.5 py-0.5 rounded text-3xs bg-emerald-500/20 text-emerald-500 font-bold">Resolved</span>
                                </div>
                            </div>
                        </div>

                        <div class="chamfer-card md:col-span-7 p-3.5 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-3">
                            <div class="flex items-center justify-between border-b border-light-border dark:border-white/[0.08] pb-2">
                                <div class="flex items-center gap-2 text-xs font-semibold">
                                    <i class="ri-user-shared-line text-amber-500"></i>
                                    <span>Agent Takeover Console</span>
                                </div>
                                <span class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-500 text-3xs font-mono font-bold">AI Co-Pilot Active</span>
                            </div>
                            <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                                When high-touch customers require custom accommodations, human agents click "Take Over" to pause automated replies. The platform suggests grounded policy citations on the right panel while maintaining unbroken conversation context.
                            </p>
                            <div class="p-2.5 rounded-lg bg-light-surface dark:bg-dark-surface border border-light-border dark:border-white/[0.08] flex items-center justify-between text-2xs font-mono">
                                <span>Suggested Response:</span>
                                <span class="text-brand-500 font-semibold cursor-pointer hover:underline">Insert Policy Ch. 3 &rarr;</span>
                            </div>
                        </div>
                    </div>

                    {{-- UI MODE: Tab 3 View: Resolution Telemetry & Latency --}}
                    <div id="heroTabView-telemetry" class="hero-tab-content hidden grid grid-cols-1 md:grid-cols-3 gap-3.5 text-xs">
                        <div class="chamfer-card p-4 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-2">
                            <div class="text-3xs uppercase font-mono text-light-muted dark:text-dark-muted">Peak Query Velocity</div>
                            <div class="text-2xl font-bold font-mono text-light-text dark:text-dark-text">1,420 <span class="text-xs font-normal">req/min</span></div>
                            <p class="text-3xs text-light-muted dark:text-dark-muted">Automated scale without concurrency locking or rate limits.</p>
                        </div>
                        <div class="chamfer-card p-4 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-2">
                            <div class="text-3xs uppercase font-mono text-light-muted dark:text-dark-muted">P95 Response Latency</div>
                            <div class="text-2xl font-bold font-mono text-emerald-500">220ms</div>
                            <p class="text-3xs text-light-muted dark:text-dark-muted">Hybrid lexical and vector index delivers answers in under 0.25s.</p>
                        </div>
                        <div class="chamfer-card p-4 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-canvas dark:bg-dark-canvas space-y-2">
                            <div class="text-3xs uppercase font-mono text-light-muted dark:text-dark-muted">Deterministic Boundary</div>
                            <div class="text-2xl font-bold font-mono text-sky-500">100% Gated</div>
                            <p class="text-3xs text-light-muted dark:text-dark-muted">Unverified inquiries are routed to fallback or human agents.</p>
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

                {{-- 4. WhatsApp Business (In Development) --}}
                <div class="chamfer-card p-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface flex flex-col items-center gap-1.5 opacity-85">
                    <i class="ri-whatsapp-fill text-emerald-500 text-xl"></i>
                    <span class="text-xs font-semibold">WhatsApp</span>
                    <span class="px-1.5 py-0.2 rounded text-3xs font-mono font-bold bg-amber-500/15 text-amber-500">In Progress</span>
                </div>

                {{-- 5. Shopify / WooCommerce (Planned) --}}
                <div class="chamfer-card p-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface flex flex-col items-center gap-1.5 opacity-70">
                    <i class="ri-shopping-bag-3-line text-orange-500 text-xl"></i>
                    <span class="text-xs font-semibold">Shopify</span>
                    <span class="px-1.5 py-0.2 rounded text-3xs font-mono font-bold bg-light-muted/20 text-light-muted dark:text-dark-muted">Planned</span>
                </div>

                {{-- 6. Telegram (Planned) --}}
                <div class="chamfer-card p-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface flex flex-col items-center gap-1.5 opacity-70">
                    <i class="ri-telegram-fill text-sky-400 text-xl"></i>
                    <span class="text-xs font-semibold">Telegram</span>
                    <span class="px-1.5 py-0.2 rounded text-3xs font-mono font-bold bg-light-muted/20 text-light-muted dark:text-dark-muted">Planned</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 3. PLATFORM CORE PILLARS (BENTO GRID WITH SPOTLIGHT GLOW) ==================== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-3 max-w-2xl mx-auto">
            <h2 class="text-2xs font-bold uppercase tracking-wider text-brand-500 font-mono">Platform Architecture</h2>
            <p class="text-2xl sm:text-3xl font-extrabold text-light-text dark:text-dark-text tracking-tight">
                Four Pillars. One Unified Business Engine.
            </p>
            <p class="text-xs sm:text-sm text-light-text-secondary dark:text-dark-text-secondary">
                Designed from the ground up for high-volume commerce operators requiring verified factual grounding and full operational visibility.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            {{-- Pillar 1: AI Knowledge Engine --}}
            <a href="{{ route('knowledge-engine') }}" class="chamfer-card spotlight-card group p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-brand-500/50 hover:bg-light-elevated dark:hover:bg-dark-elevated transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-lg font-bold group-hover:scale-105 transition-transform">
                        <i class="ri-database-2-line"></i>
                    </div>
                    <h3 class="font-bold text-sm text-light-text dark:text-dark-text group-hover:text-brand-500 transition-colors">
                        AI Knowledge Engine
                    </h3>
                    <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        Ingest complex Excel catalogs, warranty policies, and structured FAQs. Responses are grounded in verified business facts.
                    </p>
                </div>
                <div class="flex items-center gap-1 text-3xs font-semibold text-brand-500">
                    <span>Learn more</span>
                    <i class="ri-arrow-right-line group-hover:translate-x-1 transition-transform"></i>
                </div>
            </a>

            {{-- Pillar 2: Unified Customer Inbox --}}
            <a href="{{ route('unified-inbox') }}" class="chamfer-card spotlight-card group p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-sky-500/50 hover:bg-light-elevated dark:hover:bg-dark-elevated transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-500 flex items-center justify-center text-lg font-bold group-hover:scale-105 transition-transform">
                        <i class="ri-chat-1-line"></i>
                    </div>
                    <h3 class="font-bold text-sm text-light-text dark:text-dark-text group-hover:text-sky-500 transition-colors">
                        Unified Customer Inbox
                    </h3>
                    <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        Centralize multi-channel dialogues in one interface. Seamlessly transfer between autonomous AI and human support reps.
                    </p>
                </div>
                <div class="flex items-center gap-1 text-3xs font-semibold text-sky-500">
                    <span>Learn more</span>
                    <i class="ri-arrow-right-line group-hover:translate-x-1 transition-transform"></i>
                </div>
            </a>

            {{-- Pillar 3: Business Intelligence --}}
            <a href="{{ route('business-intelligence') }}" class="chamfer-card spotlight-card group p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-purple-500/50 hover:bg-light-elevated dark:hover:bg-dark-elevated transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-lg font-bold group-hover:scale-105 transition-transform">
                        <i class="ri-bar-chart-box-line"></i>
                    </div>
                    <h3 class="font-bold text-sm text-light-text dark:text-dark-text group-hover:text-purple-500 transition-colors">
                        Business Intelligence
                    </h3>
                    <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        Owner-first analytics tracking resolution velocity, customer demand clusters, and natural-language insight queries.
                    </p>
                </div>
                <div class="flex items-center gap-1 text-3xs font-semibold text-purple-500">
                    <span>Learn more</span>
                    <i class="ri-arrow-right-line group-hover:translate-x-1 transition-transform"></i>
                </div>
            </a>

            {{-- Pillar 4: Intelligent Automation --}}
            <a href="{{ route('automation') }}" class="chamfer-card spotlight-card group p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:border-emerald-500/50 hover:bg-light-elevated dark:hover:bg-dark-elevated transition-all flex flex-col justify-between space-y-4">
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-lg font-bold group-hover:scale-105 transition-transform">
                        <i class="ri-cpu-line"></i>
                    </div>
                    <h3 class="font-bold text-sm text-light-text dark:text-dark-text group-hover:text-emerald-500 transition-colors">
                        Intelligent Automation
                    </h3>
                    <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                        Automate order confirmations, return eligibility checks, and lead qualification with sub-second execution speed.
                    </p>
                </div>
                <div class="flex items-center gap-1 text-3xs font-semibold text-emerald-500">
                    <span>Learn more</span>
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
                    <span class="text-3xs font-mono font-bold uppercase tracking-wider text-brand-500">Deterministic Architecture</span>
                    <h3 class="text-xl sm:text-2xl font-bold text-light-text dark:text-dark-text tracking-tight">
                        Transform Raw Spreadsheets into Grounded Business Truth.
                    </h3>
                    <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                        Unlike generic chatbots that hallucinate pricing or specifications, Entrepreneurs Automation parses multi-sheet Excel workbooks, discovers column relationships, and verifies live stock before responding.
                    </p>
                    <div class="space-y-2 pt-2">
                        <div class="flex items-center gap-2 text-xs">
                            <i class="ri-checkbox-circle-fill text-emerald-500"></i>
                            <span>Multi-Sheet Relationships & Automatic Schema Discovery</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <i class="ri-checkbox-circle-fill text-emerald-500"></i>
                            <span>Real-Time Fallback Gates for Zero Unverified Claims</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs">
                            <i class="ri-checkbox-circle-fill text-emerald-500"></i>
                            <span>Bilingual Context Engine (English & Bengali script support)</span>
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
            <h2 class="text-2xs font-bold uppercase tracking-wider text-brand-500 font-mono">Value & Velocity</h2>
            <p class="text-2xl sm:text-3xl font-extrabold text-light-text dark:text-dark-text tracking-tight">
                Calculate Your Support Hours & Operational Savings
            </p>
            <p class="text-xs sm:text-sm text-light-text-secondary dark:text-dark-text-secondary">
                See how autonomous grounding transforms operational cost and resolution speed.
            </p>
        </div>

        <div class="chamfer-card p-6 sm:p-10 rounded-2xl border border-light-border dark:border-white/[0.1] bg-light-surface/90 dark:bg-dark-surface/90 backdrop-blur-md max-w-4xl mx-auto">
            <div class="space-y-6">
                {{-- Slider Control --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs font-semibold">
                        <label for="monthlyMessagesRange" class="text-light-text dark:text-dark-text">Monthly Customer Messages:</label>
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
                        <div class="text-3xs font-mono uppercase text-light-muted dark:text-dark-muted">Hours Saved</div>
                        <div class="text-xl sm:text-2xl font-bold font-mono text-brand-500 mt-1" id="hoursSaved">415 hrs</div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">per month</div>
                    </div>

                    <div class="chamfer-card p-3.5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08]">
                        <div class="text-3xs font-mono uppercase text-light-muted dark:text-dark-muted">Autonomous Rate</div>
                        <div class="text-xl sm:text-2xl font-bold font-mono text-emerald-500 mt-1">82%</div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">without human rep</div>
                    </div>

                    <div class="chamfer-card p-3.5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08]">
                        <div class="text-3xs font-mono uppercase text-light-muted dark:text-dark-muted">First Response</div>
                        <div class="text-xl sm:text-2xl font-bold font-mono text-sky-500 mt-1">0.38s</div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">vs 14m manual</div>
                    </div>

                    <div class="chamfer-card p-3.5 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-white/[0.08]">
                        <div class="text-3xs font-mono uppercase text-light-muted dark:text-dark-muted">Est. Savings</div>
                        <div class="text-xl sm:text-2xl font-bold font-mono text-teal-400 mt-1" id="costSavings">$2,250</div>
                        <div class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">support overhead</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 6. ENTERPRISE SECURITY & TRUST ANCHORS ==================== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
        <div class="text-center space-y-3 max-w-2xl mx-auto">
            <h2 class="text-2xs font-bold uppercase tracking-wider text-brand-500 font-mono">Enterprise Security</h2>
            <p class="text-2xl sm:text-3xl font-extrabold text-light-text dark:text-dark-text tracking-tight">
                Built for Multi-Tenant Isolation & Zero Data Leakage
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="chamfer-card spotlight-card p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-building-4-line text-brand-500 text-2xl"></i>
                <h3 class="font-bold text-sm text-light-text dark:text-dark-text">Workspace Isolation</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Database-level tenant separation ensures that catalogs, dialogues, and credentials remain private.
                </p>
            </div>

            <div class="chamfer-card spotlight-card p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-shield-user-line text-sky-500 text-2xl"></i>
                <h3 class="font-bold text-sm text-light-text dark:text-dark-text">Granular RBAC</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Role-based access control enforces explicit capabilities for SuperAdmin, Admin, Agent, and Viewer roles.
                </p>
            </div>

            <div class="chamfer-card spotlight-card p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-lock-password-line text-emerald-500 text-2xl"></i>
                <h3 class="font-bold text-sm text-light-text dark:text-dark-text">Encrypted Credentials</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Channel tokens, webhook secrets, and binary spreadsheets are encrypted and protected with application key cryptography.
                </p>
            </div>

            <div class="chamfer-card spotlight-card p-5 rounded-2xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface space-y-3">
                <i class="ri-forbid-2-line text-purple-500 text-2xl"></i>
                <h3 class="font-bold text-sm text-light-text dark:text-dark-text">Private Workspace Boundary</h3>
                <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Multi-tenant workspace partitioning ensures your catalog data, customer inquiries, and conversations are never shared across tenants.
                </p>
            </div>
        </div>
    </section>

    {{-- ==================== 7. FREQUENTLY ASKED QUESTIONS ==================== --}}
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div class="text-center space-y-2">
            <span class="text-3xs font-mono font-bold uppercase tracking-wider text-brand-500">Clear Answers</span>
            <h2 class="text-2xl font-bold text-light-text dark:text-dark-text">Frequently Asked Questions</h2>
        </div>

        <div class="space-y-3" id="faqAccordion">
            <div class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                <button type="button" class="w-full flex items-center justify-between text-left text-xs font-semibold text-light-text dark:text-dark-text" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('i').classList.toggle('rotate-180');">
                    <span>How does the AI verify product stock from Excel spreadsheets?</span>
                    <i class="ri-arrow-down-s-line text-sm transition-transform"></i>
                </button>
                <div class="pt-2 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Our spreadsheet ingestion pipeline parses your columns (SKU, title, stock quantity, price, hub location). When customer inquiries arrive, semantic search pinpoints the exact row in real-time, delivering grounded, accurate answers based strictly on your verified catalog records.
                </div>
            </div>

            <div class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                <button type="button" class="w-full flex items-center justify-between text-left text-xs font-semibold text-light-text dark:text-dark-text" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('i').classList.toggle('rotate-180');">
                    <span>Can human support agents intervene in AI conversations?</span>
                    <i class="ri-arrow-down-s-line text-sm transition-transform"></i>
                </button>
                <div class="pt-2 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Yes. The Unified Customer Inbox includes an instant "Takeover / Human Handoff" switch. Agents can send manual replies, reassign tickets, or leave internal notes while the AI pauses automatically for that customer.
                </div>
            </div>

            <div class="chamfer-card rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface p-4">
                <button type="button" class="w-full flex items-center justify-between text-left text-xs font-semibold text-light-text dark:text-dark-text" onclick="this.nextElementSibling.classList.toggle('hidden'); this.querySelector('i').classList.toggle('rotate-180');">
                    <span>What channels are currently available?</span>
                    <i class="ri-arrow-down-s-line text-sm transition-transform"></i>
                </button>
                <div class="pt-2 text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Facebook Messenger, the embeddable Web Chat Widget, and REST API/Webhooks are currently fully functional and available. WhatsApp Business integration is actively in progress, with Shopify, WooCommerce, and Telegram planned on the roadmap.
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 8. FINAL CONVERSION CTA ==================== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="chamfer-card relative rounded-3xl border border-brand-500/30 bg-gradient-to-b from-brand-500/15 via-light-surface to-light-surface dark:via-dark-surface dark:to-dark-surface p-8 sm:p-14 text-center space-y-6 overflow-hidden shadow-sm dark:shadow-[0_8px_30px_rgb(0,0,0,0.3)]">
            <div class="max-w-2xl mx-auto space-y-3">
                <h2 class="text-2xl sm:text-4xl font-extrabold text-light-text dark:text-dark-text tracking-tight">
                    Bring Your Business Into One Intelligent Ecosystem.
                </h2>
                <p class="text-xs sm:text-sm text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                    Connect customer operations, ground conversations in verified knowledge, and gain complete visibility.
                </p>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                <a href="{{ route('register') }}" class="btn-shimmer w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
                    <span>Get Started Today</span>
                    <i class="ri-arrow-right-line"></i>
                </a>
                <a href="{{ route('contact') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl border border-light-border dark:border-white/[0.08] bg-light-surface dark:bg-dark-surface hover:bg-light-elevated dark:hover:bg-dark-elevated text-light-text dark:text-dark-text font-semibold text-xs shadow-xs transition-colors">
                    <span>Contact Sales</span>
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
    // Tab switching for Hero Showcase
    function switchHeroTab(tabName) {
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

    // Simulation queries for Sandbox
    var simulationData = {
        stock: {
            q: "Do you have the iPhone 15 Pro Max 256GB in Natural Titanium in stock?",
            a: "Yes, we have 4 units of Natural Titanium 256GB available at our Gulshan Hub for ৳162,000 with official warranty.",
            src: "Product_Catalog_Nov2026.xlsx",
            row: "Row #148 matched",
            rowText: "SKU: IP15P-256-NT | Stock: 4 | Hub: Gulshan | Price: 162,000 BDT",
            latency: "180ms",
            score: "Score: 0.984",
            route: "Deterministic Catalog Query"
        },
        warranty: {
            q: "What is your official return and replacement warranty policy?",
            a: "We offer a 7-day replacement warranty for manufacturing defects and 1-year official brand service warranty across all verified branch locations.",
            src: "Official_Warranty_Policy.pdf",
            row: "Chunk #4 matched",
            rowText: "Policy Doc Section 2.1 | Coverage: 7-day replacement, 1-yr service warranty",
            latency: "195ms",
            score: "Score: 0.991",
            route: "Document Vector Search"
        },
        delivery: {
            q: "Can I receive express home delivery in Chittagong within 24 hours?",
            a: "Yes! Orders confirmed before 4:00 PM qualify for next-day priority transit to Chittagong metro via our regional logistics hub for ৳130.",
            src: "Shipping_Matrix_2026.xlsx",
            row: "Sheet 2: Metro_SLA (Row #12)",
            rowText: "Zone: Chittagong Metro | LeadTime: 24h | Cutoff: 16:00 | Fee: 130 BDT",
            latency: "165ms",
            score: "Score: 0.978",
            route: "Multi-Sheet Relational Routing"
        }
    };

    function runSimulationQuery(type) {
        var data = simulationData[type];
        if (!data) return;

        switchHeroTab('grounding');

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
        answerCard.classList.add('ring-2', 'ring-brand-500');
        setTimeout(function() {
            answerCard.classList.remove('ring-2', 'ring-brand-500');
        }, 600);
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
