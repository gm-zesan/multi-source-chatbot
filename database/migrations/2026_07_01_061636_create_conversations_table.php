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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_user_id');
            $table->string('customer_name')->nullable();
            $table->string('customer_avatar')->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->enum('last_direction', ['inbound','outbound']);
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('status')->default('open');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique([
                'channel_account_id',
                'external_user_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
