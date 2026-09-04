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
        Schema::create('knowledge_search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->text('customer_query');
            $table->foreignUuid('matched_faq_id')->nullable()->constrained('faqs')->nullOnDelete();
            $table->decimal('keyword_score', 5, 4)->default(0);
            $table->decimal('semantic_score', 5, 4)->default(0);
            $table->decimal('final_score', 5, 4)->default(0);
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->string('answer_source')->default('none');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['conversation_id']);
            $table->index(['matched_faq_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_search_logs');
    }
};
