@extends('layouts.app')

@push('styles')
<style>

    .filter-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: .75rem;
    }
</style>
@endpush

@section('page')
<x-page-header title="Compras" icon="bag-check" :count="$purchases->total() . ' compras'">
    @if(auth()->user()->hasPermissionInCompany('purchases.create', auth()->user()->getCurrentCompany()))
        @if($hasActiveCashSession)
            <x-btn icon="plus-lg" :href="route('purchases.create', ['sucursal_id' => $selectedBranchId])">Nueva Compra</x-btn>
        @else
            <x-btn variant="secondary" icon="lock" class="opacity-75" style="cursor:not-allowed;"
                data-bs-toggle="tooltip" data-bs-placement="left"
                title="Debes aperturar una caja antes de registrar una compra">Nueva Compra</x-btn>
        @endif
    @endif
</x-page-header>

@if($branches->count() > 1)
<div class="mb-3">
    <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
    <div class="chip-row d-inline-flex">
        <a class="chip {{ $selectedBranchId === 0 ? 'is-active' : '' }}"
           href="{{ route('purchases.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => 0])) }}">
            <i class="bi bi-grid-3x3-gap-fill" style="font-size:var(--fs-xs);opacity:.8;"></i>
            Todos
        </a>
        @foreach($branches as $branch)
            @php $bColor = $branch->color ?? '#0d6efd'; @endphp
            <a class="chip {{ (int) $selectedBranchId === (int) $branch->id ? 'is-active' : '' }}"
               style="--branch-color: {{ $bColor }};"
               href="{{ route('purchases.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => $branch->id])) }}">
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

<div class="card filter-card mb-3 border-0">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('purchases.index') }}" class="row g-2 align-items-end">
            <input type="hidden" name="sucursal_id" value="{{ $selectedBranchId }}">

            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Búsqueda rápida</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control form-control-sm border-start-0" value="{{ $q }}" placeholder="ID, cliente o producto">
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completada</option>
                    <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Producto</label>
                <select name="product_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ $productId === (int) $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}">
            </div>

            <div class="col-12 d-flex gap-2 mt-1">
                <x-btn type="submit" icon="funnel">Filtrar</x-btn>
                <x-btn variant="secondary" icon="x-circle" :href="route('purchases.index', ['sucursal_id' => $selectedBranchId])">Limpiar</x-btn>
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
                    <th>Cliente / Vendedor</th>
                    <th>Producto</th>
                    <th class="text-end">Monto</th>
                    <th>Fecha</th>
                    <th>Caja</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchases as $purchase)
                    <tr style="cursor:pointer" onclick="window.location='{{ route('purchases.show', $purchase) }}'">
                        <td class="ps-3">
                            <strong>CO-{{ str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT) }}</strong>
                            @if($purchase->branch)
                                @php $lbColor = $purchase->branch->color ?? '#6c757d'; @endphp
                                <div class="mt-1">
                                    <span style="display:inline-flex;align-items:center;gap:3px;font-size:var(--fs-xs);padding:1px 7px 1px 4px;border-radius:20px;background:{{ $lbColor }}18;color:{{ $lbColor }};border:1px solid {{ $lbColor }}44;line-height:1.6;white-space:nowrap;font-weight:500;">
                                        <span style="width:6px;height:6px;border-radius:50%;background:{{ $lbColor }};flex-shrink:0;"></span>
                                        {{ $purchase->branch->name }}
                                    </span>
                                </div>
                            @endif
                        </td>
                        <td>{{ $purchase->client?->name ?? 'Sin cliente' }}</td>
                        <td>
                            <div>{{ $purchase->product?->name ?? '-' }}</div>
                            @php
                                $categoryColor = $purchase->creditCategory?->color
                                    ?? match((int) ($purchase->credit_category_id ?? 0)) {
                                        1 => '#4F46E5',
                                        2 => '#D9B646',
                                        3 => '#16A34A',
                                        4 => '#E68A2E',
                                        default => '#64748B',
                                    };
                                $hex = ltrim((string) $categoryColor, '#');
                                $r = strlen($hex) === 6 ? hexdec(substr($hex, 0, 2)) : 100;
                                $g = strlen($hex) === 6 ? hexdec(substr($hex, 2, 2)) : 100;
                                $b = strlen($hex) === 6 ? hexdec(substr($hex, 4, 2)) : 139;
                                $categoryBg = "rgba({$r}, {$g}, {$b}, 0.16)";
                                $categoryBorder = "rgba({$r}, {$g}, {$b}, 0.28)";
                            @endphp
                            <div class="mt-1">
                                <span class="badge rounded-pill fw-normal" style="font-size:var(--fs-xs); padding:.2rem .48rem; background-color: {{ $categoryBg }}; color: {{ $categoryColor }}; border: 1px solid {{ $categoryBorder }};">
                                    {{ $purchase->creditCategory?->name ?? 'Sin categoría' }}
                                </span>
                            </div>
                        </td>
                        <td class="text-end fw-semibold">{{ $currencySymbol }} {{ number_format((float) $purchase->amount, 2) }}</td>
                        <td>{{ $purchase->purchase_date?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $purchase->cashRegister?->name ?? '-' }}</td>
                        <td>
                            @if($purchase->status === 'completed')
                                <span class="badge bg-success">Completada</span>
                            @elseif($purchase->status === 'cancelled')
                                <span class="badge bg-dark">Cancelada</span>
                            @else
                                <span class="badge bg-secondary">{{ ucfirst($purchase->status) }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No hay compras registradas
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

@if($purchases->hasPages())
    <div class="d-flex justify-content-center mt-3">
        {{ $purchases->links() }}
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });
});
</script>
@endpush
