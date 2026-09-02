<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concept_phrase_patterns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->default(0);

            // e.g., RETURN_POLICY, DELIVERY_TIMELINE, MULTI_ENTITY_DETECTION
            $table->string('concept_key', 100);

            // CONCEPT_META: phrase=NULL, target_doc_type=NOT NULL
            // POSITIVE:     phrase=NOT NULL, target_doc_type=NULL
            // NEGATIVE_GUARD: phrase=NOT NULL, target_doc_type=NULL
            $table->enum('pattern_type', ['CONCEPT_META', 'POSITIVE', 'NEGATIVE_GUARD'])
                  ->default('POSITIVE');

            $table->string('phrase', 255)->nullable();
            $table->string('target_doc_type', 100)->nullable();
            // e.g., return_policy | delivery_policy | payment_policy | null (for MULTI_ENTITY_DETECTION)

            $table->enum('status', ['DRAFT', 'ACTIVE', 'DEPRECATED'])->default('DRAFT');
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            // Prevent duplicate phrases per (workspace, concept).
            // CONCEPT_META rows (phrase=NULL) collapse to one per concept via app-level validation.
            // POSITIVE / NEGATIVE_GUARD rows are deduplicated by phrase.
            $table->unique(
                ['workspace_id', 'concept_key', 'phrase'],
                'uq_cpp_workspace_concept_phrase'
            );

            $table->index(['workspace_id', 'concept_key', 'pattern_type', 'status'], 'idx_cpp_wcps');
            $table->index('status', 'idx_cpp_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concept_phrase_patterns');
    }
};
