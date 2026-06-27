<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('source_table_columns')) {
            Schema::create('source_table_columns', function (Blueprint $table) {
                $table->id();
                $table->string('table_name');
                $table->string('column_name');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('source_table_columns');
    }
};
