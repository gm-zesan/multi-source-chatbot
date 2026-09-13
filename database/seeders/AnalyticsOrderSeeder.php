<?php

namespace Database\Seeders;

use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsSalesperson;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AnalyticsOrderSeeder extends Seeder
{
    public function run(): void
    {
        $w1 = Workspace::where('slug', 'entrepreneurs-automation')->first();
        $w2 = Workspace::where('slug', 'apex-solutions')->first();

        // Salespersons Workspace 1
        $hasan = AnalyticsSalesperson::where('workspace_id', $w1->id)->where('employee_code', 'SP-101')->first();
        $tarek = AnalyticsSalesperson::where('workspace_id', $w1->id)->where('employee_code', 'SP-103')->first();

        // Customers Workspace 1
        $rahim = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000001')->first();
        $karim = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000002')->first();
        $jamila = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000003')->first();
        $rafiq = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000004')->first();
        $anis = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000005')->first();

        // Workspace 2 entities
        $salma = AnalyticsSalesperson::where('workspace_id', $w2->id)->where('employee_code', 'SP-201')->first();
        $nasir = AnalyticsCustomer::where('workspace_id', $w2->id)->where('phone', '01911000099')->first();

        // Deterministic Reference Date Strategy
        $now = Carbon::now('Asia/Dhaka');
        $today = $now->copy()->startOfDay();
        $yesterday = $now->copy()->subDay()->startOfDay();
        $threeDaysAgo = $now->copy()->subDays(3)->startOfDay();
        $lastWeek = $now->copy()->subDays(7)->startOfDay();
        $fifteenDaysAgo = $now->copy()->subDays(15)->startOfDay();
        $lastMonth = $now->copy()->subDays(35)->startOfDay();

        // Workspace 1 Orders
        $w1Orders = [
            // Order 1: Hasan to Rahim (15 days ago) -> 170,000 net
            [
                'order_number' => 'ORD-2026-001',
                'customer_id' => $rahim->id,
                'salesperson_id' => $hasan->id,
                'total_amount' => 170000.00,
                'discount' => 0.00,
                'net_amount' => 170000.00,
                'status' => 'completed',
                'order_date' => $fifteenDaysAgo->toDateString(),
                'created_at' => $fifteenDaysAgo,
                'updated_at' => $fifteenDaysAgo,
            ],
            // Order 2: Hasan to Karim (7 days ago) -> 9,000 net
            [
                'order_number' => 'ORD-2026-002',
                'customer_id' => $karim->id,
                'salesperson_id' => $hasan->id,
                'total_amount' => 9000.00,
                'discount' => 0.00,
                'net_amount' => 9000.00,
                'status' => 'completed',
                'order_date' => $lastWeek->toDateString(),
                'created_at' => $lastWeek,
                'updated_at' => $lastWeek,
            ],
            // Order 3: Tarek to Jamila (3 days ago) -> 90,000 net
            [
                'order_number' => 'ORD-2026-003',
                'customer_id' => $jamila->id,
                'salesperson_id' => $tarek->id,
                'total_amount' => 90000.00,
                'discount' => 0.00,
                'net_amount' => 90000.00,
                'status' => 'completed',
                'order_date' => $threeDaysAgo->toDateString(),
                'created_at' => $threeDaysAgo,
                'updated_at' => $threeDaysAgo,
            ],
            // Order 4: Hasan to Rahim (Today) -> 24,000 net
            [
                'order_number' => 'ORD-2026-004',
                'customer_id' => $rahim->id,
                'salesperson_id' => $hasan->id,
                'total_amount' => 24000.00,
                'discount' => 0.00,
                'net_amount' => 24000.00,
                'status' => 'completed',
                'order_date' => $today->toDateString(),
                'created_at' => $today->copy()->addHours(10),
                'updated_at' => $today->copy()->addHours(10),
            ],
            // Order 5: Tarek to Anis (Today) -> 6,000 net
            [
                'order_number' => 'ORD-2026-005',
                'customer_id' => $anis->id,
                'salesperson_id' => $tarek->id,
                'total_amount' => 7000.00,
                'discount' => 1000.00,
                'net_amount' => 6000.00,
                'status' => 'completed',
                'order_date' => $today->toDateString(),
                'created_at' => $today->copy()->addHours(12),
                'updated_at' => $today->copy()->addHours(12),
            ],
            // Order 6: Hasan to Rafiq (Yesterday) -> 36,000 net
            [
                'order_number' => 'ORD-2026-006',
                'customer_id' => $rafiq->id,
                'salesperson_id' => $hasan->id,
                'total_amount' => 36000.00,
                'discount' => 0.00,
                'net_amount' => 36000.00,
                'status' => 'completed',
                'order_date' => $yesterday->toDateString(),
                'created_at' => $yesterday->copy()->addHours(14),
                'updated_at' => $yesterday->copy()->addHours(14),
            ],
            // Order 7: Tarek to Karim (Last month) -> 85,000 net
            [
                'order_number' => 'ORD-2026-007',
                'customer_id' => $karim->id,
                'salesperson_id' => $tarek->id,
                'total_amount' => 85000.00,
                'discount' => 0.00,
                'net_amount' => 85000.00,
                'status' => 'completed',
                'order_date' => $lastMonth->toDateString(),
                'created_at' => $lastMonth,
                'updated_at' => $lastMonth,
            ],
        ];

        foreach ($w1Orders as $data) {
            AnalyticsOrder::updateOrCreate(
                ['workspace_id' => $w1->id, 'order_number' => $data['order_number']],
                $data
            );
        }

        // Workspace 2 Orders
        $w2Orders = [
            [
                'order_number' => 'ORD-W2-001',
                'customer_id' => $nasir->id,
                'salesperson_id' => $salma->id,
                'total_amount' => 70000.00,
                'discount' => 0.00,
                'net_amount' => 70000.00,
                'status' => 'completed',
                'order_date' => $today->toDateString(),
                'created_at' => $today->copy()->addHours(9),
                'updated_at' => $today->copy()->addHours(9),
            ],
        ];

        foreach ($w2Orders as $data) {
            AnalyticsOrder::updateOrCreate(
                ['workspace_id' => $w2->id, 'order_number' => $data['order_number']],
                $data
            );
        }
    }
}
