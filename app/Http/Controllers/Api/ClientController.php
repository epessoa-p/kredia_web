<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\ClientTag;
use App\Models\Loan;
use App\Models\LoanInterestPayment;
use App\Services\ClientRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de clientes para la API.
 *
 * Expone el listado y el detalle de clientes junto con sus
 * préstamos, compras, ventas y resumen financiero.
 */
class ClientController extends Controller
{
    public function __construct(protected ClientRating $rating)
    {
    }

    /**
     * Lista los clientes de la empresa con paginación.
     *
     * Incluye conteo de préstamos, suma total prestada y suma total pagada
     * para cada cliente. Soporta búsqueda por nombre, cédula, teléfono o email,
     * y filtro por estado activo.
     *
     * @param  Request  $request  Parámetros: q (búsqueda), active (bool), per_page (1-100, def. 15)
     * @return JsonResponse  200 con colección paginada de clientes.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        $query = Client::query()
            ->withCount('loans')
            ->withSum('loans as loans_amount_sum', 'amount')
            ->withSum('loans as loans_paid_sum', 'total_paid')
            ->latest();

        if (!$user->is_super_admin) {
            $query->where('company_id', $company?->id);
        } elseif ($company) {
            $query->where('company_id', $company->id);
        }

        $q = trim((string) $request->get('q', ''));
        if ($q !== '') {
            $query->where(function ($subQuery) use ($q) {
                $subQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('id_number', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('active', (bool) $request->boolean('active'));
        }

        $tag = trim((string) $request->get('tag', ''));
        if ($tag !== '') {
            $query->where('clients.tag', $tag);
        }

        // Calificación (igual que la web): rating = fiel|puntual|regular|riesgo|nuevo|puntuales (fiel + puntual).
        $ratingFilter = (string) $request->get('rating', '');
        if (!in_array($ratingFilter, ['puntuales', ...array_keys(ClientRating::CATEGORIES)], true)) {
            $ratingFilter = '';
        }
        $withCounts = $request->boolean('with_rating_counts');
        $ratingCounts = null;

        if ($ratingFilter !== '' || $withCounts) {
            // Estadísticas de todos los clientes de la búsqueda (una consulta agregada).
            $allStats = $this->rating->statsFor((clone $query)->reorder()->pluck('clients.id'));
            if ($withCounts) {
                $ratingCounts = $allStats->countBy('rating')->all();
                $ratingCounts['puntuales'] = ($ratingCounts['fiel'] ?? 0) + ($ratingCounts['puntual'] ?? 0);
                $ratingCounts['total'] = $allStats->count();
            }
            if ($ratingFilter !== '') {
                $ids = $allStats->filter(fn ($s) => $ratingFilter === 'puntuales'
                    ? in_array($s->rating, ['fiel', 'puntual'], true)
                    : $s->rating === $ratingFilter)->keys()->all();
                $ids ? $query->whereIn('clients.id', $ids) : $query->whereRaw('1 = 0');
            }
        }

        $perPage = max(1, min(100, $request->integer('per_page', 15)));
        $clients = $query->paginate($perPage)->withQueryString();

        // Categoría, puntualidad y color de etiqueta de cada cliente de la página.
        $pageStats = $this->rating->statsFor($clients->getCollection()->pluck('id'));
        $tagColors = ClientTag::colorMap($clients->getCollection()->pluck('company_id'));
        $clients->getCollection()->each(function (Client $client) use ($pageStats, $tagColors) {
            $stats = $pageStats->get($client->id);
            $client->setAttribute('rating', $this->ratingPayload($stats));
            $client->setAttribute('tag_color', $this->tagColor($client, $tagColors));
        });

        $payload = $clients->toArray();
        if ($ratingCounts !== null) {
            $payload['rating_counts'] = $ratingCounts;
        }

        return response()->json($payload);
    }

    /**
     * PUT /clients/{client} — edita el cliente (permiso clients.edit). Igual que la web.
     *
     * Solo se cambian los campos enviados. Con archivos usar POST multipart + `_method=PUT`.
     * Foto: `photo` (archivo) o `photo_base64`; `remove_photo=1` la quita.
     * Documentos nuevos: `doc_files[]` + `doc_labels[]` (se agregan a los existentes).
     * `tag` solo se aplica con permiso clients.tag.
     */
    public function update(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClient($request, $client);

        try {
            $rules = $this->clientRules((int) $client->company_id, $client->id);
            // En la edición los campos no enviados se conservan.
            $rules['name'] = ['sometimes', ...$rules['name']];
            $rules['active'] = 'sometimes|boolean';
            $validated = $request->validate($rules, $this->clientMessages());

            $fields = collect($validated)
                ->only(['name', 'id_number', 'phone', 'email', 'address', 'notes', 'location_url', 'location_lat', 'location_lng', 'active'])
                ->all();

            // Sin permiso de etiquetar, la etiqueta actual no se toca.
            if ($request->exists('tag') && $request->user()->hasPermissionInCompany('clients.tag', $client->company)) {
                $fields['tag'] = $this->validatedTag($request, $client->company_id, $client->tag);
            }

            $client->update($fields);

            if (!$request->hasFile('photo') && empty($validated['photo_base64']) && $request->input('remove_photo') == '1') {
                if ($client->photo) {
                    Storage::disk('public')->delete($client->photo);
                }
                $client->update(['photo' => null]);
            } else {
                $this->savePhoto($request, $client, replace: true);
            }
            $this->saveDocuments($request, $client);

            $client = $client->fresh()->load('documents');
            $client->documents->each->append('url');

            return response()->json([
                'message' => "Cliente {$client->name} actualizado correctamente.",
                'client' => $client,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Error de validación.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('API update client error', ['client_id' => $client->id, 'message' => $e->getMessage()]);

            return response()->json([
                'message' => 'No fue posible actualizar el cliente. Por favor intenta nuevamente.',
                'code' => 'internal_error',
            ], 500);
        }
    }

    /** DELETE /clients/documents/{document} — elimina un documento del cliente (permiso clients.edit). */
    public function destroyDocument(Request $request, ClientDocument $document): JsonResponse
    {
        $this->authorizeClient($request, $document->client()->firstOrFail());

        $document->deleteFile();
        $document->delete();

        return response()->json(['message' => 'Documento eliminado.']);
    }

    /** Reglas del alta/edición (únicos por empresa, como la web). */
    protected function clientRules(int $companyId, ?int $ignoreId = null): array
    {
        $unique = fn (string $column) => Rule::unique('clients', $column)
            ->where(fn ($q) => $q->where('company_id', $companyId))
            ->ignore($ignoreId);

        return [
            'name'         => ['required', 'string', 'max:255', $unique('name')],
            'id_number'    => ['nullable', 'string', 'max:30', $unique('id_number')],
            'phone'        => ['nullable', 'string', 'max:20', $unique('phone')],
            'email'        => ['nullable', 'email', 'max:255', $unique('email')],
            'address'      => 'nullable|string|max:255',
            'notes'        => 'nullable|string|max:2000',
            'location_url' => 'nullable|url|max:500',
            'location_lat' => 'nullable|numeric|between:-90,90|required_with:location_lng',
            'location_lng' => 'nullable|numeric|between:-180,180|required_with:location_lat',
            'photo'        => 'nullable|file|mimes:jpeg,jpg,png,webp|max:2048',
            'photo_base64' => 'nullable|string',
            'doc_files'    => 'nullable|array|max:10',
            'doc_files.*'  => 'file|mimes:jpeg,jpg,png,webp,pdf|max:5120',
            'doc_labels'   => 'nullable|array',
            'doc_labels.*' => 'nullable|string|max:120',
        ];
    }

    protected function clientMessages(): array
    {
        return [
            'name.required'      => 'Ingresa el nombre del cliente.',
            'name.max'           => 'El nombre no puede superar los 255 caracteres.',
            'name.unique'        => 'El nombre ya está registrado en esta empresa.',
            'id_number.max'      => 'El documento no puede superar los 30 caracteres.',
            'id_number.unique'   => 'El número de documento ya está registrado.',
            'phone.max'          => 'El teléfono no puede superar los 20 caracteres.',
            'phone.unique'       => 'El teléfono ya está registrado.',
            'email.email'        => 'El correo electrónico no es válido.',
            'email.max'          => 'El correo electrónico es demasiado largo.',
            'email.unique'       => 'El correo electrónico ya está registrado.',
            'address.max'        => 'La dirección no puede superar los 255 caracteres.',
            'notes.max'          => 'Las observaciones no pueden superar los 2000 caracteres.',
            'location_url.url'   => 'El link de ubicación no es válido.',
            'location_url.max'   => 'El link de ubicación es demasiado largo.',
            'location_lat.numeric' => 'La latitud no es válida.',
            'location_lat.between' => 'La latitud no es válida.',
            'location_lng.numeric' => 'La longitud no es válida.',
            'location_lng.between' => 'La longitud no es válida.',
            'location_lat.required_with' => 'Si envías longitud, la latitud es obligatoria.',
            'location_lng.required_with' => 'Si envías latitud, la longitud es obligatoria.',
            'active.boolean'     => 'El estado no es válido.',
            'photo.file'         => 'La foto debe ser un archivo.',
            'photo.mimes'        => 'La foto debe ser JPG, PNG o WEBP.',
            'photo.max'          => 'La foto no puede pesar más de 2 MB.',
            'doc_files.max'      => 'Puedes adjuntar como máximo 10 documentos a la vez.',
            'doc_files.*.file'   => 'Cada documento debe ser un archivo.',
            'doc_files.*.mimes'  => 'Los documentos deben ser imágenes (JPG/PNG/WEBP) o PDF.',
            'doc_files.*.max'    => 'Cada documento puede pesar como máximo 5 MB.',
            'doc_labels.*.max'   => 'El nombre del documento no puede superar los 120 caracteres.',
            '*.string'           => 'El valor debe ser texto.',
        ];
    }

    /** Guarda la foto enviada (archivo o base64). Con $replace borra la anterior. */
    protected function savePhoto(Request $request, Client $client, bool $replace = false): void
    {
        $path = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('client-photos/' . $client->id, 'public');
        } elseif ($request->filled('photo_base64')) {
            $b64 = (string) $request->input('photo_base64');
            $binary = base64_decode(str_contains($b64, ',') ? explode(',', $b64, 2)[1] : $b64, true);
            if ($binary) {
                $path = 'client-photos/' . $client->id . '/foto-' . now()->format('YmdHisu') . '.jpg';
                Storage::disk('public')->put($path, $binary);
            }
        }
        if (!$path) {
            return;
        }
        if ($replace && $client->photo) {
            Storage::disk('public')->delete($client->photo);
        }
        $client->update(['photo' => $path]);
    }

    /** Agrega los documentos enviados (doc_files[] + doc_labels[]). */
    protected function saveDocuments(Request $request, Client $client): array
    {
        $documents = [];
        foreach ((array) $request->file('doc_files', []) as $i => $file) {
            if (!$file) {
                continue;
            }
            $doc = ClientDocument::create([
                'client_id'     => $client->id,
                'company_id'    => $client->company_id,
                'label'         => $request->input("doc_labels.$i") ?: null,
                'path'          => ClientDocument::storeUpload($file, $client->id),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getClientMimeType(),
                'size_bytes'    => $file->getSize(),
            ]);
            $documents[] = ['id' => $doc->id, 'label' => $doc->label, 'url' => $doc->url];
        }

        return $documents;
    }

    /**
     * GET /clients/tags — catálogo de etiquetas de la empresa (se administran en la web,
     * Clientes → Categorías) y si el usuario puede asignarlas.
     */
    public function tags(Request $request): JsonResponse
    {
        $company = $request->attributes->get('apiCompany');

        return response()->json([
            'tags' => $this->tagOptions($company?->id),
            'can_tag' => $request->user()->hasPermissionInCompany('clients.tag', $company),
        ]);
    }

    /** PATCH /clients/{client}/tag — asigna (tag = nombre) o quita (tag vacío) la etiqueta. */
    public function updateTag(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClient($request, $client);

        $tag = $this->validatedTag($request, $client->company_id, $client->tag);
        $client->update(['tag' => $tag]);

        return response()->json([
            'message' => $tag ? "Etiqueta {$tag} asignada a {$client->name}." : "Se quitó la etiqueta de {$client->name}.",
            'tag' => $tag,
            'tag_color' => $this->tagColor($client, ClientTag::colorMap([$client->company_id])),
        ]);
    }

    /** @return array<int, array{id:int, name:string, color:string}> */
    protected function tagOptions(?int $companyId): array
    {
        return ClientTag::forCompany($companyId)
            ->map(fn (ClientTag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color ?: ClientTag::DEFAULT_COLOR])
            ->values()
            ->all();
    }

    /** Color de la etiqueta del cliente (null si no tiene; color por defecto si ya no está en el catálogo). */
    protected function tagColor(Client $client, array $colorMap): ?string
    {
        if (!$client->tag) {
            return null;
        }

        return $colorMap[$client->company_id][$client->tag] ?? ClientTag::DEFAULT_COLOR;
    }

    /**
     * Igual que la web: vacía (sin etiqueta) o una del catálogo de la empresa;
     * la que el cliente ya tenía se acepta aunque se haya quitado del catálogo.
     */
    protected function validatedTag(Request $request, ?int $companyId, ?string $current = null): ?string
    {
        $names = ClientTag::forCompany($companyId)->pluck('name');
        if ($current) {
            $names->push($current);
        }

        $tag = $request->validate([
            'tag' => ['nullable', 'string', 'max:40', Rule::in($names->all())],
        ], [
            'tag.in' => 'Elige una etiqueta de la lista. Se crean en la web: Clientes → Categorías.',
            'tag.string' => 'La etiqueta no es válida.',
            'tag.max' => 'La etiqueta no es válida.',
        ])['tag'] ?? null;

        return $tag !== null && $tag !== '' ? $tag : null;
    }

    /** Categoría + estadísticas listas para mostrar en la app. */
    protected function ratingPayload(?object $stats): ?array
    {
        if (!$stats) {
            return null;
        }
        $meta = ClientRating::meta($stats->rating);

        return [
            'key' => $stats->rating,
            'label' => $meta['label'],
            'color' => $meta['color'],
            'bg' => $meta['bg'],
            'border' => $meta['border'],
            'punctuality' => $stats->punctuality,
            'loans_count' => $stats->loans_count,
            'finished_count' => $stats->finished_count,
            'defaulted_count' => $stats->defaulted_count,
            // Préstamos abiertos en mora o incumplidos hoy.
            'risk_open_count' => $stats->risk_open_count,
            'penalty_loans_count' => $stats->penalty_loans_count,
            'total_borrowed' => $stats->total_borrowed,
            'paid_interests' => $stats->paid_interests,
            'on_time_interests' => $stats->on_time_interests,
            'late_interests' => $stats->late_interests,
        ];
    }

    /**
     * Registra un nuevo cliente desde la app móvil.
     *
     * Acepta foto como archivo multipart (`photo`) o en base64 (`photo_base64`).
     * El nombre, documento, teléfono y correo deben ser únicos por empresa.
     *
     * @param  Request  $request
     * @return JsonResponse  201 con el cliente creado, 422 con errores de validación,
     *                        403 sin empresa activa, 500 error inesperado.
     */
    public function store(Request $request): JsonResponse
    {
        $user    = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$company) {
            return response()->json(['message' => 'No tienes empresa activa asignada.'], 403);
        }

        try {
            $validated = $request->validate($this->clientRules((int) $company->id), $this->clientMessages());

            // Etiqueta: solo con permiso "Etiquetar clientes" (igual que la web).
            $tag = null;
            if ($request->filled('tag') && $user->hasPermissionInCompany('clients.tag', $company)) {
                $tag = $this->validatedTag($request, $company->id);
            }

            $client = Client::create([
                'tag'          => $tag,
                'company_id'   => $company->id,
                'name'         => $validated['name'],
                'id_number'    => $validated['id_number'] ?? null,
                'phone'        => $validated['phone'] ?? null,
                'email'        => $validated['email'] ?? null,
                'address'      => $validated['address'] ?? null,
                'notes'        => $validated['notes'] ?? null,
                'location_url' => $validated['location_url'] ?? null,
                'location_lat' => $validated['location_lat'] ?? null,
                'location_lng' => $validated['location_lng'] ?? null,
                'active'       => true,
                'photo'        => null,
            ]);

            $this->savePhoto($request, $client);
            $documents = $this->saveDocuments($request, $client);

            $client->refresh();

            return response()->json([
                'message' => 'Cliente registrado exitosamente.',
                'client'  => [
                    'id'           => $client->id,
                    'name'         => $client->name,
                    'id_number'    => $client->id_number,
                    'phone'        => $client->phone,
                    'email'        => $client->email,
                    'address'      => $client->address,
                    'notes'        => $client->notes,
                    'location_url' => $client->location_url,
                    'location_lat' => $client->location_lat,
                    'location_lng' => $client->location_lng,
                    'photo_url'    => $client->photo_url,
                    'tag'          => $client->tag,
                    'active'       => $client->active,
                    'created_at'   => $client->created_at?->toIso8601String(),
                    'documents'    => $documents,
                ],
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('API store client error', [
                'user_id'    => $user->id,
                'company_id' => $company->id,
                'exception'  => get_class($e),
                'message'    => $e->getMessage(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'No fue posible registrar el cliente. Por favor intenta nuevamente.',
                'code'    => 'internal_error',
            ], 500);
        }
    }

    /**
     * Retorna el detalle completo de un cliente.
     *
     * Carga explícitamente hasta 20 préstamos, 20 compras y 20 ventas con sus
     * relaciones de producto y sucursal. Cada préstamo incluye atributos adicionales
     * de compatibilidad (status_legacy, status_label). Devuelve también un resumen
     * con totales agregados de préstamos.
     *
     * @param  Request  $request
     * @param  Client   $client
     * @return JsonResponse  200 con client, loans, purchases, sales y summary.
     */
    public function show(Request $request, Client $client): JsonResponse
    {
        $this->authorizeClient($request, $client);

        // Cargar colecciones de forma explícita para asegurar salida consistente en móvil.
        $loans = $client->loans()
            ->with(['product', 'branch'])
            ->addSelect([
                'loans.*',
                'unpaid_interest_total' => LoanInterestPayment::selectRaw('COALESCE(SUM(interest_amount - paid_amount), 0)')
                    ->whereColumn('loan_id', 'loans.id')
                    ->where('status', '!=', 'paid'),
            ])
            ->latest()
            ->limit(20)
            ->get();

        $loans->each(function ($loan) {
            $loan->setAttribute('status_legacy', $this->toLegacyLoanStatus((string) $loan->status));
            $loan->setAttribute('status_label', Loan::STATUS_LABELS[$loan->status] ?? (string) $loan->status);
        });

        $purchases = $client->purchases()
            ->with(['product', 'branch'])
            ->latest()
            ->limit(20)
            ->get();

        $sales = $client->sales()
            ->with(['product', 'branch'])
            ->latest()
            ->limit(20)
            ->get();

        // Mantener compatibilidad con el contrato anterior (client con relaciones).
        $client->setRelation('loans', $loans);
        $client->setRelation('purchases', $purchases);
        $client->setRelation('sales', $sales);

        $loansCount = $client->loans()->count();
        $purchasesCount = $client->purchases()->count();
        $salesCount = $client->sales()->count();
        $loansAmountSum = (float) $client->loans()->sum('amount');
        $loansPaidSum = (float) $client->loans()->sum('total_paid');

        // Categoría e historial de pagos (mismas reglas de la empresa que en la web).
        $client->setAttribute('rating', $this->ratingPayload($this->rating->forClient($client->id)));
        $client->setAttribute('tag_color', $this->tagColor($client, ClientTag::colorMap([$client->company_id])));
        $client->load('documents');
        $client->documents->each->append('url');
        $settings = ClientRating::settingsFor((int) $client->company_id);

        return response()->json([
            'client' => $client,
            // Etiquetas para el selector de la ficha (asignar requiere el permiso clients.tag).
            'tag_options' => $this->tagOptions((int) $client->company_id),
            'can_tag' => $request->user()->hasPermissionInCompany('clients.tag', $client->company),
            'rating_settings' => [
                'tolerance_days' => $settings['tolerance_days'],
                'punctual_max_late' => $settings['punctual_max_late'],
            ],
            // Respuesta explícita para clientes móviles.
            'loans' => $loans,
            'purchases' => $purchases,
            'sales' => $sales,
            'summary' => [
                'loans_count' => $loansCount,
                'purchases_count' => $purchasesCount,
                'sales_count' => $salesCount,
                'loans_amount_sum' => $loansAmountSum,
                'loans_paid_sum' => $loansPaidSum,
            ],
        ]);
    }

    /**
     * Convierte un estado de préstamo al valor legacy usado por clientes móviles anteriores.
     *
     * Usa el mapa inverso de Loan::LEGACY_STATUS_MAP para mantener retrocompatibilidad
     * con versiones de la app que esperan los valores anteriores del campo status.
     *
     * @param  string  $status  Estado actual del préstamo.
     * @return string|null  Valor legacy correspondiente, o null si no tiene equivalente.
     */
    protected function toLegacyLoanStatus(string $status): ?string
    {
        $reverseMap = array_flip(Loan::LEGACY_STATUS_MAP);
        return $reverseMap[$status] ?? null;
    }

    /**
     * Verifica que el usuario tenga acceso al cliente.
     *
     * Aborta con 403 si el cliente no pertenece a la empresa del token.
     *
     * @param  Request  $request
     * @param  Client   $client
     */
    protected function authorizeClient(Request $request, Client $client): void
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user->is_super_admin && $client->company_id !== $company?->id) {
            abort(403, 'No tienes acceso a este cliente.');
        }

        if ($user->is_super_admin && $company && $client->company_id !== $company->id) {
            abort(403, 'El cliente no pertenece a la empresa del token.');
        }
    }
}
