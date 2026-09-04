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
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();
            $table->string('source')->nullable();
            $table->string('external_user_id')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'external_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
