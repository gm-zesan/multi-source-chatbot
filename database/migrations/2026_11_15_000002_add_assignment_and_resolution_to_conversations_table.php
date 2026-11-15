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
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('assigned_to_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('priority', 32)->default('normal')->after('assigned_to_user_id');
            $table->timestamp('resolved_at')->nullable()->after('priority');
            $table->index(['channel_account_id', 'status'], 'conv_channel_status_idx');
            $table->index(['assigned_to_user_id', 'status'], 'conv_assigned_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropIndex('conv_channel_status_idx');
            $table->dropIndex('conv_assigned_status_idx');
            $table->dropColumn([
                'assigned_to_user_id',
                'priority',
                'resolved_at',
            ]);
        });
    }
};
