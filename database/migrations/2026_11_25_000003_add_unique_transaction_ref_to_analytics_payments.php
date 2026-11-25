<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforce tenant-scoped unique idempotency on payment and refund transaction references.
     */
    public function up(): void
    {
        // 1. Defensive Pre-flight check: ensure no duplicate (workspace_id, transaction_ref) exists
        $duplicates = DB::table('analytics_payments')
            ->whereNotNull('transaction_ref')
            ->select('workspace_id', 'transaction_ref', DB::raw('count(*) as count'))
            ->groupBy('workspace_id', 'transaction_ref')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Pre-flight check failed: Found existing duplicate transaction_ref in analytics_payments: ' .
                $duplicates->toJson()
            );
        }

        Schema::table('analytics_payments', function (Blueprint $table) {
            $table->unique(['workspace_id', 'transaction_ref'], 'an_payments_ws_trx_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_payments', function (Blueprint $table) {
            $table->dropUnique('an_payments_ws_trx_unique');
        });
    }
};
