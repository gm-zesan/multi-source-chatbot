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
        if (Schema::hasTable('knowledge_documents')) {
            Schema::table('knowledge_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('knowledge_documents', 'source_id')) {
                    $table->string('source_id', 64)->nullable()->after('content_hash');
                }
                if (! Schema::hasColumn('knowledge_documents', 'sheets_count')) {
                    $table->unsignedInteger('sheets_count')->default(1)->after('chunk_count');
                }
                if (! Schema::hasColumn('knowledge_documents', 'metadata')) {
                    $table->json('metadata')->nullable()->after('error_message');
                }
            });

            // Add index for workspace-scoped source lookup if column exists
            if (Schema::hasColumn('knowledge_documents', 'source_id')) {
                Schema::table('knowledge_documents', function (Blueprint $table) {
                    $table->index(['workspace_id', 'source_id'], 'knowledge_docs_ws_source_idx');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('knowledge_documents')) {
            Schema::table('knowledge_documents', function (Blueprint $table) {
                if (Schema::hasColumn('knowledge_documents', 'source_id')) {
                    $table->dropIndex('knowledge_docs_ws_source_idx');
                    $table->dropColumn('source_id');
                }
                if (Schema::hasColumn('knowledge_documents', 'sheets_count')) {
                    $table->dropColumn('sheets_count');
                }
                if (Schema::hasColumn('knowledge_documents', 'metadata')) {
                    $table->dropColumn('metadata');
                }
            });
        }
    }
};
