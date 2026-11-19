<?php

namespace App\Http\Controllers\Collections;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Client;
use App\Models\CollectionRoute;
use App\Models\Personal;
use App\Services\Installments\InstallmentLoanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cobranza: lista del día por cobrador/ruta y gestión de rutas (orden de visita de clientes).
 * El cobrador sin collections.manage solo ve su propia cartera.
 */
class CollectionController extends Controller
{
    public function __construct(private InstallmentLoanService $service)
    {
    }

    public function today(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();
        $canManage = $user->is_super_admin || $user->hasPermissionInCompany('collections.manage', $company);
        $personal = Personal::where('user_id', $user->id)->first();

        $collectorId = $canManage ? ($request->integer('collector_id') ?: null) : $personal?->id;
        $items = (!$canManage && !$personal) ? collect() : $this->service->collectionList($collectorId, $request->integer('route_id') ?: null);

        return view('collections.today', [
            'items' => $items,
            'canManage' => $canManage,
            'collectors' => $canManage ? Personal::where('active', true)->orderBy('full_name')->get(['id', 'full_name']) : collect(),
            'routes' => CollectionRoute::where('active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => ['collector_id' => $collectorId, 'route_id' => $request->integer('route_id') ?: null],
        ]);
    }

    public function routes()
    {
        return view('collections.routes', [
            'routes' => CollectionRoute::with(['collector:id,full_name', 'branch:id,name'])->withCount('clients')->orderBy('name')->get(),
            'collectors' => Personal::where('active', true)->orderBy('full_name')->get(['id', 'full_name']),
            'branches' => Branch::where('active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeRoute(Request $request)
    {
        CollectionRoute::create($this->validatedRoute($request));

        return back()->with('success', 'Ruta creada.');
    }

    public function updateRoute(Request $request, CollectionRoute $collectionRoute)
    {
        $collectionRoute->update($this->validatedRoute($request));

        return back()->with('success', 'Ruta actualizada.');
    }

    public function destroyRoute(CollectionRoute $collectionRoute)
    {
        Client::where('collection_route_id', $collectionRoute->id)->update(['collection_route_id' => null, 'route_position' => null]);
        $collectionRoute->delete();

        return back()->with('success', 'Ruta eliminada. Sus clientes quedaron sin ruta.');
    }

    public function editRouteClients(CollectionRoute $collectionRoute)
    {
        return view('collections.route-clients', [
            'route' => $collectionRoute->load('collector:id,full_name'),
            'assigned' => $collectionRoute->clients()->get(['id', 'name', 'address', 'route_position']),
            'available' => Client::where('active', true)->whereNull('collection_route_id')->orderBy('name')->get(['id', 'name', 'address']),
        ]);
    }

    /** Guarda los clientes de la ruta en el orden recibido (client_ids[] ordenado). */
    public function updateRouteClients(Request $request, CollectionRoute $collectionRoute)
    {
        $companyId = auth()->user()->getCurrentCompany()?->id ?? $collectionRoute->company_id;
        $v = $request->validate([
            'client_ids' => 'nullable|array',
            'client_ids.*' => ['integer', Rule::exists('clients', 'id')->where('company_id', $companyId)],
        ]);
        $ids = array_values(array_unique($v['client_ids'] ?? []));

        DB::transaction(function () use ($collectionRoute, $ids) {
            Client::where('collection_route_id', $collectionRoute->id)->whereNotIn('id', $ids)
                ->update(['collection_route_id' => null, 'route_position' => null]);
            foreach ($ids as $position => $clientId) {
                Client::whereKey($clientId)->update(['collection_route_id' => $collectionRoute->id, 'route_position' => $position + 1]);
            }
        });

        return redirect()->route('collections.routes.index')->with('success', 'Clientes de la ruta actualizados.');
    }

    private function validatedRoute(Request $request): array
    {
        $companyId = auth()->user()->getCurrentCompany()?->id;

        $v = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'collector_id' => ['nullable', Rule::exists('personals', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
        ], ['name.required' => 'Ingresa el nombre de la ruta.']);
        $v['active'] = $request->boolean('active', true);

        return $v;
    }
}
