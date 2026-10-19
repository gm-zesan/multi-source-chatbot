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
        Schema::create('chat_logs', function (Blueprint $table) {
            $table->id();
            $table->text('query');
            $table->json('intent');
            $table->decimal('routing_confidence', 5, 4)
                  ->nullable()
                  ->comment('Confidence score from ContextRouter (0.0000 – 1.0000)');
            $table->string('routing_source', 50)
                  ->nullable()
                  ->comment('Resolved source connection (db_01, db_02, etc.)');
            $table->string('routing_method', 20)
                  ->nullable()
                  ->comment('Routing method used: semantic, keyword_fallback, or none');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_logs');
    }
};
