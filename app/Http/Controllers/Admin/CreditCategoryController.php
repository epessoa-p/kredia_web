<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CreditCategory;
use App\Models\CreditCategoryRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreditCategoryController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $query = CreditCategory::with(['company', 'rules'])->latest();

        if (!$user->is_super_admin) {
            $query->where('company_id', $user->getCurrentCompany()?->id);
        }

        return view('admin.credit-categories.index', [
            'categories' => $query->paginate(15),
        ]);
    }

    public function create()
    {
        $user = auth()->user();

        return view('admin.credit-categories.create', [
            'companies' => $user->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$user->getCurrentCompany()])->filter(),
            'creditCategory' => null,
            'rules' => [],
        ]);
    }

    /**
     * Resuelve la multa: devuelve [hasRate, hasFixed, penaltyRate, penaltyFixed].
     * El campo lleno conserva su valor; el otro queda en 0 (nunca null, la columna es NOT NULL).
     */
    private function resolvePenalty(array $validated): array
    {
        $rate  = $validated['penalty_rate'] ?? null;
        $fixed = $validated['penalty_fixed_amount'] ?? null;

        $hasRate  = $rate  !== null && $rate  !== '' && (float) $rate  > 0;
        $hasFixed = $fixed !== null && $fixed !== '' && (float) $fixed > 0;

        return [
            $hasRate,
            $hasFixed,
            $hasRate  ? round((float) $rate, 2)  : 0,
            $hasFixed ? round((float) $fixed, 2) : 0,
        ];
    }

    /**
     * Valida que se haya definido exactamente una forma de multa.
     * Devuelve un RedirectResponse si hay error, o null si está bien.
     */
    private function penaltyError(bool $hasRate, bool $hasFixed)
    {
        if (!$hasRate && !$hasFixed) {
            return back()->withInput()->withErrors([
                'penalty_rate' => 'Define la multa: ingresa la tasa (%) o el monto fijo (uno de los dos).',
            ]);
        }
        if ($hasRate && $hasFixed) {
            return back()->withInput()->withErrors([
                'penalty_fixed_amount' => 'Usa solo uno: tasa de multa (%) o monto fijo, no ambos.',
            ]);
        }
        return null;
    }

    public function store()
    {
        $user = auth()->user();
        $companyId = request('company_id') ?? ($user->is_super_admin ? null : $user->getCurrentCompany()?->id);

        try {
            $validated = request()->validate([
                'company_id' => 'nullable|exists:companies,id',
                'name' => 'required|string|max:255',
                'color' => ['nullable', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'icon' => ['nullable', Rule::in(array_keys(CreditCategory::ICONS))],
                'description' => 'nullable|string',
                'min_amount' => 'nullable|numeric|min:0',
                'max_amount' => ['nullable', 'numeric', 'min:0', Rule::when(request()->filled('min_amount'), ['gte:min_amount'])],
                'penalty_grace_days' => 'nullable|integer|min:0',
                'penalty_rate' => 'nullable|numeric|min:0|max:100',
                'penalty_fixed_amount' => 'nullable|numeric|min:0',
                'active' => 'sometimes|boolean',
                // Una sola regla por categoría: interés y plazo obligatorios.
                'rules' => 'required|array',
                'rules.*.name' => 'nullable|string|max:255',
                'rules.0.interest_rate' => 'required|numeric|min:0|max:999',
                'rules.*.interest_period' => 'nullable|in:monthly,quarterly,semiannual,annual',
                'rules.0.term_months_limit' => 'required|integer|min:1',
                'rules.*.min_amount' => 'nullable|numeric|min:0',
                'rules.*.max_amount' => 'nullable|numeric|min:0',
                'rules.*.active' => 'nullable|boolean',
                'rules.*.loan_kind' => 'nullable|in:pawn,installment',
                'rules.*.payment_frequency' => 'nullable|in:daily,weekly,biweekly,monthly',
                'rules.*.installment_method' => 'nullable|in:flat,french,german',
            ], self::messages());

            // La multa se define con la tasa % O el monto fijo: exactamente uno.
            [$hasRate, $hasFixed, $penaltyRate, $penaltyFixed] = $this->resolvePenalty($validated);
            if ($error = $this->penaltyError($hasRate, $hasFixed)) {
                return $error;
            }

            DB::transaction(function () use ($validated, $companyId, $penaltyRate, $penaltyFixed) {
                $category = CreditCategory::create([
                    'company_id' => $companyId,
                    'name' => $validated['name'],
                    'slug' => Str::slug($validated['name']),
                    'color' => $validated['color'] ?? null,
                    'icon' => $validated['icon'] ?? CreditCategory::guessIcon($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'min_amount' => $validated['min_amount'] ?? null,
                    'max_amount' => $validated['max_amount'] ?? null,
                    'penalty_grace_days' => $validated['penalty_grace_days'] ?? 0,
                    'penalty_rate' => $penaltyRate,
                    'penalty_fixed_amount' => $penaltyFixed,
                    'active' => request()->boolean('active', true),
                ]);

                foreach (array_slice($validated['rules'] ?? [], 0, 1) as $rule) {
                    if (!isset($rule['interest_rate'], $rule['term_months_limit'])) { // ya validados (el interés puede ser 0)
                        continue;
                    }

                    CreditCategoryRule::create([
                        'company_id' => $companyId,
                        'credit_category_id' => $category->id,
                        'name' => self::ruleName($rule['interest_rate'], $rule['interest_period'] ?? 'monthly', $rule['term_months_limit']),
                        'interest_rate' => $rule['interest_rate'],
                        'interest_period' => $rule['interest_period'] ?? 'monthly',
                        'term_months_limit' => $rule['term_months_limit'],
                        ...self::kindFields($rule),
                        // Los montos de la regla son los de la categoría (un solo campo de cada uno en el formulario).
                        'min_amount' => $validated['min_amount'] ?? null,
                        'max_amount' => $validated['max_amount'] ?? null,
                        'active' => true,
                    ]);
                }
            });

            return redirect()->route('credit-categories.index')->with('success', 'Categoría de crédito creada exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al crear categoría de crédito', [
                'company_id' => $companyId,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible crear la categoría.']);
        }
    }

    public function show(CreditCategory $creditCategory)
    {
        $this->authorizeCategory($creditCategory);
        $creditCategory->load('company', 'rules');

        return view('admin.credit-categories.show', compact('creditCategory'));
    }

    public function edit(CreditCategory $creditCategory)
    {
        $this->authorizeCategory($creditCategory);
        $user = auth()->user();

        return view('admin.credit-categories.edit', [
            'creditCategory' => $creditCategory,
            'rules' => $creditCategory->rules()->orderBy('id')->get(),
            'companies' => $user->is_super_admin
                ? Company::orderBy('name')->get()
                : collect([$user->getCurrentCompany()])->filter(),
        ]);
    }

    public function update(CreditCategory $creditCategory)
    {
        $this->authorizeCategory($creditCategory);
        $user = auth()->user();
        $companyId = $user->is_super_admin ? request('company_id', $creditCategory->company_id) : $creditCategory->company_id;

        try {
            $validated = request()->validate([
                'company_id' => 'nullable|exists:companies,id',
                'name' => 'required|string|max:255',
                'color' => ['nullable', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'icon' => ['nullable', Rule::in(array_keys(CreditCategory::ICONS))],
                'description' => 'nullable|string',
                'min_amount' => 'nullable|numeric|min:0',
                'max_amount' => ['nullable', 'numeric', 'min:0', Rule::when(request()->filled('min_amount'), ['gte:min_amount'])],
                'penalty_grace_days' => 'nullable|integer|min:0',
                'penalty_rate' => 'nullable|numeric|min:0|max:100',
                'penalty_fixed_amount' => 'nullable|numeric|min:0',
                'active' => 'sometimes|boolean',
                // Una sola regla por categoría: interés y plazo obligatorios.
                'rules' => 'required|array',
                'rules.*.id' => 'nullable|integer',
                'rules.*.name' => 'nullable|string|max:255',
                'rules.0.interest_rate' => 'required|numeric|min:0|max:999',
                'rules.*.interest_period' => 'nullable|in:monthly,quarterly,semiannual,annual',
                'rules.0.term_months_limit' => 'required|integer|min:1',
                'rules.*.min_amount' => 'nullable|numeric|min:0',
                'rules.*.max_amount' => 'nullable|numeric|min:0',
                'rules.*.active' => 'nullable|boolean',
                'rules.*.loan_kind' => 'nullable|in:pawn,installment',
                'rules.*.payment_frequency' => 'nullable|in:daily,weekly,biweekly,monthly',
                'rules.*.installment_method' => 'nullable|in:flat,french,german',
            ], self::messages());

            // La multa se define con la tasa % O el monto fijo: exactamente uno.
            [$hasRate, $hasFixed, $penaltyRate, $penaltyFixed] = $this->resolvePenalty($validated);
            if ($error = $this->penaltyError($hasRate, $hasFixed)) {
                return $error;
            }

            DB::transaction(function () use ($creditCategory, $validated, $companyId, $penaltyRate, $penaltyFixed) {
                $creditCategory->update([
                    'company_id' => $companyId,
                    'name' => $validated['name'],
                    'slug' => Str::slug($validated['name']),
                    'color' => $validated['color'] ?? null,
                    'icon' => $validated['icon'] ?? CreditCategory::guessIcon($validated['name']),
                    'description' => $validated['description'] ?? null,
                    'min_amount' => $validated['min_amount'] ?? null,
                    'max_amount' => $validated['max_amount'] ?? null,
                    'penalty_grace_days' => $validated['penalty_grace_days'] ?? 0,
                    'penalty_rate' => $penaltyRate,
                    'penalty_fixed_amount' => $penaltyFixed,
                    'active' => request()->boolean('active', false),
                ]);

                $existingIds = $creditCategory->rules()->pluck('id')->all();
                $receivedIds = [];

                // Solo la primera regla; las demás que tuviera la categoría se eliminan (soft delete).
                foreach (array_slice($validated['rules'] ?? [], 0, 1) as $ruleData) {
                    if (!isset($ruleData['interest_rate'], $ruleData['term_months_limit'])) { // ya validados (el interés puede ser 0)
                        continue;
                    }

                    $payload = [
                        'company_id' => $companyId,
                        'credit_category_id' => $creditCategory->id,
                        'name' => self::ruleName($ruleData['interest_rate'], $ruleData['interest_period'] ?? 'monthly', $ruleData['term_months_limit']),
                        'interest_rate' => $ruleData['interest_rate'],
                        'interest_period' => $ruleData['interest_period'] ?? 'monthly',
                        'term_months_limit' => $ruleData['term_months_limit'],
                        ...self::kindFields($ruleData),
                        // Los montos de la regla son los de la categoría (un solo campo de cada uno en el formulario).
                        'min_amount' => $validated['min_amount'] ?? null,
                        'max_amount' => $validated['max_amount'] ?? null,
                        'active' => true,
                    ];

                    if (!empty($ruleData['id']) && in_array((int) $ruleData['id'], $existingIds, true)) {
                        CreditCategoryRule::where('id', $ruleData['id'])->update($payload);
                        $receivedIds[] = (int) $ruleData['id'];
                    } else {
                        $created = CreditCategoryRule::create($payload);
                        $receivedIds[] = $created->id;
                    }
                }

                $toDelete = array_diff($existingIds, $receivedIds);
                if (!empty($toDelete)) {
                    CreditCategoryRule::whereIn('id', $toDelete)->delete();
                }
            });

            return redirect()->route('credit-categories.index')->with('success', 'Categoría de crédito actualizada exitosamente.');
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('Error al actualizar categoría de crédito', [
                'category_id' => $creditCategory->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar la categoría.']);
        }
    }

    public function destroy(CreditCategory $creditCategory)
    {
        $this->authorizeCategory($creditCategory);

        try {
            $creditCategory->rules()->delete();
            $creditCategory->delete();

            return redirect()->route('credit-categories.index')->with('success', 'Categoría eliminada exitosamente.');
        } catch (\Throwable $exception) {
            Log::error('Error al eliminar categoría de crédito', [
                'category_id' => $creditCategory->id,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['error' => 'No fue posible eliminar la categoría.']);
        }
    }

    /** Nombre automático de la regla, ej. "10% MENSUAL · 3 MESES" (el formulario muestra el mismo). */
    /** Tipo de crédito de la regla: empeño (sin frecuencia) o cuotas (frecuencia y cálculo). */
    protected static function kindFields(array $rule): array
    {
        $kind = ($rule['loan_kind'] ?? 'pawn') === 'installment' ? 'installment' : 'pawn';

        return [
            'loan_kind' => $kind,
            'payment_frequency' => $kind === 'installment' ? ($rule['payment_frequency'] ?? 'daily') : null,
            'installment_method' => $kind === 'installment' ? ($rule['installment_method'] ?? 'flat') : null,
        ];
    }

    public static function ruleName($rate, ?string $period, $months): string
    {
        if ($rate === null || $rate === '' || $months === null || $months === '') {
            return '';
        }

        $periods = ['monthly' => 'MENSUAL', 'quarterly' => 'TRIMESTRAL', 'semiannual' => 'SEMESTRAL', 'annual' => 'ANUAL'];
        $pct = str_replace('.', ',', (string) round((float) $rate, 2));
        $months = (int) $months;

        return trim("{$pct}% " . ($periods[$period] ?? '') . ' · ' . $months . ($months === 1 ? ' MES' : ' MESES'));
    }

    /** Mensajes en español (la app no tiene archivos de idioma). */
    protected static function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 255 caracteres.',
            'color.regex' => 'El color no es válido.',
            'icon.in' => 'Elige un ícono de la lista.',
            'max_amount.gte' => 'El monto máximo no puede ser menor que el mínimo.',
            'company_id.exists' => 'La empresa elegida no es válida.',
            'numeric' => 'El valor debe ser un número.',
            'integer' => 'El valor debe ser un número entero.',
            'min' => 'El valor no puede ser negativo.',
            'penalty_rate.max' => 'La tasa de multa no puede ser mayor a 100%.',
            'rules.required' => 'Define la regla de crédito (interés y plazo).',
            'rules.0.interest_rate.required' => 'Indica el interés % de la regla.',
            'rules.0.interest_rate.numeric' => 'El interés debe ser un número.',
            'rules.0.interest_rate.max' => 'La tasa de interés es demasiado alta.',
            'rules.0.term_months_limit.required' => 'Indica el plazo tope en meses.',
            'rules.0.term_months_limit.integer' => 'El plazo debe ser un número entero de meses.',
            'rules.0.term_months_limit.min' => 'El plazo debe ser de al menos 1 mes.',
            'rules.*.interest_period.in' => 'El periodo de interés no es válido.',
            'max' => 'El valor es demasiado largo.',
            'in' => 'El valor elegido no es válido.',
        ];
    }

    protected function authorizeCategory(CreditCategory $creditCategory): void
    {
        if (!auth()->user()->is_super_admin && $creditCategory->company_id !== auth()->user()->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
