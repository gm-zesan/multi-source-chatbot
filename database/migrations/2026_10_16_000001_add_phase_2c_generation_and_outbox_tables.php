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
        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->string('requested_generation_id', 36)->nullable()->after('version');
            $table->string('active_generation_id', 36)->nullable()->after('requested_generation_id');
            $table->string('lease_attempt_uuid', 36)->nullable()->after('active_generation_id');
            $table->string('active_attempt_uuid', 36)->nullable()->after('lease_attempt_uuid');
            $table->dateTime('heartbeat_at')->nullable()->after('error_message');
            $table->unsignedSmallInteger('retry_count')->default(0)->after('heartbeat_at');
            $table->dateTime('next_retry_at')->nullable()->after('retry_count');

            $table->index(['workspace_id', 'requested_generation_id'], 'knowledge_docs_ws_req_gen_idx');
            $table->index(['workspace_id', 'active_generation_id'], 'knowledge_docs_ws_act_gen_idx');
        });

        Schema::create('document_outbox', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_uuid', 36)->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('document_id', 36);
            $table->string('generation_id', 36);
            $table->string('attempt_uuid', 36)->nullable();
            $table->string('event_type', 32);
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->dateTime('locked_until')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at', 'locked_until'], 'outbox_processing_idx');
            $table->index(['workspace_id', 'document_id', 'attempt_uuid'], 'outbox_doc_attempt_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_outbox');

        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropIndex('knowledge_docs_ws_req_gen_idx');
            $table->dropIndex('knowledge_docs_ws_act_gen_idx');

            $table->dropColumn([
                'requested_generation_id',
                'active_generation_id',
                'lease_attempt_uuid',
                'active_attempt_uuid',
                'heartbeat_at',
                'retry_count',
                'next_retry_at',
            ]);
        });
    }
};
