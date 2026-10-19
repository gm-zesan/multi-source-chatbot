<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('knowledge_documents', 'purpose')) {
                $table->string('purpose', 32)->default('data_analytics')->after('lifecycle_status');
                $table->index(['workspace_id', 'purpose', 'is_active'], 'knowledge_docs_ws_purpose_active_idx');
            }
        });

        // Migrate existing purpose from metadata JSON if present
        try {
            DB::table('knowledge_documents')->whereNull('purpose')->orWhere('purpose', '')->chunkById(100, function ($docs) {
                foreach ($docs as $doc) {
                    $meta = json_decode($doc->metadata ?? '{}', true) ?: [];
                    $purpose = $meta['ingestion_purpose'] ?? 'data_analytics';
                    DB::table('knowledge_documents')->where('id', $doc->id)->update(['purpose' => $purpose]);
                }
            });
        } catch (\Throwable $e) {
            // Ignore if metadata column is not json parsable
        }

        // Clean up legacy generation indexes
        $legacyIndexes = [
            'knowledge_docs_ws_req_gen_idx',
            'knowledge_docs_ws_act_gen_idx',
        ];

        foreach ($legacyIndexes as $indexName) {
            try {
                Schema::table('knowledge_documents', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            } catch (\Throwable $e) {
                // Index might not exist in SQLite or already dropped
            }
        }

        // Drop unused generation and worker columns
        $legacyColumns = [
            'requested_generation_id',
            'active_generation_id',
            'lease_attempt_uuid',
            'active_attempt_uuid',
            'heartbeat_at',
            'retry_count',
            'next_retry_at',
        ];

        foreach ($legacyColumns as $col) {
            if (Schema::hasColumn('knowledge_documents', $col)) {
                try {
                    Schema::table('knowledge_documents', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                } catch (\Throwable $e) {
                    // Ignore column drop issue on specific SQLite drivers
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table) {
            if (Schema::hasColumn('knowledge_documents', 'purpose')) {
                try {
                    $table->dropIndex('knowledge_docs_ws_purpose_active_idx');
                } catch (\Throwable $e) {
                }
                $table->dropColumn('purpose');
            }

            if (! Schema::hasColumn('knowledge_documents', 'requested_generation_id')) {
                $table->string('requested_generation_id', 36)->nullable();
            }
            if (! Schema::hasColumn('knowledge_documents', 'active_generation_id')) {
                $table->string('active_generation_id', 36)->nullable();
            }
            if (! Schema::hasColumn('knowledge_documents', 'lease_attempt_uuid')) {
                $table->string('lease_attempt_uuid', 36)->nullable();
            }
            if (! Schema::hasColumn('knowledge_documents', 'active_attempt_uuid')) {
                $table->string('active_attempt_uuid', 36)->nullable();
            }
            if (! Schema::hasColumn('knowledge_documents', 'heartbeat_at')) {
                $table->dateTime('heartbeat_at')->nullable();
            }
            if (! Schema::hasColumn('knowledge_documents', 'retry_count')) {
                $table->unsignedSmallInteger('retry_count')->default(0);
            }
            if (! Schema::hasColumn('knowledge_documents', 'next_retry_at')) {
                $table->dateTime('next_retry_at')->nullable();
            }
        });
    }
};
