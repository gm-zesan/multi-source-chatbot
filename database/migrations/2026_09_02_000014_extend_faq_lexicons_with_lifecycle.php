<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faq_lexicons', function (Blueprint $table) {
            // Lifecycle status — mirrors FAQ lifecycle pattern
            $table->enum('status', ['DRAFT', 'ACTIVE', 'DEPRECATED'])
                  ->default('ACTIVE')
                  ->after('is_validated');

            // Row-level version for snapshot versioning calculations
            $table->unsignedSmallInteger('version')
                  ->default(1)
                  ->after('status');

            $table->unsignedBigInteger('activated_by')
                  ->nullable()
                  ->after('version');

            $table->timestamp('activated_at')
                  ->nullable()
                  ->after('activated_by');

            $table->index(['workspace_id', 'status'], 'idx_faq_lexicons_ws');
        });
    }

    public function down(): void
    {
        Schema::table('faq_lexicons', function (Blueprint $table) {
            $table->dropIndex('idx_faq_lexicons_ws');
            $table->dropColumn(['status', 'version', 'activated_by', 'activated_at']);
        });
    }
};
