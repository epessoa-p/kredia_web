@extends('layouts.app')

@section('title', 'Dashboard - Sistema de Préstamos')

@section('page')
<div class="container-fluid">
    <x-page-header title="Dashboard" icon="speedometer2" subtitle="Resumen general y estadísticas" />

    <!-- KPI Cards - Material Admin Style -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalLoans }}</div>
                        <div class="kpi-label">Total Préstamos</div>
                        <div class="kpi-trend text-success"><i class="bi bi-arrow-up-short"></i> Histórico</div>
                    </div>
                    <div class="kpi-icon" style="background: #7c4dff;">
                        <i class="bi bi-folder2-open"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $activeLoans }}</div>
                        <div class="kpi-label">Activos</div>
                        <div class="kpi-trend text-success"><i class="bi bi-arrow-up-short"></i> En curso</div>
                    </div>
                    <div class="kpi-icon" style="background: #ff6d00;">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
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
                    <div class="kpi-icon" style="background: #00c853;">
                        <i class="bi bi-check-circle"></i>
                    </div>
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
                    <div class="kpi-icon" style="background: #ff1744;">
                        <i class="bi bi-clock-history"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory & Capital Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg,#f0f7ff 0%,#e8f0fe 100%);">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#1565c0;">
                        <i class="bi bi-boxes"></i>
                    </div>
                    <div class="flex-fill">
                        <div class="kpi-label">Valor en Stock <span class="text-muted fw-normal">(costo)</span></div>
                        <div class="kpi-value text-primary">{{ $currencySymbol }} {{ number_format($inventoryValueCost, 0, ',', '.') }}</div>
                        <div class="fs-xs" style="color:#888;">Precio venta: {{ $currencySymbol }} {{ number_format($inventoryValuePrice, 0, ',', '.') }}</div>
                    </div>
                    <x-icon-btn icon="chevron-right" label="Ver inventario" variant="secondary" :href="route('inventory.index')" class="flex-shrink-0" />
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg,{{ $netCapital >= 0 ? '#f1f8f2,#e6f4ea' : '#fff5f5,#ffebee' }} 100%);">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:{{ $netCapital >= 0 ? '#2e7d32' : '#c62828' }};">
                        <i class="bi bi-bank2"></i>
                    </div>
                    <div class="flex-fill">
                        <div class="kpi-label">Capital Neto</div>
                        <div class="kpi-value" style="color:{{ $netCapital >= 0 ? '#2e7d32' : '#c62828' }};">
                            {{ $currencySymbol }} {{ number_format(abs($netCapital), 0, ',', '.') }}
                        </div>
                        <div class="fs-xs" style="color:#888;">{{ $netCapital >= 0 ? 'Capital disponible' : 'Déficit de capital' }}</div>
                    </div>
                    <x-icon-btn icon="chevron-right" label="Ver capital" variant="secondary" :href="route('capital.index')" class="flex-shrink-0" />
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100" style="background: linear-gradient(135deg,#fff8e1,#fff3cd);">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="kpi-icon" style="background:#f9a825;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="flex-fill">
                        <div class="kpi-label">Margen Potencial</div>
                        <div class="kpi-value" style="color:#f57f17;">
                            {{ $currencySymbol }} {{ number_format($inventoryValuePrice - $inventoryValueCost, 0, ',', '.') }}
                        </div>
                        <div class="fs-xs" style="color:#888;">Si se vende todo el stock</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Amount + Quick Stats Row -->
    <div class="row g-3 mb-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">Resumen Financiero</h6>
                            <small class="text-muted">Montos generales del sistema</small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="bi bi-funnel"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-item-text text-muted small">Vista actual</span></li>
                            </ul>
                        </div>
                    </div>
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
                    <div class="d-flex flex-column gap-3">
                        @php
                            $total = max($totalLoans, 1);
                            $activePct = round(($activeLoans / $total) * 100);
                            $completedPct = round(($completedLoans / $total) * 100);
                            $overduePct = round(($overdueLoans / $total) * 100);
                            $pendingPct = 100 - $activePct - $completedPct - $overduePct;
                        @endphp
                        <div>
                            <div class="d-flex justify-content-between small mb-1"><span>Activos</span><span>{{ $activePct }}%</span></div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $activePct }}%; background: #ff6d00;"></div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between small mb-1"><span>Completados</span><span>{{ $completedPct }}%</span></div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $completedPct }}%; background: #00c853;"></div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between small mb-1"><span>Vencidos</span><span>{{ $overduePct }}%</span></div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $overduePct }}%; background: #ff1744;"></div>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between small mb-1"><span>Pendientes</span><span>{{ $pendingPct }}%</span></div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: {{ $pendingPct }}%; background: #7c4dff;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Loans Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0">Préstamos Recientes</h6>
                <a href="{{ route('loans.index') }}" class="text-decoration-none small">VER TODOS <i class="bi bi-chevron-right"></i></a>
            </div>
            @if($recentLoans->isEmpty())
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted fs-xl"></i>
                    <p class="text-muted mt-2 mb-0">No hay préstamos registrados aún.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th class="text-end">Monto</th>
                                <th>Tasa</th>
                                <th>Plazo</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentLoans as $loan)
                                <tr>
                                    <td><strong>PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}</strong></td>
                                    <td>{{ $loan->client?->name ?? 'Sin cliente' }}</td>
                                    <td class="text-end">{{ $currencySymbol }} {{ number_format($loan->amount, 2) }}</td>
                                    <td>{{ $loan->interest_rate }}%</td>
                                    <td>{{ $loan->term_months }} meses</td>
                                    <td>
                                        @php
                                            $statusMap = [
                                                \App\Models\Loan::STATUS_ACTIVE => ['bg-success', 'Activo'],
                                                \App\Models\Loan::STATUS_FINISHED => ['bg-secondary', 'Finalizado'],
                                                \App\Models\Loan::STATUS_OVERDUE => ['bg-danger', 'En mora'],
                                                \App\Models\Loan::STATUS_DEFAULTED => ['bg-dark', 'Incumplido'],
                                                \App\Models\Loan::STATUS_CANCELLED => ['bg-secondary', 'Cancelado'],
                                            ];
                                            $s = $statusMap[$loan->status] ?? ['bg-secondary', ucfirst($loan->status)];
                                        @endphp
                                        <span class="badge {{ $s[0] }}">{{ $s[1] }}</span>
                                    </td>
                                    <td class="text-end">
                                        <x-icon-btn icon="eye" label="Ver" variant="secondary" :href="route('loans.show', $loan)" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

@push('styles')
<style>
    .kpi-card {
        background: #fff;
        border-radius: 10px;
        padding: 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 0;
    }
    .kpi-body {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .kpi-value {
        font-size: var(--fs-lg);
        font-weight: 700;
        line-height: 1;
        margin-bottom: 4px;
    }
    .kpi-label {
        font-size: var(--fs-xs);
        color: #777;
        margin-bottom: 6px;
    }
    .kpi-trend {
        font-size: var(--fs-xs);
    }
    .kpi-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: grid;
        place-items: center;
        color: #fff;
        font-size: var(--fs-md);
        flex-shrink: 0;
    }
</style>
@endpush
@endsection
