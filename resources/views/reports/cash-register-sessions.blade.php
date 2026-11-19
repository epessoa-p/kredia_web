@extends('layouts.app')

@section('title', 'Reporte de Aperturas y Cierres de Caja')

@push('styles')
<style>
    .cash-stat {
        border: 0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 3px 12px rgba(16, 24, 40, 0.08);
        padding: 14px;
    }
    .cash-stat .label {
        font-size: var(--fs-xs);
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #667085;
    }
    .cash-stat .value {
        font-size: var(--fs-lg);
        font-weight: 700;
        margin-top: 2px;
        line-height: 1.1;
    }
    .cash-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border-radius: 999px;
        padding: 2px 10px;
        font-size: var(--fs-xs);
        font-weight: 600;
    }
    .cash-chip.open { background: #e6f9f0; color: #067647; }
    .cash-chip.closed { background: #eff4ff; color: #3538cd; }
    .cash-toolbar {
        background: linear-gradient(135deg, #f9fafb, #eef4ff);
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }
    .cash-table thead th {
        white-space: nowrap;
        font-size: var(--fs-xs);
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #667085;
    }
</style>
@endpush

@section('page')
<div class="container-fluid">
    <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none">Reportes</a></li>
                    <li class="breadcrumb-item active">Aperturas y cierres de caja</li>
                </ol>
            </nav>
            <x-page-header title="Aperturas y Cierres de Caja" icon="safe2" :back="route('reports.index')" />
            <p class="text-muted mb-3">Control detallado por sesión de caja, con cobros vinculados por capital e interés.</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="cash-stat">
                <div class="label">Sesiones</div>
                <div class="value text-primary">{{ number_format($totals['sessions']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cash-stat">
                <div class="label">Abiertas / cerradas</div>
                <div class="value text-info">{{ number_format($totals['open_sessions']) }} / {{ number_format($totals['closed_sessions']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cash-stat">
                <div class="label">Pagos vinculados</div>
                <div class="value text-success">{{ number_format($totals['payments']) }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="cash-stat">
                <div class="label">Total cobrado</div>
                <div class="value text-dark">{{ $currencySymbol }} {{ number_format($totals['amount'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 cash-toolbar">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('reports.cash-register-sessions') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Caja</label>
                    <select name="cash_register_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($cashRegisters as $cash)
                            <option value="{{ $cash->id }}" {{ (string)($filters['cash_register_id'] ?? '') === (string)$cash->id ? 'selected' : '' }}>
                                {{ $cash->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Estado</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>Abierta</option>
                        <option value="closed" {{ ($filters['status'] ?? '') === 'closed' ? 'selected' : '' }}>Cerrada</option>
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
                    <x-icon-btn icon="arrow-clockwise" label="Limpiar filtros" :href="route('reports.cash-register-sessions')" />
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0 cash-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Caja</th>
                            <th>Apertura</th>
                            <th>Cierre</th>
                            <th>Responsables</th>
                            <th class="text-end">Apertura {{ $currencySymbol }}</th>
                            <th class="text-end">Cierre {{ $currencySymbol }}</th>
                            <th class="text-end">Cobrado</th>
                            <th class="text-end">Capital</th>
                            <th class="text-end">Interés</th>
                            <th class="text-end pe-3">Pagos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sessions as $session)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $session->cashRegister?->name ?? ('Caja #' . $session->cash_register_id) }}</div>
                                    <small class="text-muted">{{ $session->cashRegister?->company?->name }}</small>
                                    <div class="mt-1">
                                        @if($session->closed_at)
                                            <span class="cash-chip closed"><i class="bi bi-lock-fill"></i>Cerrada</span>
                                        @else
                                            <span class="cash-chip open"><i class="bi bi-unlock-fill"></i>Abierta</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $session->opened_at?->format('d/m/Y') }}</div>
                                    <small class="text-muted">{{ $session->opened_at?->format('H:i') }}</small>
                                </td>
                                <td>
                                    @if($session->closed_at)
                                        <div class="fw-semibold">{{ $session->closed_at->format('d/m/Y') }}</div>
                                        <small class="text-muted">{{ $session->closed_at->format('H:i') }}</small>
                                    @else
                                        <span class="text-muted">En curso</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="small"><strong>Abrió:</strong> {{ $session->openedByUser?->name ?? '-' }}</div>
                                    <div class="small"><strong>Cerró:</strong> {{ $session->closedByUser?->name ?? '-' }}</div>
                                </td>
                                <td class="text-end">{{ number_format((float) $session->opening_amount, 2) }}</td>
                                <td class="text-end">{{ $session->closing_amount !== null ? number_format((float) $session->closing_amount, 2) : '-' }}</td>
                                <td class="text-end fw-semibold text-success">{{ $currencySymbol }} {{ number_format((float) ($session->collected_amount ?? 0), 2) }}</td>
                                <td class="text-end text-info">{{ $currencySymbol }} {{ number_format((float) ($session->collected_capital ?? 0), 2) }}</td>
                                <td class="text-end text-warning">{{ $currencySymbol }} {{ number_format((float) ($session->collected_interest ?? 0), 2) }}</td>
                                <td class="text-end pe-3"><span class="badge bg-light text-dark border">{{ number_format((int) $session->loan_payments_count) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">No hay sesiones de caja para los filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($sessions->hasPages())
        <div class="mt-3 d-flex justify-content-center">{{ $sessions->links() }}</div>
    @endif
</div>
@endsection
