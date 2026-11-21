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
        Schema::table('workspace_orders', function (Blueprint $table) {
            $table->string('courier_provider', 50)->nullable()->after('status')->index(); // steadfast, pathao, redx
            $table->string('courier_consignment_id', 150)->nullable()->after('courier_provider')->index();
            $table->string('courier_tracking_code', 150)->nullable()->after('courier_consignment_id')->index();
            $table->string('courier_status', 100)->nullable()->after('courier_tracking_code');
            $table->json('courier_response')->nullable()->after('courier_status');
            $table->timestamp('courier_dispatched_at')->nullable()->after('courier_response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspace_orders', function (Blueprint $table) {
            $table->dropColumn([
                'courier_provider',
                'courier_consignment_id',
                'courier_tracking_code',
                'courier_status',
                'courier_response',
                'courier_dispatched_at',
            ]);
        });
    }
};
