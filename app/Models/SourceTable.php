<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceTable extends Model
{
    protected $fillable = [
        'alias',
        'table_name',
        'source_id'
    ];
}
