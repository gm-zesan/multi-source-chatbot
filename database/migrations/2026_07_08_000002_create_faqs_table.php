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
        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('faq_categories')->nullOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->text('searchable_text')->nullable();
            $table->string('embedding_version')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedMediumInteger('priority')->default(0);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'is_active', 'priority']);
            $table->index(['workspace_id', 'category_id']);
            $table->fullText(['question', 'searchable_text']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
