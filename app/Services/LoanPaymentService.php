<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\InventoryMovement;
use App\Models\Loan;
use App\Models\LoanCollateral;
use App\Models\LoanInterestPayment;
use App\Models\Personal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Cobros de un préstamo: interés de un periodo, multa, amortización y liquidación.
 *
 * Una sola fuente de verdad para la web (LoanActionController) y la API móvil
 * (Api\LoanActionController). Cada operación corre en una transacción y bloquea
 * el préstamo (lockForUpdate) para evitar cobros duplicados.
 *
 * Los errores de negocio se lanzan como LoanActionException con el mensaje
 * listo para mostrar al usuario.
 */
class LoanPaymentService
{
    /* ── Caja ──────────────────────────────────────────────────── */

    public function resolveOpenCashSession(int $userId, int $companyId): ?CashRegisterSession
    {
        $registerIds = $this->assignedRegisterIds($userId, $companyId);
        if (!$registerIds || $registerIds->isEmpty()) {
            return null;
        }

        return CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->latest()
            ->first();
    }

    /** Motivo por el que el usuario no puede cobrar (null si tiene caja abierta). */
    public function getCashSessionError(int $userId, int $companyId): ?string
    {
        $registerIds = $this->assignedRegisterIds($userId, $companyId);
        if ($registerIds === null) {
            return 'Tu usuario no tiene un personal asociado al sistema. Contacta al administrador.';
        }
        if ($registerIds->isEmpty()) {
            return 'No tienes una caja asignada. Contacta al administrador para que te asigne una.';
        }
        $hasOpen = CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->exists();
        if (!$hasOpen) {
            return 'No hay ninguna caja abierta. Debes aperturar una caja antes de registrar operaciones.';
        }
        return null;
    }

    /** null = el usuario no tiene personal asociado. */
    protected function assignedRegisterIds(int $userId, int $companyId)
    {
        $personal = Personal::where('user_id', $userId)->first();
        if (!$personal) {
            return null;
        }

        return CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $companyId)
            ->where('active', true)
            ->pluck('id');
    }

    /* ── Estado del préstamo ───────────────────────────────────── */

    /**
     * Motivo por el que el préstamo no admite cobros (null si los admite).
     * Igual que la vista de detalle: eliminado, finalizado o incumplido = solo lectura.
     */
    public function getActionsLockedReason(Loan $loan): ?string
    {
        if ($loan->trashed()) {
            return 'El préstamo está eliminado.';
        }
        if ($loan->status === Loan::STATUS_FINISHED) {
            return 'El préstamo ya está finalizado.';
        }
        if ($loan->status === Loan::STATUS_DEFAULTED) {
            return 'El préstamo está incumplido: no se registran más pagos.';
        }
        if (!in_array($loan->status, [Loan::STATUS_ACTIVE, Loan::STATUS_OVERDUE], true)) {
            return 'El préstamo no está activo.';
        }
        return null;
    }

    /* ── Cobros ────────────────────────────────────────────────── */

    /**
     * Pago (total o parcial) del interés de un periodo. Debe ser el periodo
     * pendiente más antiguo; el monto se limita a lo que falta del periodo.
     *
     * @param array $data amount, payment_method, reference?, notes?
     */
    public function payInterest(Loan $loan, int $interestPaymentId, array $data, int $userId, ?UploadedFile $receipt = null): LoanInterestPayment
    {
        return DB::transaction(function () use ($loan, $interestPaymentId, $data, $userId, $receipt) {
            [$loan, $session] = $this->prepare($loan, $userId);

            $interestPayment = $loan->interestPayments()->lockForUpdate()->find($interestPaymentId);
            if (!$interestPayment) {
                throw new LoanActionException('El periodo de interés no existe en este préstamo.');
            }
            if ($interestPayment->status === 'paid') {
                throw new LoanActionException('Este periodo de interés ya fue pagado.');
            }

            $oldestPending = $loan->interestPayments()
                ->where('status', '!=', 'paid')
                ->orderBy('period_number')
                ->first();
            if ($oldestPending && (int) $oldestPending->id !== (int) $interestPayment->id) {
                throw new LoanActionException('Debes pagar primero el interés pendiente más antiguo: periodo #' . $oldestPending->period_number . '.');
            }

            $amount = min((float) $data['amount'], $interestPayment->getRemainingAmount());
            if ($amount <= 0) {
                throw new LoanActionException('El monto debe ser mayor a 0.');
            }

            $receiptPath = $this->storeReceipt($receipt);
            $newPaid = (float) $interestPayment->paid_amount + $amount;

            $interestPayment->update([
                'paid_amount' => $newPaid,
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'receipt_path' => $receiptPath ?? $interestPayment->receipt_path,
                'notes' => $data['notes'] ?? null,
                'registered_by' => $userId,
                'paid_at' => now(),
                'status' => $newPaid >= (float) $interestPayment->interest_amount ? 'paid' : 'pending',
            ]);

            $this->recordPayment($loan, $session, $userId, $receiptPath, $data, [
                'amount' => $amount,
                'payment_type' => 'interest',
                'capital' => 0,
                'interest' => $amount,
                'payment_date' => now()->toDateString(),
                'notes' => 'Pago interés periodo #' . $interestPayment->period_number . '. ' . ($data['notes'] ?? ''),
            ]);
            $this->recordCashIncome($loan, $session, $userId, 'interest_payment', $amount,
                'Pago interés periodo #' . $interestPayment->period_number . ' - Préstamo ' . $this->code($loan));

            $loan->increment('total_paid', $amount);

            return $interestPayment->fresh();
        });
    }

    /**
     * Pago de la multa (monto total). Salda SOLO la multa: el estado de mora
     * no cambia hasta que se paguen los intereses vencidos.
     *
     * @return float monto cobrado
     */
    public function payPenalty(Loan $loan, array $data, int $userId, ?UploadedFile $receipt = null): float
    {
        return DB::transaction(function () use ($loan, $data, $userId, $receipt) {
            [$loan, $session] = $this->prepare($loan, $userId);

            $amount = $loan->getPendingPenalty();
            if ($amount <= 0) {
                throw new LoanActionException('No hay multa pendiente para este préstamo.');
            }

            $receiptPath = $this->storeReceipt($receipt);

            $this->recordPayment($loan, $session, $userId, $receiptPath, $data, [
                'amount' => $amount,
                'payment_type' => 'penalty',
                'capital' => 0,
                'interest' => $amount,
                'payment_date' => now()->toDateString(),
                'notes' => 'Pago de multa. ' . ($data['notes'] ?? ''),
            ]);
            $this->recordCashIncome($loan, $session, $userId, 'penalty_payment', $amount,
                'Pago de multa préstamo ' . $this->code($loan));

            $loan->increment('total_paid', $amount);
            $loan->update(['penalty_paid_at' => now()]);

            return $amount;
        });
    }

    /**
     * Amortización (abono al capital). Si el capital llega a 0 y no quedan
     * intereses pendientes, el préstamo se finaliza y se libera la prenda.
     *
     * @param array $data amount, payment_date, payment_method, reference?, notes?
     * @return float capital después del abono
     */
    public function amortize(Loan $loan, array $data, int $userId, ?UploadedFile $receipt = null): float
    {
        return DB::transaction(function () use ($loan, $data, $userId, $receipt) {
            [$loan, $session] = $this->prepare($loan, $userId);

            $currentCapital = $loan->getCurrentCapital();
            $amount = (float) $data['amount'];
            if ($amount <= 0 || $amount > $currentCapital) {
                throw new LoanActionException('El monto a amortizar debe ser mayor a 0 y no superar el capital vigente (' . \App\Support\Currency::format($currentCapital, $loan->company) . ').');
            }
            $capitalAfter = round($currentCapital - $amount, 2);

            $receiptPath = $this->storeReceipt($receipt);

            $loan->amortizations()->create([
                'amount' => $amount,
                'capital_before' => $currentCapital,
                'capital_after' => $capitalAfter,
                'payment_date' => $data['payment_date'],
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'receipt_path' => $receiptPath,
                'notes' => $data['notes'] ?? null,
                'registered_by' => $userId,
            ]);

            $loan->update(['current_capital' => $capitalAfter]);

            $this->recordPayment($loan, $session, $userId, $receiptPath, $data, [
                'amount' => $amount,
                'payment_type' => 'capital',
                'capital' => $amount,
                'interest' => 0,
                'payment_date' => $data['payment_date'],
                'notes' => 'Amortización al capital. ' . ($data['notes'] ?? ''),
            ]);
            $this->recordCashIncome($loan, $session, $userId, 'amortization', $amount,
                'Amortización préstamo ' . $this->code($loan));

            $loan->increment('total_paid', $amount);

            if ($capitalAfter <= 0 && $loan->getUnpaidInterestTotal() <= 0) {
                $loan->update(['status' => Loan::STATUS_FINISHED, 'monthly_payment' => 0]);
                $this->releaseCollateral($loan, $userId);
            }

            return $capitalAfter;
        });
    }

    /**
     * Liquidación: capital + intereses pendientes + multa. Finaliza el préstamo
     * y libera la prenda.
     *
     * @param array $data amount (>= monto de liquidación), payment_date, payment_method, reference?, notes?
     */
    public function liquidate(Loan $loan, array $data, int $userId, ?UploadedFile $receipt = null): void
    {
        DB::transaction(function () use ($loan, $data, $userId, $receipt) {
            [$loan, $session] = $this->prepare($loan, $userId);

            $liquidationAmount = $loan->getLiquidationAmount();
            $amount = (float) $data['amount'];
            if ($amount < $liquidationAmount || $amount <= 0) {
                throw new LoanActionException('El monto debe ser al menos el monto de liquidación (' . \App\Support\Currency::format($liquidationAmount, $loan->company) . ').');
            }

            $capital = $loan->getCurrentCapital();
            $unpaidInterest = $loan->getUnpaidInterestTotal();
            $penalty = $loan->getPendingPenalty();

            $receiptPath = $this->storeReceipt($receipt);

            $loan->interestPayments()->where('status', '!=', 'paid')->each(function ($ip) use ($userId) {
                $ip->update([
                    'paid_amount' => $ip->interest_amount,
                    'status' => 'paid',
                    'paid_at' => now(),
                    'registered_by' => $userId,
                ]);
            });

            $this->recordPayment($loan, $session, $userId, $receiptPath, $data, [
                'amount' => $amount,
                'payment_type' => 'mixed',
                'capital' => $capital,
                'interest' => $unpaidInterest + $penalty,
                'payment_date' => $data['payment_date'],
                'notes' => 'Liquidación total del préstamo. ' . ($data['notes'] ?? ''),
            ]);
            $this->recordCashIncome($loan, $session, $userId, 'liquidation', $amount,
                'Liquidación préstamo ' . $this->code($loan));

            $loan->increment('total_paid', $amount);

            $loan->update([
                'status' => Loan::STATUS_FINISHED,
                'current_capital' => 0,
                'monthly_payment' => 0,
                'penalty_status' => 'normal',
                'penalty_amount' => 0,
                'penalty_paid_at' => now(),
                'total_to_pay' => max((float) $loan->total_to_pay, (float) $loan->total_paid + $amount),
            ]);

            $this->releaseCollateral($loan, $userId);
        });
    }

    /* ── Eliminar / restaurar periodos de interés ──────────────── */

    /** Soft delete manual de un periodo no pagado (queda auditado y puede restaurarse). */
    public function deleteInterest(Loan $loan, LoanInterestPayment $interestPayment, int $userId): void
    {
        DB::transaction(function () use ($loan, $interestPayment, $userId) {
            $loan = Loan::withTrashed()->lockForUpdate()->findOrFail($loan->id);
            $interestPayment = $this->lockedInterest($loan, $interestPayment);

            if ($interestPayment->trashed()) {
                throw new LoanActionException('Este periodo de interés ya está eliminado.');
            }
            if ($interestPayment->status === 'paid') {
                throw new LoanActionException('No se puede eliminar un periodo de interés ya pagado.');
            }
            if ($reason = $this->getActionsLockedReason($loan)) {
                throw new LoanActionException($reason);
            }

            $interestPayment->deleteWithAudit('manual', $userId);
        });
    }

    /** Restaura un periodo eliminado; vuelve a contar para la mora. */
    public function restoreInterest(Loan $loan, LoanInterestPayment $interestPayment, int $userId): void
    {
        DB::transaction(function () use ($loan, $interestPayment, $userId) {
            $loan = Loan::withTrashed()->lockForUpdate()->findOrFail($loan->id);
            $interestPayment = $this->lockedInterest($loan, $interestPayment);

            if (!$interestPayment->trashed()) {
                throw new LoanActionException('Este periodo de interés no está eliminado.');
            }
            // Solo préstamos activos o en mora (un préstamo incumplido es de solo lectura).
            if ($loan->trashed() || !in_array($loan->status, [Loan::STATUS_ACTIVE, Loan::STATUS_OVERDUE], true)) {
                throw new LoanActionException('Solo se pueden restaurar intereses de préstamos activos o en mora.');
            }
            // No duplicar: si ya hay un interés activo para ese periodo, no se restaura.
            if ($loan->interestPayments()->where('period_number', $interestPayment->period_number)->exists()) {
                throw new LoanActionException('Ya existe un interés activo para el periodo #' . $interestPayment->period_number . '.');
            }

            $interestPayment->restore();
            $interestPayment->forceFill([
                'deleted_by' => null,
                'deletion_reason' => null,
                'restored_by' => $userId,
                'restored_at' => now(),
            ])->save();

            $loan->refresh()->checkOverdueStatus();
        });
    }

    protected function lockedInterest(Loan $loan, LoanInterestPayment $interestPayment): LoanInterestPayment
    {
        $locked = LoanInterestPayment::withTrashed()
            ->where('loan_id', $loan->id)
            ->lockForUpdate()
            ->find($interestPayment->id);

        if (!$locked) {
            throw new LoanActionException('El periodo de interés no existe en este préstamo.');
        }

        return $locked;
    }

    /* ── Internos ──────────────────────────────────────────────── */

    /**
     * Bloquea el préstamo y valida estado y caja.
     *
     * @return array{0: Loan, 1: CashRegisterSession}
     */
    protected function prepare(Loan $loan, int $userId): array
    {
        $loan = Loan::withTrashed()->lockForUpdate()->findOrFail($loan->id);

        if ($reason = $this->getActionsLockedReason($loan)) {
            throw new LoanActionException($reason);
        }

        if ($cashError = $this->getCashSessionError($userId, (int) $loan->company_id)) {
            throw new LoanActionException($cashError);
        }

        return [$loan, $this->resolveOpenCashSession($userId, (int) $loan->company_id)];
    }

    protected function storeReceipt(?UploadedFile $receipt): ?string
    {
        return $receipt ? $receipt->store('loan-receipts', 'public') : null;
    }

    protected function recordPayment(Loan $loan, CashRegisterSession $session, int $userId, ?string $receiptPath, array $data, array $fields): void
    {
        $loan->payments()->create(array_merge([
            'registered_by' => $userId,
            'cash_register_id' => $session->cash_register_id,
            'cash_register_session_id' => $session->id,
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'receipt_path' => $receiptPath,
        ], $fields));
    }

    protected function recordCashIncome(Loan $loan, CashRegisterSession $session, int $userId, string $category, float $amount, string $description): void
    {
        CashMovement::create([
            'company_id' => $loan->company_id,
            'cash_register_id' => $session->cash_register_id,
            'cash_register_session_id' => $session->id,
            'user_id' => $userId,
            'type' => 'income',
            'category' => $category,
            'amount' => $amount,
            'reference_type' => Loan::class,
            'reference_id' => $loan->id,
            'description' => $description,
            'movement_date' => now(),
        ]);
    }

    protected function code(Loan $loan): string
    {
        return 'PR-' . str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT);
    }

    /** Salida de inventario de la prenda cuando el préstamo se cierra pagado. */
    public function releaseCollateral(Loan $loan, int $userId): void
    {
        $collateral = LoanCollateral::where('loan_id', $loan->id)
            ->whereIn('status', ['retained', 'stored', 'sellable'])
            ->first();

        if (!$collateral) {
            return;
        }

        InventoryMovement::create([
            'company_id' => $loan->company_id,
            'warehouse_id' => $collateral->warehouse_id,
            'branch_id' => $collateral->branch_id,
            'product_id' => $collateral->product_id,
            'user_id' => $userId,
            'type' => 'out',
            'quantity' => 1,
            'unit_cost' => $loan->product?->cost ?? 0,
            'reference' => 'LOAN-REL-' . $loan->id,
            'notes' => 'Salida por liberación de prenda (préstamo liquidado)',
            'movement_date' => now(),
        ]);

        $collateral->update([
            'status' => 'released',
            'released_at' => now(),
        ]);
    }
}
