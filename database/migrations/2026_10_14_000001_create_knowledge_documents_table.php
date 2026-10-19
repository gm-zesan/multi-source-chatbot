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
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('mime_type', 128);
            $table->unsignedBigInteger('file_size');
            $table->string('content_hash', 64);
            $table->string('active_content_hash', 64)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('lifecycle_status', 32)->default('pending_ingestion');
            $table->string('purpose', 32)->default('data_analytics');
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedInteger('sheets_count')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->string('active_generation_id', 36)->nullable();
            $table->boolean('is_active')->default(false);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Workspace-scoped lookup and status filtering indexes
            $table->index(['workspace_id', 'content_hash']);
            $table->index(['workspace_id', 'lifecycle_status']);
            $table->index(['workspace_id', 'is_active']);
            $table->index(['workspace_id', 'purpose', 'is_active'], 'knowledge_docs_ws_purpose_active_idx');
            $table->unique(['workspace_id', 'active_content_hash'], 'knowledge_docs_active_hash_unique');
            $table->index(['workspace_id', 'source_id'], 'knowledge_docs_ws_source_idx');
            $table->index(['workspace_id', 'active_generation_id'], 'knowledge_docs_ws_act_gen_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
