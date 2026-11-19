<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use Concerns\BelongsToCompany;

    use HasFactory, SoftDeletes;

    public const PAYMENT_CASH   = 'CONTADO';
    public const PAYMENT_CREDIT = 'CREDITO';

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RETURNED  = 'returned';

    public const STATUS_LABELS = [
        self::STATUS_COMPLETED => 'Completada',
        self::STATUS_CANCELLED => 'Cancelada',
        self::STATUS_RETURNED  => 'Devuelta',
    ];

    public const STATUS_COLORS = [
        self::STATUS_COMPLETED => 'success',
        self::STATUS_CANCELLED => 'dark',
        self::STATUS_RETURNED  => 'warning',
    ];

    public const RETURN_REASONS = [
        'producto_defectuoso'  => 'Producto defectuoso',
        'error_registro'       => 'Error en el registro',
        'cliente_arrepentido'  => 'Cliente arrepentido',
        'producto_incorrecto'  => 'Producto incorrecto',
        'otro'                 => 'Otro',
    ];

    public const PAYMENT_LABELS = [
        self::PAYMENT_CASH   => 'Contado',
        self::PAYMENT_CREDIT => 'Crédito',
    ];

    public const PAYMENT_COLORS = [
        self::PAYMENT_CASH   => 'success',
        self::PAYMENT_CREDIT => 'primary',
    ];

    public const PAYMENT_ICONS = [
        self::PAYMENT_CASH   => 'bi-cash-coin',
        self::PAYMENT_CREDIT => 'bi-calendar2-week',
    ];

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'product_id',
        'credit_category_id',
        'client_id',
        'created_by',
        'cash_register_id',
        'cash_register_session_id',
        'loan_collateral_id',
        'purchase_id',
        'amount',
        'payment_type',
        'credit_additional_percentage',
        'credit_additional_amount',
        'down_payment',
        'cost',
        'profit',
        'notes',
        'status',
        'sale_date',
        'returned_at',
        'returned_by',
        'return_reason',
        'return_notes',
        'numero',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->numero)) {
                $model->numero = (static::withTrashed()->where('branch_id', $model->branch_id)->max('numero') ?? 0) + 1;
            }
        });
    }

    protected $appends = ['code'];

    protected $casts = [
        'amount' => 'decimal:2',
        'credit_additional_percentage' => 'decimal:2',
        'credit_additional_amount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'cost' => 'decimal:2',
        'profit' => 'decimal:2',
        'sale_date' => 'date',
        'returned_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getCodeAttribute(): string
    {
        return 'VE-' . str_pad((string) ($this->numero ?? 0), 5, '0', STR_PAD_LEFT);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creditCategory(): BelongsTo
    {
        return $this->belongsTo(CreditCategory::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function isReturnable(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isReturned(): bool
    {
        return $this->status === self::STATUS_RETURNED;
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function loanCollateral(): BelongsTo
    {
        return $this->belongsTo(LoanCollateral::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(SaleImage::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(SaleInstallment::class)->orderBy('installment_no');
    }

    public function isCash(): bool
    {
        return $this->payment_type === self::PAYMENT_CASH;
    }

    public function isCredit(): bool
    {
        return $this->payment_type === self::PAYMENT_CREDIT;
    }

    public function paymentLabel(): string
    {
        return self::PAYMENT_LABELS[$this->payment_type] ?? ucfirst((string) $this->payment_type);
    }

    public function paymentColor(): string
    {
        return self::PAYMENT_COLORS[$this->payment_type] ?? 'secondary';
    }

    public function paymentIcon(): string
    {
        return self::PAYMENT_ICONS[$this->payment_type] ?? 'bi-credit-card';
    }
}
