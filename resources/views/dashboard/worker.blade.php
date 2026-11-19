@extends('layouts.app')

@section('title', 'Mis Cobros - Dashboard')

@section('page')
<div class="container-fluid">

    {{-- ── Greeting ──────────────────────────────────────────── --}}
    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Buenos días' : ($hour < 18 ? 'Buenas tardes' : 'Buenas noches');
    @endphp
    <x-page-header :title="$greeting . ', ' . auth()->user()->name . ' 👋'" icon="speedometer2">
        <x-btn icon="plus-lg" :href="route('loans.index')">Nuevo préstamo</x-btn>
    </x-page-header>
    <p class="text-muted mb-4 small">
        <i class="bi bi-calendar3 me-1"></i>
        {{ now()->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}
        &nbsp;·&nbsp;
        <span class="text-mod fw-semibold">{{ $company?->name }}</span>
    </p>

    {{-- ── KPI Row ────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="wk-kpi wk-kpi--blue">
                <div class="wk-kpi__icon"><i class="bi bi-clock-fill"></i></div>
                <div class="wk-kpi__body">
                    <div class="wk-kpi__val">{{ $todayCount }}</div>
                    <div class="wk-kpi__lbl">Cobros hoy</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="wk-kpi wk-kpi--indigo">
                <div class="wk-kpi__icon"><i class="bi bi-calendar-week-fill"></i></div>
                <div class="wk-kpi__body">
                    <div class="wk-kpi__val">{{ $weekCount }}</div>
                    <div class="wk-kpi__lbl">Esta semana</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="wk-kpi wk-kpi--green">
                <div class="wk-kpi__icon"><i class="bi bi-lightning-charge-fill"></i></div>
                <div class="wk-kpi__body">
                    <div class="wk-kpi__val">{{ $activeCount }}</div>
                    <div class="wk-kpi__lbl">Préstamos activos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="wk-kpi wk-kpi--red">
                <div class="wk-kpi__icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                <div class="wk-kpi__body">
                    <div class="wk-kpi__val">{{ $overdueCount }}</div>
                    <div class="wk-kpi__lbl">En mora</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ── Left column ────────────────────────────────────── --}}
        <div class="col-lg-8">

            {{-- Cobros de hoy --}}
            <div class="wk-section mb-4">
                <div class="wk-section__header">
                    <div class="wk-section__title">
                        <span class="wk-dot wk-dot--blue"></span>
                        Cobros de hoy
                    </div>
                    @if($todayCount > 0)
                        <span class="badge bg-primary rounded-pill">{{ $todayCount }}</span>
                    @endif
                </div>

                @if($todayDue->isEmpty())
                    <div class="wk-empty">
                        <i class="bi bi-check2-circle text-success"></i>
                        <p>Sin cobros pendientes para hoy</p>
                    </div>
                @else
                    <div class="wk-card-list">
                        @foreach($todayDue as $ip)
                            <div class="wk-collect-item wk-collect-item--today">
                                <div class="wk-collect-avatar">
                                    {{ strtoupper(substr($ip->loan->client?->name ?? '?', 0, 2)) }}
                                </div>
                                <div class="wk-collect-info">
                                    <div class="wk-collect-name">{{ $ip->loan->client?->name ?? 'Sin cliente' }}</div>
                                    <div class="wk-collect-meta">
                                        Préstamo #{{ $ip->loan_id }}
                                        &nbsp;·&nbsp;
                                        Periodo {{ $ip->period_number }}
                                        @if($ip->status === 'partial')
                                            &nbsp;·&nbsp;<span class="badge bg-warning text-dark fs-xs">Parcial</span>
                                        @endif
                                    </div>
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

            {{-- Cobros próximos 7 días --}}
            <div class="wk-section mb-4">
                <div class="wk-section__header">
                    <div class="wk-section__title">
                        <span class="wk-dot wk-dot--indigo"></span>
                        Próximos cobros (7 días)
                    </div>
                    @if($upcomingDue->isNotEmpty())
                        <span class="badge bg-secondary rounded-pill">{{ $upcomingDue->count() }}</span>
                    @endif
                </div>

                @if($upcomingDue->isEmpty())
                    <div class="wk-empty">
                        <i class="bi bi-calendar-check text-muted"></i>
                        <p>Sin cobros en los próximos 7 días</p>
                    </div>
                @else
                    <div class="wk-card-list">
                        @foreach($upcomingDue as $ip)
                            @php
                                $daysLeft = now()->startOfDay()->diffInDays($ip->period_end, false);
                            @endphp
                            <div class="wk-collect-item">
                                <div class="wk-collect-avatar wk-collect-avatar--indigo">
                                    {{ strtoupper(substr($ip->loan->client?->name ?? '?', 0, 2)) }}
                                </div>
                                <div class="wk-collect-info">
                                    <div class="wk-collect-name">{{ $ip->loan->client?->name ?? 'Sin cliente' }}</div>
                                    <div class="wk-collect-meta">
                                        Préstamo #{{ $ip->loan_id }}
                                        &nbsp;·&nbsp;
                                        Vence {{ $ip->period_end->format('d/m/Y') }}
                                    </div>
                                </div>
                                <div class="wk-collect-amount">
                                    <div class="wk-collect-main">{{ $currencySymbol }} {{ number_format($ip->getRemainingAmount(), 2) }}</div>
                                    <div class="wk-collect-sub">
                                        @if($daysLeft === 1)
                                            <span class="text-warning fw-semibold">mañana</span>
                                        @else
                                            <span class="text-muted">en {{ $daysLeft }} días</span>
                                        @endif
                                    </div>
                                </div>
                                <x-icon-btn icon="eye" label="Ver" variant="secondary" :href="route('loans.show', $ip->loan_id)" class="wk-collect-btn" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Cobros del mes --}}
            @if($monthDue->isNotEmpty())
            <div class="wk-section mb-4">
                <div class="wk-section__header">
                    <div class="wk-section__title">
                        <span class="wk-dot wk-dot--teal"></span>
                        Este mes (8 – 30 días)
                    </div>
                    <span class="badge bg-secondary rounded-pill">{{ $monthDue->count() }}</span>
                </div>
                <div class="wk-card-list">
                    @foreach($monthDue as $ip)
                        <div class="wk-collect-item">
                            <div class="wk-collect-avatar wk-collect-avatar--teal">
                                {{ strtoupper(substr($ip->loan->client?->name ?? '?', 0, 2)) }}
                            </div>
                            <div class="wk-collect-info">
                                <div class="wk-collect-name">{{ $ip->loan->client?->name ?? 'Sin cliente' }}</div>
                                <div class="wk-collect-meta">
                                    Préstamo #{{ $ip->loan_id }}
                                    &nbsp;·&nbsp;
                                    Vence {{ $ip->period_end->format('d/m/Y') }}
                                </div>
                            </div>
                            <div class="wk-collect-amount">
                                <div class="wk-collect-main">{{ $currencySymbol }} {{ number_format($ip->getRemainingAmount(), 2) }}</div>
                                <div class="wk-collect-sub text-muted">
                                    en {{ now()->startOfDay()->diffInDays($ip->period_end, false) }} días
                                </div>
                            </div>
                            <x-icon-btn icon="eye" label="Ver" variant="secondary" :href="route('loans.show', $ip->loan_id)" class="wk-collect-btn" />
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

        {{-- ── Right column ────────────────────────────────────── --}}
        <div class="col-lg-4">

            {{-- Cobros vencidos --}}
            <div class="wk-section mb-4">
                <div class="wk-section__header">
                    <div class="wk-section__title">
                        <span class="wk-dot wk-dot--red"></span>
                        Cobros vencidos
                    </div>
                    @if($overdueInterests->isNotEmpty())
                        <span class="badge bg-danger rounded-pill">{{ $overdueInterests->count() }}</span>
                    @endif
                </div>

                @if($overdueInterests->isEmpty())
                    <div class="wk-empty">
                        <i class="bi bi-check-circle text-success"></i>
                        <p>Sin cobros vencidos</p>
                    </div>
                @else
                    <div class="wk-card-list">
                        @foreach($overdueInterests as $ip)
                            @php
                                $daysOverdue = $ip->period_end->diffInDays(now());
                            @endphp
                            <div class="wk-collect-item wk-collect-item--overdue">
                                <div class="wk-collect-avatar wk-collect-avatar--red">
                                    {{ strtoupper(substr($ip->loan->client?->name ?? '?', 0, 2)) }}
                                </div>
                                <div class="wk-collect-info">
                                    <div class="wk-collect-name">{{ $ip->loan->client?->name ?? 'Sin cliente' }}</div>
                                    <div class="wk-collect-meta text-danger">
                                        <i class="bi bi-exclamation-circle me-1"></i>
                                        Venció hace {{ $daysOverdue }} día{{ $daysOverdue !== 1 ? 's' : '' }}
                                    </div>
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
            @if($overdueLoans->isNotEmpty())
            <div class="wk-section">
                <div class="wk-section__header">
                    <div class="wk-section__title">
                        <span class="wk-dot wk-dot--orange"></span>
                        Préstamos en mora
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill">{{ $overdueLoans->count() }}</span>
                </div>
                <div class="wk-card-list">
                    @foreach($overdueLoans as $loan)
                        <div class="wk-collect-item">
                            <div class="wk-collect-avatar wk-collect-avatar--orange">
                                {{ strtoupper(substr($loan->client?->name ?? '?', 0, 2)) }}
                            </div>
                            <div class="wk-collect-info">
                                <div class="wk-collect-name">{{ $loan->client?->name ?? 'Sin cliente' }}</div>
                                <div class="wk-collect-meta">
                                    PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}
                                    &nbsp;·&nbsp;
                                    @if($loan->status === \App\Models\Loan::STATUS_DEFAULTED)
                                        <span class="badge bg-danger fs-xs">Incumplimiento</span>
                                    @else
                                        <span class="badge bg-warning text-dark fs-xs">Vencido</span>
                                    @endif
                                </div>
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

</div>
@endsection

@push('styles')
<style>
    /* ─── KPI Cards ─────────────────────────────────────────── */
    .wk-kpi {
        display: flex;
        align-items: center;
        gap: 14px;
        background: #fff;
        border-radius: 14px;
        padding: 16px 18px;
        box-shadow: 0 2px 10px rgba(0,0,0,.06);
        border-left: 4px solid transparent;
        transition: box-shadow .15s;
    }
    .wk-kpi:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
    .wk-kpi__icon {
        width: 42px; height: 42px; border-radius: 12px;
        display: grid; place-items: center;
        font-size: var(--fs-md); flex-shrink: 0;
    }
    .wk-kpi__val  { font-size: var(--fs-xl); font-weight: 700; line-height: 1; }
    .wk-kpi__lbl  { font-size: var(--fs-xs); color: #888; margin-top: 3px; }

    .wk-kpi--blue   { border-color: #0d6efd; }
    .wk-kpi--blue   .wk-kpi__icon { background: #e8f0fe; color: #0d6efd; }
    .wk-kpi--blue   .wk-kpi__val  { color: #0d6efd; }

    .wk-kpi--indigo { border-color: #6610f2; }
    .wk-kpi--indigo .wk-kpi__icon { background: #f0e6ff; color: #6610f2; }
    .wk-kpi--indigo .wk-kpi__val  { color: #6610f2; }

    .wk-kpi--green  { border-color: #198754; }
    .wk-kpi--green  .wk-kpi__icon { background: #d1f2e1; color: #198754; }
    .wk-kpi--green  .wk-kpi__val  { color: #198754; }

    .wk-kpi--red    { border-color: #dc3545; }
    .wk-kpi--red    .wk-kpi__icon { background: #fde8ea; color: #dc3545; }
    .wk-kpi--red    .wk-kpi__val  { color: #dc3545; }

    /* ─── Section card ──────────────────────────────────────── */
    .wk-section {
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 2px 10px rgba(0,0,0,.06);
        overflow: hidden;
    }
    .wk-section__header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 18px 10px;
        border-bottom: 1px solid #f1f3f5;
    }
    .wk-section__title {
        display: flex; align-items: center; gap: 8px;
        font-weight: 600; font-size: var(--fs-base);
    }

    /* ─── Dot indicators ────────────────────────────────────── */
    .wk-dot {
        width: 9px; height: 9px; border-radius: 50%; display: inline-block; flex-shrink: 0;
    }
    .wk-dot--blue   { background: #0d6efd; box-shadow: 0 0 0 3px rgba(13,110,253,.18); }
    .wk-dot--indigo { background: #6610f2; box-shadow: 0 0 0 3px rgba(102,16,242,.18); }
    .wk-dot--teal   { background: #0d9488; box-shadow: 0 0 0 3px rgba(13,148,136,.18); }
    .wk-dot--red    { background: #dc3545; box-shadow: 0 0 0 3px rgba(220,53,69,.18); }
    .wk-dot--orange { background: #fd7e14; box-shadow: 0 0 0 3px rgba(253,126,20,.18); }

    /* ─── Collection item ───────────────────────────────────── */
    .wk-card-list { padding: 6px 0 4px; }

    .wk-collect-item {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 18px;
        transition: background .12s;
    }
    .wk-collect-item:hover { background: #f8f9fa; }
    .wk-collect-item + .wk-collect-item { border-top: 1px solid #f1f3f5; }

    .wk-collect-item--today  { background: rgba(13,110,253,.03); }
    .wk-collect-item--overdue { background: rgba(220,53,69,.03); }

    .wk-collect-avatar {
        width: 36px; height: 36px; border-radius: 50%;
        background: linear-gradient(135deg, #0d6efd, #6610f2);
        color: #fff; font-size: var(--fs-xs); font-weight: 700;
        display: grid; place-items: center; flex-shrink: 0;
        text-transform: uppercase;
    }
    .wk-collect-avatar--indigo { background: linear-gradient(135deg, #6610f2, #0d6efd); }
    .wk-collect-avatar--teal   { background: linear-gradient(135deg, #0d9488, #06b6d4); }
    .wk-collect-avatar--red    { background: linear-gradient(135deg, #dc3545, #fd7e14); }
    .wk-collect-avatar--orange { background: linear-gradient(135deg, #fd7e14, #ffc107); }

    .wk-collect-info { flex: 1; min-width: 0; }
    .wk-collect-name {
        font-weight: 600; font-size: var(--fs-base);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .wk-collect-meta { font-size: var(--fs-xs); color: #888; margin-top: 2px; }

    .wk-collect-amount { text-align: right; flex-shrink: 0; }
    .wk-collect-main { font-weight: 700; font-size: var(--fs-base); }
    .wk-collect-sub  { font-size: var(--fs-xs); }

    /* ─── Empty state ───────────────────────────────────────── */
    .wk-empty {
        padding: 28px 18px;
        text-align: center;
        color: #aaa;
    }
    .wk-empty i { font-size: var(--fs-xl); display: block; margin-bottom: 8px; }
    .wk-empty p { margin: 0; font-size: var(--fs-sm); }
</style>
@endpush
