<?php

namespace App\Modules\Search\Models;

use Illuminate\Database\Eloquent\Model;

class SearchEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'query',
        'normalized_query',
        'filters',
        'result_count',
        'cache_hit',
        'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'cache_hit' => 'boolean',
            'searched_at' => 'datetime',
        ];
    }
}
