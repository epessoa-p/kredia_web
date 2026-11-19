@php
    // Ícono elegido: el enviado, el de la categoría o el sugerido por su nombre.
    $selectedIcon = old('icon', $creditCategory?->icon_class ?? \App\Models\CreditCategory::guessIcon(old('name')));
@endphp
@push('styles')
<style>
    .cat-icon-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); gap: .4rem; }
    @media (max-width: 575.98px) {
        .cat-icon-grid { grid-template-columns: repeat(auto-fill, minmax(72px, 1fr)); gap: .3rem; }
        .cat-icon span { font-size: var(--fs-xs); }
    }
    .cat-icon {
        display: flex; flex-direction: column; align-items: center; gap: .2rem; padding: .45rem .25rem;
        border: 1.5px solid #e5e7eb; border-radius: .6rem; cursor: pointer; text-align: center; background: #fff;
        transition: all .15s ease; user-select: none;
    }
    .cat-icon i { font-size: var(--fs-lg); color: #495057; }
    .cat-icon span { font-size: var(--fs-xs); color: #6c757d; line-height: 1.1; }
    .cat-icon:hover { border-color: #a5b4fc; }
    .cat-icon:has(input:checked) { border-color: #4f46e5; background: #eef2ff; box-shadow: 0 0 0 3px rgba(79, 70, 229, .15); }
    .cat-icon:has(input:checked) i { color: #4f46e5; }
    .cat-icon:has(input:focus-visible) { outline: 2px solid #4f46e5; outline-offset: 2px; }
    .cat-preview {
        --cat-color: #3B82F6;
        display: inline-flex; flex-direction: column; align-items: center; justify-content: center; gap: .25rem;
        min-width: 118px; min-height: 64px; padding: .4rem .6rem; border-radius: .75rem;
        border: 1.5px solid var(--cat-color); background: color-mix(in srgb, var(--cat-color) 10%, #fff);
    }
    .cat-preview i { font-size: var(--fs-lg); color: var(--cat-color); }
    .rule-name-preview { font-size: var(--fs-base); color: #6c757d; background: #f8f9fa; border-radius: .5rem; padding: .45rem .7rem; }
    .rule-name-preview strong { color: #212529; }
    .cat-preview span { font-size: var(--fs-xs); font-weight: 600; color: #212529; }
</style>
@endpush
<div class="container-fluid">
    <x-page-header :title="$creditCategory ? 'Editar categoría' : 'Nueva categoría de crédito'" icon="tags" subtitle="Diseña reglas por producto con interés mensual, trimestral, semestral o anual." :back="route('credit-categories.index')" />

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ $action }}" method="POST" id="credit-category-form">
                @csrf
                @if($method !== 'POST') @method($method) @endif

                <div class="row g-3 mb-4">
                    @if($companies->count() > 1)
                        <div class="col-md-6">
                            <label class="form-label">Empresa</label>
                            <select name="company_id" class="form-select form-select-sm">
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ (string) old('company_id', $creditCategory?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="company_id" value="{{ $companies->first()?->id }}">
                    @endif
                    <div class="col-md-6">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="name" id="cat-name" class="form-control form-control-sm @error('name') is-invalid @enderror" value="{{ old('name', $creditCategory?->name) }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Color</label>
                        <input type="color" name="color" id="cat-color" class="form-control form-control-color" value="{{ old('color', $creditCategory?->color ?? '#3B82F6') }}" title="Selecciona color">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">Vista previa</label>
                        <div class="cat-preview" id="cat-preview">
                            <i class="bi {{ $selectedIcon }}"></i>
                            <span>{{ old('name', $creditCategory?->name) ?: 'CATEGORÍA' }}</span>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label mb-1">Ícono</label>
                        <div class="form-text mt-0 mb-2">Se muestra en las tarjetas de categoría al ingresar un producto.@if(!$creditCategory) Se sugiere uno según el nombre; puedes elegir otro.@endif</div>
                        <div class="cat-icon-grid" role="radiogroup" aria-label="Ícono">
                            @foreach(\App\Models\CreditCategory::ICONS as $iconClass => $iconLabel)
                                <label class="cat-icon" title="{{ $iconLabel }}">
                                    <input type="radio" name="icon" value="{{ $iconClass }}" class="visually-hidden" {{ $selectedIcon === $iconClass ? 'checked' : '' }}>
                                    <i class="bi {{ $iconClass }}"></i>
                                    <span>{{ $iconLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('icon')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Monto mínimo ({{ $currencySymbol }})</label>
                        <input type="number" step="0.01" min="0" name="min_amount" class="form-control form-control-sm @error('min_amount') is-invalid @enderror" value="{{ old('min_amount', $creditCategory?->min_amount) }}">
                        @error('min_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Monto máximo ({{ $currencySymbol }})</label>
                        <input type="number" step="0.01" min="0" name="max_amount" class="form-control form-control-sm @error('max_amount') is-invalid @enderror" value="{{ old('max_amount', $creditCategory?->max_amount) }}">
                        @error('max_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 mt-1"><small class="text-muted">Montos probables de préstamo en esta categoría; se usan también para su regla de crédito.</small></div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2">{{ old('description', $creditCategory?->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $creditCategory?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Categoría activa</label>
                        </div>
                    </div>
                </div>

                <div class="card bg-light border mb-4">
                    <div class="card-body">
                        <h6 class="mb-1"><i class="bi bi-exclamation-triangle text-warning"></i> Condiciones de multa / mora</h6>
                        <p class="text-muted small mb-3">Define la multa con la <strong>tasa (%)</strong> <em>o</em> el <strong>monto fijo</strong> — solo uno de los dos.</p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Días de gracia</label>
                                <input type="number" name="penalty_grace_days" class="form-control form-control-sm" value="{{ old('penalty_grace_days', $creditCategory?->penalty_grace_days) }}" min="0" placeholder="Ej: 5">
                                <small class="text-muted">Días después del vencimiento antes de aplicar multa</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tasa de multa %</label>
                                <input type="number" step="0.01" name="penalty_rate" class="form-control form-control-sm" value="{{ old('penalty_rate', $creditCategory?->penalty_rate) }}" min="0" placeholder="Ej: 3">
                                <small class="text-muted">Porcentaje sobre la liquidación total (capital + interés)</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Monto fijo de multa ({{ $currencySymbol }})</label>
                                <input type="number" step="0.01" name="penalty_fixed_amount" class="form-control form-control-sm" value="{{ old('penalty_fixed_amount', $creditCategory?->penalty_fixed_amount) }}" min="0" placeholder="Ej: 50">
                                <small class="text-muted">Monto fijo alternativo a la tasa</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Una sola regla de crédito por categoría (la usa el préstamo para el interés y el plazo). --}}
                @php
                    $oldRule = old('rules.0');
                    $existingRule = is_array($rules) ? ($rules[0] ?? null) : $rules?->first()?->toArray();
                    $rule = is_array($oldRule) ? $oldRule : ($existingRule ?? []);
                @endphp
                <div class="mb-2">
                    <h5 class="mb-0"><i class="bi bi-sliders"></i> Regla de crédito</h5>
                    <small class="text-muted">Interés y plazo que se aplican a los préstamos de esta categoría.</small>
                </div>
                <div class="border rounded p-3 mb-4">
                    <input type="hidden" name="rules[0][id]" value="{{ $rule['id'] ?? '' }}">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Interés % <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="rules[0][interest_rate]" class="form-control form-control-sm @error('rules.0.interest_rate') is-invalid @enderror" value="{{ $rule['interest_rate'] ?? '' }}" required>
                            @error('rules.0.interest_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4"><label class="form-label">Periodicidad</label><select name="rules[0][interest_period]" class="form-select form-select-sm">@foreach(['monthly' => 'Mensual', 'quarterly' => 'Trimestral', 'semiannual' => 'Semestral', 'annual' => 'Anual'] as $periodValue => $periodLabel)<option value="{{ $periodValue }}" {{ ($rule['interest_period'] ?? 'monthly') === $periodValue ? 'selected' : '' }}>{{ $periodLabel }}</option>@endforeach</select></div>
                        <div class="col-md-4">
                            <label class="form-label">Plazo tope (meses) <span class="text-danger">*</span></label>
                            <input type="number" min="1" name="rules[0][term_months_limit]" class="form-control form-control-sm @error('rules.0.term_months_limit') is-invalid @enderror" value="{{ $rule['term_months_limit'] ?? '' }}" required>
                            @error('rules.0.term_months_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        {{-- Tipo de crédito: empeño (interés por periodo con prenda) o cuotas (plan de pagos). --}}
                        <div class="col-md-4">
                            <label class="form-label" for="rule_kind">Tipo de crédito</label>
                            <select id="rule_kind" name="rules[0][loan_kind]" class="form-select form-select-sm" onchange="document.getElementById('installment-fields').classList.toggle('d-none', this.value !== 'installment')">
                                <option value="pawn" @selected(($rule['loan_kind'] ?? 'pawn') === 'pawn')>Empeño (con prenda)</option>
                                <option value="installment" @selected(($rule['loan_kind'] ?? 'pawn') === 'installment')>Préstamo en cuotas</option>
                            </select>
                        </div>
                        <div id="installment-fields" class="col-md-8 {{ ($rule['loan_kind'] ?? 'pawn') === 'installment' ? '' : 'd-none' }}">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="rule_frequency">Frecuencia de cuotas</label>
                                    <select id="rule_frequency" name="rules[0][payment_frequency]" class="form-select form-select-sm">
                                        @foreach(\App\Services\Installments\ScheduleCalculator::FREQUENCIES as $k => $l)
                                            <option value="{{ $k }}" @selected(($rule['payment_frequency'] ?? 'daily') === $k)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="rule_method">Cálculo</label>
                                    <select id="rule_method" name="rules[0][installment_method]" class="form-select form-select-sm">
                                        @foreach(\App\Services\Installments\ScheduleCalculator::METHODS as $k => $l)
                                            <option value="{{ $k }}" @selected(($rule['installment_method'] ?? 'flat') === $k)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 form-text">En cuotas, el interés % se toma como tasa MENSUAL y se prorratea por la frecuencia.</div>
                            </div>
                        </div>
                    </div>
                    <div class="rule-name-preview mt-3">
                        <i class="bi bi-tag me-1"></i> Nombre de la regla (automático):
                        <strong id="rule-name-preview">{{ \App\Http\Controllers\Admin\CreditCategoryController::ruleName($rule['interest_rate'] ?? null, $rule['interest_period'] ?? 'monthly', $rule['term_months_limit'] ?? null) ?: '—' }}</strong>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <x-btn type="submit" icon="check-lg">Guardar categoría</x-btn>
                    <x-btn variant="secondary" :href="route('credit-categories.index')">Cancelar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Ícono: vista previa con el color y el nombre; en una categoría nueva se sugiere según el nombre
    // hasta que el usuario elija uno a mano.
    (() => {
        const name = document.getElementById('cat-name');
        const color = document.getElementById('cat-color');
        const preview = document.getElementById('cat-preview');
        if (!name || !color || !preview) return;
        const isNew = @json(!$creditCategory);
        let manual = @json((bool) old('icon'));

        const guess = (n) => {
            n = (n || '').toUpperCase();
            const rules = [
                [['CELULAR', 'TELEF'], 'bi-phone'], [['COMPU', 'LAPTOP'], 'bi-laptop'], [['HELADER', 'REFRI'], 'bi-snow'],
                [['ELECTRO'], 'bi-plug'], [['HERRAM'], 'bi-tools'], [['TELEV'], 'bi-tv'], [['JOYA'], 'bi-gem'],
                [['GARRAF'], 'bi-fire'], [['VEHIC', 'AUTO'], 'bi-truck'], [['MOTO', 'BICI'], 'bi-bicycle'], [['ROPA'], 'bi-bag'],
            ];
            const hit = rules.find(([words]) => words.some(w => n.includes(w)));
            return hit ? hit[1] : 'bi-box-seam';
        };
        const checked = () => document.querySelector('input[name="icon"]:checked');
        const paint = () => {
            preview.style.setProperty('--cat-color', color.value);
            preview.querySelector('i').className = 'bi ' + (checked()?.value || 'bi-box-seam');
            preview.querySelector('span').textContent = (name.value || 'CATEGORÍA').toUpperCase();
        };

        document.querySelectorAll('input[name="icon"]').forEach(r => r.addEventListener('change', () => { manual = true; paint(); }));
        name.addEventListener('input', () => {
            if (isNew && !manual) {
                const r = document.querySelector(`input[name="icon"][value="${guess(name.value)}"]`);
                if (r) r.checked = true;
            }
            paint();
        });
        color.addEventListener('input', paint);
        paint();
    })();

    // Nombre de la regla: se arma solo con el interés, la periodicidad y el plazo (igual que en el servidor).
    (() => {
        const rate = document.querySelector('input[name="rules[0][interest_rate]"]');
        const period = document.querySelector('select[name="rules[0][interest_period]"]');
        const months = document.querySelector('input[name="rules[0][term_months_limit]"]');
        const out = document.getElementById('rule-name-preview');
        if (!rate || !period || !months || !out) return;
        const periods = { monthly: 'MENSUAL', quarterly: 'TRIMESTRAL', semiannual: 'SEMESTRAL', annual: 'ANUAL' };
        const paint = () => {
            if (rate.value === '' || months.value === '') { out.textContent = '—'; return; }
            const r = parseFloat(rate.value), m = parseInt(months.value, 10);
            if (isNaN(r) || isNaN(m)) { out.textContent = '—'; return; }
            const pct = String(Math.round(r * 100) / 100).replace('.', ',');
            out.textContent = `${pct}% ${periods[period.value] || ''} · ${m} ${m === 1 ? 'MES' : 'MESES'}`;
        };
        [rate, period, months].forEach(el => el.addEventListener('input', paint));
        period.addEventListener('change', paint);
        paint();
    })();

    // Multa: tasa % y monto fijo son mutuamente excluyentes (solo uno).
    (() => {
        const rate  = document.querySelector('input[name="penalty_rate"]');
        const fixed = document.querySelector('input[name="penalty_fixed_amount"]');
        if (!rate || !fixed) return;

        const hasVal = (el) => el.value !== '' && parseFloat(el.value) > 0;

        function sync() {
            // Si uno tiene valor, deshabilita y limpia el otro.
            if (hasVal(rate)) {
                fixed.value = '';
                fixed.disabled = true;
            } else if (hasVal(fixed)) {
                rate.value = '';
                rate.disabled = true;
            } else {
                rate.disabled = false;
                fixed.disabled = false;
            }
        }

        rate.addEventListener('input', sync);
        fixed.addEventListener('input', sync);
        sync(); // estado inicial (old() o categoría existente)
    })();
</script>
@endpush
