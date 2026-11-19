@extends('layouts.app')

@section('title', 'Reportes')

@push('styles')
<style>
    .report-card {
        border: none;
        border-radius: .75rem;
        transition: all .2s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .report-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,.1);
        color: inherit;
    }
    .report-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--fs-xl);
    }
    .stat-mini {
        background: #f8f9fa;
        border-radius: .5rem;
        padding: .75rem 1rem;
        text-align: center;
    }
    .stat-mini .value {
        font-size: var(--fs-xl);
        font-weight: 700;
        line-height: 1.2;
    }
    .stat-mini .label {
        font-size: var(--fs-xs);
        color: #6c757d;
        margin-top: .15rem;
    }
</style>
@endpush

@section('page')
<div class="container-fluid">
    {{-- Header --}}
    <x-page-header title="Centro de Reportes" icon="bar-chart-line" />
    <p class="text-muted mb-4">Visualiza y exporta información clave de tu negocio.</p>

    {{-- Quick Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="value text-primary">{{ number_format($stats['total_loans']) }}</div>
                <div class="label">Total préstamos</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="value text-success">{{ number_format($stats['active_loans']) }}</div>
                <div class="label">Préstamos activos</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="value text-info">{{ number_format($stats['total_clients']) }}</div>
                <div class="label">Clientes</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-mini">
                <div class="value text-danger">{{ $stats['overdue_loans'] + $stats['defaulted_loans'] }}</div>
                <div class="label">En mora / Incumplidos</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-mini">
                <div class="value text-success">{{ $currencySymbol }} {{ number_format($stats['total_disbursed'], 2) }}</div>
                <div class="label">Total desembolsado</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-mini">
                <div class="value text-primary">{{ $currencySymbol }} {{ number_format($stats['total_collected'], 2) }}</div>
                <div class="label">Total recaudado</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-mini">
                <div class="value text-danger">{{ $currencySymbol }} {{ number_format($stats['total_pending'], 2) }}</div>
                <div class="label">Por cobrar</div>
            </div>
        </div>
    </div>

    {{-- Report Cards --}}
    <h6 class="text-uppercase text-muted small fw-bold mb-3"><i class="bi bi-grid me-1"></i> Reportes disponibles</h6>
    <div class="row g-3">
        {{-- Movements Module --}}
        <div class="col-md-6 col-lg-3">
            <a href="{{ route('reports.movements') }}" class="report-card card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="report-icon bg-dark bg-opacity-10 text-dark">
                            <i class="bi bi-activity"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Movimientos</h6>
                            <small class="text-muted">Balance operativo</small>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">Consulta transacciones y cierres de caja en un módulo unificado con utilidad operativa, filtros y control diario.</p>
                </div>
            </a>
        </div>

        {{-- Loans Report --}}
        <div class="col-md-6 col-lg-3">
            <a href="{{ route('reports.loans') }}" class="report-card card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="report-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Préstamos</h6>
                            <small class="text-muted">Cartera completa</small>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">Analiza tu cartera de préstamos por estado, sucursal, producto y fechas. Incluye montos, pagos y saldos pendientes.</p>
                </div>
            </a>
        </div>

        {{-- Payments Report --}}
        <div class="col-md-6 col-lg-3">
            <a href="{{ route('reports.payments') }}" class="report-card card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="report-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-receipt-cutoff"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Pagos</h6>
                            <small class="text-muted">Historial de cobros</small>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">Revisa todos los pagos recibidos, filtra por fecha, método de pago y monto. Desglose capital e intereses.</p>
                </div>
            </a>
        </div>

        {{-- Clients Report --}}
        <div class="col-md-6 col-lg-3">
            <a href="{{ route('reports.clients') }}" class="report-card card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="report-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-people"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Clientes</h6>
                            <small class="text-muted">Cartera de clientes</small>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">Consulta la cartera de clientes con préstamos activos, montos totales, pagados y pendientes por cliente.</p>
                </div>
            </a>
        </div>

        {{-- Warehouses Report --}}
        <div class="col-md-6 col-lg-3">
            <a href="{{ route('reports.warehouses') }}" class="report-card card shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="report-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-building-add"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0">Almacenes</h6>
                            <small class="text-muted">Stock en custodia</small>
                        </div>
                    </div>
                    <p class="small text-muted mb-0">Visualiza productos en custodia por almacén, con desglose de estados: retenidos, en mora y disponibles para venta.</p>
                </div>
            </a>
        </div>

        @if(auth()->user()->is_super_admin || auth()->user()->hasPermissionInCompany('cash-registers.report', auth()->user()->getCurrentCompany()))
            {{-- Cash Register Sessions Report --}}
            <div class="col-md-6 col-lg-3">
                <a href="{{ route('reports.cash-register-sessions') }}" class="report-card card shadow-sm h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="report-icon bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-safe2"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Cajas</h6>
                                <small class="text-muted">Aperturas y cierres</small>
                            </div>
                        </div>
                        <p class="small text-muted mb-0">Reporte detallado por sesión de caja: apertura, cierre, responsable y cobros vinculados por capital e interés.</p>
                    </div>
                </a>
            </div>
        @endif
    </div>
</div>
@endsection
