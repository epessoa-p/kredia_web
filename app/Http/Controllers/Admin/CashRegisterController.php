<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\Personal;
use App\Services\CashSessionException;
use App\Services\CashSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CashRegisterController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = CashRegister::with(['company', 'assignedPersonal', 'activeSession', 'branch'])->latest();

        if (!$user->is_super_admin) {
            $query->where('company_id', $user->getCurrentCompany()?->id);
        }

        return view('admin.cash-registers.index', [
            'cashRegisters' => $query->paginate(15),
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $companyId = $user->is_super_admin ? null : $user->getCurrentCompany()?->id;

        return view('admin.cash-registers.create', [
            'cashRegister' => null,
            'companies' => $user->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$user->getCurrentCompany()])->filter(),
            'branches' => Branch::where('active', true)
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('name')
                ->get(),
            'personals' => Personal::where('active', true)
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->is_super_admin ? $request->company_id : $user->getCurrentCompany()?->id;

        try {
            $validated = $request->validate([
                'company_id' => 'nullable|exists:companies,id',
                'branch_id' => 'required|exists:branches,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'assigned_personal_id' => 'nullable|exists:personals,id',
                'active' => 'sometimes|boolean',
            ]);

            $personalId = $validated['assigned_personal_id'] ?? null;
            if ($personalId) {
                $exists = CashRegister::where('assigned_personal_id', $personalId)
                    ->where('branch_id', $validated['branch_id'])
                    ->exists();
                if ($exists) {
                    return back()->withInput()->withErrors([
                        'assigned_personal_id' => 'Este personal ya tiene una caja asignada en esta sucursal.',
                    ]);
                }
            }

            CashRegister::create([
                'company_id' => $companyId,
                'branch_id' => $validated['branch_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'assigned_personal_id' => $validated['assigned_personal_id'] ?? null,
                'active' => $request->boolean('active', true),
            ]);

            return redirect()->route('cash-registers.index')->with('success', 'Caja creada exitosamente.');
        } catch (\Throwable $e) {
            Log::error('Error al crear caja', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear la caja.']);
        }
    }

    public function show(CashRegister $cashRegister)
    {
        $this->authorizeCashRegister($cashRegister);
        $cashRegister->load(['company', 'assignedPersonal', 'sessions' => function ($q) {
            $q->with(['openedByUser', 'closedByUser'])
                ->withCount('loanPayments')
                ->withSum('loanPayments as collected_amount', 'amount')
                ->latest()
                ->take(50);
        }]);

        return view('admin.cash-registers.show', compact('cashRegister'));
    }

    public function edit(CashRegister $cashRegister)
    {
        $this->authorizeCashRegister($cashRegister);
        $user = auth()->user();

        return view('admin.cash-registers.edit', [
            'cashRegister' => $cashRegister,
            'companies' => $user->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$user->getCurrentCompany()])->filter(),
            'branches' => Branch::where('active', true)
                ->where('company_id', $cashRegister->company_id)
                ->orderBy('name')
                ->get(),
            'personals' => Personal::where('active', true)
                ->where('company_id', $cashRegister->company_id)
                ->orderBy('full_name')
                ->get(),
        ]);
    }

    public function update(Request $request, CashRegister $cashRegister)
    {
        $this->authorizeCashRegister($cashRegister);

        try {
            $validated = $request->validate([
                'branch_id' => 'required|exists:branches,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'assigned_personal_id' => 'nullable|exists:personals,id',
                'active' => 'sometimes|boolean',
            ]);

            $personalId = $validated['assigned_personal_id'] ?? null;
            if ($personalId) {
                $exists = CashRegister::where('assigned_personal_id', $personalId)
                    ->where('branch_id', $validated['branch_id'])
                    ->where('id', '!=', $cashRegister->id)
                    ->exists();
                if ($exists) {
                    return back()->withInput()->withErrors([
                        'assigned_personal_id' => 'Este personal ya tiene una caja asignada en esta sucursal.',
                    ]);
                }
            }

            $cashRegister->update([
                'branch_id' => $validated['branch_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'assigned_personal_id' => $validated['assigned_personal_id'] ?? null,
                'active' => $request->boolean('active', false),
            ]);

            return redirect()->route('cash-registers.index')->with('success', 'Caja actualizada exitosamente.');
        } catch (\Throwable $e) {
            Log::error('Error al actualizar caja', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar la caja.']);
        }
    }

    public function destroy(CashRegister $cashRegister)
    {
        $this->authorizeCashRegister($cashRegister);

        if ($cashRegister->isOpen()) {
            return back()->with('error', 'No se puede eliminar una caja con sesión abierta.');
        }

        $cashRegister->delete();
        return redirect()->route('cash-registers.index')->with('success', 'Caja eliminada exitosamente.');
    }

    public function open(Request $request, CashSessionService $cash)
    {
        $personal = Personal::where('user_id', auth()->id())->first();

        $rules = [
            'opening_amount' => 'required|numeric|min:0',
            'opening_notes'  => 'nullable|string|max:500',
        ];
        $messages = [];
        if ($personal?->isManager()) {
            $rules['cash_register_id'] = 'required|exists:cash_registers,id';
            $messages = [
                'cash_register_id.required' => 'Debes seleccionar una caja para aperturar.',
                'cash_register_id.exists'   => 'La caja seleccionada no es válida.',
            ];
        }

        try {
            $validated = $request->validate($rules, $messages);
            $cash->open(
                (int) auth()->id(),
                null,
                isset($validated['cash_register_id']) ? (int) $validated['cash_register_id'] : null,
                (float) $validated['opening_amount'],
                $validated['opening_notes'] ?? null,
            );
        } catch (CashSessionException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Caja aperturada exitosamente.');
    }

    public function close(Request $request, CashSessionService $cash)
    {
        $rules = [
            'closing_amount' => 'nullable|numeric|min:0',
            'closing_notes' => 'nullable|string|max:500',
            'closing_breakdown' => 'nullable|array',
        ];
        foreach (array_keys(CashSessionService::DENOMINATIONS) as $key) {
            $rules['closing_breakdown.' . $key] = 'nullable|integer|min:0';
        }
        $validated = $request->validate($rules);

        try {
            $cash->close(
                (int) auth()->id(),
                null,
                ($validated['closing_amount'] ?? null) !== null ? (float) $validated['closing_amount'] : null,
                $validated['closing_breakdown'] ?? null,
                $validated['closing_notes'] ?? null,
            );
        } catch (CashSessionException $e) {
            return $e->field
                ? back()->withInput()->withErrors([$e->field => $e->getMessage()])
                : back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Caja cerrada exitosamente.');
    }

    /**
     * Verifica que la sesión pertenezca a la empresa actual y esté cerrada.
     * Devuelve un RedirectResponse si hay error, o null si todo está bien.
     */
    private function guardAdjustableSession(CashRegisterSession $session)
    {
        $user = auth()->user();

        if (!$user->is_super_admin) {
            $companyId = $session->cashRegister?->company_id;
            if ($companyId !== $user->getCurrentCompany()?->id) {
                abort(403);
            }
        }

        if (!$session->closed_at) {
            return back()->with('error', 'La caja aún está abierta; no se puede ajustar el cierre.');
        }

        return null;
    }

    /**
     * Ajuste tipo 1 — Corregir conteo: reescribe el monto declarado (closing_amount)
     * porque el usuario se equivocó al contar/registrar.
     */
    public function adjustCloseAmount(Request $request, CashRegisterSession $session)
    {
        if ($redirect = $this->guardAdjustableSession($session)) {
            return $redirect;
        }

        $validated = $request->validate([
            'closing_amount' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:500',
        ]);

        $amount = round((float) $validated['closing_amount'], 2);
        $reason = trim($validated['reason'] ?? '');

        $stamp = '[Ajuste conteo ' . now()->format('d/m/Y H:i') . ' · ' . auth()->user()->name . '] '
            . ($reason !== '' ? $reason : 'Corrección de monto de cierre.');
        $notes = trim(($session->closing_notes ? $session->closing_notes . "\n" : '') . $stamp);

        $session->update([
            'closing_amount' => $amount,
            'closing_notes' => $notes,
        ]);

        return back()->with('success', 'Cierre corregido correctamente.');
    }

    /**
     * Ajuste tipo 2 — Registrar movimiento: la diferencia es real, se crea un
     * CashMovement de ajuste (ingreso si sobró, egreso si faltó) por el descuadre.
     */
    public function adjustCloseMovement(Request $request, CashRegisterSession $session)
    {
        if ($redirect = $this->guardAdjustableSession($session)) {
            return $redirect;
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);
        $reason = trim($validated['reason'] ?? '');

        // Recalcular en el servidor (no confiar en el cliente).
        $expected = $session->currentBalance();
        $mismatch = round((float) $session->closing_amount - $expected, 2);

        if (abs($mismatch) <= 0.01) {
            return back()->with('error', 'No hay descuadre que ajustar.');
        }

        $isSurplus = $mismatch > 0; // declarado > esperado => sobrante => ingreso
        $label = $isSurplus ? 'sobrante' : 'faltante';

        CashMovement::create([
            'company_id' => $session->cashRegister?->company_id,
            'cash_register_id' => $session->cash_register_id,
            'cash_register_session_id' => $session->id,
            'user_id' => auth()->id(),
            'type' => $isSurplus ? 'income' : 'expense',
            'category' => 'adjustment',
            'amount' => abs($mismatch),
            'reference_type' => CashRegisterSession::class,
            'reference_id' => $session->id,
            'description' => 'Ajuste de cierre (' . $label . ')' . ($reason !== '' ? ' — ' . $reason : ''),
            'movement_date' => now(),
        ]);

        return back()->with('success', 'Movimiento de ajuste registrado correctamente.');
    }

    /**
     * Registra un ingreso de efectivo en una caja ABIERTA.
     * Caso de uso: la caja se quedó sin fondos y necesita efectivo
     * para poder desembolsar un préstamo.
     */
    public function depositToSession(Request $request, CashRegisterSession $session)
    {
        $user = auth()->user();

        if (!$user->is_super_admin) {
            $companyId = $session->cashRegister?->company_id;
            if ($companyId !== $user->getCurrentCompany()?->id) {
                abort(403);
            }
        }

        // A diferencia del ajuste de cierre, aquí la caja debe estar ABIERTA.
        if ($session->closed_at) {
            return back()->with('error', 'La caja ya está cerrada; no se puede registrar un ingreso.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:500',
        ]);

        $amount = round((float) $validated['amount'], 2);

        CashMovement::create([
            'company_id' => $session->cashRegister?->company_id,
            'cash_register_id' => $session->cash_register_id,
            'cash_register_session_id' => $session->id,
            'user_id' => $user->id,
            'type' => 'income',
            'category' => 'cash_deposit',
            'amount' => $amount,
            'reference_type' => CashRegisterSession::class,
            'reference_id' => $session->id,
            'description' => 'Ingreso a caja — ' . trim($validated['reason']),
            'movement_date' => now(),
        ]);

        return back()->with('success', 'Ingreso registrado. El saldo de la caja aumentó en ' . \App\Support\Currency::format($amount) . '.');
    }

    /**
     * Registra un GASTO (egreso) manual en una caja ABIERTA.
     * Casos de uso: pago de servicios, pago a personal, otros gastos operativos.
     */
    public function registerExpense(Request $request, CashRegisterSession $session)
    {
        $user = auth()->user();

        if (!$user->is_super_admin) {
            $companyId = $session->cashRegister?->company_id;
            if ($companyId !== $user->getCurrentCompany()?->id) {
                abort(403);
            }
        }

        if ($session->closed_at) {
            return back()->with('error', 'La caja ya está cerrada; no se puede registrar un gasto.');
        }

        $companyId = $session->cashRegister?->company_id;

        $validated = $request->validate([
            'category'    => 'required|in:' . implode(',', array_keys(CashSessionService::EXPENSE_CATEGORIES)),
            'personal_id' => [
                'required_if:category,staff_payment',
                'nullable',
                'integer',
                Rule::exists('personals', 'id')->where('company_id', $companyId),
            ],
            'amount'      => 'required|numeric|min:0.01',
            'notes'       => 'nullable|string|max:500',
            'submission_token' => 'required|string|max:64',
        ], [
            'personal_id.required_if' => 'Selecciona el personal al que se realiza el pago.',
            'personal_id.exists'      => 'El personal seleccionado no es válido.',
        ]);

        // Evitar duplicados por doble clic: cada formulario trae un token de un solo
        // uso. Cache::add es atómico, así que solo el primer envío lo reclama.
        $tokenKey = 'cash-expense-token:' . $validated['submission_token'];
        if (!Cache::add($tokenKey, true, now()->addHours(12))) {
            return back()->with('success', 'Este gasto ya fue registrado.');
        }

        try {
            $movement = app(CashSessionService::class)->registerExpense(
                $session,
                (int) $user->id,
                $validated['category'],
                isset($validated['personal_id']) ? (int) $validated['personal_id'] : null,
                (float) $validated['amount'],
                $validated['notes'] ?? null,
            );
        } catch (CashSessionException $e) {
            // El gasto no se registró: el mismo formulario puede volver a enviarse.
            Cache::forget($tokenKey);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Gasto registrado. El saldo de la caja disminuyó en ' . \App\Support\Currency::format((float) $movement->amount) . '.');
    }

    /**
     * Get the cash register assigned to the user's personal record.
     */
    private function getUserCashRegister($user): ?CashRegister
    {
        $personal = Personal::where('user_id', $user->id)->first();
        if (!$personal) {
            return null;
        }

        return CashRegister::where('assigned_personal_id', $personal->id)
            ->where('active', true)
            ->with('activeSession')
            ->first();
    }

    private function authorizeCashRegister(CashRegister $cashRegister): void
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $cashRegister->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
