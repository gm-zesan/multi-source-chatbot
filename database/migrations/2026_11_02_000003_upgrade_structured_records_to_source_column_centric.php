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
        Schema::table('structured_records', function (Blueprint $table) {
            if (! Schema::hasColumn('structured_records', 'source_values')) {
                $table->json('source_values')->nullable()->after('raw_values');
            }
            if (! Schema::hasColumn('structured_records', 'semantic_projections')) {
                $table->json('semantic_projections')->nullable()->after('mapped_attributes');
            }
            if (! Schema::hasColumn('structured_records', 'field_sensitivities')) {
                $table->json('field_sensitivities')->nullable()->after('semantic_projections');
            }
        });

        Schema::table('spreadsheet_import_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('spreadsheet_import_jobs', 'schema_version')) {
                $table->string('schema_version', 16)->default('2.1.0')->after('generation_id');
            }
            if (! Schema::hasColumn('spreadsheet_import_jobs', 'source_descriptors')) {
                $table->json('source_descriptors')->nullable()->after('detected_schema');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('structured_records', function (Blueprint $table) {
            if (Schema::hasColumn('structured_records', 'source_values')) {
                $table->dropColumn('source_values');
            }
            if (Schema::hasColumn('structured_records', 'semantic_projections')) {
                $table->dropColumn('semantic_projections');
            }
            if (Schema::hasColumn('structured_records', 'field_sensitivities')) {
                $table->dropColumn('field_sensitivities');
            }
        });

        Schema::table('spreadsheet_import_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('spreadsheet_import_jobs', 'schema_version')) {
                $table->dropColumn('schema_version');
            }
            if (Schema::hasColumn('spreadsheet_import_jobs', 'source_descriptors')) {
                $table->dropColumn('source_descriptors');
            }
        });
    }
};

