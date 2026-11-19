<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de movimientos de caja para la API.
 *
 * Expone los movimientos de caja (ingresos y egresos) de la empresa
 * para consulta y detalle.
 */
class MovementController extends Controller
{
    /**
     * Lista los movimientos de caja con paginación.
     *
     * Incluye caja, sesión de caja y usuario que realizó el movimiento.
     * Soporta filtros por tipo (income/expense), categoría, caja específica,
     * búsqueda en referencia/descripción y rango de fechas.
     *
     * @param  Request  $request  Parámetros: type, category, cash_register_id, q,
     *                             date_from, date_to, per_page (1-100, def. 15)
     * @return JsonResponse  200 con colección paginada de movimientos.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        $query = CashMovement::with(['cashRegister', 'cashRegisterSession', 'user'])->latest('movement_date');

        if (!$user->is_super_admin) {
            $query->where('company_id', $company?->id);
        } elseif ($company) {
            $query->where('company_id', $company->id);
        }

        $type = trim((string) $request->get('type', ''));
        if ($type !== '') {
            $query->where('type', $type);
        }

        $category = trim((string) $request->get('category', ''));
        if ($category !== '') {
            $query->where('category', $category);
        }

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->integer('cash_register_id'));
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('reference_type', 'like', "%{$q}%")
                    ->orWhere('reference_id', $q)
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $dateFrom = $request->get('date_from');
        if (!empty($dateFrom)) {
            $query->whereDate('movement_date', '>=', $dateFrom);
        }

        $dateTo = $request->get('date_to');
        if (!empty($dateTo)) {
            $query->whereDate('movement_date', '<=', $dateTo);
        }

        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $movements = $query->paginate($perPage)->withQueryString();

        return response()->json($movements);
    }

    /**
     * Retorna el detalle completo de un movimiento de caja.
     *
     * Carga la caja, sesión de caja, usuario y el modelo de referencia
     * polimórfica asociado al movimiento (préstamo, compra, etc.).
     *
     * @param  Request       $request
     * @param  CashMovement  $movement
     * @return JsonResponse  200 con el movimiento y sus relaciones.
     */
    public function show(Request $request, CashMovement $movement): JsonResponse
    {
        $this->authorizeMovement($request, $movement);

        $movement->load(['cashRegister', 'cashRegisterSession', 'user', 'reference']);

        return response()->json([
            'movement' => $movement,
        ]);
    }

    /**
     * Verifica que el usuario tenga acceso al movimiento de caja.
     *
     * Aborta con 403 si el movimiento no pertenece a la empresa del token.
     *
     * @param  Request       $request
     * @param  CashMovement  $movement
     */
    protected function authorizeMovement(Request $request, CashMovement $movement): void
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && $movement->company_id !== $company?->id) {
            abort(403, 'No tienes acceso a este movimiento.');
        }

        if ($user->is_super_admin && $company && $movement->company_id !== $company->id) {
            abort(403, 'El movimiento no pertenece a la empresa del token.');
        }
    }
}
