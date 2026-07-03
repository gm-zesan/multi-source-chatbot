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
        Schema::create('c_r_m_contact_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('c_r_m_contact_id')->constrained('c_r_m_contacts')->cascadeOnDelete();
            $table->string('email');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->unique(['c_r_m_contact_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('c_r_m_contact_emails');
    }
};
