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
        Schema::create('order_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_order_id')->constrained('workspace_orders')->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('customer_name', 128)->nullable();
            $table->string('customer_phone', 64)->nullable()->index();
            $table->string('channel', 32)->default('whatsapp');
            $table->unsignedTinyInteger('rating')->nullable(); // 1 - 5 stars
            $table->text('review_text')->nullable();
            $table->enum('status', ['requested', 'received', 'skipped'])->default('requested')->index();
            $table->timestamp('feedback_requested_at')->nullable();
            $table->timestamp('feedback_received_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['workspace_order_id'], 'unique_order_feedback');
            $table->index(['workspace_id', 'rating']);
            $table->index(['workspace_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_feedbacks');
    }
};
