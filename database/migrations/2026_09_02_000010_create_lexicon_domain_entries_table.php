<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lexicon_domain_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id')->default(0);
            $table->string('concept_key', 100);
            $table->string('pattern', 255);
            $table->text('expansion');
            $table->string('language', 10)->default('bn');
            $table->enum('status', ['DRAFT', 'ACTIVE', 'DEPRECATED'])->default('DRAFT');
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'concept_key', 'pattern'], 'uq_lexicon_entry');
            $table->index(['workspace_id', 'concept_key', 'status'], 'idx_lexicon_wcs');
            $table->index('status', 'idx_lexicon_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lexicon_domain_entries');
    }
};
