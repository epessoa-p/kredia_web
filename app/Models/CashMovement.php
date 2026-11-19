<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashMovement extends Model
{
    use Concerns\BelongsToCompany;

    use HasFactory;

    /**
     * Categorías que mueven efectivo pero NO son ganancia ni pérdida del negocio.
     * Un "Ingreso a caja" es un aporte del negocio para tener fondos con que
     * desembolsar (equivale a ampliar la apertura), así que no debe sumar a la
     * utilidad operativa — pero sí cuenta para el saldo y el cierre de la caja.
     */
    public const NON_OPERATIONAL_CATEGORIES = ['cash_deposit'];

    protected $fillable = [
        'company_id',
        'cash_register_id',
        'cash_register_session_id',
        'user_id',
        'type',
        'category',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'movement_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'movement_date' => 'datetime',
    ];

    /* ── Relations ─────────────────────────── */

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /* ── Helpers ───────────────────────────── */

    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    public function isExpense(): bool
    {
        return $this->type === 'expense';
    }

    /**
     * Referencia legible del movimiento: el código correlativo del documento
     * (PR-/VE-/CO-) o el nombre del personal, en lugar del ID interno.
     * Devuelve null cuando la referencia es la propia sesión de caja (no aporta).
     */
    public function getReferenceLabelAttribute(): ?string
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        $ref = $this->reference;
        // Préstamo eliminado (reversión): la relación no trae registros borrados.
        if (!$ref && $this->reference_type === Loan::class) {
            $ref = Loan::withTrashed()->find($this->reference_id);
        }
        $code = fn (string $prefix) => $ref && $ref->numero !== null
            ? $prefix . str_pad((string) $ref->numero, 5, '0', STR_PAD_LEFT)
            : '#' . $this->reference_id;

        return match ($this->reference_type) {
            Loan::class => $code('PR-'),
            Sale::class => $code('VE-'),
            Purchase::class => $code('CO-'),
            Personal::class => $ref?->full_name ?? ('Personal #' . $this->reference_id),
            CashRegisterSession::class => null,
            default => '#' . $this->reference_id,
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) {
            'loan_disbursement' => 'Desembolso préstamo',
            'loan_payment' => 'Pago préstamo',
            'interest_payment' => 'Pago de interés',
            'penalty_payment' => 'Pago de multa',
            'amortization' => 'Amortización',
            'liquidation' => 'Liquidación',
            'purchase' => 'Compra',
            'sale' => 'Venta',
            'sale_installment' => 'Cuota de venta',
            'sale_down_payment' => 'Cuota inicial de venta',
            'sale_liquidation'  => 'Liquidación de venta',
            'sale_return'       => 'Devolución de venta',
            'adjustment'        => 'Ajuste de caja',
            'cash_deposit'      => 'Ingreso a caja',
            'service_expense'   => 'Gasto de servicio',
            'staff_payment'     => 'Pago a personal',
            'operating_expense' => 'Otro gasto operativo',
            'loan_deletion'     => 'Reversión préstamo eliminado',
            'purchase_deletion' => 'Reversión compra eliminada',
            default => ucfirst(str_replace('_', ' ', $this->category)),
        };
    }
}
