<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_usage_quotas', function (Blueprint $table) {
            $table->unsignedInteger('whatsapp_messages_sent')->default(0)->after('spreadsheet_queries_count');
            $table->unsignedInteger('messenger_messages_sent')->default(0)->after('whatsapp_messages_sent');
            $table->unsignedInteger('whatsapp_free_limit')->default(1000)->after('messenger_messages_sent');
            $table->boolean('zero_charge_protection')->default(true)->after('whatsapp_free_limit');
            $table->timestamp('quota_warning_sent_at')->nullable()->after('zero_charge_protection');
            $table->timestamp('quota_exhausted_sent_at')->nullable()->after('quota_warning_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_usage_quotas', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_messages_sent',
                'messenger_messages_sent',
                'whatsapp_free_limit',
                'zero_charge_protection',
                'quota_warning_sent_at',
                'quota_exhausted_sent_at',
            ]);
        });
    }
};
