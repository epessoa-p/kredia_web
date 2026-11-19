<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Catálogos de productos para la app: listar, ver, crear, editar, pausar/activar, regenerar el enlace,
 * eliminar y descargar el PDF con fotos. La lógica es la misma que la web (CatalogService).
 */
class CatalogController extends Controller
{
    public function __construct(private CatalogService $catalogs)
    {
    }

    /**
     * GET /catalogs — lista paginada. Parámetros: q (título, enlace o descripción), active (bool), per_page (1-100).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        $query = Catalog::query()
            ->with(['createdBy', 'branch'])
            ->withCount('items')
            ->latest();

        if (!$user->is_super_admin) {
            $query->where('company_id', $company?->id);
        } elseif ($company) {
            $query->where('company_id', $company->id);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('title', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('active', (bool) $request->boolean('active'));
        }

        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $catalogs = $query->paginate($perPage)->withQueryString();

        $catalogs->getCollection()->transform(function (Catalog $catalog) {
            $data = $catalog->toArray();
            $data['public_url'] = $catalog->getPublicUrl();
            $data['status'] = $this->status($catalog);
            $data['logo_url'] = $catalog->logo_path ? asset('storage/' . $catalog->logo_path) : null;

            return $data;
        });

        return response()->json($catalogs);
    }

    /** GET /catalogs/{catalog} — catálogo con sus productos (foto, precio que se muestra), enlace y permisos. */
    public function show(Request $request, Catalog $catalog): JsonResponse
    {
        $this->authorizeCatalog($request, $catalog);

        return response()->json($this->detail($request, $catalog));
    }

    /**
     * GET /catalogs/options[?catalog_id=] — sucursales permitidas y productos disponibles para la venta
     * (stock libre o prenda de préstamo en venta). Con catalog_id incluye los que ya tiene ese catálogo.
     */
    public function options(Request $request): JsonResponse
    {
        $user = $request->user();
        $catalog = null;
        if ($request->filled('catalog_id')) {
            $catalog = Catalog::with('items')->findOrFail($request->integer('catalog_id'));
            $this->authorizeCatalog($request, $catalog);
            $company = $catalog->company;
        } else {
            $company = $this->companyOrFail($request);
        }

        $locked = $this->catalogs->lockedBranchId($user);
        $products = collect($this->catalogs->productPool((int) $company->id, $catalog))
            ->map(fn ($p) => collect($p)->except('origins')->put('origin', $p['origins'][0]['code'] ?? null)->all());

        return response()->json([
            'branches' => $this->catalogs->allowedBranches($user, (int) $company->id)
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name, 'phone' => $b->phone, 'email' => $b->email])->values(),
            'locked_branch_id' => $locked,
            'products' => $products->values(),
            'categories' => $products->filter(fn ($p) => $p['category_id'])
                ->map(fn ($p) => ['id' => $p['category_id'], 'name' => $p['category']])
                ->unique('id')->sortBy('name')->values(),
            'colors' => ['#0d6efd', '#198754', '#dc3545', '#fd7e14', '#6f42c1', '#0dcaf0', '#212529', '#d63384'],
        ]);
    }

    /** POST /catalogs (permiso catalogs.create). JSON, o multipart si se envía logo (products como JSON en texto). */
    public function store(Request $request): JsonResponse
    {
        $company = $this->companyOrFail($request);
        $user = $request->user();
        $this->normalize($request);

        $data = $request->validate($this->catalogs->rules($user, (int) $company->id, null), $this->catalogs->messages());
        $data['show_prices'] = $request->boolean('show_prices', true);
        $data['show_contact'] = $request->boolean('show_contact', true);

        return $this->run('crear', function () use ($company, $user, $data, $request) {
            $catalog = $this->catalogs->create($company, $user, $data, $request->file('logo'));

            return response()->json(['message' => 'Catálogo creado correctamente.'] + $this->detail($request, $catalog), 201);
        });
    }

    /** PUT /catalogs/{catalog} (permiso catalogs.create). Con logo: POST multipart + _method=PUT. */
    public function update(Request $request, Catalog $catalog): JsonResponse
    {
        $this->authorizeCatalog($request, $catalog);
        $user = $request->user();
        if ($reason = $this->catalogs->editBlockedReason($user, $catalog)) {
            return response()->json(['message' => $reason], 403);
        }
        $this->normalize($request);

        $data = $request->validate($this->catalogs->rules($user, (int) $catalog->company_id, $catalog), $this->catalogs->messages());
        $data['show_prices'] = $request->boolean('show_prices', true);
        $data['show_contact'] = $request->boolean('show_contact', true);

        return $this->run('actualizar', function () use ($catalog, $user, $data, $request) {
            $catalog = $this->catalogs->update($catalog, $user, $data, $request->file('logo'));

            return response()->json(['message' => 'Catálogo actualizado correctamente.'] + $this->detail($request, $catalog));
        });
    }

    /** POST /catalogs/{catalog}/toggle-active — pausa o reactiva el enlace (permiso catalogs.create). */
    public function toggleActive(Request $request, Catalog $catalog): JsonResponse
    {
        $this->authorizeCatalog($request, $catalog);
        if ($reason = $this->catalogs->editBlockedReason($request->user(), $catalog)) {
            return response()->json(['message' => $reason], 403);
        }
        $catalog->update(['active' => !$catalog->active]);

        return response()->json([
            'message' => $catalog->active ? 'Catálogo activado: el enlace vuelve a funcionar.' : 'Catálogo pausado: el enlace deja de funcionar.',
        ] + $this->detail($request, $catalog));
    }

    /** POST /catalogs/{catalog}/regenerate-slug — enlace nuevo; el anterior deja de funcionar (permiso catalogs.create). */
    public function regenerateSlug(Request $request, Catalog $catalog): JsonResponse
    {
        $this->authorizeCatalog($request, $catalog);
        if ($reason = $this->catalogs->editBlockedReason($request->user(), $catalog)) {
            return response()->json(['message' => $reason], 403);
        }
        $catalog->update(['slug' => Str::random(12)]);

        return response()->json(['message' => 'Enlace nuevo generado. El anterior ya no funciona.'] + $this->detail($request, $catalog));
    }

    /** DELETE /catalogs/{catalog} (permiso catalogs.delete). */
    public function destroy(Request $request, Catalog $catalog): JsonResponse
    {
        $this->authorizeCatalog($request, $catalog);
        if ($reason = $this->catalogs->editBlockedReason($request->user(), $catalog)) {
            return response()->json(['message' => $reason], 403);
        }
        $catalog->delete();

        return response()->json(['message' => 'Catálogo eliminado correctamente.']);
    }

    /** GET /catalogs/{catalog}/pdf — PDF con las fotos de los productos (permiso catalogs.view). */
    public function pdf(Request $request, Catalog $catalog)
    {
        $this->authorizeCatalog($request, $catalog);

        try {
            [$filename, $content] = $this->catalogs->pdf($catalog);
        } catch (Throwable $e) {
            Log::error('API: error al generar PDF de catálogo', ['catalog_id' => $catalog->id, 'message' => $e->getMessage()]);

            return response()->json(['message' => 'No fue posible generar el PDF del catálogo.'], 500);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /* ── Ayudantes ───────────────────────────────────────────── */

    private function detail(Request $request, Catalog $catalog): array
    {
        $user = $request->user();
        $catalog->load(['createdBy', 'branch', 'company', 'items.product.creditCategory', 'items.product.inventoryMovements.images']);
        $products = $catalog->items->pluck('product')->filter();
        $images = $this->catalogs->productImageMap($products);
        $blocked = $this->catalogs->editBlockedReason($user, $catalog);
        $can = fn (string $p) => $user->is_super_admin || $user->hasPermissionInCompany($p, $catalog->company);

        return [
            'catalog' => [
                'id' => $catalog->id,
                'title' => $catalog->title,
                'description' => $catalog->description,
                'branch_id' => $catalog->branch_id,
                'branch' => $catalog->branch?->name,
                'cover_color' => $catalog->cover_color,
                'logo_url' => $catalog->logo_path ? asset('storage/' . $catalog->logo_path) : null,
                'show_prices' => (bool) $catalog->show_prices,
                'show_contact' => (bool) $catalog->show_contact,
                'contact_phone' => $catalog->contact_phone,
                'contact_whatsapp' => $catalog->contact_whatsapp,
                'contact_email' => $catalog->contact_email,
                'active' => (bool) $catalog->active,
                'expires_at' => $catalog->expires_at?->toIso8601String(),
                'created_by' => $catalog->createdBy?->name,
                'created_at' => $catalog->created_at?->toIso8601String(),
                'items' => $catalog->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->custom_name ?: $item->product?->name,
                    'product_name' => $item->product?->name,
                    'custom_name' => $item->custom_name,
                    'custom_description' => $item->custom_description,
                    'custom_price' => $item->custom_price !== null ? (float) $item->custom_price : null,
                    'price' => (float) ($item->custom_price ?? $item->product?->price ?? 0),
                    'description' => $item->custom_description ?: $item->product?->description,
                    'category' => $item->product?->creditCategory?->name,
                    'image' => $images[$item->product_id] ?? null,
                    'product_deleted' => $item->product === null,
                ])->values(),
            ],
            'public_url' => $catalog->getPublicUrl(),
            'is_accessible' => $catalog->isAccessible(),
            'status' => $this->status($catalog),
            'abilities' => [
                'edit' => $can('catalogs.create') && !$blocked,
                'delete' => $can('catalogs.delete') && !$blocked,
            ],
            'edit_blocked_reason' => $blocked,
        ];
    }

    /** active | paused | expired */
    private function status(Catalog $catalog): string
    {
        if (!$catalog->active) {
            return 'paused';
        }

        return $catalog->expires_at && $catalog->expires_at->isPast() ? 'expired' : 'active';
    }

    /** En multipart, products llega como JSON en texto. */
    private function normalize(Request $request): void
    {
        if (is_string($request->input('products'))) {
            $decoded = json_decode($request->input('products'), true);
            $request->merge(['products' => is_array($decoded) ? $decoded : []]);
        }
        foreach (['show_prices', 'show_contact'] as $flag) {
            if (is_string($request->input($flag))) {
                $request->merge([$flag => filter_var($request->input($flag), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
    }

    private function run(string $action, \Closure $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            Log::error("API: error al {$action} catálogo", ['message' => $e->getMessage()]);

            return response()->json(['message' => "No fue posible {$action} el catálogo. Intenta nuevamente."], 500);
        }
    }

    private function companyOrFail(Request $request)
    {
        $company = $request->attributes->get('apiCompany');
        if (!$company) {
            abort(response()->json(['message' => 'Elige una empresa para trabajar con catálogos.'], 422));
        }

        return $company;
    }

    /**
     * Verifica que el catálogo sea de la empresa del token (403 si no).
     */
    protected function authorizeCatalog(Request $request, Catalog $catalog): void
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && $catalog->company_id !== $company?->id) {
            abort(403, 'No tienes acceso a este catálogo.');
        }

        if ($user->is_super_admin && $company && $catalog->company_id !== $company->id) {
            abort(403, 'El catálogo no pertenece a la empresa del token.');
        }
    }
}
