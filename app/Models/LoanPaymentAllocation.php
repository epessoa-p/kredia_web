<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cuánto de un cobro fue a cada cuota (mora / interés / capital). Permite anular con exactitud. */
class LoanPaymentAllocation extends Model
{
    protected $fillable = ['loan_payment_id', 'loan_installment_id', 'capital', 'interest', 'penalty'];

    protected $casts = [
        'capital' => 'decimal:2',
        'interest' => 'decimal:2',
        'penalty' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(LoanPayment::class, 'loan_payment_id');
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(LoanInstallment::class, 'loan_installment_id');
    }
}
