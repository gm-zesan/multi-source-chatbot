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
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('avatar')->index();
            $table->string('facebook_id')->nullable()->after('google_id')->index();
            $table->boolean('password_set')->default(true)->after('facebook_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
            $table->dropIndex(['facebook_id']);
            $table->dropColumn(['google_id', 'facebook_id', 'password_set']);
        });
    }
};
