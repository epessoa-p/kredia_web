<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Branch;
use Carbon\Carbon;

class Loan extends Model
{
    use Concerns\BelongsToCompany;

    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVO';
    public const STATUS_FINISHED = 'FINALIZADO';
    public const STATUS_CANCELLED = 'CANCELADO';
    public const STATUS_OVERDUE = 'EN_MORA';
    public const STATUS_DEFAULTED = 'INCUMPLIDO';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_FINISHED,
        self::STATUS_CANCELLED,
        self::STATUS_OVERDUE,
        self::STATUS_DEFAULTED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_ACTIVE => 'Activo',
        self::STATUS_FINISHED => 'Finalizado',
        self::STATUS_CANCELLED => 'Cancelado',
        self::STATUS_OVERDUE => 'En mora',
        self::STATUS_DEFAULTED => 'Incumplido',
    ];

    public const STATUS_COLORS = [
        self::STATUS_ACTIVE => 'success',
        self::STATUS_FINISHED => 'secondary',
        self::STATUS_CANCELLED => 'dark',
        self::STATUS_OVERDUE => 'danger',
        self::STATUS_DEFAULTED => 'danger',
    ];

    public const DELINQUENT_STATUSES = [
        self::STATUS_OVERDUE,
        self::STATUS_DEFAULTED,
    ];

    public const OPEN_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_OVERDUE,
        self::STATUS_DEFAULTED,
    ];

    public const LEGACY_STATUS_MAP = [
        'active' => self::STATUS_ACTIVE,
        'finished' => self::STATUS_FINISHED,
        'cancelled' => self::STATUS_CANCELLED,
        'overdue' => self::STATUS_OVERDUE,
        'defaulted' => self::STATUS_DEFAULTED,
    ];

    protected $fillable = [
        'company_id',
        'branch_id',
        'product_id',
        'credit_category_id',
        'credit_category_rule_id',
        'client_id',
        'created_by',
        'approved_by',
        'amount',
        'current_capital',
        'interest_rate',
        'term_months',
        'monthly_payment',
        'total_to_pay',
        'total_paid',
        'start_date',
        'end_date',
        'notes',
        'status',
        'penalty_status',
        'penalty_amount',
        'penalty_paid_at',
        'deleted_by',
        'last_interest_generated_at',
        'interest_period',
        'approved_at',
        'cash_register_id',
        'cash_register_session_id',
        'numero',
        // Préstamos en cuotas / cobranza / refinanciación
        'loan_kind',
        'installment_method',
        'payment_frequency',
        'installments_count',
        'installment_amount',
        'skip_sundays',
        'collector_id',
        'parent_loan_id',
        'refinanced_at',
    ];

    public const KIND_PAWN = 'pawn';
    public const KIND_INSTALLMENT = 'installment';

    public function isInstallment(): bool
    {
        return $this->loan_kind === self::KIND_INSTALLMENT;
    }

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
        'current_capital' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'monthly_payment' => 'decimal:2',
        'total_to_pay' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'penalty_paid_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_interest_generated_at' => 'date',
        'approved_at' => 'datetime',
        'installment_amount' => 'decimal:2',
        'installments_count' => 'integer',
        'skip_sundays' => 'boolean',
        'refinanced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getCodeAttribute(): string
    {
        return 'PR-' . str_pad((string) ($this->numero ?? 0), 5, '0', STR_PAD_LEFT);
    }

    public static function normalizeStatus(?string $status): ?string
    {
        if ($status === null) {
            return null;
        }

        $status = trim($status);
        if ($status === '') {
            return null;
        }

        if (in_array($status, self::STATUSES, true)) {
            return $status;
        }

        $legacyKey = strtolower($status);

        return self::LEGACY_STATUS_MAP[$legacyKey] ?? null;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creditCategory(): BelongsTo
    {
        return $this->belongsTo(CreditCategory::class);
    }

    public function creditCategoryRule(): BelongsTo
    {
        return $this->belongsTo(CreditCategoryRule::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function cashRegisterSession(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(LoanPayment::class);
    }

    public function contract(): HasOne
    {
        return $this->hasOne(LoanContract::class);
    }

    public function collateral(): HasMany
    {
        return $this->hasMany(LoanCollateral::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(LoanImage::class);
    }

    public function interestPayments(): HasMany
    {
        return $this->hasMany(LoanInterestPayment::class);
    }

    public function amortizations(): HasMany
    {
        return $this->hasMany(LoanAmortization::class);
    }

    /** Plan de pagos (solo préstamos en cuotas). */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class)->orderBy('number');
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class);
    }

    /** Cobrador asignado (personal). */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'collector_id');
    }

    /** Préstamo que este refinanció. */
    public function parentLoan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'parent_loan_id')->withTrashed();
    }

    /** Préstamo nuevo que refinanció a este. */
    public function refinancedBy(): HasOne
    {
        return $this->hasOne(Loan::class, 'parent_loan_id');
    }

    /**
     * Capital vigente (se reduce con amortizaciones).
     */
    public function getCurrentCapital(): float
    {
        return max(0, (float) ($this->current_capital ?? $this->amount));
    }

    /**
     * Calcula el interés simple del periodo actual sobre el capital vigente.
     * Ejemplo: capital 300, tasa 10% → interés = 30
     */
    public function calculatePeriodInterest(?float $capital = null): float
    {
        $base = $capital ?? $this->getCurrentCapital();
        $rate = (float) $this->interest_rate;
        return round($base * ($rate / 100), 2);
    }

    /**
     * Genera periodos de interés pendientes según la fecha de creación y periodo.
     */
    public function generatePendingInterestPeriods(): void
    {
        // Los préstamos en cuotas no generan periodos de interés (tienen plan de pagos fijo).
        if ($this->isInstallment()) {
            return;
        }

        $periodMonths = $this->getPeriodInMonths();
        $startDate = $this->start_date ?? $this->created_at->toImmutable();

        // El primer periodo empieza desde la fecha de creación
        $lastGenerated = $this->last_interest_generated_at;
        $existingPeriods = $this->countedInterestPeriods()->max('period_number') ?? 0;

        $now = now();
        $nextPeriodNumber = $existingPeriods + 1;

        // Calcular la fecha del siguiente periodo a generar
        if ($existingPeriods === 0) {
            // Primer periodo: se genera inmediatamente al crear el préstamo
            $periodStart = $startDate->copy();
            $periodEnd = $startDate->copy()->addMonths($periodMonths)->subDay();
        } else {
            $periodStart = $startDate->copy()->addMonths($existingPeriods * $periodMonths);
            $periodEnd = $periodStart->copy()->addMonths($periodMonths)->subDay();
        }

        // Plazo tope en número de periodos: no generar más allá de esta
        // cantidad de periodos vencidos e impagos (a partir de ahí entra la multa).
        $plazoTope = max(1, (int) round(((int) $this->term_months) / $periodMonths));

        // Regla "interés del día siguiente al aniversario": cuando está activa,
        // el primer periodo se genera el mismo día de creación, pero los periodos
        // siguientes recién se generan al DÍA SIGUIENTE de su fecha de inicio. Así,
        // en la fecha exacta del aniversario el cliente aún debe un solo interés.
        // Las fronteras de los periodos NO cambian; solo se retrasa el disparo.
        $chargeNextDay = (bool) ($this->company?->interest_next_day ?? false);

        // Generar periodos que ya debieron haberse creado
        while (true) {
            $dueDate = ($chargeNextDay && $nextPeriodNumber > 1)
                ? $periodStart->copy()->addDay()
                : $periodStart->copy();
            if ($dueDate->gt($now)) {
                break;
            }

            $overdueUnpaid = $this->interestPayments()
                ->where('status', '!=', 'paid')
                ->whereDate('period_end', '<', now()->toDateString())
                ->count();
            if ($overdueUnpaid >= $plazoTope) {
                break; // alcanzó el plazo tope → la multa se encarga, no se generan más
            }

            // Verificar que no exista ya este periodo
            $exists = $this->countedInterestPeriods()->where('period_number', $nextPeriodNumber)->exists();
            if (!$exists) {
                $capital = $this->getCurrentCapital();
                $interestAmount = $this->calculatePeriodInterest($capital);

                $this->interestPayments()->create([
                    'period_number' => $nextPeriodNumber,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'base_amount' => $capital,
                    'interest_rate' => $this->interest_rate,
                    'interest_amount' => $interestAmount,
                    'status' => 'pending',
                ]);

                $this->update(['last_interest_generated_at' => $periodStart->toDateString()]);
            }

            $nextPeriodNumber++;
            $periodStart = $periodStart->addMonths($periodMonths);
            $periodEnd = $periodStart->copy()->addMonths($periodMonths)->subDay();
        }
    }

    /**
     * Periodos que cuentan para la numeración y para "ya existe": los activos y los
     * eliminados a mano (o sin motivo), que NO se vuelven a crear. Los eliminados por
     * el sistema al editar el préstamo se ignoran, para que se regeneren cuando toque.
     */
    private function countedInterestPeriods()
    {
        return $this->interestPayments()->withTrashed()->where(function ($q) {
            $q->whereNull('deleted_at')
                ->orWhereNull('deletion_reason')
                ->orWhere('deletion_reason', '!=', LoanInterestPayment::REASON_LOAN_EDITED);
        });
    }

    /**
     * Recalcula los intereses NO pagados con las condiciones actuales del préstamo
     * (monto, tasa, plazo, fecha de inicio) conservando los mismos registros.
     * Se usa al editar el préstamo, en lugar de borrar y regenerar.
     *
     * - Fechas del periodo: misma fórmula que la generación (desde start_date).
     * - Si con la nueva fecha el periodo todavía no corresponde, se elimina con
     *   motivo "Al editar el préstamo" y se regenerará cuando llegue su fecha.
     * - Al final se generan los periodos que falten.
     */
    public function recalculatePendingInterestPeriods(): void
    {
        $periodMonths = $this->getPeriodInMonths();
        $startDate = Carbon::parse($this->start_date ?? $this->created_at)->startOfDay();
        $chargeNextDay = (bool) ($this->company?->interest_next_day ?? false);
        $capital = $this->getCurrentCapital();
        $interestAmount = $this->calculatePeriodInterest($capital);
        $now = now();

        $pending = $this->interestPayments()
            ->where('status', '!=', 'paid')
            ->orderBy('period_number')
            ->get();

        foreach ($pending as $interest) {
            $n = max(1, (int) $interest->period_number);
            $periodStart = $startDate->copy()->addMonths(($n - 1) * $periodMonths);
            $dueDate = ($chargeNextDay && $n > 1) ? $periodStart->copy()->addDay() : $periodStart->copy();

            if ($dueDate->gt($now)) {
                $interest->deleteWithAudit(LoanInterestPayment::REASON_LOAN_EDITED);
                continue;
            }

            $interest->update([
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodStart->copy()->addMonths($periodMonths)->subDay()->toDateString(),
                'base_amount' => $capital,
                'interest_rate' => $this->interest_rate,
                'interest_amount' => $interestAmount,
            ]);
        }

        $this->generatePendingInterestPeriods();
    }

    /**
     * Obtiene los meses por periodo según interest_period.
     */
    public function getPeriodInMonths(): int
    {
        return match ($this->interest_period) {
            'quarterly' => 3,
            'semiannual' => 6,
            'annual' => 12,
            default => 1, // monthly
        };
    }

    /**
     * Calcula el total de intereses no pagados.
     */
    public function getUnpaidInterestTotal(): float
    {
        return (float) $this->interestPayments()
            ->where('status', '!=', 'paid')
            ->selectRaw('SUM(interest_amount - paid_amount) as total')
            ->value('total') ?? 0;
    }

    /**
     * Calcula el monto para liquidar: capital vigente + intereses no pagados + multa pendiente.
     */
    public function getLiquidationAmount(): float
    {
        return round($this->getCurrentCapital() + $this->getUnpaidInterestTotal() + $this->getPendingPenalty(), 2);
    }

    /* ── Multa: reloj de gracia y pago ─────────────────────────── */

    /** Periodos vencidos e impagos, ordenados por vencimiento. */
    protected function overdueUnpaidPeriods()
    {
        return $this->interestPayments()
            ->where('status', '!=', 'paid')
            ->whereDate('period_end', '<', now()->toDateString())
            ->orderBy('period_end')
            ->get();
    }

    /** Cantidad de periodos vencidos impagos que dispara la multa. */
    public function getPlazoTope(): int
    {
        return max(1, (int) round(((int) $this->term_months) / $this->getPeriodInMonths()));
    }

    /**
     * Fecha REAL en que se alcanzó el plazo tope = fin del periodo #tope.
     * Se deriva de los periodos (no de cuándo corrió el chequeo), así el
     * resultado es determinista y no depende de que alguien abra la pantalla.
     */
    public function getPenaltyReachedAt(): ?Carbon
    {
        $periods = $this->overdueUnpaidPeriods();
        $tope = $this->getPlazoTope();

        if ($periods->count() < $tope) {
            return null;
        }

        return Carbon::parse($periods[$tope - 1]->period_end)->endOfDay();
    }

    /** Cuándo vence la gracia: a partir de ahí → INCUMPLIDO + prenda a la venta. */
    public function getGraceEndsAt(): ?Carbon
    {
        $reached = $this->getPenaltyReachedAt();
        if (!$reached) {
            return null;
        }

        return $reached->copy()->addDays((int) ($this->creditCategory?->penalty_grace_days ?? 0));
    }

    public function isPenaltyPaid(): bool
    {
        return $this->penalty_paid_at !== null;
    }

    /** Multa aún por cobrar (0 si ya se pagó). */
    public function getPendingPenalty(): float
    {
        return $this->isPenaltyPaid() ? 0.0 : (float) $this->penalty_amount;
    }

    /**
     * Verifica estado de mora según la CANTIDAD de periodos de interés
     * vencidos y NO pagados respecto al plazo tope.
     *
     * Flujo:
     *  - overdueUnpaid < plazoTope → ACTIVO, sin multa (se siguen generando periodos)
     *  - overdueUnpaid >= plazoTope → se genera la multa, EN_MORA, colateral stored
     *  - tras penalty_grace_days desde que se ALCANZÓ el tope → INCUMPLIDO, colateral sellable
     *
     * El reloj de gracia se cuenta desde getPenaltyReachedAt() (el period_end del
     * periodo que alcanzó el tope), NO desde cuándo corrió este chequeo. Así el
     * resultado no depende de que alguien abra la pantalla.
     */
    public function checkOverdueStatus(): void
    {
        if (!in_array($this->status, self::OPEN_STATUSES, true)) {
            return;
        }

        // Cuotas: mora por cuota y estado los calcula InstallmentLoanService.
        if ($this->isInstallment()) {
            app(\App\Services\Installments\InstallmentLoanService::class)->refreshOverdue($this);

            return;
        }

        $category = $this->creditCategory;
        $periods = $this->overdueUnpaidPeriods();
        $plazoTope = $this->getPlazoTope();

        if ($periods->count() < $plazoTope) {
            // Auto-limpiado: si estaba "En mora" pero el conteo ya no alcanza el
            // plazo tope (pagó o aún no vencen), revertir a ACTIVO/normal.
            // No se toca 'defaulted' (multa aplicada) — eso se maneja manualmente.
            if ($this->penalty_status === 'overdue') {
                $this->update([
                    'status' => self::STATUS_ACTIVE,
                    'penalty_status' => 'normal',
                    'penalty_amount' => 0,
                    'penalty_paid_at' => null,
                ]);
                $this->collateral()
                    ->where('status', 'stored')
                    ->update(['status' => 'retained', 'stored_at' => null]);
            }
            return; // aún no alcanza el plazo tope
        }

        $collateral = $this->collateral()
            ->whereIn('status', ['retained', 'stored', 'sellable'])
            ->first();

        // Etapa 1: generar multa + EN_MORA + colateral stored (una sola vez)
        if ($this->penalty_status === 'normal') {
            $penaltyAmount = (float) ($category?->penalty_fixed_amount ?? 0);
            if ($penaltyAmount <= 0) {
                $base = $this->getCurrentCapital() + $this->getUnpaidInterestTotal();
                $penaltyAmount = round($base * ((float) ($category?->penalty_rate ?? 0) / 100), 2);
            }

            $this->update([
                'status' => self::STATUS_OVERDUE,
                'penalty_status' => 'overdue',
                'penalty_amount' => $penaltyAmount,
                'penalty_paid_at' => null, // multa nueva = pendiente de pago
            ]);

            // stored_at es informativo (cuándo se guardó físicamente la prenda);
            // el reloj de gracia NO depende de él.
            if ($collateral && in_array($collateral->status, ['retained', 'stored'], true)) {
                $collateral->update([
                    'status' => 'stored',
                    'stored_at' => $collateral->stored_at ?? now(),
                ]);
            }
            $collateral?->refresh();
        }

        // Etapa 2: vencida la gracia → INCUMPLIDO + colateral sellable.
        // Puede encadenarse con la Etapa 1 en la misma pasada si la gracia ya venció.
        $graceEnds = $this->getGraceEndsAt();
        if ($this->penalty_status === 'overdue' && $graceEnds && now()->gt($graceEnds)) {
            $this->update([
                'status' => self::STATUS_DEFAULTED,
                'penalty_status' => 'defaulted',
            ]);
            if ($collateral && in_array($collateral->status, ['retained', 'stored'], true)) {
                $collateral->update(['status' => 'sellable', 'sellable_at' => now()]);
            }
        }
    }
    public function calculateMonthlyPayment(): float
    {
        $monthlyRate = ($this->getMonthlyInterestRate() / 100);
        $n = $this->term_months;
        $principal = $this->amount;

        if ($monthlyRate == 0) {
            return $principal / $n;
        }

        $payment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $n)) / (pow(1 + $monthlyRate, $n) - 1);
        return round($payment, 2);
    }

    public function getMonthlyInterestRate(): float
    {
        return match ($this->interest_period) {
            'quarterly' => ((float) $this->interest_rate) / 3,
            'semiannual' => ((float) $this->interest_rate) / 6,
            'annual' => ((float) $this->interest_rate) / 12,
            default => (float) $this->interest_rate,
        };
    }

    /**
     * Calcular total a pagar
     */
    public function calculateTotalToPay(): float
    {
        return $this->calculateMonthlyPayment() * $this->term_months;
    }

    /**
     * Pagos pendientes
     */
    public function getPendingAmount(): float
    {
        $total = $this->calculateTotalToPay();
        return $total - $this->total_paid;
    }

    /**
     * Número de pagos realizados
     */
    public function getPaymentCount(): int
    {
        return $this->payments()->count();
    }

    /**
     * Próximo pago vencido
     */
    public function getDaysOverdue(): int
    {
        if (!$this->start_date || $this->status !== self::STATUS_ACTIVE) {
            return 0;
        }

        $paymentsMade = $this->getPaymentCount();
        $nextPaymentDate = now()->copy()
            ->setDay(1)
            ->addMonths($paymentsMade);

        if ($nextPaymentDate->isPast()) {
            return $nextPaymentDate->diffInDays(now());
        }

        return 0;
    }

    public function getCapitalPaid(): float
    {
        return (float) $this->payments()->sum('capital');
    }

    public function getOutstandingPrincipal(): float
    {
        return max(0, (float) $this->amount - $this->getCapitalPaid());
    }

    public function calculateMonthlyPaymentForPrincipal(float $principal, int $months): float
    {
        $monthlyRate = ($this->getMonthlyInterestRate() / 100);

        if ($months <= 0) {
            return 0;
        }

        if ($monthlyRate == 0) {
            return round($principal / $months, 2);
        }

        $payment = $principal * ($monthlyRate * pow(1 + $monthlyRate, $months)) / (pow(1 + $monthlyRate, $months) - 1);

        return round($payment, 2);
    }
}
