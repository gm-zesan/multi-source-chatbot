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
        Schema::dropIfExists('analytics_due_assignments');

        Schema::create('analytics_due_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('analytics_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('analytics_customers')->cascadeOnDelete();
            $table->foreignId('assigned_salesperson_id')->comment('Salesperson assigned to collect the due')->constrained('analytics_salespersons')->cascadeOnDelete();
            $table->string('status', 32)->default('assigned')->comment('assigned, in_progress, settled, defaulted');
            $table->date('due_date')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status'], 'idx_ada_ws_status');
            $table->index(['workspace_id', 'assigned_salesperson_id'], 'idx_ada_ws_sp');
            $table->index(['workspace_id', 'customer_id'], 'idx_ada_ws_cust');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_due_assignments');
    }
};
