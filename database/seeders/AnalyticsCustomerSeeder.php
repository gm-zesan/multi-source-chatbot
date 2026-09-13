<?php

namespace Database\Seeders;

use App\Models\AnalyticsCustomer;
use App\Models\Workspace;
use Illuminate\Database\Seeder;

class AnalyticsCustomerSeeder extends Seeder
{
    public function run(): void
    {
        $w1 = Workspace::where('slug', 'entrepreneurs-automation')->first();
        $w2 = Workspace::where('slug', 'apex-solutions')->first();

        // Workspace 1 Customers
        $w1Customers = [
            [
                'phone' => '01811000001',
                'name' => 'Rahim',
                'email' => 'rahim@trade.com',
                'address' => 'Dhanmondi, Dhaka',
                'is_active' => true,
            ],
            [
                'phone' => '01811000002',
                'name' => 'Karim',
                'email' => 'karim@gmail.com',
                'address' => 'Gulshan, Dhaka',
                'is_active' => true,
            ],
            [
                'phone' => '01811000003',
                'name' => 'Jamila',
                'email' => 'jamila@enterprise.com',
                'address' => 'Uttara, Dhaka',
                'is_active' => true,
            ],
            [
                'phone' => '01811000004',
                'name' => 'Rafiq',
                'email' => 'rafiq@shop.com',
                'address' => 'Mirpur, Dhaka',
                'is_active' => true,
            ],
            [
                'phone' => '01811000005',
                'name' => 'Anis',
                'email' => 'anis@gmail.com',
                'address' => 'Mohakhali, Dhaka',
                'is_active' => true,
            ],
        ];

        foreach ($w1Customers as $data) {
            AnalyticsCustomer::updateOrCreate(
                ['workspace_id' => $w1->id, 'phone' => $data['phone']],
                $data
            );
        }

        // Workspace 2 Customers
        $w2Customers = [
            [
                'phone' => '01911000099',
                'name' => 'Nasir',
                'email' => 'nasir@apex.com',
                'address' => 'Banani, Dhaka',
                'is_active' => true,
            ],
        ];

        foreach ($w2Customers as $data) {
            AnalyticsCustomer::updateOrCreate(
                ['workspace_id' => $w2->id, 'phone' => $data['phone']],
                $data
            );
        }
    }
}
