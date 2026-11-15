@extends('admin.app')

@section('title')
    Business Intelligence & Analytics
@endsection

@push('custom-style')
<style>
    .analytics-card {
        border-radius: 10px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
        border: 1px solid #eef2f6;
    }
    .analytics-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .kpi-value {
        font-size: 1.85rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .kpi-label {
        font-size: 0.82rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .btn-range.active {
        background-color: #0d6efd;
        color: #fff;
        border-color: #0d6efd;
    }
    .bar-chart-container {
        display: flex;
        align-items: flex-end;
        height: 180px;
        gap: 6px;
        padding-top: 20px;
        border-bottom: 1px solid #e2e8f0;
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
        border-radius: 4px 4px 0 0;
        min-height: 4px;
        transition: height 0.3s ease;
    }
    .bar-label {
        font-size: 0.65rem;
        color: #64748b;
        margin-top: 6px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    {{-- Header & Range Filters --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 text-dark fw-bold">Business Intelligence & Analytics</h4>
            <p class="text-muted mb-0 small">Workspace: <strong>{{ $workspace->name }}</strong> · Currency: <span class="badge bg-light text-dark border">{{ $initialData['currency'] }}</span></p>
        </div>
        <div class="btn-group mt-2 mt-md-0" role="group" id="rangeFilterGroup">
            <button type="button" class="btn btn-outline-primary btn-sm btn-range" data-range="today">Today</button>
            <button type="button" class="btn btn-outline-primary btn-sm btn-range" data-range="7d">7 Days</button>
            <button type="button" class="btn btn-outline-primary btn-sm btn-range active" data-range="30d">30 Days</button>
            <button type="button" class="btn btn-outline-primary btn-sm btn-range" data-range="90d">90 Days</button>
        </div>
    </div>

    {{-- Top KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6 col-12">
            <div class="card analytics-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="kpi-label">Net Sales</span>
                        <div class="p-2 bg-primary bg-opacity-10 rounded text-primary">
                            <i class="ri-money-dollar-circle-line fs-4"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-primary" id="kpiNetSales">
                        {{ $initialData['currency'] }} {{ number_format($initialData['summary']['net_sales'], 2) }}
                    </div>
                    <small class="text-muted d-block mt-1">
                        Gross: {{ $initialData['currency'] }} <span id="kpiGrossSales">{{ number_format($initialData['summary']['gross_sales'], 2) }}</span>
                    </small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
            <div class="card analytics-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="kpi-label">Total Orders</span>
                        <div class="p-2 bg-success bg-opacity-10 rounded text-success">
                            <i class="ri-shopping-cart-line fs-4"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-success" id="kpiTotalOrders">
                        {{ number_format($initialData['summary']['total_orders']) }}
                    </div>
                    <small class="text-muted d-block mt-1">
                        Avg Order: {{ $initialData['currency'] }} <span id="kpiAvgOrder">{{ number_format($initialData['summary']['avg_order_value'], 2) }}</span>
                    </small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
            <div class="card analytics-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="kpi-label">Discounts Given</span>
                        <div class="p-2 bg-warning bg-opacity-10 rounded text-warning">
                            <i class="ri-percent-line fs-4"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-warning" id="kpiDiscounts">
                        {{ $initialData['currency'] }} {{ number_format($initialData['summary']['total_discount'], 2) }}
                    </div>
                    <small class="text-muted d-block mt-1">Total promotional deductions</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 col-12">
            <div class="card analytics-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="kpi-label">Customer Base</span>
                        <div class="p-2 bg-info bg-opacity-10 rounded text-info">
                            <i class="ri-user-heart-line fs-4"></i>
                        </div>
                    </div>
                    <div class="kpi-value text-info" id="kpiCustomers">
                        {{ number_format($initialData['customers']['total']) }}
                    </div>
                    <small class="text-muted d-block mt-1">
                        Repeat Rate: <span id="kpiRepeatRate">{{ $initialData['customers']['repeat_rate'] }}</span>%
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Visual Trends & Inventory Health Row --}}
    <div class="row g-3 mb-4">
        {{-- Revenue Trend Chart --}}
        <div class="col-lg-8 col-12">
            <div class="card table-card h-100">
                <div class="card-header table-header d-flex justify-content-between align-items-center">
                    <div class="table-title">Revenue Trend</div>
                    <small class="text-muted" id="chartDateRange">{{ $initialData['date_range']['start'] }} to {{ $initialData['date_range']['end'] }}</small>
                </div>
                <div class="card-body">
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
                                <div class="bar-fill" style="height: {{ max(4, $pct) }}%;"></div>
                                <span class="bar-label">{{ date('d M', strtotime($date)) }}</span>
                            </div>
                        @empty
                            <div class="w-100 text-center text-muted my-auto">No transaction data recorded in this period.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Inventory Breakdown --}}
        <div class="col-lg-4 col-12">
            <div class="card table-card h-100">
                <div class="card-header table-header">
                    <div class="table-title">Inventory Health</div>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex justify-content-between align-items-center border-bottom pb-2">
                        <span class="text-muted"><i class="ri-archive-line me-1 text-primary"></i> Total Catalog SKUs</span>
                        <strong id="invTotal">{{ $initialData['inventory']['total_skus'] }}</strong>
                    </div>
                    <div class="mb-3 d-flex justify-content-between align-items-center border-bottom pb-2">
                        <span class="text-muted"><i class="ri-checkbox-circle-line me-1 text-success"></i> In Stock</span>
                        <span class="badge bg-success" id="invInStock">{{ $initialData['inventory']['in_stock'] }}</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between align-items-center border-bottom pb-2">
                        <span class="text-muted"><i class="ri-alert-line me-1 text-warning"></i> Low Stock Alert</span>
                        <span class="badge bg-warning text-dark" id="invLowStock">{{ $initialData['inventory']['low_stock'] }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted"><i class="ri-close-circle-line me-1 text-danger"></i> Out of Stock</span>
                        <span class="badge bg-danger" id="invOutOfStock">{{ $initialData['inventory']['out_of_stock'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Row: Top Products & Sales Reps --}}
    <div class="row g-3">
        {{-- Top Products --}}
        <div class="col-lg-6 col-12">
            <div class="card table-card">
                <div class="card-header table-header">
                    <div class="table-title">Top Performing Products</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="topProductsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th class="text-end">Units Sold</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($initialData['top_products'] as $prod)
                                    <tr>
                                        <td><strong>{{ $prod['name'] }}</strong></td>
                                        <td><code>{{ $prod['sku'] }}</code></td>
                                        <td class="text-end">{{ number_format($prod['units_sold']) }}</td>
                                        <td class="text-end text-success fw-bold">{{ $initialData['currency'] }} {{ number_format($prod['total_revenue'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No sales recorded for products in this date range.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Workforce / Sales Reps --}}
        <div class="col-lg-6 col-12">
            <div class="card table-card">
                <div class="card-header table-header">
                    <div class="table-title">Sales Representatives Leaderboard</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="salesRepsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Code</th>
                                    <th class="text-end">Orders</th>
                                    <th class="text-end">Total Achieved</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($initialData['sales_reps'] as $rep)
                                    <tr>
                                        <td><strong>{{ $rep['name'] }}</strong></td>
                                        <td><code>{{ $rep['employee_code'] }}</code></td>
                                        <td class="text-end">{{ number_format($rep['order_count']) }}</td>
                                        <td class="text-end text-primary fw-bold">{{ $initialData['currency'] }} {{ number_format($rep['sales'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">No sales representative attribution recorded in this period.</td>
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
    document.querySelectorAll('.btn-range').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-range').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            var range = this.getAttribute('data-range');
            fetchAnalyticsData(range);
        });
    });

    function fetchAnalyticsData(range) {
        fetch('{{ route('analytics.data') }}?range=' + encodeURIComponent(range), {
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

        // Render bar chart
        var container = document.getElementById('barChartContainer');
        container.innerHTML = '';
        var dates = data.sales_trend.dates || [];
        var revenues = data.sales_trend.revenues || [];
        var maxRev = revenues.length ? Math.max.apply(null, revenues) : 1;
        if (maxRev <= 0) maxRev = 1;

        if (dates.length === 0) {
            container.innerHTML = '<div class="w-100 text-center text-muted my-auto">No transaction data recorded in this period.</div>';
        } else {
            dates.forEach(function(d, i) {
                var rev = revenues[i] || 0;
                var pct = Math.max(4, Math.round((rev / maxRev) * 100));
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
