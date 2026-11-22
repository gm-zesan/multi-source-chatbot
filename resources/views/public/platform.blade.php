@extends('layouts.public')

@section('title', 'Platform Overview — Entrepreneurs Automation')
@section('meta_description', 'Explore the unified architecture of Entrepreneurs Automation: AI Knowledge Engine, Unified Inbox, Business Intelligence, and Intelligent Workflow Automation.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Header --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
            <i class="ri-cpu-line"></i> Full Stack Platform
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            One Unified Architecture for Customer Operations & Business Data.
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            Entrepreneurs Automation brings disconnected channels, business spreadsheets, customer dialogues, and analytics into a cohesive, sub-second execution pipeline.
        </p>
    </div>

    {{-- System Flow Diagram (Visual) --}}
    <div class="p-6 sm:p-10 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6">
        <h2 class="text-xs font-mono uppercase tracking-wider font-bold text-light-muted dark:text-dark-muted">
            End-to-End Operational Lifecycle
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-xs font-mono">
            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2">
                <div class="w-7 h-7 rounded bg-blue-500/15 text-blue-500 flex items-center justify-center font-bold">1</div>
                <div class="font-bold text-light-text dark:text-dark-text">Channel Ingestion</div>
                <div class="text-3xs text-light-muted dark:text-dark-muted">Customer messages arrive via Facebook Messenger, Web Chat Widget, or Webhooks.</div>
            </div>
            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2">
                <div class="w-7 h-7 rounded bg-sky-500/15 text-sky-500 flex items-center justify-center font-bold">2</div>
                <div class="font-bold text-light-text dark:text-dark-text">Context Resolution</div>
                <div class="text-3xs text-light-muted dark:text-dark-muted">The engine classifies intent and searches uploaded Excel catalogs, CSVs, and structured business FAQs.</div>
            </div>
            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2">
                <div class="w-7 h-7 rounded bg-purple-500/15 text-purple-500 flex items-center justify-center font-bold">3</div>
                <div class="font-bold text-light-text dark:text-dark-text">Quality & Safety Gate</div>
                <div class="text-3xs text-light-muted dark:text-dark-muted">Hallucination check and confidence score validation. Unverified queries transfer to human reps.</div>
            </div>
            <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-2">
                <div class="w-7 h-7 rounded bg-emerald-500/15 text-emerald-500 flex items-center justify-center font-bold">4</div>
                <div class="font-bold text-light-text dark:text-dark-text">Telemetry & BI Logging</div>
                <div class="text-3xs text-light-muted dark:text-dark-muted">Latency, token costs, customer demand patterns, and resolution KPIs are logged to analytics.</div>
            </div>
        </div>
    </div>

    {{-- The 4 Deep Dives --}}
    <div class="space-y-16">
        {{-- Section 1 --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
            <div class="space-y-4">
                <span class="text-xs font-mono font-bold uppercase tracking-wider text-brand-500">01. AI Knowledge Engine</span>
                <h3 class="text-2xl font-bold text-light-text dark:text-dark-text">Turn Static Documents into Live Business Context</h3>
                <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                    Most AI platforms fail because they guess rather than retrieve. Entrepreneurs Automation utilizes hybrid vector search and column relationship discovery across spreadsheets, ensuring your inventory, pricing, and warranty policies are followed to the letter.
                </p>
                <a href="{{ route('knowledge-engine') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-500 hover:text-brand-600">
                    <span>Explore Knowledge Engine</span> <i class="ri-arrow-right-line"></i>
                </a>
            </div>
            <div class="p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-2 text-2xs font-mono">
                <div class="flex items-center justify-between pb-2 border-b border-light-border dark:border-dark-border">
                    <span class="text-brand-500 font-bold">Multi-Source Retrieval</span>
                    <span class="text-3xs text-emerald-500">Active</span>
                </div>
                <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas space-y-1">
                    <div class="text-light-text dark:text-dark-text font-semibold">Primary Source: Inventory.xlsx</div>
                    <div class="text-light-muted dark:text-dark-muted text-3xs">Schema: SKU, Title, Price, Warehouse_Qty, Hub_Location</div>
                </div>
                <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas space-y-1">
                    <div class="text-light-text dark:text-dark-text font-semibold">Secondary Source: Business_FAQs</div>
                    <div class="text-light-muted dark:text-dark-muted text-3xs">Type: Return Policy, Delivery Charges, Warranty Coverage</div>
                </div>
            </div>
        </div>

        {{-- Section 2 --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
            <div class="order-2 lg:order-1 p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-2 text-2xs font-mono">
                <div class="flex items-center justify-between pb-2 border-b border-light-border dark:border-dark-border">
                    <span class="text-emerald-500 font-bold">Centralized Dialogue Queue</span>
                    <span class="text-3xs text-light-muted dark:text-dark-muted">Live Feed</span>
                </div>
                <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-light-text dark:text-dark-text">Ticket #4892 (Facebook Messenger)</div>
                        <div class="text-light-muted dark:text-dark-muted text-3xs">Customer: Fahim Ahmed / 2 min ago</div>
                    </div>
                    <span class="px-2 py-0.5 rounded text-3xs bg-brand-500/15 text-brand-500 font-bold">AI Handled</span>
                </div>
                <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-light-text dark:text-dark-text">Ticket #4891 (Storefront Web Chat)</div>
                        <div class="text-light-muted dark:text-dark-muted text-3xs">Customer: Tanvir Hossain / 5 min ago</div>
                    </div>
                    <span class="px-2 py-0.5 rounded text-3xs bg-amber-500/15 text-amber-500 font-bold">Agent Review</span>
                </div>
            </div>
            <div class="order-1 lg:order-2 space-y-4">
                <span class="text-xs font-mono font-bold uppercase tracking-wider text-sky-500">02. Unified Customer Inbox</span>
                <h3 class="text-2xl font-bold text-light-text dark:text-dark-text">Manage Every Conversation in One Central Place</h3>
                <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                    Switching between Facebook Business Suite, website live chats, and messaging portals slows down your response time. Our unified inbox collates all conversations with conversation history, sentiment badges, and seamless agent takeover.
                </p>
                <a href="{{ route('unified-inbox') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-500 hover:text-sky-600">
                    <span>Explore Unified Inbox</span> <i class="ri-arrow-right-line"></i>
                </a>
            </div>
        </div>

        {{-- Section 3 --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
            <div class="space-y-4">
                <span class="text-xs font-mono font-bold uppercase tracking-wider text-purple-500">03. Business Intelligence</span>
                <h3 class="text-2xl font-bold text-light-text dark:text-dark-text">Actionable Owner Analytics, Not Vanity Metrics</h3>
                <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
                    Track the questions your customers are asking, peak traffic hours, token consumption costs, and automatic resolution percentages. Export comprehensive reports in CSV, Excel, or PDF at any time.
                </p>
                <a href="{{ route('business-intelligence') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-purple-500 hover:text-purple-600">
                    <span>Explore Business Intelligence</span> <i class="ri-arrow-right-line"></i>
                </a>
            </div>
            <div class="p-4 rounded-xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3 font-mono text-2xs">
                <div class="grid grid-cols-2 gap-2">
                    <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas">
                        <div class="text-3xs text-light-muted dark:text-dark-muted">Resolution Velocity</div>
                        <div class="text-base font-bold text-light-text dark:text-dark-text mt-1">94.8%</div>
                    </div>
                    <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas">
                        <div class="text-3xs text-light-muted dark:text-dark-muted">Median Latency</div>
                        <div class="text-base font-bold text-emerald-500 mt-1">210ms</div>
                    </div>
                </div>
                <div class="p-2.5 rounded bg-light-canvas dark:bg-dark-canvas flex items-center justify-between text-3xs">
                    <span>Top Demand Cluster: Order Delivery Queries</span>
                    <span class="text-brand-500 font-bold">42% of volume</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Call to action --}}
    <div class="text-center pt-8">
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
            <span>Get Started with Entrepreneurs Automation</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

</div>
@endsection
