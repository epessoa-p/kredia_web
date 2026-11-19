@extends('layouts.app')

@section('title', 'Préstamo ' . $loan->code)

@section('page')
@php
    $money = fn ($v) => $currencySymbol . ' ' . number_format((float) $v, 2);
    $canAct = !$lockedReason && !$cashError;
    $installmentTone = ['paid' => 'success', 'partial' => 'info', 'pending' => 'muted'];
    $installmentLabel = ['paid' => 'Pagada', 'partial' => 'Parcial', 'pending' => 'Pendiente'];
@endphp

<x-page-header :title="'Préstamo ' . $loan->code" icon="calendar2-week"
               :subtitle="$loan->client?->name . ' · ' . ($frequencies[$loan->payment_frequency] ?? '') . ' · ' . ($methods[$loan->installment_method] ?? '')"
               :back="route('loans.index')">
    <x-slot:meta>
        <x-status-badge :status="$loan->status" />
        @if($loan->refinanced_at)<x-status-badge tone="info">Refinanciado</x-status-badge>@endif
    </x-slot:meta>
    @if($can['refinance'] && !$lockedReason && !$loan->refinanced_at)
        <x-btn variant="secondary" icon="arrow-repeat" data-bs-toggle="modal" data-bs-target="#refinanceModal">Refinanciar</x-btn>
    @endif
</x-page-header>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($loan->parentLoan)
    <div class="alert alert-light border fs-sm">Refinancia a <a href="{{ route('loans.show', $loan->parentLoan) }}">{{ $loan->parentLoan->code }}</a>.</div>
@endif
@if($loan->refinancedBy)
    <div class="alert alert-light border fs-sm">Saldo trasladado a <a href="{{ route('loans.installments.show', $loan->refinancedBy) }}">{{ $loan->refinancedBy->code }}</a>.</div>
@endif

<div class="row g-3 mb-3">
    @foreach([
        ['Prestado', $money($loan->amount), 'cash-stack', null],
        ['Pagado', $money($summary['paid']), 'check2-circle', 'text-success'],
        ['Pendiente', $money($summary['outstanding']), 'hourglass-split', null],
        ['Vencido', $money($summary['overdue_amount']), 'exclamation-triangle', $summary['overdue_amount'] > 0 ? 'text-danger' : null],
    ] as [$label, $value, $icon, $class])
        <div class="col-6 col-lg-3">
            <div class="stat-card h-100">
                <div class="kpi-label"><i class="bi bi-{{ $icon }}"></i> {{ $label }}</div>
                <div class="kpi-value {{ $class }}">{{ $value }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-list-ol text-mod"></i> Plan de pagos</span>
                <span class="fs-sm text-muted">{{ $summary['paid_count'] }} de {{ $summary['count'] }} cuotas pagadas</span>
            </div>
            <div class="table-responsive">
                <table class="table table-list align-middle mb-0">
                    <thead><tr><th>#</th><th>Vence</th><th class="text-end">Capital</th><th class="text-end">Interés</th><th class="text-end">Mora</th><th class="text-end">Cuota</th><th class="text-end">Falta</th><th>Estado</th></tr></thead>
                    <tbody>
                        @foreach($loan->installments as $i)
                            <tr class="{{ $i->isOverdue() ? 'table-danger' : '' }}">
                                <td>{{ $i->number }}</td>
                                <td>{{ $i->due_date->format('d/m/Y') }}@if($i->daysLate())<div class="fs-xs text-danger">{{ $i->daysLate() }} días de atraso</div>@endif</td>
                                <td class="text-end">{{ number_format((float) $i->capital, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $i->interest, 2) }}</td>
                                <td class="text-end">{{ (float) $i->penalty > 0 ? number_format((float) $i->penalty, 2) : '—' }}</td>
                                <td class="text-end fw-semibold">{{ number_format((float) $i->amount, 2) }}</td>
                                <td class="text-end">{{ number_format($i->pendingTotal(), 2) }}</td>
                                <td><x-status-badge :tone="$i->isOverdue() ? 'danger' : $installmentTone[$i->status]">{{ $i->isOverdue() ? 'Vencida' : $installmentLabel[$i->status] }}</x-status-badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-receipt text-mod"></i> Cobros</div>
            <div class="table-responsive">
                <table class="table table-list align-middle mb-0">
                    <thead><tr><th>Fecha</th><th class="text-end">Monto</th><th>Aplicado a</th><th>Método</th><th>Ubicación</th><th></th></tr></thead>
                    <tbody>
                        @forelse($loan->payments as $p)
                            <tr class="{{ $p->isVoided() ? 'text-decoration-line-through text-muted' : '' }}">
                                <td>{{ $p->payment_date?->format('d/m/Y') }}<div class="fs-xs text-muted">{{ $p->created_at?->format('H:i') }}</div></td>
                                <td class="text-end fw-semibold">{{ number_format((float) $p->amount, 2) }}</td>
                                <td class="fs-xs">
                                    @if($p->payment_type === 'refinance')
                                        Saldo trasladado (refinanciación)
                                    @else
                                        Cap. {{ number_format((float) $p->capital, 2) }} · Int. {{ number_format((float) $p->interest, 2) }}@if((float) $p->penalty > 0) · Mora {{ number_format((float) $p->penalty, 2) }}@endif
                                        <div class="text-muted">Cuotas: {{ $p->allocations->map(fn ($a) => $a->installment?->number)->filter()->implode(', ') }}</div>
                                    @endif
                                    @if($p->isVoided())<div class="text-danger">Anulado: {{ $p->void_reason }}</div>@endif
                                </td>
                                <td class="fs-sm">{{ ucfirst($p->payment_method) }}</td>
                                <td>
                                    @if($p->latitude && $p->longitude)
                                        <x-icon-btn icon="geo-alt" label="Ver en el mapa" :href="'https://www.google.com/maps?q=' . $p->latitude . ',' . $p->longitude" target="_blank" rel="noopener" />
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if($can['void'] && !$p->isVoided() && $p->payment_type === 'installment' && !$loan->refinanced_at)
                                        <x-icon-btn icon="x-circle" label="Anular cobro" variant="danger"
                                                    data-bs-toggle="modal" data-bs-target="#voidModal"
                                                    data-action="{{ route('loans.installments.void', [$loan, $p]) }}"
                                                    data-amount="{{ number_format((float) $p->amount, 2) }}" />
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">Sin cobros todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        @if($can['pay'])
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cash-coin text-mod"></i> Registrar cobro</div>
                <div class="card-body">
                    @if($lockedReason)
                        <div class="alert alert-light border mb-0">{{ $lockedReason }}</div>
                    @elseif($cashError)
                        <div class="alert alert-warning mb-0">{{ $cashError }}</div>
                    @else
                        <form action="{{ route('loans.installments.pay', $loan) }}" method="POST">
                            @csrf
                            <input type="hidden" name="request_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @if($summary['overdue_amount'] > 0)
                                    <x-btn variant="secondary" class="js-fill" data-value="{{ $summary['overdue_amount'] }}">Vencido {{ number_format($summary['overdue_amount'], 2) }}</x-btn>
                                @endif
                                @if($summary['next_due_amount'])
                                    <x-btn variant="secondary" class="js-fill" data-value="{{ $summary['next_due_amount'] }}">Próxima cuota {{ number_format($summary['next_due_amount'], 2) }}</x-btn>
                                @endif
                                <x-btn variant="secondary" class="js-fill" data-value="{{ $summary['outstanding'] }}">Todo</x-btn>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="pay_amount">Monto ({{ $currencySymbol }})</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $summary['outstanding'] }}" id="pay_amount" name="amount" class="form-control" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="payment_method">Método</label>
                                <select id="payment_method" name="payment_method" class="form-select">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="transferencia">Transferencia</option>
                                    <option value="qr">QR</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="reference">Referencia</label>
                                <input type="text" id="reference" name="reference" class="form-control">
                            </div>
                            <x-btn type="submit" variant="success" icon="cash" size="md" :block="true">Cobrar</x-btn>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-info-circle text-mod"></i> Datos</div>
            <div class="card-body fs-sm">
                <dl class="row mb-0">
                    <dt class="col-6 fw-normal text-muted">Tasa mensual</dt><dd class="col-6">{{ number_format((float) $loan->interest_rate, 2) }}%</dd>
                    <dt class="col-6 fw-normal text-muted">Cuotas</dt><dd class="col-6">{{ $loan->installments_count }} × {{ number_format((float) $loan->installment_amount, 2) }}</dd>
                    <dt class="col-6 fw-normal text-muted">Desembolso</dt><dd class="col-6">{{ $loan->start_date?->format('d/m/Y') }}</dd>
                    <dt class="col-6 fw-normal text-muted">Última cuota</dt><dd class="col-6">{{ $loan->end_date?->format('d/m/Y') }}</dd>
                    <dt class="col-6 fw-normal text-muted">Próximo vencimiento</dt><dd class="col-6">{{ $summary['next_due_date'] ? \Carbon\Carbon::parse($summary['next_due_date'])->format('d/m/Y') : '—' }}</dd>
                    <dt class="col-6 fw-normal text-muted">Sucursal</dt><dd class="col-6">{{ $loan->branch?->name }}</dd>
                    <dt class="col-6 fw-normal text-muted">Cobrador</dt><dd class="col-6">{{ $loan->collector?->full_name ?? '—' }}</dd>
                    <dt class="col-6 fw-normal text-muted">Teléfono</dt><dd class="col-6">{{ $loan->client?->phone ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-person-check text-mod"></i> Garantes</div>
            <div class="card-body fs-sm">
                @forelse($loan->guarantors as $g)
                    <div class="{{ !$loop->last ? 'border-bottom pb-2 mb-2' : '' }}">
                        <div class="fw-semibold">{{ $g->name }}</div>
                        <div class="text-muted">{{ collect([$g->id_number, $g->phone, $g->relationship])->filter()->implode(' · ') }}</div>
                        @if($g->address)<div class="text-muted">{{ $g->address }}</div>@endif
                    </div>
                @empty
                    <span class="text-muted">Sin garantes.</span>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Anular cobro --}}
<div class="modal fade" id="voidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="voidForm">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Anular cobro de {{ $currencySymbol }} <span id="voidAmount"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <p class="fs-sm text-muted">Se revierte lo aplicado a las cuotas y se registra la salida del dinero en tu caja abierta.</p>
                <label class="form-label" for="void_reason">Motivo</label>
                <textarea id="void_reason" name="reason" rows="2" class="form-control" required minlength="5"></textarea>
            </div>
            <div class="modal-footer">
                <x-btn variant="secondary" data-bs-dismiss="modal">Cancelar</x-btn>
                <x-btn type="submit" variant="danger" icon="x-circle">Anular</x-btn>
            </div>
        </form>
    </div>
</div>

{{-- Refinanciar --}}
@if($can['refinance'] && !$lockedReason && !$loan->refinanced_at)
<div class="modal fade" id="refinanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('loans.installments.refinance', $loan) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Refinanciar {{ $loan->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12 fs-sm">Saldo de capital que pasa al nuevo préstamo: <strong>{{ $money($summary['outstanding_capital']) }}</strong>. Del saldo no sale dinero de caja.</div>
                <div class="col-6"><label class="form-label" for="extra_amount">Dinero nuevo a entregar</label><input type="number" step="0.01" min="0" id="extra_amount" name="extra_amount" class="form-control" value="0"></div>
                <div class="col-6"><label class="form-label" for="rf_rate">Interés mensual (%)</label><input type="number" step="0.01" min="0" id="rf_rate" name="interest_rate" class="form-control" value="{{ $loan->interest_rate }}" required></div>
                <div class="col-4"><label class="form-label" for="rf_count">Cuotas</label><input type="number" min="1" id="rf_count" name="installments_count" class="form-control" value="{{ $loan->installments_count }}" required></div>
                <div class="col-4"><label class="form-label" for="rf_freq">Frecuencia</label>
                    <select id="rf_freq" name="payment_frequency" class="form-select">@foreach($frequencies as $k => $l)<option value="{{ $k }}" @selected($loan->payment_frequency === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-4"><label class="form-label" for="rf_method">Cálculo</label>
                    <select id="rf_method" name="installment_method" class="form-select">@foreach($methods as $k => $l)<option value="{{ $k }}" @selected($loan->installment_method === $k)>{{ $l }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label" for="rf_notes">Notas</label><textarea id="rf_notes" name="notes" rows="2" class="form-control"></textarea></div>
            </div>
            <div class="modal-footer">
                <x-btn variant="secondary" data-bs-dismiss="modal">Cancelar</x-btn>
                <x-btn type="submit" icon="arrow-repeat">Refinanciar</x-btn>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('.js-fill').forEach(btn => btn.addEventListener('click', () => {
    document.getElementById('pay_amount').value = Number(btn.dataset.value).toFixed(2);
}));
document.getElementById('voidModal')?.addEventListener('show.bs.modal', e => {
    const btn = e.relatedTarget;
    document.getElementById('voidForm').action = btn.dataset.action;
    document.getElementById('voidAmount').textContent = btn.dataset.amount;
});
</script>
@endpush
