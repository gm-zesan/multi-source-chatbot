@extends('layouts.public')

@section('title', 'Enterprise Pricing & Plans — Entrepreneurs Automation')
@section('meta_description', 'Flexible pricing tiers designed for emerging merchants, high-growth e-commerce brands, and enterprise retailers. Contact our team for customized deployment.')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 space-y-24">

    {{-- Hero --}}
    <div class="text-center space-y-4 max-w-3xl mx-auto">
        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-mono font-semibold bg-brand-500/10 text-brand-500 border border-brand-500/30">
            <i class="ri-price-tag-3-line"></i> Transparent Enterprise Tiers
        </div>
        <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight text-light-text dark:text-dark-text">
            Simple, Scalable Plans for High-Growth Commerce.
        </h1>
        <p class="text-sm sm:text-base text-light-text-secondary dark:text-dark-text-secondary leading-relaxed">
            Choose the tier that matches your monthly conversation volume and catalog complexity. Contact our team for bespoke enterprise deployments and dedicated SLA setups.
        </p>
    </div>

    {{-- Tier Cards Grid (Early Access / Contact for Custom Pricing) --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        {{-- Plan 1: Starter --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6 flex flex-col justify-between">
            <div class="space-y-4">
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Starter</h3>
                    <p class="text-2xs text-light-muted dark:text-dark-muted">For emerging social commerce stores and single-hub merchants.</p>
                </div>

                <div class="py-2 border-y border-light-border dark:border-dark-border">
                    <span class="text-2xl font-bold font-mono text-light-text dark:text-dark-text">Early Access</span>
                    <span class="text-3xs text-light-muted dark:text-dark-muted block">Custom monthly quota available</span>
                </div>

                <ul class="space-y-2 text-xs text-light-text-secondary dark:text-dark-text-secondary">
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> 1 Active Workspace</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Web Chat Widget Integration</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Spreadsheet & FAQ Knowledge Grounding</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Standard Latency Telemetry</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Email & Community Support</li>
                </ul>
            </div>

            <a href="{{ route('contact') }}" class="w-full text-center py-2.5 rounded-lg border border-light-border dark:border-dark-border hover:bg-light-elevated dark:hover:bg-dark-elevated text-xs font-semibold text-light-text dark:text-dark-text transition-colors">
                Request Early Access
            </a>
        </div>

        {{-- Plan 2: Growth (Featured) --}}
        <div class="relative p-8 rounded-2xl border-2 border-brand-500 bg-light-surface dark:bg-dark-surface shadow-sm dark:shadow-[0_8px_30px_rgb(0,0,0,0.3)] space-y-6 flex flex-col justify-between">
            <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 rounded-full bg-brand-500 text-white text-3xs font-mono font-bold uppercase tracking-wider">
                Most Popular
            </div>

            <div class="space-y-4">
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Growth</h3>
                    <p class="text-2xs text-light-muted dark:text-dark-muted">For high-volume e-commerce brands and multi-channel retailers.</p>
                </div>

                <div class="py-2 border-y border-light-border dark:border-dark-border">
                    <span class="text-2xl font-bold font-mono text-brand-500">Tailored Plan</span>
                    <span class="text-3xs text-light-muted dark:text-dark-muted block">Scales with conversation volume</span>
                </div>

                <ul class="space-y-2 text-xs text-light-text-secondary dark:text-dark-text-secondary">
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Multi-Channel (Messenger + Web Widget)</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Multi-Sheet Excel Catalog Relationships</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Unified Customer Inbox + Human Takeover</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Real-Time Telemetry & LLM Cost Wizard</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> CSV, Excel & PDF Dialogue Export</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Priority Technical Support</li>
                </ul>
            </div>

            <a href="{{ route('contact') }}" class="w-full text-center py-2.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-xs font-semibold shadow-xs hover:shadow-sm transition-all">
                Contact for Growth Plan
            </a>
        </div>

        {{-- Plan 3: Enterprise --}}
        <div class="p-8 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface space-y-6 flex flex-col justify-between">
            <div class="space-y-4">
                <div class="space-y-1">
                    <h3 class="text-lg font-bold text-light-text dark:text-dark-text">Enterprise</h3>
                    <p class="text-2xs text-light-muted dark:text-dark-muted">For large-scale retail chains, franchises, and high-security compliance.</p>
                </div>

                <div class="py-2 border-y border-light-border dark:border-dark-border">
                    <span class="text-2xl font-bold font-mono text-light-text dark:text-dark-text">Custom Quote</span>
                    <span class="text-3xs text-light-muted dark:text-dark-muted block">Bespoke SLA & dedicated deployment</span>
                </div>

                <ul class="space-y-2 text-xs text-light-text-secondary dark:text-dark-text-secondary">
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Unlimited Multi-Tenant Workspaces</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Custom LLM Model Integration & Routing</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Granular Spatie RBAC Role Delegation</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Dedicated REST API Quota & Custom Webhooks</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> 99.9% Uptime SLA Guarantee</li>
                    <li class="flex items-center gap-2"><i class="ri-checkbox-circle-fill text-brand-500"></i> Dedicated Solutions Architect</li>
                </ul>
            </div>

            <a href="{{ route('contact') }}" class="w-full text-center py-2.5 rounded-lg border border-light-border dark:border-dark-border hover:bg-light-elevated dark:hover:bg-dark-elevated text-xs font-semibold text-light-text dark:text-dark-text transition-colors">
                Talk to Enterprise Sales
            </a>
        </div>

    </div>

    {{-- Security & Deployment Note --}}
    <div class="p-6 rounded-2xl border border-light-border dark:border-dark-border bg-light-surface/40 dark:bg-dark-surface/40 text-center max-w-3xl mx-auto space-y-2">
        <h4 class="text-xs font-bold text-light-text dark:text-dark-text">Need custom volume or on-premise data compliance?</h4>
        <p class="text-2xs text-light-muted dark:text-dark-muted leading-relaxed">
            We work with enterprise procurement teams to meet data isolation, role-based governance, and localized regulatory requirements. Reach out to our engineering team directly through the contact page.
        </p>
    </div>

</div>
@endsection
