<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCompatibleModel extends Model
{
    protected $fillable = [
        'product_id',
        'model_number',
        'model_family',
        'normalized_model_number',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
