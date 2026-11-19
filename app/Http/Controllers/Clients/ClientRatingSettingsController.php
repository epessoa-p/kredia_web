<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientTag;
use App\Models\Company;
use App\Services\ClientRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Panel "Configurar categorías de clientes": reglas de Fiel, Puntual, Riesgo…
 * de cada empresa (companies.client_rating_settings).
 */
class ClientRatingSettingsController extends Controller
{
    public function edit(Request $request, ClientRating $rating)
    {
        $companies = auth()->user()->is_super_admin
            ? Company::where('active', true)->orderBy('name')->get(['id', 'name'])
            : collect();
        $company = $this->company($request, $companies);

        if (!$company && $companies->isEmpty()) {
            return redirect()->route('clients.index')->with('error', 'No tienes una empresa seleccionada.');
        }

        $settings = $company ? ClientRating::settingsFor($company) : ClientRating::defaults();

        return view('clients.rating-settings', [
            'company' => $company,
            'companies' => $companies,
            'settings' => $settings,
            'defaults' => ClientRating::defaults(),
            'isCustom' => (bool) $company?->client_rating_settings,
            'counts' => $company ? $this->counts($rating, $company, $settings) : [],
            'tags' => ClientTag::forCompany($company?->id),
            'tagUsage' => $company
                ? Client::where('company_id', $company->id)->whereNotNull('tag')->groupBy('tag')->selectRaw('tag, COUNT(*) AS total')->pluck('total', 'tag')
                : collect(),
        ]);
    }

    public function update(Request $request)
    {
        $company = $this->company($request);
        if (!$company) {
            return back()->with('error', 'Selecciona una empresa.');
        }

        if ($request->input('action') === 'reset') {
            $company->update(['client_rating_settings' => null]);

            return redirect()->route('clients.rating-settings.edit', $this->companyParam($company))
                ->with('success', 'Se restablecieron los valores por defecto de las categorías.');
        }

        $data = $this->validated($request);
        $company->update(['client_rating_settings' => ClientRating::normalize($data, ClientRating::defaults())]);

        return redirect()->route('clients.rating-settings.edit', $this->companyParam($company))
            ->with('success', 'Configuración de categorías guardada. La lista de clientes ya usa los nuevos valores.');
    }

    /** Cantidad de clientes por categoría con los valores del formulario (sin guardar). */
    public function preview(Request $request, ClientRating $rating): JsonResponse
    {
        $company = $this->company($request);
        if (!$company) {
            return response()->json(['counts' => []]);
        }

        $settings = ClientRating::normalize($this->validated($request), ClientRating::defaults());

        return response()->json([
            'counts' => $this->counts($rating, $company, $settings),
            'current' => $this->counts($rating, $company, ClientRating::settingsFor($company)),
        ]);
    }

    // ── Etiquetas de clientes ──────────────────────────────────────

    public function storeTag(Request $request)
    {
        $company = $this->company($request);
        if (!$company) {
            return back()->with('error', 'Selecciona una empresa.');
        }

        $data = $this->validatedTag($request, $company);
        ClientTag::create(['company_id' => $company->id] + $data);

        return $this->backToTags($company, "Etiqueta {$data['name']} creada. Ya se puede asignar a los clientes.");
    }

    public function updateTag(Request $request, ClientTag $tag)
    {
        $company = $this->tagCompany($tag);
        $data = $this->validatedTag($request, $company, $tag);

        DB::transaction(function () use ($tag, $data) {
            // Al renombrar, los clientes que la tenían conservan la etiqueta.
            if ($data['name'] !== $tag->name) {
                Client::where('company_id', $tag->company_id)->where('tag', $tag->name)->update(['tag' => $data['name']]);
            }
            $tag->update($data);
        });

        return $this->backToTags($company, "Etiqueta {$data['name']} actualizada.");
    }

    public function destroyTag(ClientTag $tag)
    {
        $company = $this->tagCompany($tag);

        $removed = DB::transaction(function () use ($tag) {
            $count = Client::where('company_id', $tag->company_id)->where('tag', $tag->name)->update(['tag' => null]);
            $tag->delete();

            return $count;
        });

        return $this->backToTags($company, "Etiqueta {$tag->name} eliminada" . ($removed ? " y quitada de {$removed} cliente(s)." : '.'));
    }

    private function validatedTag(Request $request, Company $company, ?ClientTag $tag = null): array
    {
        $request->merge([
            'name' => ClientTag::normalizeName($request->input('name')),
            'color' => $request->filled('color') ? strtolower(trim((string) $request->input('color'))) : null,
        ]);

        $data = $request->validateWithBag('tags', [
            'name' => [
                'required', 'string', 'max:40',
                Rule::unique('client_tags', 'name')->where('company_id', $company->id)->ignore($tag?->id),
            ],
            'color' => ['nullable', Rule::in(array_keys(ClientTag::COLORS))],
        ], [
            'name.required' => 'Escribe el nombre de la etiqueta.',
            'name.max' => 'El nombre de la etiqueta puede tener hasta 40 caracteres.',
            'name.unique' => 'Ya existe una etiqueta con ese nombre.',
            'color.in' => 'Elige uno de los colores de la lista.',
        ], ['name' => 'nombre de la etiqueta']);

        $data['color'] = $data['color'] ?? ClientTag::DEFAULT_COLOR;

        return $data;
    }

    /** Empresa de la etiqueta, verificando que el usuario pueda administrarla. */
    private function tagCompany(ClientTag $tag): Company
    {
        $user = auth()->user();
        if (!$user->is_super_admin && $tag->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }

        return $tag->company;
    }

    private function backToTags(Company $company, string $message)
    {
        return redirect()->to(route('clients.rating-settings.edit', $this->companyParam($company)) . '#etiquetas')
            ->with('success', $message);
    }

    private function validated(Request $request): array
    {
        $request->merge(['loyal_mode' => strtolower((string) $request->input('loyal_mode'))]);

        $data = $request->validate([
            'tolerance_days' => 'required|integer|min:0|max:60',
            'punctual_max_late' => 'required|integer|min:0|max:100',
            'penalty_breaks' => 'nullable|boolean',
            'loyal_min_loans' => 'required|integer|min:1|max:100',
            'loyal_min_amount' => 'required|numeric|min:0|max:10000000',
            'loyal_mode' => 'required|in:any,all',
            'loyal_min_punctuality' => 'required|integer|min:0|max:100',
            'risk_include_mora' => 'nullable|boolean',
        ], [
            'required' => 'Completa el campo :attribute.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'min' => 'El campo :attribute no puede ser menor a :min.',
            'max' => 'El campo :attribute no puede ser mayor a :max.',
            'loyal_mode.in' => 'Elige si debe cumplir una o ambas condiciones.',
        ], [
            'tolerance_days' => 'días de tolerancia',
            'punctual_max_late' => 'atrasos permitidos',
            'loyal_min_loans' => 'préstamos mínimos',
            'loyal_min_amount' => 'monto mínimo prestado',
            'loyal_min_punctuality' => 'puntualidad mínima',
        ]);

        // Los checkbox desmarcados no se envían.
        $data['penalty_breaks'] = $request->boolean('penalty_breaks');
        $data['risk_include_mora'] = $request->boolean('risk_include_mora');

        return $data;
    }

    /** Clientes por categoría de la empresa. */
    private function counts(ClientRating $rating, Company $company, array $settings): array
    {
        $counts = $rating->statsFor(Client::where('company_id', $company->id)->pluck('id'), $settings)->countBy('rating');

        return collect(ClientRating::CATEGORIES)->keys()->mapWithKeys(fn ($key) => [$key => (int) ($counts[$key] ?? 0)])->all();
    }

    /** Empresa a configurar: la del usuario, o la elegida por el super admin. */
    private function company(Request $request, $companies = null): ?Company
    {
        $user = auth()->user();
        if (!$user->is_super_admin) {
            return $user->getCurrentCompany();
        }

        $companies ??= Company::where('active', true)->orderBy('name')->get(['id', 'name']);
        $id = (int) $request->input('company_id', session('current_company_id'));
        if (!$id && $companies->count() === 1) {
            $id = (int) $companies->first()->id;
        }

        return $id ? Company::find($id) : null;
    }

    private function companyParam(Company $company): array
    {
        return auth()->user()->is_super_admin ? ['company_id' => $company->id] : [];
    }
}
