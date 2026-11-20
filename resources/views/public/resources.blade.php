@extends('layouts.public')

@section('title', 'Resources, Guides & Documentation — Entrepreneurs Automation')
@section('meta_description', 'Technical guides, architecture whitepapers, spreadsheet schema recommendations, and API documentation for Entrepreneurs Automation.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
            <i class="ri-book-read-line"></i> Documentation & Knowledge
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            Resources, Guides & Technical References.
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            Everything you need to configure your catalog workbooks, integrate web chat widgets, and optimize autonomous resolution velocity.
        </p>
    </div>

    {{-- Featured Technical Guides --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Guide 1 --}}
        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center text-xl font-bold">
                    <i class="ri-file-excel-2-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">Spreadsheet Ingestion Guide</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Learn how to format multi-sheet Excel catalogs with column headers (SKU, Title, Hub, Price, Stock) for 99%+ semantic matching precision.
                </p>
            </div>
            <div class="pt-2 text-3xs font-mono text-emerald-500 font-bold flex items-center gap-1">
                <span>Architecture Guide</span> <span class="text-light-border dark:text-dark-border">/</span> <span>5 min read</span>
            </div>
        </div>

        {{-- Guide 2 --}}
        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-xl font-bold">
                    <i class="ri-code-box-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">Storefront Web Widget Embed</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Step-by-step tutorial on embedding the floating web chat drawer into Shopify, WordPress, or custom HTML with zero latency impact.
                </p>
            </div>
            <div class="pt-2 text-3xs font-mono text-brand-500 font-bold flex items-center gap-1">
                <span>Frontend Tutorial</span> <span class="text-light-border dark:text-dark-border">/</span> <span>3 min read</span>
            </div>
        </div>

        {{-- Guide 3 --}}
        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4 flex flex-col justify-between">
            <div class="space-y-3">
                <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl font-bold">
                    <i class="ri-shield-keyhole-line"></i>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">RBAC Security & Multi-Tenancy</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Deep dive into our tenant separation architecture, Spatie role delegations, and token encryption protocols.
                </p>
            </div>
            <div class="pt-2 text-3xs font-mono text-purple-500 font-bold flex items-center gap-1">
                <span>Security Whitepaper</span> <span class="text-light-border dark:text-dark-border">/</span> <span>8 min read</span>
            </div>
        </div>
    </div>

    {{-- Interactive Developer Code Snippet Showcase --}}
    <div class="p-6 sm:p-10 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-light-border dark:border-dark-border">
            <div class="flex items-center gap-2">
                <i class="ri-terminal-box-line text-brand-500"></i>
                <span class="text-xs font-bold text-light-text dark:text-dark-text">1-Line Embed Code Reference</span>
            </div>
            <span class="text-3xs font-mono text-light-muted dark:text-dark-muted">Platform Native</span>
        </div>

        <div class="p-4 rounded-xl bg-dark-canvas border border-dark-border text-2xs font-mono text-slate-300 space-y-2 overflow-x-auto">
            <div class="text-light-muted dark:text-dark-muted">// Paste this script tag right before your &lt;/body&gt; closing tag</div>
            <div class="text-sky-400">&lt;<span class="text-rose-400">script</span> <span class="text-amber-400">src</span>=<span class="text-emerald-400">"{{ url('/widget/bundle.js') }}"</span> <span class="text-amber-400">data-account</span>=<span class="text-emerald-400">"YOUR_TENANT_KEY"</span> <span class="text-amber-400">async</span>&gt;&lt;/<span class="text-rose-400">script</span>&gt;</div>
        </div>
    </div>

    {{-- CTA --}}
    <div class="text-center pt-8">
        <a href="{{ route('faq') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface hover:bg-light-elevated dark:hover:bg-dark-elevated text-xs font-semibold text-light-text dark:text-dark-text transition-colors">
            <span>View Frequently Asked Questions</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

</div>
@endsection
