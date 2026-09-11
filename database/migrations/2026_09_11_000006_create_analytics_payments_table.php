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
        if (!Schema::hasTable('analytics_payments')) {
            Schema::create('analytics_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('analytics_orders')->nullOnDelete();
                $table->foreignId('customer_id')->constrained('analytics_customers')->cascadeOnDelete();
                $table->foreignId('salesperson_id')->comment('Collector who received the payment')->constrained('analytics_salespersons')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 32)->comment('cash, bkash, nagad, bank, card');
                $table->string('transaction_ref', 100)->nullable();
                $table->dateTime('collected_at')->index()->comment('Business payment timestamp');
                $table->timestamps();

                $table->index(['workspace_id', 'collected_at']);
                $table->index(['workspace_id', 'customer_id']);
                $table->index(['workspace_id', 'salesperson_id']);
                $table->index(['workspace_id', 'payment_method']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_payments');
    }
};
