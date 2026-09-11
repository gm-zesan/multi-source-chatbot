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
        if (!Schema::hasTable('analytics_order_items')) {
            Schema::create('analytics_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('analytics_orders')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('analytics_products')->cascadeOnDelete();
                $table->integer('quantity');
                $table->decimal('unit_price', 10, 2);
                $table->decimal('subtotal', 12, 2);
                $table->timestamps();

                $table->index(['order_id', 'product_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_order_items');
    }
};
