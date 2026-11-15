@extends('admin.app')

@section('title')
    SaaS Commercial & Billing Dashboard
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 text-dark fw-bold">SaaS Commercial & Revenue Governance</h4>
            <p class="text-muted mb-0 small">Billing Period: <span class="badge bg-primary">{{ $period }}</span> · Multi-Tenant Platform Administration</p>
        </div>
    </div>

    {{-- Top Revenue Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-12">
            <div class="card table-card h-100">
                <div class="card-body">
                    <span class="text-muted text-uppercase small d-block mb-1">Monthly Recurring Revenue (MRR)</span>
                    <h3 class="text-success fw-bold mb-0">৳ {{ number_format($mrr, 2) }}</h3>
                    <small class="text-muted">Normalized active ARR / 12</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-12">
            <div class="card table-card h-100">
                <div class="card-body">
                    <span class="text-muted text-uppercase small d-block mb-1">Active Tenants</span>
                    <h3 class="text-primary fw-bold mb-0">{{ $activeTenants }} <span class="text-muted fs-6 fw-normal">/ {{ $totalTenants }}</span></h3>
                    <small class="text-muted">Registered businesses</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-12">
            <div class="card table-card h-100">
                <div class="card-body">
                    <span class="text-muted text-uppercase small d-block mb-1">Paid Subscriptions</span>
                    <h3 class="text-info fw-bold mb-0">{{ count($subscriptions) }}</h3>
                    <small class="text-muted">Active recurring accounts</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-12">
            <div class="card table-card h-100">
                <div class="card-body">
                    <span class="text-muted text-uppercase small d-block mb-1">Available Plans</span>
                    <h3 class="text-dark fw-bold mb-0">{{ count($plans) }}</h3>
                    <small class="text-muted">Tier configurations</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Subscription Plans --}}
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card table-card">
                <div class="card-header table-header">
                    <div class="table-title">Commercial Subscription Plans</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Plan Name</th>
                                    <th>Monthly Price</th>
                                    <th>Yearly Price</th>
                                    <th>Max Conversations / Mo</th>
                                    <th>Max Docs</th>
                                    <th>Staff Seats</th>
                                    <th>Subscribers</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($plans as $plan)
                                    <tr>
                                        <td><strong>{{ $plan->name }}</strong> <code class="small">({{ $plan->slug }})</code></td>
                                        <td>{{ $plan->currency }} {{ number_format((float) $plan->price_monthly, 2) }}</td>
                                        <td>{{ $plan->currency }} {{ number_format((float) $plan->price_yearly, 2) }}</td>
                                        <td>{{ $plan->max_conversations_monthly === -1 ? 'Unlimited' : number_format($plan->max_conversations_monthly) }}</td>
                                        <td>{{ $plan->max_knowledge_docs }}</td>
                                        <td>{{ $plan->max_staff_seats }}</td>
                                        <td><span class="badge bg-primary">{{ $plan->subscriptions_count }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">No plans configured yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Active Subscriptions & Usage Quotas --}}
    <div class="row g-3">
        <div class="col-lg-6 col-12">
            <div class="card table-card">
                <div class="card-header table-header">
                    <div class="table-title">Active Tenant Subscriptions</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Workspace</th>
                                    <th>Plan</th>
                                    <th>Cycle</th>
                                    <th>Gateway</th>
                                    <th>Expires</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subscriptions as $sub)
                                    <tr>
                                        <td><strong>{{ $sub->workspace?->name ?? 'N/A' }}</strong></td>
                                        <td><span class="badge bg-info">{{ $sub->plan?->name }}</span></td>
                                        <td>{{ ucfirst($sub->billing_cycle) }}</td>
                                        <td><code>{{ strtoupper($sub->payment_gateway) }}</code></td>
                                        <td>{{ $sub->ends_at?->format('d M, Y') ?? 'Never' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">No active subscriptions.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 col-12">
            <div class="card table-card">
                <div class="card-header table-header">
                    <div class="table-title">Tenant Monthly Usage Quotas ({{ $period }})</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Workspace</th>
                                    <th class="text-end">Conversations</th>
                                    <th class="text-end">AI Messages</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($quotas as $q)
                                    <tr>
                                        <td><strong>{{ $q->workspace?->name ?? 'N/A' }}</strong></td>
                                        <td class="text-end fw-bold">{{ number_format($q->conversation_count) }}</td>
                                        <td class="text-end text-primary">{{ number_format($q->ai_messages_count) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">No quota usage logged for this period.</td>
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
