<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_logs', function (Blueprint $table) {
            $table->decimal('routing_confidence', 5, 4)
                  ->nullable()
                  ->after('intent')
                  ->comment('Confidence score from ContextRouter (0.0000 – 1.0000)');

            $table->string('routing_source', 50)
                  ->nullable()
                  ->after('routing_confidence')
                  ->comment('Resolved source connection (db_01, db_02, etc.)');

            $table->string('routing_method', 20)
                  ->nullable()
                  ->after('routing_source')
                  ->comment('Routing method used: semantic, keyword_fallback, or none');
        });
    }

    public function down(): void
    {
        Schema::table('chat_logs', function (Blueprint $table) {
            $table->dropColumn(['routing_confidence', 'routing_source', 'routing_method']);
        });
    }
};
