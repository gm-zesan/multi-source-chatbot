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
