@extends('layouts.app')

@section('title', 'Nuevo préstamo en cuotas')

@section('page')
<x-page-header title="Nuevo préstamo en cuotas" icon="calendar2-week" subtitle="Plan de pagos diario, semanal, quincenal o mensual" :back="route('loans.index')" />

@if($cashError)
    <div class="alert alert-warning"><i class="bi bi-safe"></i> {{ $cashError }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form action="{{ route('loans.installments.store') }}" method="POST" id="installmentForm">
    @csrf
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person text-mod"></i> Cliente y sucursal</div>
                <div class="card-body row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="client_id">Cliente</label>
                        <select id="client_id" name="client_id" class="form-select" required>
                            <option value="">— Selecciona —</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" @selected(old('client_id', $selectedClientId) == $client->id)>{{ $client->name }}{{ $client->id_number ? ' · ' . $client->id_number : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="branch_id">Sucursal</label>
                        <select id="branch_id" name="branch_id" class="form-select" required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="collector_id">Cobrador</label>
                        <select id="collector_id" name="collector_id" class="form-select">
                            <option value="">— Sin asignar —</option>
                            @foreach($collectors as $collector)
                                <option value="{{ $collector->id }}" @selected(old('collector_id') == $collector->id)>{{ $collector->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($rules->isNotEmpty())
                        <div class="col-md-6">
                            <label class="form-label" for="credit_category_rule_id">Producto de crédito</label>
                            <select id="credit_category_rule_id" name="credit_category_rule_id" class="form-select">
                                <option value="">— Manual —</option>
                                @foreach($rules as $rule)
                                    <option value="{{ $rule->id }}"
                                            data-rate="{{ $rule->interest_rate }}"
                                            data-method="{{ $rule->installment_method }}"
                                            data-frequency="{{ $rule->payment_frequency }}"
                                            data-skip="{{ $rule->skip_sundays ? 1 : 0 }}"
                                            @selected(old('credit_category_rule_id') == $rule->id)>
                                        {{ $rule->creditCategory?->name }}{{ $rule->name ? ' · ' . $rule->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-calculator text-mod"></i> Condiciones</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="amount">Monto a prestar ({{ $currencySymbol }})</label>
                        <input type="number" step="0.01" min="1" id="amount" name="amount" class="form-control" value="{{ old('amount') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="interest_rate">Interés mensual (%)</label>
                        <input type="number" step="0.01" min="0" max="{{ $maxRate ?? 100 }}" id="interest_rate" name="interest_rate" class="form-control" value="{{ old('interest_rate') }}" required>
                        @if($maxRate)<div class="form-text">Máximo permitido: {{ $maxRate }}%</div>@endif
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="installments_count">N.º de cuotas</label>
                        <input type="number" min="1" max="730" id="installments_count" name="installments_count" class="form-control" value="{{ old('installments_count', 20) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="payment_frequency">Frecuencia</label>
                        <select id="payment_frequency" name="payment_frequency" class="form-select">
                            @foreach($frequencies as $key => $label)
                                <option value="{{ $key }}" @selected(old('payment_frequency', 'daily') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="installment_method">Cálculo</label>
                        <select id="installment_method" name="installment_method" class="form-select">
                            @foreach($methods as $key => $label)
                                <option value="{{ $key }}" @selected(old('installment_method', 'flat') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="start_date">Fecha de desembolso</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" value="{{ old('start_date', today()->toDateString()) }}">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input type="hidden" name="skip_sundays" value="0">
                            <input type="checkbox" class="form-check-input" id="skip_sundays" name="skip_sundays" value="1" @checked(old('skip_sundays', true))>
                            <label class="form-check-label" for="skip_sundays">No cobrar los domingos (la cuota pasa al lunes)</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="notes">Notas</label>
                        <textarea id="notes" name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person-check text-mod"></i> Garante (opcional)</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="g_name">Nombre completo</label>
                        <input type="text" id="g_name" name="guarantors[0][name]" class="form-control" value="{{ old('guarantors.0.name') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="g_id">Documento</label>
                        <input type="text" id="g_id" name="guarantors[0][id_number]" class="form-control" value="{{ old('guarantors.0.id_number') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="g_phone">Teléfono</label>
                        <input type="tel" id="g_phone" name="guarantors[0][phone]" class="form-control" value="{{ old('guarantors.0.phone') }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="g_address">Dirección</label>
                        <input type="text" id="g_address" name="guarantors[0][address]" class="form-control" value="{{ old('guarantors.0.address') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="g_rel">Parentesco / relación</label>
                        <input type="text" id="g_rel" name="guarantors[0][relationship]" class="form-control" value="{{ old('guarantors.0.relationship') }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card position-sticky" style="top: 70px;">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar2-week text-mod"></i> Plan de pagos</span>
                    <x-btn variant="secondary" icon="arrow-repeat" id="previewBtn">Calcular</x-btn>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3 text-center">
                        <div class="col-4"><div class="kpi-label">Cuota</div><div class="fs-md fw-bold" id="pvInstallment">—</div></div>
                        <div class="col-4"><div class="kpi-label">Interés</div><div class="fs-md fw-bold" id="pvInterest">—</div></div>
                        <div class="col-4"><div class="kpi-label">Total</div><div class="fs-md fw-bold text-mod" id="pvTotal">—</div></div>
                    </div>
                    <div class="table-responsive" style="max-height: 340px;">
                        <table class="table table-sm table-list mb-0">
                            <thead><tr><th>#</th><th>Vence</th><th class="text-end">Capital</th><th class="text-end">Interés</th><th class="text-end">Cuota</th></tr></thead>
                            <tbody id="pvRows"><tr><td colspan="5" class="text-center text-muted py-3">Completa las condiciones y presiona Calcular.</td></tr></tbody>
                        </table>
                    </div>
                    <div class="text-danger fs-sm mt-2 d-none" id="pvError"></div>
                </div>
                <div class="card-footer d-flex justify-content-end gap-2">
                    <x-btn variant="secondary" :href="route('loans.index')">Cancelar</x-btn>
                    <x-btn type="submit" icon="check-lg" :disabled="(bool) $cashError">Registrar y desembolsar</x-btn>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('installmentForm');
    const money = v => '{{ $currencySymbol }} ' + Number(v).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtDate = d => d.split('-').reverse().join('/');
    let timer;

    // Al elegir un producto de crédito se precargan su tasa, cálculo y frecuencia.
    const rule = document.getElementById('credit_category_rule_id');
    rule?.addEventListener('change', () => {
        const o = rule.selectedOptions[0];
        if (!o || !o.value) return;
        if (o.dataset.rate) form.interest_rate.value = o.dataset.rate;
        if (o.dataset.method) form.installment_method.value = o.dataset.method;
        if (o.dataset.frequency) form.payment_frequency.value = o.dataset.frequency;
        form.skip_sundays[1].checked = o.dataset.skip === '1';
        preview();
    });

    async function preview() {
        const err = document.getElementById('pvError');
        err.classList.add('d-none');
        if (!form.amount.value || !form.interest_rate.value || !form.installments_count.value) return;

        const body = new FormData(form);
        body.set('skip_sundays', form.skip_sundays[1].checked ? '1' : '0');
        const res = await fetch('{{ route('loans.installments.preview') }}', {
            method: 'POST', body, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        });
        const data = await res.json();
        if (!res.ok) {
            err.textContent = data.message || 'Revisa las condiciones.';
            err.classList.remove('d-none');
            return;
        }
        document.getElementById('pvInstallment').textContent = money(data.installments[0].amount);
        document.getElementById('pvInterest').textContent = money(data.interest);
        document.getElementById('pvTotal').textContent = money(data.total);
        document.getElementById('pvRows').innerHTML = data.installments.map(r =>
            `<tr><td>${r.number}</td><td>${fmtDate(r.due_date)}</td><td class="text-end">${money(r.capital)}</td><td class="text-end">${money(r.interest)}</td><td class="text-end fw-semibold">${money(r.amount)}</td></tr>`
        ).join('');
    }

    document.getElementById('previewBtn').addEventListener('click', preview);
    ['amount', 'interest_rate', 'installments_count', 'payment_frequency', 'installment_method', 'start_date', 'skip_sundays']
        .forEach(name => form.querySelectorAll(`[name="${name}"]`).forEach(el => el.addEventListener('change', () => {
            clearTimeout(timer); timer = setTimeout(preview, 250);
        })));
    preview();
})();
</script>
@endpush
