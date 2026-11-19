<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Cuota del plan de pagos de un préstamo en cuotas. */
class LoanInstallment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'loan_id', 'number', 'due_date', 'capital', 'interest', 'amount', 'penalty',
        'paid_capital', 'paid_interest', 'paid_penalty', 'status', 'paid_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'capital' => 'decimal:2',
        'interest' => 'decimal:2',
        'amount' => 'decimal:2',
        'penalty' => 'decimal:2',
        'paid_capital' => 'decimal:2',
        'paid_interest' => 'decimal:2',
        'paid_penalty' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LoanPaymentAllocation::class);
    }

    public function pendingCapital(): float
    {
        return round(max(0, (float) $this->capital - (float) $this->paid_capital), 2);
    }

    public function pendingInterest(): float
    {
        return round(max(0, (float) $this->interest - (float) $this->paid_interest), 2);
    }

    public function pendingPenalty(): float
    {
        return round(max(0, (float) $this->penalty - (float) $this->paid_penalty), 2);
    }

    /** Lo que falta pagar de la cuota (mora + interés + capital). */
    public function pendingTotal(): float
    {
        return round($this->pendingPenalty() + $this->pendingInterest() + $this->pendingCapital(), 2);
    }

    public function isOverdue(): bool
    {
        return $this->status !== self::STATUS_PAID && $this->due_date->lt(today());
    }

    public function daysLate(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    /** Recalcula el estado según lo pagado. */
    public function refreshStatus(): void
    {
        $pending = $this->pendingTotal();
        $paid = (float) $this->paid_capital + (float) $this->paid_interest + (float) $this->paid_penalty;

        if ($pending <= 0.0) {
            $this->status = self::STATUS_PAID;
            $this->paid_at ??= now();
        } else {
            $this->status = $paid > 0 ? self::STATUS_PARTIAL : self::STATUS_PENDING;
            $this->paid_at = null;
        }
    }
}
