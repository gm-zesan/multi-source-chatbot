<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class RegistryService
{
    public static function getColumns(string $table): array
    {
        return DB::table('source_table_columns')
            ->where('table_name', $table)
            ->pluck('column_name')
            ->toArray();
    }
}