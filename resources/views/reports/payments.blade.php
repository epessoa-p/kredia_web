@extends('layouts.app')

@section('title', 'Reporte de Pagos')

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
                    <li class="breadcrumb-item active">Pagos</li>
                </ol>
            </nav>
    <x-page-header title="Reporte de Pagos" icon="receipt-cutoff" :back="route('reports.index')">
        @include('components.export-dropdown', ['baseUrl' => route('exports.payments'), 'params' => $filters])
    </x-page-header>

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-primary">{{ number_format($totals['count']) }}</div>
                <div class="label">Pagos registrados</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-success">{{ $currencySymbol }} {{ number_format($totals['amount'], 2) }}</div>
                <div class="label">Monto total cobrado</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-info">{{ $currencySymbol }} {{ number_format($totals['capital'], 2) }}</div>
                <div class="label">Capital cobrado</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="report-stat">
                <div class="value text-warning">{{ $currencySymbol }} {{ number_format($totals['interest'], 2) }}</div>
                <div class="label">Intereses cobrados</div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('reports.payments') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Búsqueda</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="Referencia o cliente">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Método de pago</label>
                    <select name="payment_method" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($methods as $method)
                            <option value="{{ $method }}" {{ ($filters['payment_method'] ?? '') === $method ? 'selected' : '' }}>{{ ucfirst($method) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Desde</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Hasta</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <x-btn icon="funnel" type="submit">Filtrar</x-btn>
                    <x-icon-btn icon="x-circle" label="Limpiar" variant="secondary" :href="route('reports.payments')" />
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
                            <th class="ps-3">Fecha</th>
                            <th>Préstamo</th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>Caja</th>
                            <th class="text-end">Monto</th>
                            <th class="text-end">Capital</th>
                            <th class="text-end">Interés</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th class="pe-3">Registrado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td class="ps-3">{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                <td>
                                    <a href="{{ route('loans.show', $payment->loan_id) }}" class="text-decoration-none fw-semibold">#{{ $payment->loan_id }}</a>
                                </td>
                                <td>{{ $payment->loan?->client?->name ?? '-' }}</td>
                                <td>{{ $payment->loan?->product?->name ?? '-' }}</td>
                                <td>
                                    @if($payment->cashRegisterSession)
                                        <div class="small fw-semibold">{{ $payment->cashRegister?->name ?? ('Caja #' . $payment->cash_register_id) }}</div>
                                        <small class="text-muted">Sesión #{{ $payment->cash_register_session_id }}</small>
                                    @else
                                        <span class="badge bg-light text-muted border">Sin sesión</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ $currencySymbol }} {{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="text-end text-info">{{ $currencySymbol }} {{ number_format((float) $payment->capital, 2) }}</td>
                                <td class="text-end text-warning">{{ $currencySymbol }} {{ number_format((float) $payment->interest, 2) }}</td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ ucfirst($payment->payment_method) }}</span>
                                </td>
                                <td class="text-muted">{{ $payment->reference ?: '-' }}</td>
                                <td class="pe-3">{{ $payment->registeredBy?->name ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center py-4 text-muted">No se encontraron pagos con los filtros aplicados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($payments->hasPages())
        <div class="mt-3 d-flex justify-content-center">{{ $payments->links() }}</div>
    @endif
</div>
@endsection
