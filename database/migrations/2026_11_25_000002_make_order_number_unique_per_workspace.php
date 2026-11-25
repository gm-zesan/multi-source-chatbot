<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforce tenant-safe scoping on order_number unique constraints.
     */
    public function up(): void
    {
        Schema::table('workspace_orders', function (Blueprint $table) {
            $table->dropUnique('workspace_orders_order_number_unique');
            $table->unique(['workspace_id', 'order_number'], 'ws_orders_ws_order_num_unique');
        });

        Schema::table('analytics_orders', function (Blueprint $table) {
            $table->dropUnique('analytics_orders_order_number_unique');
            $table->unique(['workspace_id', 'order_number'], 'an_orders_ws_order_num_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_orders', function (Blueprint $table) {
            $table->dropUnique('ws_orders_ws_order_num_unique');
            $table->unique('order_number', 'workspace_orders_order_number_unique');
        });

        Schema::table('analytics_orders', function (Blueprint $table) {
            $table->dropUnique('an_orders_ws_order_num_unique');
            $table->unique('order_number', 'analytics_orders_order_number_unique');
        });
    }
};
