<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditCategory;
use App\Models\Purchase;
use App\Services\LoanPaymentService;
use App\Services\PurchaseException;
use App\Services\PurchaseService;
use App\Support\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controlador de compras para la API.
 *
 * Expone el listado y el detalle de compras (empeños/prendas)
 * con sus relaciones: cliente, producto, sucursal, caja y más.
 */
class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $purchases, protected LoanPaymentService $payments)
    {
    }

    /**
     * Lista las compras con paginación.
     *
     * Incluye cliente, producto, sucursal, categoría de crédito, usuario creador,
     * caja y sesión de caja. Soporta filtros por sucursal, cliente, producto, estado,
     * búsqueda por ID/notas/cliente/producto y rango de fechas de compra.
     *
     * @param  Request  $request  Parámetros: branch_id, client_id, product_id, status,
     *                             q, date_from, date_to, per_page (1-100, def. 15)
     * @return JsonResponse  200 con colección paginada de compras (incluye numero y code).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        $query = Purchase::with([
            'client',
            'product',
            'branch',
            'creditCategory',
            'createdBy',
            'cashRegister',
            'cashRegisterSession',
        ])->latest();

        if (!$user->is_super_admin) {
            $query->where('company_id', $company?->id);
        } elseif ($company) {
            $query->where('company_id', $company->id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        $status = trim((string) $request->get('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('id', $q)
                    ->orWhere('notes', 'like', "%{$q}%")
                    ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$q}%"));
            });
        }

        $dateFrom = $request->get('date_from');
        if (!empty($dateFrom)) {
            $query->whereDate('purchase_date', '>=', $dateFrom);
        }

        $dateTo = $request->get('date_to');
        if (!empty($dateTo)) {
            $query->whereDate('purchase_date', '<=', $dateTo);
        }

        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $purchases = $query->paginate($perPage)->withQueryString();

        return response()->json($purchases);
    }

    /**
     * Retorna el detalle completo de una compra.
     *
     * Carga todas las relaciones: cliente, producto, sucursal, categoría de crédito,
     * usuario creador, caja, sesión de caja e imágenes adjuntas.
     *
     * @param  Request   $request
     * @param  Purchase  $purchase
     * @return JsonResponse  200 con la compra y sus relaciones cargadas.
     */
    public function show(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizePurchase($request, $purchase);

        $purchase->load([
            'client',
            'product',
            'branch',
            'creditCategory',
            'createdBy',
            'cashRegister',
            'cashRegisterSession',
            'images',
        ]);

        $user = $request->user();
        $deleteBlocked = $this->purchases->deleteBlockReason($purchase);

        return response()->json([
            'purchase' => $purchase,
            'computed' => [
                'delete_blocked_reason' => $deleteBlocked,
                'cash_session_error' => $this->payments->getCashSessionError((int) $user->id, (int) $purchase->company_id),
            ],
            'abilities' => [
                'delete' => $deleteBlocked === null && $user->hasPermissionInCompany('purchases.delete', $purchase->company),
            ],
        ]);
    }

    /**
     * GET /purchases/options — datos para el formulario "Nueva compra": sucursal y saldo de la
     * caja abierta, categorías activas de la empresa, kilatajes y materiales de joyas.
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');
        if (!$company) {
            return response()->json(['message' => 'No tienes empresa activa asignada.'], 403);
        }

        $cashError = $this->payments->getCashSessionError((int) $user->id, (int) $company->id);
        $session = $cashError ? null : $this->payments->resolveOpenCashSession((int) $user->id, (int) $company->id);
        $branch = $session?->loadMissing('cashRegister.branch')->cashRegister?->branch;

        $categories = CreditCategory::where('company_id', $company->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CreditCategory $cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                // Formulario del producto: 1 vehículo, 2 joyas, 3 garrafas, 4 artículos (cualquier otra).
                'form' => in_array((int) $cat->id, [CreditCategory::CATEGORIA_VEHICULO, CreditCategory::CATEGORIA_JOYAS, CreditCategory::CATEGORIA_GARRAFAS], true)
                    ? (int) $cat->id
                    : 4,
            ])
            ->values();

        return response()->json([
            'cash_session_error' => $cashError,
            'branch' => $branch ? ['id' => $branch->id, 'name' => $branch->name, 'color' => $branch->color] : null,
            'available_cash' => $session?->currentBalance(),
            'categories' => $categories,
            'karats' => PurchaseService::KARATS,
            'materials' => PurchaseService::MATERIALS,
        ]);
    }

    /**
     * POST /purchases (multipart) — registra una compra. Igual que la web: el producto entra al
     * almacén de la sucursal de la caja abierta y el monto sale de esa caja.
     * Campos: los de PurchaseService::validationRules(), images[] y request_id (anti duplicados).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');
        if (!$company) {
            return response()->json(['message' => 'No tienes empresa activa asignada.'], 403);
        }

        if ($limitError = $this->planLimitError((int) $company->id, ['products'])) {
            return response()->json(['message' => $limitError, 'code' => 'plan_limit_reached'], 403);
        }

        $validated = $request->validate(
            PurchaseService::validationRules() + ['request_id' => 'nullable|string|max:64'],
            PurchaseService::validationMessages()
        );

        return $this->run($request, (int) $company->id, function ($session) use ($company, $user, $validated, $request) {
            $purchase = $this->purchases->create($company, (int) $user->id, $session, $validated, $request->file('images') ?? []);

            return [
                'message' => 'Compra ' . $purchase->code . ' registrada.',
                'purchase' => $purchase->fresh(['client', 'product', 'branch', 'creditCategory']),
            ];
        });
    }

    /**
     * DELETE /purchases/{purchase} { request_id? } — elimina la compra (permiso purchases.delete):
     * lo pagado vuelve a la caja abierta del usuario y el producto sale del almacén.
     */
    public function destroy(Request $request, Purchase $purchase): JsonResponse
    {
        $this->authorizePurchase($request, $purchase);
        $request->validate(['request_id' => 'nullable|string|max:64']);

        if ($reason = $this->purchases->deleteBlockReason($purchase)) {
            return response()->json(['message' => $reason], 422);
        }

        return $this->run($request, (int) $purchase->company_id, function ($session) use ($purchase, $request) {
            $spent = $this->purchases->delete($purchase, (int) $request->user()->id, $session);

            return [
                'message' => $spent > 0
                    ? 'Compra eliminada. Se devolvieron ' . Currency::format($spent, $purchase->company) . ' a tu caja.'
                    : 'Compra eliminada.',
            ];
        });
    }

    /**
     * Ejecuta una operación que mueve caja: exige caja abierta, evita duplicados con request_id
     * (409 duplicate_request) y traduce PurchaseException a 422.
     */
    protected function run(Request $request, int $companyId, \Closure $action): JsonResponse
    {
        $userId = (int) $request->user()->id;
        if ($cashError = $this->payments->getCashSessionError($userId, $companyId)) {
            return response()->json(['message' => $cashError, 'code' => 'no_open_cash_session'], 422);
        }
        $session = $this->payments->resolveOpenCashSession($userId, $companyId);

        $requestId = trim((string) $request->input('request_id', ''));
        $lockKey = $requestId !== '' ? 'api-purchase:' . $userId . ':' . $requestId : null;
        if ($lockKey && !Cache::add($lockKey, true, now()->addMinutes(10))) {
            return response()->json(['message' => 'Esta operación ya fue enviada. Revisa la lista de compras antes de intentarlo de nuevo.', 'code' => 'duplicate_request'], 409);
        }

        try {
            $result = $action($session);
        } catch (PurchaseException $e) {
            if ($lockKey) Cache::forget($lockKey);
            return response()->json(array_filter([
                'message' => $e->getMessage(),
                'errors' => $e->field ? [$e->field => [$e->getMessage()]] : null,
            ]), 422);
        } catch (\Throwable $e) {
            if ($lockKey) Cache::forget($lockKey);
            Log::error('API: error en compra', ['route' => $request->path(), 'message' => $e->getMessage()]);
            return response()->json(['message' => 'No fue posible completar la operación. Intenta nuevamente.'], 500);
        }

        return response()->json($result, 201);
    }

    /**
     * Verifica que el usuario tenga acceso a la compra.
     *
     * Aborta con 403 si la compra no pertenece a la empresa del token.
     *
     * @param  Request   $request
     * @param  Purchase  $purchase
     */
    protected function authorizePurchase(Request $request, Purchase $purchase): void
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && $purchase->company_id !== $company?->id) {
            abort(403, 'No tienes acceso a esta compra.');
        }

        if ($user->is_super_admin && $company && $purchase->company_id !== $company->id) {
            abort(403, 'La compra no pertenece a la empresa del token.');
        }
    }
}
