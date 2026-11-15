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
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('currency', 8)->default('BDT')->after('slug');
            $table->string('support_phone', 32)->nullable()->after('currency');
            $table->string('support_email', 128)->nullable()->after('support_phone');
            $table->text('business_address')->nullable()->after('support_email');
            $table->json('operating_hours')->nullable()->after('business_address');
            $table->json('settings')->nullable()->after('operating_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn([
                'currency',
                'support_phone',
                'support_email',
                'business_address',
                'operating_hours',
                'settings',
            ]);
        });
    }
};
