<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RegistryResolver
{
    public function resolveTable(
        string $table
    ): ?array
    {

        $record = DB::table('source_tables')
            ->where('table_name', $table)
            ->first();

        if (!$record) {
            return null;
        }

        return [
            'source_id' => $record->source_id,
            'table_name' => $record->table_name
        ];
    }
}