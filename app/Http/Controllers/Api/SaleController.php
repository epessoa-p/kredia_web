<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleDeleteGrant;
use App\Models\SaleInstallment;
use App\Services\LoanPaymentService;
use App\Services\SaleException;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controlador de ventas para la API.
 *
 * Expone el listado y el detalle de ventas con sus relaciones:
 * cliente, producto, sucursal, almacén, caja, cuotas e imágenes.
 */
class SaleController extends Controller
{
    /** $payments: solo para resolver la caja abierta del usuario (misma regla que los cobros). */
    public function __construct(protected SaleService $sales, protected LoanPaymentService $payments)
    {
    }

    /**
     * Lista las ventas con paginación.
     *
     * Incluye cliente, producto, sucursal, almacén, categoría de crédito,
     * usuario creador, caja y sesión de caja. Soporta filtros por sucursal,
     * almacén, cliente, producto, estado, búsqueda por ID/notas/cliente/producto
     * y rango de fechas de venta.
     *
     * @param  Request  $request  Parámetros: branch_id, warehouse_id, client_id, product_id,
     *                             status, q, date_from, date_to, per_page (1-100, def. 15)
     * @return JsonResponse  200 con colección paginada de ventas (incluye numero y code).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        $query = Sale::with([
            'client',
            'product',
            'branch',
            'warehouse',
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

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
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
            $query->whereDate('sale_date', '>=', $dateFrom);
        }

        $dateTo = $request->get('date_to');
        if (!empty($dateTo)) {
            $query->whereDate('sale_date', '<=', $dateTo);
        }

        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $sales = $query->paginate($perPage)->withQueryString();

        return response()->json($sales);
    }

    /**
     * Retorna el detalle completo de una venta.
     *
     * Carga todas las relaciones: cliente, producto, sucursal, almacén, categoría
     * de crédito, usuario creador, caja, sesión de caja, colateral de préstamo,
     * compra de origen, imágenes y cuotas de pago.
     *
     * @param  Request  $request
     * @param  Sale     $sale
     * @return JsonResponse  200 con la venta y sus relaciones cargadas.
     */
    public function show(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeSale($request, $sale);

        $sale->load([
            'client',
            'product',
            'branch',
            'warehouse',
            'creditCategory',
            'createdBy',
            'cashRegister',
            'cashRegisterSession',
            'loanCollateral',
            'purchase',
            'images',
            'installments' => fn ($q) => $q->orderBy('installment_no'),
            'returnedBy:id,name',
        ]);

        $user = $request->user();
        $completed = $sale->status === Sale::STATUS_COMPLETED;
        $hasUnpaid = $sale->installments->whereIn('status', [SaleInstallment::STATUS_PENDIENTE, SaleInstallment::STATUS_VENCIDA])->isNotEmpty();
        // Devolver: permiso por cargo o autorización puntual vigente para esta venta (como la web).
        $canReturn = $user->hasPermissionInCompany('sales.delete', $sale->company)
            || SaleDeleteGrant::activeFor($sale->id, (int) $user->id) !== null;

        return response()->json([
            'sale' => $sale,
            'computed' => [
                'status_label' => Sale::STATUS_LABELS[$sale->status] ?? $sale->status,
                'credit_total' => round((float) $sale->amount + (float) $sale->credit_additional_amount, 2),
                // Liquidación anticipada: capital impago + interés de las cuotas vencidas.
                'liquidation' => $sale->isCredit() ? $this->sales->liquidationBreakdown($sale) : null,
                'cash_session_error' => $this->payments->getCashSessionError((int) $user->id, (int) $sale->company_id),
                'return_reasons' => Sale::RETURN_REASONS,
            ],
            'abilities' => [
                'pay_installment' => $completed && $hasUnpaid,
                'liquidate' => $completed && $sale->isCredit() && $hasUnpaid
                    && $user->hasPermissionInCompany('sales.create', $sale->company),
                'return' => $completed && $canReturn,
            ],
        ]);
    }

    /**
     * GET /sales/options — lo necesario para registrar una venta: caja abierta y los productos
     * disponibles en el almacén de su sucursal (solo se vende lo de ese almacén, como la web).
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

        $products = [];
        if ($branch && $branch->warehouse_id) {
            $products = collect($this->sales->availableProducts((int) $company->id, [$branch]))
                ->sortBy('name')
                ->values()
                ->all();
        } elseif ($branch) {
            $cashError = 'La sucursal de tu caja no tiene un almacén asignado.';
        }

        return response()->json([
            'cash_session_error' => $cashError,
            'branch' => $branch ? ['id' => $branch->id, 'name' => $branch->name, 'color' => $branch->color] : null,
            'available_cash' => $session?->currentBalance(),
            'products' => $products,
        ]);
    }

    /**
     * POST /sales — registra una venta (contado o crédito con cuotas). Igual que la web.
     * La sucursal y el almacén salen de la caja abierta del usuario.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');
        if (!$company) {
            return response()->json(['message' => 'No tienes empresa activa asignada.'], 403);
        }

        $validated = $request->validate([
            'product_id' => 'required|integer',
            'payment_type' => 'required|in:CONTADO,CREDITO,contado,credito',
            'client_id' => 'nullable|integer',
            'client_name' => 'nullable|required_without:client_id|string|max:255',
            'client_id_number' => 'nullable|string|max:30',
            'client_phone' => 'nullable|string|max:20',
            'client_email' => 'nullable|email|max:255',
            'client_address' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'credit_additional_percentage' => 'nullable|numeric|max:1000',
            'down_payment' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'installments' => 'nullable|array|min:1',
            'installments.*.due_date' => 'required_if:payment_type,CREDITO,credito|date',
            'installments.*.percentage' => 'required_if:payment_type,CREDITO,credito|numeric|min:0.0001|max:100',
            'installments.*.amount' => 'required_if:payment_type,CREDITO,credito|numeric|min:0.01',
            'images' => 'nullable|array|max:20',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'request_id' => 'nullable|string|max:64',
        ], SaleService::validationMessages());

        return $this->run($request, (int) $company->id, 'Venta registrada exitosamente.', function ($session) use ($company, $user, $validated, $request) {
            $sale = $this->sales->create($company, (int) $user->id, $session, $validated, $request->file('images') ?? []);

            return ['sale' => ['id' => $sale->id, 'code' => $sale->code, 'status' => $sale->status]];
        });
    }

    /** POST /sales/installments/{installment}/pay { payment_notes? , request_id? } */
    public function payInstallment(Request $request, SaleInstallment $installment): JsonResponse
    {
        $sale = $installment->sale()->firstOrFail();
        $this->authorizeSale($request, $sale);

        $validated = $request->validate([
            'payment_notes' => 'nullable|string|max:500',
            'request_id' => 'nullable|string|max:64',
        ], SaleService::validationMessages());

        return $this->run($request, (int) $sale->company_id, null, function ($session) use ($installment, $request, $validated) {
            $paid = $this->sales->payInstallment($installment, (int) $request->user()->id, $session, $validated['payment_notes'] ?? null);

            return [
                'message' => 'Cuota ' . $paid->installment_no . ' pagada: ' . \App\Support\Currency::format((float) $paid->amount, $installment->sale?->company) . '.',
                'installment' => $paid,
            ];
        });
    }

    /** POST /sales/{sale}/liquidate { request_id? } — cancela todas las cuotas impagas (permiso sales.create). */
    public function liquidate(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeSale($request, $sale);
        $request->validate(['request_id' => 'nullable|string|max:64']);

        return $this->run($request, (int) $sale->company_id, null, function ($session) use ($sale, $request) {
            $total = $this->sales->liquidate($sale, (int) $request->user()->id, $session);

            return ['message' => 'Liquidación procesada por ' . \App\Support\Currency::format($total, $sale->company) . '. Todas las cuotas han sido canceladas.'];
        });
    }

    /**
     * POST /sales/{sale}/return { return_reason, return_notes?, request_id? }
     * Requiere el permiso sales.delete o una autorización puntual vigente para esta venta.
     */
    public function processReturn(Request $request, Sale $sale): JsonResponse
    {
        $this->authorizeSale($request, $sale);
        $user = $request->user();

        $canDelete = $user->hasPermissionInCompany('sales.delete', $sale->company);
        $grant = $canDelete ? null : SaleDeleteGrant::activeFor($sale->id, (int) $user->id);
        if (!$canDelete && !$grant) {
            return response()->json(['message' => 'No tienes permiso para devolver esta venta.'], 403);
        }

        $validated = $request->validate([
            'return_reason' => 'required|string|max:255',
            'return_notes' => 'nullable|string|max:1000',
            'request_id' => 'nullable|string|max:64',
        ], SaleService::validationMessages());

        return $this->run($request, (int) $sale->company_id, 'Devolución procesada correctamente. El producto ha sido reingresado al inventario.',
            function ($session) use ($sale, $user, $validated, $grant) {
                $this->sales->processReturn($sale, (int) $user->id, $session, $validated['return_reason'], $validated['return_notes'] ?? null, $grant);

                return [];
            });
    }

    /**
     * Ejecuta una operación que requiere caja abierta, con protección contra doble envío
     * (request_id → 409) y errores en JSON. La acción recibe la sesión de caja y devuelve
     * datos extra para la respuesta (puede incluir 'message').
     */
    protected function run(Request $request, int $companyId, ?string $message, \Closure $action): JsonResponse
    {
        $userId = (int) $request->user()->id;
        if ($cashError = $this->payments->getCashSessionError($userId, $companyId)) {
            return response()->json(['message' => $cashError, 'code' => 'no_open_cash_session'], 422);
        }
        $session = $this->payments->resolveOpenCashSession($userId, $companyId);

        $requestId = trim((string) $request->input('request_id', ''));
        $lockKey = $requestId !== '' ? 'api-sale:' . $userId . ':' . $requestId : null;
        if ($lockKey && !Cache::add($lockKey, true, now()->addMinutes(10))) {
            return response()->json(['message' => 'Esta operación ya fue enviada. Revisa la venta antes de intentarlo de nuevo.', 'code' => 'duplicate_request'], 409);
        }

        try {
            $result = $action($session);
        } catch (SaleException $e) {
            if ($lockKey) Cache::forget($lockKey);
            return response()->json(array_filter([
                'message' => $e->getMessage(),
                'errors' => $e->field ? [$e->field => [$e->getMessage()]] : null,
            ]), 422);
        } catch (\Throwable $e) {
            if ($lockKey) Cache::forget($lockKey);
            Log::error('API: error en venta', ['route' => $request->path(), 'message' => $e->getMessage()]);
            return response()->json(['message' => 'No fue posible completar la operación. Intenta nuevamente.'], 500);
        }

        return response()->json(array_merge(['message' => $message ?? 'Operación registrada.'], $result), 201);
    }

    /**
     * Verifica que el usuario tenga acceso a la venta.
     *
     * Aborta con 403 si la venta no pertenece a la empresa del token.
     *
     * @param  Request  $request
     * @param  Sale     $sale
     */
    protected function authorizeSale(Request $request, Sale $sale): void
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && $sale->company_id !== $company?->id) {
            abort(403, 'No tienes acceso a esta venta.');
        }

        if ($user->is_super_admin && $company && $sale->company_id !== $company->id) {
            abort(403, 'La venta no pertenece a la empresa del token.');
        }
    }
}
