@extends('layouts.app')

@section('title', 'Reporte de Almacenes')

@push('styles')
<style>
    .report-stat { background: #f8f9fa; border-radius: .75rem; padding: 1rem 1.25rem; text-align: center; }
    .report-stat .value { font-size: var(--fs-lg); font-weight: 700; line-height: 1.2; }
    .report-stat .label { font-size: var(--fs-xs); color: #6c757d; margin-top: .1rem; }
    .wh-card { border: 1px solid #e9ecef; border-radius: .75rem; transition: all .2s; }
    .wh-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,.08); }
    .status-pill { display: inline-flex; align-items: center; gap: 4px; padding: .25em .65em; border-radius: 1rem; font-size: var(--fs-xs); font-weight: 600; }
</style>
@endpush

@section('page')
<div class="container-fluid">
    {{-- Header --}}
    <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none">Reportes</a></li>
                    <li class="breadcrumb-item active">Almacenes</li>
                </ol>
            </nav>
    <x-page-header title="Reporte de Almacenes" icon="building-add" :back="route('reports.index')">
        @include('components.export-dropdown', ['baseUrl' => route('exports.warehouses'), 'params' => $filters])
    </x-page-header>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-2">
            <div class="report-stat">
                <div class="value text-primary">{{ $totals['warehouses'] }}</div>
                <div class="label">Almacenes</div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="report-stat">
                <div class="value text-success">{{ $totals['active'] }}</div>
                <div class="label">Activos</div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="report-stat">
                <div class="value text-dark">{{ $totals['total_collateral'] }}</div>
                <div class="label">Total en custodia</div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="report-stat">
                <div class="value text-info">{{ $totals['total_retained'] }}</div>
                <div class="label">En préstamo</div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="report-stat">
                <div class="value text-warning">{{ $totals['total_stored'] }}</div>
                <div class="label">En retención</div>
            </div>
        </div>
        <div class="col-6 col-lg-2">
            <div class="report-stat">
                <div class="value text-success">{{ $totals['total_sellable'] }}</div>
                <div class="label">Disponible</div>
            </div>
        </div>
    </div>

    {{-- Warehouse Cards --}}
    <div class="row g-3">
        @forelse($warehouses as $wh)
            <div class="col-md-6 col-xl-4">
                <div class="wh-card p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="fw-bold mb-0">{{ $wh->name }}</h6>
                            <small class="text-muted">{{ $wh->code }} · {{ $wh->primaryBranch?->name ?? 'Sin sucursal' }}</small>
                        </div>
                        <span class="badge {{ $wh->active ? 'bg-success' : 'bg-secondary' }}">{{ $wh->active ? 'Activo' : 'Inactivo' }}</span>
                    </div>

                    @if($wh->company)
                        <div class="small text-muted mb-2"><i class="bi bi-building me-1"></i>{{ $wh->company->name }}</div>
                    @endif

                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="status-pill bg-info bg-opacity-10 text-info">
                            <i class="bi bi-lock-fill"></i> {{ $wh->retained_count }} en préstamo
                        </span>
                        <span class="status-pill bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-archive-fill"></i> {{ $wh->stored_count }} en retención
                        </span>
                        <span class="status-pill bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-tag-fill"></i> {{ $wh->sellable_count }} para venta
                        </span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div class="small text-muted">
                            <strong>{{ $wh->collateral_count }}</strong> productos en custodia
                        </div>
                        <x-btn variant="outline-primary" icon="eye" :href="route('warehouses.show', $wh)">Ver detalle</x-btn>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No hay almacenes registrados.
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
