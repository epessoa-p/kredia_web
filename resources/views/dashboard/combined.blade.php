@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
/* ─── Tab switcher ──────────────────────────────────────── */
.tab-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; padding: 0 5px;
    border-radius: 999px; font-size: var(--fs-xs); font-weight: 700;
    background: var(--k-danger); color: #fff;
}

/* ─── Branch selector ──────────────────────────────────── */
.branch-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .35rem .9rem; border-radius: 2rem; font-size: var(--fs-sm);
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
.branch-btn-todos.active { background: #212529; border-color: #212529; --branch-color: #212529; }

/* ─── KPI Cards (manager) ───────────────────────────────── */
.kpi-card {
    background: #fff; border-radius: 10px;
    padding: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.06); border: 0;
}
.kpi-body { display: flex; justify-content: space-between; align-items: flex-start; }
.kpi-value { font-size: var(--fs-lg); font-weight: 700; line-height: 1; margin-bottom: 4px; }
.kpi-label { font-size: var(--fs-xs); color: #777; margin-bottom: 6px; }
.kpi-trend { font-size: var(--fs-xs); }
.kpi-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: grid; place-items: center;
    color: #fff; font-size: var(--fs-md); flex-shrink: 0;
}

/* ─── Fin/Stock cards (manager) ─────────────────────────── */
.asset-card {
    border-radius: 14px; padding: 16px 18px;
    display: flex; align-items: center; gap: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.asset-icon {
    width: 42px; height: 42px; border-radius: 12px;
    display: grid; place-items: center;
    font-size: var(--fs-md); flex-shrink: 0;
}

/* ─── Cajas / saldos (manager) ──────────────────────────── */
.cash-panel {
    background: #fff; border-radius: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
}
.cash-panel__head {
    display: flex; align-items: center; justify-content: space-between;
    gap: 14px; flex-wrap: wrap;
    padding: 16px 20px;
    background: linear-gradient(135deg, #0f2a4a, #14507f);
    color: #fff;
}
.cash-panel__total { font-size: var(--fs-xl); font-weight: 700; line-height: 1.1; }
.cash-panel__body { padding: 16px 20px 20px; }

.cash-tile {
    position: relative;
    height: 100%;
    border: 1px solid #e6ecf4;
    border-radius: 12px;
    padding: 10px 12px 10px 14px;
    background: #fff;
    font-size: var(--fs-xs);
    transition: box-shadow .18s ease, transform .18s ease;
}
.cash-tile::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0;
    width: 3px; border-radius: 12px 0 0 12px;
    background: var(--cash-accent, #adb5bd);
}
.cash-tile:hover { box-shadow: 0 6px 18px rgba(16,24,40,.10); transform: translateY(-2px); }
.cash-tile--open   { --cash-accent: #16a34a; background: linear-gradient(135deg, #ffffff, #f4fcf7); }
.cash-tile--closed { --cash-accent: #adb5bd; background: #fafbfc; }
.cash-tile__name  { font-weight: 700; font-size: var(--fs-base); color: #1f2937; }
.cash-tile__meta  { font-size: var(--fs-xs); color: #8a94a6; }
.cash-tile__ago   { color: #a0a8b6; }
.cash-tile__value { font-size: var(--fs-md); font-weight: 700; line-height: 1.15; }
.cash-tile--open   .cash-tile__value { color: #15803d; }
.cash-tile--closed .cash-tile__value { color: #9aa3af; }
.cash-tile__summary {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px;
    margin-top: 8px; padding: 6px 8px;
    background: rgba(15, 42, 74, .04); border-radius: 8px;
    text-align: center;
}
.cash-tile__summary > div { min-width: 0; }
.cash-tile__summary span { display: block; font-size: var(--fs-xs); text-transform: uppercase; letter-spacing: .02em; color: #8a94a6; }
.cash-tile__summary strong { display: block; font-size: var(--fs-xs); font-weight: 700; overflow-wrap: anywhere; line-height: 1.2; }
.cash-tile__detail {
    list-style: none; margin: 8px 0 0; padding: 0;
}
.cash-tile__detail li {
    display: flex; justify-content: space-between; align-items: center; gap: 8px;
    padding: 3px 0; border-bottom: 1px dotted #e6ecf4;
    font-size: var(--fs-xs); color: #6b7280;
}
.cash-tile__detail li:last-child { border-bottom: 0; }
.cash-tile__detail li i { width: 14px; display: inline-block; opacity: .7; }
.cash-tile__detail strong { font-weight: 600; color: #374151; white-space: nowrap; }
.cash-tile__foot {
    display: flex; justify-content: space-between; gap: 8px;
    margin-top: 8px; padding-top: 6px;
    border-top: 1px dashed #e6ecf4;
    font-size: var(--fs-xs); color: #8a94a6;
}
.cash-pill {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: var(--fs-xs); font-weight: 600; letter-spacing: .03em;
    padding: 2px 7px; border-radius: 999px; text-transform: uppercase;
}
.cash-pill--open   { background: #dcfce7; color: #15803d; }
.cash-pill--closed { background: #eef1f5; color: #6b7280; }
.cash-branch-dot {
    width: 7px; height: 7px; border-radius: 50%;
    display: inline-block; flex-shrink: 0;
}
.cash-empty {
    text-align: center; padding: 26px 16px;
    color: #98a2b3; font-size: var(--fs-base);
}

/* ─── KPI Cards (worker) ────────────────────────────────── */
.wk-kpi {
    display: flex; align-items: center; gap: 14px;
    background: #fff; border-radius: 14px;
    padding: 16px 18px; box-shadow: 0 2px 10px rgba(0,0,0,.06);
    border-left: 4px solid transparent; transition: box-shadow .15s;
}
.wk-kpi:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.wk-kpi__icon { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: var(--fs-md); flex-shrink: 0; }
.wk-kpi__val  { font-size: var(--fs-xl); font-weight: 700; line-height: 1; }
.wk-kpi__lbl  { font-size: var(--fs-xs); color: #888; margin-top: 3px; }
.wk-kpi--blue   { border-color: #0d6efd; } .wk-kpi--blue   .wk-kpi__icon { background: #e8f0fe; color: #0d6efd; } .wk-kpi--blue   .wk-kpi__val { color: #0d6efd; }
.wk-kpi--indigo { border-color: #6610f2; } .wk-kpi--indigo .wk-kpi__icon { background: #f0e6ff; color: #6610f2; } .wk-kpi--indigo .wk-kpi__val { color: #6610f2; }
.wk-kpi--green  { border-color: #198754; } .wk-kpi--green  .wk-kpi__icon { background: #d1f2e1; color: #198754; } .wk-kpi--green  .wk-kpi__val { color: #198754; }
.wk-kpi--red    { border-color: #dc3545; } .wk-kpi--red    .wk-kpi__icon { background: #fde8ea; color: #dc3545; } .wk-kpi--red    .wk-kpi__val { color: #dc3545; }

/* ─── Worker section cards ──────────────────────────────── */
.wk-section { background: #fff; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,.06); overflow: hidden; }
.wk-section__header { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px 10px; border-bottom: 1px solid #f1f3f5; }
.wk-section__title  { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: var(--fs-base); }
.wk-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
.wk-dot--blue   { background: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.18); }
.wk-dot--indigo { background: #6610f2; box-shadow: 0 0 0 3px rgba(102,16,242,.18); }
.wk-dot--teal   { background: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,.18); }
.wk-dot--red    { background: #dc3545; box-shadow: 0 0 0 3px rgba(220,53,69,.18); }
.wk-dot--orange { background: #fd7e14; box-shadow: 0 0 0 3px rgba(253,126,20,.18); }
.wk-card-list { padding: 6px 0 4px; }
.wk-collect-item { display: flex; align-items: center; gap: 12px; padding: 10px 18px; transition: background .12s; }
.wk-collect-item:hover { background: #f8f9fa; }
.wk-collect-item + .wk-collect-item { border-top: 1px solid #f1f3f5; }
.wk-collect-item--today  { background: rgba(13,110,253,.03); }
.wk-collect-item--overdue { background: rgba(220,53,69,.03); }
.wk-collect-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg,#0d6efd,#6610f2); color: #fff; font-size: var(--fs-xs); font-weight: 700; display: grid; place-items: center; flex-shrink: 0; text-transform: uppercase; }
.wk-collect-avatar--indigo { background: linear-gradient(135deg,#6610f2,#0d6efd); }
.wk-collect-avatar--teal   { background: linear-gradient(135deg,#0d9488,#06b6d4); }
.wk-collect-avatar--red    { background: linear-gradient(135deg,#dc3545,#fd7e14); }
.wk-collect-avatar--orange { background: linear-gradient(135deg,#fd7e14,#ffc107); }
.wk-collect-info { flex: 1; min-width: 0; }
.wk-collect-name { font-weight: 600; font-size: var(--fs-base); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wk-collect-meta { font-size: var(--fs-xs); color: #888; margin-top: 2px; }
.wk-collect-amount { text-align: right; flex-shrink: 0; }
.wk-collect-main { font-weight: 700; font-size: var(--fs-base); }
.wk-collect-sub  { font-size: var(--fs-xs); }
.wk-empty { padding: 28px 18px; text-align: center; color: #aaa; }
.wk-empty i { font-size: var(--fs-xl); display: block; margin-bottom: 8px; }
.wk-empty p { margin: 0; font-size: var(--fs-sm); }
</style>
@endpush

@section('page')
<div class="container-fluid">

    {{-- ── Header ─────────────────────────────────────────── --}}
    <x-page-header title="Dashboard" icon="speedometer2">
        {{-- Tab switcher --}}
        @if($showManagerTab && $showWorkerTab)
        <div class="k-seg" id="dashTabSwitcher">
            <button type="button" class="btn btn-secondary dash-tab-btn active" data-target="tab-manager">
                <i class="bi bi-graph-up-arrow"></i> Gerencia
            </button>
            <button type="button" class="btn btn-secondary dash-tab-btn" data-target="tab-cobros">
                <i class="bi bi-clock-fill"></i> Cobros
                @if(isset($todayCount) && $todayCount > 0)
                    <span class="tab-badge">{{ $todayCount }}</span>
                @endif
            </button>
        </div>
        @endif
    </x-page-header>
    <p class="text-muted mb-4 small">
        <i class="bi bi-calendar3 me-1"></i>
        {{ now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
        @if($company)
            &nbsp;·&nbsp;<span class="fw-semibold text-mod">{{ $company->name }}</span>
        @endif
    </p>

    {{-- ── Tab panes ───────────────────────────────────────── --}}
    <div id="dashTabContent">

        {{-- ════════════════════════════════════════════════════
             TAB 1: MANAGER / GERENCIA
        ════════════════════════════════════════════════════ --}}
        @if($showManagerTab)
        <div class="dash-pane" id="tab-manager">

            {{-- Branch selector - Gerencial --}}
            @if($branches->count() > 1)
            <div class="mb-4 d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
                <a class="branch-btn branch-btn-todos {{ $selectedManagerBranchId === 0 ? 'active' : '' }}"
                   href="{{ route('dashboard', array_merge(request()->except(['sucursal_id']), ['sucursal_id' => 0, 'c_sid' => $selectedWorkerBranchId])) }}">
                    <i class="bi bi-grid-3x3-gap-fill fs-xs" style="opacity:.8;"></i> Todos
                </a>
                @foreach($branches as $branch)
                    @php $bColor = $branch->color ?? '#0d6efd'; @endphp
                    <a class="branch-btn {{ $selectedManagerBranchId === (int) $branch->id ? 'active' : '' }}"
                       style="--branch-color: {{ $bColor }};"
                       href="{{ route('dashboard', array_merge(request()->except(['sucursal_id']), ['sucursal_id' => $branch->id, 'c_sid' => $selectedWorkerBranchId])) }}">
                        <span class="branch-dot"></span>{{ $branch->name }}
                    </a>
                @endforeach
            </div>
            @elseif($branches->count() === 1)
            <div class="mb-4">
                @php $bColor = $branches->first()->color ?? '#0d6efd'; @endphp
                <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
                <span class="branch-btn active" style="--branch-color: {{ $bColor }}; cursor:default;">
                    <span class="branch-dot"></span>{{ $branches->first()->name }}
                </span>
            </div>
            @endif

            {{-- KPI row --}}
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-body">
                            <div>
                                <div class="kpi-value">{{ $totalLoans }}</div>
                                <div class="kpi-label">Total Préstamos</div>
                                <div class="kpi-trend text-muted"><i class="bi bi-archive"></i> Histórico</div>
                            </div>
                            <div class="kpi-icon" style="background:#7c4dff;"><i class="bi bi-folder2-open"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-body">
                            <div>
                                <div class="kpi-value">{{ $activeLoans }}</div>
                                <div class="kpi-label">Activos</div>
                                <div class="kpi-trend text-success"><i class="bi bi-lightning-charge"></i> En curso</div>
                            </div>
                            <div class="kpi-icon" style="background:#ff6d00;"><i class="bi bi-lightning-charge"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-body">
                            <div>
                                <div class="kpi-value">{{ $completedLoans }}</div>
                                <div class="kpi-label">Completados</div>
                                <div class="kpi-trend text-muted"><i class="bi bi-check2-all"></i> Finalizados</div>
                            </div>
                            <div class="kpi-icon" style="background:#00c853;"><i class="bi bi-check-circle"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="kpi-card">
                        <div class="kpi-body">
                            <div>
                                <div class="kpi-value">{{ $overdueLoans }}</div>
                                <div class="kpi-label">Vencidos</div>
                                <div class="kpi-trend text-danger"><i class="bi bi-exclamation-triangle"></i> Atención</div>
                            </div>
                            <div class="kpi-icon" style="background:#ff1744;"><i class="bi bi-clock-history"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Assets row: Inventario + Capital + Margen --}}
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="asset-card" style="background:linear-gradient(135deg,#f0f7ff,#e8f0fe);">
                        <div class="asset-icon" style="background:#1565c0;color:#fff;"><i class="bi bi-boxes"></i></div>
                        <div class="flex-fill">
                            <div class="fs-xs" style="color:#666;text-transform:uppercase;letter-spacing:.04em;">Valor en Stock</div>
                            <div class="fs-lg" style="font-weight:700;color:#1565c0;">{{ $currencySymbol }} {{ number_format($inventoryValueCost, 0, ',', '.') }}</div>
                            <div class="fs-xs" style="color:#888;">Venta: {{ $currencySymbol }} {{ number_format($inventoryValuePrice, 0, ',', '.') }}</div>
                        </div>
                        <x-icon-btn icon="chevron-right" label="Ver detalle" variant="secondary" :href="route('inventory.index')" />
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="asset-card" style="background:linear-gradient(135deg,{{ $netCapital >= 0 ? '#f1f8f2,#e6f4ea' : '#fff5f5,#ffebee' }});">
                        <div class="asset-icon" style="background:{{ $netCapital >= 0 ? '#2e7d32' : '#c62828' }};color:#fff;"><i class="bi bi-bank2"></i></div>
                        <div class="flex-fill">
                            <div class="fs-xs" style="color:#666;text-transform:uppercase;letter-spacing:.04em;">Capital Neto</div>
                            <div class="fs-lg" style="font-weight:700;color:{{ $netCapital >= 0 ? '#2e7d32' : '#c62828' }};">{{ $currencySymbol }} {{ number_format(abs($netCapital), 0, ',', '.') }}</div>
                            <div class="fs-xs" style="color:#888;">{{ $netCapital >= 0 ? 'Disponible' : 'Déficit' }}</div>
                        </div>
                        <x-icon-btn icon="chevron-right" label="Ver detalle" variant="secondary" :href="route('capital.index')" />
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="asset-card" style="background:linear-gradient(135deg,#fff8e1,#fff3cd);">
                        <div class="asset-icon" style="background:#f9a825;color:#fff;"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="flex-fill">
                            <div class="fs-xs" style="color:#666;text-transform:uppercase;letter-spacing:.04em;">Margen Potencial</div>
                            <div class="fs-lg" style="font-weight:700;color:#f57f17;">{{ $currencySymbol }} {{ number_format($inventoryValuePrice - $inventoryValueCost, 0, ',', '.') }}</div>
                            <div class="fs-xs" style="color:#888;">Si se vende todo el stock</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Cajas y saldos --}}
            <div class="cash-panel mb-4">
                <div class="cash-panel__head">
                    <div class="d-flex align-items-center gap-3">
                        <div class="asset-icon" style="background:rgba(255,255,255,.16);color:#fff;">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                        <div>
                            <div class="fs-xs" style="text-transform:uppercase;letter-spacing:.06em;opacity:.75;">Efectivo en cajas</div>
                            <div class="cash-panel__total">{{ $currencySymbol }} {{ number_format($cashTotalBalance, 2, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill" style="background:rgba(255,255,255,.16);font-weight:600;">
                            <i class="bi bi-unlock-fill me-1"></i>{{ $cashOpenCount }} abierta{{ $cashOpenCount === 1 ? '' : 's' }}
                        </span>
                        <span class="badge rounded-pill" style="background:rgba(255,255,255,.16);font-weight:600;">
                            <i class="bi bi-safe2 me-1"></i>{{ $cashRegisters->count() }} caja{{ $cashRegisters->count() === 1 ? '' : 's' }}
                        </span>
                        <x-btn variant="secondary" icon-right="chevron-right" :href="route('reports.movements', ['tab' => 'closures'])">
                            Ver movimientos
                        </x-btn>
                    </div>
                </div>
                <div class="cash-panel__body">
                    @if($cashRegisters->isEmpty())
                        <div class="cash-empty">
                            <i class="bi bi-safe2 d-block mb-2 fs-xl" style="opacity:.5;"></i>
                            No hay cajas activas{{ $selectedManagerBranchId ? ' en esta sucursal' : '' }}.
                        </div>
                    @else
                        <div class="row g-3">
                            @foreach($cashRegisters as $register)
                                @php $bColor = $register->branch?->color ?? '#0d6efd'; @endphp
                                <div class="col-xl-3 col-lg-4 col-md-6">
                                    <div class="cash-tile {{ $register->is_open ? 'cash-tile--open' : 'cash-tile--closed' }}">
                                        <div class="mb-2" style="min-width:0;">
                                            <div class="cash-tile__name text-truncate" title="{{ $register->name }}">{{ $register->name }}</div>
                                            <div class="d-flex align-items-center justify-content-between gap-2 mt-1">
                                                <div class="cash-tile__meta d-flex align-items-center gap-1" style="min-width:0;">
                                                    <span class="cash-branch-dot" style="background:{{ $bColor }};"></span>
                                                    <span class="text-truncate">{{ $register->branch?->name ?? 'Sin sucursal' }}</span>
                                                </div>
                                                <span class="cash-pill flex-shrink-0 {{ $register->is_open ? 'cash-pill--open' : 'cash-pill--closed' }}">
                                                    <i class="bi {{ $register->is_open ? 'bi-unlock-fill' : 'bi-lock-fill' }}"></i>
                                                    {{ $register->is_open ? 'Abierta' : 'Cerrada' }}
                                                </span>
                                            </div>
                                        </div>

                                        @if($register->is_open)
                                            @php $openedAt = $register->activeSession->opened_at; @endphp
                                            <div class="cash-tile__value">{{ $currencySymbol }} {{ number_format($register->balance, 2, ',', '.') }}</div>
                                            <div class="cash-tile__meta">
                                                Saldo actual · Abrió {{ $openedAt?->format('d/m H:i') }}
                                                @if($openedAt)
                                                    <span class="cash-tile__ago">({{ $openedAt->diffForHumans(null, true, true) }})</span>
                                                @endif
                                            </div>

                                            {{-- Resumen: apertura / ingresos / egresos --}}
                                            <div class="cash-tile__summary">
                                                <div><span>Apertura</span><strong>{{ number_format((float) $register->activeSession->opening_amount, 2, ',', '.') }}</strong></div>
                                                <div class="text-success"><span>Ingresos</span><strong>+{{ number_format($register->session_income, 2, ',', '.') }}</strong></div>
                                                <div class="text-danger"><span>Egresos</span><strong>−{{ number_format($register->session_expense, 2, ',', '.') }}</strong></div>
                                            </div>

                                            {{-- Desglose por concepto --}}
                                            <ul class="cash-tile__detail">
                                                <li>
                                                    <span><i class="bi bi-cash-coin"></i> Desembolsos{{ $register->session_disbursed_count ? ' (' . $register->session_disbursed_count . ')' : '' }}</span>
                                                    <strong class="text-danger">{{ number_format($register->session_disbursed, 2, ',', '.') }}</strong>
                                                </li>
                                                <li>
                                                    <span><i class="bi bi-wallet2"></i> Cobros préstamos</span>
                                                    <strong class="text-success">{{ number_format($register->session_loan_collected, 2, ',', '.') }}</strong>
                                                </li>
                                                <li>
                                                    <span><i class="bi bi-bag-check"></i> Ventas</span>
                                                    <strong class="text-success">{{ number_format($register->session_sales, 2, ',', '.') }}</strong>
                                                </li>
                                                <li>
                                                    <span><i class="bi bi-receipt"></i> Gastos</span>
                                                    <strong class="text-danger">{{ number_format($register->session_manual_expense, 2, ',', '.') }}</strong>
                                                </li>
                                                @php $lastClosed = $register->lastClosedSession; @endphp
                                                <li title="{{ $lastClosed ? 'Cerró ' . ($lastClosed->closedByUser?->name ?? '—') . ' el ' . $lastClosed->closed_at?->format('d/m/Y H:i') : '' }}">
                                                    <span>
                                                        <i class="bi bi-lock"></i> Último cierre
                                                        @if($lastClosed?->closed_at)
                                                            <span class="cash-tile__ago">({{ $lastClosed->closed_at->format('d/m') }})</span>
                                                        @endif
                                                    </span>
                                                    <strong>{{ $lastClosed ? number_format((float) $lastClosed->closing_amount, 2, ',', '.') : '—' }}</strong>
                                                </li>
                                            </ul>

                                            <div class="cash-tile__foot">
                                                <span class="text-truncate"><i class="bi bi-person-circle"></i> {{ $register->activeSession->openedByUser?->name ?? $register->assignedPersonal?->full_name ?? 'Sin responsable' }}</span>
                                                <span class="flex-shrink-0"><i class="bi bi-list-ul"></i> {{ $register->session_movements }} mov.</span>
                                            </div>
                                        @else
                                            @php $lastClosed = $register->lastClosedSession; @endphp
                                            <div class="cash-tile__value">{{ $currencySymbol }} {{ number_format($register->balance, 2, ',', '.') }}</div>
                                            <div class="cash-tile__meta">Sin caja aperturada</div>

                                            <ul class="cash-tile__detail">
                                                @if($lastClosed)
                                                    <li>
                                                        <span><i class="bi bi-lock"></i> Último cierre</span>
                                                        <strong>{{ $lastClosed->closed_at?->format('d/m H:i') }}</strong>
                                                    </li>
                                                    <li>
                                                        <span><i class="bi bi-cash"></i> Monto cerrado</span>
                                                        <strong>{{ number_format((float) $lastClosed->closing_amount, 2, ',', '.') }}</strong>
                                                    </li>
                                                    <li>
                                                        <span><i class="bi bi-person-check"></i> Cerró</span>
                                                        <strong class="text-truncate">{{ $lastClosed->closedByUser?->name ?? '—' }}</strong>
                                                    </li>
                                                @else
                                                    <li><span><i class="bi bi-info-circle"></i> Sin cierres registrados</span></li>
                                                @endif
                                            </ul>

                                            <div class="cash-tile__foot">
                                                <span class="text-truncate"><i class="bi bi-person-circle"></i> {{ $register->assignedPersonal?->full_name ?? 'Sin asignar' }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Financial summary + Distribution --}}
            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Resumen Financiero</h6>
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="border-end">
                                        <div class="fs-3 fw-bold text-primary">{{ $currencySymbol }} {{ number_format($totalAmount, 0, ',', '.') }}</div>
                                        <small class="text-muted">Monto Total</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <div class="fs-3 fw-bold text-success">{{ $currencySymbol }} {{ number_format($totalPaid ?? 0, 0, ',', '.') }}</div>
                                        <small class="text-muted">Total Cobrado</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="fs-3 fw-bold text-warning">{{ $currencySymbol }} {{ number_format($totalPending ?? 0, 0, ',', '.') }}</div>
                                    <small class="text-muted">Por Cobrar</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="fw-bold mb-3">Distribución</h6>
                            @php
                                $tot = max($totalLoans, 1);
                                $aPct = round(($activeLoans / $tot) * 100);
                                $cPct = round(($completedLoans / $tot) * 100);
                                $oPct = round(($overdueLoans / $tot) * 100);
                            @endphp
                            <div class="d-flex flex-column gap-3">
                                @foreach([['Activos', $aPct, '#ff6d00'], ['Completados', $cPct, '#00c853'], ['Vencidos', $oPct, '#ff1744']] as [$lbl, $pct, $clr])
                                <div>
                                    <div class="d-flex justify-content-between small mb-1"><span>{{ $lbl }}</span><span>{{ $pct }}%</span></div>
                                    <div class="progress" style="height:8px;"><div class="progress-bar" style="width:{{ $pct }}%;background:{{ $clr }};"></div></div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recent loans --}}
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0">Préstamos Recientes</h6>
                        <a href="{{ route('loans.index') }}" class="text-decoration-none small">VER TODOS <i class="bi bi-chevron-right"></i></a>
                    </div>
                    @if($recentLoans->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-xl"></i>
                            <p class="mt-2 mb-0 small">No hay préstamos registrados.</p>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>ID</th><th>Cliente</th><th class="text-end">Monto</th><th>Tasa</th><th>Plazo</th><th>Estado</th><th class="text-end">Acc.</th></tr>
                            </thead>
                            <tbody>
                                @foreach($recentLoans as $loan)
                                @php
                                    $sMap = [\App\Models\Loan::STATUS_ACTIVE=>'bg-success',\App\Models\Loan::STATUS_FINISHED=>'bg-secondary',\App\Models\Loan::STATUS_OVERDUE=>'bg-danger',\App\Models\Loan::STATUS_DEFAULTED=>'bg-dark',\App\Models\Loan::STATUS_CANCELLED=>'bg-secondary'];
                                    $sLbl = [\App\Models\Loan::STATUS_ACTIVE=>'Activo',\App\Models\Loan::STATUS_FINISHED=>'Finalizado',\App\Models\Loan::STATUS_OVERDUE=>'En mora',\App\Models\Loan::STATUS_DEFAULTED=>'Incumplido',\App\Models\Loan::STATUS_CANCELLED=>'Cancelado'];
                                @endphp
                                <tr>
                                    <td><strong>PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}</strong></td>
                                    <td>{{ $loan->client?->name ?? '—' }}</td>
                                    <td class="text-end">{{ $currencySymbol }} {{ number_format($loan->amount, 0, ',', '.') }}</td>
                                    <td>{{ $loan->interest_rate }}%</td>
                                    <td>{{ $loan->term_months }}m</td>
                                    <td><span class="badge {{ $sMap[$loan->status] ?? 'bg-secondary' }}">{{ $sLbl[$loan->status] ?? $loan->status }}</span></td>
                                    <td class="text-end"><x-icon-btn icon="eye" label="Ver" variant="secondary" :href="route('loans.show', $loan)" /></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

        </div>{{-- /tab-manager --}}
        @endif

        {{-- ════════════════════════════════════════════════════
             TAB 2: WORKER / COBROS
        ════════════════════════════════════════════════════ --}}
        @if($showWorkerTab)
        <div class="dash-pane {{ $showManagerTab ? 'd-none' : '' }}" id="tab-cobros">

            {{-- Branch selector / badge - Cobros --}}
            @if($isPersonalManager && $branches->count() > 1)
            <div class="mb-4 d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
                <a class="branch-btn branch-btn-todos {{ $selectedWorkerBranchId === 0 ? 'active' : '' }}"
                   href="{{ route('dashboard', array_merge(request()->except(['c_sid']), ['c_sid' => 0, 'sucursal_id' => $selectedManagerBranchId])) }}">
                    <i class="bi bi-grid-3x3-gap-fill fs-xs" style="opacity:.8;"></i> Todos
                </a>
                @foreach($branches as $branch)
                    @php $bColor = $branch->color ?? '#0d6efd'; @endphp
                    <a class="branch-btn {{ $selectedWorkerBranchId === (int) $branch->id ? 'active' : '' }}"
                       style="--branch-color: {{ $bColor }};"
                       href="{{ route('dashboard', array_merge(request()->except(['c_sid']), ['c_sid' => $branch->id, 'sucursal_id' => $selectedManagerBranchId])) }}">
                        <span class="branch-dot"></span>{{ $branch->name }}
                    </a>
                @endforeach
            </div>
            @elseif($isPersonalManager && $branches->count() === 1)
            <div class="mb-4">
                @php $bColor = $branches->first()->color ?? '#0d6efd'; @endphp
                <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
                <span class="branch-btn active" style="--branch-color: {{ $bColor }}; cursor:default;">
                    <span class="branch-dot"></span>{{ $branches->first()->name }}
                </span>
            </div>
            @elseif(!$isPersonalManager && $selectedWorkerBranchId > 0)
            @php
                $workerBranch = $branches->firstWhere('id', $selectedWorkerBranchId);
                $wbColor = $workerBranch?->color ?? '#0d6efd';
            @endphp
            <div class="mb-4">
                <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
                <span class="branch-btn active" style="--branch-color: {{ $wbColor }}; cursor:default;">
                    <span class="branch-dot"></span>{{ $workerBranch?->name ?? 'Asignada' }}
                </span>
            </div>
            @endif

            {{-- Greeting --}}
            <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                <div>
                    @php $hour = now()->hour; $greeting = $hour < 12 ? 'Buenos días' : ($hour < 18 ? 'Buenas tardes' : 'Buenas noches'); @endphp
                    <h5 class="fw-bold mb-1">{{ $greeting }}, {{ auth()->user()->name }} 👋</h5>
                    <p class="text-muted small mb-0">Aquí están tus cobros del día y pendientes</p>
                </div>
                <x-btn icon="plus-lg" :href="route('loans.index')">Nuevo préstamo</x-btn>
            </div>

            {{-- Worker KPIs --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="wk-kpi wk-kpi--blue">
                        <div class="wk-kpi__icon"><i class="bi bi-clock-fill"></i></div>
                        <div class="wk-kpi__body"><div class="wk-kpi__val">{{ $todayCount }}</div><div class="wk-kpi__lbl">Cobros hoy</div></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="wk-kpi wk-kpi--indigo">
                        <div class="wk-kpi__icon"><i class="bi bi-calendar-week-fill"></i></div>
                        <div class="wk-kpi__body"><div class="wk-kpi__val">{{ $weekCount }}</div><div class="wk-kpi__lbl">Esta semana</div></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="wk-kpi wk-kpi--green">
                        <div class="wk-kpi__icon"><i class="bi bi-lightning-charge-fill"></i></div>
                        <div class="wk-kpi__body"><div class="wk-kpi__val">{{ $activeCount }}</div><div class="wk-kpi__lbl">Activos</div></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="wk-kpi wk-kpi--red">
                        <div class="wk-kpi__icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="wk-kpi__body"><div class="wk-kpi__val">{{ $overdueCount }}</div><div class="wk-kpi__lbl">En mora</div></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    {{-- Cobros de hoy --}}
                    <div class="wk-section mb-4">
                        <div class="wk-section__header">
                            <div class="wk-section__title"><span class="wk-dot wk-dot--blue"></span> Cobros de hoy</div>
                            @if($todayCount > 0)<span class="badge bg-primary rounded-pill">{{ $todayCount }}</span>@endif
                        </div>
                        @if($todayDue->isEmpty())
                            <div class="wk-empty"><i class="bi bi-check2-circle text-success"></i><p>Sin cobros pendientes para hoy</p></div>
                        @else
                        <div class="wk-card-list">
                            @foreach($todayDue as $ip)
                            <div class="wk-collect-item wk-collect-item--today">
                                <div class="wk-collect-avatar">{{ strtoupper(substr($ip->loan->client?->name ?? '?', 0, 2)) }}</div>
                                <div class="wk-collect-info">
                                    <div class="wk-collect-name">{{ $ip->loan->client?->name ?? 'Sin cliente' }}</div>
                                    <div class="wk-collect-meta">Préstamo PR-{{ str_pad((string) ($ip->loan?->numero ?? $ip->loan_id), 5, '0', STR_PAD_LEFT) }} · Periodo {{ $ip->period_number }}@if($ip->status==='partial') · <span class="badge bg-warning text-dark fs-xs">Parcial</span>@endif</div>
                                </div>
                                <div class="wk-collect-amount">
                                    <div class="wk-collect-main">{{ $currencySymbol }} {{ number_format($ip->getRemainingAmount(), 2) }}</div>
                                    <div class="wk-collect-sub text-muted">de {{ $currencySymbol }} {{ number_format($ip->interest_amount, 2) }}</div>
                                </div>
                                <x-icon-btn icon="eye" label="Ver" variant="primary" :href="route('loans.show', $ip->loan_id)" class="wk-collect-btn" />
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Próximos 7 días --}}
                    <div class="wk-section mb-4">
                        <div class="wk-section__header">
                            <div class="wk-section__title"><span class="wk-dot wk-dot--indigo"></span> Próximos cobros (7 días)</div>
                            @if($upcomingDue->isNotEmpty())<span class="badge bg-secondary rounded-pill">{{ $upcomingDue->count() }}</span>@endif
                        </div>
                        @if($upcomingDue->isEmpty())
                            <div class="wk-empty"><i class="bi bi-calendar-check text-muted"></i><p>Sin cobros en los próximos 7 días</p></div>
                        @else
                        <div class="wk-card-list">
                            @foreach($upcomingDue as $ip)
                            @php $daysLeft = now()->startOfDay()->diffInDays($ip->period_end, false); @endphp
                            <div class="wk-collect-item">
                                <div class="wk-collect-avatar wk-collect-avatar--indigo">{{ strtoupper(substr($ip->loan->client?->name ?? '?', 0, 2)) }}</div>
                                <div class="wk-collect-info">
                                    <div class="wk-collect-name">{{ $ip->loan->client?->name ?? 'Sin cliente' }}</div>
                                    <div class="wk-collect-meta">Préstamo PR-{{ str_pad((string) ($ip->loan?->numero ?? $ip->loan_id), 5, '0', STR_PAD_LEFT) }} · Vence {{ $ip->period_end->format('d/m/Y') }}</div>
                                </div>
                                <div class="wk-collect-amount">
                                    <div class="wk-collect-main">{{ $currencySymbol }} {{ number_format($ip->getRemainingAmount(), 2) }}</div>
                                    <div class="wk-collect-sub">@if($daysLeft===1)<span class="text-warning fw-semibold">mañana</span>@else<span class="text-muted">en {{ $daysLeft }} días</span>@endif</div>
                                </div>
                                <x-icon-btn icon="eye" label="Ver" variant="secondary" :href="route('loans.show', $ip->loan_id)" class="wk-collect-btn" />
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Este mes --}}
                    @if($monthDue->isNotEmpty())
                    <div class="wk-section mb-4">
                        <div class="wk-section__header">
                            <div class="wk-section__title"><span class="wk-dot wk-dot--teal"></span> Este mes (8 – 30 días)</div>
                            <span class="badge bg-secondary rounded-pill">{{ $monthDue->count() }}</span>
                        </div>
                        <div class="wk-card-list">
                            @foreach($monthDue as $ip)
                            <div class="wk-collect-item">
                                <div class="wk-collect-avatar wk-collect-avatar--teal">{{ strtoupper(substr($ip->loan->client?->name ?? '?', 0, 2)) }}</div>
                                <div class="wk-collect-info">
                                    <div class="wk-collect-name">{{ $ip->loan->client?->name ?? 'Sin cliente' }}</div>
                                    <div class="wk-collect-meta">Préstamo PR-{{ str_pad((string) ($ip->loan?->numero ?? $ip->loan_id), 5, '0', STR_PAD_LEFT) }} · Vence {{ $ip->period_end->format('d/m/Y') }}</div>
                                </div>
                                <div class="wk-collect-amount">
                                    <div class="wk-collect-main">{{ $currencySymbol }} {{ number_format($ip->getRemainingAmount(), 2) }}</div>
                                    <div class="wk-collect-sub text-muted">en {{ now()->startOfDay()->diffInDays($ip->period_end, false) }} días</div>
                                </div>
                                <x-icon-btn icon="eye" label="Ver" variant="secondary" :href="route('loans.show', $ip->loan_id)" class="wk-collect-btn" />
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

                <div class="col-lg-4">
                    {{-- Cobros vencidos --}}
                    <div class="wk-section mb-4">
                        <div class="wk-section__header">
                            <div class="wk-section__title"><span class="wk-dot wk-dot--red"></span> Cobros vencidos</div>
                            @if($overdueInterests->isNotEmpty())<span class="badge bg-danger rounded-pill">{{ $overdueInterests->count() }}</span>@endif
                        </div>
                        @if($overdueInterests->isEmpty())
                            <div class="wk-empty"><i class="bi bi-check-circle text-success"></i><p>Sin cobros vencidos</p></div>
                        @else
                        <div class="wk-card-list">
                            @foreach($overdueInterests as $ip)
                            @php $daysOv = (int) $ip->period_end->startOfDay()->diffInDays(now()->startOfDay()); @endphp
                            <div class="wk-collect-item wk-collect-item--overdue">
                                <div class="wk-collect-avatar wk-collect-avatar--red">{{ strtoupper(substr($ip->loan->client?->name ?? '?', 0, 2)) }}</div>
                                <div class="wk-collect-info">
                                    <div class="wk-collect-name">{{ $ip->loan->client?->name ?? 'Sin cliente' }}</div>
                                    <div class="wk-collect-meta text-danger"><i class="bi bi-exclamation-circle me-1"></i>Venció hace {{ $daysOv }} día{{ $daysOv!==1?'s':'' }}</div>
                                </div>
                                <div class="wk-collect-amount">
                                    <div class="wk-collect-main text-danger">{{ $currencySymbol }} {{ number_format($ip->getRemainingAmount(), 2) }}</div>
                                </div>
                                <x-icon-btn icon="eye" label="Ver" variant="danger" :href="route('loans.show', $ip->loan_id)" class="wk-collect-btn" />
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>

                    {{-- Préstamos en mora --}}
                    @if(isset($overdueLoansCollection) && $overdueLoansCollection->isNotEmpty())
                    <div class="wk-section">
                        <div class="wk-section__header">
                            <div class="wk-section__title"><span class="wk-dot wk-dot--orange"></span> Préstamos en mora</div>
                            <span class="badge bg-warning text-dark rounded-pill">{{ $overdueLoansCollection->count() }}</span>
                        </div>
                        <div class="wk-card-list">
                            @foreach($overdueLoansCollection as $loan)
                            <div class="wk-collect-item">
                                <div class="wk-collect-avatar wk-collect-avatar--orange">{{ strtoupper(substr($loan->client?->name ?? '?', 0, 2)) }}</div>
                                <div class="wk-collect-info">
                                    <div class="wk-collect-name">{{ $loan->client?->name ?? 'Sin cliente' }}</div>
                                    <div class="wk-collect-meta">PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }} · @if($loan->status===\App\Models\Loan::STATUS_DEFAULTED)<span class="badge bg-danger fs-xs">Incumplimiento</span>@else<span class="badge bg-warning text-dark fs-xs">Vencido</span>@endif</div>
                                </div>
                                <div class="wk-collect-amount">
                                    <div class="wk-collect-main">{{ $currencySymbol }} {{ number_format($loan->current_capital, 2) }}</div>
                                    <div class="wk-collect-sub text-muted">capital vigente</div>
                                </div>
                                <x-icon-btn icon="eye" label="Ver" variant="secondary" :href="route('loans.show', $loan)" class="wk-collect-btn" />
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>

        </div>{{-- /tab-cobros --}}
        @endif

    </div>{{-- /dashTabContent --}}
</div>
@endsection

@push('scripts')
<script>
(function () {
    const btns  = document.querySelectorAll('.dash-tab-btn');
    const panes = document.querySelectorAll('.dash-pane');
    if (!btns.length) return;

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = btn.dataset.target;

            btns.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');

            panes.forEach(function (p) {
                p.classList.toggle('d-none', p.id !== target);
            });

            try { sessionStorage.setItem('dashActiveTab', target); } catch(e){}
        });
    });

    // Restore last active tab
    try {
        const saved = sessionStorage.getItem('dashActiveTab');
        if (saved) {
            const btn = document.querySelector('[data-target="' + saved + '"]');
            if (btn) btn.click();
        }
    } catch(e){}
})();
</script>
@endpush
