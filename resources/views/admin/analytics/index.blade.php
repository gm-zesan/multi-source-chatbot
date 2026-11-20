@extends('admin.app')

@section('title')
    Business Intelligence & Analytics
@endsection

@push('custom-style')
<style>
    .analytics-card-modern {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .analytics-card-modern:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -4px rgba(0, 0, 0, 0.03);
        border-color: #cbd5e1;
    }
    .kpi-num {
        font-size: 1.85rem;
        font-weight: 700;
        line-height: 1.2;
        color: #0f172a;
    }
    .kpi-title {
        font-size: 0.8rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .btn-range-filter {
        padding: 5px 14px;
        font-size: 0.82rem;
        font-weight: 500;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        background: #ffffff;
        color: #475569;
        transition: all 0.15s ease;
    }
    .btn-range-filter:hover {
        background: #f8fafc;
        color: #0f172a;
        border-color: #cbd5e1;
    }
    .btn-range-filter.active {
        background: #3b82f6;
        color: #ffffff;
        border-color: #3b82f6;
        box-shadow: 0 2px 6px rgba(59, 130, 246, 0.3);
    }
    .bar-chart-container {
        display: flex;
        align-items: flex-end;
        height: 190px;
        gap: 8px;
        padding-top: 24px;
        border-bottom: 1px solid #f1f5f9;
    }
    .bar-col {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        height: 100%;
        justify-content: flex-end;
    }
    .bar-fill {
        width: 100%;
        background: linear-gradient(180deg, #3b82f6 0%, #1d4ed8 100%);
        border-radius: 5px 5px 0 0;
        min-height: 5px;
        transition: height 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        cursor: pointer;
    }
    .bar-fill:hover {
        background: linear-gradient(180deg, #60a5fa 0%, #2563eb 100%);
    }
    .bar-label {
        font-size: 0.68rem;
        color: #64748b;
        margin-top: 8px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
        font-weight: 500;
    }
    .metric-icon-box {
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
    {{-- Top Header & Time Filter Bar --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="mb-0 text-dark fw-bold">Business Intelligence & Analytics</h4>
                <span class="badge badge-soft-primary px-3 py-1.5 rounded-pill small fw-semibold">
                    {{ $workspace->name }}
                </span>
            </div>
            <p class="text-muted mb-0 small">
                Real-time commerce telemetry, sales velocity, catalog inventory status, and sales rep performance.
            </p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="d-inline-flex p-1 bg-light rounded-3 border" id="rangeFilterGroup">
                <button type="button" class="btn btn-range-filter" data-range="today">Today</button>
                <button type="button" class="btn btn-range-filter" data-range="7d">7 Days</button>
                <button type="button" class="btn btn-range-filter active" data-range="30d">30 Days</button>
                <button type="button" class="btn btn-range-filter" data-range="90d">90 Days</button>
            </div>
            <a href="{{ route('workspace.settings.edit', ['workspace_id' => $workspace->id]) }}" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1 shadow-sm">
                <i class="ri-settings-4-line"></i> Business Settings
            </a>
        </div>
    </div>

    {{-- Top 4 KPI Metrics --}}
    <div class="row g-3 mb-4">
        {{-- Net Sales --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="analytics-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="kpi-title">Net Revenue</div>
                        <div class="kpi-num mt-1 text-primary" id="kpiNetSales">
                            {{ $initialData['currency'] }} {{ number_format($initialData['summary']['net_sales'], 2) }}
                        </div>
                    </div>
                    <div class="icon-box-primary">
                        <i class="ri-money-dollar-circle-line"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top small text-muted">
                    <span>Gross: {{ $initialData['currency'] }} <strong id="kpiGrossSales">{{ number_format($initialData['summary']['gross_sales'], 2) }}</strong></span>
                    <span class="badge badge-soft-primary">{{ $initialData['currency'] }}</span>
                </div>
            </div>
        </div>

        {{-- Total Orders --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="analytics-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="kpi-title">Completed Orders</div>
                        <div class="kpi-num mt-1 text-success" id="kpiTotalOrders">
                            {{ number_format($initialData['summary']['total_orders']) }}
                        </div>
                    </div>
                    <div class="icon-box-success">
                        <i class="ri-shopping-cart-2-line"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top small text-muted">
                    <span>Avg Order Value (AOV):</span>
                    <strong class="text-dark">{{ $initialData['currency'] }} <span id="kpiAvgOrder">{{ number_format($initialData['summary']['avg_order_value'], 2) }}</span></strong>
                </div>
            </div>
        </div>

        {{-- Total Discounts --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="analytics-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="kpi-title">Discounts Applied</div>
                        <div class="kpi-num mt-1 text-warning" id="kpiDiscounts">
                            {{ $initialData['currency'] }} {{ number_format($initialData['summary']['total_discount'], 2) }}
                        </div>
                    </div>
                    <div class="icon-box-warning">
                        <i class="ri-percent-line"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top small text-muted">
                    <span>Promotional deductions</span>
                    <span class="badge badge-soft-warning">Promos</span>
                </div>
            </div>
        </div>

        {{-- Customer Loyalty / Repeat Rate --}}
        <div class="col-xl-3 col-md-6 col-12">
            <div class="analytics-card-modern p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="kpi-title">Customer Base</div>
                        <div class="kpi-num mt-1 text-info" id="kpiCustomers">
                            {{ number_format($initialData['customers']['total']) }}
                        </div>
                    </div>
                    <div class="icon-box-info">
                        <i class="ri-user-heart-line"></i>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top small text-muted">
                    <span>Repeat Order Rate:</span>
                    <span class="badge badge-soft-info fw-bold">
                        <span id="kpiRepeatRate">{{ $initialData['customers']['repeat_rate'] }}</span>%
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Visual Chart & Inventory Health Row --}}
    <div class="row g-3 mb-4">
        {{-- Revenue Velocity Trend Chart --}}
        <div class="col-lg-8 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-line-chart-line text-primary fs-5"></i>
                        <h6 class="fw-bold mb-0 text-dark">Revenue Velocity Trend</h6>
                    </div>
                    <span class="badge bg-light text-muted border small" id="chartDateRange">
                        {{ $initialData['date_range']['start'] }} to {{ $initialData['date_range']['end'] }}
                    </span>
                </div>
                <div class="card-body p-4">
                    <div class="bar-chart-container" id="barChartContainer">
                        @php
                            $maxRevenue = !empty($initialData['sales_trend']['revenues']) ? max($initialData['sales_trend']['revenues']) : 1;
                            if ($maxRevenue <= 0) $maxRevenue = 1;
                        @endphp
                        @forelse($initialData['sales_trend']['dates'] as $idx => $date)
                            @php
                                $rev = $initialData['sales_trend']['revenues'][$idx] ?? 0;
                                $pct = round(($rev / $maxRevenue) * 100);
                            @endphp
                            <div class="bar-col" title="{{ $date }}: {{ $initialData['currency'] }} {{ number_format($rev, 2) }}">
                                <div class="bar-fill" style="height: {{ max(5, $pct) }}%;"></div>
                                <span class="bar-label">{{ date('d M', strtotime($date)) }}</span>
                            </div>
                        @empty
                            <div class="w-100 text-center text-muted my-auto py-5">
                                <i class="ri-bar-chart-box-line fs-2 opacity-50 d-block mb-2"></i>
                                No transaction data recorded in this period.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Inventory Health & SKU Status --}}
        <div class="col-lg-4 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-archive-2-line text-primary fs-5"></i>
                        <h6 class="fw-bold mb-0 text-dark">Catalog Inventory Health</h6>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="py-2 border-bottom d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="ri-stack-line me-1.5 text-primary"></i> Total Catalog SKUs</span>
                        <strong class="text-dark fs-6" id="invTotal">{{ $initialData['inventory']['total_skus'] }}</strong>
                    </div>
                    <div class="py-2 border-bottom d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="ri-checkbox-circle-line me-1.5 text-success"></i> Fully In Stock</span>
                        <span class="badge badge-soft-success" id="invInStock">
                            {{ $initialData['inventory']['in_stock'] }}
                        </span>
                    </div>
                    <div class="py-2 border-bottom d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="ri-alert-line me-1.5 text-warning"></i> Low Stock Threshold</span>
                        <span class="badge badge-soft-warning" id="invLowStock">
                            {{ $initialData['inventory']['low_stock'] }}
                        </span>
                    </div>
                    <div class="py-2 d-flex justify-content-between align-items-center">
                        <span class="text-muted small"><i class="ri-close-circle-line me-1.5 text-danger"></i> Out of Stock</span>
                        <span class="badge badge-soft-danger" id="invOutOfStock">
                            {{ $initialData['inventory']['out_of_stock'] }}
                        </span>
                    </div>

                    <div class="mt-4 p-3 bg-light rounded-3">
                        <small class="text-muted d-block mb-1"><i class="ri-shield-check-line text-primary me-1"></i> Grounded Bot Inventory</small>
                        <p class="small text-muted mb-0">
                            The AI chatbot verifies these stock levels before proposing order confirmations to prevent overselling.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Row: Top Performing Products & Sales Reps Leaderboard --}}
    <div class="row g-3">
        {{-- Top Products Table --}}
        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-trophy-line text-warning fs-5"></i>
                        <h6 class="fw-bold mb-0 text-dark">Top-Selling Products</h6>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="topProductsTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Product Name</th>
                                    <th>SKU</th>
                                    <th class="text-end">Units Sold</th>
                                    <th class="text-end pe-4">Total Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($initialData['top_products'] as $prod)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold text-dark">{{ $prod['name'] }}</div>
                                        </td>
                                        <td><code class="small text-muted">{{ $prod['sku'] }}</code></td>
                                        <td class="text-end fw-semibold text-dark">{{ number_format($prod['units_sold']) }}</td>
                                        <td class="text-end pe-4 text-primary fw-bold">{{ $initialData['currency'] }} {{ number_format($prod['total_revenue'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No product sales recorded in this period.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sales Rep Leaderboard --}}
        <div class="col-lg-6 col-12">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                        <i class="ri-user-star-line text-primary fs-5"></i>
                        <h6 class="fw-bold mb-0 text-dark">Sales Representatives Leaderboard</h6>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="salesRepsTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Representative</th>
                                    <th>Employee Code</th>
                                    <th class="text-end">Orders Closed</th>
                                    <th class="text-end pe-4">Revenue Generated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($initialData['sales_reps'] as $rep)
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-semibold text-dark">{{ $rep['name'] }}</div>
                                        </td>
                                        <td><code class="small text-muted">{{ $rep['employee_code'] }}</code></td>
                                        <td class="text-end fw-semibold text-dark">{{ number_format($rep['order_count']) }}</td>
                                        <td class="text-end pe-4 text-success fw-bold">{{ $initialData['currency'] }} {{ number_format($rep['sales'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No representative attribution recorded in this period.</td>
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

@push('custom-scripts')
<script>
    const currentWorkspaceId = '{{ $workspace->id }}';

    document.querySelectorAll('.btn-range-filter').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-range-filter').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            var range = this.getAttribute('data-range');
            fetchAnalyticsData(range);
        });
    });

    function fetchAnalyticsData(range) {
        const url = '{{ route('analytics.data') }}?range=' + encodeURIComponent(range) + '&workspace_id=' + encodeURIComponent(currentWorkspaceId);
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function(res) { return res.json(); })
        .then(function(json) {
            if (json.status === 'success') {
                updateDashboardView(json.data);
            }
        })
        .catch(function(err) {
            console.error('Failed to load analytics data', err);
        });
    }

    function updateDashboardView(data) {
        var curr = data.currency;
        document.getElementById('kpiNetSales').textContent = curr + ' ' + Number(data.summary.net_sales).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('kpiGrossSales').textContent = Number(data.summary.gross_sales).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('kpiTotalOrders').textContent = Number(data.summary.total_orders).toLocaleString();
        document.getElementById('kpiAvgOrder').textContent = Number(data.summary.avg_order_value).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('kpiDiscounts').textContent = curr + ' ' + Number(data.summary.total_discount).toLocaleString(undefined, {minimumFractionDigits: 2});
        document.getElementById('chartDateRange').textContent = data.date_range.start + ' to ' + data.date_range.end;
        if (data.customers) {
            document.getElementById('kpiCustomers').textContent = Number(data.customers.total).toLocaleString();
            document.getElementById('kpiRepeatRate').textContent = data.customers.repeat_rate;
        }

        // Render bar chart
        var container = document.getElementById('barChartContainer');
        container.innerHTML = '';
        var dates = data.sales_trend.dates || [];
        var revenues = data.sales_trend.revenues || [];
        var maxRev = revenues.length ? Math.max.apply(null, revenues) : 1;
        if (maxRev <= 0) maxRev = 1;

        if (dates.length === 0) {
            container.innerHTML = '<div class="w-100 text-center text-muted my-auto py-5"><i class="ri-bar-chart-box-line fs-2 opacity-50 d-block mb-2"></i>No transaction data recorded in this period.</div>';
        } else {
            dates.forEach(function(d, i) {
                var rev = revenues[i] || 0;
                var pct = Math.max(5, Math.round((rev / maxRev) * 100));
                var col = document.createElement('div');
                col.className = 'bar-col';
                col.title = d + ': ' + curr + ' ' + Number(rev).toLocaleString(undefined, {minimumFractionDigits: 2});
                col.innerHTML = '<div class="bar-fill" style="height: ' + pct + '%;"></div><span class="bar-label">' + d.substring(5) + '</span>';
                container.appendChild(col);
            });
        }
    }
</script>
@endpush
