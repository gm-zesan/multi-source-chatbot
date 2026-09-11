<?php

namespace Database\Seeders;

use App\Models\AnalyticsCustomer;
use App\Models\AnalyticsDueAssignment;
use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsPayment;
use App\Models\AnalyticsProduct;
use App\Models\AnalyticsSalesperson;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BusinessAnalyticsSeeder extends Seeder
{
    /**
     * Run the business analytics database seeds.
     */
    public function run(): void
    {
        $workspace = Workspace::first();
        if (!$workspace) {
            $workspace = Workspace::create([
                'name' => 'Default Workspace',
                'slug' => 'default-workspace',
                'is_active' => true,
            ]);
        }

        $workspaceId = $workspace->id;

        // 1. Seed Salespersons
        $salespersons = [
            'hasan' => AnalyticsSalesperson::updateOrCreate(
                ['workspace_id' => $workspaceId, 'employee_code' => 'SP-101'],
                [
                    'name' => 'Hasan',
                    'phone' => '01711000001',
                    'email' => 'hasan@company.com',
                    'target_amount' => 500000.00,
                    'is_active' => true,
                ]
            ),
            'rakib' => AnalyticsSalesperson::updateOrCreate(
                ['workspace_id' => $workspaceId, 'employee_code' => 'SP-102'],
                [
                    'name' => 'Rakib',
                    'phone' => '01711000002',
                    'email' => 'rakib@company.com',
                    'target_amount' => 400000.00,
                    'is_active' => true,
                ]
            ),
            'tarek' => AnalyticsSalesperson::updateOrCreate(
                ['workspace_id' => $workspaceId, 'employee_code' => 'SP-103'],
                [
                    'name' => 'Tarek',
                    'phone' => '01711000003',
                    'email' => 'tarek@company.com',
                    'target_amount' => 300000.00,
                    'is_active' => true,
                ]
            ),
            'mehedi' => AnalyticsSalesperson::updateOrCreate(
                ['workspace_id' => $workspaceId, 'employee_code' => 'SP-104'],
                [
                    'name' => 'Mehedi',
                    'phone' => '01711000004',
                    'email' => 'mehedi@company.com',
                    'target_amount' => 250000.00,
                    'is_active' => true,
                ]
            ),
        ];

        // 2. Seed Customers
        $customers = [
            'rahim' => AnalyticsCustomer::updateOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => '01811000001'],
                [
                    'name' => 'Rahim',
                    'email' => 'rahim@trade.com',
                    'address' => 'Dhanmondi, Dhaka',
                    'is_active' => true,
                ]
            ),
            'karim' => AnalyticsCustomer::updateOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => '01811000002'],
                [
                    'name' => 'Karim',
                    'email' => 'karim@gmail.com',
                    'address' => 'Gulshan, Dhaka',
                    'is_active' => true,
                ]
            ),
            'jamila' => AnalyticsCustomer::updateOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => '01811000003'],
                [
                    'name' => 'Jamila',
                    'email' => 'jamila@enterprise.com',
                    'address' => 'Uttara, Dhaka',
                    'is_active' => true,
                ]
            ),
            'rafiq' => AnalyticsCustomer::updateOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => '01811000004'],
                [
                    'name' => 'Rafiq',
                    'email' => 'rafiq@shop.com',
                    'address' => 'Mirpur, Dhaka',
                    'is_active' => true,
                ]
            ),
            'anis' => AnalyticsCustomer::updateOrCreate(
                ['workspace_id' => $workspaceId, 'phone' => '01811000005'],
                [
                    'name' => 'Anis',
                    'email' => 'anis@gmail.com',
                    'address' => 'Mohakhali, Dhaka',
                    'is_active' => true,
                ]
            ),
        ];

        // 3. Seed Products
        $products = [
            'laptop' => AnalyticsProduct::updateOrCreate(
                ['workspace_id' => $workspaceId, 'name' => 'Laptop Pro 15'],
                [
                    'category' => 'Electronics',
                    'unit_price' => 85000.00,
                    'cost_price' => 70000.00,
                    'is_active' => true,
                ]
            ),
            'headphone' => AnalyticsProduct::updateOrCreate(
                ['workspace_id' => $workspaceId, 'name' => 'Wireless Headphone'],
                [
                    'category' => 'Electronics',
                    'unit_price' => 4500.00,
                    'cost_price' => 3000.00,
                    'is_active' => true,
                ]
            ),
            'chair' => AnalyticsProduct::updateOrCreate(
                ['workspace_id' => $workspaceId, 'name' => 'Ergonomic Office Chair'],
                [
                    'category' => 'Furniture',
                    'unit_price' => 18000.00,
                    'cost_price' => 12000.00,
                    'is_active' => true,
                ]
            ),
            'keyboard' => AnalyticsProduct::updateOrCreate(
                ['workspace_id' => $workspaceId, 'name' => 'Mechanical Keyboard'],
                [
                    'category' => 'Accessories',
                    'unit_price' => 6000.00,
                    'cost_price' => 4000.00,
                    'is_active' => true,
                ]
            ),
            'hub' => AnalyticsProduct::updateOrCreate(
                ['workspace_id' => $workspaceId, 'name' => 'USB-C Multi-hub'],
                [
                    'category' => 'Accessories',
                    'unit_price' => 2500.00,
                    'cost_price' => 1500.00,
                    'is_active' => true,
                ]
            ),
        ];

        // Anchor date: Current reference date (aligned with MySQL CURRENT_DATE() in Asia/Dhaka)
        $now = Carbon::now('Asia/Dhaka');
        $today = $now->copy()->startOfDay();
        $yesterday = $now->copy()->subDay()->startOfDay();
        $threeDaysAgo = $now->copy()->subDays(3);
        $lastWeek = $now->copy()->subDays(7);
        $fifteenDaysAgo = $now->copy()->subDays(15);
        $lastMonth = $now->copy()->subDays(35);

        // 4. Orders & Order Items
        // Order 1: High value order by Hasan to Rahim (15 days ago)
        $ord1 = AnalyticsOrder::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_number' => 'ORD-2026-001'],
            [
                'customer_id' => $customers['rahim']->id,
                'salesperson_id' => $salespersons['hasan']->id, // Seller: Hasan
                'total_amount' => 170000.00,
                'discount' => 0.00,
                'net_amount' => 170000.00,
                'status' => 'completed',
                'order_date' => $fifteenDaysAgo->toDateString(),
                'created_at' => $fifteenDaysAgo,
            ]
        );
        AnalyticsOrderItem::firstOrCreate(
            ['order_id' => $ord1->id, 'product_id' => $products['laptop']->id],
            ['quantity' => 2, 'unit_price' => 85000.00, 'subtotal' => 170000.00]
        );

        // Order 2: Moderate order by Hasan to Karim (7 days ago) - Fully paid
        $ord2 = AnalyticsOrder::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_number' => 'ORD-2026-002'],
            [
                'customer_id' => $customers['karim']->id,
                'salesperson_id' => $salespersons['hasan']->id, // Seller: Hasan
                'total_amount' => 9000.00,
                'discount' => 0.00,
                'net_amount' => 9000.00,
                'status' => 'completed',
                'order_date' => $lastWeek->toDateString(),
                'created_at' => $lastWeek,
            ]
        );
        AnalyticsOrderItem::firstOrCreate(
            ['order_id' => $ord2->id, 'product_id' => $products['headphone']->id],
            ['quantity' => 2, 'unit_price' => 4500.00, 'subtotal' => 9000.00]
        );

        // Order 3: Bulk order by Tarek to Jamila (3 days ago) - Partial paid
        $ord3 = AnalyticsOrder::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_number' => 'ORD-2026-003'],
            [
                'customer_id' => $customers['jamila']->id,
                'salesperson_id' => $salespersons['tarek']->id, // Seller: Tarek
                'total_amount' => 90000.00,
                'discount' => 0.00,
                'net_amount' => 90000.00,
                'status' => 'completed',
                'order_date' => $threeDaysAgo->toDateString(),
                'created_at' => $threeDaysAgo,
            ]
        );
        AnalyticsOrderItem::firstOrCreate(
            ['order_id' => $ord3->id, 'product_id' => $products['chair']->id],
            ['quantity' => 5, 'unit_price' => 18000.00, 'subtotal' => 90000.00]
        );

        // Order 4: TODAY's order by Hasan to Rahim (Today) - Partial paid
        $ord4 = AnalyticsOrder::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_number' => 'ORD-2026-004'],
            [
                'customer_id' => $customers['rahim']->id,
                'salesperson_id' => $salespersons['hasan']->id, // Seller: Hasan
                'total_amount' => 24000.00,
                'discount' => 0.00,
                'net_amount' => 24000.00,
                'status' => 'completed',
                'order_date' => $today->toDateString(),
                'created_at' => $today->copy()->addHours(10),
            ]
        );
        AnalyticsOrderItem::firstOrCreate(
            ['order_id' => $ord4->id, 'product_id' => $products['keyboard']->id],
            ['quantity' => 4, 'unit_price' => 6000.00, 'subtotal' => 24000.00]
        );

        // Order 5: TODAY's order by Tarek to Anis (Today) - Fully paid
        $ord5 = AnalyticsOrder::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_number' => 'ORD-2026-005'],
            [
                'customer_id' => $customers['anis']->id,
                'salesperson_id' => $salespersons['tarek']->id, // Seller: Tarek
                'total_amount' => 7000.00,
                'discount' => 1000.00,
                'net_amount' => 6000.00,
                'status' => 'completed',
                'order_date' => $today->toDateString(),
                'created_at' => $today->copy()->addHours(12),
            ]
        );
        AnalyticsOrderItem::firstOrCreate(
            ['order_id' => $ord5->id, 'product_id' => $products['headphone']->id],
            ['quantity' => 1, 'unit_price' => 4500.00, 'subtotal' => 4500.00]
        );
        AnalyticsOrderItem::firstOrCreate(
            ['order_id' => $ord5->id, 'product_id' => $products['hub']->id],
            ['quantity' => 1, 'unit_price' => 2500.00, 'subtotal' => 2500.00]
        );

        // Order 6: YESTERDAY's order by Hasan to Rafiq (Yesterday) - Unpaid/Due
        $ord6 = AnalyticsOrder::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_number' => 'ORD-2026-006'],
            [
                'customer_id' => $customers['rafiq']->id,
                'salesperson_id' => $salespersons['hasan']->id, // Seller: Hasan
                'total_amount' => 36000.00,
                'discount' => 0.00,
                'net_amount' => 36000.00,
                'status' => 'completed',
                'order_date' => $yesterday->toDateString(),
                'created_at' => $yesterday->copy()->addHours(14),
            ]
        );
        AnalyticsOrderItem::firstOrCreate(
            ['order_id' => $ord6->id, 'product_id' => $products['chair']->id],
            ['quantity' => 2, 'unit_price' => 18000.00, 'subtotal' => 36000.00]
        );

        // Order 7: Last Month's order by Tarek to Karim - Fully paid
        $ord7 = AnalyticsOrder::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_number' => 'ORD-2026-007'],
            [
                'customer_id' => $customers['karim']->id,
                'salesperson_id' => $salespersons['tarek']->id, // Seller: Tarek
                'total_amount' => 85000.00,
                'discount' => 0.00,
                'net_amount' => 85000.00,
                'status' => 'completed',
                'order_date' => $lastMonth->toDateString(),
                'created_at' => $lastMonth,
            ]
        );
        AnalyticsOrderItem::firstOrCreate(
            ['order_id' => $ord7->id, 'product_id' => $products['laptop']->id],
            ['quantity' => 1, 'unit_price' => 85000.00, 'subtotal' => 85000.00]
        );

        // 5. Payments (Cash-in / Collection)
        // P1: Rahim partial payment on ORD-1 (50,000) collected by Hasan
        AnalyticsPayment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'transaction_ref' => 'TXN-001'],
            [
                'order_id' => $ord1->id,
                'customer_id' => $customers['rahim']->id,
                'salesperson_id' => $salespersons['hasan']->id, // Collector: Hasan
                'amount' => 50000.00,
                'payment_method' => 'bank',
                'collected_at' => $fifteenDaysAgo->copy()->addHours(2),
            ]
        );

        // P2: Rahim second partial payment on ORD-1 (30,000) collected by Rakib (Top collector!)
        AnalyticsPayment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'transaction_ref' => 'TXN-002'],
            [
                'order_id' => $ord1->id,
                'customer_id' => $customers['rahim']->id,
                'salesperson_id' => $salespersons['rakib']->id, // Collector: Rakib
                'amount' => 30000.00,
                'payment_method' => 'bkash',
                'collected_at' => $threeDaysAgo->copy()->addHours(1),
            ]
        );
        // -> Remaining Due for ORD-1: 170,000 - 80,000 = 90,000

        // P3: Karim full payment on ORD-2 (9,000) collected by Hasan
        AnalyticsPayment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'transaction_ref' => 'TXN-003'],
            [
                'order_id' => $ord2->id,
                'customer_id' => $customers['karim']->id,
                'salesperson_id' => $salespersons['hasan']->id, // Collector: Hasan
                'amount' => 9000.00,
                'payment_method' => 'bkash',
                'collected_at' => $lastWeek->copy()->addHours(1),
            ]
        );

        // P4: Jamila partial payment on ORD-3 (60,000) collected by Rakib
        AnalyticsPayment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'transaction_ref' => 'TXN-004'],
            [
                'order_id' => $ord3->id,
                'customer_id' => $customers['jamila']->id,
                'salesperson_id' => $salespersons['rakib']->id, // Collector: Rakib
                'amount' => 60000.00,
                'payment_method' => 'bank',
                'collected_at' => $threeDaysAgo->copy()->addHours(3),
            ]
        );
        // -> Remaining Due for ORD-3: 90,000 - 60,000 = 30,000

        // P5: TODAY'S CASH-IN: Rahim pays 10,000 on ORD-4, collected by Rakib
        AnalyticsPayment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'transaction_ref' => 'TXN-005'],
            [
                'order_id' => $ord4->id,
                'customer_id' => $customers['rahim']->id,
                'salesperson_id' => $salespersons['rakib']->id, // Collector: Rakib
                'amount' => 10000.00,
                'payment_method' => 'cash',
                'collected_at' => $today->copy()->addHours(11),
            ]
        );
        // -> Remaining Due for ORD-4: 24,000 - 10,000 = 14,000
        // -> Total Due for Rahim = 90,000 + 14,000 = 104,000!

        // P6: TODAY'S CASH-IN: Anis full payment (6,000) on ORD-5, collected by Tarek
        AnalyticsPayment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'transaction_ref' => 'TXN-006'],
            [
                'order_id' => $ord5->id,
                'customer_id' => $customers['anis']->id,
                'salesperson_id' => $salespersons['tarek']->id, // Collector: Tarek
                'amount' => 6000.00,
                'payment_method' => 'cash',
                'collected_at' => $today->copy()->addHours(13),
            ]
        );

        // P7: TODAY'S CASH-IN: Rafiq pays 16,000 partial on ORD-6, collected by Rakib
        AnalyticsPayment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'transaction_ref' => 'TXN-007'],
            [
                'order_id' => $ord6->id,
                'customer_id' => $customers['rafiq']->id,
                'salesperson_id' => $salespersons['rakib']->id, // Collector: Rakib
                'amount' => 16000.00,
                'payment_method' => 'bkash',
                'collected_at' => $today->copy()->addHours(15),
            ]
        );
        // -> Remaining Due for ORD-6: 36,000 - 16,000 = 20,000

        // P8: Last month full payment on ORD-7 (85,000) collected by Rakib
        AnalyticsPayment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'transaction_ref' => 'TXN-008'],
            [
                'order_id' => $ord7->id,
                'customer_id' => $customers['karim']->id,
                'salesperson_id' => $salespersons['rakib']->id, // Collector: Rakib
                'amount' => 85000.00,
                'payment_method' => 'card',
                'collected_at' => $lastMonth->copy()->addHours(2),
            ]
        );

        // 6. Due Assignments (Recovery assignment, NO redundant due_amount column)
        // Assignment 1: Rahim's heavy due on ORD-1 assigned to Mehedi (Recovery specialist)
        AnalyticsDueAssignment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_id' => $ord1->id],
            [
                'customer_id' => $customers['rahim']->id,
                'assigned_salesperson_id' => $salespersons['mehedi']->id, // Due Assignee: Mehedi
                'status' => 'in_progress',
                'due_date' => $today->copy()->addDays(7)->toDateString(),
                'notes' => 'Customer promised to clear remaining due by next week via bank transfer.',
            ]
        );

        // Assignment 2: Rafiq's remaining due on ORD-6 assigned to Mehedi
        AnalyticsDueAssignment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_id' => $ord6->id],
            [
                'customer_id' => $customers['rafiq']->id,
                'assigned_salesperson_id' => $salespersons['mehedi']->id, // Due Assignee: Mehedi
                'status' => 'assigned',
                'due_date' => $today->copy()->addDays(5)->toDateString(),
                'notes' => 'First follow-up call scheduled.',
            ]
        );

        // Assignment 3: Jamila's due on ORD-3 assigned to Tarek (the original seller)
        AnalyticsDueAssignment::updateOrCreate(
            ['workspace_id' => $workspaceId, 'order_id' => $ord3->id],
            [
                'customer_id' => $customers['jamila']->id,
                'assigned_salesperson_id' => $salespersons['tarek']->id, // Due Assignee: Tarek
                'status' => 'in_progress',
                'due_date' => $today->copy()->addDays(10)->toDateString(),
                'notes' => 'Corporate account with 30-day net terms.',
            ]
        );

        // =========================================================================
        // 7. SEED WORKSPACE B (Workspace 2) — For Genuine Cross-Tenant Isolation Test
        // =========================================================================
        $workspaceB = Workspace::updateOrCreate(
            ['slug' => 'apex-solutions'],
            ['name' => 'Apex Solutions', 'is_active' => true]
        );
        $w2Id = $workspaceB->id;

        $salma = AnalyticsSalesperson::updateOrCreate(
            ['workspace_id' => $w2Id, 'employee_code' => 'SP-201'],
            [
                'name' => 'Salma',
                'phone' => '01911000001',
                'email' => 'salma@apex.com',
                'target_amount' => 350000.00,
                'is_active' => true,
            ]
        );

        $nasir = AnalyticsCustomer::updateOrCreate(
            ['workspace_id' => $w2Id, 'phone' => '01911000099'],
            [
                'name' => 'Nasir',
                'email' => 'nasir@apex.com',
                'address' => 'Banani, Dhaka',
                'is_active' => true,
            ]
        );

        $mouse = AnalyticsProduct::updateOrCreate(
            ['workspace_id' => $w2Id, 'name' => 'RGB Gaming Mouse'],
            [
                'category' => 'Accessories',
                'unit_price' => 3500.00,
                'cost_price' => 2000.00,
                'is_active' => true,
            ]
        );

        $ordW2 = AnalyticsOrder::updateOrCreate(
            ['workspace_id' => $w2Id, 'order_number' => 'ORD-W2-001'],
            [
                'customer_id' => $nasir->id,
                'salesperson_id' => $salma->id,
                'total_amount' => 70000.00,
                'discount' => 0.00,
                'net_amount' => 70000.00,
                'status' => 'completed',
                'order_date' => $today->toDateString(),
                'created_at' => $today->copy()->addHours(9),
            ]
        );

        AnalyticsOrderItem::firstOrCreate(
            ['order_id' => $ordW2->id, 'product_id' => $mouse->id],
            ['quantity' => 20, 'unit_price' => 3500.00, 'subtotal' => 70000.00]
        );

        AnalyticsPayment::updateOrCreate(
            ['workspace_id' => $w2Id, 'transaction_ref' => 'TXN-W2-001'],
            [
                'order_id' => $ordW2->id,
                'customer_id' => $nasir->id,
                'salesperson_id' => $salma->id,
                'amount' => 20000.00,
                'payment_method' => 'cash',
                'collected_at' => $today->copy()->addHours(11),
            ]
        );

        AnalyticsDueAssignment::updateOrCreate(
            ['workspace_id' => $w2Id, 'order_id' => $ordW2->id],
            [
                'customer_id' => $nasir->id,
                'assigned_salesperson_id' => $salma->id,
                'status' => 'assigned',
                'due_date' => $today->copy()->addDays(14)->toDateString(),
                'notes' => 'Workspace B isolated order due.',
            ]
        );
    }
}
