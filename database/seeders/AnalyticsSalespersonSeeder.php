<?php

namespace Database\Seeders;

use App\Models\AnalyticsSalesperson;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AnalyticsSalespersonSeeder extends Seeder
{
    public function run(): void
    {
        $w1 = Workspace::where('slug', 'entrepreneurs-automation')->first() 
            ?? Workspace::firstOrCreate(['slug' => 'entrepreneurs-automation'], ['name' => 'Entrepreneurs Automation', 'is_active' => true]);
        
        $w2 = Workspace::where('slug', 'apex-solutions')->first() 
            ?? Workspace::firstOrCreate(['slug' => 'apex-solutions'], ['name' => 'Apex Solutions', 'is_active' => true]);

        // Workspace 1 Salespersons
        $w1Salespersons = [
            [
                'employee_code' => 'SP-101',
                'name' => 'Hasan',
                'phone' => '01711000001',
                'email' => 'hasan@company.com',
                'target_amount' => 500000.00,
                'is_active' => true,
            ],
            [
                'employee_code' => 'SP-102',
                'name' => 'Rakib',
                'phone' => '01711000002',
                'email' => 'rakib@company.com',
                'target_amount' => 400000.00,
                'is_active' => true,
            ],
            [
                'employee_code' => 'SP-103',
                'name' => 'Tarek',
                'phone' => '01711000003',
                'email' => 'tarek@company.com',
                'target_amount' => 300000.00,
                'is_active' => true,
            ],
            [
                'employee_code' => 'SP-104',
                'name' => 'Mehedi',
                'phone' => '01711000004',
                'email' => 'mehedi@company.com',
                'target_amount' => 250000.00,
                'is_active' => true,
            ],
        ];

        foreach ($w1Salespersons as $data) {
            AnalyticsSalesperson::updateOrCreate(
                ['employee_code' => $data['employee_code']],
                array_merge($data, ['workspace_id' => $w1->id])
            );
        }

        // Workspace 2 Salespersons
        $w2Salespersons = [
            [
                'employee_code' => 'SP-201',
                'name' => 'Salma',
                'phone' => '01911000001',
                'email' => 'salma@apex.com',
                'target_amount' => 350000.00,
                'is_active' => true,
            ],
        ];

        foreach ($w2Salespersons as $data) {
            AnalyticsSalesperson::updateOrCreate(
                ['employee_code' => $data['employee_code']],
                array_merge($data, ['workspace_id' => $w2->id])
            );
        }
    }
}
