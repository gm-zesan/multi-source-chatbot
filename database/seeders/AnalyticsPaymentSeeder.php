<?php

namespace Database\Seeders;

use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsPayment;
use App\Models\AnalyticsSalesperson;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AnalyticsPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $w1 = Workspace::where('slug', 'entrepreneurs-automation')->first();
        $w2 = Workspace::where('slug', 'apex-solutions')->first();

        // Salespersons Workspace 1
        $hasan = AnalyticsSalesperson::where('workspace_id', $w1->id)->where('employee_code', 'SP-101')->first();
        $rakib = AnalyticsSalesperson::where('workspace_id', $w1->id)->where('employee_code', 'SP-102')->first();
        $tarek = AnalyticsSalesperson::where('workspace_id', $w1->id)->where('employee_code', 'SP-103')->first();

        // Customers Workspace 1
        $rahim = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000001')->first();
        $karim = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000002')->first();
        $jamila = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000003')->first();
        $rafiq = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000004')->first();
        $anis = AnalyticsCustomer::where('workspace_id', $w1->id)->where('phone', '01811000005')->first();

        // Orders Workspace 1
        $ord1 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-001')->first();
        $ord2 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-002')->first();
        $ord3 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-003')->first();
        $ord4 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-004')->first();
        $ord5 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-005')->first();
        $ord6 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-006')->first();
        $ord7 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-007')->first();

        // Workspace 2 entities
        $salma = AnalyticsSalesperson::where('workspace_id', $w2->id)->where('employee_code', 'SP-201')->first();
        $nasir = AnalyticsCustomer::where('workspace_id', $w2->id)->where('phone', '01911000099')->first();
        $ordW2 = AnalyticsOrder::where('workspace_id', $w2->id)->where('order_number', 'ORD-W2-001')->first();

        // Dates
        $now = Carbon::now('Asia/Dhaka');
        $today = $now->copy()->startOfDay();
        $threeDaysAgo = $now->copy()->subDays(3)->startOfDay();
        $lastWeek = $now->copy()->subDays(7)->startOfDay();
        $fifteenDaysAgo = $now->copy()->subDays(15)->startOfDay();
        $lastMonth = $now->copy()->subDays(35)->startOfDay();

        // Payments Workspace 1
        $w1Payments = [
            // P1: Rahim partial payment on ORD-1 (50,000) collected by Hasan
            [
                'transaction_ref' => 'TXN-001',
                'order_id' => $ord1->id,
                'customer_id' => $rahim->id,
                'salesperson_id' => $hasan->id, // Collector: Hasan
                'amount' => 50000.00,
                'payment_method' => 'bank',
                'collected_at' => $fifteenDaysAgo->copy()->addHours(2),
            ],
            // P2: Rahim second partial payment on ORD-1 (30,000) collected by Rakib
            [
                'transaction_ref' => 'TXN-002',
                'order_id' => $ord1->id,
                'customer_id' => $rahim->id,
                'salesperson_id' => $rakib->id, // Collector: Rakib
                'amount' => 30000.00,
                'payment_method' => 'bkash',
                'collected_at' => $threeDaysAgo->copy()->addHours(1),
            ],
            // P3: Karim full payment on ORD-2 (9,000) collected by Hasan
            [
                'transaction_ref' => 'TXN-003',
                'order_id' => $ord2->id,
                'customer_id' => $karim->id,
                'salesperson_id' => $hasan->id, // Collector: Hasan
                'amount' => 9000.00,
                'payment_method' => 'bkash',
                'collected_at' => $lastWeek->copy()->addHours(1),
            ],
            // P4: Jamila partial payment on ORD-3 (60,000) collected by Rakib
            [
                'transaction_ref' => 'TXN-004',
                'order_id' => $ord3->id,
                'customer_id' => $jamila->id,
                'salesperson_id' => $rakib->id, // Collector: Rakib
                'amount' => 60000.00,
                'payment_method' => 'bank',
                'collected_at' => $threeDaysAgo->copy()->addHours(3),
            ],
            // P5: TODAY CASH-IN: Rahim pays 10,000 on ORD-4, collected by Rakib
            [
                'transaction_ref' => 'TXN-005',
                'order_id' => $ord4->id,
                'customer_id' => $rahim->id,
                'salesperson_id' => $rakib->id, // Collector: Rakib
                'amount' => 10000.00,
                'payment_method' => 'cash',
                'collected_at' => $today->copy()->addHours(11),
            ],
            // P6: TODAY CASH-IN: Anis full payment (6,000) on ORD-5, collected by Tarek
            [
                'transaction_ref' => 'TXN-006',
                'order_id' => $ord5->id,
                'customer_id' => $anis->id,
                'salesperson_id' => $tarek->id, // Collector: Tarek
                'amount' => 6000.00,
                'payment_method' => 'cash',
                'collected_at' => $today->copy()->addHours(13),
            ],
            // P7: TODAY CASH-IN: Rafiq pays 16,000 partial on ORD-6, collected by Rakib
            [
                'transaction_ref' => 'TXN-007',
                'order_id' => $ord6->id,
                'customer_id' => $rafiq->id,
                'salesperson_id' => $rakib->id, // Collector: Rakib
                'amount' => 16000.00,
                'payment_method' => 'bkash',
                'collected_at' => $today->copy()->addHours(15),
            ],
            // P8: Last month full payment on ORD-7 (85,000) collected by Rakib
            [
                'transaction_ref' => 'TXN-008',
                'order_id' => $ord7->id,
                'customer_id' => $karim->id,
                'salesperson_id' => $rakib->id, // Collector: Rakib
                'amount' => 85000.00,
                'payment_method' => 'card',
                'collected_at' => $lastMonth->copy()->addHours(2),
            ],
        ];

        foreach ($w1Payments as $data) {
            AnalyticsPayment::updateOrCreate(
                ['workspace_id' => $w1->id, 'transaction_ref' => $data['transaction_ref']],
                $data
            );
        }

        // Workspace 2 Payments
        $w2Payments = [
            [
                'transaction_ref' => 'TXN-W2-001',
                'order_id' => $ordW2->id,
                'customer_id' => $nasir->id,
                'salesperson_id' => $salma->id,
                'amount' => 20000.00,
                'payment_method' => 'cash',
                'collected_at' => $today->copy()->addHours(11),
            ],
        ];

        foreach ($w2Payments as $data) {
            AnalyticsPayment::updateOrCreate(
                ['workspace_id' => $w2->id, 'transaction_ref' => $data['transaction_ref']],
                $data
            );
        }
    }
}
