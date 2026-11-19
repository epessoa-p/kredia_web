@extends('layouts.app')

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});
</script>
@endpush

@push('styles')
<style>

    /* ── Filter Card ─────────────────────────────────────── */
    .filter-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: .75rem;
    }
</style>
@endpush

@section('page')
{{-- ── Header ─────────────────────────────────────────────── --}}
<x-page-header title="Préstamos" icon="cash-coin" :count="$loans->total() . ' préstamos'">
        @include('components.export-dropdown', ['baseUrl' => route('exports.loans'), 'params' => request()->all()])
        @php
            $planCompany = auth()->user()->getCurrentCompany();
            $allowsPawn = auth()->user()->is_super_admin || ($planCompany && $planCompany->planAllows('pawn'));
            $allowsInstallments = auth()->user()->is_super_admin || ($planCompany && $planCompany->planAllows('installments'));
        @endphp
        @if(auth()->user()->hasPermissionInCompany('loans.create', auth()->user()->getCurrentCompany()))
            @if($hasActiveCashSession)
                @if($allowsInstallments)
                    <x-btn variant="secondary" icon="calendar2-week" :href="route('loans.installments.create')">Préstamo en cuotas</x-btn>
                @endif
                @if($allowsPawn)
                    <x-btn icon="plus-circle" :href="route('loans.create', ['sucursal_id' => $selectedBranchId])">Nuevo empeño</x-btn>
                @endif
            @else
                <x-btn variant="secondary" icon="lock" style="cursor:not-allowed;opacity:.65;"
                    data-bs-toggle="tooltip" data-bs-placement="left"
                    title="Debes aperturar una caja antes de crear un préstamo">Nuevo Préstamo</x-btn>
            @endif
        @endif
</x-page-header>

{{-- ── Branch Selector ────────────────────────────────────── --}}
@if($branches->count() > 1)
<div class="mb-3">
    <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
    <div class="chip-row d-inline-flex">
        <a class="chip {{ $selectedBranchId === 0 ? 'is-active' : '' }}"
           href="{{ route('loans.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => 0])) }}">
            <i class="bi bi-grid-3x3-gap-fill fs-xs" style="opacity:.8;"></i>
            Todos
        </a>
        @foreach($branches as $branch)
            @php $bColor = $branch->color ?? '#0d6efd'; @endphp
            <a class="chip {{ (int) $selectedBranchId === (int) $branch->id ? 'is-active' : '' }}"
               style="--branch-color: {{ $bColor }};"
               href="{{ route('loans.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => $branch->id])) }}">
                <span class="chip-dot"></span>
                {{ $branch->name }}
            </a>
        @endforeach
    </div>
</div>
@elseif($branches->count() === 1)
<div class="mb-3">
    @php $bColor = $branches->first()->color ?? '#0d6efd'; @endphp
    <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
    <span class="chip is-active" style="--branch-color: {{ $bColor }}; cursor:default;">
        <span class="chip-dot"></span>
        {{ $branches->first()->name }}
    </span>
</div>
@endif

{{-- ── Filters ────────────────────────────────────────────── --}}
<div class="card filter-card mb-3 border-0">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('loans.index') }}" class="row g-2 align-items-end">
            <input type="hidden" name="sucursal_id" value="{{ $selectedBranchId }}">
            <input type="hidden" name="deleted_filter" value="{{ $deletedFilter }}">

            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Búsqueda rápida</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control form-control-sm border-start-0 no-upper" value="{{ $q }}" placeholder="Código, cliente o producto (ej. 146, 147)">
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach(\App\Models\Loan::STATUS_LABELS as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Categoría</label>
                <select name="credit_category_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ (int) $categoryId === (int) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Personal</label>
                <select name="personal_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($personals as $p)
                        <option value="{{ $p->id }}" {{ (int) $personalId === (int) $p->id ? 'selected' : '' }}>{{ $p->full_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Producto</label>
                <select name="sold_filter" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="sold" {{ $soldFilter === 'sold' ? 'selected' : '' }}>Producto vendido</option>
                    <option value="not_sold" {{ $soldFilter === 'not_sold' ? 'selected' : '' }}>Sin vender</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Intereses sin pagar</label>
                <div class="d-flex gap-1 flex-wrap">
                    @foreach(['1', '2', '3', '4', '5+'] as $n)
                        <input type="checkbox" class="btn-check" name="unpaid_interests[]" value="{{ $n }}"
                               id="unpaid-{{ $loop->index }}" autocomplete="off"
                               {{ in_array($n, $unpaidCounts, true) ? 'checked' : '' }}>
                        <label class="btn btn-sm btn-outline-danger" style="min-width:34px;" for="unpaid-{{ $loop->index }}">{{ $n }}</label>
                    @endforeach
                </div>
                <div class="text-muted fs-xs" >Marca una o varias cantidades (ej. 2 y 3).</div>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
            </div>

            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Registros</label>
                <div class="k-seg w-100">
                    @foreach(['' => 'Activos', 'deleted' => 'Eliminados', 'all' => 'Todos'] as $dfVal => $dfLbl)
                        <a href="{{ route('loans.index', array_merge(request()->except(['deleted_filter', 'page']), ['deleted_filter' => $dfVal])) }}"
                           class="btn btn-sm btn-secondary flex-fill {{ $deletedFilter === $dfVal ? 'active' : '' }}">
                            {{ $dfLbl }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="col-12 d-flex gap-2 mt-1">
                <x-btn type="submit" icon="funnel">Filtrar</x-btn>
                <x-btn variant="secondary" icon="x-circle" :href="route('loans.index', ['sucursal_id' => $selectedBranchId])">Limpiar</x-btn>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 table-list">
            <thead class="table-light">
                <tr>
                    <th class="ps-3">COD</th>
                    <th>Cliente</th>
                    <th>Producto</th>
                    <th class="text-end">Monto</th>
                    <th class="text-end">Pagado</th>
                    <th class="text-end">Pendiente</th>
                    <th>Tasa</th>
                    <th>Estado</th>
                    <th class="text-end pe-3">Fecha</th>
                </tr>
            </thead>
            <tbody>
                @forelse($loans as $loan)
                    @php $isDeleted = $loan->trashed(); @endphp
                    <tr style="cursor:pointer;@if($isDeleted) background:rgba(220,53,69,.04);opacity:.85;@endif"
                        onclick="window.location='{{ route('loans.show', ['loan' => $loan, 'from' => 'loans']) }}'">
                        <td class="ps-3">
                            <strong>PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}</strong>
                            @if($isDeleted)
                                <span class="badge bg-danger ms-1 fs-xs" >Eliminado</span>
                            @endif
                            @if($loan->branch)
                                @php $lbColor = $loan->branch->color ?? '#6c757d'; @endphp
                                <div class="mt-1">
                                    <span class="fs-xs" style="display:inline-flex;align-items:center;gap:3px;padding:1px 7px 1px 4px;border-radius:20px;background:{{ $lbColor }}18;color:{{ $lbColor }};border:1px solid {{ $lbColor }}44;line-height:1.6;white-space:nowrap;font-weight:500;">
                                        <span style="width:6px;height:6px;border-radius:50%;background:{{ $lbColor }};flex-shrink:0;"></span>
                                        {{ $loan->branch->name }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $loan->client?->name ?? 'Sin cliente' }}</div>
                            @php $registeredBy = $loan->createdBy?->personal?->full_name ?? $loan->createdBy?->name; @endphp
                            @if($registeredBy)
                                <div class="text-muted d-inline-flex align-items-center gap-1 mt-1 fs-xs"  title="Registrado por">
                                    <i class="bi bi-person-badge text-mod"></i>{{ $registeredBy }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $loan->product?->name ?? '-' }}</div>
                            @php
                                $categoryColor = $loan->creditCategory?->color
                                    ?? match((int) ($loan->credit_category_id ?? 0)) {
                                        1 => '#4F46E5',
                                        2 => '#D9B646',
                                        3 => '#16A34A',
                                        4 => '#E68A2E',
                                        default => '#64748B',
                                    };
                                $hex = ltrim((string) $categoryColor, '#');
                                $r = strlen($hex) === 6 ? hexdec(substr($hex, 0, 2)) : 100;
                                $g = strlen($hex) === 6 ? hexdec(substr($hex, 2, 2)) : 116;
                                $b = strlen($hex) === 6 ? hexdec(substr($hex, 4, 2)) : 139;
                                $categoryBg = "rgba({$r}, {$g}, {$b}, 0.16)";
                                $categoryBorder = "rgba({$r}, {$g}, {$b}, 0.28)";
                            @endphp
                            <div class="mt-1">
                                    <span class="badge rounded-pill fw-normal fs-xs" style="padding:.2rem .48rem; background-color: {{ $categoryBg }}; color: {{ $categoryColor }}; border: 1px solid {{ $categoryBorder }};">
                                    {{ $loan->creditCategory?->name ?? 'Sin categoría' }}
                                </span>
                            </div>
                            @php
                                $productoVendido = ($loan->collateral ?? collect())->contains(fn ($c) => $c->status === 'sold');
                            @endphp
                            @if($productoVendido)
                                <div class="mt-1">
                                    <span class="badge bg-dark fs-xs"  title="El producto en garantía de este préstamo fue vendido">
                                        <i class="bi bi-cart-check me-1"></i>Producto vendido
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td class="text-end">{{ $currencySymbol }} {{ number_format($loan->amount, 2) }}</td>
                        <td class="text-end text-success">{{ $currencySymbol }} {{ number_format($loan->total_paid, 2) }}</td>
                        @php
                            $loanPending = in_array($loan->status, [\App\Models\Loan::STATUS_FINISHED, \App\Models\Loan::STATUS_CANCELLED])
                                ? 0
                                : round((float) $loan->current_capital + $loan->getUnpaidInterestTotal(), 2);
                        @endphp
                        <td class="text-end {{ $loanPending > 0 ? 'text-danger' : 'text-muted' }}">{{ $currencySymbol }} {{ number_format($loanPending, 2) }}</td>
                        <td>{{ $loan->interest_rate }}%</td>
                        <td>
                            <x-status-badge :status="$loan->status" />
                            @if($loan->penalty_status === 'overdue')
                                <span class="badge bg-warning text-dark" title="En periodo de gracia"><i class="bi bi-clock-fill"></i></span>
                            @elseif($loan->penalty_status === 'defaulted')
                                <span class="badge bg-danger" title="Multa aplicada"><i class="bi bi-exclamation-triangle-fill"></i></span>
                            @endif
                            @if(($loan->unpaid_interests_count ?? 0) > 0)
                                <div class="mt-1">
                                    <span class="badge bg-light text-danger border border-danger-subtle fw-semibold fs-xs" 
                                          title="Intereses generados sin pagar">
                                        <i class="bi bi-hourglass-split me-1"></i>{{ $loan->unpaid_interests_count }} {{ $loan->unpaid_interests_count === 1 ? 'interés' : 'intereses' }} sin pagar
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td class="text-end pe-3 text-muted fs-sm">
                            <div>{{ $loan->created_at?->format('d/m/Y') }}</div>
                            <small class="fs-xs" ><i class="bi bi-clock me-1"></i>{{ $loan->created_at?->format('H:i') }}</small>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            No hay préstamos registrados
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

        @if($loans->hasPages())
            <div class="d-flex justify-content-center mt-3">
                {{ $loans->appends(request()->query())->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
@endsection
