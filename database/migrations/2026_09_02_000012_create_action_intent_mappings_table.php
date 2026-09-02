<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_intent_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->default(0);

            // e.g., invoice | payment_method | plan_change
            $table->string('intent_name', 100);

            // Trigger keyword in user query
            // e.g., "view", "download", "পেমেন্ট মেথড পরিবর্তন"
            $table->string('action_keyword', 100);

            // Typesense question string to BOOST when intent matches
            $table->string('target_phrase', 255)->nullable();

            // Typesense question string to DEMOTE when intent matches
            $table->string('penalty_phrase', 255)->nullable();

            // ── ACTION EXECUTION INVARIANT ────────────────────────────────────
            // execution_enabled is FUTURE-ONLY metadata.
            // Python runtime MUST treat this as FALSE regardless of DB value.
            // Current release NEVER executes tools based on this flag.
            // execution_enabled=TRUE requires execution_handler to be non-NULL.
            $table->boolean('execution_enabled')->default(false);
            $table->string('execution_handler', 100)->nullable();
            // future values: "placeOrder" | "cancelOrder" | "trackShipment"

            $table->enum('status', ['DRAFT', 'ACTIVE', 'DEPRECATED'])->default('DRAFT');
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'intent_name', 'action_keyword'], 'uq_action_mapping');
            $table->index(['workspace_id', 'intent_name', 'status'], 'idx_aim_wis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_intent_mappings');
    }
};
