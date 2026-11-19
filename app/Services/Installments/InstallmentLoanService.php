<?php

namespace App\Services\Installments;

use App\Models\ActivityLog;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanInstallment;
use App\Models\LoanPayment;
use App\Models\LoanPaymentAllocation;
use App\Services\LoanActionException;
use App\Services\LoanPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Préstamos en CUOTAS: alta (con plan de pagos y desembolso), cobro, mora, anulación y refinanciación.
 * Una sola fuente de verdad para la web y la API móvil. Cada operación corre en una transacción
 * y bloquea el préstamo (lockForUpdate). Los errores de negocio se lanzan como LoanActionException.
 *
 * Orden de aplicación de un cobro: de la cuota más antigua a la más nueva y, dentro de cada
 * cuota, primero la MORA, luego el INTERÉS y al final el CAPITAL.
 */
class InstallmentLoanService
{
    public function __construct(private LoanPaymentService $payments)
    {
    }

    /* ── Alta ──────────────────────────────────────────────────── */

    /**
     * @param array $data client_id, branch_id, credit_category_id?, credit_category_rule_id?, amount, interest_rate,
     *                    installment_method, payment_frequency, installments_count, start_date?, skip_sundays?,
     *                    collector_id?, notes?, guarantors[] (name, id_number?, phone?, address?, relationship?, client_id?)
     */
    public function create(int $companyId, array $data, int $userId, ?Loan $parent = null, float $refinancedBalance = 0): Loan
    {
        return DB::transaction(function () use ($companyId, $data, $userId, $parent, $refinancedBalance) {
            $session = $this->openSession($userId, $companyId);

            $client = Client::where('company_id', $companyId)->find($data['client_id'] ?? null);
            if (!$client) {
                throw new LoanActionException('El cliente no existe en esta empresa.');
            }

            $amount = round((float) $data['amount'], 2);
            $rate = (float) $data['interest_rate'];
            $maxRate = $client->company?->max_interest_rate;
            if ($maxRate !== null && $rate > (float) $maxRate) {
                throw new LoanActionException("La tasa supera el máximo permitido por la empresa ({$maxRate}% mensual).");
            }

            $startDate = Carbon::parse($data['start_date'] ?? today());
            $skipSundays = (bool) ($data['skip_sundays'] ?? false);
            $schedule = ScheduleCalculator::build(
                $amount, $rate, $data['installment_method'], $data['payment_frequency'],
                (int) $data['installments_count'], $startDate, $skipSundays,
            );

            $totalToPay = round(array_sum(array_column($schedule, 'amount')), 2);

            $loan = Loan::create([
                'company_id' => $companyId,
                'branch_id' => $data['branch_id'],
                'client_id' => $client->id,
                'credit_category_id' => $data['credit_category_id'] ?? null,
                'credit_category_rule_id' => $data['credit_category_rule_id'] ?? null,
                'loan_kind' => Loan::KIND_INSTALLMENT,
                'installment_method' => $data['installment_method'],
                'payment_frequency' => $data['payment_frequency'],
                'installments_count' => count($schedule),
                'installment_amount' => $schedule[0]['amount'],
                'skip_sundays' => $skipSundays,
                'collector_id' => $data['collector_id'] ?? null,
                'parent_loan_id' => $parent?->id,
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now(),
                'amount' => $amount,
                'current_capital' => $amount,
                'interest_rate' => $rate,
                'term_months' => max(1, (int) ceil(count($schedule) * $this->periodDays($data['payment_frequency']) / 30)),
                'monthly_payment' => $schedule[0]['amount'],
                'total_to_pay' => $totalToPay,
                'total_paid' => 0,
                'start_date' => $startDate->toDateString(),
                'end_date' => end($schedule)['due_date'],
                'notes' => $data['notes'] ?? null,
                'status' => Loan::STATUS_ACTIVE,
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' => $session->id,
            ]);

            foreach ($schedule as $row) {
                $loan->installments()->create([
                    'number' => $row['number'],
                    'due_date' => $row['due_date'],
                    'capital' => $row['capital'],
                    'interest' => $row['interest'],
                    'amount' => $row['amount'],
                ]);
            }

            foreach ($data['guarantors'] ?? [] as $g) {
                if (trim((string) ($g['name'] ?? '')) === '') {
                    continue;
                }
                LoanGuarantor::create([
                    'company_id' => $companyId,
                    'loan_id' => $loan->id,
                    'client_id' => $g['client_id'] ?? null,
                    'name' => $g['name'],
                    'id_number' => $g['id_number'] ?? null,
                    'phone' => $g['phone'] ?? null,
                    'address' => $g['address'] ?? null,
                    'relationship' => $g['relationship'] ?? null,
                    'notes' => $g['notes'] ?? null,
                ]);
            }

            // Desembolso: sale de caja solo el dinero nuevo (en una refinanciación, el saldo anterior no se entrega).
            $cashOut = round($amount - $refinancedBalance, 2);
            if ($cashOut > 0) {
                $this->cashMovement($loan, $session, $userId, 'expense', 'loan_disbursement', $cashOut,
                    'Desembolso préstamo en cuotas ' . $loan->code);
            }

            ActivityLog::record($loan, 'created', 'Préstamo en cuotas ' . $loan->code, [
                'amount' => $amount, 'rate' => $rate, 'method' => $data['installment_method'],
                'frequency' => $data['payment_frequency'], 'installments' => count($schedule),
            ]);

            return $loan->fresh(['installments', 'guarantors']);
        });
    }

    /* ── Cobro ─────────────────────────────────────────────────── */

    /**
     * Registra un cobro y lo reparte entre las cuotas pendientes.
     * Idempotente por request_id: si ya existe un pago con ese id en el préstamo, devuelve ese pago.
     *
     * @param array $data amount, payment_method?, reference?, notes?, latitude?, longitude?, request_id?, payment_date?
     */
    public function pay(Loan $loan, array $data, int $userId): LoanPayment
    {
        return DB::transaction(function () use ($loan, $data, $userId) {
            $loan = Loan::withTrashed()->lockForUpdate()->findOrFail($loan->id);
            $this->assertInstallment($loan);

            $requestId = trim((string) ($data['request_id'] ?? '')) ?: null;
            if ($requestId && ($existing = $loan->payments()->where('request_id', $requestId)->first())) {
                $existing->setAttribute('was_duplicate', true);

                return $existing;
            }

            if ($reason = $this->payments->getActionsLockedReason($loan)) {
                throw new LoanActionException($reason);
            }
            $session = $this->openSession($userId, (int) $loan->company_id);

            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw new LoanActionException('El monto debe ser mayor a 0.');
            }

            $installments = $loan->installments()->where('status', '!=', LoanInstallment::STATUS_PAID)
                ->orderBy('number')->lockForUpdate()->get();
            $outstanding = round($installments->sum(fn (LoanInstallment $i) => $i->pendingTotal()), 2);
            if ($amount > $outstanding) {
                throw new LoanActionException('El monto supera la deuda pendiente (' . number_format($outstanding, 2) . ').');
            }

            $payment = $loan->payments()->create([
                'registered_by' => $userId,
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' => $session->id,
                'amount' => $amount,
                'payment_type' => 'installment',
                'capital' => 0,
                'interest' => 0,
                'penalty' => 0,
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_method' => strtolower(trim((string) ($data['payment_method'] ?? ''))) ?: 'efectivo',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'request_id' => $requestId,
            ]);

            [$capital, $interest, $penalty] = $this->allocate($payment, $installments, $amount);
            $payment->update(['capital' => $capital, 'interest' => $interest, 'penalty' => $penalty]);

            $this->syncLoanTotals($loan);

            $this->cashMovement($loan, $session, $userId, 'income', 'loan_payment', $amount, 'Cobro cuotas ' . $loan->code);

            return $payment->fresh('allocations.installment');
        });
    }

    /**
     * Reparte $amount entre las cuotas (mora → interés → capital, de la más antigua a la más nueva).
     *
     * @return array{0: float, 1: float, 2: float} capital, interés y mora aplicados
     */
    private function allocate(LoanPayment $payment, $installments, float $amount): array
    {
        $remaining = $amount;
        $totals = ['capital' => 0.0, 'interest' => 0.0, 'penalty' => 0.0];

        foreach ($installments as $installment) {
            if ($remaining <= 0) {
                break;
            }

            $applied = ['penalty' => 0.0, 'interest' => 0.0, 'capital' => 0.0];
            foreach (['penalty' => 'pendingPenalty', 'interest' => 'pendingInterest', 'capital' => 'pendingCapital'] as $part => $pendingFn) {
                $take = round(min($remaining, $installment->{$pendingFn}()), 2);
                if ($take <= 0) {
                    continue;
                }
                $installment->{"paid_{$part}"} = round((float) $installment->{"paid_{$part}"} + $take, 2);
                $applied[$part] = $take;
                $remaining = round($remaining - $take, 2);
            }

            if (array_sum($applied) > 0) {
                $installment->refreshStatus();
                $installment->save();
                LoanPaymentAllocation::create([
                    'loan_payment_id' => $payment->id,
                    'loan_installment_id' => $installment->id,
                    'capital' => $applied['capital'],
                    'interest' => $applied['interest'],
                    'penalty' => $applied['penalty'],
                ]);
                foreach ($applied as $part => $value) {
                    $totals[$part] += $value;
                }
            }
        }

        return [round($totals['capital'], 2), round($totals['interest'], 2), round($totals['penalty'], 2)];
    }

    /* ── Anulación ─────────────────────────────────────────────── */

    /**
     * Anula un cobro de cuotas: revierte lo aplicado a cada cuota, registra la salida de caja
     * en la caja abierta del usuario y deja constancia (quién, cuándo y por qué).
     */
    public function void(LoanPayment $payment, string $reason, int $userId): LoanPayment
    {
        return DB::transaction(function () use ($payment, $reason, $userId) {
            $payment = LoanPayment::lockForUpdate()->findOrFail($payment->id);
            $loan = Loan::withTrashed()->lockForUpdate()->findOrFail($payment->loan_id);
            $this->assertInstallment($loan);

            if ($payment->isVoided()) {
                throw new LoanActionException('Este pago ya fue anulado.');
            }
            if ($payment->payment_type !== 'installment') {
                throw new LoanActionException('Solo se pueden anular cobros de cuotas.');
            }
            if (trim($reason) === '') {
                throw new LoanActionException('Indica el motivo de la anulación.');
            }
            if ($loan->refinanced_at) {
                throw new LoanActionException('El préstamo fue refinanciado: no se pueden anular sus pagos.');
            }

            $session = $this->openSession($userId, (int) $loan->company_id);

            foreach ($payment->allocations()->with('installment')->get() as $allocation) {
                $installment = LoanInstallment::lockForUpdate()->find($allocation->loan_installment_id);
                foreach (['capital', 'interest', 'penalty'] as $part) {
                    $installment->{"paid_{$part}"} = round(max(0, (float) $installment->{"paid_{$part}"} - (float) $allocation->{$part}), 2);
                }
                $installment->refreshStatus();
                $installment->save();
            }

            $payment->update([
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => mb_substr(trim($reason), 0, 500),
            ]);

            // Si el préstamo había quedado finalizado por este pago, vuelve a estar vigente.
            if ($loan->status === Loan::STATUS_FINISHED) {
                $loan->status = Loan::STATUS_ACTIVE;
            }
            $this->syncLoanTotals($loan);
            $this->refreshOverdue($loan);

            $this->cashMovement($loan, $session, $userId, 'expense', 'loan_payment_void', (float) $payment->amount,
                'Anulación de cobro ' . $loan->code . ': ' . mb_substr(trim($reason), 0, 200));

            ActivityLog::record($payment, 'voided', 'Cobro anulado de ' . $loan->code, ['reason' => $reason, 'amount' => (float) $payment->amount]);

            return $payment->fresh();
        });
    }

    /* ── Refinanciación ────────────────────────────────────────── */

    /**
     * Cierra el préstamo actual y crea uno nuevo por (saldo de capital pendiente + dinero nuevo).
     * Del saldo no sale dinero de caja: solo se desembolsa el dinero nuevo.
     * Los intereses y la mora pendientes del préstamo anterior se condonan o deben cobrarse antes.
     *
     * @param array $data extra_amount, interest_rate, installment_method, payment_frequency, installments_count, start_date?, notes?
     */
    public function refinance(Loan $loan, array $data, int $userId): Loan
    {
        return DB::transaction(function () use ($loan, $data, $userId) {
            $loan = Loan::withTrashed()->lockForUpdate()->findOrFail($loan->id);
            $this->assertInstallment($loan);

            if ($reason = $this->payments->getActionsLockedReason($loan)) {
                throw new LoanActionException($reason);
            }

            $balance = round($loan->installments()->get()->sum(fn (LoanInstallment $i) => $i->pendingCapital()), 2);
            if ($balance <= 0) {
                throw new LoanActionException('El préstamo no tiene saldo de capital para refinanciar.');
            }

            $extra = round(max(0, (float) ($data['extra_amount'] ?? 0)), 2);

            $new = $this->create((int) $loan->company_id, [
                'client_id' => $loan->client_id,
                'branch_id' => $loan->branch_id,
                'credit_category_id' => $loan->credit_category_id,
                'credit_category_rule_id' => $loan->credit_category_rule_id,
                'collector_id' => $data['collector_id'] ?? $loan->collector_id,
                'amount' => $balance + $extra,
                'interest_rate' => $data['interest_rate'] ?? $loan->interest_rate,
                'installment_method' => $data['installment_method'] ?? $loan->installment_method,
                'payment_frequency' => $data['payment_frequency'] ?? $loan->payment_frequency,
                'installments_count' => $data['installments_count'] ?? $loan->installments_count,
                'skip_sundays' => $data['skip_sundays'] ?? $loan->skip_sundays,
                'start_date' => $data['start_date'] ?? today()->toDateString(),
                'notes' => trim('Refinanciación de ' . $loan->code . '. ' . ($data['notes'] ?? '')),
                'guarantors' => $loan->guarantors()->get()->map->only(['client_id', 'name', 'id_number', 'phone', 'address', 'relationship'])->all(),
            ], $userId, $loan, $balance);

            // El préstamo anterior queda cerrado: su capital pendiente pasó al nuevo.
            foreach ($loan->installments()->where('status', '!=', LoanInstallment::STATUS_PAID)->get() as $installment) {
                $installment->paid_capital = $installment->capital;
                $installment->refreshStatus();
                $installment->save();
            }

            $session = $this->openSession($userId, (int) $loan->company_id);
            $loan->payments()->create([
                'registered_by' => $userId,
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' => $session->id,
                'amount' => $balance,
                'payment_type' => 'refinance',
                'capital' => $balance,
                'interest' => 0,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'refinanciacion',
                'notes' => 'Saldo trasladado a ' . $new->code . ' (no ingresa a caja).',
            ]);

            $loan->forceFill([
                'status' => Loan::STATUS_FINISHED,
                'current_capital' => 0,
                'refinanced_at' => now(),
            ])->save();

            ActivityLog::record($loan, 'refinanced', $loan->code . ' refinanciado en ' . $new->code, [
                'balance' => $balance, 'extra' => $extra, 'new_loan_id' => $new->id,
            ]);

            return $new;
        });
    }

    /* ── Mora y estado ─────────────────────────────────────────── */

    /**
     * Aplica la mora a las cuotas vencidas (una vez por cuota, pasada la gracia de la categoría)
     * y actualiza el estado: EN_MORA si hay cuotas vencidas sin pagar, ACTIVO si está al día,
     * FINALIZADO si se pagó todo.
     */
    public function refreshOverdue(Loan $loan): void
    {
        if (!$loan->isInstallment() || !in_array($loan->status, Loan::OPEN_STATUSES, true)) {
            return;
        }

        $category = $loan->creditCategory;
        $graceDays = (int) ($category?->penalty_grace_days ?? 0);
        $rate = (float) ($category?->penalty_rate ?? 0);
        $fixed = (float) ($category?->penalty_fixed_amount ?? 0);

        $hasOverdue = false;
        foreach ($loan->installments()->where('status', '!=', LoanInstallment::STATUS_PAID)->get() as $installment) {
            if (!$installment->isOverdue()) {
                continue;
            }
            $hasOverdue = true;

            if ((float) $installment->penalty <= 0 && $installment->daysLate() > $graceDays && ($fixed > 0 || $rate > 0)) {
                $installment->penalty = $fixed > 0 ? $fixed : round((float) $installment->amount * $rate / 100, 2);
                $installment->refreshStatus();
                $installment->save();
            }
        }

        $status = $hasOverdue ? Loan::STATUS_OVERDUE : Loan::STATUS_ACTIVE;
        if ($loan->status !== Loan::STATUS_DEFAULTED && $loan->status !== $status) {
            $loan->forceFill(['status' => $status])->save();
        }
    }

    /** Cuotas pendientes con lo que falta, para la UI y la API. */
    public function summary(Loan $loan): array
    {
        $installments = $loan->installments()->get();
        $pending = $installments->where('status', '!=', LoanInstallment::STATUS_PAID);
        $overdue = $pending->filter->isOverdue();
        $next = $pending->sortBy('number')->first();

        return [
            'total_to_pay' => round((float) $installments->sum('amount') + (float) $installments->sum('penalty'), 2),
            'paid' => round((float) $installments->sum(fn ($i) => (float) $i->paid_capital + (float) $i->paid_interest + (float) $i->paid_penalty), 2),
            'outstanding' => round($pending->sum(fn (LoanInstallment $i) => $i->pendingTotal()), 2),
            'outstanding_capital' => round($pending->sum(fn (LoanInstallment $i) => $i->pendingCapital()), 2),
            'overdue_amount' => round($overdue->sum(fn (LoanInstallment $i) => $i->pendingTotal()), 2),
            'overdue_count' => $overdue->count(),
            'max_days_late' => (int) ($overdue->map->daysLate()->max() ?? 0),
            'paid_count' => $installments->where('status', LoanInstallment::STATUS_PAID)->count(),
            'count' => $installments->count(),
            'next_due_date' => $next?->due_date?->toDateString(),
            'next_due_amount' => $next?->pendingTotal(),
        ];
    }

    /* ── Cobranza del día ──────────────────────────────────────── */

    /**
     * Préstamos en cuotas con algo para cobrar hoy (vence hoy o está atrasado).
     * Con $collectorId: solo su cartera (préstamos asignados o clientes de sus rutas).
     * Ordenados por la posición del cliente en la ruta y luego por más días de atraso.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function collectionList(?int $collectorId = null, ?int $routeId = null): \Illuminate\Support\Collection
    {
        $query = Loan::query()
            ->where('loan_kind', Loan::KIND_INSTALLMENT)
            ->whereIn('status', Loan::OPEN_STATUSES)
            ->whereHas('installments', fn ($q) => $q->where('status', '!=', LoanInstallment::STATUS_PAID)->whereDate('due_date', '<=', today()))
            ->with(['client:id,name,phone,address,location_lat,location_lng,collection_route_id,route_position',
                'branch:id,name,color', 'collector:id,full_name', 'installments']);

        if ($collectorId) {
            $query->where(function ($q) use ($collectorId) {
                $q->where('collector_id', $collectorId)
                    ->orWhereHas('client.collectionRoute', fn ($r) => $r->where('collector_id', $collectorId));
            });
        }
        if ($routeId) {
            $query->whereHas('client', fn ($c) => $c->where('collection_route_id', $routeId));
        }

        return $query->get()->map(function (Loan $loan) {
            $pending = $loan->installments->where('status', '!=', LoanInstallment::STATUS_PAID);
            $due = $pending->filter(fn (LoanInstallment $i) => $i->due_date->lte(today()));

            return [
                'loan_id' => $loan->id,
                'code' => $loan->code,
                'client' => $loan->client?->only(['id', 'name', 'phone', 'address', 'location_lat', 'location_lng']),
                'route_position' => $loan->client?->route_position ?? 9999,
                'branch' => $loan->branch?->only(['id', 'name', 'color']),
                'collector' => $loan->collector?->full_name,
                'status' => $loan->status,
                'due_today' => round($due->sum(fn (LoanInstallment $i) => $i->pendingTotal()), 2),
                'overdue_count' => $pending->filter->isOverdue()->count(),
                'max_days_late' => (int) ($pending->map->daysLate()->max() ?? 0),
                'outstanding' => round($pending->sum(fn (LoanInstallment $i) => $i->pendingTotal()), 2),
                'installment_amount' => (float) $loan->installment_amount,
            ];
        })->sortBy([['route_position', 'asc'], ['max_days_late', 'desc']])->values();
    }

    /* ── Internos ──────────────────────────────────────────────── */

    private function syncLoanTotals(Loan $loan): void
    {
        $installments = $loan->installments()->get();
        $paidTotal = $loan->payments()->whereNull('voided_at')->where('payment_type', 'installment')->sum('amount');
        $pendingCapital = round($installments->sum(fn (LoanInstallment $i) => $i->pendingCapital()), 2);
        $allPaid = $installments->every(fn (LoanInstallment $i) => $i->status === LoanInstallment::STATUS_PAID);

        $loan->forceFill([
            'total_paid' => round((float) $paidTotal, 2),
            'current_capital' => $pendingCapital,
            'total_to_pay' => round((float) $installments->sum('amount') + (float) $installments->sum('penalty'), 2),
            'status' => $allPaid ? Loan::STATUS_FINISHED : ($loan->status === Loan::STATUS_FINISHED ? Loan::STATUS_ACTIVE : $loan->status),
        ])->save();
    }

    private function assertInstallment(Loan $loan): void
    {
        if (!$loan->isInstallment()) {
            throw new LoanActionException('Esta operación es solo para préstamos en cuotas.');
        }
    }

    private function openSession(int $userId, int $companyId): CashRegisterSession
    {
        if ($error = $this->payments->getCashSessionError($userId, $companyId)) {
            throw new LoanActionException($error);
        }

        return $this->payments->resolveOpenCashSession($userId, $companyId);
    }

    private function cashMovement(Loan $loan, CashRegisterSession $session, int $userId, string $type, string $category, float $amount, string $description): void
    {
        CashMovement::create([
            'company_id' => $loan->company_id,
            'cash_register_id' => $session->cash_register_id,
            'cash_register_session_id' => $session->id,
            'user_id' => $userId,
            'type' => $type,
            'category' => $category,
            'amount' => $amount,
            'reference_type' => Loan::class,
            'reference_id' => $loan->id,
            'description' => $description,
            'movement_date' => now(),
        ]);
    }

    private function periodDays(string $frequency): int
    {
        return ['daily' => 1, 'weekly' => 7, 'biweekly' => 15, 'monthly' => 30][$frequency] ?? 30;
    }
}
