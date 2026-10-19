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
        try {
            DB::table('knowledge_documents')->where('purpose', 'generic_analytics')->update(['purpose' => 'data_analytics']);
            DB::table('knowledge_documents')->whereIn('purpose', ['semantic_knowledge', 'product_catalog'])->update(['purpose' => 'knowledge_base']);
        } catch (\Throwable $e) {
            // Ignore if table not present yet
        }

        if (Schema::hasColumn('knowledge_documents', 'purpose')) {
            try {
                Schema::table('knowledge_documents', function (Blueprint $table) {
                    $table->string('purpose', 32)->default('data_analytics')->change();
                });
            } catch (\Throwable $e) {
                // SQLite change limitation safety
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::table('knowledge_documents')->where('purpose', 'data_analytics')->update(['purpose' => 'generic_analytics']);
            DB::table('knowledge_documents')->where('purpose', 'knowledge_base')->update(['purpose' => 'semantic_knowledge']);
        } catch (\Throwable $e) {
        }

        if (Schema::hasColumn('knowledge_documents', 'purpose')) {
            try {
                Schema::table('knowledge_documents', function (Blueprint $table) {
                    $table->string('purpose', 32)->default('generic_analytics')->change();
                });
            } catch (\Throwable $e) {
            }
        }
    }
};
