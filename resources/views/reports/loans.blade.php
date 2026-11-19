@extends('layouts.app')

@section('title', 'Reporte de Préstamos')

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
                    <li class="breadcrumb-item active">Préstamos</li>
                </ol>
            </nav>
    <x-page-header title="Reporte de Préstamos" icon="cash-coin" :back="route('reports.index')">
        @include('components.export-dropdown', ['baseUrl' => route('exports.loans'), 'params' => $filters])
    </x-page-header>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-primary">{{ $loans->total() }}</div>
                <div class="label">Préstamos encontrados</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-dark">{{ $currencySymbol }} {{ number_format($totals['amount'], 2) }}</div>
                <div class="label">Monto total</div>
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
            <form method="GET" action="{{ route('reports.loans') }}" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Búsqueda</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="ID o cliente">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Estado</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach(\App\Models\Loan::STATUS_LABELS as $val => $lbl)
                            <option value="{{ $val }}" {{ ($filters['status'] ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Sucursal</label>
                    <select name="branch_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ (int)($filters['branch_id'] ?? 0) === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Producto</label>
                    <select name="product_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}" {{ (int)($filters['product_id'] ?? 0) === $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-semibold mb-1">Desde</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-semibold mb-1">Hasta</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <x-btn icon="funnel" type="submit">Filtrar</x-btn>
                    <x-icon-btn icon="x-circle" label="Limpiar" variant="secondary" :href="route('reports.loans')" />
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
                            <th class="ps-3">ID</th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>Sucursal</th>
                            <th class="text-end">Monto</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Pendiente</th>
                            <th class="text-center">Tasa</th>
                            <th class="text-center">Plazo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $statusColors = \App\Models\Loan::STATUS_COLORS;
                            $statusLabels = \App\Models\Loan::STATUS_LABELS;
                        @endphp
                        @forelse($loans as $loan)
                            @php $pending = \App\Services\Reports\ReportService::loanPending($loan); @endphp
                            <tr style="cursor:pointer" onclick="window.location='{{ route('loans.show', $loan) }}'">
                                <td class="ps-3 fw-semibold">PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $loan->client?->name ?? '-' }}</td>
                                <td>{{ $loan->product?->name ?? '-' }}</td>
                                <td>{{ $loan->branch?->name ?? '-' }}</td>
                                <td class="text-end">{{ $currencySymbol }} {{ number_format($loan->amount, 2) }}</td>
                                <td class="text-end text-success">{{ $currencySymbol }} {{ number_format($loan->total_paid, 2) }}</td>
                                <td class="text-end {{ $pending > 0 ? 'text-danger' : 'text-muted' }}">{{ $currencySymbol }} {{ number_format($pending, 2) }}</td>
                                <td class="text-center">{{ $loan->interest_rate }}%</td>
                                <td class="text-center">{{ $loan->term_months }}m</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $statusColors[$loan->status] ?? 'secondary' }}">{{ $statusLabels[$loan->status] ?? ucfirst($loan->status) }}</span>
                                </td>
                                <td class="text-end pe-3">{{ $loan->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center py-4 text-muted">No se encontraron préstamos con los filtros aplicados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($loans->hasPages())
        <div class="mt-3 d-flex justify-content-center">{{ $loans->links() }}</div>
    @endif
</div>
@endsection
