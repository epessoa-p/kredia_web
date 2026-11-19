@extends('layouts.app')

@section('title', 'Movimientos')

@push('styles')
<style>
    .mv-hero {
        border: 1px solid #dce4ef;
        border-radius: 16px;
        background:
            radial-gradient(circle at 8% 10%, rgba(255, 255, 255, 0.8), transparent 44%),
            radial-gradient(circle at 85% 85%, rgba(22, 163, 74, 0.09), transparent 40%),
            linear-gradient(135deg, #f8fbff 0%, #f2f7ff 52%, #eefaf3 100%);
        padding: 18px;
        box-shadow: 0 10px 22px rgba(16, 24, 40, 0.06);
    }

    .mv-tab-bar {
        background: #f4f7fb;
        border: 1px solid #dde6f2;
        border-radius: 12px;
        display: grid;
        grid-template-columns: 1fr 1fr;
        padding: 4px;
        gap: 4px;
    }

    .mv-tab {
        text-align: center;
        border-radius: 9px;
        text-decoration: none;
        color: #475467;
        font-weight: 600;
        padding: 8px 10px;
        font-size: var(--fs-base);
    }

    .mv-tab.active {
        background: #1f3449;
        color: #fff;
        box-shadow: 0 4px 10px rgba(31, 52, 73, 0.2);
    }

    .mv-kpi {
        border: 1px solid #e6edf5;
        border-radius: 12px;
        background: #fff;
        padding: 12px;
        min-height: 98px;
    }

    .mv-kpi .label {
        color: #667085;
        font-size: var(--fs-xs);
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .mv-kpi .value {
        font-size: var(--fs-lg);
        line-height: 1.1;
        font-weight: 700;
        margin-top: 3px;
        color: #101828;
    }

    .mv-filter-box {
        border: 1px solid #dbe3f0;
        background: linear-gradient(140deg, #ffffff 0%, #f7f9fd 100%);
        border-radius: 14px;
        box-shadow: 0 8px 18px rgba(16, 24, 40, 0.05);
    }

    .mv-table thead th {
        white-space: nowrap;
        font-size: var(--fs-xs);
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #667085;
        padding: 10px 8px;
        vertical-align: middle;
        border-bottom: 2px solid #e4e9f2;
        font-weight: 600;
    }

    /* Aire vertical entre filas sin ensanchar la tabla. */
    .mv-table tbody td {
        padding: 9px 8px;
        vertical-align: middle;
    }

    .mv-closure-date {
        font-size: var(--fs-sm);
        line-height: 1.1;
    }

    .mv-closure-time {
        font-size: var(--fs-xs);
    }

    .mv-cash-name {
        font-size: var(--fs-base);
        line-height: 1.15;
    }

    .mv-status-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 3px 10px;
        font-size: var(--fs-xs);
        font-weight: 600;
        line-height: 1;
    }

    .mv-status-pill.open {
        background: #e6f8ef;
        color: #027a48;
    }

    .mv-status-pill.closed {
        background: #edf3ff;
        color: #3538cd;
    }

    .mv-status-pill.mismatch-ok {
        background: #ecfdf3;
        color: #027a48;
    }

    .mv-status-pill.mismatch-alert {
        background: #fff1f0;
        color: #b42318;
    }

    .mv-status-pill.deletion {
        background: #fff8e6;
        color: #92400e;
        border: 1px solid #f5d488;
    }

    /* Columna combinada de montos: apertura arriba, cierre abajo. */
    .mv-amount-line {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 6px;
        white-space: nowrap;
        line-height: 1.35;
    }
    .mv-amount-tag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px;
        height: 18px;
        border-radius: 5px;
        font-size: var(--fs-xs);
        font-weight: 700;
        flex-shrink: 0;
    }
    .mv-amount-tag.open  { background: #dcfce7; color: #16a34a; }   /* apertura: verde */
    .mv-amount-tag.close { background: #dbeafe; color: #2563eb; }   /* cierre: azul */
    .mv-amount-tag.moves   { background: #eef2ff; color: #4f46e5; } /* movimientos: índigo */
    .mv-amount-tag.deleted { background: #fff8e6; color: #b45309; } /* eliminados: ámbar */

    /* Columna combinada de fechas: apertura arriba, cierre abajo. */
    .mv-date-line {
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
        line-height: 1.5;
    }

    .mv-amount-positive { color: #067647; font-weight: 700; }
    .mv-amount-negative { color: #b42318; font-weight: 700; }

    .mv-session-row {
        cursor: pointer;
        transition: background-color .18s ease;
    }

    .mv-session-row:hover {
        background: #f8fbff;
    }

    .mv-session-row.is-mismatch {
        background: linear-gradient(90deg, rgba(244, 67, 54, 0.08) 0%, rgba(244, 67, 54, 0.02) 100%);
    }

    .mv-expand-indicator {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eef4ff;
        color: #3559b8;
        transition: transform .18s ease, background-color .18s ease;
        font-size: var(--fs-sm);
    }

    .mv-session-row.is-open .mv-expand-indicator {
        transform: rotate(180deg);
        background: #dbe8ff;
    }

    .mv-session-details-row > td {
        padding: 0 !important;
        border-top: 0;
        background: #fbfdff;
    }

    .mv-session-details {
        border-top: 1px solid #e6edf6;
        background:
            radial-gradient(circle at top right, rgba(53, 89, 184, .08), transparent 24%),
            linear-gradient(180deg, #fbfdff 0%, #f5f9ff 100%);
        padding: 18px;
    }

    .mv-detail-summary {
        border: 1px solid #dfe8f5;
        border-radius: 14px;
        background: rgba(255,255,255,.9);
        padding: 12px;
        height: 100%;
    }

    .mv-detail-summary .label {
        color: #667085;
        font-size: var(--fs-xs);
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .mv-detail-summary .value {
        color: #101828;
        font-size: var(--fs-md);
        font-weight: 700;
        margin-top: 4px;
    }

    .mv-detail-note {
        color: #667085;
        font-size: var(--fs-xs);
        margin-top: 4px;
        line-height: 1.35;
    }

    .mv-movement-list {
        border: 1px solid #dfe8f5;
        border-radius: 16px;
        background: rgba(255,255,255,.96);
        overflow: hidden;
    }

    .mv-movement-item {
        display: grid;
        grid-template-columns: minmax(0, 1.8fr) auto auto;
        gap: 12px;
        align-items: center;
        padding: 12px 14px;
        border-top: 1px solid #edf2f7;
    }

    .mv-movement-item:first-child {
        border-top: 0;
    }

    .mv-movement-meta {
        min-width: 0;
    }

    .mv-movement-title {
        font-size: var(--fs-base);
        font-weight: 700;
        color: #0f1728;
        line-height: 1.2;
    }

    .mv-movement-subtitle {
        color: #667085;
        font-size: var(--fs-sm);
        margin-top: 2px;
    }

    /* Ingreso por reversión de un préstamo eliminado: se resalta para que no
       se confunda con un cobro real. */
    .mv-movement-item.is-loan-deletion {
        background: linear-gradient(90deg, #fff8e6 0%, #fffdf7 70%);
        border-left: 3px solid #f0a500;
        padding-left: 11px;
    }

    .mv-movement-item.is-loan-deletion .mv-movement-title {
        color: #92400e;
    }

    .mv-reversal-tag {
        display: inline-flex;
        align-items: center;
        background: #fde9c0;
        color: #92400e;
        font-size: var(--fs-xs);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
        padding: 2px 7px;
        border-radius: 999px;
        margin-right: 4px;
    }

    .mv-deletion-note {
        display: inline-flex;
        align-items: center;
        margin-top: 8px;
        background: #fff8e6;
        border: 1px solid #f5d488;
        color: #92400e;
        font-size: var(--fs-xs);
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 8px;
    }

    .mv-mini-badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .28rem .55rem;
        border-radius: 999px;
        font-size: var(--fs-xs);
        font-weight: 700;
        border: 1px solid transparent;
        white-space: nowrap;
    }

    .mv-mini-badge.income {
        color: #067647;
        background: #ecfdf3;
        border-color: #b7efd0;
    }

    .mv-mini-badge.expense {
        color: #b42318;
        background: #fff1f0;
        border-color: #f5c6c1;
    }

    .mv-session-empty {
        border: 1px dashed #d7e3f3;
        border-radius: 14px;
        background: rgba(255,255,255,.8);
        text-align: center;
        padding: 18px 12px;
        color: #667085;
    }

    .mv-mismatch-card {
        border: 1px solid #f0d5d2;
        border-radius: 14px;
        background: linear-gradient(140deg, #fff7f6 0%, #fff2f0 100%);
        padding: 12px;
        height: 100%;
    }

    .mv-mismatch-card.ok {
        border-color: #bfe7d1;
        background: linear-gradient(140deg, #f3fcf7 0%, #ecfaf2 100%);
    }

    .mv-mismatch-title {
        font-size: var(--fs-xs);
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #667085;
        margin-bottom: 6px;
    }

    .mv-mismatch-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .mv-mismatch-amount {
        font-size: var(--fs-md);
        font-weight: 700;
    }

    .mv-mismatch-help {
        font-size: var(--fs-sm);
        color: #667085;
        margin-top: 5px;
    }

    @media (max-width: 991px) {
        .mv-movement-item {
            grid-template-columns: 1fr;
        }
    }

    .branch-btn {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .3rem .85rem; border-radius: 2rem; font-size: var(--fs-sm);
        font-weight: 500; border: 1.5px solid #dee2e6;
        background: #fff; color: #495057;
        text-decoration: none; transition: all .18s ease; white-space: nowrap;
    }
    .branch-btn:hover { border-color: #0d6efd; color: #0d6efd; background: #f0f5ff; text-decoration: none; }
    .branch-btn.active {
        background: var(--branch-color, #0d6efd);
        border-color: var(--branch-color, #0d6efd); color: #fff;
        box-shadow: 0 3px 10px color-mix(in srgb, var(--branch-color, #0d6efd) 40%, transparent);
    }
    .branch-btn:not(.active) { color: var(--branch-color, #495057); border-color: color-mix(in srgb, var(--branch-color, #dee2e6) 60%, transparent); }
    .branch-btn:not(.active):hover { background: color-mix(in srgb, var(--branch-color, #0d6efd) 8%, white); border-color: var(--branch-color, #0d6efd); }
    .branch-btn .branch-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--branch-color, currentColor); opacity: .7; flex-shrink: 0; }
    .branch-btn.active .branch-dot { opacity: 1; background: rgba(255,255,255,.85); }
    .branch-btn-todos { --branch-color: #495057; }
    .branch-btn-todos.active { background: #212529; border-color: #212529; }
</style>
@endpush

@section('page')
<div class="container-fluid">
    <div class="mv-hero mb-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('reports.index') }}" class="text-decoration-none">Reportes</a></li>
                <li class="breadcrumb-item active">Movimientos</li>
            </ol>
        </nav>
        <x-page-header title="Movimientos" icon="activity" :back="route('reports.index')" class="mb-2">
                {{-- Export dual dropdown --}}
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width:250px;">
                        <li>
                            <span class="dropdown-header d-flex align-items-center gap-1 text-uppercase fw-bold px-3 py-2 fs-xs" style="color:#1f3449;">
                                <i class="bi bi-list-ul"></i> Transacciones
                            </span>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('exports.movements', array_merge($filters, ['format' => 'pdf', 'type' => 'summary'])) }}" target="_blank">
                                <span class="badge bg-danger-subtle text-danger rounded-pill" style="width:28px"><i class="bi bi-file-text"></i></span>
                                PDF Resumen
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('exports.movements', array_merge($filters, ['format' => 'pdf', 'type' => 'detailed'])) }}" target="_blank">
                                <span class="badge bg-danger-subtle text-danger rounded-pill" style="width:28px"><i class="bi bi-file-richtext"></i></span>
                                PDF Detallado
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('exports.movements', array_merge($filters, ['format' => 'excel', 'type' => 'summary'])) }}">
                                <span class="badge bg-success-subtle text-success rounded-pill" style="width:28px"><i class="bi bi-file-text"></i></span>
                                Excel Resumen
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('exports.movements', array_merge($filters, ['format' => 'excel', 'type' => 'detailed'])) }}">
                                <span class="badge bg-success-subtle text-success rounded-pill" style="width:28px"><i class="bi bi-file-richtext"></i></span>
                                Excel Detallado
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <span class="dropdown-header d-flex align-items-center gap-1 text-uppercase fw-bold px-3 py-2 fs-xs" style="color:#1f3449;">
                                <i class="bi bi-safe2"></i> Cierres de Caja
                            </span>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('exports.closures', array_merge($filters, ['format' => 'pdf', 'type' => 'summary'])) }}" target="_blank">
                                <span class="badge bg-danger-subtle text-danger rounded-pill" style="width:28px"><i class="bi bi-file-text"></i></span>
                                PDF Resumen
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('exports.closures', array_merge($filters, ['format' => 'pdf', 'type' => 'detailed'])) }}" target="_blank">
                                <span class="badge bg-danger-subtle text-danger rounded-pill" style="width:28px"><i class="bi bi-file-richtext"></i></span>
                                PDF Detallado
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('exports.closures', array_merge($filters, ['format' => 'excel', 'type' => 'summary'])) }}">
                                <span class="badge bg-success-subtle text-success rounded-pill" style="width:28px"><i class="bi bi-file-text"></i></span>
                                Excel Resumen
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="{{ route('exports.closures', array_merge($filters, ['format' => 'excel', 'type' => 'detailed'])) }}">
                                <span class="badge bg-success-subtle text-success rounded-pill" style="width:28px"><i class="bi bi-file-richtext"></i></span>
                                Excel Detallado
                            </a>
                        </li>
                    </ul>
                </div>
        </x-page-header>
        <p class="text-muted mb-3">Controla ingresos y cierres de caja desde un solo lugar. Monitorea tu utilidad operativa al instante.</p>

        <div class="mv-tab-bar mb-3">
            <a href="{{ route('reports.movements', array_merge(request()->query(), ['tab' => 'transactions'])) }}" class="mv-tab {{ $tab === 'transactions' ? 'active' : '' }}">
                <i class="bi bi-list-ul me-1"></i> Transacciones
            </a>
            <a href="{{ route('reports.movements', array_merge(request()->query(), ['tab' => 'closures'])) }}" class="mv-tab {{ $tab === 'closures' ? 'active' : '' }}">
                <i class="bi bi-safe2 me-1"></i> Cierres de caja
            </a>
        </div>

        @if($branches->count() > 1)
        <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
            <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
            <a class="branch-btn branch-btn-todos {{ $selectedBranchId === 0 ? 'active' : '' }}"
               href="{{ route('reports.movements', array_merge(request()->except(['branch_id']), ['branch_id' => 0])) }}">
                <i class="bi bi-grid-3x3-gap-fill fs-xs" style="opacity:.8;"></i> Todos
            </a>
            @foreach($branches as $branch)
                @php $bColor = $branch->color ?? '#0d6efd'; @endphp
                <a class="branch-btn {{ $selectedBranchId === (int) $branch->id ? 'active' : '' }}"
                   style="--branch-color: {{ $bColor }};"
                   href="{{ route('reports.movements', array_merge(request()->except(['branch_id']), ['branch_id' => $branch->id])) }}">
                    <span class="branch-dot"></span>{{ $branch->name }}
                </a>
            @endforeach
        </div>
        @elseif($branches->count() === 1)
        <div class="mb-3">
            @php $bColor = $branches->first()->color ?? '#0d6efd'; @endphp
            <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
            <span class="branch-btn active" style="--branch-color: {{ $bColor }}; cursor:default;">
                <span class="branch-dot"></span>{{ $branches->first()->name }}
            </span>
        </div>
        @endif

        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="mv-kpi">
                    <div class="label">Ingresos</div>
                    <div class="value text-success">{{ $currencySymbol }} {{ number_format((float) $totals['ingresos'], 2) }}</div>
                    @if((float) ($totals['aportes'] ?? 0) > 0)
                        <small class="text-muted">
                            <i class="bi bi-plus-circle"></i>
                            {{ $currencySymbol }} {{ number_format((float) $totals['aportes'], 2) }} en aportes (no operativo)
                        </small>
                    @endif
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="mv-kpi">
                    <div class="label">Egresos</div>
                    <div class="value text-danger">{{ $currencySymbol }} {{ number_format((float) $totals['egresos'], 2) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="mv-kpi">
                    <div class="label">Utilidad operativa</div>
                    <div class="value {{ $totals['utilidad'] >= 0 ? 'mv-amount-positive' : 'mv-amount-negative' }}">{{ $currencySymbol }} {{ number_format((float) $totals['utilidad'], 2) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="mv-kpi">
                    <div class="label">Actividad</div>
                    <div class="value text-primary">{{ number_format((int) $totals['transacciones']) }} mov.</div>
                    <small class="text-muted">{{ number_format((int) $totals['open_sessions']) }} cajas abiertas</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 mv-filter-box mb-3">
        <div class="card-body py-3">
            <form method="GET" action="{{ route('reports.movements') }}" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="branch_id" value="{{ $selectedBranchId }}">

                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Buscar</label>
                    <input type="text" name="q" class="form-control form-control-sm" value="{{ $filters['q'] ?? '' }}" placeholder="Descripción, referencia, categoría">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Caja</label>
                    <select name="cash_register_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($cashRegisters as $cash)
                            <option value="{{ $cash->id }}" {{ (string)($filters['cash_register_id'] ?? '') === (string)$cash->id ? 'selected' : '' }}>{{ $cash->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Tipo</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="income" {{ ($filters['type'] ?? '') === 'income' ? 'selected' : '' }}>Ingreso</option>
                        <option value="expense" {{ ($filters['type'] ?? '') === 'expense' ? 'selected' : '' }}>Egreso</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-semibold mb-1">Categoría</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="loan_disbursement" {{ ($filters['category'] ?? '') === 'loan_disbursement' ? 'selected' : '' }}>Desembolso</option>
                        <option value="loan_payment" {{ ($filters['category'] ?? '') === 'loan_payment' ? 'selected' : '' }}>Pago préstamo</option>
                        <option value="interest_payment" {{ ($filters['category'] ?? '') === 'interest_payment' ? 'selected' : '' }}>Pago interés</option>
                        <option value="penalty_payment" {{ ($filters['category'] ?? '') === 'penalty_payment' ? 'selected' : '' }}>Pago de multa</option>
                        <option value="amortization" {{ ($filters['category'] ?? '') === 'amortization' ? 'selected' : '' }}>Amortización</option>
                        <option value="liquidation" {{ ($filters['category'] ?? '') === 'liquidation' ? 'selected' : '' }}>Liquidación</option>
                        <option value="purchase" {{ ($filters['category'] ?? '') === 'purchase' ? 'selected' : '' }}>Compra</option>
                        <option value="sale" {{ ($filters['category'] ?? '') === 'sale' ? 'selected' : '' }}>Venta</option>
                        <option value="adjustment" {{ ($filters['category'] ?? '') === 'adjustment' ? 'selected' : '' }}>Ajuste de caja</option>
                        <option value="cash_deposit" {{ ($filters['category'] ?? '') === 'cash_deposit' ? 'selected' : '' }}>Ingreso a caja</option>
                        <option value="loan_deletion" {{ ($filters['category'] ?? '') === 'loan_deletion' ? 'selected' : '' }}>Reversión préstamo eliminado</option>
                        <option value="purchase_deletion" {{ ($filters['category'] ?? '') === 'purchase_deletion' ? 'selected' : '' }}>Reversión compra eliminada</option>
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

                <div class="col-md-1">
                    <label class="form-label small fw-semibold mb-1">Estado</label>
                    <select name="status" class="form-select form-select-sm" {{ $tab !== 'closures' ? 'disabled' : '' }}>
                        <option value="">Todos</option>
                        <option value="open" {{ ($filters['status'] ?? '') === 'open' ? 'selected' : '' }}>Abierta</option>
                        <option value="closed" {{ ($filters['status'] ?? '') === 'closed' ? 'selected' : '' }}>Cerrada</option>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2 pt-2">
                    <x-btn icon="funnel" type="submit">Filtrar</x-btn>
                    <x-icon-btn icon="arrow-clockwise" label="Limpiar filtros" :href="route('reports.movements', ['tab' => $tab])" />
                </div>
            </form>
        </div>
    </div>

    @if($tab === 'transactions')
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 mv-table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Tipo</th>
                                <th>Categoría</th>
                                <th>Descripción</th>
                                <th>Caja</th>
                                <th class="text-end">Monto</th>
                                <th class="pe-3">Registrado por</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactions as $movement)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold">{{ $movement->movement_date?->format('d/m/Y') }}</div>
                                        <small class="text-muted">{{ $movement->created_at?->format('H:i') }}</small>
                                    </td>
                                    <td>
                                        @if($movement->type === 'income')
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-arrow-down-left me-1"></i>Ingreso</span>
                                        @else
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-arrow-up-right me-1"></i>Egreso</span>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-light text-dark border">{{ $movement->category_label }}</span></td>
                                    <td>
                                        <div class="fw-semibold">{{ $movement->description }}</div>
                                        @if($movement->reference_label)
                                            <small class="text-muted">Ref: {{ $movement->reference_label }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $movement->cashRegister?->name ?? ('Caja #' . $movement->cash_register_id) }}</td>
                                    <td class="text-end fw-bold {{ $movement->type === 'income' ? 'text-success' : 'text-danger' }}">
                                        {{ $movement->type === 'expense' ? '-' : '' }}{{ $currencySymbol }} {{ number_format((float) $movement->amount, 2) }}
                                    </td>
                                    <td class="pe-3">{{ $movement->user?->name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">No hay movimientos para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($transactions->hasPages())
            <div class="mt-3 d-flex justify-content-center">{{ $transactions->appends(['tab' => 'transactions'])->links() }}</div>
        @endif
    @else
        @php
            $canAdjustCash = auth()->user()->is_super_admin
                || auth()->user()->hasPermissionInCompany('cash-registers.adjust', auth()->user()->getCurrentCompany());
            $canDepositCash = auth()->user()->is_super_admin
                || auth()->user()->hasPermissionInCompany('cash-registers.deposit', auth()->user()->getCurrentCompany());
        @endphp
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 mv-table">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3" style="width:44px;"></th>
                                <th class="ps-3">Caja</th>
                                <th>Fechas</th>
                                <th>Responsables</th>
                                <th class="text-end">Montos</th>
                                <th class="text-end">Descuadre</th>
                                <th class="text-end">Cobrado</th>
                                <th class="text-end">Movimientos</th>
                                <th class="pe-3">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sessions as $session)
                                @php
                                    // Bruto: incluye los aportes a caja. Es el que usa el cierre esperado.
                                    $sessionIncomeRaw = (float) ($session->income_amount ?? 0);
                                    $sessionDeposit = (float) ($session->deposit_amount ?? 0);
                                    // Operativo: sin los aportes (no son ganancia del negocio).
                                    $sessionIncome = $sessionIncomeRaw - $sessionDeposit;
                                    $sessionExpense = (float) ($session->expense_amount ?? 0);
                                    $sessionBalance = $sessionIncome - $sessionExpense;
                                    $sessionOpening = (float) ($session->opening_amount ?? 0);
                                    $sessionFund = $sessionOpening + $sessionDeposit;
                                    $expectedClosing = $sessionOpening + $sessionIncomeRaw - $sessionExpense;
                                    $hasClosed = $session->closed_at && $session->closing_amount !== null;
                                    $mismatchAmount = $hasClosed ? ((float) $session->closing_amount - $expectedClosing) : null;
                                    $absMismatch = $mismatchAmount !== null ? abs($mismatchAmount) : 0.0;
                                    $isMismatch = $hasClosed && $absMismatch > 0.01;
                                    $loanDeletionCount = (int) ($session->loan_deletion_count ?? 0);
                                    $detailId = 'session-details-' . $session->id;
                                @endphp
                                <tr class="mv-session-row {{ $isMismatch ? 'is-mismatch' : '' }}" data-bs-toggle="collapse" data-bs-target="#{{ $detailId }}" aria-expanded="false" aria-controls="{{ $detailId }}">
                                    <td class="ps-3">
                                        <span class="mv-expand-indicator">
                                            <i class="bi bi-chevron-down"></i>
                                        </span>
                                    </td>
                                    <td class="ps-3">
                                        <div class="fw-semibold mv-cash-name">{{ $session->cashRegister?->name ?? ('Caja #' . $session->cash_register_id) }}</div>
                                    </td>
                                    <td>
                                        <div class="mv-date-line" title="Fecha de apertura">
                                            <span class="mv-amount-tag open">A</span>
                                            <span class="fw-semibold mv-closure-date">
                                                {{ $session->opened_at?->format('d/m/Y') }}
                                                <small class="text-muted fw-normal">{{ $session->opened_at?->format('H:i') }}</small>
                                            </span>
                                        </div>
                                        <div class="mv-date-line" title="Fecha de cierre">
                                            <span class="mv-amount-tag close">C</span>
                                            @if($session->closed_at)
                                                <span class="fw-semibold mv-closure-date">
                                                    {{ $session->closed_at->format('d/m/Y') }}
                                                    <small class="text-muted fw-normal">{{ $session->closed_at->format('H:i') }}</small>
                                                </span>
                                            @else
                                                <span class="text-muted">En curso</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small"><strong>Abrió:</strong> {{ $session->openedByUser?->name ?? '-' }}</div>
                                        <div class="small"><strong>Cerró:</strong> {{ $session->closedByUser?->name ?? '-' }}</div>
                                    </td>
                                    <td class="text-end">
                                        <div class="mv-amount-line" title="Monto de apertura">
                                            <span class="mv-amount-tag open">A</span>
                                            <span>{{ $currencySymbol }} {{ number_format((float) $session->opening_amount, 2) }}</span>
                                        </div>
                                        <div class="mv-amount-line" title="Monto de cierre">
                                            <span class="mv-amount-tag close">C</span>
                                            <span>{{ $session->closing_amount !== null ? ($currencySymbol . ' ' . number_format((float) $session->closing_amount, 2)) : '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        @if($hasClosed)
                                            <span class="fw-semibold {{ $isMismatch ? 'text-danger' : 'text-success' }}">
                                                {{ $mismatchAmount > 0 ? '+' : '' }}{{ $currencySymbol }} {{ number_format($mismatchAmount, 2) }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold text-success">{{ $currencySymbol }} {{ number_format($sessionIncome, 2) }}</td>
                                    <td class="text-end">
                                        <div class="mv-amount-line" title="Movimientos de la sesión">
                                            <span class="mv-amount-tag moves"><i class="bi bi-arrow-left-right"></i></span>
                                            <span class="fw-semibold">{{ number_format((int) $session->cash_movements_count) }}</span>
                                        </div>
                                        @if($loanDeletionCount > 0)
                                            <div class="mv-amount-line" title="Préstamos eliminados reintegrados a esta caja">
                                                <span class="mv-amount-tag deleted"><i class="bi bi-trash3"></i></span>
                                                <span class="fw-semibold text-warning-emphasis">{{ $loanDeletionCount }}</span>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="pe-3">
                                        @if($session->closed_at)
                                            <div class="d-flex flex-column gap-1 align-items-start">
                                                <span class="mv-status-pill closed"><i class="bi bi-lock-fill me-1"></i>Cerrada</span>
                                                <span class="mv-status-pill {{ $isMismatch ? 'mismatch-alert' : 'mismatch-ok' }}">
                                                    <i class="bi {{ $isMismatch ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' }} me-1"></i>
                                                    {{ $isMismatch ? 'Alerta descuadre' : 'Sin descuadre' }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="mv-status-pill open"><i class="bi bi-unlock-fill me-1"></i>Abierta</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr class="mv-session-details-row">
                                    <td colspan="9">
                                        <div id="{{ $detailId }}" class="collapse">
                                            <div class="mv-session-details">
                                                <div class="row g-3 mb-3">
                                                    <div class="col-lg-3 col-md-6">
                                                        <div class="mv-detail-summary">
                                                            <div class="label">Monto de apertura</div>
                                                            <div class="value">{{ $currencySymbol }} {{ number_format($sessionOpening, 2) }}</div>
                                                            @if($sessionDeposit > 0)
                                                                <div class="mv-detail-note">
                                                                    <i class="bi bi-plus-circle"></i>
                                                                    {{ $currencySymbol }} {{ number_format($sessionDeposit, 2) }} en aportes
                                                                    · Fondo total <strong>{{ $currencySymbol }} {{ number_format($sessionFund, 2) }}</strong>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-3 col-md-6">
                                                        <div class="mv-detail-summary">
                                                            <div class="label">Ingresos de la sesión</div>
                                                            <div class="value text-success">{{ $currencySymbol }} {{ number_format($sessionIncome, 2) }}</div>
                                                            @if($sessionDeposit > 0)
                                                                <div class="mv-detail-note">Sin contar aportes (no operativos)</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-3 col-md-6">
                                                        <div class="mv-detail-summary">
                                                            <div class="label">Egresos de la sesión</div>
                                                            <div class="value text-danger">{{ $currencySymbol }} {{ number_format($sessionExpense, 2) }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-3 col-md-6">
                                                        <div class="mv-detail-summary">
                                                            <div class="label">Balance operativo</div>
                                                            <div class="value {{ $sessionBalance >= 0 ? 'mv-amount-positive' : 'mv-amount-negative' }}">{{ $currencySymbol }} {{ number_format($sessionBalance, 2) }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="mv-mismatch-card {{ !$isMismatch ? 'ok' : '' }}">
                                                            <div class="mv-mismatch-title">{{ $hasClosed ? 'Control de cierre' : 'Caja en curso' }}</div>
                                                            <div class="mv-mismatch-main">
                                                                <span class="mv-status-pill {{ $isMismatch ? 'mismatch-alert' : 'mismatch-ok' }}">
                                                                    <i class="bi {{ $isMismatch ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' }} me-1"></i>
                                                                    {{ $hasClosed ? ($isMismatch ? 'Descuadre detectado' : 'Cuadre correcto') : 'En curso' }}
                                                                </span>
                                                                @if($hasClosed)
                                                                    <span class="mv-mismatch-amount {{ $isMismatch ? 'text-danger' : 'text-success' }}">
                                                                        {{ $mismatchAmount > 0 ? '+' : '' }}{{ $currencySymbol }} {{ number_format($mismatchAmount, 2) }}
                                                                    </span>
                                                                @else
                                                                    <span class="mv-mismatch-amount text-success">{{ $currencySymbol }} {{ number_format($expectedClosing, 2) }}</span>
                                                                @endif
                                                            </div>
                                                            @if($hasClosed)
                                                                <div class="mv-mismatch-help">
                                                                    Esperado: <strong>{{ $currencySymbol }} {{ number_format($expectedClosing, 2) }}</strong>
                                                                    · Declarado: <strong>{{ $currencySymbol }} {{ number_format((float) $session->closing_amount, 2) }}</strong>
                                                                </div>
                                                            @else
                                                                <div class="mv-mismatch-help">
                                                                    Saldo actual disponible en la caja. El descuadre se calcula al cerrarla.
                                                                </div>
                                                            @endif

                                                            @if($loanDeletionCount > 0)
                                                                <div class="mv-deletion-note">
                                                                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                                                                    {{ $loanDeletionCount }} {{ $loanDeletionCount === 1 ? 'préstamo eliminado reintegrado' : 'préstamos eliminados reintegrados' }}
                                                                </div>
                                                            @endif

                                                            @if($hasClosed && $canAdjustCash)
                                                                <x-btn variant="outline-primary" icon="sliders" type="button"
                                                                    data-bs-toggle="modal" data-bs-target="#cashAdjustModal"
                                                                    data-caja="{{ $session->cashRegister?->name ?? ('Caja #' . $session->cash_register_id) }}"
                                                                    data-opening="{{ number_format($sessionOpening, 2, '.', '') }}"
                                                                    data-income="{{ number_format($sessionIncomeRaw, 2, '.', '') }}"
                                                                    data-expense="{{ number_format($sessionExpense, 2, '.', '') }}"
                                                                    data-expected="{{ number_format($expectedClosing, 2, '.', '') }}"
                                                                    data-declared="{{ number_format((float) $session->closing_amount, 2, '.', '') }}"
                                                                    data-mismatch="{{ number_format($mismatchAmount, 2, '.', '') }}"
                                                                    data-closed-at="{{ $session->closed_at?->format('d/m/Y H:i') }}"
                                                                    data-amount-url="{{ route('cash-register.session.adjust-amount', $session) }}"
                                                                    data-movement-url="{{ route('cash-register.session.adjust-movement', $session) }}" class="mt-2 mv-adjust-btn">Ajuste de cierre</x-btn>
                                                            @endif

                                                            @if(!$session->closed_at && $canDepositCash)
                                                                <x-btn variant="success" icon="plus-circle" type="button"
                                                                    data-bs-toggle="modal" data-bs-target="#cashDepositModal"
                                                                    data-caja="{{ $session->cashRegister?->name ?? ('Caja #' . $session->cash_register_id) }}"
                                                                    data-opening="{{ number_format($sessionOpening, 2, '.', '') }}"
                                                                    data-income="{{ number_format($sessionIncomeRaw, 2, '.', '') }}"
                                                                    data-expense="{{ number_format($sessionExpense, 2, '.', '') }}"
                                                                    data-balance="{{ number_format($expectedClosing, 2, '.', '') }}"
                                                                    data-opened-at="{{ $session->opened_at?->format('d/m/Y H:i') }}"
                                                                    data-url="{{ route('cash-register.session.deposit', $session) }}" class="mt-2 mv-deposit-btn">Registrar ingreso</x-btn>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>

                                                @if($session->cashMovements->isNotEmpty())
                                                    <div class="mv-movement-list">
                                                        @foreach($session->cashMovements as $movement)
                                                            @php $isLoanDeletion = $movement->category === 'loan_deletion'; @endphp
                                                            <div class="mv-movement-item {{ $isLoanDeletion ? 'is-loan-deletion' : '' }}">
                                                                <div class="mv-movement-meta">
                                                                    <div class="mv-movement-title">
                                                                        @if($isLoanDeletion)
                                                                            <i class="bi bi-arrow-counterclockwise me-1"></i>
                                                                        @endif
                                                                        {{ $movement->description }}
                                                                    </div>
                                                                    <div class="mv-movement-subtitle">
                                                                        @if($isLoanDeletion)
                                                                            <span class="mv-reversal-tag">Préstamo eliminado</span>
                                                                        @endif
                                                                        {{ $movement->category_label }}
                                                                        · {{ $movement->movement_date?->format('d/m/Y H:i') ?? '-' }}
                                                                        · {{ $movement->user?->name ?? 'Sin usuario' }}
                                                                        @if($movement->reference_label)
                                                                            · Ref: {{ $movement->reference_label }}
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                                <div>
                                                                    <span class="mv-mini-badge {{ $movement->type === 'income' ? 'income' : 'expense' }}">
                                                                        <i class="bi {{ $movement->type === 'income' ? 'bi-arrow-down-left' : 'bi-arrow-up-right' }}"></i>
                                                                        {{ $movement->type === 'income' ? 'Ingreso' : 'Egreso' }}
                                                                    </span>
                                                                </div>
                                                                <div class="text-end fw-bold {{ $movement->type === 'income' ? 'text-success' : 'text-danger' }}">
                                                                    {{ $movement->type === 'expense' ? '-' : '+' }}{{ $currencySymbol }} {{ number_format((float) $movement->amount, 2) }}
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="mv-session-empty">
                                                        <i class="bi bi-inbox d-block mb-2 fs-4 opacity-50"></i>
                                                        Esta sesión no tiene movimientos registrados.
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">No hay cierres de caja para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($sessions->hasPages())
            <div class="mt-3 d-flex justify-content-center">{{ $sessions->appends(['tab' => 'closures'])->links() }}</div>
        @endif

        @if($canAdjustCash)
        {{-- ─── Modal de ajuste de cierre (compartido, poblado por JS) ─── --}}
        <div class="modal fade" id="cashAdjustModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg" style="border-radius:18px; overflow:hidden;">
                    <div class="modal-header border-0 text-white" style="background:linear-gradient(135deg,#0d6efd,#0a58ca); padding:18px 22px;">
                        <div>
                            <h5 class="modal-title fw-bold mb-0"><i class="bi bi-sliders me-2"></i>Ajuste de cierre</h5>
                            <small class="opacity-75"><span id="ca-caja">—</span> · Cerrada el <span id="ca-closed-at">—</span></small>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" style="padding:22px;">
                        {{-- Resumen de la caja --}}
                        <div class="p-3 mb-3" style="background:#f6f9ff; border:1px solid #e2ebf8; border-radius:14px;">
                            <div class="row g-3 text-center">
                                <div class="col">
                                    <div class="text-muted small">Apertura</div>
                                    <div class="fw-bold">$<span id="ca-opening">0.00</span></div>
                                </div>
                                <div class="col">
                                    <div class="text-muted small">+ Ingresos</div>
                                    <div class="fw-bold text-success">$<span id="ca-income">0.00</span></div>
                                </div>
                                <div class="col">
                                    <div class="text-muted small">− Egresos</div>
                                    <div class="fw-bold text-danger">$<span id="ca-expense">0.00</span></div>
                                </div>
                                <div class="col" style="border-left:1px dashed #c9d7ee;">
                                    <div class="text-muted small">= Esperado</div>
                                    <div class="fw-bold">$<span id="ca-expected">0.00</span></div>
                                </div>
                                <div class="col">
                                    <div class="text-muted small">Declarado</div>
                                    <div class="fw-bold">$<span id="ca-declared">0.00</span></div>
                                </div>
                            </div>
                            <div class="text-center mt-3">
                                <span id="ca-mismatch-badge" class="badge rounded-pill px-3 py-2 fs-base">
                                    Descuadre: <span id="ca-mismatch">0.00</span>
                                </span>
                            </div>
                        </div>

                        {{-- Tabs --}}
                        <ul class="nav nav-pills nav-fill mb-3 gap-2" id="cashAdjustTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="ca-tab-count-btn" data-bs-toggle="pill" data-bs-target="#ca-tab-count" type="button" role="tab">
                                    <i class="bi bi-pencil-square me-1"></i> Corregir conteo
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="ca-tab-move-btn" data-bs-toggle="pill" data-bs-target="#ca-tab-move" type="button" role="tab">
                                    <i class="bi bi-arrow-left-right me-1"></i> Registrar ajuste
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            {{-- Tab 1: corregir conteo --}}
                            <div class="tab-pane fade show active" id="ca-tab-count" role="tabpanel">
                                <p class="text-muted small mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Úsalo si te <strong>equivocaste al contar o registrar</strong> el monto de cierre. Corrige el monto declarado y el descuadre se recalcula.
                                </p>
                                <form id="ca-form-count" method="POST" action="">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Monto correcto de cierre ({{ $currencySymbol }})</label>
                                        <input type="number" step="0.01" min="0" name="closing_amount" id="ca-count-amount" class="form-control no-upper" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Motivo <span class="text-muted fw-normal">(opcional)</span></label>
                                        <textarea name="reason" class="form-control" rows="2" maxlength="500" placeholder="Ej. error al contar los billetes de 100"></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <x-btn icon="check-lg" type="submit">Guardar corrección</x-btn>
                                    </div>
                                </form>
                            </div>

                            {{-- Tab 2: registrar movimiento de ajuste --}}
                            <div class="tab-pane fade" id="ca-tab-move" role="tabpanel">
                                <p class="text-muted small mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Úsalo si la <strong>diferencia es real</strong> (faltó o sobró dinero). Se registra un movimiento de caja que deja el cierre cuadrado.
                                </p>
                                <div id="ca-move-summary" class="alert mb-3 d-flex align-items-center gap-2">
                                    <i class="bi bi-arrow-left-right fs-5"></i>
                                    <span id="ca-move-text">—</span>
                                </div>
                                <form id="ca-form-move" method="POST" action="">
                                    @csrf
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Motivo <span class="text-muted fw-normal">(opcional)</span></label>
                                        <textarea name="reason" class="form-control" rows="2" maxlength="500" placeholder="Ej. faltante de caja no justificado"></textarea>
                                    </div>
                                    <div class="d-flex justify-content-end">
                                        <x-btn icon="check-lg" type="submit" id="ca-move-submit">Registrar ajuste</x-btn>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if($canDepositCash)
        {{-- ─── Modal de ingreso a caja abierta (compartido, poblado por JS) ─── --}}
        <div class="modal fade" id="cashDepositModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius:18px; overflow:hidden;">
                    <div class="modal-header border-0 text-white" style="background:linear-gradient(135deg,#16a34a,#15803d); padding:18px 22px;">
                        <div>
                            <h5 class="modal-title fw-bold mb-0"><i class="bi bi-plus-circle me-2"></i>Registrar ingreso a caja</h5>
                            <small class="opacity-75"><span id="cd-caja">—</span> · Abierta el <span id="cd-opened-at">—</span></small>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" style="padding:22px;">
                        {{-- Resumen de la caja --}}
                        <div class="p-3 mb-3" style="background:#f3fcf7; border:1px solid #cdeedd; border-radius:14px;">
                            <div class="row g-2 text-center">
                                <div class="col">
                                    <div class="text-muted small">Apertura</div>
                                    <div class="fw-bold">$<span id="cd-opening">0.00</span></div>
                                </div>
                                <div class="col">
                                    <div class="text-muted small">+ Ingresos</div>
                                    <div class="fw-bold text-success">$<span id="cd-income">0.00</span></div>
                                </div>
                                <div class="col">
                                    <div class="text-muted small">− Egresos</div>
                                    <div class="fw-bold text-danger">$<span id="cd-expense">0.00</span></div>
                                </div>
                                <div class="col" style="border-left:1px dashed #b7e2ca;">
                                    <div class="text-muted small">Saldo actual</div>
                                    <div class="fw-bold" style="color:#15803d;">$<span id="cd-balance">0.00</span></div>
                                </div>
                            </div>
                        </div>

                        <form id="cd-form" method="POST" action="">
                            @csrf
                            <div class="mb-2">
                                <label class="form-label small fw-semibold">Monto a ingresar ({{ $currencySymbol }})</label>
                                <input type="number" step="0.01" min="0.01" name="amount" id="cd-amount"
                                       class="form-control form-control-lg no-upper" placeholder="0.00" required autocomplete="off">
                            </div>

                            {{-- Montos rápidos --}}
                            <div class="d-flex gap-2 flex-wrap mb-3">
                                @foreach([500, 1000, 2000, 5000] as $quick)
                                    <x-btn variant="secondary" type="button" data-amount="{{ $quick }}" class="cd-quick">+ {{ number_format($quick, 0, ',', '.') }}</x-btn>
                                @endforeach
                            </div>

                            {{-- Preview del nuevo saldo --}}
                            <div id="cd-preview" class="alert alert-light border d-flex align-items-center gap-2 py-2 mb-3 fs-base">
                                <i class="bi bi-arrow-right-circle text-muted"></i>
                                <span>Ingresa un monto para ver cómo queda la caja.</span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Motivo <span class="text-danger">*</span></label>
                                <textarea name="reason" class="form-control" rows="2" maxlength="500" required
                                          placeholder="Ej. recarga de efectivo para desembolsos"></textarea>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <x-btn variant="secondary" type="button" data-bs-dismiss="modal">Cancelar</x-btn>
                                <x-btn variant="success" icon="check-lg" type="submit" id="cd-submit" disabled>Registrar ingreso</x-btn>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.mv-session-row').forEach(function (row) {
            const targetSelector = row.getAttribute('data-bs-target');
            if (!targetSelector) return;

            const collapseEl = document.querySelector(targetSelector);
            if (!collapseEl) return;

            collapseEl.addEventListener('show.bs.collapse', function () {
                row.classList.add('is-open');
            });

            collapseEl.addEventListener('hide.bs.collapse', function () {
                row.classList.remove('is-open');
            });
        });

        // ─── Modal de ajuste de cierre ───────────────────────────
        const adjustModal = document.getElementById('cashAdjustModal');
        if (adjustModal) {
            const fmt = (n) => Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            adjustModal.addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                if (!btn) return;
                const d = btn.dataset;

                const opening  = parseFloat(d.opening  || 0);
                const income   = parseFloat(d.income   || 0);
                const expense  = parseFloat(d.expense  || 0);
                const expected = parseFloat(d.expected || 0);
                const declared = parseFloat(d.declared || 0);
                const mismatch = parseFloat(d.mismatch || 0);

                // Resumen
                document.getElementById('ca-caja').textContent      = d.caja || '—';
                document.getElementById('ca-closed-at').textContent = d.closedAt || '—';
                document.getElementById('ca-opening').textContent   = fmt(opening);
                document.getElementById('ca-income').textContent    = fmt(income);
                document.getElementById('ca-expense').textContent   = fmt(expense);
                document.getElementById('ca-expected').textContent  = fmt(expected);
                document.getElementById('ca-declared').textContent  = fmt(declared);
                document.getElementById('ca-mismatch').textContent  = (mismatch > 0 ? '+' : '') + fmt(mismatch);

                // Badge de descuadre
                const badge = document.getElementById('ca-mismatch-badge');
                badge.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'text-dark');
                if (Math.abs(mismatch) <= 0.01) {
                    badge.classList.add('bg-success');
                } else if (mismatch > 0) {
                    badge.classList.add('bg-warning', 'text-dark'); // sobrante
                } else {
                    badge.classList.add('bg-danger'); // faltante
                }

                // Forms: acción + prellenado
                document.getElementById('ca-form-count').action = d.amountUrl || '';
                document.getElementById('ca-form-move').action  = d.movementUrl || '';
                document.getElementById('ca-count-amount').value = declared.toFixed(2);

                // Tab 2: texto y estado del botón
                const moveText   = document.getElementById('ca-move-text');
                const moveAlert  = document.getElementById('ca-move-summary');
                const moveSubmit = document.getElementById('ca-move-submit');
                moveAlert.classList.remove('alert-success', 'alert-warning', 'alert-danger', 'alert-secondary');

                if (Math.abs(mismatch) <= 0.01) {
                    moveText.innerHTML = 'La caja está cuadrada. No hay descuadre que ajustar.';
                    moveAlert.classList.add('alert-secondary');
                    moveSubmit.disabled = true;
                } else if (mismatch > 0) {
                    moveText.innerHTML = 'Se registrará un <strong>INGRESO</strong> de ajuste por <strong>{{ $currencySymbol }} ' + fmt(Math.abs(mismatch)) + '</strong> (sobrante).';
                    moveAlert.classList.add('alert-success');
                    moveSubmit.disabled = false;
                } else {
                    moveText.innerHTML = 'Se registrará un <strong>EGRESO</strong> de ajuste por <strong>{{ $currencySymbol }} ' + fmt(Math.abs(mismatch)) + '</strong> (faltante).';
                    moveAlert.classList.add('alert-danger');
                    moveSubmit.disabled = false;
                }
            });
        }

        // ─── Modal de ingreso a caja abierta ─────────────────────
        const depositModal = document.getElementById('cashDepositModal');
        if (depositModal) {
            const fmt = (n) => Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            const amountInput = document.getElementById('cd-amount');
            const preview     = document.getElementById('cd-preview');
            const submitBtn   = document.getElementById('cd-submit');
            let balance = 0;

            function updatePreview() {
                const amount = parseFloat(amountInput.value || 0);
                preview.classList.remove('alert-light', 'alert-success');

                if (!amount || amount <= 0) {
                    preview.classList.add('alert-light');
                    preview.innerHTML = '<i class="bi bi-arrow-right-circle text-muted"></i> <span>Ingresa un monto para ver cómo queda la caja.</span>';
                    submitBtn.disabled = true;
                    return;
                }

                preview.classList.add('alert-success');
                preview.innerHTML = '<i class="bi bi-arrow-right-circle"></i> <span>Saldo actual <strong>{{ $currencySymbol }} ' + fmt(balance)
                    + '</strong> → <strong>{{ $currencySymbol }} ' + fmt(balance + amount) + '</strong></span>';
                submitBtn.disabled = false;
            }

            depositModal.addEventListener('show.bs.modal', function (event) {
                const btn = event.relatedTarget;
                if (!btn) return;
                const d = btn.dataset;

                balance = parseFloat(d.balance || 0);

                document.getElementById('cd-caja').textContent      = d.caja || '—';
                document.getElementById('cd-opened-at').textContent = d.openedAt || '—';
                document.getElementById('cd-opening').textContent   = fmt(parseFloat(d.opening || 0));
                document.getElementById('cd-income').textContent    = fmt(parseFloat(d.income || 0));
                document.getElementById('cd-expense').textContent   = fmt(parseFloat(d.expense || 0));
                document.getElementById('cd-balance').textContent   = fmt(balance);

                document.getElementById('cd-form').action = d.url || '';
                document.getElementById('cd-form').reset();
                updatePreview();
            });

            depositModal.addEventListener('shown.bs.modal', () => amountInput.focus());

            amountInput.addEventListener('input', updatePreview);

            depositModal.querySelectorAll('.cd-quick').forEach(function (chip) {
                chip.addEventListener('click', function () {
                    const current = parseFloat(amountInput.value || 0);
                    amountInput.value = (current + parseFloat(this.dataset.amount)).toFixed(2);
                    updatePreview();
                });
            });
        }
    });
</script>
@endpush
