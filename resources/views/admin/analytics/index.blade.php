@extends('admin.app')

@section('title')
    Business Intelligence & Analytics
@endsection

@section('content')
<div class="space-y-4">
    {{-- Header & Range Filters --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text flex items-center gap-2">
                <span>Business Intelligence & Analytics</span>
                <span class="app-badge app-badge-primary">{{ $workspace->name }}</span>
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                Real-time commerce telemetry, sales velocity, catalog inventory status, and sales rep performance.
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="inline-flex p-0.5 rounded border border-light-border dark:border-dark-border bg-light-surface dark:bg-dark-surface" id="rangeFilterGroup">
                <button type="button" class="btn-range-filter px-2.5 py-1 text-xs font-medium rounded text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text" data-range="today">Today</button>
                <button type="button" class="btn-range-filter px-2.5 py-1 text-xs font-medium rounded text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text" data-range="7d">7 Days</button>
                <button type="button" class="btn-range-filter px-2.5 py-1 text-xs font-medium rounded bg-brand-500 text-white font-semibold" data-range="30d">30 Days</button>
                <button type="button" class="btn-range-filter px-2.5 py-1 text-xs font-medium rounded text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text" data-range="90d">90 Days</button>
            </div>
            <a href="{{ route('workspace.settings.edit', ['workspace_id' => $workspace->id]) }}" class="app-btn app-btn-secondary">
                <i class="ri-settings-4-line"></i> Settings
            </a>
        </div>
    </div>

    {{-- Top 4 KPI Metrics --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        {{-- Net Sales --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Net Revenue</div>
                    <div class="text-xl font-bold tracking-tight text-brand-500 mt-0.5" id="kpiNetSales">
                        {{ $initialData['currency'] }} {{ number_format($initialData['summary']['net_sales'], 2) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-brand-500/15 text-brand-500 flex items-center justify-center text-sm">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Gross: {{ $initialData['currency'] }} <strong id="kpiGrossSales" class="text-light-text dark:text-dark-text">{{ number_format($initialData['summary']['gross_sales'], 2) }}</strong></span>
                <span class="app-badge app-badge-primary">{{ $initialData['currency'] }}</span>
            </div>
        </div>

        {{-- Completed Orders --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Completed Orders</div>
                    <div class="text-xl font-bold tracking-tight text-emerald-500 mt-0.5" id="kpiTotalOrders">
                        {{ number_format($initialData['summary']['total_orders']) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-sm">
                    <i class="ri-shopping-cart-2-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Avg Order Value:</span>
                <strong class="text-light-text dark:text-dark-text">{{ $initialData['currency'] }} <span id="kpiAvgOrder">{{ number_format($initialData['summary']['avg_order_value'], 2) }}</span></strong>
            </div>
        </div>

        {{-- Discounts Applied --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Discounts Applied</div>
                    <div class="text-xl font-bold tracking-tight text-amber-500 mt-0.5" id="kpiDiscounts">
                        {{ $initialData['currency'] }} {{ number_format($initialData['summary']['total_discount'], 2) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-amber-500/15 text-amber-500 flex items-center justify-center text-sm">
                    <i class="ri-percent-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Promotions</span>
                <span class="app-badge app-badge-warning">Deductions</span>
            </div>
        </div>

        {{-- Customer Loyalty / Repeat Rate --}}
        <div class="app-card p-3.5 flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <div>
                    <div class="text-2xs font-semibold uppercase tracking-wider text-light-muted dark:text-dark-muted">Customer Base</div>
                    <div class="text-xl font-bold tracking-tight text-sky-500 mt-0.5" id="kpiCustomers">
                        {{ number_format($initialData['customers']['total']) }}
                    </div>
                </div>
                <div class="w-8 h-8 rounded bg-sky-500/15 text-sky-500 flex items-center justify-center text-sm">
                    <i class="ri-user-heart-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Repeat Rate:</span>
                <span class="app-badge app-badge-primary">
                    <span id="kpiRepeatRate">{{ $initialData['customers']['repeat_rate'] }}</span>%
                </span>
            </div>
        </div>
    </div>

    {{-- Main Visual Chart & Inventory Health Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        {{-- Revenue Velocity Trend Chart --}}
        <div class="lg:col-span-2 app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-2">
                    <i class="ri-line-chart-line text-brand-500 text-sm"></i>
                    <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Revenue Velocity Trend</h2>
                </div>
                <span class="app-badge app-badge-secondary" id="chartDateRange">
                    {{ $initialData['date_range']['start'] }} to {{ $initialData['date_range']['end'] }}
                </span>
            </div>
            <div class="app-card-body">
                <div class="h-44 flex items-end gap-1.5 pt-4 border-b border-light-border dark:border-dark-border" id="barChartContainer">
                    @php
                        $maxRevenue = !empty($initialData['sales_trend']['revenues']) ? max($initialData['sales_trend']['revenues']) : 1;
                        if ($maxRevenue <= 0) $maxRevenue = 1;
                    @endphp
                    @forelse($initialData['sales_trend']['dates'] as $idx => $date)
                        @php
                            $rev = $initialData['sales_trend']['revenues'][$idx] ?? 0;
                            $pct = round(($rev / $maxRevenue) * 100);
                        @endphp
                        <div class="flex-1 flex flex-col items-center justify-end h-full group" title="{{ $date }}: {{ $initialData['currency'] }} {{ number_format($rev, 2) }}">
                            <div class="w-full rounded-t bg-brand-500 group-hover:bg-brand-400 transition-all cursor-pointer" style="height: {{ max(4, $pct) }}%;"></div>
                            <span class="text-2xs text-light-muted dark:text-dark-muted mt-1.5 truncate max-w-full font-mono">{{ date('d M', strtotime($date)) }}</span>
                        </div>
                    @empty
                        <div class="w-full text-center text-light-muted dark:text-dark-muted my-auto py-8 text-xs">
                            <i class="ri-bar-chart-box-line text-2xl opacity-40 block mb-1"></i>
                            No transaction records found for this period.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Inventory Health --}}
        <div class="app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-2">
                    <i class="ri-archive-2-line text-brand-500 text-sm"></i>
                    <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Catalog Inventory Health</h2>
                </div>
            </div>
            <div class="app-card-body space-y-2 text-xs">
                <div class="py-1.5 border-b border-light-border dark:border-dark-border flex items-center justify-between">
                    <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5">
                        <i class="ri-stack-line text-brand-500"></i> Total Catalog SKUs
                    </span>
                    <strong class="text-light-text dark:text-dark-text font-bold" id="invTotal">{{ $initialData['inventory']['total_skus'] }}</strong>
                </div>
                <div class="py-1.5 border-b border-light-border dark:border-dark-border flex items-center justify-between">
                    <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5">
                        <i class="ri-checkbox-circle-line text-emerald-500"></i> In Stock
                    </span>
                    <span class="app-badge app-badge-success" id="invInStock">{{ $initialData['inventory']['in_stock'] }}</span>
                </div>
                <div class="py-1.5 border-b border-light-border dark:border-dark-border flex items-center justify-between">
                    <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5">
                        <i class="ri-alert-line text-amber-500"></i> Low Stock Threshold
                    </span>
                    <span class="app-badge app-badge-warning" id="invLowStock">{{ $initialData['inventory']['low_stock'] }}</span>
                </div>
                <div class="py-1.5 flex items-center justify-between">
                    <span class="text-light-muted dark:text-dark-muted flex items-center gap-1.5">
                        <i class="ri-close-circle-line text-red-500"></i> Out of Stock
                    </span>
                    <span class="app-badge app-badge-danger" id="invOutOfStock">{{ $initialData['inventory']['out_of_stock'] }}</span>
                </div>

                <div class="p-2.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-2xs space-y-0.5 mt-2">
                    <div class="font-semibold text-brand-500 flex items-center gap-1">
                        <i class="ri-shield-check-line"></i> Grounded Bot Inventory
                    </div>
                    <p class="text-light-muted dark:text-dark-muted">
                        AI verifies catalog stock limits before proposing order confirmations.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Row: Top Products & Sales Reps Leaderboard --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        {{-- Top Products Table --}}
        <div class="app-card overflow-hidden">
            <div class="app-card-header">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                    <i class="ri-trophy-line text-amber-500"></i>
                    <span>Top-Selling Products</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table" id="topProductsTable">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th class="text-right">Units</th>
                            <th class="text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($initialData['top_products'] as $prod)
                            <tr>
                                <td>
                                    <div class="font-semibold text-light-text dark:text-dark-text">{{ $prod['name'] }}</div>
                                </td>
                                <td><code class="text-2xs text-light-muted dark:text-dark-muted font-mono">{{ $prod['sku'] }}</code></td>
                                <td class="text-right font-semibold text-light-text dark:text-dark-text">{{ number_format($prod['units_sold']) }}</td>
                                <td class="text-right font-bold text-brand-500">{{ $initialData['currency'] }} {{ number_format($prod['total_revenue'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-light-muted dark:text-dark-muted py-6 text-xs">No product sales in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Sales Rep Leaderboard --}}
        <div class="app-card overflow-hidden">
            <div class="app-card-header">
                <div class="flex items-center gap-1.5 text-xs font-semibold text-light-text dark:text-dark-text">
                    <i class="ri-user-star-line text-brand-500"></i>
                    <span>Sales Representatives</span>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table" id="salesRepsTable">
                    <thead>
                        <tr>
                            <th>Representative</th>
                            <th>Code</th>
                            <th class="text-right">Orders</th>
                            <th class="text-right">Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($initialData['sales_reps'] as $rep)
                            <tr>
                                <td>
                                    <div class="font-semibold text-light-text dark:text-dark-text">{{ $rep['name'] }}</div>
                                </td>
                                <td><code class="text-2xs text-light-muted dark:text-dark-muted font-mono">{{ $rep['employee_code'] }}</code></td>
                                <td class="text-right font-semibold text-light-text dark:text-dark-text">{{ number_format($rep['order_count']) }}</td>
                                <td class="text-right font-bold text-emerald-500">{{ $initialData['currency'] }} {{ number_format($rep['sales'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-light-muted dark:text-dark-muted py-6 text-xs">No representative activity recorded in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
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
            document.querySelectorAll('.btn-range-filter').forEach(function(b) {
                b.classList.remove('bg-brand-500', 'text-white', 'font-semibold');
                b.classList.add('text-light-text-secondary', 'dark:text-dark-text-secondary');
            });
            this.classList.remove('text-light-text-secondary', 'dark:text-dark-text-secondary');
            this.classList.add('bg-brand-500', 'text-white', 'font-semibold');
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
            container.innerHTML = '<div class="w-full text-center text-light-muted dark:text-dark-muted my-auto py-8 text-xs"><i class="ri-bar-chart-box-line text-2xl opacity-40 block mb-1"></i>No transaction records found for this period.</div>';
        } else {
            dates.forEach(function(d, i) {
                var rev = revenues[i] || 0;
                var pct = Math.max(4, Math.round((rev / maxRev) * 100));
                var col = document.createElement('div');
                col.className = 'flex-1 flex flex-col items-center justify-end h-full group';
                col.title = d + ': ' + curr + ' ' + Number(rev).toLocaleString(undefined, {minimumFractionDigits: 2});
                col.innerHTML = '<div class="w-full rounded-t bg-brand-500 group-hover:bg-brand-400 transition-all cursor-pointer" style="height: ' + pct + '%;"></div><span class="text-2xs text-light-muted dark:text-dark-muted mt-1.5 truncate max-w-full font-mono">' + d.substring(5) + '</span>';
                container.appendChild(col);
            });
        }
    }
</script>
@endpush
