<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleImage extends Model
{
    protected $fillable = [
        'sale_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
