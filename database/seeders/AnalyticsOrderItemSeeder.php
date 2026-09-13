<?php

namespace Database\Seeders;

use App\Models\AnalyticsOrder;
use App\Models\AnalyticsOrderItem;
use App\Models\AnalyticsProduct;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AnalyticsOrderItemSeeder extends Seeder
{
    public function run(): void
    {
        $w1 = Workspace::where('slug', 'entrepreneurs-automation')->first();
        $w2 = Workspace::where('slug', 'apex-solutions')->first();

        // Products Workspace 1
        $laptop = AnalyticsProduct::where('workspace_id', $w1->id)->where('name', 'Laptop Pro 15')->first();
        $headphone = AnalyticsProduct::where('workspace_id', $w1->id)->where('name', 'Wireless Headphone')->first();
        $chair = AnalyticsProduct::where('workspace_id', $w1->id)->where('name', 'Ergonomic Office Chair')->first();
        $keyboard = AnalyticsProduct::where('workspace_id', $w1->id)->where('name', 'Mechanical Keyboard')->first();
        $hub = AnalyticsProduct::where('workspace_id', $w1->id)->where('name', 'USB-C Multi-hub')->first();

        // Orders Workspace 1
        $ord1 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-001')->first();
        $ord2 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-002')->first();
        $ord3 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-003')->first();
        $ord4 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-004')->first();
        $ord5 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-005')->first();
        $ord6 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-006')->first();
        $ord7 = AnalyticsOrder::where('workspace_id', $w1->id)->where('order_number', 'ORD-2026-007')->first();

        // Workspace 2
        $mouse = AnalyticsProduct::where('workspace_id', $w2->id)->where('name', 'RGB Gaming Mouse')->first();
        $ordW2 = AnalyticsOrder::where('workspace_id', $w2->id)->where('order_number', 'ORD-W2-001')->first();

        $items = [
            // ORD-2026-001: 2 x Laptop Pro 15 = 170,000
            ['order_id' => $ord1->id, 'product_id' => $laptop->id, 'quantity' => 2, 'unit_price' => 85000.00, 'subtotal' => 170000.00],
            
            // ORD-2026-002: 2 x Wireless Headphone = 9,000
            ['order_id' => $ord2->id, 'product_id' => $headphone->id, 'quantity' => 2, 'unit_price' => 4500.00, 'subtotal' => 9000.00],
            
            // ORD-2026-003: 5 x Ergonomic Office Chair = 90,000
            ['order_id' => $ord3->id, 'product_id' => $chair->id, 'quantity' => 5, 'unit_price' => 18000.00, 'subtotal' => 90000.00],
            
            // ORD-2026-004: 4 x Mechanical Keyboard = 24,000
            ['order_id' => $ord4->id, 'product_id' => $keyboard->id, 'quantity' => 4, 'unit_price' => 6000.00, 'subtotal' => 24000.00],
            
            // ORD-2026-005: 1 x Headphone (4,500) + 1 x Hub (2,500) = 7,000
            ['order_id' => $ord5->id, 'product_id' => $headphone->id, 'quantity' => 1, 'unit_price' => 4500.00, 'subtotal' => 4500.00],
            ['order_id' => $ord5->id, 'product_id' => $hub->id, 'quantity' => 1, 'unit_price' => 2500.00, 'subtotal' => 2500.00],
            
            // ORD-2026-006: 2 x Ergonomic Office Chair = 36,000
            ['order_id' => $ord6->id, 'product_id' => $chair->id, 'quantity' => 2, 'unit_price' => 18000.00, 'subtotal' => 36000.00],
            
            // ORD-2026-007: 1 x Laptop Pro 15 = 85,000
            ['order_id' => $ord7->id, 'product_id' => $laptop->id, 'quantity' => 1, 'unit_price' => 85000.00, 'subtotal' => 85000.00],
            
            // ORD-W2-001: 20 x RGB Gaming Mouse = 70,000
            ['order_id' => $ordW2->id, 'product_id' => $mouse->id, 'quantity' => 20, 'unit_price' => 3500.00, 'subtotal' => 70000.00],
        ];

        foreach ($items as $data) {
            AnalyticsOrderItem::updateOrCreate(
                ['order_id' => $data['order_id'], 'product_id' => $data['product_id']],
                $data
            );
        }
    }
}
