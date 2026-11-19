<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rol = bolsa de permisos.
 * - company_id NULL  → rol de SISTEMA (catálogo global, solo lo edita el superadmin).
 * - company_id = X   → rol PROPIO de la empresa X (lo crean sus cargos); nadie más lo ve ni lo modifica.
 */
class Role extends Model
{
    use HasFactory, SoftDeletes;

    public const SUPER_ADMIN = 'super_admin';

    protected $fillable = ['name', 'slug', 'description', 'company_id'];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user', 'role_id', 'user_id');
    }

    public function isSystem(): bool
    {
        return $this->company_id === null;
    }

    /**
     * Roles que se pueden asignar a un usuario en la empresa dada:
     * roles de sistema + roles propios de esa empresa, nunca super_admin.
     */
    public function scopeAssignableFor(Builder $query, ?int $companyId): Builder
    {
        return $query->where('slug', '!=', self::SUPER_ADMIN)
            ->where(function (Builder $q) use ($companyId) {
                $q->whereNull('company_id');
                if ($companyId) {
                    $q->orWhere('company_id', $companyId);
                }
            });
    }

    /** ¿Este rol puede usarse/verse desde la empresa dada? */
    public function isVisibleTo(?int $companyId): bool
    {
        return $this->slug !== self::SUPER_ADMIN
            && ($this->company_id === null || (int) $this->company_id === (int) $companyId);
    }
}
