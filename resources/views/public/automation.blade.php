@extends('layouts.public')

@section('title', 'Intelligent Workflow Automation — Entrepreneurs Automation')
@section('meta_description', 'Automate repetitive inquiries, order lookups, and customer handoffs with sub-second execution speed and enterprise reliability.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-emerald-500/10 text-emerald-500 border border-emerald-500/30">
            <i class="ri-cpu-line"></i> Sub-Second Automation
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            Smarter Operations. Automated Workflows.
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            Eliminate repetitive support tickets and manual lookups. Connect customer communication directly with verified catalog data and order logistics.
        </p>
    </div>

    {{-- 3 Workflow Use Cases --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
            <i class="ri-truck-line text-brand-500 text-2xl"></i>
            <h3 class="text-base font-bold text-light-text dark:text-dark-text">Order & Delivery Queries</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Automatically verify tracking status, estimated shipping arrival, and courier charges without manual agent intervention.
            </p>
        </div>

        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
            <i class="ri-refresh-line text-sky-500 text-2xl"></i>
            <h3 class="text-base font-bold text-light-text dark:text-dark-text">Return & Warranty Auditing</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Check whether a customer’s item falls within the 7-day return policy or warranty window using exact document clauses.
            </p>
        </div>

        <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-3">
            <i class="ri-user-star-line text-emerald-500 text-2xl"></i>
            <h3 class="text-base font-bold text-light-text dark:text-dark-text">Lead Qualification</h3>
            <p class="text-xs text-light-muted dark:text-dark-muted leading-relaxed">
                Collect buyer phone number, delivery address, and SKU variant interest, delivering pre-qualified leads directly into the unified queue.
            </p>
        </div>
    </div>

    {{-- Workflow Node Graphic Mockup --}}
    <div class="p-6 sm:p-10 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6">
        <h2 class="text-xs font-mono uppercase tracking-wider font-bold text-light-muted dark:text-dark-muted">
            Autonomous Decision Pipeline
        </h2>

        <div class="p-4 rounded-xl bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border space-y-3 font-mono text-2xs">
            <div class="flex items-center gap-3">
                <i class="ri-play-circle-line text-brand-500 text-sm"></i>
                <span class="text-light-text dark:text-dark-text font-bold">Trigger: Incoming Message on Storefront</span>
            </div>
            <div class="pl-5 border-l-2 border-dashed border-light-border dark:border-dark-border space-y-2 py-1">
                <div class="p-2 rounded bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted">
                    1. Intent Classification: <strong class="text-light-text dark:text-dark-text">Product Availability & Price</strong>
                </div>
                <div class="p-2 rounded bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted">
                    2. Knowledge Retrieval: <strong class="text-emerald-500">Inventory.xlsx matched with 98% confidence</strong>
                </div>
                <div class="p-2 rounded bg-light-surface dark:bg-dark-surface text-light-muted dark:text-dark-muted">
                    3. Safety Check: <strong class="text-sky-500">No unverified data detected</strong>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <i class="ri-checkbox-circle-fill text-emerald-500 text-sm"></i>
                <span class="text-emerald-500 font-bold">Action: Grounded Response Dispatched (210ms total execution)</span>
            </div>
        </div>
    </div>

    {{-- CTA --}}
    <div class="text-center pt-8">
        <a href="{{ route('register') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-brand-500 hover:bg-brand-600 text-white font-semibold text-xs shadow-xs hover:shadow-sm transition-all">
            <span>Automate Your Workflows</span>
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>

</div>
@endsection
