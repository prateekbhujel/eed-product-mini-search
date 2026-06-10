<?php

namespace App\Modules\Search\Models;

use Illuminate\Database\Eloquent\Model;

class SearchSynonym extends Model
{
    protected $fillable = [
        'term',
        'replacement',
        'weight',
    ];
}
