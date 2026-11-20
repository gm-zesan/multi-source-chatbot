@extends('admin.app')

@section('title')
    SaaS Commercial & Billing Dashboard
@endsection

@push('custom-style')
<style>
    .commercial-card-modern {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .commercial-card-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.03);
        border-color: #cbd5e1;
    }
    .comm-stat-val {
        font-size: 1.85rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .comm-stat-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .comm-icon-box {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-2">
    {{-- Header Section --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="mb-0 text-dark fw-bold">SaaS Commercial & Revenue Governance</h4>
                <span class="badge badge-soft-primary px-3 py-1.5 rounded-pill small fw-semibold">
                    Billing Period: {{ $period }}
                </span>
            </div>
            <p class="text-muted mb-0 small">
                Multi-tenant commercial subscription plans, monthly recurring revenue (MRR) tracking, and quota enforcement.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('workspaces.index') }}" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="ri-building-line"></i> Workspaces Directory
            </a>
            <a href="{{ route('workspace.settings.edit') }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="ri-settings-4-line"></i> Business Settings
            </a>
        </div>
    </div>

    {{-- Sandbox / Demo Mode Banner --}}
    <div class="alert alert-info border-0 shadow-sm d-flex align-items-center justify-content-between mb-4 p-3 rounded-3" role="alert" style="background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%); border-left: 4px solid #3b82f6 !important;">
        <div class="d-flex align-items-center gap-3">
            <div class="icon-box-primary">
                <i class="ri-shield-check-line fs-4"></i>
            </div>
            <div>
                <strong class="text-dark d-block">Commercial Sandbox Mode (Prototype UI)</strong>
                <span class="small text-muted">
                    Payment gateway verification is decoupled in prototype mode. Subscription cycles, tenant quota enforcement, and MRR metrics are actively simulated. Real gateways (Stripe, SSLCommerz, bKash) can be attached with API keys once finalized.
                </span>
            </div>
        </div>
        <span class="badge bg-primary px-3 py-2 text-uppercase rounded-pill fw-semibold shadow-sm">Sandbox Active</span>
    </div>

    {{-- Top 4 KPI Metrics --}}
    <div class="row g-3 mb-4">
        {{-- MRR Card --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="commercial-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="comm-stat-label">Monthly Recurring Revenue</div>
                        <div class="comm-stat-val mt-1 text-success">
                            ৳ {{ number_format($mrr, 2) }}
                        </div>
                    </div>
                    <div class="icon-box-success">
                        <i class="ri-money-dollar-box-line"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top small text-muted">
                    <span>Normalized MRR</span>
                    <span class="badge badge-soft-success">Active ARR / 12</span>
                </div>
            </div>
        </div>

        {{-- Active Tenants Card --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="commercial-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="comm-stat-label">Active Tenants</div>
                        <div class="comm-stat-val mt-1 text-primary">
                            {{ $activeTenants }} <span class="fs-6 text-muted fw-normal">/ {{ $totalTenants }}</span>
                        </div>
                    </div>
                    <div class="icon-box-primary">
                        <i class="ri-building-line"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top small text-muted">
                    <span>Tenant Utilization:</span>
                    <strong class="text-dark">{{ $totalTenants > 0 ? round(($activeTenants / $totalTenants) * 100) : 0 }}% active</strong>
                </div>
            </div>
        </div>

        {{-- Paid Subscriptions Card --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="commercial-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="comm-stat-label">Paid Subscriptions</div>
                        <div class="comm-stat-val mt-1 text-info">
                            {{ count($subscriptions) }}
                        </div>
                    </div>
                    <div class="icon-box-info">
                        <i class="ri-vip-crown-line"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top small text-muted">
                    <span>Recurring Billing Accounts</span>
                    <span class="badge badge-soft-info">Active</span>
                </div>
            </div>
        </div>

        {{-- Quota Overages Card --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="commercial-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="comm-stat-label">Quota Monitored</div>
                        <div class="comm-stat-val mt-1 text-dark">
                            {{ count($quotas) }}
                        </div>
                    </div>
                    <div class="icon-box-secondary">
                        <i class="ri-dashboard-3-line"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top small text-muted">
                    <span>Tenants tracking quotas</span>
                    <span class="badge bg-light text-muted border rounded-pill">Current Cycle</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Subscription Plans Table Card --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="ri-price-tag-3-line text-primary fs-5"></i>
                <h6 class="fw-bold mb-0 text-dark">Commercial SaaS Subscription Plans</h6>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Plan Name</th>
                            <th>Monthly Price</th>
                            <th>Yearly Price</th>
                            <th>Conversation Quota</th>
                            <th>Max Docs</th>
                            <th>Staff Seats</th>
                            <th class="text-end pe-4">Subscribers</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $plan->name }}</div>
                                    <code class="small text-muted">{{ $plan->slug }}</code>
                                </td>
                                <td>
                                    <span class="badge badge-soft-primary px-2.5 py-1">
                                        {{ $plan->currency }} {{ number_format((float) $plan->price_monthly, 2) }}
                                    </span>
                                </td>
                                <td>{{ $plan->currency }} {{ number_format((float) $plan->price_yearly, 2) }}</td>
                                <td>
                                    @if($plan->max_conversations_monthly === -1)
                                        <span class="badge badge-soft-success">Unlimited</span>
                                    @else
                                        <strong>{{ number_format($plan->max_conversations_monthly) }}</strong> / mo
                                    @endif
                                </td>
                                <td>{{ $plan->max_knowledge_docs }} files</td>
                                <td>{{ $plan->max_staff_seats }} seats</td>
                                <td class="text-end pe-4">
                                    <span class="badge bg-primary rounded-pill px-2.5 py-1">{{ $plan->subscriptions_count }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No subscription plans configured yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Bottom Two Columns: Active Subscriptions & Quota Usages --}}
    <div class="row g-3">
        {{-- Active Tenant Subscriptions --}}
        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-user-follow-line text-success fs-5"></i>
                        <h6 class="fw-bold mb-0 text-dark">Active Tenant Subscriptions</h6>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Workspace</th>
                                    <th>Plan</th>
                                    <th>Cycle</th>
                                    <th>Gateway</th>
                                    <th class="text-end pe-4">Expires</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subscriptions as $sub)
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark">{{ $sub->workspace?->name ?? 'N/A' }}</strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-soft-info">{{ $sub->plan?->name }}</span>
                                        </td>
                                        <td><span class="text-capitalize small text-muted">{{ $sub->billing_cycle }}</span></td>
                                        <td><code class="small">{{ strtoupper($sub->payment_gateway) }}</code></td>
                                        <td class="text-end pe-4 small text-muted">{{ $sub->ends_at ? $sub->ends_at->format('d M, Y') : 'Auto-renew' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No active subscriptions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quota Usage & Enforcement --}}
        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-speed-up-line text-primary fs-5"></i>
                        <h6 class="fw-bold mb-0 text-dark">Monthly Quota Enforcement ({{ $period }})</h6>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Workspace</th>
                                    <th>Conversations</th>
                                    <th>File Uploads</th>
                                    <th class="text-end pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($quotas as $q)
                                    <tr>
                                        <td class="ps-4">
                                            <strong class="text-dark">{{ $q->workspace?->name ?? 'N/A' }}</strong>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ number_format($q->conversations_count) }} / {{ $q->conversations_limit === -1 ? '∞' : number_format($q->conversations_limit) }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ $q->documents_uploaded }} docs</small>
                                        </td>
                                        <td class="text-end pe-4">
                                            @if($q->is_over_quota)
                                                <span class="badge badge-soft-danger">Quota Exceeded</span>
                                            @else
                                                <span class="badge badge-soft-success">Normal</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No quota records logged for current period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
