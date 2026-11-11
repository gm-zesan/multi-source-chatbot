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
        Schema::create('structured_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('document_id');
            $table->foreign('document_id')->references('id')->on('knowledge_documents')->cascadeOnDelete();
            $table->uuid('import_job_id')->nullable();
            $table->foreign('import_job_id')->references('id')->on('spreadsheet_import_jobs')->nullOnDelete();

            // Provenance
            $table->string('sheet_name', 128)->default('Sheet1');
            $table->unsignedInteger('row_index'); // Original file row number (1-based)
            $table->string('record_identifier', 128)->nullable(); // Business key / ID (e.g. SKU, Order #, Emp ID)

            // Typed Canonical Projections (Indexed for instant aggregation and analytical query)
            $table->string('canonical_entity_name')->nullable();
            $table->string('canonical_category', 128)->nullable();
            $table->decimal('canonical_amount', 15, 4)->nullable(); // Price, salary, total, cost
            $table->decimal('canonical_quantity', 15, 4)->nullable(); // Stock, count, units
            $table->dateTime('canonical_date')->nullable(); // Order date, timestamp, attendance date
            $table->string('canonical_status', 64)->nullable(); // Paid, delivered, active, pending
            $table->string('canonical_person', 128)->nullable(); // Salesman, employee, customer name
            $table->string('canonical_contact', 128)->nullable(); // Phone, email

            // Payload & Preserved Attributes
            $table->json('raw_values'); // Exact unmutated cell values by original column name
            $table->json('source_values')->nullable();
            $table->json('mapped_attributes')->nullable(); // Canonical fields normalized
            $table->json('semantic_projections')->nullable();
            $table->json('field_sensitivities')->nullable();
            $table->json('custom_attributes')->nullable(); // All unmapped columns preserved safely
            $table->string('content_fingerprint', 64)->nullable(); // Hash of row values for duplicate detection
            $table->boolean('is_public')->default(false); // Only public catalog descriptions may be exposed to retrieval

            $table->timestamps();
            $table->softDeletes();

            // Indexes for high-performance multi-tenant queries
            $table->index(['workspace_id', 'document_id'], 'struct_rec_ws_doc_idx');
            $table->index(['workspace_id', 'record_identifier'], 'struct_rec_ws_ident_idx');
            $table->index(['workspace_id', 'canonical_entity_name'], 'struct_rec_ws_entity_idx');
            $table->index(['workspace_id', 'canonical_category'], 'struct_rec_ws_cat_idx');
            $table->index(['workspace_id', 'canonical_person'], 'struct_rec_ws_person_idx');
            $table->index(['workspace_id', 'canonical_date'], 'struct_rec_ws_date_idx');
            $table->index(['workspace_id', 'canonical_amount'], 'struct_rec_ws_amt_idx');
            $table->index(['workspace_id', 'content_fingerprint'], 'struct_rec_ws_fingerprint_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('structured_records');
    }
};

