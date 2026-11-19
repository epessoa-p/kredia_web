@extends('layouts.app')

@section('title', 'Clientes - Sistema de Préstamos')

@section('page')
<div class="container-fluid">
    <x-page-header title="Clientes" icon="person-vcard" :count="$clients->total() . ' clientes encontrados'">
        @include('components.export-dropdown', ['baseUrl' => route('exports.clients'), 'params' => request()->all()])
        @if(auth()->user()->hasPermissionInCompany('clients.rating.settings', $company))
            <x-btn variant="secondary" icon="sliders" :href="route('clients.rating-settings.edit')" title="Configurar categorías de clientes">Categorías</x-btn>
        @endif
        <x-btn variant="success" icon="cash-coin" :href="route('loans.create')">Nuevo préstamo</x-btn>
        <x-btn icon="plus-lg" :href="route('clients.create')">Nuevo cliente</x-btn>
    </x-page-header>

    @php
        $ratingChips = [
            '' => ['label' => 'Todos', 'icon' => 'bi-people', 'color' => '#334155', 'bg' => '#f8fafc', 'border' => '#cbd5e1', 'count' => $totalBase],
            'puntuales' => ['label' => 'Fiel + Puntual', 'icon' => 'bi-hand-thumbs-up-fill', 'color' => '#0f766e', 'bg' => '#ccfbf1', 'border' => '#5eead4', 'count' => $ratingCounts['puntuales'] ?? 0, 'hint' => 'Atajo: muestra juntos a los Clientes fieles y a los Puntuales (todos los que pagan a tiempo)'],
        ];
        foreach (\App\Services\ClientRating::CATEGORIES as $key => $meta) {
            $ratingChips[$key] = $meta + ['count' => $ratingCounts[$key] ?? 0];
        }
        $tolerance = $ratingSettings['tolerance_days'];
        $canRatingSettings = auth()->user()->hasPermissionInCompany('clients.rating.settings', $company);
        $hasRatingFilters = $filters['rating'] !== '' || $filters['min_amount'] !== null || $filters['min_loans'] !== null || $filters['tag'] !== '' || $filters['sort'] !== 'recent';
    @endphp

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('clients.index') }}" id="clientFilters">
                {{-- Categoría --}}
                <input type="hidden" name="rating" value="{{ $filters['rating'] }}">
                <div class="chip-row mb-2">
                    @foreach($ratingChips as $key => $chip)
                        @php $active = $filters['rating'] === $key; @endphp
                        <a href="{{ route('clients.index', array_merge(request()->except(['rating', 'page']), $key !== '' ? ['rating' => $key] : [])) }}"
                           class="chip {{ $active ? 'is-active' : '' }}"
                           style="--chip-color: {{ $chip['color'] }}; --chip-bg: {{ $chip['bg'] }}; --chip-border: {{ $chip['border'] }};"
                           title="{{ $chip['hint'] ?? $chip['label'] }}">
                            <i class="bi {{ $chip['icon'] }}"></i> {{ $chip['label'] }}
                            <span class="chip-count">{{ $chip['count'] }}</span>
                        </a>
                    @endforeach
                </div>

                <div class="row g-2 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Buscar</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control form-control-sm" name="q" value="{{ $search }}" placeholder="Nombre, documento, teléfono o correo">
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-3 col-6">
                        <label class="form-label small fw-semibold mb-1">Prestado mínimo</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">{{ $currencySymbol }}</span>
                            <input type="number" class="form-control form-control-sm no-upper" name="min_amount" min="0" step="100"
                                   value="{{ $filters['min_amount'] !== null ? (int) $filters['min_amount'] : '' }}" placeholder="Ej. 2000">
                        </div>
                    </div>
                    <div class="col-lg-1 col-md-3 col-6">
                        <label class="form-label small fw-semibold mb-1">Préstamos mín.</label>
                        <input type="number" class="form-control form-control-sm no-upper" name="min_loans" min="0" step="1"
                               value="{{ $filters['min_loans'] ?? '' }}" placeholder="Ej. 2">
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small fw-semibold mb-1">
                            Etiqueta
                            <i class="bi bi-question-circle text-muted" title="Etiqueta manual que el personal le pone a un cliente (ej. CLIENTE FIEL, VIP) en Editar cliente → Etiqueta."></i>
                        </label>
                        <select name="tag" class="form-select form-select-sm" @disabled($availableTags->isEmpty())
                                title="{{ $availableTags->isEmpty() ? 'Todavía ningún cliente tiene etiqueta. Se asigna en Editar cliente → Etiqueta.' : 'Filtrar por etiqueta manual' }}">
                            <option value="">{{ $availableTags->isEmpty() ? 'Sin etiquetas aún' : 'Todas' }}</option>
                            @foreach($availableTags as $t)
                                <option value="{{ $t }}" @selected($filters['tag'] === $t)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6">
                        <label class="form-label small fw-semibold mb-1">Ordenar por</label>
                        <select name="sort" class="form-select form-select-sm">
                            <option value="recent" @selected($filters['sort'] === 'recent')>Más recientes</option>
                            <option value="amount" @selected($filters['sort'] === 'amount')>Más prestado</option>
                            <option value="loans" @selected($filters['sort'] === 'loans')>Más préstamos</option>
                            <option value="punctuality" @selected($filters['sort'] === 'punctuality')>Mejor puntualidad</option>
                        </select>
                    </div>
                    <div class="col-lg-1 col-md-4 d-flex gap-1">
                        <x-icon-btn variant="primary" icon="funnel" label="Filtrar" type="submit" class="w-100" />
                        @if($search !== '' || $hasRatingFilters)
                            <x-icon-btn icon="x-lg" label="Limpiar filtros" :href="route('clients.index')" class="w-100" />
                        @endif
                    </div>
                </div>
                <div class="text-muted mt-2 fs-xs">
                    <i class="bi bi-info-circle"></i>
                    <strong>Puntual</strong>: pagó sus intereses hasta {{ $tolerance }} día(s) después del vencimiento{{ $ratingSettings['punctual_max_late'] > 0 ? ' (se permiten ' . $ratingSettings['punctual_max_late'] . ' atraso(s))' : '' }}, {{ $ratingSettings['penalty_breaks'] ? 'sin multas ni' : 'sin' }} préstamos incumplidos.
                    <strong>Cliente fiel</strong>: puntual con {{ $ratingSettings['loyal_min_loans'] }} o más préstamos {{ $ratingSettings['loyal_mode'] === 'all' ? 'y' : 'o' }} {{ $currencySymbol }} {{ number_format($ratingSettings['loyal_min_amount'], 0, ',', '.') }} o más prestados{{ $ratingSettings['loyal_min_punctuality'] > 0 ? ', con ' . $ratingSettings['loyal_min_punctuality'] . '% o más de puntualidad' : '' }}.
                    <strong>Riesgo</strong>: tiene hoy un préstamo {{ $ratingSettings['risk_include_mora'] ? 'en mora o incumplido' : 'incumplido' }}.
                    @if($canRatingSettings)
                        <a href="{{ route('clients.rating-settings.edit') }}" class="ms-1 text-decoration-none fw-semibold"><i class="bi bi-sliders"></i> Configurar categorías</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 table-list">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Cliente</th>
                            <th>Categoría</th>
                            <th>Documento</th>
                            <th>Contacto</th>
                            @if(auth()->user()->is_super_admin)
                                <th>Empresa</th>
                            @endif
                            <th class="text-center">Puntualidad</th>
                            <th class="text-end">Préstamos</th>
                            <th class="text-end">Total Prestado</th>
                            <th class="text-end">Total Pagado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clients as $client)
                            @php
                                $s = $client->stats;
                            @endphp
                            <tr style="cursor:pointer" onclick="window.location='{{ route('clients.show', $client) }}'">
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $client->name }}</div>
                                </td>
                                <td>
                                    @include('clients.partials.rating-badge', ['rating' => $s?->rating, 'tag' => $client->tag, 'tagColor' => $tagColors[$client->company_id][$client->tag] ?? null])
                                </td>
                                <td>{{ $client->id_number ?: '-' }}</td>
                                <td>
                                    <div>{{ $client->phone ?: '-' }}</div>
                                    <small class="text-muted">{{ $client->email ?: '-' }}</small>
                                </td>
                                @if(auth()->user()->is_super_admin)
                                    <td>
                                        <span class="badge text-bg-light border">{{ $client->company?->name }}</span>
                                    </td>
                                @endif
                                <td class="text-center">
                                    @if($s && $s->punctuality !== null)
                                        <div class="fw-semibold" style="color: {{ $s->punctuality >= 90 ? '#15803d' : ($s->punctuality >= 70 ? '#a16207' : '#b91c1c') }}">{{ $s->punctuality }}%</div>
                                        <small class="text-muted fs-xs">{{ $s->on_time_interests }} de {{ $s->paid_interests }} a tiempo</small>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ $s->loans_count ?? 0 }}</td>
                                <td class="text-end">{{ $currencySymbol }} {{ number_format((float) ($s->total_borrowed ?? 0), 2) }}</td>
                                <td class="text-end">{{ $currencySymbol }} {{ number_format((float) $client->total_paid, 2) }}</td>
                                <td class="text-end pe-3" onclick="event.stopPropagation()">
                                    <x-icon-btn variant="success" icon="cash-coin" label="Crear préstamo para este cliente" :href="route('loans.create', ['client_id' => $client->id])" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->is_super_admin ? 10 : 9 }}" class="text-center py-5 text-muted">
                                    @if($search !== '' || $hasRatingFilters)
                                        <i class="bi bi-search d-block mb-1" style="font-size:var(--fs-lg);opacity:.5"></i>
                                        No hay clientes con estos filtros.
                                    @else
                                        No hay clientes registrados todavía.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($clients->hasPages())
        <div class="mt-3 d-flex justify-content-center">
            {{ $clients->appends(request()->query())->links('pagination::bootstrap-5') }}
        </div>
    @endif
</div>
@endsection
