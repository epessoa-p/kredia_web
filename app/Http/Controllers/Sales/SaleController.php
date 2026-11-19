<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Client;
use App\Models\CreditCategory;
use App\Models\InventoryMovement;
use App\Models\LoanCollateral;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleDeleteGrant;
use App\Models\SaleImage;
use App\Models\SaleInstallment;
use App\Services\SaleException;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-permission:sales.view');
    }

    /* ── INDEX ─────────────────────────────── */

    public function index(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        $branchesQuery = Branch::where('active', true)->orderBy('name');
        if (!$user->is_super_admin) {
            $branchesQuery->where('company_id', $company?->id);
        }
        $branches = $branchesQuery->get();

        $selectedBranchId = $request->integer('sucursal_id');

        // Restrict to personal's branch for non-managers
        if (!$user->is_super_admin) {
            $personal = Personal::where('user_id', $user->id)->first();
            if ($personal && !$personal->isManager() && $personal->branch_id) {
                $branches = $branches->where('id', $personal->branch_id)->values();
                $selectedBranchId = (int) $personal->branch_id;
            }
        }

        if ($selectedBranchId && $branches->isNotEmpty() && !$branches->pluck('id')->contains($selectedBranchId)) {
            $selectedBranchId = 0;
        }

        $hasActiveCashSession = $this->resolveOpenCashSession((int) $company?->id) !== null;

        $query = Sale::with(['client', 'product', 'creditCategory', 'branch', 'createdBy.personal', 'cashRegister'])
            ->withCount([
                'installments as installments_total_count',
                'installments as installments_paid_count' => fn ($q) => $q->where('status', SaleInstallment::STATUS_PAGADA),
            ])
            ->latest();

        if (!$user->is_super_admin && $company) {
            $query->where('company_id', $company->id);
        }
        if ($selectedBranchId) {
            $query->where('branch_id', $selectedBranchId);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('id', $q)
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%"));
            });
        }

        $status = $request->get('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $dateFrom = $request->get('date_from', '');
        $dateTo = $request->get('date_to', '');
        if ($dateFrom) {
            $query->whereDate('sale_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('sale_date', '<=', $dateTo);
        }

        $productId = $request->integer('product_id');
        if ($productId) {
            $query->where('product_id', $productId);
        }

        $personalId = $request->integer('personal_id');
        if ($personalId) {
            $query->whereHas('createdBy.personal', fn ($p) => $p->where('id', $personalId));
        }

        // Personal (con usuario) para el filtro de "registrado por".
        $personalsQuery = Personal::whereNotNull('user_id')->where('active', true)->orderBy('full_name');
        if (!$user->is_super_admin) {
            $personalsQuery->where('company_id', $company?->id);
        }
        $personals = $personalsQuery->get();

        $sales = $query->paginate(15)->withQueryString();

        // Ventas sobre las que el usuario tiene una autorización puntual vigente
        // (una sola query, evita N+1 en el listado).
        $grantedSaleIds = SaleDeleteGrant::active()
            ->where('granted_to', $user->id)
            ->pluck('sale_id')
            ->all();

        return view('sales.index', compact(
            'sales', 'branches', 'selectedBranchId',
            'q', 'status', 'dateFrom', 'dateTo', 'productId',
            'personals', 'personalId', 'grantedSaleIds',
            'hasActiveCashSession'
        ));
    }

    /* ── CREATE ────────────────────────────── */

    public function create(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return redirect()->route('select-company')->withErrors(['error' => 'Selecciona una empresa para registrar ventas.']);
        }

        $branches = $user->is_super_admin
            ? Branch::where('active', true)->orderBy('name')->get()
            : Branch::where('company_id', $company->id)->where('active', true)->orderBy('name')->get();

        $defaultBranchId = old('branch_id', $request->integer('branch_id') ?: $request->integer('sucursal_id'));
        if (empty($defaultBranchId) && $branches->isNotEmpty()) {
            $defaultBranchId = $branches->first()->id;
        }

        $clients = $user->is_super_admin
            ? Client::where('active', true)->orderBy('name')->get()
            : $company->clients()->where('active', true)->orderBy('name')->get();

        $activeCashSession = $this->resolveOpenCashSession((int) $company->id);
        $cashSessionError = $this->getCashSessionError((int) $company->id);

        $activeCashSession?->load('cashRegister.branch');
        $sessionBranch = $activeCashSession?->cashRegister?->branch;
        if ($sessionBranch) {
            $defaultBranchId = $sessionBranch->id;
        }
        // Almacén de la caja: solo se puede vender lo que está en él.
        $sessionWarehouseId = $sessionBranch?->warehouse_id;

        // Get available products for sale across all branches
        $availableProducts = $this->getAvailableProducts($company->id, $branches);

        // Map branches with their warehouse_id for JS filtering
        $branchesJson = $branches->map(fn ($b) => [
            'id' => $b->id,
            'name' => $b->name,
            'warehouse_id' => $b->warehouse_id,
        ]);

        return view('sales.create', compact(
            'company', 'clients', 'branches', 'defaultBranchId',
            'activeCashSession', 'cashSessionError', 'sessionBranch', 'sessionWarehouseId',
            'availableProducts', 'branchesJson'
        ));
    }

    public function quickCatalog(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return redirect()->route('select-company')->withErrors(['error' => 'Selecciona una empresa para continuar.']);
        }

        $branches = $user->is_super_admin
            ? Branch::where('active', true)->orderBy('name')->get()
            : Branch::where('company_id', $company->id)->where('active', true)->orderBy('name')->get();

        // Caja abierta → su almacén. Solo se vende lo de ese almacén.
        $activeCashSession = $this->resolveOpenCashSession((int) $company->id);
        $activeCashSession?->load('cashRegister.branch');
        $sessionBranch = $activeCashSession?->cashRegister?->branch;
        $sessionWarehouseId = $sessionBranch?->warehouse_id;
        $hasCash = (bool) $activeCashSession;

        $availableProducts = collect($this->getAvailableProducts($company->id, $branches))
            ->map(function (array $product) use ($sessionWarehouseId) {
                $product['own'] = $sessionWarehouseId && (int) $product['warehouse_id'] === (int) $sessionWarehouseId;
                $product['sale_url'] = route('sales.create', [
                    'branch_id' => $product['branch_id'],
                    'product_id' => $product['id'],
                ]);
                $product['search_text'] = mb_strtolower(implode(' ', array_filter([
                    $product['name'] ?? null,
                    $product['brand'] ?? null,
                    $product['branch_name'] ?? null,
                    $product['category_name'] ?? null,
                    $product['origin_label'] ?? null,
                    $product['description'] ?? null,
                    $product['plate'] ?? null,
                    $product['serial_number'] ?? null,
                ])));

                return $product;
            })
            ->sortBy([['branch_name', 'asc'], ['name', 'asc']])
            ->values();

        return view('sales.quick-catalog', [
            'quickSaleProducts' => $availableProducts,
            'hasCash' => $hasCash,
            'sessionBranchName' => $sessionBranch?->name,
        ]);
    }

    /* ── STORE ─────────────────────────────── */

    public function store(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return redirect()->route('select-company')->withErrors(['error' => 'Selecciona una empresa.']);
        }

        $cashError = $this->getCashSessionError((int) $company->id);
        if ($cashError) {
            return back()->withInput()->withErrors(['error' => $cashError]);
        }
        $activeCashSession = $this->resolveOpenCashSession((int) $company->id);

        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'product_id' => 'required|exists:products,id',
            'payment_type' => 'required|in:CONTADO,CREDITO',
            'client_id' => 'nullable|exists:clients,id',
            'client_name' => 'nullable|required_without:client_id|string|max:255',
            'client_id_number' => 'nullable|string|max:30',
            'client_phone' => 'nullable|string|max:20',
            'client_email' => 'nullable|email|max:255',
            'client_address' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'credit_additional_percentage' => [
                'nullable', 'numeric', 'max:1000',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('payment_type') === 'CREDITO' && (float) $value <= 0) {
                        $fail('El porcentaje adicional de crédito es obligatorio y debe ser mayor a 0 para ventas al crédito.');
                    }
                },
            ],
            'credit_additional_amount' => 'nullable|numeric|min:0',
            'down_payment' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'installments' => 'nullable|array|min:1',
            'installments.*.due_date' => 'required_if:payment_type,CREDITO|date',
            'installments.*.percentage' => 'required_if:payment_type,CREDITO|numeric|min:0.0001|max:100',
            'installments.*.amount' => 'required_if:payment_type,CREDITO|numeric|min:0.01',
            'images' => 'nullable|array|max:20',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], SaleService::validationMessages());

        try {
            $sale = app(SaleService::class)->create(
                $company,
                (int) $user->id,
                $activeCashSession,
                $validated,
                $request->file('images') ?? [],
            );
        } catch (SaleException $e) {
            return back()->withInput()->withErrors([$e->field ?? 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Error al registrar venta', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible registrar la venta. Intenta nuevamente.']);
        }

        return redirect()->route('sales.show', $sale)->with('success', 'Venta registrada exitosamente.');
    }

    /* ── SHOW ──────────────────────────────── */

    public function show(Sale $sale)
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $sale->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }

        $sale->load([
            'company', 'branch', 'warehouse', 'product.creditCategory',
            'client', 'createdBy', 'cashRegister', 'cashRegisterSession',
            'loanCollateral.loan', 'purchase', 'images', 'installments',
        ]);

        $company = $user->getCurrentCompany();

        // Autorización puntual vigente del usuario actual sobre esta venta.
        $myGrant = SaleDeleteGrant::activeFor($sale->id, $user->id)?->load('grantedBy.personal');

        // Autorizaciones vigentes otorgadas sobre esta venta (para listarlas/revocarlas).
        $activeGrants = SaleDeleteGrant::active()
            ->where('sale_id', $sale->id)
            ->with('grantedTo.personal')
            ->latest()
            ->get();

        // Personal (con usuario) al que se puede autorizar.
        $personalsQuery = Personal::whereNotNull('user_id')
            ->where('active', true)
            ->where('user_id', '!=', $user->id)
            ->orderBy('full_name');
        if (!$user->is_super_admin) {
            $personalsQuery->where('company_id', $company?->id);
        }
        $personals = $personalsQuery->get();

        return view('sales.show', compact('sale', 'myGrant', 'activeGrants', 'personals'));
    }

    /* ── RETURN ────────────────────────────── */

    public function processReturn(Request $request, Sale $sale)
    {
        $user    = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company || ($sale->company_id !== $company->id && !$user->is_super_admin)) {
            abort(403);
        }

        // Puede eliminar quien tenga el permiso por su cargo, o quien tenga una
        // autorización puntual vigente para ESTA venta (un solo uso, 24h).
        $canDelete = $user->hasPermissionInCompany('sales.delete', $company);
        $grant = $canDelete ? null : SaleDeleteGrant::activeFor($sale->id, $user->id);

        if (!$canDelete && !$grant) {
            abort(403, 'No tienes permiso para eliminar esta venta.');
        }

        if (!$sale->isReturnable()) {
            return back()->withErrors(['error' => 'Esta venta no puede ser devuelta (estado: ' . (Sale::STATUS_LABELS[$sale->status] ?? $sale->status) . ').']);
        }

        $validated = $request->validate([
            'return_reason' => 'required|string|max:255',
            'return_notes'  => 'nullable|string|max:1000',
        ], SaleService::validationMessages());

        // Necesitamos una caja abierta para registrar el egreso
        $activeCashSession = $this->resolveOpenCashSession((int) $company->id);
        if (!$activeCashSession) {
            return back()->withErrors(['error' => 'Debes tener una caja abierta para procesar la devolución.']);
        }

        try {
            app(SaleService::class)->processReturn(
                $sale,
                (int) $user->id,
                $activeCashSession,
                $validated['return_reason'],
                $validated['return_notes'] ?? null,
                $grant,
            );
        } catch (SaleException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Error al procesar devolución de venta', ['sale_id' => $sale->id, 'message' => $e->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible procesar la devolución. Intenta nuevamente.']);
        }

        return back()->with('success', 'Devolución procesada correctamente. El producto ha sido reingresado al inventario.');
    }

    /* ── AUTORIZACIÓN POR ÚNICA VEZ ───────── */

    /**
     * Autoriza a otro usuario a eliminar ESTA venta: un solo uso, 24 horas.
     * No requiere solicitud previa del autorizado.
     */
    public function storeDeleteGrant(Request $request, Sale $sale)
    {
        $user    = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company || ($sale->company_id !== $company->id && !$user->is_super_admin)) {
            abort(403);
        }

        if (!$sale->isReturnable()) {
            return back()->withErrors(['error' => 'Esta venta ya no se puede eliminar; no tiene sentido autorizar.']);
        }

        $validated = $request->validate([
            'personal_id' => 'required|integer|exists:personals,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $personal = Personal::find($validated['personal_id']);

        if (!$personal || !$personal->user_id) {
            return back()->withErrors(['personal_id' => 'Ese personal no tiene un usuario asociado; no puede recibir la autorización.']);
        }

        if (!$user->is_super_admin && $personal->company_id !== $company->id) {
            abort(403);
        }

        if ((int) $personal->user_id === (int) $user->id) {
            return back()->withErrors(['personal_id' => 'No necesitas autorizarte a ti mismo.']);
        }

        // Si ya tiene una vigente para esta venta, se renueva en vez de duplicar.
        $existing = SaleDeleteGrant::activeFor($sale->id, (int) $personal->user_id);

        if ($existing) {
            $existing->update([
                'granted_by' => $user->id,
                'reason' => $validated['reason'] ?? null,
                'expires_at' => now()->addDay(),
            ]);
        } else {
            SaleDeleteGrant::create([
                'company_id' => $sale->company_id,
                'sale_id' => $sale->id,
                'granted_to' => $personal->user_id,
                'granted_by' => $user->id,
                'reason' => $validated['reason'] ?? null,
                'expires_at' => now()->addDay(),
            ]);
        }

        return back()->with('success', 'Autorizaste a ' . $personal->full_name . ' a eliminar esta venta. Vence en 24 horas.');
    }

    /** Revoca una autorización vigente. */
    public function revokeDeleteGrant(SaleDeleteGrant $grant)
    {
        $user    = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company || ($grant->company_id !== $company->id && !$user->is_super_admin)) {
            abort(403);
        }

        $grant->update(['revoked_at' => now()]);

        return back()->with('success', 'Autorización revocada.');
    }

    /* ── LIQUIDATION ──────────────────────── */

    public function processLiquidation(Request $request, Sale $sale)
    {
        $user    = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company || ($sale->company_id !== $company->id && !$user->is_super_admin)) {
            abort(403);
        }
        if (!$sale->isCredit()) {
            return back()->withErrors(['error' => 'Solo se puede liquidar ventas al crédito.']);
        }
        if ($sale->status !== Sale::STATUS_COMPLETED) {
            return back()->withErrors(['error' => 'Solo se pueden liquidar ventas con estado "Completada".']);
        }

        $cashError = $this->getCashSessionError((int) $company->id);
        if ($cashError) {
            return back()->withErrors(['error' => $cashError]);
        }
        $activeCashSession = $this->resolveOpenCashSession((int) $company->id);

        try {
            app(SaleService::class)->liquidate($sale, (int) $user->id, $activeCashSession);
        } catch (SaleException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Error al liquidar venta', ['sale_id' => $sale->id, 'message' => $e->getMessage()]);
            return back()->withErrors(['error' => 'No fue posible procesar la liquidación. Intenta nuevamente.']);
        }

        return redirect()->route('sales.show', $sale)
            ->with('success', 'Liquidación procesada. Todas las cuotas han sido canceladas.');
    }

    /* ── DESTROY ───────────────────────────── */

    public function destroy(Sale $sale)
    {
        $user = auth()->user();
        if (!$user->hasPermissionInCompany('sales.delete', $user->getCurrentCompany())) {
            abort(403, 'No tienes permiso para eliminar ventas.');
        }

        $sale->delete();

        return redirect()->route('sales.index')->with('success', 'Venta eliminada correctamente.');
    }

    /* ── HELPERS ───────────────────────────── */

    private function getAvailableProducts(int $companyId, $branches): array
    {
        return app(SaleService::class)->availableProducts($companyId, $branches);
    }

    private function resolveOpenCashSession(int $companyId): ?CashRegisterSession
    {
        $personalRecord = Personal::where('user_id', auth()->id())->first();
        if (!$personalRecord) return null;

        $registerIds = CashRegister::where('assigned_personal_id', $personalRecord->id)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) return null;

        return CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->latest()
            ->first();
    }

    private function getCashSessionError(int $companyId): ?string
    {
        $personalRecord = Personal::where('user_id', auth()->id())->first();
        if (!$personalRecord) {
            return 'No tienes un registro de personal asociado. Contacta al administrador.';
        }

        $registerIds = CashRegister::where('assigned_personal_id', $personalRecord->id)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) {
            return 'No tienes una caja asignada. Contacta al administrador.';
        }

        $hasOpen = CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->exists();

        if (!$hasOpen) {
            return 'No hay ninguna caja abierta. Debes aperturar una caja antes de registrar ventas.';
        }

        return null;
    }
}
