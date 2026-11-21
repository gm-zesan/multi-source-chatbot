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
        Schema::create('workspace_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('order_number', 64)->unique();
            $table->string('channel', 32)->default('facebook'); // facebook, whatsapp, widget, telegram, tiktok, api
            $table->string('customer_name', 128)->nullable();
            $table->string('customer_phone', 64)->nullable()->index();
            $table->text('delivery_address')->nullable();
            $table->text('product_summary')->nullable();
            $table->decimal('order_amount', 10, 2)->nullable();
            $table->decimal('delivery_fee', 8, 2)->default(0.00);
            $table->enum('status', ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'])->default('pending')->index();
            $table->string('courier_provider', 50)->nullable()->index(); // steadfast, pathao, redx
            $table->string('courier_consignment_id', 150)->nullable()->index();
            $table->string('courier_tracking_code', 150)->nullable()->index();
            $table->string('courier_status', 100)->nullable();
            $table->json('courier_response')->nullable();
            $table->timestamp('courier_dispatched_at')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamps();

            $table->index(['workspace_id', 'status'], 'ws_orders_status_idx');
            $table->index(['workspace_id', 'captured_at'], 'ws_orders_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_orders');
    }
};
