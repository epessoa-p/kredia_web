<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanInterestPayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'loan_id',
        'period_number',
        'period_start',
        'period_end',
        'base_amount',
        'interest_rate',
        'interest_amount',
        'paid_amount',
        'status',
        'paid_at',
        'payment_method',
        'reference',
        'receipt_path',
        'notes',
        'registered_by',
        'deleted_by',
        'deletion_reason',
        'restored_by',
        'restored_at',
    ];

    /**
     * Eliminado por el sistema al editar el préstamo (recálculo). La generación
     * de periodos lo ignora, así ese periodo se vuelve a generar cuando corresponda.
     */
    public const REASON_LOAN_EDITED = 'loan_edited';

    /** Motivos de eliminación de un periodo de interés. */
    public const DELETION_REASONS = [
        'manual'       => 'Eliminado manualmente',
        'loan_edited'  => 'Al editar el préstamo',
        'loan_deleted' => 'Al eliminar el préstamo',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'interest_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'deleted_at' => 'datetime',
        'restored_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }

    public function getDeletionReasonLabelAttribute(): ?string
    {
        return $this->deletion_reason ? (self::DELETION_REASONS[$this->deletion_reason] ?? $this->deletion_reason) : null;
    }

    /**
     * Soft delete registrando quién y por qué.
     */
    public function deleteWithAudit(string $reason, ?int $userId = null): void
    {
        $this->forceFill([
            'deleted_by' => $userId ?? auth()->id(),
            'deletion_reason' => $reason,
        ])->save();
        $this->delete();
    }

    public function getRemainingAmount(): float
    {
        return max(0, round((float) $this->interest_amount - (float) $this->paid_amount, 2));
    }
}
