<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceTableColumn extends Model
{
    protected $table = 'source_table_columns';

    protected $fillable = [
        'table_name',
        'column_name',
    ];
}
