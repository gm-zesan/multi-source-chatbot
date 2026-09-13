<?php

namespace Database\Seeders;

use App\Models\AnalyticsProduct;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AnalyticsProductSeeder extends Seeder
{
    public function run(): void
    {
        $w1 = Workspace::where('slug', 'entrepreneurs-automation')->first();
        $w2 = Workspace::where('slug', 'apex-solutions')->first();

        // Workspace 1 Products
        $w1Products = [
            [
                'name' => 'Laptop Pro 15',
                'category' => 'Electronics',
                'unit_price' => 85000.00,
                'cost_price' => 70000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Wireless Headphone',
                'category' => 'Electronics',
                'unit_price' => 4500.00,
                'cost_price' => 3000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Ergonomic Office Chair',
                'category' => 'Furniture',
                'unit_price' => 18000.00,
                'cost_price' => 12000.00,
                'is_active' => true,
            ],
            [
                'name' => 'Mechanical Keyboard',
                'category' => 'Accessories',
                'unit_price' => 6000.00,
                'cost_price' => 4000.00,
                'is_active' => true,
            ],
            [
                'name' => 'USB-C Multi-hub',
                'category' => 'Accessories',
                'unit_price' => 2500.00,
                'cost_price' => 1500.00,
                'is_active' => true,
            ],
        ];

        foreach ($w1Products as $data) {
            AnalyticsProduct::updateOrCreate(
                ['workspace_id' => $w1->id, 'name' => $data['name']],
                $data
            );
        }

        // Workspace 2 Products
        $w2Products = [
            [
                'name' => 'RGB Gaming Mouse',
                'category' => 'Accessories',
                'unit_price' => 3500.00,
                'cost_price' => 2000.00,
                'is_active' => true,
            ],
        ];

        foreach ($w2Products as $data) {
            AnalyticsProduct::updateOrCreate(
                ['workspace_id' => $w2->id, 'name' => $data['name']],
                $data
            );
        }
    }
}
