<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Concerns\ResolvesCashSession;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientTag;
use App\Models\Company;
use App\Services\ClientRating;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientController extends Controller
{
    use ResolvesCashSession;

    public function __construct()
    {
        $this->middleware('check-permission:loans.view');
    }

    public function index(ClientRating $rating)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();
        $search = trim((string) request('q', ''));

        // Filtros de calificación
        $filters = [
            'rating' => (string) request('rating', ''),
            'min_amount' => request('min_amount') !== null && request('min_amount') !== '' ? max(0, (float) request('min_amount')) : null,
            'min_loans' => request('min_loans') !== null && request('min_loans') !== '' ? max(0, (int) request('min_loans')) : null,
            'tag' => trim((string) request('tag', '')),
            'sort' => (string) request('sort', 'recent'),
        ];
        if (!in_array($filters['rating'], ['', 'puntuales', ...array_keys(ClientRating::CATEGORIES)], true)) {
            $filters['rating'] = '';
        }
        if (!in_array($filters['sort'], ['recent', 'amount', 'loans', 'punctuality'], true)) {
            $filters['sort'] = 'recent';
        }

        $base = Client::query();
        if (!$user->is_super_admin) {
            $base->where('clients.company_id', $company?->id);
        }
        if ($search !== '') {
            $base->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }
        if ($filters['tag'] !== '') {
            $base->where('clients.tag', $filters['tag']);
        }

        // Estadísticas de todos los clientes del filtro base (una consulta agregada).
        $stats = $rating->statsFor((clone $base)->pluck('clients.id'));

        // Cantidad por categoría (para los chips), antes de filtrar por categoría.
        $ratingCounts = $stats->countBy('rating');
        $ratingCounts['puntuales'] = ($ratingCounts['fiel'] ?? 0) + ($ratingCounts['puntual'] ?? 0);

        $matching = $stats->filter(function ($s) use ($filters) {
            if ($filters['rating'] === 'puntuales' && !in_array($s->rating, ['fiel', 'puntual'], true)) return false;
            if (!in_array($filters['rating'], ['', 'puntuales'], true) && $s->rating !== $filters['rating']) return false;
            if ($filters['min_amount'] !== null && $s->total_borrowed < $filters['min_amount']) return false;
            if ($filters['min_loans'] !== null && $s->loans_count < $filters['min_loans']) return false;
            return true;
        });

        $query = (clone $base)->with('company')
            ->withSum('loans as total_paid', 'total_paid');

        if ($matching->isEmpty()) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('clients.id', $matching->keys()->all());
        }

        if ($filters['sort'] === 'recent' || $matching->isEmpty()) {
            $query->latest('clients.created_at');
        } else {
            $sorted = match ($filters['sort']) {
                'amount' => $matching->sortByDesc(fn ($s) => [$s->total_borrowed, $s->loans_count]),
                'loans' => $matching->sortByDesc(fn ($s) => [$s->loans_count, $s->total_borrowed]),
                'punctuality' => $matching->sortByDesc(fn ($s) => [$s->punctuality ?? -1, $s->paid_interests]),
            };
            $query->orderByRaw('FIELD(clients.id, ' . $sorted->keys()->map(fn ($id) => (int) $id)->implode(',') . ')');
        }

        $clients = $query->paginate(15)->withQueryString();

        // Adjuntar estadísticas y categoría a cada cliente de la página.
        $clients->getCollection()->each(function (Client $client) use ($stats) {
            $client->setAttribute('stats', $stats->get($client->id));
        });

        // Etiquetas para el filtro: las del catálogo y las que ya tienen los clientes.
        $tagsQuery = Client::query()->whereNotNull('tag')->where('tag', '!=', '');
        $catalogQuery = ClientTag::query();
        if (!$user->is_super_admin) {
            $tagsQuery->where('company_id', $company?->id);
            $catalogQuery->where('company_id', $company?->id);
        }
        $availableTags = $catalogQuery->pluck('name')
            ->merge($tagsQuery->distinct()->pluck('tag'))
            ->unique()->sort()->values();
        $tagColors = ClientTag::colorMap($clients->getCollection()->pluck('company_id'));

        return view('clients.index', [
            'clients' => $clients,
            'search' => $search,
            'company' => $company,
            'filters' => $filters,
            'ratingCounts' => $ratingCounts,
            'availableTags' => $availableTags,
            'tagColors' => $tagColors,
            'totalBase' => $stats->count(),
            'ratingSettings' => ClientRating::settingsFor($company),
        ]);
    }

    public function create()
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && !$company) {
            return redirect()->route('clients.index')->withErrors(['error' => 'No tienes una empresa activa para crear clientes.']);
        }

        return view('clients.create', [
            'company' => $company,
        ]);
    }

    public function store()
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$user->is_super_admin && !$company) {
            return redirect()->route('clients.index')->withErrors(['error' => 'No tienes una empresa activa para crear clientes.']);
        }

        try {
            $validated = request()->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('clients', 'name')->where(fn ($q) => $q->where('company_id', $company?->id)),
                ],
                'id_number' => [
                    'nullable',
                    'string',
                    'max:30',
                    Rule::unique('clients', 'id_number')->where(fn ($q) => $q->where('company_id', $company?->id)),
                ],
                'phone' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('clients', 'phone')->where(fn ($q) => $q->where('company_id', $company?->id)),
                ],
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('clients', 'email')->where(fn ($q) => $q->where('company_id', $company?->id)),
                ],
                'address' => 'nullable|string|max:255',
                'location_url' => 'nullable|url|max:500',
                'location_lat' => 'nullable|numeric|between:-90,90|required_with:location_lng',
                'location_lng' => 'nullable|numeric|between:-180,180|required_with:location_lat',
                'notes' => 'nullable|string',
                'active' => 'sometimes|boolean',
                'photo'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'doc_files'   => 'nullable|array|max:10',
                'doc_files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
                'doc_labels'  => 'nullable|array',
                'doc_labels.*'=> 'nullable|string|max:120',
            ], [
                'name.unique'     => 'El nombre ya está registrado.',
                'id_number.unique'=> 'El documento ya está registrado.',
                'phone.unique'    => 'El teléfono ya está registrado.',
                'email.unique'    => 'El correo electrónico ya está registrado.',
            ]);
            if ($this->canTag($company?->id)) {
                $validated['tag'] = $this->validatedTag($company?->id);
            }

            $client = Client::create([
                ...$validated,
                'company_id' => $company?->id,
                'active'     => request()->boolean('active', true),
                'photo'      => null,
            ]);

            if (request()->hasFile('photo')) {
                $path = request()->file('photo')->store('client-photos/' . $client->id, 'public');
                $client->update(['photo' => $path]);
            }

            if (request()->hasFile('doc_files')) {
                foreach (request()->file('doc_files') as $i => $file) {
                    if (!$file) continue;
                    $path = ClientDocument::storeUpload($file, $client->id);
                    ClientDocument::create([
                        'client_id'     => $client->id,
                        'company_id'    => $company->id,
                        'label'         => request()->input("doc_labels.$i") ?: null,
                        'path'          => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type'     => $file->getClientMimeType(),
                        'size_bytes'    => $file->getSize(),
                    ]);
                }
            }

            return redirect()->route('clients.index')->with('success', "Cliente {$client->name} creado exitosamente.");
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al crear cliente', [
                'company_id' => $company?->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => $this->resolveClientErrorMessage($exception, 'crear')]);
        }
    }

    public function edit(Client $client)
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $client->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }

        $client->load('documents');

        return view('clients.edit', compact('client'));
    }

    public function show(Client $client, ClientRating $rating)
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $client->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }

        $client->load('documents');
        $client->loadCount(['loans', 'purchases', 'sales']);
        $client->loadSum('loans as total_borrowed', 'amount');
        $client->loadSum('loans as total_paid_sum', 'total_paid');
        $client->loadSum('purchases as total_purchased', 'amount');
        $client->loadSum('sales as total_sold', 'amount');

        $loans = $client->loans()
            ->with('product', 'creditCategory', 'branch', 'createdBy.personal')
            ->withCount(['interestPayments as unpaid_interests_count' => fn ($ip) => $ip->where('status', '!=', 'paid')])
            ->latest()
            ->get();

        $purchases = $client->purchases()
            ->with('product', 'creditCategory', 'branch', 'cashRegister', 'createdBy.personal')
            ->latest()
            ->get();

        $sales = $client->sales()
            ->with('product', 'creditCategory', 'branch', 'createdBy.personal')
            ->withCount([
                'installments as installments_total_count',
                'installments as installments_paid_count' => fn ($q) => $q->where('status', \App\Models\SaleInstallment::STATUS_PAGADA),
            ])
            ->latest()
            ->get();

        // Solo se puede vender con una caja abierta.
        $hasActiveCashSession = $this->resolveOpenCashSession((int) auth()->user()->getCurrentCompany()?->id) !== null;

        // Categoría y puntualidad según su historial de préstamos.
        $clientRating = $rating->forClient($client->id);
        $ratingSettings = ClientRating::settingsFor((int) $client->company_id);

        // Etiqueta: se puede cambiar desde la ficha con permiso "Etiquetar clientes".
        $clientTags = ClientTag::forCompany($client->company_id);
        $canTag = $this->canTag($client->company_id);

        return view('clients.show', compact('client', 'loans', 'purchases', 'sales', 'hasActiveCashSession', 'clientRating', 'ratingSettings', 'clientTags', 'canTag'));
    }

    /** Poner o quitar la etiqueta del cliente (desde su ficha). */
    public function updateTag(Client $client)
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $client->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }

        $tag = $this->validatedTag($client->company_id, $client->tag);
        $client->update(['tag' => $tag]);

        return back()->with('success', $tag ? "Etiqueta {$tag} asignada a {$client->name}." : "Se quitó la etiqueta de {$client->name}.");
    }

    /** ¿El usuario puede poner o quitar etiquetas en esta empresa? */
    private function canTag(?int $companyId): bool
    {
        $company = $companyId ? Company::find($companyId) : auth()->user()->getCurrentCompany();

        return auth()->user()->hasPermissionInCompany('clients.tag', $company);
    }

    /**
     * Etiqueta enviada: vacía (sin etiqueta) o una del catálogo de la empresa.
     * La que el cliente ya tenía se acepta aunque se haya quitado del catálogo.
     */
    private function validatedTag(?int $companyId, ?string $current = null): ?string
    {
        $names = ClientTag::forCompany($companyId)->pluck('name');
        if ($current) {
            $names->push($current);
        }

        $tag = request()->validate([
            'tag' => ['nullable', 'string', 'max:40', Rule::in($names->all())],
        ], ['tag.in' => 'Elige una etiqueta de la lista. Se crean en Clientes → Categorías.'])['tag'] ?? null;

        return $tag !== null && $tag !== '' ? $tag : null;
    }

    public function update(Client $client)
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $client->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }

        try {
            $validated = request()->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('clients', 'name')
                        ->where(fn ($q) => $q->where('company_id', $client->company_id))
                        ->ignore($client->id),
                ],
                'id_number' => [
                    'nullable',
                    'string',
                    'max:30',
                    Rule::unique('clients', 'id_number')
                        ->where(fn ($q) => $q->where('company_id', $client->company_id))
                        ->ignore($client->id),
                ],
                'phone' => [
                    'nullable',
                    'string',
                    'max:20',
                    Rule::unique('clients', 'phone')
                        ->where(fn ($q) => $q->where('company_id', $client->company_id))
                        ->ignore($client->id),
                ],
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                    Rule::unique('clients', 'email')
                        ->where(fn ($q) => $q->where('company_id', $client->company_id))
                        ->ignore($client->id),
                ],
                'address' => 'nullable|string|max:255',
                'location_url' => 'nullable|url|max:500',
                'location_lat' => 'nullable|numeric|between:-90,90|required_with:location_lng',
                'location_lng' => 'nullable|numeric|between:-180,180|required_with:location_lat',
                'notes' => 'nullable|string',
                'active' => 'sometimes|boolean',
                'photo'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
                'doc_files'   => 'nullable|array|max:10',
                'doc_files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
                'doc_labels'  => 'nullable|array',
                'doc_labels.*'=> 'nullable|string|max:120',
            ], [
                'name.unique'     => 'El nombre ya está registrado.',
                'id_number.unique'=> 'El documento ya está registrado.',
                'phone.unique'    => 'El teléfono ya está registrado.',
                'email.unique'    => 'El correo electrónico ya está registrado.',
            ]);
            // Sin permiso de etiquetar, la etiqueta actual no se toca.
            if ($this->canTag($client->company_id) && request()->has('tag')) {
                $validated['tag'] = $this->validatedTag($client->company_id, $client->tag);
            }

            $oldPhoto = $client->photo;

            $client->update([
                ...$validated,
                'active' => request()->boolean('active', false),
            ]);

            if (request()->hasFile('photo')) {
                if ($oldPhoto) Storage::disk('public')->delete($oldPhoto);
                $path = request()->file('photo')->store('client-photos/' . $client->id, 'public');
                $client->update(['photo' => $path]);
            } elseif (request('remove_photo') == '1') {
                if ($oldPhoto) Storage::disk('public')->delete($oldPhoto);
                $client->update(['photo' => null]);
            }

            if (request()->hasFile('doc_files')) {
                foreach (request()->file('doc_files') as $i => $file) {
                    if (!$file) continue;
                    $path = ClientDocument::storeUpload($file, $client->id);
                    ClientDocument::create([
                        'client_id'     => $client->id,
                        'company_id'    => $client->company_id,
                        'label'         => request()->input("doc_labels.$i") ?: null,
                        'path'          => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type'     => $file->getClientMimeType(),
                        'size_bytes'    => $file->getSize(),
                    ]);
                }
            }

            return redirect()->route('clients.index')->with('success', "Cliente {$client->name} actualizado exitosamente.");
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar cliente', [
                'client_id' => $client->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => $this->resolveClientErrorMessage($exception, 'actualizar')]);
        }
    }

    public function destroyDocument(ClientDocument $document)
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $document->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }

        $document->deleteFile();
        $document->delete();

        return back()->with('success', 'Documento eliminado.');
    }

    protected function resolveClientErrorMessage(\Throwable $exception, string $action = 'procesar')
    {
        if ($exception instanceof \Illuminate\Database\QueryException) {
            $dbMessage = strtolower($exception->errorInfo[2] ?? $exception->getMessage());
            if (str_contains($dbMessage, 'duplicate entry') || str_contains($dbMessage, 'duplicate key')) {
                if (str_contains($dbMessage, 'id_number') || str_contains($dbMessage, 'clients_id_number')) {
                    return 'Ya existe un cliente con ese documento en esta empresa.';
                }
                return 'Ya existe un cliente con datos duplicados en la base de datos.';
            }
        }

        return 'No fue posible ' . $action . ' el cliente. Intenta nuevamente.';
    }
}
