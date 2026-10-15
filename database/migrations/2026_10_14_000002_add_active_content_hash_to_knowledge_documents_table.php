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
        if (! Schema::hasColumn('knowledge_documents', 'active_content_hash')) {
            Schema::table('knowledge_documents', function (Blueprint $table) {
                $table->string('active_content_hash', 64)->nullable()->after('content_hash');
            });

            DB::table('knowledge_documents')
                ->whereNull('deleted_at')
                ->update(['active_content_hash' => DB::raw('content_hash')]);

            Schema::table('knowledge_documents', function (Blueprint $table) {
                $table->unique(['workspace_id', 'active_content_hash'], 'knowledge_docs_active_hash_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('knowledge_documents', 'active_content_hash')) {
            Schema::table('knowledge_documents', function (Blueprint $table) {
                $table->dropUnique('knowledge_docs_active_hash_unique');
                $table->dropColumn('active_content_hash');
            });
        }
    }
};
