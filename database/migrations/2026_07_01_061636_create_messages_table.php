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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('external_message_id')->nullable();
            $table->enum('direction', ['inbound','outbound']);
            $table->enum('type', ['text','image','file','video','audio','location','sticker','template']);
            $table->longText('body');
            $table->string('status')->default('sent');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index([
                'conversation_id',
                'created_at'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
