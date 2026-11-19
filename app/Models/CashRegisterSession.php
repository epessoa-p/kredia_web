<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegisterSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_register_id',
        'opened_by',
        'closed_by',
        'opening_amount',
        'closing_amount',
        'closing_breakdown',
        'opening_notes',
        'closing_notes',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'closing_breakdown' => 'array',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function openedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function loanPayments(): HasMany
    {
        return $this->hasMany(LoanPayment::class, 'cash_register_session_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /**
     * Saldo disponible actual de la caja:
     * apertura + ingresos - egresos.
     */
    public function currentBalance(): float
    {
        $income  = (float) $this->cashMovements()->where('type', 'income')->sum('amount');
        $expense = (float) $this->cashMovements()->where('type', 'expense')->sum('amount');

        return round((float) $this->opening_amount + $income - $expense, 2);
    }
}
