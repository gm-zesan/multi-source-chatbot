<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class RegistryResolver
{
    public function resolveTable(
        string $table
    ): ?array
    {
        $canonicalTable = DB::table('source_tables')
            ->where('alias', $table)
            ->orWhere('table_name', $table)
            ->orderBy('id')
            ->value('table_name');

        if (!$canonicalTable) {
            return null;
        }

        $record = DB::table('source_tables')
            ->where('table_name', $canonicalTable)
            ->orderBy('id')
            ->first();

        if (!$record) {
            return null;
        }

        return [
            'source_id' => $record->source_id,
            'table_name' => $canonicalTable,
        ];
    }
}
