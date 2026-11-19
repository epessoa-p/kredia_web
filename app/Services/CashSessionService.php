<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Personal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Apertura y cierre de caja del usuario. Una sola fuente de verdad para la web
 * (Admin\CashRegisterController) y la API móvil (Api\CashSessionController).
 *
 * Reglas (las de la web):
 *  - Solo se trabaja con las cajas activas asignadas al personal del usuario.
 *  - No se abre otra caja si ya tiene una abierta.
 *  - El gerente elige cuál de sus cajas abrir; el resto usa su caja y debe tener sucursal.
 *  - Al cerrar se informa el monto contado o el conteo por denominaciones.
 *
 * $companyId null = todas las empresas (comportamiento de la web); la API pasa la del token.
 * Los errores de negocio se lanzan como CashSessionException con el mensaje para el usuario.
 */
class CashSessionService
{
    /** Billetes y monedas del conteo de cierre (clave => valor en Bs). */
    public const DENOMINATIONS = [
        'b200' => 200, 'b100' => 100, 'b50' => 50, 'b20' => 20, 'b10' => 10,
        'm5' => 5, 'm2' => 2, 'm1' => 1,
        'c50' => 0.50, 'c20' => 0.20, 'c10' => 0.10,
    ];

    public function personalFor(int $userId): ?Personal
    {
        return Personal::where('user_id', $userId)->first();
    }

    /** Cajas activas asignadas al personal (con sucursal). */
    public function assignedRegisters(Personal $personal, ?int $companyId = null): Collection
    {
        return CashRegister::where('assigned_personal_id', $personal->id)
            ->where('active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->with('branch')
            ->orderBy('name')
            ->get();
    }

    public function openSessionFor(Personal $personal, ?int $companyId = null): ?CashRegisterSession
    {
        $ids = $this->assignedRegisters($personal, $companyId)->pluck('id');
        if ($ids->isEmpty()) {
            return null;
        }

        return CashRegisterSession::whereIn('cash_register_id', $ids)
            ->whereNull('closed_at')
            ->with('cashRegister.branch')
            ->latest()
            ->first();
    }

    /** Apertura, ingresos, egresos y lo que debería haber en caja. */
    public function summary(CashRegisterSession $session): array
    {
        $income = (float) $session->cashMovements()->where('type', 'income')->sum('amount');
        $expense = (float) $session->cashMovements()->where('type', 'expense')->sum('amount');

        return [
            'opening_amount' => round((float) $session->opening_amount, 2),
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'expected' => round((float) $session->opening_amount + $income - $expense, 2),
        ];
    }

    /** Tipos de gasto manual (categoría del movimiento de caja => etiqueta). */
    public const EXPENSE_CATEGORIES = [
        'service_expense'   => 'Gasto de servicio',
        'staff_payment'     => 'Pago a personal',
        'operating_expense' => 'Otro gasto operativo',
    ];

    /** Personal activo de la empresa (destinatarios del gasto "Pago a personal"). */
    public function expensePersonals(int $companyId): Collection
    {
        return Personal::where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name']);
    }

    /**
     * Registra un GASTO (egreso) manual en una caja ABIERTA: servicio, pago a personal u
     * otro gasto operativo. No se puede gastar más del efectivo disponible.
     * La protección contra doble envío (token) la hace quien llama.
     */
    public function registerExpense(CashRegisterSession $session, int $userId, string $category, ?int $personalId, float $amount, ?string $notes): CashMovement
    {
        return DB::transaction(function () use ($session, $userId, $category, $personalId, $amount, $notes) {
            // Bloquea la sesión: dos gastos simultáneos no pueden pasar ambos el tope de saldo.
            $session = CashRegisterSession::with('cashRegister')->lockForUpdate()->findOrFail($session->id);

            if ($session->closed_at) {
                throw new CashSessionException('La caja ya está cerrada; no se puede registrar un gasto.');
            }
            if (!isset(self::EXPENSE_CATEGORIES[$category])) {
                throw new CashSessionException('Selecciona un tipo de gasto válido.', 'category');
            }

            $companyId = $session->cashRegister?->company_id;
            $amount = round($amount, 2);
            if ($amount <= 0) {
                throw new CashSessionException('El monto debe ser mayor a 0.', 'amount');
            }

            // Tope por saldo: no se puede gastar más del efectivo disponible.
            $available = $session->currentBalance();
            if ($amount > $available) {
                throw new CashSessionException('No hay suficiente efectivo en la caja; disponible ' . \App\Support\Currency::format($available, $session->cashRegister?->company) . '.', 'amount');
            }

            // Pago a personal: el movimiento queda vinculado al personal que recibe el pago.
            $personal = null;
            if ($category === 'staff_payment') {
                $personal = $personalId
                    ? Personal::where('id', $personalId)->where('company_id', $companyId)->first()
                    : null;
                if (!$personal) {
                    throw new CashSessionException('Selecciona el personal al que se realiza el pago.', 'personal_id');
                }
            }

            $label = self::EXPENSE_CATEGORIES[$category] . ($personal ? ' — ' . $personal->full_name : '');
            $notes = trim((string) $notes);

            return CashMovement::create([
                'company_id' => $companyId,
                'cash_register_id' => $session->cash_register_id,
                'cash_register_session_id' => $session->id,
                'user_id' => $userId,
                'type' => 'expense',
                'category' => $category,
                'amount' => $amount,
                'reference_type' => $personal ? Personal::class : CashRegisterSession::class,
                'reference_id' => $personal ? $personal->id : $session->id,
                'description' => $label . ($notes !== '' ? ' — ' . $notes : ''),
                'movement_date' => now(),
            ]);
        });
    }

    /**
     * Abre una caja.
     *
     * @param int|null $cashRegisterId obligatorio para el gerente; el resto usa su caja.
     */
    public function open(int $userId, ?int $companyId, ?int $cashRegisterId, float $openingAmount, ?string $notes): CashRegisterSession
    {
        return DB::transaction(function () use ($userId, $companyId, $cashRegisterId, $openingAmount, $notes) {
            $personal = $this->personalFor($userId);
            if (!$personal) {
                throw new CashSessionException('Tu usuario no tiene un personal asociado al sistema.');
            }

            $registers = $this->assignedRegisters($personal, $companyId);
            // Bloquea las cajas del usuario para que dos aperturas simultáneas no pasen ambas.
            CashRegister::whereIn('id', $registers->pluck('id'))->lockForUpdate()->get();

            if ($registers->isNotEmpty() && CashRegisterSession::whereIn('cash_register_id', $registers->pluck('id'))->whereNull('closed_at')->exists()) {
                throw new CashSessionException('Ya tienes una caja abierta. Ciérrala antes de aperturar otra.');
            }

            if ($personal->isManager()) {
                if (!$cashRegisterId) {
                    throw new CashSessionException('Debes seleccionar una caja para aperturar.', 'cash_register_id');
                }
                $cashRegister = $registers->firstWhere('id', $cashRegisterId);
                if (!$cashRegister) {
                    throw new CashSessionException('La caja seleccionada no está asignada a tu usuario.', 'cash_register_id');
                }
            } else {
                if (!$personal->branch_id) {
                    throw new CashSessionException('Tu perfil de personal no tiene una sucursal asignada. Contacta al administrador.');
                }
                $cashRegister = $registers->first();
                if (!$cashRegister) {
                    throw new CashSessionException('No tienes una caja asignada.');
                }
            }

            if ($cashRegister->isOpen()) {
                throw new CashSessionException('La caja ya está abierta.');
            }

            return CashRegisterSession::create([
                'cash_register_id' => $cashRegister->id,
                'opened_by' => $userId,
                'opening_amount' => $openingAmount,
                'opening_notes' => $notes,
                'opened_at' => now(),
            ]);
        });
    }

    /**
     * Cierra la caja abierta del usuario.
     *
     * @param array<string,int>|null $breakdown cantidades por denominación (ver DENOMINATIONS);
     *                                          si trae datos, el monto de cierre es su total.
     */
    public function close(int $userId, ?int $companyId, ?float $closingAmount, ?array $breakdown, ?string $notes): CashRegisterSession
    {
        return DB::transaction(function () use ($userId, $companyId, $closingAmount, $breakdown, $notes) {
            $personal = $this->personalFor($userId);
            if (!$personal) {
                throw new CashSessionException('No tienes un perfil de personal asociado.');
            }

            $session = $this->openSessionFor($personal, $companyId);
            if (!$session) {
                throw new CashSessionException('No hay ninguna caja abierta.');
            }
            $session = CashRegisterSession::lockForUpdate()->find($session->id);
            if ($session->closed_at) {
                throw new CashSessionException('La caja ya fue cerrada.');
            }

            $quantities = [];
            $total = 0.0;
            $hasBreakdown = false;
            foreach (self::DENOMINATIONS as $key => $value) {
                $qty = (int) ($breakdown[$key] ?? 0);
                $quantities[$key] = $qty;
                $hasBreakdown = $hasBreakdown || $qty > 0;
                $total += $qty * $value;
            }

            $amount = $hasBreakdown ? round($total, 2) : ($closingAmount !== null ? round($closingAmount, 2) : null);
            if ($amount === null) {
                throw new CashSessionException('Ingresa el monto de cierre o completa las denominaciones.', 'closing_amount');
            }

            $data = [
                'closed_by' => $userId,
                'closing_amount' => $amount,
                'closing_notes' => $notes,
                'closed_at' => now(),
            ];
            if (Schema::hasColumn('cash_register_sessions', 'closing_breakdown')) {
                $data['closing_breakdown'] = ['quantities' => $quantities, 'total' => $amount];
            }

            $session->update($data);

            return $session;
        });
    }
}
