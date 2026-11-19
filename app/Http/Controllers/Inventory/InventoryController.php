<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CreditCategory;
use App\Models\InventoryMovement;
use App\Models\Loan;
use App\Models\LoanCollateral;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\MovementImage;
use App\Models\Personal;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        $branchesQuery = Branch::where('active', true)->orderBy('name');
        if (!$user->is_super_admin) {
            $branchesQuery->where('company_id', $company?->id);
        }
        $branches = $branchesQuery->get();

        // 0 = all branches (TODOS); any positive int = specific branch
        $selectedBranchId = $request->has('sucursal_id') ? $request->integer('sucursal_id') : 0;

        if ($selectedBranchId && $branches->isNotEmpty() && !$branches->pluck('id')->contains($selectedBranchId)) {
            $selectedBranchId = 0;
        }

        $selectedBranch = $selectedBranchId ? $branches->firstWhere('id', $selectedBranchId) : null;
        $selectedWarehouseId = $request->integer('warehouse_id');

        $warehousesQuery = Warehouse::query()->where('active', true)->orderBy('name');
        if (!$user->is_super_admin) {
            $warehousesQuery->where('company_id', $company?->id);
        }
        if ($selectedBranch?->warehouse_id) {
            $warehousesQuery->where('id', $selectedBranch->warehouse_id);
        }
        $warehouses = $warehousesQuery->get();

        if ($selectedWarehouseId && $warehouses->isNotEmpty() && !$warehouses->pluck('id')->contains($selectedWarehouseId)) {
            $selectedWarehouseId = null;
        }

        $q = trim((string) $request->get('q', ''));
        $statuses = array_filter((array) $request->get('statuses', []), fn($v) => $v !== '');
        $categoryId = $request->integer('category_id');

        // has_stock: '1' = con stock (default), '0' = sin stock, '' = todos
        $hasStock = $request->has('has_stock') ? (string) $request->get('has_stock') : '1';

        // qty_gt (solo super admin): productos cuya cantidad total en el almacén sea MAYOR a este número (1, 3, 5, 10).
        $qtyGt = $user->is_super_admin && in_array((string) $request->get('qty_gt'), ['1', '3', '5', '10'], true) ? (int) $request->get('qty_gt') : null;

        // movement_types[]: loan, purchase, manual, sale
        $movementTypes = $request->get('movement_types', []);
        if (is_string($movementTypes)) {
            $movementTypes = array_filter(explode(',', $movementTypes));
        }
        $movementTypes = array_values(array_filter((array) $movementTypes));

        $categoriesQuery = CreditCategory::where('active', true)->orderBy('name');
        if (!$user->is_super_admin) {
            $categoriesQuery->where('company_id', $company?->id);
        }
        $categories = $categoriesQuery->get();

        $stockQuery = InventoryMovement::query()
            ->join('products', 'products.id', '=', 'inventory_movements.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_movements.warehouse_id')
            ->leftJoin('credit_categories', 'credit_categories.id', '=', 'products.credit_category_id')
            ->select([
                'inventory_movements.product_id',
                'inventory_movements.warehouse_id',
                'products.name as product_name',
                'products.sku',
                'products.code as product_code',
                'products.active as product_active',
                'products.price as price',
                'credit_categories.name as category_name',
                'credit_categories.color as category_color',
                'warehouses.name as warehouse_name',
                DB::raw("SUM(CASE WHEN inventory_movements.type = 'in' THEN inventory_movements.quantity ELSE -inventory_movements.quantity END) as stock"),
                DB::raw('MAX(inventory_movements.movement_date) as last_movement_at'),
            ])
            ->whereNull('inventory_movements.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereNull('warehouses.deleted_at')
            ->groupBy(
                'inventory_movements.product_id',
                'inventory_movements.warehouse_id',
                'products.name',
                'products.sku',
                'products.code',
                'products.active',
                'products.price',
                'credit_categories.name',
                'credit_categories.color',
                'warehouses.name'
            );

        if (!$user->is_super_admin) {
            $stockQuery->where('inventory_movements.company_id', $company?->id);
        }

        if ($selectedBranchId) {
            $stockQuery->where('inventory_movements.branch_id', $selectedBranchId);
        }

        if ($selectedWarehouseId) {
            $stockQuery->where('inventory_movements.warehouse_id', $selectedWarehouseId);
        }

        if ($q !== '') {
            // También por código de producto: "PR-00031", "pr31", "PD-12", "CO7" o parte del código.
            $code = \App\Models\Product::codeFromSearch($q);
            $stockQuery->where(function ($sub) use ($q, $code) {
                $sub->where('products.name', 'like', "%{$q}%")
                    ->orWhere('products.sku', 'like', "%{$q}%")
                    ->orWhere('products.code', 'like', "%{$q}%");
                if ($code !== null) {
                    $sub->orWhere('products.code', $code);
                }
            });
        }

        if ($categoryId) {
            $stockQuery->where('products.credit_category_id', $categoryId);
        }

        if (!empty($movementTypes)) {
            $stockQuery->whereIn('inventory_movements.product_id', function ($sub) use ($movementTypes, $company, $user) {
                $sub->select('product_id')
                    ->from('inventory_movements')
                    ->whereNull('deleted_at')
                    ->when(!$user->is_super_admin, fn ($q) => $q->where('company_id', $company?->id))
                    ->where(function ($q) use ($movementTypes) {
                        foreach ($movementTypes as $mt) {
                            match ($mt) {
                                'loan'     => $q->orWhere('reference', 'LIKE', 'LOAN-RET-%'),
                                'purchase' => $q->orWhere('reference', 'LIKE', 'COMPRA-%'),
                                'manual'   => $q->orWhere('reference', 'INGRESO-MANUAL'),
                                'sale'     => $q->orWhere('reference', 'LIKE', 'VENTA-%'),
                                default    => null,
                            };
                        }
                    });
            });
        }

        $items = collect($stockQuery->get());

        $collateralQuery = LoanCollateral::query()
            ->select([
                'product_id',
                'warehouse_id',
                DB::raw("SUM(CASE WHEN status = 'retained' THEN 1 ELSE 0 END) as retained_count"),
                DB::raw("SUM(CASE WHEN status = 'stored' THEN 1 ELSE 0 END) as stored_count"),
                DB::raw("SUM(CASE WHEN status = 'sellable' THEN 1 ELSE 0 END) as sellable_count"),
            ])
            ->whereNull('deleted_at')
            ->groupBy('product_id', 'warehouse_id');

        if (!$user->is_super_admin) {
            $collateralQuery->where('company_id', $company?->id);
        }

        if ($selectedBranchId) {
            $collateralQuery->where('branch_id', $selectedBranchId);
        }

        if ($selectedWarehouseId) {
            $collateralQuery->where('warehouse_id', $selectedWarehouseId);
        }

        $collateralMap = $collateralQuery->get()->keyBy(fn ($row) => $row->product_id . ':' . $row->warehouse_id);

        // Cada producto+almacén se divide en filas separadas (buckets) según el
        // destino de sus unidades: stock libre, en préstamo, en retención y a la
        // venta. Un producto "mixto" produce varias filas; uno puro, una sola.
        $rows = $items->flatMap(function ($item) use ($collateralMap) {
            $key = $item->product_id . ':' . $item->warehouse_id;
            $collateral = $collateralMap->get($key);

            $retainedCount = (int) ($collateral->retained_count ?? 0);
            $storedCount = (int) ($collateral->stored_count ?? 0);
            $sellableCount = (int) ($collateral->sellable_count ?? 0);
            $stock = (float) ($item->stock ?? 0);

            // Stock disponible = unidades no retenidas por un préstamo vigente.
            // 'sellable' (prenda de préstamo incumplido) ya NO está retenida: cuenta
            // como disponible, igual que en Ventas (SaleController resta solo
            // retained+stored). Así inventario y ventas muestran lo mismo.
            $heldCount = $retainedCount + $storedCount;
            $freeStock = max(0, $stock - $heldCount);

            $base = [
                'product_id' => (int) $item->product_id,
                'warehouse_id' => (int) $item->warehouse_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'code' => $item->product_code,
                'product_active' => (bool) $item->product_active,
                'price' => (float) $item->price,
                'category_name' => $item->category_name,
                'category_color' => $item->category_color,
                'warehouse_name' => $item->warehouse_name,
                'last_movement_at' => $item->last_movement_at,
                'product_total_stock' => round($stock, 2),
            ];

            $makeRow = fn (array $extra) => (object) array_merge($base, [
                'retained_count' => 0,
                'stored_count' => 0,
                'sellable_count' => 0,
            ], $extra);

            // Sin stock → una sola fila
            if ($stock <= 0) {
                return [$makeRow([
                    'qty' => 0, 'state' => 'out',
                    'state_label' => 'Sin stock', 'state_color' => 'secondary',
                ])];
            }

            $rowsOut = [];

            if ($freeStock > 0) {
                $rowsOut[] = $makeRow([
                    'qty' => round($freeStock, 2), 'state' => 'available',
                    'state_label' => 'Disponible', 'state_color' => 'success',
                ]);
            }
            if ($retainedCount > 0) {
                $rowsOut[] = $makeRow([
                    'qty' => $retainedCount, 'state' => 'retained',
                    'state_label' => 'En prestamo', 'state_color' => 'info',
                    'retained_count' => $retainedCount,
                ]);
            }
            if ($storedCount > 0) {
                $rowsOut[] = $makeRow([
                    'qty' => $storedCount, 'state' => 'stored',
                    'state_label' => 'En retencion', 'state_color' => 'warning',
                    'stored_count' => $storedCount,
                ]);
            }
            return $rowsOut;
        });

        // has_stock filter: '1' = solo con stock, '0' = solo sin stock, '' = todos
        if ($hasStock === '1') {
            $rows = $rows->filter(fn ($row) => $row->qty > 0)->values();
        } elseif ($hasStock === '0') {
            $rows = $rows->filter(fn ($row) => $row->qty <= 0)->values();
        }

        // Claves (product_id:warehouse_id:estado de la fila) de colateral de préstamos eliminados pendiente de
        // regularizar, para marcar la fila y para el filtro "Por regularizar". La prenda 'sellable' (a la venta)
        // se muestra en la fila 'available'.
        $orphanKeys = $this->orphanedCollateralsQuery($user, $company)
            ->get(['product_id', 'warehouse_id', 'status'])
            ->map(fn ($c) => $c->product_id . ':' . $c->warehouse_id . ':' . ($c->status === 'sellable' ? 'available' : $c->status))
            ->unique()
            ->values();

        $onlyRegularize = $request->boolean('regularize');
        if ($onlyRegularize) {
            $rows = $rows->filter(fn ($row) => $orphanKeys->contains($row->product_id . ':' . $row->warehouse_id . ':' . $row->state))->values();
        }

        if ($qtyGt !== null) {
            $rows = $rows->filter(fn ($row) => (float) $row->product_total_stock > $qtyGt)->values();
        }

        // Unidades por estado con los demás filtros (sin el de estados): se muestran en cada botón de Estado.
        $unitsOf = fn ($collection) => (float) $collection->sum(fn ($row) => max(0, (float) $row->qty));
        $qtyByState = $rows->groupBy('state')->map($unitsOf);

        if (!empty($statuses)) {
            $rows = $rows->filter(fn ($row) => in_array($row->state, $statuses))->values();
        }

        // Paginación manual (las filas se calculan en memoria por buckets).
        $perPage = 20;
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage('page');
        $allRows = $rows->values();

        // Unidades de lo filtrado (todas las páginas, todos los filtros incluido el de estados).
        $qtyTotals = [
            'rows' => $allRows->count(),
            'total' => $unitsOf($allRows),
            'by_state' => $qtyByState,
        ];
        $rows = new \Illuminate\Pagination\LengthAwarePaginator(
            $allRows->forPage($page, $perPage)->values(),
            $allRows->count(),
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        // Inventory value totals (across all filters, per company)
        $valueQuery = InventoryMovement::query()
            ->join('products', 'products.id', '=', 'inventory_movements.product_id')
            ->whereNull('inventory_movements.deleted_at')
            ->whereNull('products.deleted_at')
            ->selectRaw("
                products.cost,
                products.price,
                SUM(CASE WHEN inventory_movements.type = 'in'
                    THEN inventory_movements.quantity
                    ELSE -inventory_movements.quantity END) as stock
            ")
            ->groupBy('inventory_movements.product_id', 'products.cost', 'products.price');

        if (!$user->is_super_admin) {
            $valueQuery->where('inventory_movements.company_id', $company?->id);
        }

        $valueRows = $valueQuery->get();
        $inventoryValueCost  = $valueRows->filter(fn($r) => $r->stock > 0)->sum(fn($r) => $r->stock * $r->cost);
        $inventoryValuePrice = $valueRows->filter(fn($r) => $r->stock > 0)->sum(fn($r) => $r->stock * $r->price);
        $inventoryItemCount  = $valueRows->filter(fn($r) => $r->stock > 0)->count();


        return view('inventory.index', [
            'rows' => $rows,
            'branches' => $branches,
            'selectedBranchId' => $selectedBranchId,
            'warehouses' => $warehouses,
            'selectedWarehouseId' => $selectedWarehouseId,
            'categories' => $categories,
            'selectedCategoryId' => $categoryId,
            'statuses' => $statuses,
            'q' => $q,
            'hasStock' => $hasStock,
            'qtyGt' => $qtyGt,
            'qtyTotals' => $qtyTotals,
            // Solo el super admin ve el aviso y el botón "Separar productos".
            'sharedPreview' => $user->is_super_admin ? app(\App\Services\ProductSplitService::class)->preview() : null,
            'movementTypes' => $movementTypes,
            'inventoryValueCost'  => $inventoryValueCost,
            'inventoryValuePrice' => $inventoryValuePrice,
            'inventoryItemCount'  => $inventoryItemCount,
            'orphanKeys'          => $orphanKeys,
            'onlyRegularize'      => $onlyRegularize,
        ]);
    }

    /**
     * Actualiza el precio de venta de un producto (edición inline vía AJAX).
     */
    public function updatePrice(Request $request, Product $product)
    {
        $user = auth()->user();

        if (!$user->is_super_admin && $product->company_id !== $user->getCurrentCompany()?->id) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 403);
        }

        $validated = $request->validate([
            'price' => 'required|numeric|min:0|max:99999999.99',
        ], [
            'price.required' => 'Ingresa el precio de venta.',
            'price.numeric' => 'El precio debe ser un número.',
            'price.min' => 'El precio no puede ser negativo.',
            'price.max' => 'El precio es demasiado alto.',
        ]);

        $product->update(['price' => round((float) $validated['price'], 2)]);

        return response()->json([
            'success' => true,
            'price'   => number_format((float) $product->price, 2, '.', ''),
        ]);
    }

    /* ── REGULARIZACIÓN DE COLATERAL HUÉRFANO ─────────────────────── */

    /**
     * Colaterales activos cuyo préstamo fue ELIMINADO (soft-delete): quedaron sin
     * liberar y sin salida de inventario, por lo que inflan el stock. El colateral
     * en sí no está soft-deleted, solo su préstamo, así que la query normal lo halla.
     */
    private function orphanedCollateralsQuery($user, $company, ?int $productId = null, ?int $warehouseId = null)
    {
        return LoanCollateral::query()
            ->whereIn('status', ['retained', 'stored', 'sellable'])
            ->when(!$user->is_super_admin, fn ($q) => $q->where('company_id', $company?->id))
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereHas('loan', fn ($q) => $q->onlyTrashed());
    }

    /**
     * Genera la salida de inventario y libera el colateral de los préstamos ya
     * eliminados (regularización de datos históricos). Idempotente.
     */
    public function regularizeOrphans(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        // Opcional: acotar a un producto/almacén (desde el detalle de producto).
        $productId = $request->integer('product_id') ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $orphans = $this->orphanedCollateralsQuery($user, $company, $productId, $warehouseId)
            ->with(['loan' => fn ($q) => $q->onlyTrashed(), 'product'])
            ->get();

        $fixed = 0;

        DB::transaction(function () use ($orphans, &$fixed) {
            foreach ($orphans as $col) {
                // Guard de idempotencia: no duplicar la salida si ya existe.
                $already = InventoryMovement::where('reference', 'LOAN-DEL-' . $col->loan_id)->exists();

                if (!$already) {
                    InventoryMovement::create([
                        'company_id'    => $col->company_id,
                        'warehouse_id'  => $col->warehouse_id,
                        'branch_id'     => $col->branch_id,
                        'product_id'    => $col->product_id,
                        'user_id'       => auth()->id(),
                        'type'          => 'out',
                        'quantity'      => 1,
                        'unit_cost'     => $col->product?->cost ?? 0,
                        'reference'     => 'LOAN-DEL-' . $col->loan_id,
                        'notes'         => 'Regularización: salida por eliminación del préstamo PR-'
                            . str_pad((string) ($col->loan?->numero ?? $col->loan_id), 5, '0', STR_PAD_LEFT),
                        'movement_date' => now(),
                    ]);
                }

                $col->update(['status' => 'released', 'released_at' => now()]);
                $fixed++;
            }
        });

        return back()->with('success', $fixed > 0
            ? "Se regularizaron {$fixed} artículo(s) de préstamos eliminados."
            : 'No hay inventario pendiente de regularizar.');
    }

    /* ── PRODUCT DETAIL ─────────────────────── */

    public function showProduct(Request $request, int $productId, int $warehouseId)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        $product   = Product::with('creditCategory')->findOrFail($productId);
        $warehouse = Warehouse::findOrFail($warehouseId);

        if (!$user->is_super_admin && $product->company_id !== $company?->id) {
            abort(403);
        }

        // Current stock (total)
        $stock = (float) (InventoryMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereNull('deleted_at')
            ->selectRaw("SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as total")
            ->value('total') ?? 0);

        // Collateral breakdown
        $collaterals = LoanCollateral::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereNull('deleted_at')
            ->when(!$user->is_super_admin, fn ($q) => $q->where('company_id', $company?->id))
            // Solo garantías activas (las liberadas/vendidas ya no ocupan stock).
            ->whereIn('status', ['retained', 'stored', 'sellable'])
            // Incluir préstamos eliminados (soft-deleted): la garantía puede seguir
            // apuntando a un préstamo eliminado y debe mostrarse igualmente.
            ->with(['loan' => fn ($q) => $q->withTrashed()->with('client')])
            ->get();

        // Garantías cuyo préstamo fue eliminado: pendientes de regularizar.
        $orphanCount = $collaterals->filter(fn ($c) => $c->loan && $c->loan->trashed())->count();

        $retainedCount = $collaterals->where('status', 'retained')->count();
        $storedCount   = $collaterals->where('status', 'stored')->count();
        $sellableCount = $collaterals->where('status', 'sellable')->count();
        // 'sellable' ya no retiene la unidad: cuenta como disponible (igual que en Ventas).
        $freeStock     = max(0, $stock - ($retainedCount + $storedCount));

        // Movement history paginated
        $movements = InventoryMovement::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereNull('deleted_at')
            ->with(['user'])
            ->orderByDesc('movement_date')
            ->paginate(30);

        // Build numero maps to display formatted codes instead of raw IDs
        $movItems = collect($movements->items());

        $extractId = fn (string $prefix, string $ref) => str_starts_with($ref, $prefix)
            ? ((int) substr($ref, strlen($prefix)) ?: null)
            : null;

        $saleIds = $movItems->map(fn ($m) =>
            $extractId('VENTA-', $m->reference ?? '') ?? $extractId('DEVOLUCION-', $m->reference ?? '')
        )->filter()->unique()->values();

        $purchaseIds = $movItems->map(fn ($m) =>
            $extractId('COMPRA-', $m->reference ?? '')
        )->filter()->unique()->values();

        $loanIds = $movItems->map(fn ($m) =>
            $extractId('LOAN-RET-', $m->reference ?? '')
                ?? $extractId('LOAN-REL-', $m->reference ?? '')
                ?? $extractId('LOAN-DEL-', $m->reference ?? '')
        )->filter()->unique()->values();

        // withTrashed: un movimiento puede referenciar un registro ya eliminado;
        // igual debe mostrar su código (numero) en vez del ID crudo.
        $salesNumeroMap    = $saleIds->isNotEmpty()
            ? Sale::withTrashed()->whereIn('id', $saleIds)->pluck('numero', 'id')
            : collect();
        $purchasesNumeroMap = $purchaseIds->isNotEmpty()
            ? Purchase::withTrashed()->whereIn('id', $purchaseIds)->pluck('numero', 'id')
            : collect();
        $loansNumeroMap    = $loanIds->isNotEmpty()
            ? Loan::withTrashed()->whereIn('id', $loanIds)->pluck('numero', 'id')
            : collect();

        return view('inventory.product-detail', compact(
            'product', 'warehouse', 'stock', 'freeStock',
            'retainedCount', 'storedCount', 'sellableCount', 'orphanCount',
            'collaterals', 'movements',
            'salesNumeroMap', 'purchasesNumeroMap', 'loansNumeroMap'
        ));
    }

    /* ── INGRESAR PRODUCTO (formulario único: cada producto es un artículo único, cantidad 1) ── */

    /**
     * Sucursales donde el usuario puede ingresar productos: las activas de la empresa; el personal que no es
     * gerente, solo la suya.
     */
    private function entryBranches($user, $company)
    {
        $branches = Branch::where('company_id', $company->id)->where('active', true)->orderBy('name')->get();

        if (!$user->is_super_admin) {
            $personal = Personal::where('user_id', $user->id)->first();
            if ($personal && !$personal->isManager() && $personal->branch_id) {
                $branches = $branches->where('id', $personal->branch_id)->values();
            }
        }

        return $branches;
    }

    public function createEntry(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return redirect()->route('select-company')->withErrors(['error' => 'Selecciona una empresa.']);
        }

        $branches = $this->entryBranches($user, $company);

        $defaultBranchId = (int) old('branch_id', $request->integer('sucursal_id'));
        // Desde la ficha de un almacén llega warehouse_id: se elige la sucursal de ese almacén.
        if (!$defaultBranchId && $request->filled('warehouse_id')) {
            $defaultBranchId = (int) $branches->firstWhere('warehouse_id', $request->integer('warehouse_id'))?->id;
        }
        if (!$branches->contains('id', $defaultBranchId)) {
            $defaultBranchId = $branches->count() === 1 ? (int) $branches->first()->id : 0;
        }

        $categories = CreditCategory::where('company_id', $company->id)->where('active', true)->orderBy('name')->get();

        // Código sugerido: el siguiente PD- de la empresa (se puede cambiar).
        $nextProductCode = Product::nextPdCode($company->id);

        return view('inventory.stock-entry', compact('company', 'branches', 'defaultBranchId', 'categories', 'nextProductCode'));
    }

    public function storeEntry(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return redirect()->route('select-company')->withErrors(['error' => 'Selecciona una empresa.']);
        }

        if ($limitError = $this->planLimitError((int) $company->id, ['products'])) {
            return back()->withInput()->withErrors(['error' => $limitError]);
        }

        $validated = $request->validate([
            'branch_id' => 'required|integer',
            'credit_category_id' => ['required', Rule::exists('credit_categories', 'id')->where('company_id', $company->id)],
            'product_name' => 'required|string|max:255',
            // Vacío = el siguiente PD- sugerido; no se repite en la sucursal.
            'product_code' => 'nullable|string|max:30',
            'product_brand' => 'nullable|string|max:255',
            'product_model_year' => 'nullable|string|max:10',
            'product_plate' => 'nullable|string|max:30',
            'product_chassis' => 'nullable|string|max:80',
            'product_serial_number' => 'nullable|string|max:80',
            'product_item_type' => 'nullable|string|max:100',
            'product_karat' => 'nullable|string|max:10',
            'product_material_type' => 'nullable|string|max:100',
            'product_item_model' => 'nullable|string|max:100',
            'product_description' => 'nullable|string|max:2000',
            'unit_cost' => 'required|numeric|min:0',
            'product_price' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'movement_date' => 'required|date',
            'images' => 'nullable|array|max:20',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], [
            'branch_id.required' => 'Elige la sucursal donde entra el producto.',
            'branch_id.integer' => 'Elige la sucursal donde entra el producto.',
            'credit_category_id.required' => 'Elige la categoría del producto.',
            'credit_category_id.exists' => 'La categoría elegida no es válida.',
            'product_name.required' => 'Escribe el nombre del producto.',
            'product_name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'product_code.max' => 'El código no puede tener más de 30 caracteres.',
            'unit_cost.required' => 'Indica el costo del producto (lo que se pagó por él; 0 si no costó nada).',
            'unit_cost.numeric' => 'El costo debe ser un número.',
            'unit_cost.min' => 'El costo no puede ser negativo.',
            'product_price.numeric' => 'El precio de venta debe ser un número.',
            'product_price.min' => 'El precio de venta no puede ser negativo.',
            'movement_date.required' => 'Indica la fecha del ingreso.',
            'movement_date.date' => 'La fecha del ingreso no es válida.',
            'images.max' => 'Puedes subir hasta 20 fotos.',
            'images.*.image' => 'Cada archivo debe ser una imagen.',
            'images.*.mimes' => 'Las fotos deben ser JPG, PNG o WebP.',
            'images.*.max' => 'Cada foto puede pesar hasta 5 MB.',
            'max' => 'El valor es demasiado largo.',
            'string' => 'El valor no es válido.',
        ]);

        // La sucursal debe ser una de las permitidas (el personal que no es gerente, solo la suya).
        $branch = $this->entryBranches($user, $company)->firstWhere('id', (int) $validated['branch_id']);
        if (!$branch) {
            return back()->withInput()->withErrors(['branch_id' => 'No puedes ingresar productos en esa sucursal.']);
        }

        // Cada sucursal tiene su almacén: el producto entra ahí.
        $warehouse = $branch->warehouse_id
            ? Warehouse::where('id', $branch->warehouse_id)->where('company_id', $company->id)->first()
            : null;
        if (!$warehouse) {
            return back()->withInput()->withErrors(['branch_id' => "La sucursal {$branch->name} no tiene almacén asignado. Asígnale uno en Sucursales."]);
        }

        $productCode = Product::normalizeCode($validated['product_code'] ?? null) ?? Product::nextPdCode($company->id);
        if (Product::codeTaken($company->id, $branch->id, $productCode)) {
            return back()->withInput()->withErrors(['product_code' => "El código {$productCode} ya está en uso en esta sucursal."]);
        }

        try {
            DB::beginTransaction();

            $cost = (float) $validated['unit_cost'];

            $product = Product::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'code' => $productCode,
                'credit_category_id' => $validated['credit_category_id'],
                'name' => $validated['product_name'],
                'sku' => Product::makeSku($validated['product_name']),
                'brand' => $validated['product_brand'] ?? null,
                'model_year' => $validated['product_model_year'] ?? null,
                'plate' => $validated['product_plate'] ?? null,
                'chassis' => $validated['product_chassis'] ?? null,
                'serial_number' => $validated['product_serial_number'] ?? null,
                'item_type' => $validated['product_item_type'] ?? null,
                'karat' => $validated['product_karat'] ?? null,
                'material_type' => $validated['product_material_type'] ?? null,
                'item_model' => $validated['product_item_model'] ?? null,
                'description' => $validated['product_description'] ?? null,
                'cost' => $cost,
                'price' => $validated['product_price'] ?? 0,
                'active' => true,
            ]);

            $movement = InventoryMovement::create([
                'company_id' => $company->id,
                'warehouse_id' => $warehouse->id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'type' => 'in',
                'quantity' => 1, // un producto por artículo
                'unit_cost' => $cost,
                'reference' => $validated['reference'] ?? 'INGRESO-MANUAL',
                'notes' => $validated['notes'] ?? null,
                'movement_date' => $validated['movement_date'],
            ]);

            // Fotos del artículo
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    if (!$image) continue;
                    $path = $image->store('movement-images/movement-' . $movement->id, 'public');
                    MovementImage::create([
                        'inventory_movement_id' => $movement->id,
                        'path' => $path,
                        'original_name' => $image->getClientOriginalName(),
                        'mime_type' => $image->getClientMimeType(),
                        'size_bytes' => $image->getSize(),
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('inventory.index', ['sucursal_id' => $branch->id])
                ->with('success', "Producto {$product->code} — {$product->name} ingresado al inventario de {$branch->name}.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al ingresar producto', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible ingresar el producto. Intenta de nuevo.']);
        }
    }
}
