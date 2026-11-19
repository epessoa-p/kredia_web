<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleInstallment extends Model
{
    use HasFactory;

    public const STATUS_PENDIENTE = 'PENDIENTE';
    public const STATUS_PAGADA    = 'PAGADA';
    public const STATUS_VENCIDA   = 'VENCIDA';
    public const STATUS_ANULADA   = 'ANULADA';

    public const STATUS_LABELS = [
        self::STATUS_PENDIENTE => 'Pendiente',
        self::STATUS_PAGADA    => 'Pagada',
        self::STATUS_VENCIDA   => 'Vencida',
        self::STATUS_ANULADA   => 'Anulada',
    ];

    public const STATUS_COLORS = [
        self::STATUS_PENDIENTE => 'secondary',
        self::STATUS_PAGADA    => 'success',
        self::STATUS_VENCIDA   => 'danger',
        self::STATUS_ANULADA   => 'warning',
    ];

    protected $fillable = [
        'sale_id',
        'installment_no',
        'due_date',
        'percentage',
        'amount',
        'principal_amount',
        'interest_amount',
        'status',
        'paid_at',
        'notes',
        'payment_notes',
    ];

    protected $casts = [
        'due_date'         => 'date',
        'percentage'       => 'decimal:4',
        'amount'           => 'decimal:2',
        'principal_amount' => 'decimal:2',
        'interest_amount'  => 'decimal:2',
        'paid_at'          => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }
}
