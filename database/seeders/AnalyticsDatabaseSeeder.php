<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AnalyticsDatabaseSeeder extends Seeder
{
    /**
     * Run all analytics database seeders in correct relational dependency order.
     */
    public function run(): void
    {
        $this->call([
            AnalyticsSalespersonSeeder::class,
            AnalyticsCustomerSeeder::class,
            AnalyticsProductSeeder::class,
            AnalyticsOrderSeeder::class,
            AnalyticsOrderItemSeeder::class,
            AnalyticsPaymentSeeder::class,
            AnalyticsDueAssignmentSeeder::class,
        ]);
    }
}
