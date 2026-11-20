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
        // 1. Workspace Prepaid Wallets Table
        Schema::create('workspace_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 10, 2)->default(50.00)->comment('Current balance in BDT (৳50 starter credit)');
            $table->string('currency', 8)->default('BDT');
            $table->decimal('rate_per_message', 6, 2)->default(0.40)->comment('Charge per AI reply (৳0.40)');
            $table->boolean('is_frozen')->default(false)->comment('True if frozen due to zero/negative balance');
            $table->decimal('low_balance_threshold', 8, 2)->default(20.00);
            $table->timestamps();

            $table->index(['workspace_id', 'is_frozen'], 'ws_wallet_status_idx');
        });

        // 2. Workspace Wallet Transactions Ledger Table
        Schema::create('workspace_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('workspace_wallets')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['credit', 'debit'])->index();
            $table->string('category', 32)->index(); // recharge, message_usage, bonus, refund, adjustment
            $table->decimal('amount', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->string('reference_id', 120)->nullable()->index();
            $table->string('description', 255);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at'], 'ws_tx_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_wallet_transactions');
        Schema::dropIfExists('workspace_wallets');
    }
};
