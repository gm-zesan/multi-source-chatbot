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
        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('faq_categories')->nullOnDelete();
            $table->string('document_type', 64)->default('faq');
            $table->string('question');
            $table->text('answer');
            $table->text('searchable_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('lifecycle_status', 32)->default('active');
            $table->text('sync_error')->nullable();
            $table->unsignedMediumInteger('priority')->default(0);
            $table->unsignedBigInteger('hit_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'is_active', 'priority']);
            $table->index(['workspace_id', 'category_id']);
            $table->index(['workspace_id', 'document_type']);
            $table->index(['workspace_id', 'lifecycle_status']);

            // Fulltext index — MySQL only (SQLite/test drivers don't support it)
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $table->fullText(['question', 'searchable_text']);
            }
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
