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
     * Enforces explicit financial transaction classification and provider-scoped transaction uniqueness.
     */
    public function up(): void
    {
        Schema::table('analytics_payments', function (Blueprint $table) {
            $table->string('transaction_type', 32)
                ->default('courier_settlement')
                ->after('amount')
                ->comment('customer_advance, courier_settlement, customer_refund, sales_return, adjustment');

            $table->string('provider', 50)
                ->default('manual')
                ->after('transaction_type')
                ->comment('Courier or gateway provider (steadfast, pathao, bkash, nagad, manual, etc.)');
        });

        // Backfill existing rows with deterministic classifications
        DB::table('analytics_payments')
            ->where('payment_method', 'courier_settlement')
            ->update([
                'transaction_type' => 'courier_settlement',
                'provider'         => 'courier',
            ]);

        DB::table('analytics_payments')
            ->where('payment_method', 'refund')
            ->orWhere('amount', '<', 0)
            ->update([
                'transaction_type' => 'customer_refund',
                'provider'         => 'manual',
            ]);

        DB::table('analytics_payments')
            ->whereIn('payment_method', ['bkash', 'nagad', 'rocket', 'upay', 'card', 'bank'])
            ->where('amount', '>', 0)
            ->update([
                'transaction_type' => 'customer_advance',
                'provider'         => DB::raw('payment_method'),
            ]);

        // Defensive Pre-flight check: verify provider-scoped uniqueness before applying index
        $duplicates = DB::table('analytics_payments')
            ->whereNotNull('transaction_ref')
            ->select('workspace_id', 'provider', 'transaction_ref', DB::raw('count(*) as count'))
            ->groupBy('workspace_id', 'provider', 'transaction_ref')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'Pre-flight check failed: Found existing duplicate (workspace_id, provider, transaction_ref) in analytics_payments: ' .
                $duplicates->toJson()
            );
        }

        Schema::table('analytics_payments', function (Blueprint $table) {
            // Drop old coarse unique index if present
            if (Schema::hasIndex('analytics_payments', 'an_payments_ws_trx_unique')) {
                $table->dropUnique('an_payments_ws_trx_unique');
            }

            // Create provider-scoped unique index
            $table->unique(['workspace_id', 'provider', 'transaction_ref'], 'an_payments_ws_prov_trx_unique');
            $table->index(['workspace_id', 'transaction_type'], 'an_payments_ws_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_payments', function (Blueprint $table) {
            $table->dropUnique('an_payments_ws_prov_trx_unique');
            $table->dropIndex('an_payments_ws_type_index');
            $table->unique(['workspace_id', 'transaction_ref'], 'an_payments_ws_trx_unique');
            $table->dropColumn(['transaction_type', 'provider']);
        });
    }
};
