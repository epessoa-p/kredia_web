<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Services\CashSessionException;
use App\Services\CashSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Caja del usuario desde la app móvil: estado, apertura y cierre.
 * Misma lógica que la web (CashSessionService), limitada a la empresa del token.
 */
class CashSessionController extends Controller
{
    public function __construct(protected CashSessionService $cash)
    {
    }

    /** GET /cash-session — estado de la caja del usuario y lo necesario para abrir/cerrar. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');
        $personal = $this->cash->personalFor((int) $user->id);

        $registers = $personal ? $this->cash->assignedRegisters($personal, $company?->id) : collect();
        $session = $personal ? $this->cash->openSessionFor($personal, $company?->id) : null;
        $isManager = (bool) $personal?->isManager();

        // Motivo por el que no puede abrir caja (null = puede).
        $openError = match (true) {
            !$personal => 'Tu usuario no tiene un personal asociado al sistema.',
            $registers->isEmpty() => 'No tienes una caja asignada. Contacta al administrador.',
            !$isManager && !$personal->branch_id => 'Tu perfil de personal no tiene una sucursal asignada. Contacta al administrador.',
            default => null,
        };

        // El gerente elige entre sus cajas; el resto abre la suya.
        $openable = $isManager ? $registers : $registers->take(1);

        // Gastos: solo con caja abierta y permiso (igual que el botón "Registrar gasto" de la web).
        $canExpense = $session !== null && $user->hasPermissionInCompany('cash-registers.expense', $company);

        return response()->json([
            'can_register_expense' => $canExpense,
            'expense_categories' => collect(CashSessionService::EXPENSE_CATEGORIES)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])
                ->values(),
            'expense_personals' => $canExpense
                ? $this->cash->expensePersonals((int) $session->cashRegister->company_id)
                    ->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name])
                    ->values()
                : [],
            'can_operate' => $user->hasPermissionInCompany('cash-registers.operate', $company),
            'is_manager' => $isManager,
            'open_error' => $session ? null : $openError,
            'registers' => $openable->map(fn (CashRegister $r) => $this->registerPayload($r))->values(),
            'session' => $session ? $this->sessionPayload($session) : null,
            'denominations' => collect(CashSessionService::DENOMINATIONS)
                ->map(fn ($value, $key) => ['key' => $key, 'value' => $value])
                ->values(),
        ]);
    }

    /** POST /cash-session/open { cash_register_id? (gerente), opening_amount, opening_notes?, request_id? } */
    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cash_register_id' => 'nullable|integer',
            'opening_amount' => 'required|numeric|min:0',
            'opening_notes' => 'nullable|string|max:500',
            'request_id' => 'nullable|string|max:64',
        ], [
            'cash_register_id.integer' => 'La caja seleccionada no es válida.',
            'opening_amount.required' => 'Ingresa el monto de apertura.',
            'opening_amount.numeric' => 'El monto de apertura debe ser un número.',
            'opening_amount.min' => 'El monto de apertura no puede ser negativo.',
            'opening_notes.max' => 'Las notas no pueden superar los 500 caracteres.',
        ]);

        return $this->run($request, 'Caja aperturada exitosamente.', fn () => $this->cash->open(
            (int) $request->user()->id,
            $request->attributes->get('apiCompany')?->id,
            isset($validated['cash_register_id']) ? (int) $validated['cash_register_id'] : null,
            (float) $validated['opening_amount'],
            $validated['opening_notes'] ?? null,
        ));
    }

    /** POST /cash-session/close { closing_amount? | closing_breakdown{b200..c10}?, closing_notes?, request_id? } */
    public function close(Request $request): JsonResponse
    {
        $rules = [
            'closing_amount' => 'nullable|numeric|min:0',
            'closing_notes' => 'nullable|string|max:500',
            'closing_breakdown' => 'nullable|array',
            'request_id' => 'nullable|string|max:64',
        ];
        foreach (array_keys(CashSessionService::DENOMINATIONS) as $key) {
            $rules['closing_breakdown.' . $key] = 'nullable|integer|min:0';
        }
        $validated = $request->validate($rules, [
            'closing_amount.numeric' => 'El monto de cierre debe ser un número.',
            'closing_amount.min' => 'El monto de cierre no puede ser negativo.',
            'closing_notes.max' => 'Las notas no pueden superar los 500 caracteres.',
            'closing_breakdown.*.integer' => 'Las cantidades deben ser números enteros.',
            'closing_breakdown.*.min' => 'Las cantidades no pueden ser negativas.',
        ]);

        return $this->run($request, 'Caja cerrada exitosamente.', fn () => $this->cash->close(
            (int) $request->user()->id,
            $request->attributes->get('apiCompany')?->id,
            ($validated['closing_amount'] ?? null) !== null ? (float) $validated['closing_amount'] : null,
            $validated['closing_breakdown'] ?? null,
            $validated['closing_notes'] ?? null,
        ));
    }

    /**
     * POST /cash-session/expense — gasto en la caja abierta del usuario.
     * { category: service_expense|staff_payment|operating_expense, personal_id? (pago a personal),
     *   amount, notes?, request_id? }
     */
    public function expense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'personal_id' => 'nullable|integer',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
            'request_id' => 'nullable|string|max:64',
        ], [
            'category.required' => 'Selecciona el tipo de gasto.',
            'personal_id.integer' => 'El personal seleccionado no es válido.',
            'amount.required' => 'Ingresa el monto del gasto.',
            'amount.numeric' => 'El monto debe ser un número.',
            'amount.min' => 'El monto debe ser mayor a 0.',
            'notes.max' => 'Las notas no pueden superar los 500 caracteres.',
        ]);

        $userId = (int) $request->user()->id;
        $company = $request->attributes->get('apiCompany');

        return $this->run($request, null, function () use ($validated, $userId, $company) {
            $personal = $this->cash->personalFor($userId);
            $session = $personal ? $this->cash->openSessionFor($personal, $company?->id) : null;
            if (!$session) {
                throw new CashSessionException('No hay ninguna caja abierta. Debes aperturar una caja antes de registrar gastos.');
            }

            $movement = $this->cash->registerExpense(
                $session,
                $userId,
                strtolower(trim($validated['category'])),
                isset($validated['personal_id']) ? (int) $validated['personal_id'] : null,
                (float) $validated['amount'],
                $validated['notes'] ?? null,
            );

            return [$session, 'Gasto registrado. El saldo de la caja disminuyó en ' . \App\Support\Currency::format((float) $movement->amount, $company) . '.'];
        });
    }

    /** Ejecuta con protección contra doble envío (request_id) y traduce errores a JSON. */
    protected function run(Request $request, ?string $message, \Closure $action): JsonResponse
    {
        $requestId = trim((string) $request->input('request_id', ''));
        $lockKey = $requestId !== '' ? 'api-cash-session:' . $request->user()->id . ':' . $requestId : null;
        if ($lockKey && !Cache::add($lockKey, true, now()->addMinutes(10))) {
            return response()->json(['message' => 'Esta operación ya fue enviada.', 'code' => 'duplicate_request'], 409);
        }

        try {
            // La acción devuelve la sesión, o [sesión, mensaje] si el mensaje depende del resultado.
            $result = $action();
            [$session, $message] = is_array($result) ? $result : [$result, $message];
        } catch (CashSessionException $e) {
            if ($lockKey) {
                Cache::forget($lockKey);
            }
            return response()->json(array_filter([
                'message' => $e->getMessage(),
                'errors' => $e->field ? [$e->field => [$e->getMessage()]] : null,
            ]), 422);
        } catch (\Throwable $e) {
            if ($lockKey) {
                Cache::forget($lockKey);
            }
            Log::error('API cash session error', ['user_id' => $request->user()->id, 'message' => $e->getMessage()]);
            return response()->json(['message' => 'No fue posible completar la operación. Intenta nuevamente.'], 500);
        }

        /** @var CashRegisterSession $session */
        $session->refresh()->load('cashRegister.branch');

        return response()->json([
            'message' => $message,
            'session' => $this->sessionPayload($session),
        ]);
    }

    protected function registerPayload(CashRegister $r): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'branch' => $r->branch ? ['id' => $r->branch->id, 'name' => $r->branch->name, 'color' => $r->branch->color] : null,
        ];
    }

    protected function sessionPayload(CashRegisterSession $s): array
    {
        return [
            'id' => $s->id,
            'cash_register' => $s->cashRegister ? $this->registerPayload($s->cashRegister) : null,
            'opened_at' => $s->opened_at?->toIso8601String(),
            'opening_notes' => $s->opening_notes,
            'closed_at' => $s->closed_at?->toIso8601String(),
            'closing_amount' => $s->closing_amount !== null ? (float) $s->closing_amount : null,
            'summary' => $this->cash->summary($s),
        ];
    }
}
