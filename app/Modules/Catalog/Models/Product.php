<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'supplier_source_id',
        'sku',
        'slug',
        'brand',
        'name',
        'family',
        'description',
        'price',
        'compare_price',
        'currency',
        'availability',
        'delivery_text',
        'delivery_days',
        'stock_quantity',
        'rating',
        'review_count',
        'image_path',
        'search_keywords',
        'specs',
        'is_active',
        'indexed_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'rating' => 'decimal:2',
            'specs' => 'array',
            'is_active' => 'boolean',
            'indexed_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(ProductIdentifier::class);
    }

    public function compatibleModels(): HasMany
    {
        return $this->hasMany(ProductCompatibleModel::class);
    }
}
