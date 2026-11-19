@extends('layouts.app')

@section('title', 'Detalle Categoría de Crédito')

@section('page')
<div class="container-fluid">
    <x-page-header :title="$creditCategory->name" icon="tags" subtitle="Detalle de reglas y límites de la categoría." :back="route('credit-categories.index')">
        <x-btn icon="pencil" :href="route('credit-categories.edit', $creditCategory)">Editar</x-btn>
    </x-page-header>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @php
                        $chipColor = $creditCategory->color ?: '#64748B';
                        $chipTextClass = in_array(strtolower($chipColor), ['#d9b646', '#e68a2e', '#f4c542', '#f59e0b'], true) ? 'text-dark' : 'text-white';
                    @endphp
                    <p><strong>Empresa:</strong> {{ $creditCategory->company?->name }}</p>
                    <p><strong>Slug:</strong> {{ $creditCategory->slug }}</p>
                    <p><strong>Color:</strong> <span class="badge {{ $chipTextClass }}" style="background-color: {{ $chipColor }};">{{ $chipColor }}</span></p>
                    <p><strong>Ícono:</strong> <i class="bi {{ $creditCategory->icon_class }} fs-5 align-middle" style="color: {{ $chipColor }}"></i> {{ \App\Models\CreditCategory::ICONS[$creditCategory->icon_class] ?? '' }}</p>
                    <p><strong>Monto mínimo:</strong> {{ $currencySymbol }} {{ number_format((float) ($creditCategory->min_amount ?? 0), 2) }}</p>
                    <p><strong>Monto máximo:</strong> {{ $currencySymbol }} {{ number_format((float) ($creditCategory->max_amount ?? 0), 2) }}</p>
                    <p><strong>Estado:</strong> {{ $creditCategory->active ? 'Activa' : 'Inactiva' }}</p>
                    <p class="mb-0"><strong>Descripción:</strong> {{ $creditCategory->description ?: '-' }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Reglas de crédito</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tasa</th>
                                    <th>Periodicidad</th>
                                    <th>Límite de plazo</th>
                                    <th>Monto Min/Max</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($creditCategory->rules as $rule)
                                    <tr>
                                        <td>{{ $rule->name ?: 'Regla #' . $rule->id }}</td>
                                        <td>{{ number_format((float) $rule->interest_rate, 2) }}%</td>
                                        <td>{{ ucfirst($rule->interest_period) }}</td>
                                        <td>{{ $rule->term_months_limit }} meses</td>
                                        <td>{{ $currencySymbol }} {{ number_format((float) ($rule->min_amount ?? 0), 2) }} / {{ $currencySymbol }} {{ number_format((float) ($rule->max_amount ?? 0), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">No hay reglas definidas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
