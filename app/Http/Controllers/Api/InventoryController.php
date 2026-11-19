<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CreditCategory;
use App\Models\InventoryMovement;
use App\Models\MovementImage;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Controlador de inventario para la API.
 *
 * Gestiona el stock de productos: consulta de opciones de formulario,
 * listado de stock actual, detalle por producto y registro de ingresos.
 */
class InventoryController extends Controller
{
    /**
     * Retorna las opciones de formulario necesarias para el ingreso de stock.
     *
     * Devuelve listas de sucursales, almacenes, categorías y productos activos
     * filtrados por la empresa del token. Soporta búsqueda de productos por
     * nombre, SKU o marca, y limita la cantidad de productos retornados.
     *
     * @param  Request  $request  Parámetros: q (búsqueda de producto), product_limit (1-200, def. 100)
     * @return JsonResponse  200 con branches, warehouses, categories y products.
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && !$company) {
            return response()->json([
                'message' => 'No tienes empresa activa asignada en el token.',
            ], 403);
        }

        $branchesQuery = Branch::query()->where('active', true)->orderBy('name');
        $warehousesQuery = Warehouse::query()->where('active', true)->orderBy('name');
        $categoriesQuery = CreditCategory::query()->where('active', true)->orderBy('name');
        $productsQuery = Product::query()->where('active', true)->whereNull('deleted_at')->orderBy('name');

        if (!$user->is_super_admin) {
            $branchesQuery->where('company_id', $company?->id);
            $warehousesQuery->where('company_id', $company?->id);
            $categoriesQuery->where('company_id', $company?->id);
            $productsQuery->where('company_id', $company?->id);
        } elseif ($company) {
            $branchesQuery->where('company_id', $company->id);
            $warehousesQuery->where('company_id', $company->id);
            $categoriesQuery->where('company_id', $company->id);
            $productsQuery->where('company_id', $company->id);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $productsQuery->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('sku', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%");
            });
        }

        $productLimit = max(1, min(200, $request->integer('product_limit', 100)));

        return response()->json([
            // Cada sucursal tiene su almacén (warehouse_id): al ingresar stock basta con elegir la sucursal.
            'branches' => $branchesQuery->get(['id', 'company_id', 'warehouse_id', 'name', 'color']),
            'warehouses' => $warehousesQuery->get(['id', 'company_id', 'name', 'code']),
            // form: formulario del producto, como en préstamos y compras (1 vehículo, 2 joyas, 3 garrafas, 4 artículos).
            // icon: clase de Bootstrap Icons elegida en la categoría (o la sugerida por su nombre).
            'categories' => $categoriesQuery->get()
                ->map(fn (CreditCategory $cat) => $cat->only(['id', 'company_id', 'name', 'color'])
                    + ['icon' => $cat->icon_class, 'form' => self::categoryForm($cat->id)])
                ->values(),
            // Personal que no es gerente: solo ingresa stock en su sucursal.
            'locked_branch_id' => app(CatalogService::class)->lockedBranchId($user),
            // Código sugerido para un producto nuevo (se puede cambiar; no se repite en la sucursal).
            'next_product_code' => ($company?->id ?? $user->activeCompanies()->value('companies.id'))
                ? Product::nextPdCode((int) ($company?->id ?? $user->activeCompanies()->value('companies.id')))
                : null,
            'products' => $productsQuery->limit($productLimit)->get([
                'id',
                'company_id',
                'credit_category_id',
                'name',
                'sku',
                'brand',
                'item_model',
                'material_type',
                'cost',
                'price',
            ]),
        ]);
    }

    /**
     * Lista el stock actual agregado por producto, almacén y sucursal.
     *
     * Calcula el stock neto (entradas − salidas) agrupado por combinación
     * de producto/almacén/sucursal. Soporta filtros por producto, almacén,
     * sucursal y búsqueda por nombre/SKU. El resultado se ordena por fecha
     * de último movimiento descendente.
     *
     * @param  Request  $request  Parámetros: product_id, warehouse_id, branch_id, q, per_page (1-100, def. 15)
     * @return JsonResponse  200 con colección paginada de filas de stock.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        $query = InventoryMovement::query()
            ->join('products', 'products.id', '=', 'inventory_movements.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_movements.warehouse_id')
            ->leftJoin('branches', 'branches.id', '=', 'inventory_movements.branch_id')
            ->whereNull('inventory_movements.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereNull('warehouses.deleted_at')
            ->select([
                'inventory_movements.product_id',
                'inventory_movements.warehouse_id',
                'inventory_movements.branch_id',
                'products.name as product_name',
                'products.sku as product_sku',
                'products.code as product_code',
                'products.price as product_price',
                'products.cost as product_cost',
                'warehouses.name as warehouse_name',
                'branches.name as branch_name',
                'branches.color as branch_color',
                DB::raw("SUM(CASE WHEN inventory_movements.type = 'in' THEN inventory_movements.quantity ELSE -inventory_movements.quantity END) as stock"),
                DB::raw('MAX(inventory_movements.movement_date) as last_movement_at'),
            ])
            ->groupBy(
                'inventory_movements.product_id',
                'inventory_movements.warehouse_id',
                'inventory_movements.branch_id',
                'products.name',
                'products.sku',
                'products.code',
                'products.price',
                'products.cost',
                'warehouses.name',
                'branches.name',
                'branches.color'
            )
            ->orderByDesc('last_movement_at');

        if (!$user->is_super_admin) {
            $query->where('inventory_movements.company_id', $company?->id);
        } elseif ($company) {
            $query->where('inventory_movements.company_id', $company->id);
        }

        if ($request->filled('product_id')) {
            $query->where('inventory_movements.product_id', $request->integer('product_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('inventory_movements.warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('inventory_movements.branch_id', $request->integer('branch_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('products.credit_category_id', $request->integer('category_id'));
        }

        // Solo filas con stock disponible (sin las que quedaron en 0 por ventas o liberaciones).
        if ($request->boolean('in_stock')) {
            $query->havingRaw("SUM(CASE WHEN inventory_movements.type = 'in' THEN inventory_movements.quantity ELSE -inventory_movements.quantity END) > 0");
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            // También por código de producto: "PR-00031", "pr31", "PD-12", "CO7" o parte del código.
            $code = Product::codeFromSearch($q);
            $query->where(function ($subQuery) use ($q, $code) {
                $subQuery->where('products.name', 'like', "%{$q}%")
                    ->orWhere('products.sku', 'like', "%{$q}%")
                    ->orWhere('products.code', 'like', "%{$q}%");
                if ($code !== null) {
                    $subQuery->orWhere('products.code', $code);
                }
            });
        }

        $totals = $this->stockValue($query);

        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $stock = $query->paginate($perPage)->withQueryString();

        return response()->json(array_merge($stock->toArray(), ['totals' => $totals]));
    }

    /**
     * Valor del stock filtrado (solo unidades con stock positivo), separado como en las ventas
     * (SaleService::freeStock): "en garantía" = prendas de préstamos vigentes (retenidas o en mora, el
     * cliente aún puede recuperarlas) y "para la venta" = el resto (stock libre y prendas vendibles).
     * Costo = products.cost (monto prestado o de compra); venta = products.price. La ganancia solo
     * cuenta productos con precio de venta (los que no lo tienen se informan en missing_price).
     */
    protected function stockValue($query): array
    {
        $held = DB::table('loan_collaterals')
            ->whereIn('status', ['retained', 'stored'])
            ->whereNull('deleted_at')
            ->groupBy('product_id', 'warehouse_id')
            ->selectRaw('product_id, warehouse_id, COUNT(*) as held');

        $rows = DB::query()
            ->fromSub((clone $query)->reorder(), 's')
            ->leftJoinSub($held, 'h', fn ($j) => $j->on('h.product_id', '=', 's.product_id')->on('h.warehouse_id', '=', 's.warehouse_id'))
            ->where('s.stock', '>', 0)
            ->selectRaw('s.stock, LEAST(s.stock, COALESCE(h.held, 0)) as pledged,'
                . ' COALESCE(s.product_cost, 0) as cost, COALESCE(s.product_price, 0) as price');

        $sum = fn (string $units) => "COALESCE(SUM({$units}),0) as units_%1\$s,"
            . " COALESCE(SUM(({$units}) * cost),0) as cost_%1\$s,"
            . " COALESCE(SUM(CASE WHEN price > 0 THEN ({$units}) * price ELSE 0 END),0) as sale_%1\$s,"
            . " COALESCE(SUM(CASE WHEN price > 0 THEN ({$units}) * (price - cost) ELSE 0 END),0) as profit_%1\$s";

        $r = DB::query()->fromSub($rows, 'v')->selectRaw(
            sprintf($sum('stock'), 'all') . ', ' . sprintf($sum('stock - pledged'), 'free') . ', '
            . sprintf($sum('pledged'), 'pledged')
            . ', SUM(CASE WHEN price <= 0 THEN 1 ELSE 0 END) as missing_price'
        )->first();

        $block = fn (string $k) => [
            'units' => (float) $r->{"units_$k"},
            'cost' => round((float) $r->{"cost_$k"}, 2),
            'sale' => round((float) $r->{"sale_$k"}, 2),
            'profit' => round((float) $r->{"profit_$k"}, 2),
        ];

        return $block('all') + [
            'for_sale' => $block('free'),
            'pledged' => $block('pledged'),
            'missing_price' => (int) $r->missing_price,
        ];
    }

    /**
     * Retorna el detalle de stock de un producto específico.
     *
     * Incluye el stock total global del producto, el desglose por almacén/sucursal
     * y los últimos 50 movimientos con sus imágenes adjuntas.
     *
     * @param  Request  $request
     * @param  Product  $product
     * @return JsonResponse  200 con product, summary, stock_by_warehouse y recent_movements.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $stockByWarehouse = InventoryMovement::query()
            ->join('warehouses', 'warehouses.id', '=', 'inventory_movements.warehouse_id')
            ->leftJoin('branches', 'branches.id', '=', 'inventory_movements.branch_id')
            ->where('inventory_movements.product_id', $product->id)
            ->whereNull('inventory_movements.deleted_at')
            ->select([
                'inventory_movements.warehouse_id',
                'inventory_movements.branch_id',
                'warehouses.name as warehouse_name',
                'branches.name as branch_name',
                'branches.color as branch_color',
                DB::raw("SUM(CASE WHEN inventory_movements.type = 'in' THEN inventory_movements.quantity ELSE -inventory_movements.quantity END) as stock"),
                DB::raw('MAX(inventory_movements.movement_date) as last_movement_at'),
            ])
            ->groupBy(
                'inventory_movements.warehouse_id',
                'inventory_movements.branch_id',
                'warehouses.name',
                'branches.name',
                'branches.color'
            )
            ->orderBy('warehouses.name')
            ->get();

        $movements = InventoryMovement::with(['warehouse', 'branch', 'user', 'images'])
            ->where('product_id', $product->id)
            ->latest('movement_date')
            ->limit(50)
            ->get();

        return response()->json([
            'product' => $product,
            'summary' => [
                'stock_total' => (float) InventoryMovement::query()
                    ->where('product_id', $product->id)
                    ->whereNull('deleted_at')
                    ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0) as stock_total")
                    ->value('stock_total'),
            ],
            'stock_by_warehouse' => $stockByWarehouse,
            'recent_movements' => $movements,
            'abilities' => [
                'edit_price' => $this->canEditPrice($request, $product),
            ],
        ]);
    }

    /**
     * Registra un ingreso de stock y opcionalmente crea un producto nuevo.
     *
     * Si product_mode = 'new', crea el producto con los datos enviados y genera
     * un SKU automático. Si product_mode = 'existing', usa el producto existente.
     * Luego crea el movimiento de inventario de tipo 'in' y almacena las imágenes
     * adjuntas. Toda la operación se ejecuta dentro de una transacción de base de datos.
     *
     * @param  Request  $request  Ver reglas de validación en el método para el detalle de campos.
     * @return JsonResponse  201 con movement y product creados, o 403/500 en caso de error.
     */
    public function storeEntry(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && !$company) {
            return response()->json([
                'message' => 'No tienes empresa activa asignada en el token.',
            ], 403);
        }

        if ($company && ($limitError = $this->planLimitError((int) $company->id, ['products']))) {
            return response()->json(['message' => $limitError, 'code' => 'plan_limit_reached'], 403);
        }

        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            // Opcional: si no llega, se usa el almacén de la sucursal.
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'product_mode' => ['required', Rule::in(['new'])], // siempre un producto nuevo: cada artículo usado tiene su propio valor
            'product_id' => 'required_if:product_mode,existing|nullable|integer|exists:products,id',
            'credit_category_id' => 'required_if:product_mode,new|nullable|integer|exists:credit_categories,id',
            // Los obligatorios del producto dependen de la categoría: PurchaseService::validateProductFields.
            'product_name' => 'nullable|string|max:255',
            // Vacío = el siguiente PD- sugerido (next_product_code en /inventory/options).
            'product_code' => 'nullable|string|max:30',
            'product_brand' => 'nullable|string|max:255',
            'product_model_year' => 'nullable|integer|min:1900|max:2100',
            'product_plate' => 'nullable|string|max:30',
            'product_chassis' => 'nullable|string|max:80',
            'product_serial_number' => 'nullable|string|max:80',
            'product_item_type' => 'nullable|string|max:100',
            'product_karat' => 'nullable|string|max:10',
            'product_material_type' => 'nullable|string|max:100',
            'product_item_model' => 'nullable|string|max:100',
            'product_description' => 'nullable|string',
            'product_cost' => 'nullable|numeric|min:0',
            'product_price' => 'nullable|numeric|min:0',
            'quantity' => 'required|numeric|in:1', // cantidad siempre 1 (un producto por artículo)
            'unit_cost' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'movement_date' => 'required|date',
            'images' => 'nullable|array|max:20',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ], self::entryMessages());

        $branch = Branch::findOrFail($validated['branch_id']);

        // El personal que no es gerente solo ingresa stock en su sucursal.
        $lockedBranchId = app(CatalogService::class)->lockedBranchId($user);
        if ($lockedBranchId && (int) $branch->id !== $lockedBranchId) {
            return response()->json([
                'message' => 'Solo puedes registrar ingresos en tu sucursal.',
                'errors' => ['branch_id' => ['Solo puedes registrar ingresos en tu sucursal.']],
            ], 422);
        }

        // Cada sucursal tiene su almacén: si no se eligió uno, se usa el de la sucursal.
        $warehouseId = $validated['warehouse_id'] ?? $branch->warehouse_id;
        if (!$warehouseId) {
            return response()->json([
                'message' => 'La sucursal no tiene un almacén asignado. Asígnalo en la web (Administración › Sucursales).',
                'errors' => ['branch_id' => ['La sucursal no tiene un almacén asignado.']],
            ], 422);
        }
        $warehouse = Warehouse::findOrFail($warehouseId);
        $validated['warehouse_id'] = (int) $warehouse->id;

        // Producto nuevo: campos obligatorios según la categoría, igual que en compras y préstamos.
        if ($validated['product_mode'] === 'new') {
            $purchases = app(\App\Services\PurchaseService::class);
            if (!empty($validated['product_karat'])) {
                $validated['product_karat'] = strtoupper(trim((string) $validated['product_karat']));
            }
            try {
                $purchases->validateProductFields((int) $validated['credit_category_id'], $validated);
            } catch (\App\Services\PurchaseException $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => [($e->field ?? 'product_name') => [$e->getMessage()]],
                ], 422);
            }
        }

        $tokenCompanyId = $company?->id;
        if ($user->is_super_admin) {
            $resolvedCompanyId = $tokenCompanyId ?: (int) $branch->company_id;
        } else {
            $resolvedCompanyId = (int) $tokenCompanyId;
        }

        if ((int) $branch->company_id !== $resolvedCompanyId) {
            return response()->json([
                'message' => 'La sucursal no pertenece a la empresa activa del token.',
            ], 403);
        }

        if ((int) $warehouse->company_id !== $resolvedCompanyId) {
            return response()->json([
                'message' => 'El almacén no pertenece a la empresa activa del token.',
            ], 403);
        }

        // Código del producto nuevo: vacío = el siguiente PD- sugerido; no se repite en la sucursal.
        $productCode = null;
        if ($validated['product_mode'] === 'new') {
            $productCode = Product::normalizeCode($validated['product_code'] ?? null) ?? Product::nextPdCode($resolvedCompanyId);
            if (Product::codeTaken($resolvedCompanyId, (int) $branch->id, $productCode)) {
                $message = "El código {$productCode} ya está en uso en esta sucursal.";
                return response()->json(['message' => $message, 'errors' => ['product_code' => [$message]]], 422);
            }
        }

        try {
            DB::beginTransaction();

            if ($validated['product_mode'] === 'existing') {
                $product = Product::findOrFail($validated['product_id']);
                if ((int) $product->company_id !== $resolvedCompanyId) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'El producto no pertenece a la empresa activa del token.',
                    ], 403);
                }
            } else {
                $category = CreditCategory::findOrFail($validated['credit_category_id']);
                if ((int) $category->company_id !== $resolvedCompanyId) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'La categoría no pertenece a la empresa activa del token.',
                    ], 403);
                }

                $product = Product::create([
                    'company_id' => $resolvedCompanyId,
                    'branch_id' => (int) $branch->id,
                    'code' => $productCode,
                    'credit_category_id' => $validated['credit_category_id'],
                    'name' => app(\App\Services\PurchaseService::class)->productName($category, $validated),
                    'sku' => 'AUTO-' . time() . '-' . random_int(1000, 9999),
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
                    'cost' => $validated['product_cost'] ?? 0,
                    'price' => $validated['product_price'] ?? 0,
                    'active' => true,
                ]);
            }

            $movement = InventoryMovement::create([
                'company_id' => $resolvedCompanyId,
                'warehouse_id' => $warehouse->id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'user_id' => $user->id,
                'type' => 'in',
                'quantity' => $validated['quantity'],
                'unit_cost' => $validated['unit_cost'] ?? $product->cost,
                'reference' => $validated['reference'] ?? 'INGRESO-MANUAL-API',
                'notes' => $validated['notes'] ?? null,
                'movement_date' => $validated['movement_date'],
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    if (!$image) {
                        continue;
                    }

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

            $movementData = $movement->fresh(['warehouse', 'branch', 'user'])->toArray();
            $movementData['images'] = $movement->images()->get()->map(fn ($img) => [
                'id' => $img->id,
                'inventory_movement_id' => $img->inventory_movement_id,
                'path' => $img->path,
                'url' => Storage::disk('public')->url($img->path),
                'original_name' => $img->original_name,
                'mime_type' => $img->mime_type,
                'size_bytes' => (int) $img->size_bytes,
            ])->values();

            return response()->json([
                'message' => 'Ingreso de stock registrado correctamente.',
                'data' => [
                    'movement' => $movementData,
                    'product' => $product,
                ],
            ], 201);
        } catch (Throwable $exception) {
            DB::rollBack();

            Log::error('Error API al registrar ingreso de stock', [
                'user_id' => $user?->id,
                'token_company_id' => $tokenCompanyId,
                'branch_id' => $validated['branch_id'] ?? null,
                'warehouse_id' => $validated['warehouse_id'] ?? null,
                'product_mode' => $validated['product_mode'] ?? null,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'No fue posible registrar el ingreso de stock. Intenta nuevamente.',
            ], 500);
        }
    }

    /**
     * Actualiza el precio de venta de un producto (igual que la edición en línea del inventario web).
     *
     * @param  Request  $request  Parámetros: price (numérico, 0 a 99.999.999,99)
     * @return JsonResponse  200 con el producto actualizado, 422 si el precio no es válido.
     */
    public function updatePrice(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

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
            'message' => 'Precio actualizado a ' . \App\Support\Currency::format($product->price, $product->company) . '.',
            'data' => [
                'product' => $product->fresh(),
            ],
        ]);
    }

    protected function canEditPrice(Request $request, Product $product): bool
    {
        $user = $request->user();

        return $user->is_super_admin
            || $user->hasPermissionInCompany('warehouses.edit', $request->attributes->get('apiCompany') ?? $product->company);
    }

    /**
     * Verifica que el usuario tenga acceso al producto.
     *
     * Aborta con 403 si el producto no pertenece a la empresa del token.
     *
     * @param  Request  $request
     * @param  Product  $product
     */
    protected function authorizeProduct(Request $request, Product $product): void
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && $product->company_id !== $company?->id) {
            abort(403, 'No tienes acceso a este producto.');
        }

        if ($user->is_super_admin && $company && $product->company_id !== $company->id) {
            abort(403, 'El producto no pertenece a la empresa del token.');
        }
    }

    /** Formulario del producto según la categoría: 1 vehículo, 2 joyas, 3 garrafas, 4 artículos (cualquier otra). */
    public static function categoryForm(?int $categoryId): int
    {
        return in_array((int) $categoryId, [CreditCategory::CATEGORIA_VEHICULO, CreditCategory::CATEGORIA_JOYAS, CreditCategory::CATEGORIA_GARRAFAS], true)
            ? (int) $categoryId
            : CreditCategory::CATEGORIA_ARTICULOS;
    }

    /** Mensajes en español del ingreso de stock (la app no tiene archivos de idioma). */
    protected static function entryMessages(): array
    {
        return [
            'branch_id.required' => 'Selecciona la sucursal.',
            'branch_id.exists' => 'La sucursal no existe.',
            'warehouse_id.exists' => 'El almacén no existe.',
            'product_mode.required' => 'Indica si el producto es nuevo o existente.',
            'product_mode.in' => 'Cada ingreso crea un producto nuevo (no se suma a uno existente).',
            'product_id.required_if' => 'Selecciona el producto.',
            'product_id.exists' => 'El producto no existe.',
            'credit_category_id.required_if' => 'Selecciona la categoría.',
            'credit_category_id.exists' => 'La categoría no existe.',
            'product_name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'product_code.max' => 'El código no puede tener más de 30 caracteres.',
            'product_model_year.integer' => 'El año del modelo debe ser un número.',
            'product_model_year.min' => 'El año del modelo no es válido.',
            'product_model_year.max' => 'El año del modelo no es válido.',
            'product_cost.numeric' => 'El costo debe ser un número.',
            'product_cost.min' => 'El costo no puede ser negativo.',
            'product_price.numeric' => 'El precio debe ser un número.',
            'product_price.min' => 'El precio no puede ser negativo.',
            'quantity.required' => 'Indica la cantidad.',
            'quantity.numeric' => 'La cantidad debe ser un número.',
            'quantity.min' => 'La cantidad debe ser mayor a cero.',
            'quantity.in' => 'Cada ingreso es de un solo producto (cantidad 1): registra cada artículo por separado.',
            'reference.max' => 'La referencia no puede tener más de 100 caracteres.',
            'movement_date.required' => 'Indica la fecha del movimiento.',
            'movement_date.date' => 'La fecha del movimiento no es válida.',
            'images.max' => 'Puedes adjuntar como máximo 20 imágenes.',
            'images.*.image' => 'Cada archivo debe ser una imagen.',
            'images.*.mimes' => 'Las imágenes deben ser JPG, PNG o WEBP.',
            'images.*.max' => 'Cada imagen puede pesar como máximo 5 MB.',
            'max' => 'El valor es demasiado largo.',
            'string' => 'El valor debe ser texto.',
            'integer' => 'El valor debe ser un número entero.',
        ];
    }
}
