@extends('layouts.app')

@section('title', 'Pagos de Préstamos')

@push('styles')
<style>
    .filter-card { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: .75rem; }
</style>
@endpush

@section('page')
<div class="container-fluid">
    <x-page-header title="Pagos de Préstamos" icon="receipt-cutoff" :count="$payments->total() . ' pagos'">
        <x-btn variant="secondary" icon="arrow-left" :href="route('loans.index')">Volver a préstamos</x-btn>
    </x-page-header>

    {{-- Branch Selector --}}
    @if($branches->count() > 1)
    <div class="mb-3">
        <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
        <div class="chip-row d-inline-flex">
            <a class="chip {{ $selectedBranchId === 0 ? 'is-active' : '' }}"
               href="{{ route('loans.payments.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => 0])) }}">
                <i class="bi bi-grid-3x3-gap-fill fs-xs" style="opacity:.8;"></i> Todos
            </a>
            @foreach($branches as $branch)
                @php $bColor = $branch->color ?? '#0d6efd'; @endphp
                <a class="chip {{ (int) $selectedBranchId === (int) $branch->id ? 'is-active' : '' }}"
                   style="--branch-color: {{ $bColor }};"
                   href="{{ route('loans.payments.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => $branch->id])) }}">
                    <span class="chip-dot"></span>{{ $branch->name }}
                </a>
            @endforeach
        </div>
    </div>
    @elseif($branches->count() === 1)
    <div class="mb-3">
        @php $bColor = $branches->first()->color ?? '#0d6efd'; @endphp
        <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
        <span class="chip is-active" style="--branch-color: {{ $bColor }}; cursor:default;">
            <span class="chip-dot"></span>{{ $branches->first()->name }}
        </span>
    </div>
    @endif

    {{-- Filters --}}
    <div class="card filter-card mb-3 border-0">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('loans.payments.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="sucursal_id" value="{{ $selectedBranchId }}">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Búsqueda</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control form-control-sm border-start-0"
                               value="{{ $q }}" placeholder="Referencia o cliente">
                    </div>
                </div>
                <div class="col-auto">
                    <x-btn type="submit" icon="funnel">Filtrar</x-btn>
                </div>
                <div class="col-auto">
                    <x-btn variant="secondary" :href="route('loans.payments.index', ['sucursal_id' => $selectedBranchId])">Limpiar</x-btn>
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
                            <th>Fecha</th>
                            <th>Préstamo</th>
                            <th>Sucursal</th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>Caja</th>
                            <th class="text-end">Monto</th>
                            <th class="text-end">Capital</th>
                            <th class="text-end">Interés</th>
                            <th>Método</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $payment)
                            <tr>
                                <td>{{ $payment->payment_date?->format('d/m/Y') }}</td>
                                <td>
                                    <a href="{{ route('loans.show', $payment->loan_id) }}" class="text-decoration-none fw-semibold">
                                        {{ $payment->loan?->code ?? ('#' . $payment->loan_id) }}
                                    </a>
                                </td>
                                <td>
                                    @if($payment->loan?->branch)
                                        @php $bColor = $payment->loan->branch->color ?? '#0d6efd'; @endphp
                                        <span class="badge"
                                              style="background:{{ $bColor }}20; color:{{ $bColor }}; border:1px solid {{ $bColor }}50; font-weight:500;">
                                            {{ $payment->loan->branch->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $payment->loan?->client?->name ?? '-' }}</td>
                                <td>{{ $payment->loan?->product?->name ?? '-' }}</td>
                                <td>
                                    @if($payment->cashRegisterSession)
                                        <div class="fw-semibold small">{{ $payment->cashRegister?->name ?? ('Caja #' . $payment->cash_register_id) }}</div>
                                        <small class="text-muted">Sesión #{{ $payment->cash_register_session_id }}</small>
                                    @else
                                        <span class="badge bg-light text-muted border">Sin sesión</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $currencySymbol }} {{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="text-end">{{ $currencySymbol }} {{ number_format((float) $payment->capital, 2) }}</td>
                                <td class="text-end">{{ $currencySymbol }} {{ number_format((float) $payment->interest, 2) }}</td>
                                <td>{{ $payment->payment_method }}</td>
                                <td>{{ $payment->reference ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-4 text-muted">No hay pagos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex justify-content-center">
        {{ $payments->links() }}
    </div>
</div>
@endsection
