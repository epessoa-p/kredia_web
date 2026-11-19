<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseImage extends Model
{
    protected $fillable = [
        'purchase_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    /** URL pública de la foto (la usa la app móvil). */
    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        return $this->path ? asset('storage/' . $this->path) : null;
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
