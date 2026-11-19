<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoanPayment extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_LABELS = [
        'interest' => 'Interés',
        'penalty' => 'Multa',
        'capital' => 'Amortización',
        'mixed' => 'Liquidación',
    ];

    public const METHOD_LABELS = [
        'efectivo' => 'Efectivo',
        'cash' => 'Efectivo',
        'transferencia' => 'Transferencia',
        'transfer' => 'Transferencia',
        'tarjeta' => 'Tarjeta',
        'card' => 'Tarjeta',
        'pago_qr' => 'Pago QR',
        'qr' => 'Pago QR',
        'deposito' => 'Depósito',
    ];

    public static function typeLabel(?string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucfirst((string) $type);
    }

    public static function methodLabel(?string $method): string
    {
        return self::METHOD_LABELS[strtolower((string) $method)] ?? ucfirst(str_replace('_', ' ', (string) $method));
    }

    protected $fillable = [
        'loan_id',
        'cash_register_id',
        'cash_register_session_id',
        'registered_by',
        'amount',
        'payment_type',
        'capital',
        'interest',
        'payment_date',
        'payment_method',
        'reference',
        'receipt_path',
        'notes',
        'penalty',
        'latitude',
        'longitude',
        'request_id',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'capital' => 'decimal:2',
        'interest' => 'decimal:2',
        'penalty' => 'decimal:2',
        'voided_at' => 'datetime',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'cash_register_session_id');
    }

    /** Reparto del cobro entre cuotas (préstamos en cuotas). */
    public function allocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LoanPaymentAllocation::class);
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
