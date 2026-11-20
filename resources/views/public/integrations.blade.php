@extends('layouts.public')

@section('title', 'Integrations & Ecosystem — Entrepreneurs Automation')
@section('meta_description', 'Connect your existing channels, storefronts, and messaging portals. See currently available and in-development platform integrations.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
            <i class="ri-links-line"></i> Connected Ecosystem
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            Integrated With Your Commerce Stack.
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            Connect customer inquiry channels and existing e-commerce infrastructure into Entrepreneurs Automation with verified enterprise reliability.
        </p>
    </div>

    {{-- Verified Available Integrations --}}
    <div class="space-y-6">
        <div class="flex items-center justify-between pb-2 border-b border-light-border dark:border-dark-border">
            <h2 class="text-sm font-bold uppercase tracking-wider text-light-text dark:text-dark-text flex items-center gap-2">
                <i class="ri-checkbox-circle-fill text-emerald-500 text-sm"></i>
                <span>Currently Available & Operational</span>
            </h2>
            <span class="text-3xs font-mono text-emerald-500 font-semibold">Production Verified</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Facebook Messenger --}}
            <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center text-xl">
                        <i class="ri-facebook-circle-fill"></i>
                    </div>
                    <span class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">Live</span>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">Facebook Messenger</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Direct Meta Webhook connection with token sync. Supports instant replies, media handling, human handover, and page-level tenant mapping.
                </p>
            </div>

            {{-- Web Chat Widget --}}
            <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-xl bg-brand-500/10 text-brand-500 flex items-center justify-center text-xl">
                        <i class="ri-global-line"></i>
                    </div>
                    <span class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">Live</span>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">Storefront Web Widget</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Zero-latency lightweight embed script. Drop a single script tag on any custom HTML, WordPress, or e-commerce site for a floating AI chat drawer.
                </p>
            </div>

            {{-- REST API & Webhooks --}}
            <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
                <div class="flex justify-between items-start">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center text-xl">
                        <i class="ri-code-s-slash-line"></i>
                    </div>
                    <span class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-emerald-500/15 text-emerald-500">Live</span>
                </div>
                <h3 class="text-base font-bold text-light-text dark:text-dark-text">REST API & Webhook Gateway</h3>
                <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                    Programmatic synchronous chat endpoints (`/api/v1/widget/chat`) and webhook dispatchers for custom CRM integration and backend pipelines.
                </p>
            </div>
        </div>
    </div>

    {{-- In Progress & Roadmap --}}
    <div class="space-y-6">
        <div class="flex items-center justify-between pb-2 border-b border-light-border dark:border-dark-border">
            <h2 class="text-sm font-bold uppercase tracking-wider text-light-text dark:text-dark-text flex items-center gap-2">
                <i class="ri-time-line text-amber-500 text-sm"></i>
                <span>Active Development & Roadmap</span>
            </h2>
            <span class="text-3xs font-mono text-amber-500 font-semibold">In Progress</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="p-5 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/70 dark:bg-dark-surface/70 space-y-2">
                <div class="flex justify-between items-start">
                    <i class="ri-whatsapp-fill text-emerald-500 text-xl"></i>
                    <span class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-amber-500/15 text-amber-500">In Progress</span>
                </div>
                <h4 class="text-xs font-bold text-light-text dark:text-dark-text">WhatsApp Business</h4>
                <p class="text-3xs text-light-muted dark:text-dark-muted">Official Meta Cloud API integration for automated WhatsApp customer dialogues.</p>
            </div>

            <div class="p-5 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/50 dark:bg-dark-surface/50 space-y-2 opacity-80">
                <div class="flex justify-between items-start">
                    <i class="ri-instagram-fill text-pink-500 text-xl"></i>
                    <span class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-light-muted/20 text-light-muted dark:text-dark-muted">Planned</span>
                </div>
                <h4 class="text-xs font-bold text-light-text dark:text-dark-text">Instagram Direct</h4>
                <p class="text-3xs text-light-muted dark:text-dark-muted">Direct message automation and story mention response routing.</p>
            </div>

            <div class="p-5 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/50 dark:bg-dark-surface/50 space-y-2 opacity-80">
                <div class="flex justify-between items-start">
                    <i class="ri-shopping-bag-3-line text-orange-500 text-xl"></i>
                    <span class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-light-muted/20 text-light-muted dark:text-dark-muted">Planned</span>
                </div>
                <h4 class="text-xs font-bold text-light-text dark:text-dark-text">Shopify Store Sync</h4>
                <p class="text-3xs text-light-muted dark:text-dark-muted">Automated live product catalog synchronization and order tracking.</p>
            </div>

            <div class="p-5 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/50 dark:bg-dark-surface/50 space-y-2 opacity-80">
                <div class="flex justify-between items-start">
                    <i class="ri-wordpress-fill text-sky-500 text-xl"></i>
                    <span class="px-2 py-0.5 rounded text-3xs font-mono font-bold bg-light-muted/20 text-light-muted dark:text-dark-muted">Planned</span>
                </div>
                <h4 class="text-xs font-bold text-light-text dark:text-dark-text">WooCommerce Plugin</h4>
                <p class="text-3xs text-light-muted dark:text-dark-muted">Native WordPress plugin for 1-click widget and inventory bridge.</p>
            </div>
        </div>
    </div>

    {{-- CTA --}}
    <div class="text-center pt-8">
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
            <span>Connect Your First Channel</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

</div>
@endsection
