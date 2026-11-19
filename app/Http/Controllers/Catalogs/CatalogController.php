<?php

namespace App\Http\Controllers\Catalogs;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\Company;
use App\Services\CatalogService;
use App\Support\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function __construct(private CatalogService $catalogs)
    {
        $this->middleware('check-permission:catalogs.view')->except(['publicView']);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        $query = Catalog::with('createdBy', 'branch')
            ->withCount('items')
            ->latest();

        if (!$user->is_super_admin && $company) {
            $query->where('company_id', $company->id);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where('title', 'like', "%{$q}%");
        }

        $status = $request->get('status');
        if ($status === 'active') {
            $query->where('active', true)->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        } elseif ($status === 'inactive') {
            $query->where(function ($q) {
                $q->where('active', false)->orWhere(function ($q2) {
                    $q2->whereNotNull('expires_at')->where('expires_at', '<=', now());
                });
            });
        }

        $catalogs = $query->paginate(15)->withQueryString();

        return view('catalogs.index', compact('catalogs', 'q', 'status'));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $companies = collect();

        if ($user->is_super_admin) {
            // El super admin no tiene empresa: elige la empresa del catálogo en el formulario.
            $companies = Company::where('active', true)->orderBy('name')->get(['id', 'name']);
            $companyId = (int) $request->query('company_id', old('company_id', 0));
            $company = $companyId ? $companies->firstWhere('id', $companyId) : null;
            if (!$company && $companies->count() === 1) {
                $company = $companies->first(); // una sola empresa: se preselecciona
            }
        } else {
            $company = $user->getCurrentCompany();
            // Un catálogo pertenece a una empresa: sin empresa seleccionada no se puede crear.
            if (!$company) {
                return redirect()->route('catalogs.index')->with('error', $this->noCompanyMessage());
            }
        }

        return view('catalogs.create', $this->formData($company, null) + ['companies' => $companies]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->is_super_admin) {
            $company = Company::where('active', true)->find($request->integer('company_id'));
            if (!$company) {
                return back()->withInput()->withErrors(['company_id' => 'Selecciona la empresa del catálogo.']);
            }
        } else {
            $company = $user->getCurrentCompany();
            if (!$company) {
                return redirect()->route('catalogs.index')->with('error', $this->noCompanyMessage());
            }
        }

        $data = $this->validateCatalogRequest($request, (int) $company->id, null);

        try {
            $catalog = $this->catalogs->create($company, $user, $data, $request->file('logo'));
            return redirect()->route('catalogs.show', $catalog)->with('success', 'Catálogo creado correctamente.');
        } catch (\Exception $e) {
            report($e);
            return back()->withInput()->with('error', 'No fue posible crear el catálogo. Intenta nuevamente.');
        }
    }

    public function show(Catalog $catalog)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && $catalog->company_id !== $company?->id) {
            abort(403);
        }

        $catalog->load(['items.product.inventoryMovements.images', 'createdBy', 'branch']);

        $productOrigins = $this->productOrigins($catalog->items->pluck('product_id'), (int) $catalog->company_id);
        $productImages = $this->productImageMap($catalog->items->pluck('product')->filter());

        return view('catalogs.show', compact('catalog', 'productOrigins', 'productImages'));
    }

    public function edit(Catalog $catalog)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && $catalog->company_id !== $company?->id) {
            abort(403);
        }

        if ($redirect = $this->denyIfOtherBranch($catalog)) {
            return $redirect;
        }

        $catalog->load('items.product', 'company', 'branch');

        // Los productos se toman de la empresa DEL CATÁLOGO (no de la empresa
        // seleccionada): el super admin puede editar catálogos de cualquier empresa.
        return view('catalogs.edit', $this->formData($catalog->company, $catalog) + ['companies' => collect()]);
    }

    public function update(Request $request, Catalog $catalog)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && $catalog->company_id !== $company?->id) {
            abort(403);
        }

        if ($redirect = $this->denyIfOtherBranch($catalog)) {
            return $redirect;
        }

        $data = $this->validateCatalogRequest($request, (int) $catalog->company_id, $catalog);

        try {
            $this->catalogs->update($catalog, auth()->user(), $data, $request->file('logo'));
            return redirect()->route('catalogs.show', $catalog)->with('success', 'Catálogo actualizado correctamente.');
        } catch (\Exception $e) {
            report($e);
            return back()->withInput()->with('error', 'No fue posible actualizar el catálogo. Intenta nuevamente.');
        }
    }

    public function toggleActive(Catalog $catalog)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && $catalog->company_id !== $company?->id) {
            abort(403);
        }

        $catalog->update(['active' => !$catalog->active]);

        $state = $catalog->active ? 'activado' : 'desactivado';
        return back()->with('success', "Catálogo {$state} correctamente.");
    }

    public function destroy(Catalog $catalog)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && $catalog->company_id !== $company?->id) {
            abort(403);
        }

        $catalog->delete();

        return redirect()->route('catalogs.index')->with('success', 'Catálogo eliminado correctamente.');
    }

    public function regenerateSlug(Catalog $catalog)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && $catalog->company_id !== $company?->id) {
            abort(403);
        }

        $catalog->update(['slug' => Str::random(12)]);

        return back()->with('success', 'Enlace público regenerado correctamente.');
    }

    /**
     * Vista pública del catálogo — sin autenticación
     */
    public function publicView(string $slug)
    {
        // Hardening: evitar exponer catálogos inactivos/vencidos por enumeración de slug.
        $catalog = Catalog::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        // La empresa debe tener la suscripción vigente y el módulo de catálogos en su plan.
        $company = $catalog->company;
        abort_unless($company && $company->subscriptionAllowsRead() && $company->planAllows('catalogs'), 404);

        $catalog->load(['items.product.inventoryMovements.images', 'company', 'branch']);

        // Fotos de inventario, del préstamo o de la compra (igual que la vista previa).
        $productImages = $this->productImageMap($catalog->items->pluck('product')->filter());

        return view('catalogs.public', compact('catalog', 'productImages') + ['currencySymbol' => Currency::symbol($catalog->company)]);
    }

    /**
     * Exportar catálogo como PDF
     */
    public function exportPdf(Catalog $catalog)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && $catalog->company_id !== $company?->id) {
            abort(403);
        }

        // PDF real con las fotos de los productos (las mismas que ve el cliente en el enlace).
        [$filename, $content] = $this->catalogs->pdf($catalog);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Datos que comparten los formularios de crear y editar catálogo.
     */
    private function formData(?Company $company, ?Catalog $catalog): array
    {
        if (!$company) {
            return [
                'company' => null, 'catalog' => $catalog, 'branches' => collect(),
                'lockedBranchId' => null, 'products' => [], 'categories' => collect(),
            ];
        }

        $products = $this->productPool((int) $company->id, $catalog);

        $categories = collect($products)
            ->filter(fn ($p) => $p['category_id'])
            ->mapWithKeys(fn ($p) => [$p['category_id'] => $p['category']])
            ->sort();

        return [
            'company' => $company,
            'catalog' => $catalog,
            'branches' => $this->allowedBranches((int) $company->id),
            'lockedBranchId' => $this->lockedBranchId(),
            'products' => $products,
            'categories' => $categories,
        ];
    }

    private function lockedBranchId(): ?int
    {
        return $this->catalogs->lockedBranchId(auth()->user());
    }

    private function allowedBranches(int $companyId): Collection
    {
        return $this->catalogs->allowedBranches(auth()->user(), $companyId);
    }

    /**
     * El personal no gerente solo edita catálogos de su sucursal.
     */
    private function denyIfOtherBranch(Catalog $catalog)
    {
        $reason = $this->catalogs->editBlockedReason(auth()->user(), $catalog);

        return $reason ? redirect()->route('catalogs.show', $catalog)->with('error', $reason) : null;
    }

    /** Valida el formulario (reglas y mensajes de CatalogService) y devuelve los datos. */
    private function validateCatalogRequest(Request $request, int $companyId, ?Catalog $catalog): array
    {
        $data = $request->validate(
            $this->catalogs->rules(auth()->user(), $companyId, $catalog),
            $this->catalogs->messages()
        );
        $data['show_prices'] = $request->boolean('show_prices', true);
        $data['show_contact'] = $request->boolean('show_contact', true);

        return $data;
    }

    private function productPool(int $companyId, ?Catalog $catalog): array
    {
        return $this->catalogs->productPool($companyId, $catalog);
    }

    private function productImageMap(Collection $products): array
    {
        return $this->catalogs->productImageMap($products);
    }

    /**
     * Mensaje cuando no hay empresa actual. El super administrador trabaja sin
     * empresa (no puede elegir una), así que un catálogo se crea desde un usuario
     * de la empresa.
     */
    private function noCompanyMessage(): string
    {
        return auth()->user()->is_super_admin
            ? 'El super administrador no tiene una empresa asignada. Para crear un catálogo, ingresa con un usuario de la empresa.'
            : 'No tienes una empresa seleccionada. Selecciona una empresa para crear un catálogo.';
    }

    private function productOrigins(Collection $productIds, int $companyId): array
    {
        return $this->catalogs->productOrigins($productIds, $companyId);
    }
}
