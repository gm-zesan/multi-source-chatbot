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
        Schema::create('super_admin_governance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('justification', 500);
            $table->string('reason', 500)->nullable();
            $table->string('ticket_ref', 100)->nullable();
            $table->string('ticket_reference', 100)->nullable();
            $table->boolean('read_only_scope')->default(true);
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->json('audit_log')->nullable();
            $table->timestamps();

            $table->index(['actor_id', 'target_workspace_id', 'revoked_at', 'expires_at'], 'idx_gov_session_actor');
            $table->index(['user_id', 'target_workspace_id', 'revoked_at', 'expires_at'], 'idx_gov_session_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_governance_sessions');
    }
};
