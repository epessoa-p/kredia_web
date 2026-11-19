@extends('layouts.app')

@section('title', 'Reporte de Clientes')

@push('styles')
<style>
    .report-stat { background: #f8f9fa; border-radius: .75rem; padding: 1rem 1.25rem; text-align: center; }
    .report-stat .value { font-size: var(--fs-lg); font-weight: 700; line-height: 1.2; }
    .report-stat .label { font-size: var(--fs-xs); color: #6c757d; margin-top: .1rem; }
</style>
@endpush

@section('page')
<div class="container-fluid">
    {{-- Header --}}
    <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none">Reportes</a></li>
                    <li class="breadcrumb-item active">Clientes</li>
                </ol>
            </nav>
    <x-page-header title="Reporte de Clientes" icon="people" :back="route('reports.index')">
        @include('components.export-dropdown', ['baseUrl' => route('exports.clients'), 'params' => $filters])
    </x-page-header>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-primary">{{ $clients->total() }}</div>
                <div class="label">Clientes encontrados</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-dark">{{ $currencySymbol }} {{ number_format($totals['borrowed'], 2) }}</div>
                <div class="label">Total prestado</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-success">{{ $currencySymbol }} {{ number_format($totals['paid'], 2) }}</div>
                <div class="label">Total pagado</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-danger">{{ $currencySymbol }} {{ number_format($totals['pending'], 2) }}</div>
                <div class="label">Total pendiente</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('reports.clients') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Búsqueda</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="Nombre o documento">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Filtro</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="active" {{ ($filters['status'] ?? '') === 'active' ? 'selected' : '' }}>Activos</option>
                        <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactivos</option>
                        <option value="with_loans" {{ ($filters['status'] ?? '') === 'with_loans' ? 'selected' : '' }}>Con préstamos</option>
                        <option value="overdue" {{ ($filters['status'] ?? '') === 'overdue' ? 'selected' : '' }}>Con mora</option>
                    </select>
                </div>
                <div class="col-md-5 d-flex gap-2">
                    <x-btn icon="funnel" type="submit">Filtrar</x-btn>
                    <x-icon-btn icon="x-circle" label="Limpiar" variant="secondary" :href="route('reports.clients')" />
                </div>
            </form>
        </div>
    </div>

    {{-- Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Cliente</th>
                            <th>Documento</th>
                            <th>Contacto</th>
                            <th class="text-center">Préstamos</th>
                            <th class="text-center">Activos</th>
                            <th class="text-center">En mora</th>
                            <th class="text-end">Total Prestado</th>
                            <th class="text-end">Total Pagado</th>
                            <th class="text-end pe-3">Pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clients as $client)
                            @php
                                $borrowed = (float) ($client->total_borrowed ?? 0);
                                $paid = (float) ($client->total_paid ?? 0);
                                $pending = max(0, $borrowed - $paid);
                            @endphp
                            <tr style="cursor:pointer" onclick="window.location='{{ route('clients.show', $client) }}'">
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $client->name }}</div>
                                    <span class="badge {{ $client->active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} fs-xs">
                                        {{ $client->active ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>
                                <td>{{ $client->id_number ?? '-' }}</td>
                                <td>
                                    <div>{{ $client->phone ?? '-' }}</div>
                                    <small class="text-muted">{{ $client->email ?? '' }}</small>
                                </td>
                                <td class="text-center fw-semibold">{{ $client->loans_count }}</td>
                                <td class="text-center">
                                    @if($client->active_loans_count > 0)
                                        <span class="badge bg-success-subtle text-success">{{ $client->active_loans_count }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($client->overdue_loans_count > 0)
                                        <span class="badge bg-danger">{{ $client->overdue_loans_count }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $currencySymbol }} {{ number_format($borrowed, 2) }}</td>
                                <td class="text-end text-success">{{ $currencySymbol }} {{ number_format($paid, 2) }}</td>
                                <td class="text-end pe-3 {{ $pending > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $currencySymbol }} {{ number_format($pending, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center py-4 text-muted">No se encontraron clientes con los filtros aplicados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($clients->hasPages())
        <div class="mt-3 d-flex justify-content-center">{{ $clients->links() }}</div>
    @endif
</div>
@endsection
