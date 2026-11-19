@extends('layouts.app')

@section('title', 'Cobranza del día')

@section('page')
<x-page-header title="Cobranza del día" icon="geo" :count="$items->count()" :subtitle="now()->translatedFormat('l d \d\e F')">
    @if($canManage)
        <x-btn variant="secondary" icon="signpost-split" :href="route('collections.routes.index')">Rutas</x-btn>
    @endif
</x-page-header>

<form method="GET" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        @if($canManage)
            <div class="col-md-4">
                <label class="form-label" for="collector_id">Cobrador</label>
                <select id="collector_id" name="collector_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($collectors as $c)<option value="{{ $c->id }}" @selected($filters['collector_id'] == $c->id)>{{ $c->full_name }}</option>@endforeach
                </select>
            </div>
        @endif
        <div class="col-md-4">
            <label class="form-label" for="route_id">Ruta</label>
            <select id="route_id" name="route_id" class="form-select form-select-sm">
                <option value="">Todas</option>
                @foreach($routes as $r)<option value="{{ $r->id }}" @selected($filters['route_id'] == $r->id)>{{ $r->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4"><x-btn type="submit" icon="funnel">Filtrar</x-btn></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="kpi-label">Clientes a visitar</div><div class="kpi-value">{{ $items->count() }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="kpi-label">Por cobrar hoy</div><div class="kpi-value text-mod">{{ $currencySymbol }} {{ number_format($items->sum('due_today'), 2) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="kpi-label">Con atraso</div><div class="kpi-value text-danger">{{ $items->where('overdue_count', '>', 0)->count() }}</div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="kpi-label">Saldo de la cartera</div><div class="kpi-value">{{ $currencySymbol }} {{ number_format($items->sum('outstanding'), 2) }}</div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-list align-middle mb-0">
            <thead><tr><th>#</th><th>Cliente</th><th>Préstamo</th><th>Cobrador</th><th class="text-end">Cuota</th><th class="text-end">Cobrar hoy</th><th>Atraso</th><th></th></tr></thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td class="text-muted">{{ $item['route_position'] < 9999 ? $item['route_position'] : '—' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $item['client']['name'] ?? '' }}</div>
                            <div class="fs-xs text-muted">{{ collect([$item['client']['phone'] ?? null, $item['client']['address'] ?? null])->filter()->implode(' · ') }}</div>
                        </td>
                        <td><span class="code-pill">{{ $item['code'] }}</span></td>
                        <td class="fs-sm">{{ $item['collector'] ?? '—' }}</td>
                        <td class="text-end">{{ number_format($item['installment_amount'], 2) }}</td>
                        <td class="text-end fw-semibold">{{ number_format($item['due_today'], 2) }}</td>
                        <td>
                            @if($item['max_days_late'] > 0)
                                <x-status-badge :tone="$item['max_days_late'] > 30 ? 'danger' : 'warning'">{{ $item['max_days_late'] }} días</x-status-badge>
                            @else
                                <x-status-badge tone="success">Al día</x-status-badge>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if(!empty($item['client']['location_lat']))
                                <x-icon-btn icon="geo-alt" label="Cómo llegar" :href="'https://www.google.com/maps/dir/?api=1&destination=' . $item['client']['location_lat'] . ',' . $item['client']['location_lng']" target="_blank" rel="noopener" />
                            @endif
                            <x-icon-btn icon="cash-coin" label="Cobrar" variant="success" :href="route('loans.installments.show', $item['loan_id'])" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay cobros pendientes para hoy.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
