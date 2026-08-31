<?php

declare(strict_types=1);

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
        Schema::create('faq_lexicons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('faq_id')->constrained('faqs')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('domain', 100);
            $table->string('intent', 100);
            $table->json('canonical_terms');
            $table->json('bangla_terms');
            $table->json('commerce_terms');
            $table->string('generated_by', 50)->default('deepseek');
            $table->boolean('is_validated')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'domain']);
            $table->index('intent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faq_lexicons');
    }
};
