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
        if (!Schema::hasTable('analytics_products')) {
            Schema::create('analytics_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('category')->default('General');
                $table->decimal('unit_price', 10, 2);
                $table->decimal('cost_price', 10, 2)->default(0.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['workspace_id', 'category']);
                $table->index(['workspace_id', 'name']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_products');
    }
};
