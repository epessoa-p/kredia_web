<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Catalog;
use App\Models\CatalogItem;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\LoanCollateral;
use App\Models\LoanImage;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseImage;
use App\Models\User;
use App\Support\Currency;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Lógica de catálogos que comparten la web (Catalogs\CatalogController) y la API (Api\CatalogController):
 * productos disponibles, fotos, origen, sucursal permitida, alta/edición y el PDF con fotos.
 */
class CatalogService
{
    /* ── Sucursal ────────────────────────────────────────────── */

    /**
     * Sucursal forzada para el personal que NO es gerente (solo trabaja con la suya),
     * mismo criterio que el listado de préstamos. Null = puede elegir.
     */
    public function lockedBranchId(User $user): ?int
    {
        if ($user->is_super_admin) {
            return null;
        }

        $personal = Personal::where('user_id', $user->id)->first();

        return ($personal && !$personal->isManager() && $personal->branch_id)
            ? (int) $personal->branch_id
            : null;
    }

    /** Sucursales que el usuario puede asignar a un catálogo de la empresa. */
    public function allowedBranches(User $user, int $companyId): Collection
    {
        $query = Branch::where('company_id', $companyId)->where('active', true)->orderBy('name');

        if ($locked = $this->lockedBranchId($user)) {
            $query->where('id', $locked);
        }

        return $query->get(['id', 'name', 'phone', 'email', 'address', 'color', 'warehouse_id']);
    }

    /** Motivo por el que el usuario no puede editar el catálogo (otra sucursal), o null. */
    public function editBlockedReason(User $user, Catalog $catalog): ?string
    {
        $locked = $this->lockedBranchId($user);
        if ($locked && (int) $catalog->branch_id !== $locked) {
            return 'Este catálogo es de otra sucursal (o de todas). Solo un gerente o administrador puede editarlo.';
        }

        return null;
    }

    /* ── Validación y guardado ───────────────────────────────── */

    public function rules(User $user, int $companyId, ?Catalog $catalog): array
    {
        return [
            'branch_id' => ['nullable', 'integer', Rule::in($this->allowedBranches($user, $companyId)->pluck('id')->all())],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'cover_color' => ['nullable', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{3,8}$/'],
            'show_prices' => 'nullable|boolean',
            'show_contact' => 'nullable|boolean',
            'contact_phone' => 'nullable|string|max:30',
            'contact_whatsapp' => 'nullable|string|max:30',
            'contact_email' => 'nullable|email|max:255',
            'expires_at' => $catalog ? 'nullable|date' : 'nullable|date|after:now',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'products' => 'required|array|min:1',
            'products.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('company_id', $companyId),
            ],
            'products.*.custom_name' => 'nullable|string|max:255',
            'products.*.custom_description' => 'nullable|string|max:2000',
            'products.*.custom_price' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'branch_id.in' => 'No puedes asignar el catálogo a esa sucursal.',
            'title.required' => 'Escribe el título del catálogo.',
            'title.max' => 'El título es demasiado largo.',
            'cover_color.regex' => 'El color no es válido.',
            'contact_email.email' => 'El correo de contacto no es válido.',
            'expires_at.date' => 'La fecha de vencimiento no es válida.',
            'expires_at.after' => 'La fecha de vencimiento debe ser futura.',
            'logo.image' => 'El logo debe ser una imagen.',
            'logo.mimes' => 'El logo debe ser JPG, PNG o WebP.',
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
            'products.required' => 'Agrega al menos un producto al catálogo.',
            'products.min' => 'Agrega al menos un producto al catálogo.',
            'products.*.product_id.required' => 'Falta un producto.',
            'products.*.product_id.exists' => 'Uno de los productos no pertenece a la empresa del catálogo.',
            'products.*.custom_price.numeric' => 'El precio de un producto no es válido.',
            'products.*.custom_price.min' => 'El precio de un producto no puede ser negativo.',
        ];
    }

    /** Sucursal a guardar: la forzada del usuario o la elegida (null = todas). */
    public function resolveBranchId(User $user, array $data): ?int
    {
        if ($locked = $this->lockedBranchId($user)) {
            return $locked;
        }

        return !empty($data['branch_id']) ? (int) $data['branch_id'] : null;
    }

    /** Alta de catálogo. $data ya validado con rules(). */
    public function create(Company $company, User $user, array $data, ?UploadedFile $logo): Catalog
    {
        return DB::transaction(function () use ($company, $user, $data, $logo) {
            $catalog = Catalog::create([
                'company_id' => $company->id,
                'branch_id' => $this->resolveBranchId($user, $data),
                'created_by' => $user->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'slug' => Str::random(12),
                'cover_color' => $data['cover_color'] ?? '#0d6efd',
                'logo_path' => $logo?->store('catalog-logos', 'public'),
                'show_prices' => (bool) ($data['show_prices'] ?? true),
                'show_contact' => (bool) ($data['show_contact'] ?? true),
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_whatsapp' => $data['contact_whatsapp'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'active' => true,
                'expires_at' => $data['expires_at'] ?? null,
            ]);
            $this->saveItems($catalog, $data['products']);

            return $catalog;
        });
    }

    /** Edición: datos y productos (se reemplazan en el orden recibido). */
    public function update(Catalog $catalog, User $user, array $data, ?UploadedFile $logo): Catalog
    {
        return DB::transaction(function () use ($catalog, $user, $data, $logo) {
            $update = [
                'branch_id' => $this->resolveBranchId($user, $data),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'cover_color' => $data['cover_color'] ?? '#0d6efd',
                'show_prices' => (bool) ($data['show_prices'] ?? true),
                'show_contact' => (bool) ($data['show_contact'] ?? true),
                'contact_phone' => $data['contact_phone'] ?? null,
                'contact_whatsapp' => $data['contact_whatsapp'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
            ];
            if ($logo) {
                $update['logo_path'] = $logo->store('catalog-logos', 'public');
            }
            $catalog->update($update);

            $catalog->items()->delete();
            $this->saveItems($catalog, $data['products']);

            return $catalog->fresh();
        });
    }

    /** Guarda los productos del catálogo en el orden recibido. */
    public function saveItems(Catalog $catalog, array $products): void
    {
        $order = 0;
        foreach ($products as $item) {
            CatalogItem::create([
                'catalog_id' => $catalog->id,
                'product_id' => $item['product_id'],
                'custom_name' => ($item['custom_name'] ?? null) ?: null,
                'custom_description' => ($item['custom_description'] ?? null) ?: null,
                'custom_price' => (isset($item['custom_price']) && $item['custom_price'] !== '') ? $item['custom_price'] : null,
                'sort_order' => $order++,
            ]);
        }
    }

    /* ── Productos disponibles ───────────────────────────────── */

    /**
     * Productos que se pueden poner a la venta en un catálogo de la empresa:
     * stock libre o prenda de préstamo disponible para la venta. Al editar se
     * agregan los productos ya incluidos aunque ya no estén disponibles.
     *
     * Cada producto incluye foto, categoría, sucursales donde está y origen.
     */
    public function productPool(int $companyId, ?Catalog $catalog): array
    {
        // Stock por producto y almacén (para saber en qué sucursal está).
        $stockRows = InventoryMovement::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->selectRaw('product_id, warehouse_id, SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) as stock')
            ->groupBy('product_id', 'warehouse_id')
            ->get();

        $stockMap = $stockRows->groupBy('product_id')
            ->map(fn ($rows) => (float) $rows->sum('stock'))
            ->filter(fn ($stock) => $stock > 0);

        $productWarehouses = $stockRows->filter(fn ($r) => (float) $r->stock > 0)
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->pluck('warehouse_id')->filter()->unique()->values()->all());

        // Prendas retenidas/guardadas reducen el stock libre.
        $heldMap = $stockMap->isNotEmpty()
            ? LoanCollateral::where('company_id', $companyId)
                ->whereIn('status', ['retained', 'stored'])
                ->whereNull('deleted_at')
                ->whereIn('product_id', $stockMap->keys())
                ->selectRaw('product_id, COUNT(*) as held')
                ->groupBy('product_id')
                ->pluck('held', 'product_id')
            : collect();

        // Prendas disponibles para la venta (disponibles aunque no haya stock libre).
        $sellable = LoanCollateral::where('company_id', $companyId)
            ->where('status', 'sellable')
            ->whereNull('deleted_at')
            ->get(['product_id', 'branch_id', 'warehouse_id']);
        $sellableIds = $sellable->pluck('product_id')->unique();

        $availableIds = $stockMap->keys()->filter(function ($pid) use ($stockMap, $heldMap, $sellableIds) {
            $freeStock = max(0, (float) $stockMap[$pid] - (int) ($heldMap[$pid] ?? 0));
            return $freeStock > 0 || $sellableIds->contains($pid);
        })->merge($sellableIds->diff($stockMap->keys()))->unique()->values();

        $relations = ['inventoryMovements.images', 'creditCategory:id,name'];

        $products = Product::where('company_id', $companyId)
            ->where('active', true)
            ->whereIn('id', $availableIds)
            ->with($relations)
            ->get();

        // Al editar: productos ya incluidos que dejaron de estar disponibles.
        $includedIds = $catalog ? $catalog->items->pluck('product_id')->diff($products->pluck('id')) : collect();
        if ($includedIds->isNotEmpty()) {
            $products = $products->merge(
                Product::withTrashed()->where('company_id', $companyId)->whereIn('id', $includedIds)->with($relations)->get()
            );
        }

        $products = $products->sortBy(fn ($p) => mb_strtolower((string) $p->name))->values();

        // Sucursal de cada almacén.
        $warehouseBranch = Branch::where('company_id', $companyId)
            ->whereNotNull('warehouse_id')
            ->pluck('id', 'warehouse_id');

        $sellableBranches = $sellable->groupBy('product_id')->map(fn ($rows) => $rows
            ->map(fn ($c) => $c->branch_id ?: ($warehouseBranch[$c->warehouse_id] ?? null))
            ->filter()->unique()->values()->all());

        $images = $this->productImageMap($products);
        $origins = $this->productOrigins($products->pluck('id'), $companyId);
        $availableSet = $availableIds->flip();

        return $products->map(function (Product $product) use ($productWarehouses, $warehouseBranch, $sellableBranches, $images, $origins, $availableSet) {
            $branchIds = collect($productWarehouses[$product->id] ?? [])
                ->map(fn ($wid) => $warehouseBranch[$wid] ?? null)
                ->merge($sellableBranches[$product->id] ?? [])
                ->filter()->unique()->map(fn ($id) => (int) $id)->values()->all();

            $productOrigins = $origins[$product->id] ?? [];

            return [
                'id' => $product->id,
                'name' => (string) $product->name,
                'detail' => trim(($product->brand ?? '') . ' ' . ($product->item_type ?? '')),
                'description' => (string) ($product->description ?? ''),
                'price' => (float) ($product->price ?? 0),
                'image' => $images[$product->id] ?? '',
                'category_id' => $product->credit_category_id ? (int) $product->credit_category_id : null,
                'category' => $product->creditCategory?->name ?? '',
                'branch_ids' => $branchIds,
                'origins' => $productOrigins,
                'origin_type' => $productOrigins[0]['type'] ?? 'other',
                'available' => isset($availableSet[$product->id]) && !$product->trashed(),
            ];
        })->all();
    }

    /* ── Fotos ───────────────────────────────────────────────── */

    /** product_id => URL de la primera foto del producto. */
    public function productImageMap(Collection $products): array
    {
        return array_map(fn ($path) => asset('storage/' . $path), $this->productImagePaths($products));
    }

    /**
     * Primera foto de cada producto (ruta en el disco public), buscando en 3 fuentes:
     * 1. fotos de movimientos de inventario (entradas manuales)
     * 2. fotos del préstamo (productos de préstamos, ref. LOAN-RET-{id})
     * 3. fotos de la compra (ref. COMPRA-{id})
     *
     * @return array<int, string> product_id => ruta relativa
     */
    public function productImagePaths(Collection $products): array
    {
        $loanSourceMap = [];     // product_id → [loan_id, ...]
        $purchaseSourceMap = []; // product_id → [purchase_id, ...]

        foreach ($products as $product) {
            if ($product->inventoryMovements->flatMap->images->isNotEmpty()) {
                continue;
            }
            foreach ($product->inventoryMovements as $mov) {
                $ref = $mov->reference ?? '';
                if (str_starts_with($ref, 'LOAN-RET-')) {
                    $id = (int) substr($ref, strlen('LOAN-RET-'));
                    if ($id) $loanSourceMap[$product->id][] = $id;
                } elseif (str_starts_with($ref, 'COMPRA-')) {
                    $id = (int) substr($ref, strlen('COMPRA-'));
                    if ($id) $purchaseSourceMap[$product->id][] = $id;
                }
            }
        }

        $allLoanIds = collect($loanSourceMap)->flatten()->unique()->values();
        $allPurchaseIds = collect($purchaseSourceMap)->flatten()->unique()->values();

        $loanImagesMap = $allLoanIds->isNotEmpty()
            ? LoanImage::whereIn('loan_id', $allLoanIds)->get()->groupBy('loan_id')
            : collect();
        $purchaseImagesMap = $allPurchaseIds->isNotEmpty()
            ? PurchaseImage::whereIn('purchase_id', $allPurchaseIds)->get()->groupBy('purchase_id')
            : collect();

        $map = [];
        foreach ($products as $product) {
            $movImg = $product->inventoryMovements->flatMap->images->first();
            if ($movImg) {
                $map[$product->id] = $movImg->path;
                continue;
            }
            foreach ($loanSourceMap[$product->id] ?? [] as $loanId) {
                $img = $loanImagesMap->get($loanId)?->first();
                if ($img) {
                    $map[$product->id] = $img->path;
                    continue 2;
                }
            }
            foreach ($purchaseSourceMap[$product->id] ?? [] as $purchaseId) {
                $img = $purchaseImagesMap->get($purchaseId)?->first();
                if ($img) {
                    $map[$product->id] = $img->path;
                    continue 2;
                }
            }
        }

        return $map;
    }

    /**
     * Origen de cada producto para la revisión interna del catálogo:
     * préstamos cuya prenda está en venta (PR-) o, si no hay, compras (CO-).
     * Solo para vistas internas; no se usa en el enlace público ni en el PDF.
     *
     * @return array<int, array<int, array{code: string, type: string, id: int, url: string}>>
     */
    public function productOrigins(Collection $productIds, int $companyId): array
    {
        $productIds = $productIds->filter()->unique()->values();
        if ($productIds->isEmpty()) {
            return [];
        }

        $origins = [];

        // 1) Préstamos con la prenda disponible para la venta.
        LoanCollateral::where('company_id', $companyId)
            ->where('status', 'sellable')
            ->whereIn('product_id', $productIds)
            ->whereHas('loan')
            ->with('loan:id,numero')
            ->orderBy('loan_id')
            ->get(['id', 'product_id', 'loan_id'])
            ->each(function (LoanCollateral $collateral) use (&$origins) {
                $origins[$collateral->product_id][$collateral->loan_id] = [
                    'code' => 'PR-' . str_pad((string) $collateral->loan->numero, 5, '0', STR_PAD_LEFT),
                    'type' => 'loan',
                    'id' => (int) $collateral->loan_id,
                    'url' => route('loans.show', $collateral->loan_id),
                ];
            });

        // 2) Productos sin prenda en venta: compras de ese producto.
        $withoutLoan = $productIds->reject(fn ($id) => isset($origins[$id]));
        if ($withoutLoan->isNotEmpty()) {
            Purchase::where('company_id', $companyId)
                ->whereIn('product_id', $withoutLoan)
                ->orderBy('id')
                ->get(['id', 'numero', 'product_id'])
                ->each(function (Purchase $purchase) use (&$origins) {
                    $origins[$purchase->product_id][$purchase->id] = [
                        'code' => 'CO-' . str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT),
                        'type' => 'purchase',
                        'id' => (int) $purchase->id,
                        'url' => route('purchases.show', $purchase->id),
                    ];
                });
        }

        return array_map('array_values', $origins);
    }

    /* ── PDF con fotos ───────────────────────────────────────── */

    /**
     * PDF real del catálogo (dompdf) con las fotos de los productos incrustadas y reducidas
     * (dompdf no descarga imágenes por URL y las fotos de celular pesan varios MB).
     *
     * @return array{0:string,1:string} [nombre de archivo, contenido]
     */
    public function pdf(Catalog $catalog): array
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $catalog->loadMissing(['items.product.inventoryMovements.images', 'company']);
        $products = $catalog->items->pluck('product')->filter();
        $images = array_map(fn ($path) => $this->embeddedImage($path, 600), $this->productImagePaths($products));

        $html = view('catalogs.pdf', [
            'catalog' => $catalog,
            'productImages' => array_filter($images),
            'logo' => $catalog->logo_path ? $this->embeddedImage($catalog->logo_path, 300) : null,
            'currencySymbol' => Currency::symbol($catalog->company),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('letter')->setOption('isFontSubsettingEnabled', true);

        return ['catalogo-' . (Str::slug($catalog->title) ?: $catalog->id) . '.pdf', $pdf->output()];
    }

    /** Imagen del disco public como data URI JPEG de máximo $max px de lado (null si no se puede leer). */
    private function embeddedImage(string $path, int $max): ?string
    {
        $disk = Storage::disk('public');
        if (!$disk->exists($path)) {
            return null;
        }
        $raw = $disk->get($path);
        $src = @imagecreatefromstring($raw);
        if (!$src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // fondo blanco para PNG transparentes
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($dst, null, 78);
        $jpeg = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return 'data:image/jpeg;base64,' . base64_encode($jpeg);
    }
}
