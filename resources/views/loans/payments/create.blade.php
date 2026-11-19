@extends('layouts.app')

@section('title', 'Registrar Pago')

@section('page')
<div class="container-fluid" style="max-width: 900px;">
    <x-page-header title="Registrar Pago" icon="cash-stack" subtitle="Préstamo PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }} - {{ $loan->client?->name ?? 'Sin cliente' }}" :back="route('loans.show', $loan)" />

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><small class="text-muted">Monto préstamo</small><div class="fw-bold">{{ $currencySymbol }} {{ number_format((float) $loan->amount, 2) }}</div></div>
                <div class="col-md-3"><small class="text-muted">Total a pagar</small><div class="fw-bold">{{ $currencySymbol }} {{ number_format((float) $loan->total_to_pay, 2) }}</div></div>
                <div class="col-md-3"><small class="text-muted">Total pagado</small><div class="fw-bold text-success">{{ $currencySymbol }} {{ number_format((float) $loan->total_paid, 2) }}</div></div>
                <div class="col-md-3"><small class="text-muted">Pendiente</small><div class="fw-bold text-danger">{{ $currencySymbol }} {{ number_format((float) ($loan->total_to_pay - $loan->total_paid), 2) }}</div></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('loans.payments.store', $loan) }}" method="POST" class="row g-3">
                @csrf

                <div class="col-12">
                    @if($activeCashSession)
                        <div class="alert alert-success d-flex align-items-center justify-content-between py-2 px-3 mb-2">
                            <div class="small">
                                <i class="bi bi-safe2-fill me-1"></i>
                                Caja activa: <strong>{{ $activeCashSession->cashRegister?->name ?? ('#' . $activeCashSession->cash_register_id) }}</strong>
                                · Aperturada: {{ $activeCashSession->opened_at?->format('d/m/Y H:i') }}
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle">Lista para cobrar</span>
                        </div>
                    @else
                        <div class="alert alert-danger py-2 px-3 mb-2">
                            <i class="bi bi-exclamation-octagon me-1"></i>
                            {{ $cashSessionError ?? 'Debes aperturar una caja para poder registrar pagos.' }}
                        </div>
                    @endif
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tipo de pago</label>
                    <select name="payment_type" id="payment_type" class="form-select form-select-sm" required>
                        <option value="interest" {{ old('payment_type') === 'interest' ? 'selected' : '' }}>Interés</option>
                        <option value="capital" {{ old('payment_type') === 'capital' ? 'selected' : '' }}>Capital</option>
                        <option value="mixed" {{ old('payment_type') === 'mixed' ? 'selected' : '' }}>Mixto</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Monto</label>
                    <input type="number" step="0.01" name="amount" id="amount" class="form-control form-control-sm" value="{{ old('amount') }}" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Fecha del pago</label>
                    @if($canSetPaymentDate)
                        <input type="date" name="payment_date" class="form-control form-control-sm" value="{{ old('payment_date', now()->toDateString()) }}">
                        <small class="text-muted"><i class="bi bi-unlock-fill me-1 text-primary"></i>Puedes modificar la fecha.</small>
                    @else
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-lock-fill"></i></span>
                            <input type="text" class="form-control form-control-sm bg-light border-start-0"
                                value="{{ now()->format('d/m/Y') }}" readonly>
                        </div>
                        <small class="text-muted">Se registra con la fecha de hoy.</small>
                    @endif
                </div>

                <div class="col-md-6" id="capital-field">
                    <label class="form-label">Capital</label>
                    <input type="number" step="0.01" name="capital" class="form-control form-control-sm" value="{{ old('capital') }}">
                </div>

                <div class="col-md-6" id="interest-field">
                    <label class="form-label">Interés</label>
                    <input type="number" step="0.01" name="interest" class="form-control form-control-sm" value="{{ old('interest') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Método de pago</label>
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

                <div class="col-12">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" rows="2" class="form-control form-control-sm">{{ old('notes') }}</textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <x-btn variant="success" type="submit" icon="check2-circle" :disabled="!$activeCashSession">Registrar pago</x-btn>
                    <x-btn variant="secondary" :href="route('loans.show', $loan)">Cancelar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (() => {
        const type = document.getElementById('payment_type');
        const capitalField = document.getElementById('capital-field');
        const interestField = document.getElementById('interest-field');

        function syncFields() {
            const val = type.value;
            capitalField.style.display = (val === 'interest') ? 'none' : '';
            interestField.style.display = (val === 'capital') ? 'none' : '';
        }

        type.addEventListener('change', syncFields);
        syncFields();
    })();
</script>
@endpush
@endsection
