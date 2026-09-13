<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BusinessAnalyticsSeeder extends Seeder
{
    /**
     * Run the business analytics database seeds by delegating to AnalyticsDatabaseSeeder.
     */
    public function run(): void
    {
        $this->call(AnalyticsDatabaseSeeder::class);
    }
}
