@extends('admin.app')

@section('title')
    Business Intelligence & Analytics
@endsection

@section('content')
<div class="space-y-4">
    {{-- Header & Range Filters --}}
    <div class="app-card p-3 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="app-badge app-badge-primary">
                    <i class="ri-pie-chart-2-line"></i> Commerce BI
                </span>
                <span class="text-2xs text-light-muted dark:text-dark-muted">
                    Workspace: <strong class="text-light-text dark:text-dark-text">{{ $workspace->name }}</strong>
                </span>
            </div>
            <h1 class="text-base sm:text-lg font-bold text-light-text dark:text-dark-text">
                Business Intelligence & Sales Velocity
            </h1>
            <p class="text-xs text-light-text-secondary dark:text-dark-text-secondary">
                Financial revenue metrics, SKU stock movement, payment gateways, and representative leaderboards.
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="inline-flex p-0.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-2xs" id="rangeFilterGroup">
                <button type="button" class="btn-range-filter px-2.5 py-1 font-medium rounded text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text" data-range="today">Today</button>
                <button type="button" class="btn-range-filter px-2.5 py-1 font-medium rounded text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text" data-range="7d">7 Days</button>
                <button type="button" class="btn-range-filter px-2.5 py-1 font-semibold rounded bg-brand-500 text-white" data-range="30d">30 Days</button>
                <button type="button" class="btn-range-filter px-2.5 py-1 font-medium rounded text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text" data-range="90d">90 Days</button>
            </div>
            <a href="{{ route('workspace.settings.edit', ['workspace_id' => $workspace->id]) }}" class="app-btn app-btn-secondary !h-7 !px-2.5 text-2xs">
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
                <div class="flex items-center gap-1.5">
                    <span class="app-badge {{ ($initialData['summary']['net_sales_growth'] ?? 0) >= 0 ? 'app-badge-success' : 'app-badge-warning' }} !text-2xs" id="kpiGrowthBadge">
                        {{ ($initialData['summary']['net_sales_growth'] ?? 0) >= 0 ? '+' : '' }}{{ $initialData['summary']['net_sales_growth'] ?? 0 }}%
                    </span>
                    <div class="w-7 h-7 rounded bg-brand-500/15 text-brand-500 flex items-center justify-center text-xs">
                        <i class="ri-money-dollar-circle-line"></i>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Gross: {{ $initialData['currency'] }} <strong id="kpiGrossSales" class="text-light-text dark:text-dark-text">{{ number_format($initialData['summary']['gross_sales'], 2) }}</strong></span>
                <span class="text-brand-500 font-medium">vs prior period</span>
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
                <div class="w-7 h-7 rounded bg-emerald-500/15 text-emerald-500 flex items-center justify-center text-xs">
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
                <div class="w-7 h-7 rounded bg-amber-500/15 text-amber-500 flex items-center justify-center text-xs">
                    <i class="ri-percent-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Promotional Reductions</span>
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
                <div class="w-7 h-7 rounded bg-sky-500/15 text-sky-500 flex items-center justify-center text-xs">
                    <i class="ri-user-heart-line"></i>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 mt-2 border-t border-light-border dark:border-dark-border text-2xs text-light-muted dark:text-dark-muted">
                <span>Repeat Buyers:</span>
                <span class="app-badge app-badge-primary">
                    <span id="kpiRepeatRate">{{ $initialData['customers']['repeat_rate'] }}</span>% Repeat
                </span>
            </div>
        </div>
    </div>

    {{-- Main Visual Charts Row: Dual-Axis Revenue Velocity & Catalog Health --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
        {{-- Revenue Velocity Trend (Dual-Axis Area + Column) --}}
        <div class="lg:col-span-2 app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-2">
                    <i class="ri-line-chart-line text-brand-500 text-sm"></i>
                    <div>
                        <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Revenue Velocity & Order Volume</h2>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Dual-axis view of financial net sales and customer order quantity</div>
                    </div>
                </div>
                <span class="app-badge app-badge-secondary" id="chartDateRange">
                    {{ $initialData['date_range']['start'] }} to {{ $initialData['date_range']['end'] }}
                </span>
            </div>
            <div class="app-card-body p-3">
                <div id="chartRevenueOrders" class="w-full h-[260px]"></div>
            </div>
        </div>

        {{-- Catalog Inventory Health (RadialBar) --}}
        <div class="app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-2">
                    <i class="ri-archive-2-line text-brand-500 text-sm"></i>
                    <div>
                        <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Catalog Inventory Health</h2>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">SKU stock availability & reorder monitoring</div>
                    </div>
                </div>
                <strong class="text-xs text-light-text dark:text-dark-text font-bold" id="invTotalHeader">{{ $initialData['inventory']['total_skus'] }} SKUs</strong>
            </div>
            <div class="app-card-body p-3 flex flex-col justify-between">
                <div id="chartInventoryHealth" class="w-full h-[180px] flex items-center justify-center"></div>

                <div class="space-y-1.5 pt-3 border-t border-light-border dark:border-dark-border text-xs">
                    <div class="flex items-center justify-between py-0.5">
                        <span class="flex items-center gap-1.5 text-light-text dark:text-dark-text">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> In Stock
                        </span>
                        <strong class="font-mono text-emerald-500 font-bold" id="invInStock">{{ $initialData['inventory']['in_stock'] }}</strong>
                    </div>
                    <div class="flex items-center justify-between py-0.5">
                        <span class="flex items-center gap-1.5 text-light-text dark:text-dark-text">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span> Low Stock (≤ 5)
                        </span>
                        <strong class="font-mono text-amber-500 font-bold" id="invLowStock">{{ $initialData['inventory']['low_stock'] }}</strong>
                    </div>
                    <div class="flex items-center justify-between py-0.5">
                        <span class="flex items-center gap-1.5 text-light-text dark:text-dark-text">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span> Out of Stock
                        </span>
                        <strong class="font-mono text-red-500 font-bold" id="invOutOfStock">{{ $initialData['inventory']['out_of_stock'] }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Financial Telemetry: Payment Methods & Due Receivables --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        {{-- Payment Methods Breakdown --}}
        <div class="app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-2">
                    <i class="ri-bank-card-line text-brand-500 text-sm"></i>
                    <div>
                        <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Payment Gateway & Method Share</h2>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Cash on delivery vs MFS (bKash/Nagad) vs Cards</div>
                    </div>
                </div>
            </div>
            <div class="app-card-body p-3 flex flex-col justify-between">
                <div id="chartPaymentMethods" class="w-full h-[200px] flex items-center justify-center"></div>

                <div class="grid grid-cols-2 gap-2 pt-3 mt-1 border-t border-light-border dark:border-dark-border text-2xs" id="paymentMethodsList">
                    @foreach($initialData['payment_methods']['labels'] as $idx => $label)
                        @php
                            $amt = $initialData['payment_methods']['amounts'][$idx] ?? 0;
                            $cnt = $initialData['payment_methods']['counts'][$idx] ?? 0;
                        @endphp
                        <div class="p-2 rounded bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-light-text dark:text-dark-text">{{ $label }}</span>
                                <div class="text-light-muted dark:text-dark-muted">{{ $cnt }} txns</div>
                            </div>
                            <strong class="text-brand-500 font-mono">{{ $initialData['currency'] }} {{ number_format($amt, 2) }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Due Collection & Recovery Funnel --}}
        <div class="app-card flex flex-col justify-between">
            <div class="app-card-header">
                <div class="flex items-center gap-2">
                    <i class="ri-hand-coin-line text-emerald-500 text-sm"></i>
                    <div>
                        <h2 class="text-xs font-semibold text-light-text dark:text-dark-text">Due & Receivable Recovery</h2>
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Tracking assigned customer dues, collections & settlements</div>
                    </div>
                </div>
                <span class="app-badge app-badge-success" id="dueRecoveryRate">
                    {{ $initialData['due_collection']['recovery_rate'] ?? 100 }}% Recovered
                </span>
            </div>
            <div class="app-card-body p-3 space-y-3">
                <div class="grid grid-cols-4 gap-2 text-center text-xs">
                    <div class="p-2 rounded bg-light-canvas dark:bg-dark-canvas border border-light-border dark:border-dark-border">
                        <div class="text-2xs text-light-muted dark:text-dark-muted">Total Cases</div>
                        <div class="text-base font-bold text-light-text dark:text-dark-text mt-0.5" id="dueTotalCases">{{ $initialData['due_collection']['total_cases'] ?? 0 }}</div>
                    </div>
                    <div class="p-2 rounded bg-emerald-500/10 border border-emerald-500/20">
                        <div class="text-2xs text-emerald-500 font-medium">Settled</div>
                        <div class="text-base font-bold text-emerald-500 mt-0.5" id="dueSettled">{{ $initialData['due_collection']['settled'] ?? 0 }}</div>
                    </div>
                    <div class="p-2 rounded bg-amber-500/10 border border-amber-500/20">
                        <div class="text-2xs text-amber-500 font-medium">In Progress</div>
                        <div class="text-base font-bold text-amber-500 mt-0.5" id="dueInProgress">{{ $initialData['due_collection']['in_progress'] ?? 0 }}</div>
                    </div>
                    <div class="p-2 rounded bg-red-500/10 border border-red-500/20">
                        <div class="text-2xs text-red-500 font-medium">Defaulted</div>
                        <div class="text-base font-bold text-red-500 mt-0.5" id="dueDefaulted">{{ $initialData['due_collection']['defaulted'] ?? 0 }}</div>
                    </div>
                </div>

                {{-- Visual Stacked Recovery Bar --}}
                <div class="space-y-1">
                    <div class="flex items-center justify-between text-2xs text-light-muted dark:text-dark-muted">
                        <span>Recovery Progress Distribution</span>
                        <span class="font-mono">{{ $initialData['due_collection']['recovery_rate'] ?? 100 }}%</span>
                    </div>
                    <div class="h-2.5 rounded-full bg-light-canvas dark:bg-dark-canvas overflow-hidden border border-light-border dark:border-dark-border flex">
                        @php
                            $totalCases = max(1, $initialData['due_collection']['total_cases'] ?? 1);
                            $settledPct = round((($initialData['due_collection']['settled'] ?? 0) / $totalCases) * 100);
                            $progressPct = round((($initialData['due_collection']['in_progress'] ?? 0) / $totalCases) * 100);
                            $defaultPct = max(0, 100 - $settledPct - $progressPct);
                        @endphp
                        <div class="bg-emerald-500 h-full transition-all" style="width: {{ $settledPct }}%;" title="Settled: {{ $settledPct }}%"></div>
                        <div class="bg-amber-500 h-full transition-all" style="width: {{ $progressPct }}%;" title="In Progress: {{ $progressPct }}%"></div>
                        <div class="bg-red-500 h-full transition-all" style="width: {{ $defaultPct }}%;" title="Defaulted: {{ $defaultPct }}%"></div>
                    </div>
                </div>

                <div class="p-2.5 rounded border border-light-border dark:border-dark-border bg-light-canvas dark:bg-dark-canvas text-2xs space-y-1">
                    <div class="font-semibold text-brand-500 flex items-center gap-1">
                        <i class="ri-shield-user-line"></i> Workforce Due Collection Pipeline
                    </div>
                    <p class="text-light-muted dark:text-dark-muted leading-relaxed">
                        Dues are automatically linked to assigned field sales representatives via <code>AnalyticsDueAssignment</code> for tracking recovery velocity.
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
                    <span>Top-Selling Products Leaderboard</span>
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
                    <span>Sales Representatives Performance</span>
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
    const isDarkMode = () => document.documentElement.classList.contains('dark');

    const getColors = () => ({
        text: isDarkMode() ? '#94A3B8' : '#64748B',
        border: isDarkMode() ? '#1E293B' : '#E2E8F0',
        grid: isDarkMode() ? '#1E293B' : '#F1F5F9',
        brand: '#0284C7',
        emerald: '#10B981',
        amber: '#F59E0B',
        red: '#EF4444',
        purple: '#8B5CF6'
    });

    let revChart = null;
    let invChart = null;
    let pmChart = null;

    // 1. Dual-Axis Revenue & Order Volume Chart
    function renderRevenueOrdersChart(salesTrend, currency) {
        const el = document.getElementById('chartRevenueOrders');
        if (!el || typeof ApexCharts === 'undefined') return;

        const c = getColors();
        const dates = salesTrend.dates || [];
        const revenues = salesTrend.revenues || [];
        const orders = salesTrend.orders || [];

        const options = {
            series: [
                {
                    name: 'Net Sales (' + currency + ')',
                    type: 'area',
                    data: revenues
                },
                {
                    name: 'Completed Orders',
                    type: 'column',
                    data: orders
                }
            ],
            chart: {
                height: 250,
                type: 'line',
                toolbar: { show: false },
                fontFamily: 'Inter, sans-serif',
                background: 'transparent'
            },
            colors: [c.brand, c.emerald],
            stroke: {
                width: [2, 0],
                curve: 'smooth'
            },
            fill: {
                type: ['gradient', 'solid'],
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.3,
                    opacityTo: 0.02,
                    stops: [0, 95, 100]
                }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: dates.map(d => d.substring(5)),
                labels: { style: { colors: c.text, fontSize: '11px' } },
                axisBorder: { color: c.border },
                axisTicks: { color: c.border }
            },
            yaxis: [
                {
                    title: { text: 'Revenue (' + currency + ')', style: { color: c.text, fontSize: '11px' } },
                    labels: {
                        style: { colors: c.text, fontSize: '11px' },
                        formatter: val => Number(val).toLocaleString(undefined, { maximumFractionDigits: 0 })
                    }
                },
                {
                    opposite: true,
                    title: { text: 'Orders', style: { color: c.text, fontSize: '11px' } },
                    labels: {
                        style: { colors: c.text, fontSize: '11px' },
                        formatter: val => Math.round(val)
                    }
                }
            ],
            grid: {
                borderColor: c.grid,
                strokeDashArray: 3
            },
            legend: {
                position: 'top',
                horizontalAlign: 'right',
                labels: { colors: isDarkMode() ? '#F8FAFC' : '#020617' },
                fontSize: '11px'
            },
            tooltip: {
                theme: isDarkMode() ? 'dark' : 'light',
                shared: true
            }
        };

        if (revChart) {
            revChart.updateOptions(options);
        } else {
            revChart = new ApexCharts(el, options);
            revChart.render();
        }
    }

    // 2. Inventory Health Radial / Donut Chart
    function renderInventoryChart(inv) {
        const el = document.getElementById('chartInventoryHealth');
        if (!el || typeof ApexCharts === 'undefined') return;

        const inStock = inv.in_stock || 0;
        const lowStock = inv.low_stock || 0;
        const outOfStock = inv.out_of_stock || 0;

        const options = {
            series: [inStock, lowStock, outOfStock],
            labels: ['In Stock', 'Low Stock', 'Out of Stock'],
            colors: ['#10B981', '#F59E0B', '#EF4444'],
            chart: {
                type: 'donut',
                height: 180,
                fontFamily: 'Inter, sans-serif',
                background: 'transparent'
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '68%',
                        labels: {
                            show: true,
                            name: { fontSize: '11px', color: isDarkMode() ? '#94A3B8' : '#64748B' },
                            value: {
                                fontSize: '15px',
                                fontWeight: 'bold',
                                color: isDarkMode() ? '#F8FAFC' : '#020617',
                                formatter: val => val
                            },
                            total: {
                                show: true,
                                label: 'SKUs',
                                fontSize: '11px',
                                color: isDarkMode() ? '#94A3B8' : '#64748B',
                                formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            legend: { show: false },
            stroke: { show: true, width: 2, colors: [isDarkMode() ? '#0F172A' : '#FFFFFF'] },
            tooltip: { theme: isDarkMode() ? 'dark' : 'light' }
        };

        if (invChart) {
            invChart.updateOptions(options);
        } else {
            invChart = new ApexCharts(el, options);
            invChart.render();
        }
    }

    // 3. Payment Methods Donut Chart
    function renderPaymentMethodsChart(pm) {
        const el = document.getElementById('chartPaymentMethods');
        if (!el || typeof ApexCharts === 'undefined') return;

        const options = {
            series: pm.amounts || [1],
            labels: pm.labels || ['COD'],
            colors: ['#10B981', '#EC4899', '#F59E0B', '#0284C7', '#8B5CF6'],
            chart: {
                type: 'donut',
                height: 190,
                fontFamily: 'Inter, sans-serif',
                background: 'transparent'
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            name: { fontSize: '11px', color: isDarkMode() ? '#94A3B8' : '#64748B' },
                            value: {
                                fontSize: '14px',
                                fontWeight: 'bold',
                                color: isDarkMode() ? '#F8FAFC' : '#020617',
                                formatter: val => Number(val).toLocaleString()
                            },
                            total: {
                                show: true,
                                label: 'Volume',
                                fontSize: '11px',
                                color: isDarkMode() ? '#94A3B8' : '#64748B',
                                formatter: w => Number(w.globals.seriesTotals.reduce((a, b) => a + b, 0)).toLocaleString()
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false },
            legend: { show: false },
            stroke: { show: true, width: 2, colors: [isDarkMode() ? '#0F172A' : '#FFFFFF'] },
            tooltip: { theme: isDarkMode() ? 'dark' : 'light' }
        };

        if (pmChart) {
            pmChart.updateOptions(options);
        } else {
            pmChart = new ApexCharts(el, options);
            pmChart.render();
        }
    }

    // Initial Render
    document.addEventListener('DOMContentLoaded', function () {
        const initial = {!! json_encode($initialData) !!};
        renderRevenueOrdersChart(initial.sales_trend, initial.currency);
        renderInventoryChart(initial.inventory);
        if (initial.payment_methods) {
            renderPaymentMethodsChart(initial.payment_methods);
        }

        // Range Filter Buttons (Today, 7D, 30D, 90D)
        document.querySelectorAll('.btn-range-filter').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.btn-range-filter').forEach(b => {
                    b.className = 'btn-range-filter px-2.5 py-1 font-medium rounded text-light-text-secondary dark:text-dark-text-secondary hover:text-light-text dark:hover:text-dark-text';
                });
                this.className = 'btn-range-filter px-2.5 py-1 font-semibold rounded bg-brand-500 text-white';

                const range = this.getAttribute('data-range');
                fetchAnalyticsData(range);
            });
        });

        // Theme switch listener
        window.addEventListener('agy-theme-changed', function (e) {
            const isDark = e.detail.theme === 'dark';
            const c = getColors();
            if (revChart) {
                revChart.updateOptions({
                    xaxis: { labels: { style: { colors: c.text } }, axisBorder: { color: c.border }, axisTicks: { color: c.border } },
                    yaxis: [
                        { labels: { style: { colors: c.text } }, title: { style: { color: c.text } } },
                        { labels: { style: { colors: c.text } }, title: { style: { color: c.text } } }
                    ],
                    grid: { borderColor: c.grid },
                    legend: { labels: { colors: isDark ? '#F8FAFC' : '#020617' } },
                    tooltip: { theme: isDark ? 'dark' : 'light' }
                });
            }
            if (invChart) {
                invChart.updateOptions({
                    stroke: { colors: [isDark ? '#0F172A' : '#FFFFFF'] },
                    tooltip: { theme: isDark ? 'dark' : 'light' }
                });
            }
            if (pmChart) {
                pmChart.updateOptions({
                    stroke: { colors: [isDark ? '#0F172A' : '#FFFFFF'] },
                    tooltip: { theme: isDark ? 'dark' : 'light' }
                });
            }
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
        .then(res => res.json())
        .then(json => {
            if (json.status === 'success') {
                updateDashboardView(json.data);
            }
        })
        .catch(err => console.error('Failed to load analytics data', err));
    }

    function updateDashboardView(data) {
        const curr = data.currency;
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

        // Update charts
        renderRevenueOrdersChart(data.sales_trend, curr);
        renderInventoryChart(data.inventory);
        if (data.payment_methods) {
            renderPaymentMethodsChart(data.payment_methods);
        }
    }
</script>
@endpush
