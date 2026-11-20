@extends('admin.app')

@section('title')
    Commercial & Billing Dashboard
@endsection

@section('content')
<div class="space-y-4">
    {{-- Header Section --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                <span>Commercial SaaS & Revenue Governance</span>
                <span class="app-badge app-badge-primary">Billing Cycle: {{ $period }}</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                Multi-tenant commercial subscription plans, monthly recurring revenue (MRR) tracking, and quota limits.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('workspaces.index') }}" class="app-btn app-btn-secondary">
                <i class="ri-building-line text-xs"></i>
                <span>Workspaces</span>
            </a>
            <a href="{{ route('workspace.settings.edit') }}" class="app-btn app-btn-secondary">
                <i class="ri-settings-4-line text-xs"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>

    {{-- Sandbox Notice Banner --}}
    <div class="p-3 rounded border border-brand-500/20 bg-brand-500/10 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded bg-brand-500/15 text-brand-500 flex items-center justify-center text-sm flex-shrink-0">
                <i class="ri-shield-check-line"></i>
            </div>
            <div>
                <strong class="text-xs font-semibold text-light-text dark:text-dark-text block">Commercial Sandbox Mode (Prototype UI)</strong>
                <span class="text-2xs text-light-text-secondary dark:text-dark-text-secondary">
                    Payment gateway verification is decoupled in prototype mode. Subscription cycles, tenant quota enforcement, and MRR metrics are actively simulated. Real gateways (Stripe, SSLCommerz, bKash) can be attached with API keys once finalized.
                </span>
            </div>
        </div>
        <span class="app-badge app-badge-primary uppercase flex-shrink-0">Sandbox Active</span>
    </div>

    {{-- Top 4 KPI Metrics --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {{-- MRR Card --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Monthly Recurring Revenue</div>
                    <div class="text-xl font-bold tracking-tight text-emerald-500 mt-0.5">
                        ৳ {{ number_format($mrr, 2) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-sm">
                    <i class="ri-money-dollar-box-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Normalized MRR</span>
                <span class="app-badge app-badge-success">Active ARR / 12</span>
            </div>
        </div>

        {{-- Active Tenants Card --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Active Tenants</div>
                    <div class="text-xl font-bold tracking-tight text-brand-500 mt-0.5">
                        {{ $activeTenants }} <span class="text-xs font-normal text-light-muted dark:text-dark-muted">/ {{ $totalTenants }}</span>
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-brand-500/15 text-brand-500 flex items-center justify-center text-sm">
                    <i class="ri-building-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Utilization:</span>
                <strong class="text-light-text dark:text-dark-text">{{ $totalTenants > 0 ? round(($activeTenants / $totalTenants) * 100) : 0 }}% active</strong>
            </div>
        </div>

        {{-- Paid Subscriptions Card --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Paid Subscriptions</div>
                    <div class="text-xl font-bold tracking-tight text-sky-500 mt-0.5">
                        {{ count($subscriptions) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-sky-500/15 text-sky-500 flex items-center justify-center text-sm">
                    <i class="ri-vip-crown-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Accounts</span>
                <span class="app-badge app-badge-secondary">Active</span>
            </div>
        </div>

        {{-- Quota Monitored Card --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Quota Monitored</div>
                    <div class="text-xl font-bold tracking-tight text-light-text dark:text-dark-text mt-0.5">
                        {{ count($quotas) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-light-elevated dark:bg-dark-elevated text-light-muted dark:text-dark-muted flex items-center justify-center text-sm">
                    <i class="ri-dashboard-3-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Tracking Cycles</span>
                <span class="app-badge app-badge-secondary">Current</span>
            </div>
        </div>
    </div>

    {{-- Subscription Plans Table Card --}}
    <div class="app-card overflow-hidden">
        <div class="app-card-header">
            <div class="flex items-center gap-2">
                <i class="ri-price-tag-3-line text-brand-500 text-sm"></i>
                <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Commercial SaaS Subscription Plans</h2>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th>Monthly</th>
                        <th>Yearly</th>
                        <th>Quota</th>
                        <th>Docs Limit</th>
                        <th>Staff Seats</th>
                        <th class="text-right">Subscribers</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        <tr>
                            <td>
                                <div class="font-bold text-light-text dark:text-dark-text">{{ $plan->name }}</div>
                                <code class="text-2xs text-light-muted dark:text-dark-muted font-mono">{{ $plan->slug }}</code>
                            </td>
                            <td>
                                <span class="app-badge app-badge-primary">
                                    {{ $plan->currency }} {{ number_format((float) $plan->price_monthly, 2) }}
                                </span>
                            </td>
                            <td>{{ $plan->currency }} {{ number_format((float) $plan->price_yearly, 2) }}</td>
                            <td>
                                @if($plan->max_conversations_monthly === -1)
                                    <span class="app-badge app-badge-success">Unlimited</span>
                                @else
                                    <strong>{{ number_format($plan->max_conversations_monthly) }}</strong> / mo
                                @endif
                            </td>
                            <td>{{ $plan->max_knowledge_docs }} files</td>
                            <td>{{ $plan->max_staff_seats }} seats</td>
                            <td class="text-right">
                                <span class="px-2 py-0.5 rounded-full text-2xs font-bold bg-brand-500 text-white font-mono">{{ $plan->subscriptions_count }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-light-muted dark:text-dark-muted py-6 text-xs">No subscription plans configured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Bottom Two Columns: Active Subscriptions & Quota Usages --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        {{-- Active Subscriptions --}}
        <div class="app-card overflow-hidden">
            <div class="app-card-header">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                    <i class="ri-user-follow-line text-emerald-500"></i>
                    <span>Active Tenant Subscriptions</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Plan</th>
                            <th>Cycle</th>
                            <th>Gateway</th>
                            <th class="text-right">Expires</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($subscriptions as $sub)
                            <tr>
                                <td>
                                    <strong class="text-light-text dark:text-dark-text">{{ $sub->workspace?->name ?? 'N/A' }}</strong>
                                </td>
                                <td>
                                    <span class="app-badge app-badge-primary">{{ $sub->plan?->name }}</span>
                                </td>
                                <td><span class="capitalize text-2xs text-light-muted dark:text-dark-muted">{{ $sub->billing_cycle }}</span></td>
                                <td><code class="text-2xs font-mono uppercase">{{ $sub->payment_gateway }}</code></td>
                                <td class="text-right text-2xs text-light-muted dark:text-dark-muted">{{ $sub->ends_at ? $sub->ends_at->format('d M, Y') : 'Auto-renew' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-light-muted dark:text-dark-muted py-6 text-xs">No active subscriptions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Quota Enforcement --}}
        <div class="app-card overflow-hidden">
            <div class="app-card-header">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                    <i class="ri-speed-up-line text-brand-500"></i>
                    <span>Quota Enforcement ({{ $period }})</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Conversations</th>
                            <th>Uploads</th>
                            <th class="text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($quotas as $q)
                            <tr>
                                <td>
                                    <strong class="text-light-text dark:text-dark-text">{{ $q->workspace?->name ?? 'N/A' }}</strong>
                                </td>
                                <td>
                                    <span class="text-2xs text-light-muted dark:text-dark-muted">{{ number_format($q->conversations_count) }} / {{ $q->conversations_limit === -1 ? '∞' : number_format($q->conversations_limit) }}</span>
                                </td>
                                <td>
                                    <span class="text-2xs text-light-muted dark:text-dark-muted">{{ $q->documents_uploaded }} docs</span>
                                </td>
                                <td class="text-right">
                                    @if($q->is_over_quota)
                                        <span class="app-badge app-badge-danger">Over Quota</span>
                                    @else
                                        <span class="app-badge app-badge-success">Normal</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-light-muted dark:text-dark-muted py-6 text-xs">No quota records logged for current period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
