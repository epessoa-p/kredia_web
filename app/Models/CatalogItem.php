<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogItem extends Model
{
    protected $fillable = [
        'catalog_id',
        'product_id',
        'custom_name',
        'custom_description',
        'custom_price',
        'sort_order',
    ];

    protected $casts = [
        'custom_price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
