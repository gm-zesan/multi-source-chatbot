<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsProduct;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AnalyticsDashboardController extends Controller
{
    /**
     * Show the Owner Business Intelligence Visual Dashboard.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $this->authorizeOwnerOrStaff($user);

        $workspaceId = ($user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: Workspace::first()?->id ?: 1);

        $workspace = Workspace::findOrFail($workspaceId);

        $data = $this->aggregateAnalytics($workspaceId, '30d');

        return view('admin.analytics.index', [
            'workspace' => $workspace,
            'initialData' => $data,
        ]);
    }

    /**
     * Fetch JSON payload for AJAX dynamic filtering and charts.
     */
    public function data(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->authorizeOwnerOrStaff($user);

        $workspaceId = ($user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value) && $request->filled('workspace_id'))
            ? (int) $request->input('workspace_id')
            : (int) ($user->workspace_id ?: Workspace::first()?->id ?: 1);

        $range = (string) $request->input('range', '30d');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $data = $this->aggregateAnalytics($workspaceId, $range, $startDate, $endDate);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Aggregate business analytics data strictly scoped to workspace.
     */
    public function aggregateAnalytics(int $workspaceId, string $range = '30d', ?string $customStart = null, ?string $customEnd = null): array
    {
        $today = Carbon::today();

        if (! empty($customStart) && ! empty($customEnd)) {
            $startDate = Carbon::parse($customStart)->startOfDay();
            $endDate = Carbon::parse($customEnd)->endOfDay();
        } else {
            switch ($range) {
                case 'today':
                    $startDate = $today->copy()->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
                case '7d':
                    $startDate = $today->copy()->subDays(6)->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
                case '90d':
                    $startDate = $today->copy()->subDays(89)->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
                case 'all':
                    $startDate = Carbon::create(2020, 1, 1)->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
                case '30d':
                default:
                    $startDate = $today->copy()->subDays(29)->startOfDay();
                    $endDate = $today->copy()->endOfDay();
                    break;
            }
        }

        $workspace = Workspace::find($workspaceId);
        $currency = $workspace?->currency ?: 'BDT';

        $startDateTime = $startDate->copy()->startOfDay()->toDateTimeString();
        $endDateTime = $endDate->copy()->endOfDay()->toDateTimeString();

        // 1. Orders and Sales Metrics
        $ordersQuery = AnalyticsOrder::where('workspace_id', $workspaceId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [$startDateTime, $endDateTime]);

        $grossSales = round((float) (clone $ordersQuery)->sum('total_amount'), 2);
        $totalDiscount = round((float) (clone $ordersQuery)->sum('discount'), 2);
        $netSales = round((float) (clone $ordersQuery)->sum('net_amount'), 2);
        $totalOrders = (int) (clone $ordersQuery)->count();
        $avgOrderValue = $totalOrders > 0 ? round($netSales / $totalOrders, 2) : 0.0;

        // 2. Sales Trend (by date)
        $salesTrendRaw = DB::table('analytics_orders')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [$startDateTime, $endDateTime])
            ->groupBy('order_date')
            ->orderBy('order_date')
            ->select('order_date', DB::raw('SUM(net_amount) as revenue'), DB::raw('COUNT(*) as orders'))
            ->get();

        $trendDates = [];
        $trendRevenues = [];
        $trendOrders = [];
        foreach ($salesTrendRaw as $row) {
            $trendDates[] = substr((string) $row->order_date, 0, 10);
            $trendRevenues[] = round((float) $row->revenue, 2);
            $trendOrders[] = (int) $row->orders;
        }

        // 3. Top Products Performance
        $topProductsRaw = DB::table('analytics_order_items')
            ->join('analytics_orders', 'analytics_orders.id', '=', 'analytics_order_items.order_id')
            ->join('analytics_products', 'analytics_products.id', '=', 'analytics_order_items.product_id')
            ->where('analytics_orders.workspace_id', $workspaceId)
            ->where('analytics_orders.status', 'completed')
            ->whereBetween('analytics_orders.order_date', [$startDateTime, $endDateTime])
            ->groupBy('analytics_products.id', 'analytics_products.name', 'analytics_products.sku')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->select(
                'analytics_products.name',
                'analytics_products.sku',
                DB::raw('SUM(analytics_order_items.quantity) as units_sold'),
                DB::raw('SUM(analytics_order_items.subtotal) as total_revenue')
            )
            ->get();

        $topProducts = [];
        foreach ($topProductsRaw as $tp) {
            $topProducts[] = [
                'name' => $tp->name,
                'sku' => $tp->sku,
                'units_sold' => (float) $tp->units_sold,
                'total_revenue' => round((float) $tp->total_revenue, 2),
            ];
        }

        // 4. Inventory Health
        $productsQuery = AnalyticsProduct::where('workspace_id', $workspaceId)->where('is_active', true);
        $totalProducts = (clone $productsQuery)->count();
        $outOfStockCount = 0;
        $lowStockCount = 0;

        if (Schema::hasColumn('analytics_products', 'stock_quantity')) {
            $outOfStockCount = (clone $productsQuery)->where('stock_quantity', '<=', 0)->count();
            $lowStockCount = (clone $productsQuery)->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 5)->count();
        }
        $inStockCount = max(0, $totalProducts - $outOfStockCount);

        // 5. Customers Insights
        $totalCustomers = AnalyticsCustomer::where('workspace_id', $workspaceId)->count();
        $repeatCustomers = AnalyticsCustomer::where('workspace_id', $workspaceId)->has('orders', '>', 1)->count();
        $repeatRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0.0;

        // 6. Workforce Sales Performance
        $salesRepsRaw = DB::table('analytics_orders')
            ->join('analytics_salespersons', 'analytics_salespersons.id', '=', 'analytics_orders.salesperson_id')
            ->where('analytics_orders.workspace_id', $workspaceId)
            ->where('analytics_orders.status', 'completed')
            ->whereBetween('analytics_orders.order_date', [$startDateTime, $endDateTime])
            ->groupBy('analytics_salespersons.id', 'analytics_salespersons.name', 'analytics_salespersons.employee_code', 'analytics_salespersons.target_amount')
            ->orderByDesc('total_sales')
            ->limit(10)
            ->select(
                'analytics_salespersons.name',
                'analytics_salespersons.employee_code',
                'analytics_salespersons.target_amount',
                DB::raw('SUM(analytics_orders.net_amount) as total_sales'),
                DB::raw('COUNT(analytics_orders.id) as order_count')
            )
            ->get();

        $salesReps = [];
        foreach ($salesRepsRaw as $rep) {
            $salesReps[] = [
                'name' => $rep->name,
                'employee_code' => $rep->employee_code,
                'target' => (float) $rep->target_amount,
                'sales' => round((float) $rep->total_sales, 2),
                'order_count' => (int) $rep->order_count,
            ];
        }

        return [
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'range' => $range,
            ],
            'currency' => $currency,
            'summary' => [
                'gross_sales' => $grossSales,
                'total_discount' => $totalDiscount,
                'net_sales' => $netSales,
                'total_orders' => $totalOrders,
                'avg_order_value' => $avgOrderValue,
            ],
            'sales_trend' => [
                'dates' => $trendDates,
                'revenues' => $trendRevenues,
                'orders' => $trendOrders,
            ],
            'top_products' => $topProducts,
            'inventory' => [
                'total_skus' => $totalProducts,
                'in_stock' => $inStockCount,
                'low_stock' => $lowStockCount,
                'out_of_stock' => $outOfStockCount,
            ],
            'customers' => [
                'total' => $totalCustomers,
                'repeat_count' => $repeatCustomers,
                'repeat_rate' => $repeatRate,
            ],
            'sales_reps' => $salesReps,
        ];
    }

    private function authorizeOwnerOrStaff(?User $user): void
    {
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if ($user->hasRole(\App\Enums\RoleEnum::SUPERADMIN->value)) {
            return;
        }

        if (! $user->workspace_id) {
            abort(403, 'User does not belong to any workspace.');
        }
    }
}
