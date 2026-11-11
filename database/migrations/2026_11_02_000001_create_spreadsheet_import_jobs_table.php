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
        Schema::create('spreadsheet_import_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('knowledge_documents')->cascadeOnDelete();
            $table->string('sheet_name', 128)->nullable();
            $table->string('status', 32)->default('queued'); // queued, inspecting, awaiting_mapping, processing, completed, completed_with_errors, failed, cancelled
            $table->string('conflict_mode', 32)->default('skip'); // skip, overwrite, append
            $table->string('generation_id', 36); // Fencing token to invalidate stale attempts
            $table->string('schema_version', 16)->default('2.1.0');
            $table->unsignedInteger('header_row_index')->default(0); // 0-based row index of header
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('staged_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('checkpoint_row')->default(0);
            $table->json('detected_schema')->nullable(); // Sheets, columns, candidate headers, types
            $table->json('source_descriptors')->nullable();
            $table->json('mapping_config')->nullable(); // Confirmed column -> canonical category mapping
            $table->json('validation_errors')->nullable(); // Detailed row-level errors [{row, col, reason, value}]
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status'], 'ss_import_ws_status_idx');
            $table->index(['workspace_id', 'document_id'], 'ss_import_ws_doc_idx');
            $table->index(['workspace_id', 'generation_id'], 'ss_import_ws_gen_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spreadsheet_import_jobs');
    }
};

