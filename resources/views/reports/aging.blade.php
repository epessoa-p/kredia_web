@extends('layouts.app')

@section('title', 'Morosidad')

@section('page')
<x-page-header title="Cartera por atraso (morosidad)" icon="hourglass-split" :back="route('reports.index')" />

<form method="GET" class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label" for="branch_id">Sucursal</label>
            <select id="branch_id" name="branch_id" class="form-select form-select-sm">
                <option value="">Todas</option>
                @foreach($branches as $b)<option value="{{ $b->id }}" @selected($branchId == $b->id)>{{ $b->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4"><x-btn type="submit" icon="funnel">Filtrar</x-btn></div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="stat-card"><div class="kpi-label">Saldo total de cartera</div><div class="kpi-value">{{ $currencySymbol }} {{ number_format($total_balance, 2) }}</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="kpi-label">Saldo con atraso</div><div class="kpi-value text-danger">{{ $currencySymbol }} {{ number_format($late_balance, 2) }}</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="kpi-label">Índice de morosidad</div><div class="kpi-value {{ $delinquency_rate > 10 ? 'text-danger' : 'text-success' }}">{{ number_format($delinquency_rate, 2) }}%</div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-bar-chart text-mod"></i> Tramos de atraso</div>
    <div class="card-body">
        @foreach($buckets as $bucket)
            @php $pct = $total_balance > 0 ? round($bucket['balance'] / $total_balance * 100, 1) : 0; @endphp
            <div class="d-flex align-items-center gap-3 mb-2">
                <div class="fs-sm" style="width: 130px;">{{ $bucket['label'] }}</div>
                <div class="flex-fill">
                    <div class="progress" style="height: 18px;" role="progressbar" aria-label="{{ $bucket['label'] }}" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar {{ $bucket['key'] === 'current' ? 'bg-success' : ($bucket['key'] === '1_30' ? 'bg-warning' : 'bg-danger') }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                <div class="fs-sm text-end" style="width: 220px;">{{ $bucket['count'] }} préstamos · <strong>{{ $currencySymbol }} {{ number_format($bucket['balance'], 2) }}</strong> ({{ $pct }}%)</div>
            </div>
        @endforeach
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-list-ul text-mod"></i> Préstamos con atraso</div>
    <div class="table-responsive">
        <table class="table table-list align-middle mb-0">
            <thead><tr><th>Préstamo</th><th>Tipo</th><th>Cliente</th><th>Sucursal</th><th>Estado</th><th class="text-end">Días</th><th class="text-end">Saldo</th></tr></thead>
            <tbody>
                @forelse($rows->where('days_late', '>', 0) as $row)
                    <tr>
                        <td><a href="{{ route('loans.show', $row['loan_id']) }}" class="code-pill text-decoration-none">{{ $row['code'] }}</a></td>
                        <td class="fs-sm">{{ $row['kind'] === 'installment' ? 'Cuotas' : 'Empeño' }}</td>
                        <td>{{ $row['client'] }}<div class="fs-xs text-muted">{{ $row['phone'] }}</div></td>
                        <td><span class="chip" style="--branch-color: {{ $row['branch_color'] ?: '#64748b' }}"><span class="chip-dot"></span>{{ $row['branch'] }}</span></td>
                        <td><x-status-badge :status="$row['status']" /></td>
                        <td class="text-end fw-semibold {{ $row['days_late'] > 30 ? 'text-danger' : '' }}">{{ $row['days_late'] }}</td>
                        <td class="text-end">{{ number_format($row['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No hay préstamos con atraso. 🎉</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
