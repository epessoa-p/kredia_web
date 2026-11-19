@extends('layouts.app')

@section('title', 'Configurar categorías de clientes')

@php
    $cats = \App\Services\ClientRating::CATEGORIES;
    $val = fn ($key) => old($key, $settings[$key]);
    $def = fn ($key) => $defaults[$key];
    $money = fn ($v) => number_format((float) $v, 0, ',', '.');
@endphp

@push('styles')
<style>
    .rs-card { border: 0; border-left: 4px solid var(--rs-color); box-shadow: 0 1px 3px rgba(15,23,42,.08); }
    .rs-card .card-body { padding: .9rem 1rem; }
    .rs-head { display: flex; align-items: center; gap: .55rem; margin-bottom: .35rem; }
    .rs-icon {
        width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--rs-bg); color: var(--rs-color); font-size:var(--fs-md);
    }
    .rs-title { font-weight: 700; font-size:var(--fs-base); color: var(--rs-color); }
    .rs-rule { font-size:var(--fs-sm); color: #64748b; margin-bottom: .7rem; }
    .rs-rule strong { color: #334155; }
    .rs-field label { font-size:var(--fs-sm); font-weight: 600; margin-bottom: .2rem; }
    .rs-default { font-size:var(--fs-xs); color: #94a3b8; margin-top: .15rem; }
    .rs-switch { font-size:var(--fs-sm); }
    .rs-switch .form-check-input { cursor: pointer; }
    .rs-help {
        font-size:var(--fs-xs); color: #78350f; background: #fffbeb;
        border: 1px solid #fde68a; border-radius: 8px; padding: .45rem .65rem;
    }
    .rs-help.is-muted { color: #64748b; background: #f8fafc; border-color: #e2e8f0; }
    .rs-preview { position: sticky; top: 80px; }
    .rs-count-row { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .4rem 0; border-bottom: 1px dashed #e2e8f0; font-size:var(--fs-sm); }
    .rs-count-row:last-child { border-bottom: 0; }
    .rs-count-val { font-weight: 700; min-width: 2.2rem; text-align: right; }
    .rs-diff { font-size:var(--fs-xs); font-weight: 700; border-radius: 999px; padding: 0 .4rem; }
    .rs-diff.up { background: #dcfce7; color: #15803d; }
    .rs-diff.down { background: #fee2e2; color: #b91c1c; }
    .rs-loading { opacity: .5; transition: opacity .15s; }
    .rs-swatches { display: flex; flex-wrap: wrap; gap: 5px; padding: 3px 0; }
    .rs-swatch {
        width: 22px; height: 22px; border-radius: 50%; cursor: pointer; margin: 0;
        background: var(--sw); border: 2px solid #fff; box-shadow: 0 0 0 1px #cbd5e1;
        transition: transform .1s, box-shadow .1s;
    }
    .rs-swatch:hover { transform: scale(1.12); }
    .btn-check:checked + .rs-swatch { box-shadow: 0 0 0 2px var(--sw); transform: scale(1.12); }
    .btn-check:focus-visible + .rs-swatch { outline: 2px solid #0d6efd; outline-offset: 2px; }
    .rs-tag-row { padding: .55rem 0; border-top: 1px solid #eef2f7; }
</style>
@endpush

@section('page')
<div class="container-fluid">
    <x-page-header title="Configurar categorías de clientes" icon="sliders" :back="route('clients.index')" />

    <div class="mb-3">
        <div class="text-muted small">
            Ajusta las reglas con las que se clasifica a cada cliente.
            @if($company)
                Empresa: <strong>{{ $company->name }}</strong>
                @if($isCustom)
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1">Personalizada</span>
                @else
                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1">Valores por defecto</span>
                @endif
            @endif
        </div>
    </div>

    @if($companies->isNotEmpty())
        <form method="GET" action="{{ route('clients.rating-settings.edit') }}" class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2 d-flex align-items-center gap-2 flex-wrap">
                <label class="small fw-semibold mb-0" for="rsCompany"><i class="bi bi-building me-1"></i> Empresa</label>
                <select name="company_id" id="rsCompany" class="form-select form-select-sm" style="max-width: 320px" onchange="this.form.submit()">
                    <option value="">— Selecciona una empresa —</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected($company?->id === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    @endif

    @if(!$company)
        <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i> Selecciona una empresa para configurar sus categorías.</div>
    @else
    <form method="POST" action="{{ route('clients.rating-settings.update') }}" id="rsForm">
        @csrf
        @method('PUT')
        @if($companies->isNotEmpty())
            <input type="hidden" name="company_id" value="{{ $company->id }}">
        @endif

        @if($errors->any())
            <div class="alert alert-danger small py-2">
                <i class="bi bi-exclamation-triangle me-1"></i> Revisa los valores: {{ $errors->first() }}
            </div>
        @endif

        <div class="row g-3">
            <div class="col-lg-8 d-flex flex-column gap-3">

                {{-- General --}}
                <div class="card rs-card" style="--rs-color:#0f766e; --rs-bg:#ccfbf1;">
                    <div class="card-body">
                        <div class="rs-head">
                            <span class="rs-icon"><i class="bi bi-calendar-check"></i></span>
                            <span class="rs-title">Pago a tiempo (aplica a todas)</span>
                        </div>
                        <div class="rs-rule">Un interés cuenta como <strong>pagado a tiempo</strong> si se pagó hasta estos días después de su vencimiento. Los intereses vencidos que pasaron este plazo sin pagarse cuentan como <strong>atraso</strong>.</div>
                        <div class="row g-2">
                            <div class="col-sm-4 rs-field">
                                <label for="tolerance_days">Días de tolerancia</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control no-upper @error('tolerance_days') is-invalid @enderror" id="tolerance_days" name="tolerance_days" min="0" max="60" step="1" value="{{ $val('tolerance_days') }}" required>
                                    <span class="input-group-text">días</span>
                                </div>
                                <div class="rs-default">Por defecto: {{ $def('tolerance_days') }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Cliente fiel --}}
                @php $m = $cats['fiel']; @endphp
                <div class="card rs-card" style="--rs-color:{{ $m['color'] }}; --rs-bg:{{ $m['bg'] }};">
                    <div class="card-body">
                        <div class="rs-head">
                            <span class="rs-icon"><i class="bi {{ $m['icon'] }}"></i></span>
                            <span class="rs-title">{{ $m['label'] }}</span>
                        </div>
                        <div class="rs-rule" id="rsLoyalRule"></div>
                        <div class="row g-2">
                            <div class="col-sm-3 col-6 rs-field">
                                <label for="loyal_min_loans">Préstamos mínimos</label>
                                <input type="number" class="form-control form-control-sm no-upper @error('loyal_min_loans') is-invalid @enderror" id="loyal_min_loans" name="loyal_min_loans" min="1" max="100" step="1" value="{{ $val('loyal_min_loans') }}" required>
                                <div class="rs-default">Por defecto: {{ $def('loyal_min_loans') }}</div>
                            </div>
                            <div class="col-sm-3 col-6 rs-field">
                                <label for="loyal_min_amount">Monto mínimo prestado</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">{{ $currencySymbol }}</span>
                                    <input type="number" class="form-control no-upper @error('loyal_min_amount') is-invalid @enderror" id="loyal_min_amount" name="loyal_min_amount" min="0" step="100" value="{{ (float) $val('loyal_min_amount') == (int) $val('loyal_min_amount') ? (int) $val('loyal_min_amount') : $val('loyal_min_amount') }}" required>
                                </div>
                                <div class="rs-default">Por defecto: {{ $currencySymbol }} {{ $money($def('loyal_min_amount')) }}</div>
                            </div>
                            <div class="col-sm-3 col-6 rs-field">
                                <label>Debe cumplir</label>
                                <div class="k-seg w-100 rs-mode" role="group">
                                    <input type="radio" class="btn-check" name="loyal_mode" id="loyal_mode_any" value="any" @checked($val('loyal_mode') === 'any')>
                                    <label class="btn btn-sm btn-ghost flex-fill" for="loyal_mode_any" title="Basta con una de las dos condiciones">Una</label>
                                    <input type="radio" class="btn-check" name="loyal_mode" id="loyal_mode_all" value="all" @checked($val('loyal_mode') === 'all')>
                                    <label class="btn btn-sm btn-ghost flex-fill" for="loyal_mode_all" title="Debe cumplir las dos condiciones">Ambas</label>
                                </div>
                                <div class="rs-default">Por defecto: {{ $def('loyal_mode') === 'all' ? 'Ambas' : 'Una' }}</div>
                            </div>
                            <div class="col-sm-3 col-6 rs-field">
                                <label for="loyal_min_punctuality">
                                    Intereses pagados a tiempo
                                    <i class="bi bi-question-circle text-muted" data-bs-toggle="tooltip"
                                       title="De todos los intereses que pagó el cliente, qué porcentaje pagó a tiempo. 0 = no se exige."></i>
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">mín.</span>
                                    <input type="number" class="form-control no-upper @error('loyal_min_punctuality') is-invalid @enderror" id="loyal_min_punctuality" name="loyal_min_punctuality" min="0" max="100" step="1" value="{{ $val('loyal_min_punctuality') }}" required>
                                    <span class="input-group-text">%</span>
                                </div>
                                <div class="rs-default">0 = no se exige · Por defecto: {{ $def('loyal_min_punctuality') }}</div>
                            </div>
                        </div>
                        <div class="rs-help mt-2" id="rsPunctHelp"></div>
                    </div>
                </div>

                {{-- Puntual --}}
                @php $m = $cats['puntual']; @endphp
                <div class="card rs-card" style="--rs-color:{{ $m['color'] }}; --rs-bg:{{ $m['bg'] }};">
                    <div class="card-body">
                        <div class="rs-head">
                            <span class="rs-icon"><i class="bi {{ $m['icon'] }}"></i></span>
                            <span class="rs-title">{{ $m['label'] }}</span>
                        </div>
                        <div class="rs-rule" id="rsPunctualRule"></div>
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-4 rs-field">
                                <label for="punctual_max_late">Atrasos permitidos</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control no-upper @error('punctual_max_late') is-invalid @enderror" id="punctual_max_late" name="punctual_max_late" min="0" max="100" step="1" value="{{ $val('punctual_max_late') }}" required>
                                    <span class="input-group-text">intereses</span>
                                </div>
                                <div class="rs-default">Por defecto: {{ $def('punctual_max_late') }}</div>
                            </div>
                            <div class="col-sm-8">
                                <div class="form-check form-switch rs-switch mb-1">
                                    <input class="form-check-input" type="checkbox" role="switch" id="penalty_breaks" name="penalty_breaks" value="1" @checked((bool) $val('penalty_breaks'))>
                                    <label class="form-check-label" for="penalty_breaks">Si tuvo una <strong>multa</strong>, deja de ser puntual</label>
                                </div>
                                <div class="rs-default">Por defecto: {{ $def('penalty_breaks') ? 'activado' : 'desactivado' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Riesgo --}}
                @php $m = $cats['riesgo']; @endphp
                <div class="card rs-card" style="--rs-color:{{ $m['color'] }}; --rs-bg:{{ $m['bg'] }};">
                    <div class="card-body">
                        <div class="rs-head">
                            <span class="rs-icon"><i class="bi {{ $m['icon'] }}"></i></span>
                            <span class="rs-title">{{ $m['label'] }}</span>
                        </div>
                        <div class="rs-rule">Un cliente con un préstamo <strong>incumplido</strong> siempre está en Riesgo.</div>
                        <div class="form-check form-switch rs-switch mb-1">
                            <input class="form-check-input" type="checkbox" role="switch" id="risk_include_mora" name="risk_include_mora" value="1" @checked((bool) $val('risk_include_mora'))>
                            <label class="form-check-label" for="risk_include_mora">También si tiene un préstamo <strong>en mora</strong></label>
                        </div>
                        <div class="rs-default">Por defecto: {{ $def('risk_include_mora') ? 'activado' : 'desactivado' }}</div>
                    </div>
                </div>

                {{-- Regular y Nuevo (automáticas) --}}
                <div class="row g-3">
                    @foreach(['regular' => 'Tuvo más atrasos de los permitidos o multas, pero hoy está al día. Es lo que queda después de las demás reglas.', 'nuevo' => 'Todavía no tiene intereses pagados ni préstamos finalizados.'] as $key => $text)
                        @php $m = $cats[$key]; @endphp
                        <div class="col-md-6">
                            <div class="card rs-card h-100" style="--rs-color:{{ $m['color'] }}; --rs-bg:{{ $m['bg'] }};">
                                <div class="card-body">
                                    <div class="rs-head">
                                        <span class="rs-icon"><i class="bi {{ $m['icon'] }}"></i></span>
                                        <span class="rs-title">{{ $m['label'] }}</span>
                                        <span class="badge bg-light text-muted border ms-auto fs-xs">Automática</span>
                                    </div>
                                    <div class="rs-rule mb-0">{{ $text }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Vista previa --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rs-preview">
                    <div class="card-body p-3">
                        <div class="fw-bold small mb-1"><i class="bi bi-eye me-1 text-primary"></i> Vista previa</div>
                        <div class="text-muted mb-2 fs-xs">Cantidad de clientes en cada categoría con estos valores. Se actualiza al cambiarlos; todavía no se guarda.</div>
                        <div id="rsCounts">
                            @foreach($cats as $key => $m)
                                <div class="rs-count-row">
                                    <span style="color:{{ $m['color'] }}"><i class="bi {{ $m['icon'] }} me-1"></i>{{ $m['label'] }}</span>
                                    <span class="d-flex align-items-center gap-1">
                                        <span class="rs-diff d-none" data-diff="{{ $key }}"></span>
                                        <span class="rs-count-val" data-count="{{ $key }}">{{ $counts[$key] ?? 0 }}</span>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="d-grid gap-2 mt-3">
                            <x-btn type="submit" id="rsSave" icon="check-lg">Guardar configuración</x-btn>
                            @if($isCustom)
                                <x-btn variant="secondary" type="submit" name="action" value="reset" id="rsReset" formnovalidate icon="arrow-counterclockwise">Restablecer valores por defecto</x-btn>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- ═══ Etiquetas de clientes ═══ --}}
    @php
        $tagErrors = $errors->getBag('tags');
        $companyField = $companies->isNotEmpty() ? '<input type="hidden" name="company_id" value="' . $company->id . '">' : '';
        $editingTag = old('_tag_id');
    @endphp
    <div class="row g-3 mt-0" id="etiquetas">
        <div class="col-lg-8">
            <div class="card rs-card" style="--rs-color:#4f46e5; --rs-bg:#e0e7ff;">
                <div class="card-body">
                    <div class="rs-head">
                        <span class="rs-icon"><i class="bi bi-tags-fill"></i></span>
                        <span class="rs-title">Etiquetas de clientes</span>
                        <span class="badge bg-light text-muted border ms-auto fs-xs">Manual</span>
                    </div>
                    <div class="rs-rule">
                        Crea aquí las etiquetas (ej. <strong>CLIENTE FIEL</strong>, <strong>VIP</strong>, <strong>PREFERENCIAL</strong>) y luego elígelas
                        al <strong>editar un cliente</strong> o desde su <strong>ficha</strong>. Son aparte de las categorías automáticas.
                        Para ponerlas o quitarlas se necesita el permiso <strong>"Etiquetar clientes"</strong>.
                    </div>

                    @if($tagErrors->any())
                        <div class="alert alert-danger small py-2 mb-2"><i class="bi bi-exclamation-triangle me-1"></i> {{ $tagErrors->first() }}</div>
                    @endif

                    {{-- Nueva etiqueta --}}
                    <form method="POST" action="{{ route('clients.rating-settings.tags.store') }}" class="rs-tag-new mb-3">
                        @csrf
                        {!! $companyField !!}
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-5 rs-field">
                                <label for="newTagName">Nueva etiqueta</label>
                                <input type="text" id="newTagName" name="name" maxlength="40" required
                                       class="form-control form-control-sm {{ $tagErrors->has('name') && !$editingTag ? 'is-invalid' : '' }}"
                                       value="{{ $editingTag ? '' : old('name') }}" placeholder="Ej. CLIENTE FIEL">
                            </div>
                            <div class="col-sm-5 rs-field">
                                <label>Color</label>
                                @include('clients.partials.tag-colors', ['prefix' => 'new', 'selected' => $editingTag ? null : old('color')])
                            </div>
                            <div class="col-sm-2">
                                <x-btn type="submit" icon="plus-lg" :block="true">Agregar</x-btn>
                            </div>
                        </div>
                    </form>

                    {{-- Lista --}}
                    @forelse($tags as $tag)
                        @php $used = (int) ($tagUsage[$tag->name] ?? 0); @endphp
                        <div class="rs-tag-row">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="badge rounded-pill" style="background: {{ $tag->color }}; color: #fff; font-size:var(--fs-xs);">
                                    <i class="bi bi-tag-fill"></i> {{ $tag->name }}
                                </span>
                                <a href="{{ route('clients.index', ['tag' => $tag->name]) }}" class="text-muted text-decoration-none" style="font-size:var(--fs-xs);">
                                    {{ $used }} cliente{{ $used === 1 ? '' : 's' }}
                                </a>
                                <div class="ms-auto d-flex gap-1">
                                    <x-icon-btn icon="pencil" label="Editar" class="rs-tag-edit-btn" data-target="tagEdit{{ $tag->id }}" />
                                    <form method="POST" action="{{ route('clients.rating-settings.tags.destroy', $tag) }}" class="rs-tag-delete"
                                          data-name="{{ $tag->name }}" data-used="{{ $used }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" />
                                    </form>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('clients.rating-settings.tags.update', $tag) }}" id="tagEdit{{ $tag->id }}"
                                  class="rs-tag-edit mt-2 {{ (int) $editingTag === $tag->id ? '' : 'd-none' }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="_tag_id" value="{{ $tag->id }}">
                                <div class="row g-2 align-items-end">
                                    <div class="col-sm-5 rs-field">
                                        <label>Nombre</label>
                                        <input type="text" name="name" maxlength="40" required
                                               class="form-control form-control-sm {{ (int) $editingTag === $tag->id && $tagErrors->has('name') ? 'is-invalid' : '' }}"
                                               value="{{ (int) $editingTag === $tag->id ? old('name') : $tag->name }}">
                                    </div>
                                    <div class="col-sm-5 rs-field">
                                        <label>Color</label>
                                        @include('clients.partials.tag-colors', ['prefix' => 'e' . $tag->id, 'selected' => (int) $editingTag === $tag->id ? old('color') : $tag->color])
                                    </div>
                                    <div class="col-sm-2">
                                        <x-btn type="submit" icon="check-lg" :block="true">Guardar</x-btn>
                                    </div>
                                </div>
                                @if($used)
                                    <div class="rs-default mt-1">Si cambias el nombre, los {{ $used }} cliente(s) con esta etiqueta la conservan con el nombre nuevo.</div>
                                @endif
                            </form>
                        </div>
                    @empty
                        <div class="text-muted text-center py-3 fs-sm">
                            <i class="bi bi-tags d-block fs-4 mb-1"></i> Todavía no hay etiquetas. Crea la primera arriba.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
@if($company)
<script>
(function () {
    const form = document.getElementById('rsForm');
    if (!form) return;
    const el = (id) => document.getElementById(id);
    const fmt = (n) => Number(n || 0).toLocaleString('es-BO', { maximumFractionDigits: 0 });

    function describe() {
        const loans = parseInt(el('loyal_min_loans').value || '1', 10);
        const amount = el('loyal_min_amount').value;
        const all = el('loyal_mode_all').checked;
        const punct = parseInt(el('loyal_min_punctuality').value || '0', 10);
        el('rsLoyalRule').innerHTML = 'Es <strong>puntual</strong> y tiene <strong>' + loans + ' o más préstamos</strong> '
            + (all ? '<strong>y</strong>' : '<strong>o</strong>') + ' <strong>{{ $currencySymbol }} ' + fmt(amount) + ' o más prestados</strong>'
            + (punct > 0 ? ', con al menos <strong>' + punct + '%</strong> de intereses pagados a tiempo' : '') + '.';

        // Explicación del % de intereses a tiempo, con un ejemplo y su relación con "atrasos permitidos".
        const late = parseInt(el('punctual_max_late').value || '0', 10);
        const help = el('rsPunctHelp');
        if (punct <= 0) {
            help.className = 'rs-help mt-2 is-muted';
            help.innerHTML = '<i class="bi bi-info-circle me-1"></i><strong>Intereses pagados a tiempo:</strong> no se exige. '
                + 'Si pones por ejemplo <strong>90%</strong>, solo será fiel el cliente que pagó a tiempo al menos 9 de cada 10 intereses.';
        } else {
            const needed = Math.ceil(punct / 10);
            let text = '<i class="bi bi-lightbulb me-1"></i><strong>Intereses pagados a tiempo (mín. ' + punct + '%):</strong> '
                + 'de todos los intereses que pagó el cliente, al menos el ' + punct + '% debe haberlos pagado a tiempo. '
                + 'Ejemplo: si pagó 10 intereses, necesita <strong>' + Math.min(10, needed) + ' o más a tiempo</strong> para ser fiel.';
            if (late === 0) {
                text += '<br><i class="bi bi-exclamation-circle me-1"></i>Con <strong>0 atrasos permitidos</strong> (en "Puntual") esto no cambia nada: '
                    + 'un cliente puntual ya pagó el 100% a tiempo. Sirve cuando permites algunos atrasos.';
            } else {
                text += '<br>Así, un cliente con ' + late + ' atraso(s) permitido(s) sigue siendo <strong>Puntual</strong>, '
                    + 'pero solo pasa a <strong>Cliente fiel</strong> si su porcentaje de pagos a tiempo es alto.';
            }
            help.className = 'rs-help mt-2';
            help.innerHTML = text;
        }

        el('rsPunctualRule').innerHTML = (late === 0 ? 'Pagó <strong>todos</strong> sus intereses a tiempo' : 'Tiene <strong>' + late + ' atraso(s) o menos</strong>')
            + ', sin préstamos incumplidos' + (el('penalty_breaks').checked ? ' y <strong>sin multas</strong>' : '') + '.';
    }

    const current = @json($counts);
    let timer = null, seq = 0;
    function preview() {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            if (!form.checkValidity()) return;
            const data = new FormData(form);
            data.delete('_method');
            const mine = ++seq;
            el('rsCounts').classList.add('rs-loading');
            try {
                const res = await fetch(@json(route('clients.rating-settings.preview')), {
                    method: 'POST', body: data,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok || mine !== seq) return;
                const json = await res.json();
                Object.entries(json.counts || {}).forEach(([key, n]) => {
                    const count = document.querySelector('[data-count="' + key + '"]');
                    const diff = document.querySelector('[data-diff="' + key + '"]');
                    if (count) count.textContent = n;
                    if (diff) {
                        const d = n - (current[key] ?? 0);
                        diff.textContent = d > 0 ? '+' + d : String(d);
                        diff.className = 'rs-diff ' + (d > 0 ? 'up' : 'down') + (d === 0 ? ' d-none' : '');
                    }
                });
            } catch (e) {
                // Sin vista previa no pasa nada: al guardar se recalcula.
            } finally {
                if (mine === seq) el('rsCounts').classList.remove('rs-loading');
            }
        }, 350);
    }

    if (window.bootstrap?.Tooltip) {
        form.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(t => new bootstrap.Tooltip(t));
    }
    form.addEventListener('input', () => { describe(); preview(); });
    form.addEventListener('change', () => { describe(); preview(); });
    describe();

    form.addEventListener('submit', (e) => {
        const btn = e.submitter;
        if (btn && btn.id === 'rsReset' && !confirm('¿Volver a los valores por defecto para esta empresa?')) {
            e.preventDefault();
            return;
        }
        form.querySelectorAll('button[type="submit"]').forEach(b => {
            if (b !== btn) b.disabled = true;
        });
        if (btn) {
            // El valor del botón presionado (action=reset) se envía aunque se deshabilite después.
            if (btn.name) {
                const h = document.createElement('input');
                h.type = 'hidden'; h.name = btn.name; h.value = btn.value;
                form.appendChild(h);
            }
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';
        }
    });
})();

// Etiquetas: mostrar/ocultar edición y confirmar eliminación.
(function () {
    document.querySelectorAll('.rs-tag-edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const form = document.getElementById(btn.dataset.target);
            form.classList.toggle('d-none');
            if (!form.classList.contains('d-none')) form.querySelector('input[name="name"]').focus();
        });
    });

    document.querySelectorAll('.rs-tag-delete').forEach(form => {
        form.addEventListener('submit', async (e) => {
            if (form.dataset.confirmed) return;
            e.preventDefault();
            const used = parseInt(form.dataset.used || '0', 10);
            const text = used > 0
                ? 'La tienen ' + used + ' cliente(s); se les quitará la etiqueta.'
                : 'Ningún cliente la tiene asignada.';
            let ok;
            if (window.Swal) {
                ok = (await Swal.fire({
                    title: '¿Eliminar la etiqueta ' + form.dataset.name + '?', text, icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#dc3545',
                })).isConfirmed;
            } else {
                ok = confirm('¿Eliminar la etiqueta ' + form.dataset.name + '? ' + text);
            }
            if (!ok) return;
            form.dataset.confirmed = '1';
            form.querySelector('button').disabled = true;
            form.submit();
        });
    });

    // Evitar doble envío al crear o guardar una etiqueta.
    document.querySelectorAll('.rs-tag-new, .rs-tag-edit').forEach(form => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        });
    });
})();
</script>
@endif
@endpush
