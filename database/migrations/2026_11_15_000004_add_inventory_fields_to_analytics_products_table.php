<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('analytics_products')) {
            Schema::table('analytics_products', function (Blueprint $table) {
                if (!Schema::hasColumn('analytics_products', 'stock_quantity')) {
                    $table->integer('stock_quantity')->default(0)->after('cost_price');
                }
                if (!Schema::hasColumn('analytics_products', 'track_inventory')) {
                    $table->boolean('track_inventory')->default(true)->after('stock_quantity');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('analytics_products')) {
            Schema::table('analytics_products', function (Blueprint $table) {
                if (Schema::hasColumn('analytics_products', 'track_inventory')) {
                    $table->dropColumn('track_inventory');
                }
                if (Schema::hasColumn('analytics_products', 'stock_quantity')) {
                    $table->dropColumn('stock_quantity');
                }
            });
        }
    }
};
