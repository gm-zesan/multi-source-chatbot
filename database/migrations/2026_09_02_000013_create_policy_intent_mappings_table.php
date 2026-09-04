<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_intent_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->default(0);

            // e.g., return_policy | delivery_policy | payment_policy | warranty_policy
            $table->string('policy_name', 100);

            // Trigger cue phrase found in user query
            // e.g., "রিটার্ন পলিসি", "ডেলিভারি চার্জ", "delivery charge"
            $table->string('cue_phrase', 255);

            // The document_type this policy cue should boost in reranking
            // e.g., return_policy | delivery_policy | payment_policy
            $table->string('target_doc_type', 100);

            $table->enum('status', ['DRAFT', 'ACTIVE', 'DEPRECATED'])->default('DRAFT');
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'policy_name', 'cue_phrase'], 'uq_policy_cue');
            $table->index(['workspace_id', 'policy_name', 'status'], 'idx_pim_wps');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_intent_mappings');
    }
};
