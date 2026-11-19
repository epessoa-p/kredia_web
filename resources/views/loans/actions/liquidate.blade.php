@extends('layouts.app')

@section('title', 'Liquidar Préstamo')

@section('page')
<div class="container-fluid" style="max-width: 900px;">
    <x-page-header title="Liquidar Préstamo" icon="currency-dollar" subtitle="Pago total para cerrar el préstamo PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}" :back="route('loans.show', $loan)" />

    {{-- Estado de caja --}}
    @if($activeCashSession)
        <div class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 mb-3">
            <div class="small">
                <i class="bi bi-safe2-fill me-1"></i>
                Caja activa: <strong>{{ $activeCashSession->cashRegister?->name ?? ('#' . $activeCashSession->cash_register_id) }}</strong>
                · Aperturada: {{ $activeCashSession->opened_at?->format('d/m/Y H:i') }}
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle">Lista para cobrar</span>
        </div>
    @else
        <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3">
            <i class="bi bi-exclamation-octagon me-2 flex-shrink-0"></i>
            <span class="small">{{ $cashSessionError ?? 'Debes tener una caja abierta para registrar pagos.' }}</span>
        </div>
    @endif

    <div class="alert alert-warning border-0 shadow-sm">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong>Monto recomendado para liquidar:</strong>
                <span class="ms-2 fs-5">{{ $currencySymbol }} {{ number_format((float) $pending, 2) }}</span>
            </div>
            <span class="badge bg-dark">Incluye interés pendiente</span>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('loans.liquidate.store', $loan) }}" method="POST" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label">Monto de liquidación</label>
                    <input type="number" name="amount" step="0.01" min="{{ $pending }}" class="form-control form-control-sm" value="{{ old('amount', $pending) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ old('payment_date', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Método</label>
                    <select name="payment_method" class="form-select form-select-sm" required>
                        <option value="efectivo">Efectivo</option>
                        <option value="transferencia">Transferencia</option>
                        <option value="tarjeta">Tarjeta</option>
                        <option value="otro">Otro</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Referencia</label>
                    <input type="text" name="reference" class="form-control form-control-sm" value="{{ old('reference') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notas</label>
                    <input type="text" name="notes" class="form-control form-control-sm" value="{{ old('notes') }}">
                </div>
                <div class="col-12 d-flex gap-2">
                    <x-btn variant="success" type="submit" icon="lock-fill" :disabled="!$activeCashSession">Liquidar préstamo</x-btn>
                    <x-btn variant="secondary" :href="route('loans.show', $loan)">Cancelar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
