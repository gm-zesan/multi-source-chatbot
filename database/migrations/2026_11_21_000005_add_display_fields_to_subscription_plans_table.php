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
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('badge', 64)->nullable()->after('slug')->comment('e.g. Standard Metered, Free Trial, Most Popular');
            $table->string('tagline', 255)->nullable()->after('badge')->comment('Short description under plan title');
            $table->string('billing_period_text', 64)->nullable()->after('currency')->comment('e.g. / AI reply, Free Credit, / month, Custom Quote');
            $table->string('price_note', 255)->nullable()->after('billing_period_text')->comment('Subtext under price, e.g. Zero monthly commitment');
            $table->boolean('is_featured')->default(false)->after('is_active');
            $table->integer('sort_order')->default(0)->after('is_featured');
            $table->string('cta_text', 64)->nullable()->after('sort_order')->comment('Button text');
            $table->string('cta_url', 255)->nullable()->after('cta_text')->comment('Target url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn([
                'badge',
                'tagline',
                'billing_period_text',
                'price_note',
                'is_featured',
                'sort_order',
                'cta_text',
                'cta_url',
            ]);
        });
    }
};
