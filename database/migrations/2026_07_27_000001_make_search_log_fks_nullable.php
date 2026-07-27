<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make conversation_id and message_id nullable in knowledge_search_logs.
     *
     * These columns were non-nullable with FK constraints, which caused a
     * silent DB error (and lost log rows) when FAQAnswerEngine::answer() was
     * called without a conversation context (e.g. CLI, API, health-check).
     * The fix: null is the correct sentinel for "no conversation", not 0.
     */
    public function up(): void
    {
        // ── knowledge_search_logs ─────────────────────────────────────────
        Schema::table('knowledge_search_logs', function (Blueprint $table) {
            // Drop the existing strict FK constraints first
            $table->dropForeign(['conversation_id']);
            $table->dropForeign(['message_id']);

            // Re-add both columns as nullable with nullOnDelete FKs
            $table->foreignId('conversation_id')
                ->nullable()
                ->change();

            $table->foreignId('message_id')
                ->nullable()
                ->change();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->nullOnDelete();

            $table->foreign('message_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });

        // ── unanswered_questions ──────────────────────────────────────────
        // Same root cause: conversation_id was non-nullable, causing FK
        // violations when saving unanswered queries without a conversation.
        Schema::table('unanswered_questions', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);

            $table->foreignId('conversation_id')
                ->nullable()
                ->change();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse: restore non-nullable FK constraints.
     *
     * WARNING: this will fail if any rows already have NULL in these columns.
     */
    public function down(): void
    {
        Schema::table('knowledge_search_logs', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropForeign(['message_id']);

            $table->foreignId('conversation_id')
                ->nullable(false)
                ->change();

            $table->foreignId('message_id')
                ->nullable(false)
                ->change();

            $table->foreign('conversation_id')
                ->references('id')
                ->on('conversations')
                ->cascadeOnDelete();

            $table->foreign('message_id')
                ->references('id')
                ->on('messages')
                ->cascadeOnDelete();
        });
    }
};
