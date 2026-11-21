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
        // 1. Subscription Plans Table
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('slug', 64)->unique();
            $table->string('badge', 64)->nullable()->comment('e.g. Standard Metered, Free Trial, Most Popular');
            $table->string('tagline', 255)->nullable()->comment('Short description under plan title');
            $table->decimal('price_monthly', 10, 2)->default(0.00);
            $table->decimal('price_yearly', 10, 2)->default(0.00);
            $table->string('currency', 8)->default('BDT');
            $table->string('billing_period_text', 64)->nullable()->comment('e.g. / AI reply, Free Credit, / month, Custom Quote');
            $table->string('price_note', 255)->nullable()->comment('Subtext under price, e.g. Zero monthly commitment');
            $table->integer('max_conversations_monthly')->default(500)->comment('-1 for unlimited');
            $table->integer('max_knowledge_docs')->default(5);
            $table->integer('max_staff_seats')->default(2);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('cta_text', 64)->nullable()->comment('Button text');
            $table->string('cta_url', 255)->nullable()->comment('Target url');
            $table->timestamps();
        });

        // 2. Workspace Subscriptions Table
        Schema::create('workspace_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->string('status', 32)->default('active')->index(); // active, trialing, past_due, canceled
            $table->string('billing_cycle', 16)->default('monthly'); // monthly, yearly
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('payment_gateway', 32)->default('manual'); // bkash, sslcommerz, stripe, manual
            $table->string('gateway_subscription_id')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status'], 'ws_sub_status_idx');
        });

        // 3. Workspace Usage Quotas Table (Monthly Bucket)
        Schema::create('workspace_usage_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('period_year_month', 7)->comment('Format: YYYY-MM');
            $table->unsignedInteger('conversation_count')->default(0);
            $table->unsignedInteger('ai_messages_count')->default(0);
            $table->unsignedInteger('spreadsheet_queries_count')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'period_year_month'], 'ws_quota_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_usage_quotas');
        Schema::dropIfExists('workspace_subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
