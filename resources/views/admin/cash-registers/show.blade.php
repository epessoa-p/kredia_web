@extends('layouts.app')

@section('title', 'Detalle Caja - ' . $cashRegister->name)

@push('styles')
<style>
    .cash-summary {
        border: 0;
        border-radius: 12px;
        padding: 14px;
        background: linear-gradient(135deg, #f8fafc, #eef4ff);
        box-shadow: 0 2px 8px rgba(0,0,0,.05);
    }
    .cash-summary .label { font-size: var(--fs-xs); color: #6b7280; text-transform: uppercase; }
    .cash-summary .value { font-size: var(--fs-lg); font-weight: 700; line-height: 1.1; }
</style>
@endpush

@section('page')
<div class="container-fluid">
    <x-page-header :title="$cashRegister->name" icon="cash-stack" :subtitle="'Asignada a: ' . ($cashRegister->assignedPersonal?->full_name ?? 'Sin asignar')" :back="route('cash-registers.index')">
        <x-slot:meta>
            @if($cashRegister->active)
                <span class="badge bg-success">Activa</span>
            @else
                <span class="badge bg-secondary">Inactiva</span>
            @endif
        </x-slot:meta>
        <x-btn variant="secondary" icon="pencil" :href="route('cash-registers.edit', $cashRegister)">Editar</x-btn>
    </x-page-header>

    @if($cashRegister->description)
        <div class="alert alert-light border mb-4">{{ $cashRegister->description }}</div>
    @endif

    @php
        $openSessions = $cashRegister->sessions->whereNull('closed_at')->count();
        $closedSessions = $cashRegister->sessions->whereNotNull('closed_at')->count();
        $totalCollected = $cashRegister->sessions->sum(fn($s) => (float) ($s->collected_amount ?? 0));
        $totalPayments = $cashRegister->sessions->sum('loan_payments_count');
    @endphp

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="cash-summary">
                <div class="label">Sesiones</div>
                <div class="value text-primary">{{ $cashRegister->sessions->count() }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cash-summary">
                <div class="label">Abiertas / cerradas</div>
                <div class="value text-info">{{ $openSessions }} / {{ $closedSessions }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cash-summary">
                <div class="label">Pagos vinculados</div>
                <div class="value text-success">{{ number_format((int) $totalPayments) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cash-summary">
                <div class="label">Total cobrado</div>
                <div class="value text-dark">{{ $currencySymbol }} {{ number_format($totalCollected, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h5 class="mb-0"><i class="bi bi-clock-history"></i> Historial de sesiones</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Apertura</th>
                            <th>Monto apertura</th>
                            <th>Cierre</th>
                            <th>Monto cierre</th>
                            <th class="text-end">Total cobrado</th>
                            <th class="text-end">Pagos</th>
                            <th>Abierta por</th>
                            <th>Cerrada por</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cashRegister->sessions as $session)
                            <tr class="{{ !$session->closed_at ? 'table-info' : '' }}">
                                <td>{{ $session->id }}</td>
                                <td>{{ $session->opened_at->format('d/m/Y H:i') }}</td>
                                <td><strong>{{ number_format($session->opening_amount, 2) }} {{ $currencySymbol }}</strong></td>
                                <td>
                                    @if($session->closed_at)
                                        {{ $session->closed_at->format('d/m/Y H:i') }}
                                    @else
                                        <span class="badge bg-info">En curso</span>
                                    @endif
                                </td>
                                <td>
                                    @if($session->closing_amount !== null)
                                        <strong>{{ number_format($session->closing_amount, 2) }} {{ $currencySymbol }}</strong>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end text-success fw-semibold">{{ $currencySymbol }} {{ number_format((float) ($session->collected_amount ?? 0), 2) }}</td>
                                <td class="text-end"><span class="badge bg-light text-dark border">{{ number_format((int) $session->loan_payments_count) }}</span></td>
                                <td>{{ $session->openedByUser?->name ?? '—' }}</td>
                                <td>{{ $session->closedByUser?->name ?? '—' }}</td>
                                <td>
                                    @if($session->opening_notes)
                                        <small class="text-muted">Apertura: {{ Str::limit($session->opening_notes, 40) }}</small>
                                    @endif
                                    @if($session->closing_notes)
                                        <br><small class="text-muted">Cierre: {{ Str::limit($session->closing_notes, 40) }}</small>
                                    @endif
                                    @if(!empty($session->closing_breakdown['quantities']))
                                        @php
                                            $labels = [
                                                'b200' => '200',
                                                'b100' => '100',
                                                'b50' => '50',
                                                'b20' => '20',
                                                'b10' => '10',
                                                'm5' => '5',
                                                'm2' => '2',
                                                'm1' => '1',
                                                'c50' => '0.50',
                                                'c20' => '0.20',
                                                'c10' => '0.10',
                                            ];
                                            $pieces = collect($session->closing_breakdown['quantities'])
                                                ->filter(fn ($qty) => (int) $qty > 0)
                                                ->map(function ($qty, $key) use ($labels) {
                                                    $label = $labels[$key] ?? $key;
                                                    return $qty . 'x' . $label;
                                                })
                                                ->implode(', ');
                                        @endphp
                                        @if($pieces)
                                            <br><small class="text-muted">Desglose: {{ Str::limit($pieces, 70) }}</small>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">No hay sesiones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
