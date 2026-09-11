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
        if (!Schema::hasTable('analytics_orders')) {
            Schema::create('analytics_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('analytics_customers')->cascadeOnDelete();
                $table->foreignId('salesperson_id')->comment('Seller who originated the order')->constrained('analytics_salespersons')->cascadeOnDelete();
                $table->string('order_number')->unique();
                $table->decimal('total_amount', 12, 2);
                $table->decimal('discount', 12, 2)->default(0.00);
                $table->decimal('net_amount', 12, 2);
                $table->string('status', 32)->default('completed');
                $table->date('order_date')->index()->comment('Business transaction date');
                $table->timestamps();

                $table->index(['workspace_id', 'order_date']);
                $table->index(['workspace_id', 'customer_id']);
                $table->index(['workspace_id', 'salesperson_id']);
                $table->index(['workspace_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_orders');
    }
};
