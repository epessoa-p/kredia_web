<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autorización por única vez para eliminar una venta concreta.
 * La otorga un usuario con 'sales.delegate_delete' a otro usuario que no
 * tiene el permiso 'sales.delete'. Caduca a las 24h y se consume al usarla.
 */
class SaleDeleteGrant extends Model
{
    use Concerns\BelongsToCompany;

    use HasFactory;

    protected $fillable = [
        'company_id',
        'sale_id',
        'granted_to',
        'granted_by',
        'reason',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function grantedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_to');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** Vigente = sin usar, sin revocar y no caducada. */
    public function scopeActive($query)
    {
        return $query->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /** Autorización vigente de un usuario sobre una venta (o null). */
    public static function activeFor(int $saleId, int $userId): ?self
    {
        return static::active()
            ->where('sale_id', $saleId)
            ->where('granted_to', $userId)
            ->first();
    }
}
