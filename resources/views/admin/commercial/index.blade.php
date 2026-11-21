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
        <div class="app-card-header flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <i class="ri-price-tag-3-line text-brand-500 text-sm"></i>
                <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Commercial SaaS Subscription Plans</h2>
                <span class="app-badge app-badge-primary font-mono text-3xs">{{ $plans->count() }} Plans</span>
            </div>
            <button type="button" class="app-btn app-btn-primary !h-7 !px-2.5 text-2xs flex items-center gap-1.5" onclick="openCreatePlanModal()">
                <i class="ri-add-line"></i>
                <span>New Subscription Plan</span>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Plan Name & Slug</th>
                        <th>Monthly Price</th>
                        <th>Yearly Price</th>
                        <th>Monthly Quota</th>
                        <th>Limits (Docs & Seats)</th>
                        <th>Status</th>
                        <th>Subscribers</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        <tr>
                            <td>
                                <div class="font-bold text-light-text dark:text-dark-text flex items-center gap-1.5">
                                    <span>{{ $plan->name }}</span>
                                    @if($plan->slug === 'starter-trial')
                                        <span class="app-badge app-badge-secondary text-3xs font-mono">Default</span>
                                    @endif
                                </div>
                                <code class="text-2xs text-light-muted dark:text-dark-muted font-mono">{{ $plan->slug }}</code>
                                @if(!empty($plan->features) && count($plan->features) > 0)
                                    <div class="flex flex-wrap gap-1 mt-1">
                                        @foreach(array_slice($plan->features, 0, 3) as $feat)
                                            <span class="px-1.5 py-0.2 rounded text-3xs bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border text-light-muted dark:text-dark-muted">{{ $feat }}</span>
                                        @endforeach
                                        @if(count($plan->features) > 3)
                                            <span class="text-3xs text-light-muted dark:text-dark-muted">+{{ count($plan->features) - 3 }} more</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="app-badge app-badge-primary font-mono font-bold">
                                    {{ $plan->currency }} {{ number_format((float) $plan->price_monthly, 2) }}
                                </span>
                            </td>
                            <td>
                                <span class="text-xs font-mono text-light-text dark:text-dark-text">
                                    {{ $plan->currency }} {{ number_format((float) $plan->price_yearly, 2) }}
                                </span>
                            </td>
                            <td>
                                @if($plan->max_conversations_monthly === -1)
                                    <span class="app-badge app-badge-success">Unlimited</span>
                                @else
                                    <strong class="font-mono">{{ number_format($plan->max_conversations_monthly) }}</strong> <span class="text-3xs text-light-muted dark:text-dark-muted">/ mo</span>
                                @endif
                            </td>
                            <td>
                                <span class="text-xs text-light-text dark:text-dark-text font-mono">{{ $plan->max_knowledge_docs }} docs</span>
                                <span class="text-light-muted dark:text-dark-muted mx-1">•</span>
                                <span class="text-xs text-light-text dark:text-dark-text font-mono">{{ $plan->max_staff_seats }} seats</span>
                            </td>
                            <td>
                                @if($plan->is_active)
                                    <span class="app-badge app-badge-success text-3xs uppercase">Active</span>
                                @else
                                    <span class="app-badge app-badge-secondary text-3xs uppercase">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <span class="px-2 py-0.5 rounded-full text-2xs font-bold bg-brand-500/15 text-brand-500 font-mono">{{ $plan->subscriptions_count }}</span>
                            </td>
                            <td class="text-right">
                                <div class="inline-flex items-center gap-1">
                                    {{-- Edit Plan Button --}}
                                    <button 
                                        type="button" 
                                        class="app-btn app-btn-secondary !h-6 !w-6 !p-0 text-2xs" 
                                        title="Edit Plan"
                                        onclick='openEditPlanModal(@json($plan))'
                                    >
                                        <i class="ri-edit-line"></i>
                                    </button>

                                    {{-- Toggle Status --}}
                                    <form method="POST" action="{{ route('admin.commercial.plans.toggle', $plan) }}" class="inline">
                                        @csrf
                                        <button 
                                            type="submit" 
                                            class="app-btn app-btn-secondary !h-6 !w-6 !p-0 text-2xs {{ $plan->is_active ? 'text-amber-500 hover:text-amber-600' : 'text-emerald-500 hover:text-emerald-600' }}" 
                                            title="{{ $plan->is_active ? 'Deactivate Plan' : 'Activate Plan' }}"
                                        >
                                            <i class="{{ $plan->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}"></i>
                                        </button>
                                    </form>

                                    {{-- Delete Plan --}}
                                    <form method="POST" action="{{ route('admin.commercial.plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete plan \'{{ $plan->name }}\'?');">
                                        @csrf
                                        @method('DELETE')
                                        <button 
                                            type="submit" 
                                            class="app-btn app-btn-secondary !h-6 !w-6 !p-0 text-2xs text-rose-500 hover:text-rose-600" 
                                            title="Delete Plan"
                                        >
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-light-muted dark:text-dark-muted py-6 text-xs">No subscription plans configured yet. Click "New Subscription Plan" to create one.</td>
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

    {{-- Tenant Prepaid Wallets (৳0.40 Metered Usage Model) --}}
    <div class="app-card overflow-hidden">
        <div class="p-3.5 border-b border-light-border dark:border-dark-border flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-xs font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-wallet-3-line text-brand-500"></i>
                    <span>Tenant Prepaid Wallets & Metered Balances (৳0.40/msg)</span>
                </h3>
                <p class="text-3xs text-light-muted dark:text-dark-muted mt-0.5">
                    Prepaid wallet balances deducted in real-time per AI response across omnichannels.
                </p>
            </div>
            <span class="app-badge app-badge-primary text-3xs uppercase">Option 1 Active</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-light-canvas/70 dark:bg-dark-canvas/70 border-b border-light-border dark:border-dark-border text-2xs uppercase tracking-wider text-light-muted dark:text-dark-muted font-semibold">
                    <tr>
                        <th class="py-2.5 px-4">Workspace</th>
                        <th class="py-2.5 px-3">Prepaid Balance</th>
                        <th class="py-2.5 px-3">Est. Messages Left</th>
                        <th class="py-2.5 px-3">Rate</th>
                        <th class="py-2.5 px-3">Status</th>
                        <th class="py-2.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-light-border dark:divide-dark-border text-light-text dark:text-dark-text">
                    @forelse($tenantsWithWallets as $ws)
                        @php
                            $w = $ws->wallet;
                            $balance = $w ? (float)$w->balance : 50.00;
                            $rate = $w ? (float)$w->rate_per_message : 0.40;
                            $remaining = (int) floor($balance / max($rate, 0.01));
                            $isFrozen = $w ? $w->is_frozen : false;
                        @endphp
                        <tr class="hover:bg-light-canvas/40 dark:hover:bg-dark-canvas/40 transition">
                            <td class="py-3 px-4 font-semibold text-light-text dark:text-dark-text whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded bg-brand-500/10 text-brand-500 flex items-center justify-center text-xs">
                                        <i class="ri-building-line"></i>
                                    </div>
                                    <span>{{ $ws->name }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-3 font-mono font-bold text-light-text dark:text-dark-text whitespace-nowrap">
                                ৳ {{ number_format($balance, 2) }}
                            </td>
                            <td class="py-3 px-3 font-semibold text-emerald-500 whitespace-nowrap">
                                {{ number_format($remaining) }} msgs
                            </td>
                            <td class="py-3 px-3 text-2xs text-light-muted dark:text-dark-muted font-mono whitespace-nowrap">
                                ৳{{ number_format($rate, 2) }}/msg
                            </td>
                            <td class="py-3 px-3 whitespace-nowrap">
                                @if($isFrozen)
                                    <span class="app-badge app-badge-danger text-3xs font-bold uppercase">Frozen</span>
                                @elseif($balance < 20.00)
                                    <span class="app-badge app-badge-warning text-3xs font-bold uppercase">Low</span>
                                @else
                                    <span class="app-badge app-badge-success text-3xs font-bold uppercase">Active</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <button 
                                    type="button" 
                                    class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs inline-flex items-center gap-1"
                                    onclick="openWalletAdjustModal('{{ $ws->id }}', '{{ addslashes($ws->name) }}', '{{ $balance }}')"
                                >
                                    <i class="ri-money-dollar-circle-line text-brand-500"></i>
                                    <span>Adjust Balance</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-light-muted dark:text-dark-muted text-xs">
                                No tenants found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tenantsWithWallets->hasPages())
            <div class="p-3 border-t border-light-border dark:border-dark-border">
                {{ $tenantsWithWallets->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Modal: Create Subscription Plan --}}
<div class="modal fade" id="createPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-add-box-line text-brand-500"></i>
                    <span>Create New Subscription Plan</span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form method="POST" action="{{ route('admin.commercial.plans.store') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Plan Name --}}
                    <div>
                        <label for="plan_name" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Plan Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="plan_name" 
                            name="name" 
                            required 
                            placeholder="e.g. Starter, Growth Commerce, Enterprise"
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Plan Slug --}}
                    <div>
                        <label for="plan_slug" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Plan Slug <span class="text-2xs text-light-muted dark:text-dark-muted">(optional, unique identifier)</span>
                        </label>
                        <input 
                            type="text" 
                            id="plan_slug" 
                            name="slug" 
                            placeholder="e.g. growth-commerce (auto-generated if empty)"
                            class="app-input w-full text-xs font-mono"
                        >
                    </div>

                    {{-- Badge --}}
                    <div>
                        <label for="plan_badge" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Card Badge <span class="text-2xs text-light-muted dark:text-dark-muted">(e.g. Standard Metered, Free Trial, Most Popular)</span>
                        </label>
                        <input 
                            type="text" 
                            id="plan_badge" 
                            name="badge" 
                            placeholder="e.g. Free Trial or Most Popular"
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Tagline --}}
                    <div>
                        <label for="plan_tagline" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Tagline / Subtitle
                        </label>
                        <input 
                            type="text" 
                            id="plan_tagline" 
                            name="tagline" 
                            placeholder="e.g. Ideal for emerging social commerce brands"
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Monthly Price --}}
                    <div>
                        <label for="plan_price_monthly" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Monthly / Unit Price (BDT) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-light-muted dark:text-dark-muted text-xs">৳</span>
                            <input 
                                type="number" 
                                id="plan_price_monthly" 
                                name="price_monthly" 
                                step="0.01" 
                                required 
                                placeholder="0.40 or 999.00"
                                class="app-input w-full pl-8 font-mono text-xs"
                            >
                        </div>
                    </div>

                    {{-- Billing Period / Suffix Text --}}
                    <div>
                        <label for="plan_billing_period_text" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Price Suffix / Label
                        </label>
                        <input 
                            type="text" 
                            id="plan_billing_period_text" 
                            name="billing_period_text" 
                            placeholder="e.g. / AI reply, Free Credit, / month, Volume Discount"
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Price Note --}}
                    <div class="md:col-span-2">
                        <label for="plan_price_note" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Price Subtext Note
                        </label>
                        <input 
                            type="text" 
                            id="plan_price_note" 
                            name="price_note" 
                            placeholder="e.g. Zero monthly commitment • Deducted per response"
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Yearly Price --}}
                    <div>
                        <label for="plan_price_yearly" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Yearly Price (BDT) <span class="text-2xs text-light-muted dark:text-dark-muted">(optional)</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-light-muted dark:text-dark-muted text-xs">৳</span>
                            <input 
                                type="number" 
                                id="plan_price_yearly" 
                                name="price_yearly" 
                                step="0.01" 
                                placeholder="9990.00"
                                class="app-input w-full pl-8 font-mono text-xs"
                            >
                        </div>
                    </div>

                    {{-- Currency --}}
                    <div>
                        <label for="plan_currency" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Currency <span class="text-rose-500">*</span>
                        </label>
                        <select id="plan_currency" name="currency" class="app-input w-full text-xs">
                            <option value="BDT" selected>BDT (৳)</option>
                            <option value="USD">USD ($)</option>
                        </select>
                    </div>

                    {{-- Monthly Messages Quota --}}
                    <div>
                        <label for="plan_max_conversations" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Monthly AI Messages Quota <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="plan_max_conversations" 
                            name="max_conversations_monthly" 
                            required 
                            value="1000"
                            placeholder="e.g. 1000 or -1 for unlimited"
                            class="app-input w-full text-xs font-mono"
                        >
                        <span class="text-3xs text-light-muted dark:text-dark-muted block mt-0.5">Use <code>-1</code> for Unlimited conversation capacity</span>
                    </div>

                    {{-- Max Knowledge Docs --}}
                    <div>
                        <label for="plan_max_docs" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Max Knowledge Docs <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="plan_max_docs" 
                            name="max_knowledge_docs" 
                            required 
                            value="10"
                            class="app-input w-full text-xs font-mono"
                        >
                    </div>

                    {{-- Max Staff Seats --}}
                    <div>
                        <label for="plan_max_seats" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Max Staff Seats <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="plan_max_seats" 
                            name="max_staff_seats" 
                            required 
                            value="3"
                            class="app-input w-full text-xs font-mono"
                        >
                    </div>

                    {{-- Features List --}}
                    <div class="md:col-span-2">
                        <label for="plan_features" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Included Features <span class="text-2xs text-light-muted dark:text-dark-muted">(one per line or comma-separated)</span>
                        </label>
                        <textarea 
                            id="plan_features" 
                            name="features" 
                            rows="3" 
                            placeholder="Omnichannel AI (Messenger, WhatsApp, TikTok, Web)&#10;Automated Order & Lead Extraction&#10;Excel & Knowledge Grounding&#10;Live Human Agent Takeover&#10;bKash & Nagad Top-Up"
                            class="app-input w-full text-xs font-sans leading-relaxed"
                        ></textarea>
                    </div>

                    {{-- CTA Button Text --}}
                    <div>
                        <label for="plan_cta_text" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            CTA Button Text
                        </label>
                        <input 
                            type="text" 
                            id="plan_cta_text" 
                            name="cta_text" 
                            placeholder="e.g. Get Started with ৳50 Bonus"
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- CTA Button URL --}}
                    <div>
                        <label for="plan_cta_url" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            CTA Button Link
                        </label>
                        <input 
                            type="text" 
                            id="plan_cta_url" 
                            name="cta_url" 
                            placeholder="e.g. /register or /contact"
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Sort Order --}}
                    <div>
                        <label for="plan_sort_order" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Display Sort Order
                        </label>
                        <input 
                            type="number" 
                            id="plan_sort_order" 
                            name="sort_order" 
                            value="0"
                            class="app-input w-full text-xs font-mono"
                        >
                    </div>

                    {{-- Featured Checkbox --}}
                    <div class="flex items-center gap-2 pt-5">
                        <input 
                            type="checkbox" 
                            id="plan_is_featured" 
                            name="is_featured" 
                            value="1" 
                            class="w-4 h-4 rounded text-brand-500 border-light-border dark:border-dark-border focus:ring-brand-500"
                        >
                        <label for="plan_is_featured" class="text-xs font-medium text-light-text dark:text-dark-text select-none cursor-pointer">
                            Highlight as Featured / Most Popular Plan
                        </label>
                    </div>

                    {{-- Active Checkbox --}}
                    <div class="md:col-span-2 flex items-center gap-2 pt-1">
                        <input 
                            type="checkbox" 
                            id="plan_is_active" 
                            name="is_active" 
                            value="1" 
                            checked 
                            class="w-4 h-4 rounded text-brand-500 border-light-border dark:border-dark-border focus:ring-brand-500"
                        >
                        <label for="plan_is_active" class="text-xs font-medium text-light-text dark:text-dark-text select-none cursor-pointer">
                            Active (Make this plan immediately available for workspaces and subscription assignment)
                        </label>
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5 shadow-md">
                        <i class="ri-check-line"></i>
                        <span>Create Plan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: Edit Subscription Plan --}}
<div class="modal fade" id="editPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-edit-line text-brand-500"></i>
                    <span>Edit Subscription Plan</span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form id="editPlanForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Plan Name --}}
                    <div>
                        <label for="edit_plan_name" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Plan Name <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="edit_plan_name" 
                            name="name" 
                            required 
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Plan Slug --}}
                    <div>
                        <label for="edit_plan_slug" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Plan Slug
                        </label>
                        <input 
                            type="text" 
                            id="edit_plan_slug" 
                            name="slug" 
                            class="app-input w-full text-xs font-mono"
                        >
                    </div>

                    {{-- Badge --}}
                    <div>
                        <label for="edit_plan_badge" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Card Badge <span class="text-2xs text-light-muted dark:text-dark-muted">(e.g. Standard Metered, Free Trial, Most Popular)</span>
                        </label>
                        <input 
                            type="text" 
                            id="edit_plan_badge" 
                            name="badge" 
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Tagline --}}
                    <div>
                        <label for="edit_plan_tagline" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Tagline / Subtitle
                        </label>
                        <input 
                            type="text" 
                            id="edit_plan_tagline" 
                            name="tagline" 
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Monthly Price --}}
                    <div>
                        <label for="edit_plan_price_monthly" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Monthly / Unit Price (BDT) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-light-muted dark:text-dark-muted text-xs">৳</span>
                            <input 
                                type="number" 
                                id="edit_plan_price_monthly" 
                                name="price_monthly" 
                                step="0.01" 
                                required 
                                class="app-input w-full pl-8 font-mono text-xs"
                            >
                        </div>
                    </div>

                    {{-- Billing Period / Suffix Text --}}
                    <div>
                        <label for="edit_plan_billing_period_text" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Price Suffix / Label
                        </label>
                        <input 
                            type="text" 
                            id="edit_plan_billing_period_text" 
                            name="billing_period_text" 
                            placeholder="e.g. / AI reply, Free Credit, / month, Volume Discount"
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Price Note --}}
                    <div class="md:col-span-2">
                        <label for="edit_plan_price_note" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Price Subtext Note
                        </label>
                        <input 
                            type="text" 
                            id="edit_plan_price_note" 
                            name="price_note" 
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Yearly Price --}}
                    <div>
                        <label for="edit_plan_price_yearly" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Yearly Price (BDT)
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-light-muted dark:text-dark-muted text-xs">৳</span>
                            <input 
                                type="number" 
                                id="edit_plan_price_yearly" 
                                name="price_yearly" 
                                step="0.01" 
                                class="app-input w-full pl-8 font-mono text-xs"
                            >
                        </div>
                    </div>

                    {{-- Currency --}}
                    <div>
                        <label for="edit_plan_currency" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Currency <span class="text-rose-500">*</span>
                        </label>
                        <select id="edit_plan_currency" name="currency" class="app-input w-full text-xs">
                            <option value="BDT">BDT (৳)</option>
                            <option value="USD">USD ($)</option>
                        </select>
                    </div>

                    {{-- Monthly Messages Quota --}}
                    <div>
                        <label for="edit_plan_max_conversations" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Monthly AI Messages Quota <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="edit_plan_max_conversations" 
                            name="max_conversations_monthly" 
                            required 
                            class="app-input w-full text-xs font-mono"
                        >
                        <span class="text-3xs text-light-muted dark:text-dark-muted block mt-0.5">Use <code>-1</code> for Unlimited</span>
                    </div>

                    {{-- Max Knowledge Docs --}}
                    <div>
                        <label for="edit_plan_max_docs" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Max Knowledge Docs <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="edit_plan_max_docs" 
                            name="max_knowledge_docs" 
                            required 
                            class="app-input w-full text-xs font-mono"
                        >
                    </div>

                    {{-- Max Staff Seats --}}
                    <div>
                        <label for="edit_plan_max_seats" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Max Staff Seats <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="number" 
                            id="edit_plan_max_seats" 
                            name="max_staff_seats" 
                            required 
                            class="app-input w-full text-xs font-mono"
                        >
                    </div>

                    {{-- Features List --}}
                    <div class="md:col-span-2">
                        <label for="edit_plan_features" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Included Features <span class="text-2xs text-light-muted dark:text-dark-muted">(one per line or comma-separated)</span>
                        </label>
                        <textarea 
                            id="edit_plan_features" 
                            name="features" 
                            rows="3" 
                            class="app-input w-full text-xs font-sans leading-relaxed"
                        ></textarea>
                    </div>

                    {{-- CTA Button Text --}}
                    <div>
                        <label for="edit_plan_cta_text" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            CTA Button Text
                        </label>
                        <input 
                            type="text" 
                            id="edit_plan_cta_text" 
                            name="cta_text" 
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- CTA Button URL --}}
                    <div>
                        <label for="edit_plan_cta_url" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            CTA Button Link
                        </label>
                        <input 
                            type="text" 
                            id="edit_plan_cta_url" 
                            name="cta_url" 
                            class="app-input w-full text-xs"
                        >
                    </div>

                    {{-- Sort Order --}}
                    <div>
                        <label for="edit_plan_sort_order" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Display Sort Order
                        </label>
                        <input 
                            type="number" 
                            id="edit_plan_sort_order" 
                            name="sort_order" 
                            class="app-input w-full text-xs font-mono"
                        >
                    </div>

                    {{-- Featured Checkbox --}}
                    <div class="flex items-center gap-2 pt-5">
                        <input 
                            type="checkbox" 
                            id="edit_plan_is_featured" 
                            name="is_featured" 
                            value="1" 
                            class="w-4 h-4 rounded text-brand-500 border-light-border dark:border-dark-border focus:ring-brand-500"
                        >
                        <label for="edit_plan_is_featured" class="text-xs font-medium text-light-text dark:text-dark-text select-none cursor-pointer">
                            Highlight as Featured / Most Popular Plan
                        </label>
                    </div>

                    {{-- Active Checkbox --}}
                    <div class="md:col-span-2 flex items-center gap-2 pt-1">
                        <input 
                            type="checkbox" 
                            id="edit_plan_is_active" 
                            name="is_active" 
                            value="1" 
                            class="w-4 h-4 rounded text-brand-500 border-light-border dark:border-dark-border focus:ring-brand-500"
                        >
                        <label for="edit_plan_is_active" class="text-xs font-medium text-light-text dark:text-dark-text select-none cursor-pointer">
                            Active (Make this plan immediately available for workspaces and subscription assignment)
                        </label>
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5 shadow-md">
                        <i class="ri-check-line"></i>
                        <span>Update Plan</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal: SuperAdmin Wallet Adjustment --}}
<div class="modal fade" id="adjustWalletModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-2xl border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface shadow-2xl p-5">
            <div class="flex items-center justify-between pb-3 border-b border-light-border dark:border-dark-border mb-4">
                <h3 class="text-sm font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                    <i class="ri-money-dollar-circle-line text-brand-500"></i>
                    <span>Adjust Tenant Prepaid Balance</span>
                </h3>
                <button type="button" class="text-light-muted hover:text-light-text dark:text-dark-muted dark:hover:text-dark-text" data-bs-dismiss="modal">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>

            <form id="adjustWalletForm" method="POST" action="">
                @csrf
                <div class="space-y-4">
                    <div class="p-3 rounded-lg bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border text-2xs">
                        <div class="text-light-muted dark:text-dark-muted">Selected Workspace:</div>
                        <strong id="modalWorkspaceName" class="text-xs text-light-text dark:text-dark-text block mt-0.5"></strong>
                        <div class="text-light-muted dark:text-dark-muted mt-1">
                            Current Balance: <span id="modalCurrentBalance" class="font-mono font-bold text-brand-500"></span>
                        </div>
                    </div>

                    <div>
                        <label for="adj_amount" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Adjustment Amount (BDT) <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-light-muted dark:text-dark-muted text-xs">৳</span>
                            <input 
                                type="number" 
                                id="adj_amount" 
                                name="amount" 
                                step="0.01" 
                                required 
                                placeholder="e.g. 500.00 for credit, or -50.00 for debit"
                                class="app-input w-full pl-8 font-bold text-sm"
                            >
                        </div>
                        <p class="text-3xs text-light-muted dark:text-dark-muted mt-1">
                            Positive numbers credit/top-up the wallet. Negative numbers debit/deduct from the wallet.
                        </p>
                    </div>

                    <div>
                        <label for="adj_reason" class="block text-xs font-semibold text-light-text dark:text-dark-text mb-1">
                            Reason / Audit Note <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="adj_reason" 
                            name="reason" 
                            required 
                            placeholder="e.g. Promotional credit, offline bKash payment verification, or billing correction"
                            class="app-input w-full text-xs"
                        >
                    </div>
                </div>

                <div class="mt-5 pt-3 border-t border-light-border dark:border-dark-border flex justify-end gap-2">
                    <button type="button" class="app-btn app-btn-secondary text-xs" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="app-btn app-btn-primary text-xs flex items-center gap-1.5 shadow-md">
                        <i class="ri-check-line"></i>
                        <span>Apply Adjustment</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreatePlanModal() {
    const modalEl = document.getElementById('createPlanModal');
    if (modalEl && window.bootstrap) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function openEditPlanModal(plan) {
    const form = document.getElementById('editPlanForm');
    if (form) {
        form.action = '/admin/commercial/plans/' + plan.id;
    }

    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.value = (val !== null && val !== undefined) ? val : '';
    };

    setVal('edit_plan_name', plan.name);
    setVal('edit_plan_slug', plan.slug);
    setVal('edit_plan_badge', plan.badge);
    setVal('edit_plan_tagline', plan.tagline);
    setVal('edit_plan_price_monthly', plan.price_monthly);
    setVal('edit_plan_billing_period_text', plan.billing_period_text);
    setVal('edit_plan_price_note', plan.price_note);
    setVal('edit_plan_price_yearly', plan.price_yearly);
    setVal('edit_plan_currency', plan.currency || 'BDT');
    setVal('edit_plan_max_conversations', plan.max_conversations_monthly);
    setVal('edit_plan_max_docs', plan.max_knowledge_docs);
    setVal('edit_plan_max_seats', plan.max_staff_seats);
    setVal('edit_plan_cta_text', plan.cta_text);
    setVal('edit_plan_cta_url', plan.cta_url);
    setVal('edit_plan_sort_order', plan.sort_order);

    const featEl = document.getElementById('edit_plan_features');
    if (featEl) {
        featEl.value = Array.isArray(plan.features) ? plan.features.join('\n') : (plan.features || '');
    }

    const featuredEl = document.getElementById('edit_plan_is_featured');
    if (featuredEl) {
        featuredEl.checked = Boolean(plan.is_featured);
    }

    const activeEl = document.getElementById('edit_plan_is_active');
    if (activeEl) {
        activeEl.checked = Boolean(plan.is_active);
    }

    const modalEl = document.getElementById('editPlanModal');
    if (modalEl && window.bootstrap) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function openWalletAdjustModal(workspaceId, workspaceName, currentBalance) {
    const form = document.getElementById('adjustWalletForm');
    const nameEl = document.getElementById('modalWorkspaceName');
    const balEl = document.getElementById('modalCurrentBalance');

    if (form) {
        form.action = '/admin/commercial/wallets/' + workspaceId + '/adjust';
    }
    if (nameEl) nameEl.innerText = workspaceName;
    if (balEl) balEl.innerText = '৳ ' + parseFloat(currentBalance).toFixed(2);

    const modalEl = document.getElementById('adjustWalletModal');
    if (modalEl && window.bootstrap) {
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }
}
</script>
@endsection
