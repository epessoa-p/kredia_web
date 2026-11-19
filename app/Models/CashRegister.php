<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashRegister extends Model
{
    use Concerns\BelongsToCompany;

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'description',
        'assigned_personal_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedPersonal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'assigned_personal_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashRegisterSession::class);
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(CashRegisterSession::class)->whereNull('closed_at')->latest();
    }

    /** Última sesión cerrada (para mostrar el último cierre de una caja cerrada). */
    public function lastClosedSession(): HasOne
    {
        return $this->hasOne(CashRegisterSession::class)
            ->ofMany(['closed_at' => 'max'], fn ($q) => $q->whereNotNull('closed_at'));
    }

    public function isOpen(): bool
    {
        return $this->activeSession()->exists();
    }
}
