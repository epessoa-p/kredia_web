<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementImage extends Model
{
    protected $table = 'movement_images';

    protected $fillable = [
        'inventory_movement_id',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }
}
