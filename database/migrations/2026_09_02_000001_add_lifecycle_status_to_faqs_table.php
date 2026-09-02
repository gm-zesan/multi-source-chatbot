<?php

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
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('lifecycle_status', 32)->default('active')->after('is_active');
            $table->text('sync_error')->nullable()->after('lifecycle_status');

            $table->index(['workspace_id', 'lifecycle_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'lifecycle_status']);
            $table->dropColumn(['lifecycle_status', 'sync_error']);
        });
    }
};
