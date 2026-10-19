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
                $table->uuid('document_id')->nullable();
                $table->string('generation_id', 36)->nullable();
                $table->string('source_row_key')->nullable();
                $table->string('source_type')->default('manual');
                $table->string('name');
                $table->string('sku')->nullable();
                $table->string('barcode')->nullable();
                $table->string('category')->default('General');
                $table->string('item_type')->default('standard');
                $table->decimal('unit_price', 10, 2);
                $table->decimal('cost_price', 10, 2)->default(0.00);
                $table->text('description')->nullable();
                $table->json('source_metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['workspace_id', 'category']);
                $table->index(['workspace_id', 'name']);
                $table->index(['workspace_id', 'sku'], 'analytics_prod_ws_sku_idx');
                $table->index(['document_id', 'generation_id'], 'analytics_prod_doc_gen_idx');
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
