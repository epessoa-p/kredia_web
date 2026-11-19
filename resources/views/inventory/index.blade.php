@extends('layouts.app')

@push('styles')
<style>
    .branch-selector {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        align-items: center;
    }
    .branch-btn {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .45rem 1.1rem;
        border-radius: 2rem;
        font-size: var(--fs-base);
        font-weight: 500;
        border: 1.5px solid #dee2e6;
        background: #fff;
        color: #495057;
        text-decoration: none;
        transition: all .18s ease;
        white-space: nowrap;
    }
    .branch-btn:hover {
        border-color: #0d6efd;
        color: #0d6efd;
        background: #f0f5ff;
        text-decoration: none;
    }
    .branch-btn.active {
        background: var(--branch-color, #0d6efd);
        border-color: var(--branch-color, #0d6efd);
        color: #fff;
        box-shadow: 0 3px 10px color-mix(in srgb, var(--branch-color, #0d6efd) 40%, transparent);
    }
    .branch-btn:not(.active) {
        color: var(--branch-color, #495057);
        border-color: color-mix(in srgb, var(--branch-color, #dee2e6) 60%, transparent);
    }
    .branch-btn:not(.active):hover {
        background: color-mix(in srgb, var(--branch-color, #0d6efd) 8%, white);
        border-color: var(--branch-color, #0d6efd);
    }
    .branch-btn .branch-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--branch-color, currentColor);
        opacity: .7;
        flex-shrink: 0;
    }
    .branch-btn.active .branch-dot {
        opacity: 1;
        background: rgba(255,255,255,.85);
    }
    .branch-btn-todos { --branch-color: #495057; }
    .branch-btn-todos.active { background: #212529; border-color: #212529; --branch-color: #212529; }

    .filter-card {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: .75rem;
    }
    .stock-pill {
        font-size: var(--fs-xs);
        padding: .2rem .55rem;
    }

    /* ─── Toggle pill buttons (stock / movement type) ─── */
    .toggle-pill {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .3rem .85rem;
        border-radius: 2rem;
        font-size: var(--fs-sm);
        font-weight: 500;
        border: 1.5px solid #dee2e6;
        background: #fff;
        color: #6c757d;
        cursor: pointer;
        transition: all .16s ease;
        white-space: nowrap;
        user-select: none;
    }
    .toggle-pill:hover {
        border-color: #adb5bd;
        color: #343a40;
        background: #f1f3f5;
    }
    .toggle-pill.active {
        background: #212529;
        border-color: #212529;
        color: #fff;
    }
    .toggle-pill.active.pill-green  { background: #198754; border-color: #198754; }
    .toggle-pill.active.pill-red    { background: #dc3545; border-color: #dc3545; }
    .toggle-pill.active.pill-blue   { background: #0d6efd; border-color: #0d6efd; }
    .toggle-pill.active.pill-orange { background: #fd7e14; border-color: #fd7e14; }
    .toggle-pill.active.pill-dark { background: #1e293b; border-color: #1e293b; color: #fff; }
    .toggle-pill .pill-qty { font-weight: 700; background: rgba(0,0,0,.07); border-radius: 999px; padding: 0 .45rem; margin-left: .1rem; }
    .toggle-pill.active .pill-qty { background: rgba(255,255,255,.25); }
    .toggle-pill.active.pill-purple { background: #6f42c1; border-color: #6f42c1; }
    .toggle-pill.active.pill-teal   { background: #0dcaf0; border-color: #0dcaf0; color: #000; }

    /* ─── Clickable rows ─── */
    .inv-row { cursor: pointer; transition: background .12s; }
    .inv-row:hover td { background: #f0f5ff !important; }

    /* ─── Inline price edit ─── */
    .price-input { max-width: 90px; }
    .price-input.is-saved { border-color: #198754; background: #f1f8f2; }
    .price-input.is-error { border-color: #dc3545; background: #fdf0f0; }
    .price-group .input-group-text { background: #fff; color: #6c757d; font-size: var(--fs-xs); }
</style>
@endpush

@section('title', 'Inventario')

@section('page')
@php
    $canEditPrice = auth()->user()->is_super_admin
        || auth()->user()->hasPermissionInCompany('warehouses.edit', auth()->user()->getCurrentCompany());
    $orphanTotal = ($orphanKeys ?? collect())->count();
@endphp
<x-page-header title="Inventario" icon="boxes">
        @if($orphanTotal > 0 || $onlyRegularize)
            {{-- Clic: muestra solo los productos por regularizar (otro clic quita el filtro). --}}
            <a href="{{ route('inventory.index', array_filter(['sucursal_id' => $selectedBranchId, 'regularize' => $onlyRegularize ? null : 1, 'has_stock' => $onlyRegularize ? null : ''], fn ($v) => $v !== null)) }}"
               class="badge d-inline-flex align-items-center gap-1 py-2 px-3 text-decoration-none fs-xs"
               style="background:{{ $onlyRegularize ? '#f59e0b' : '#fff8e6' }};color:{{ $onlyRegularize ? '#fff' : '#92400e' }};border:1px solid #f5d488;font-weight:600;"
               title="{{ $onlyRegularize ? 'Quitar el filtro' : 'Productos de préstamos eliminados que quedaron sin liberar. Clic para ver solo esos.' }}">
                <i class="bi bi-exclamation-triangle-fill"></i>
                {{ $orphanTotal }} por regularizar
                @if($onlyRegularize)<i class="bi bi-x-circle ms-1"></i>@else<i class="bi bi-funnel ms-1"></i>@endif
            </a>
        @endif
        @if(auth()->user()->hasPermissionInCompany('products.create', auth()->user()->getCurrentCompany()))
            <x-btn icon="box-arrow-in-down" :href="route('inventory.stock-entry', array_filter(['sucursal_id' => request('sucursal_id')]))">Ingresar producto</x-btn>
        @endif
</x-page-header>

{{-- Inventory value summary --}}
<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="rounded-3 p-3 d-flex align-items-center gap-3" style="background:#f0f7ff;border:1px solid #d0e4ff;">
            <div class="fs-md" style="width:40px;height:40px;border-radius:12px;background:#1565c0;color:#fff;display:grid;place-items:center;flex-shrink:0;">
                <i class="bi bi-currency-dollar"></i>
            </div>
            <div>
                <div class="fs-xs" style="color:#666;text-transform:uppercase;letter-spacing:.04em;">Valor a costo</div>
                <div class="fs-lg" style="font-weight:700;color:#1565c0;">{{ $currencySymbol }} {{ number_format($inventoryValueCost, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="rounded-3 p-3 d-flex align-items-center gap-3" style="background:#f1f8f2;border:1px solid #c8e6c9;">
            <div class="fs-md" style="width:40px;height:40px;border-radius:12px;background:#2e7d32;color:#fff;display:grid;place-items:center;flex-shrink:0;">
                <i class="bi bi-tag-fill"></i>
            </div>
            <div>
                <div class="fs-xs" style="color:#666;text-transform:uppercase;letter-spacing:.04em;">Valor a precio venta</div>
                <div class="fs-lg" style="font-weight:700;color:#2e7d32;">{{ $currencySymbol }} {{ number_format($inventoryValuePrice, 2, ',', '.') }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="rounded-3 p-3 d-flex align-items-center gap-3" style="background:#fff8e1;border:1px solid #ffe082;">
            <div class="fs-md" style="width:40px;height:40px;border-radius:12px;background:#f9a825;color:#fff;display:grid;place-items:center;flex-shrink:0;">
                <i class="bi bi-graph-up"></i>
            </div>
            <div>
                <div class="fs-xs" style="color:#666;text-transform:uppercase;letter-spacing:.04em;">Margen potencial</div>
                <div class="fs-lg" style="font-weight:700;color:#f57f17;">{{ $currencySymbol }} {{ number_format($inventoryValuePrice - $inventoryValueCost, 2, ',', '.') }}</div>
                <div class="fs-xs" style="color:#aaa;">{{ $inventoryItemCount }} productos con stock</div>
            </div>
        </div>
    </div>
</div>

@if($branches->count() > 1)
<div class="mb-3">
    <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
    <div class="branch-selector d-inline-flex">
        <a class="branch-btn branch-btn-todos {{ $selectedBranchId === 0 ? 'active' : '' }}"
           href="{{ route('inventory.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => 0])) }}">
            <i class="bi bi-grid-3x3-gap-fill fs-xs" style="opacity:.8;"></i>
            Todos
        </a>
        @foreach($branches as $branch)
            @php $bColor = $branch->color ?? '#0d6efd'; @endphp
            <a class="branch-btn {{ (int) $selectedBranchId === (int) $branch->id ? 'active' : '' }}"
               style="--branch-color: {{ $bColor }};"
               href="{{ route('inventory.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => $branch->id])) }}">
                <span class="branch-dot"></span>
                {{ $branch->name }}
            </a>
        @endforeach
    </div>
</div>
@elseif($branches->count() === 1)
<div class="mb-3">
    @php $bColor = $branches->first()->color ?? '#0d6efd'; @endphp
    <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
    <span class="branch-btn active" style="--branch-color: {{ $bColor }}; cursor:default;">
        <span class="branch-dot"></span>
        {{ $branches->first()->name }}
    </span>
</div>
@endif

{{-- ─── FILTER CARD ─────────────────────────────────────────────── --}}
@include('admin.products._split-shared', ['sharedPreview' => $sharedPreview ?? null])

<div class="card filter-card mb-3 border-0">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('inventory.index') }}" id="filterForm">
            <input type="hidden" name="sucursal_id" value="{{ $selectedBranchId }}">
            @if($onlyRegularize)<input type="hidden" name="regularize" value="1">@endif

            {{-- Row 1: search + dropdowns + submit --}}
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Busqueda</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control form-control-sm border-start-0" value="{{ $q }}" placeholder="Producto, código (PD-12) o SKU">
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Almacen</label>
                    <select name="warehouse_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ (int) $selectedWarehouseId === (int) $warehouse->id ? 'selected' : '' }}>
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-semibold mb-1">Categoria</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (int) $selectedCategoryId === (int) $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2 align-items-end">
                    <x-btn icon="funnel" :block="true" type="submit">Filtrar</x-btn>
                    <x-icon-btn icon="x-circle" label="Limpiar" variant="secondary" :href="route('inventory.index', ['sucursal_id' => $selectedBranchId])" />
                </div>
            </div>

            {{-- Row 2: status multi-select --}}
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <span class="text-muted small fw-semibold" style="white-space:nowrap;">
                    <i class="bi bi-tag me-1"></i>Estado:
                </span>
                @php
                    $statusOpts = [
                        'available' => ['label' => 'Disponible',       'icon' => 'bi-check-circle-fill', 'cls' => 'pill-green'],
                        'retained'  => ['label' => 'En préstamo',      'icon' => 'bi-person-badge',      'cls' => 'pill-blue'],
                        'stored'    => ['label' => 'En retención',      'icon' => 'bi-lock-fill',         'cls' => 'pill-purple'],
                    ];
                @endphp
                @php $fmtQty = fn ($n) => number_format($n, floor($n) == $n ? 0 : 2, ',', '.'); @endphp
                {{-- Total: unidades con todos los filtros aplicados (todas las páginas). Clic = quitar el filtro de estados. --}}
                <a href="{{ request()->fullUrlWithoutQuery(['statuses', 'page']) }}"
                   class="toggle-pill pill-dark {{ empty($statuses) ? 'active' : '' }} text-decoration-none"
                   title="Unidades con los filtros aplicados ({{ $qtyTotals['rows'] }} {{ $qtyTotals['rows'] === 1 ? 'fila' : 'filas' }}). Clic: todos los estados.">
                    <i class="bi bi-boxes fs-xs"></i>
                    Total <span class="pill-qty">{{ $fmtQty($qtyTotals['total']) }}</span>
                </a>
                @foreach($statusOpts as $val => $opt)
                    <label class="toggle-pill {{ $opt['cls'] }} {{ in_array($val, $statuses) ? 'active' : '' }}" style="cursor:pointer;">
                        <input type="checkbox" name="statuses[]" value="{{ $val }}" class="d-none auto-submit" {{ in_array($val, $statuses) ? 'checked' : '' }}>
                        <i class="bi {{ $opt['icon'] }} fs-xs"></i>
                        {{ $opt['label'] }} <span class="pill-qty">{{ $fmtQty($qtyTotals['by_state'][$val] ?? 0) }}</span>
                    </label>
                @endforeach
                @if(!empty($statuses))
                    <span class="text-muted small ms-1 fs-xs">
                        ({{ count($statuses) }} seleccionado{{ count($statuses) > 1 ? 's' : '' }})
                    </span>
                @endif
            </div>

            {{-- Row 3: stock availability toggle --}}

            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <span class="text-muted small fw-semibold" style="white-space:nowrap;">
                    <i class="bi bi-stack me-1"></i>Disponibilidad:
                </span>
                @php
                    $stockOpts = [
                        '1'  => ['label' => 'Con stock',  'icon' => 'bi-check-circle-fill', 'cls' => 'pill-green'],
                        '0'  => ['label' => 'Sin stock',  'icon' => 'bi-x-circle-fill',     'cls' => 'pill-red'],
                        ''   => ['label' => 'Todos',      'icon' => 'bi-list',               'cls' => ''],
                    ];
                @endphp
                @foreach($stockOpts as $val => $opt)
                    <label class="toggle-pill {{ $opt['cls'] }} {{ $hasStock === (string)$val ? 'active' : '' }}" style="cursor:pointer;">
                        <input type="radio" name="has_stock" value="{{ $val }}" class="d-none auto-submit" {{ $hasStock === (string)$val ? 'checked' : '' }}>
                        <i class="bi {{ $opt['icon'] }} fs-xs"></i>
                        {{ $opt['label'] }}
                    </label>
                @endforeach
            </div>

            {{-- Cantidad (solo super admin): productos con más de N unidades en el almacén --}}
            @if(auth()->user()->is_super_admin)
            <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
                <span class="text-muted small fw-semibold" style="white-space:nowrap;">
                    <i class="bi bi-123 me-1"></i>Cantidad:
                </span>
                @php $qtyOpts = ['' => 'Cualquiera', '1' => 'Más de 1', '3' => 'Más de 3', '5' => 'Más de 5', '10' => 'Más de 10']; @endphp
                @foreach($qtyOpts as $val => $label)
                    @php $isActive = (string) ($qtyGt ?? '') === (string) $val; @endphp
                    <label class="toggle-pill {{ $val !== '' ? 'pill-orange' : '' }} {{ $isActive ? 'active' : '' }}" style="cursor:pointer;">
                        <input type="radio" name="qty_gt" value="{{ $val }}" class="d-none auto-submit" {{ $isActive ? 'checked' : '' }}>
                        @if($val !== '')<i class="bi bi-stack fs-xs"></i>@endif
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @endif

            {{-- Row 3: movement type multi-select --}}
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="text-muted small fw-semibold" style="white-space:nowrap;">
                    <i class="bi bi-arrow-left-right me-1"></i>Origen:
                </span>
                @php
                    $typeOpts = [
                        'loan'     => ['label' => 'Préstamo',      'icon' => 'bi-person-badge',    'cls' => 'pill-blue'],
                        'purchase' => ['label' => 'Compra',        'icon' => 'bi-bag-fill',         'cls' => 'pill-orange'],
                        'manual'   => ['label' => 'Ingreso manual','icon' => 'bi-box-arrow-in-down','cls' => 'pill-purple'],
                        'sale'     => ['label' => 'Venta',         'icon' => 'bi-cart-fill',        'cls' => 'pill-teal'],
                    ];
                @endphp
                @foreach($typeOpts as $val => $opt)
                    <label class="toggle-pill {{ $opt['cls'] }} {{ in_array($val, $movementTypes) ? 'active' : '' }}" style="cursor:pointer;">
                        <input type="checkbox" name="movement_types[]" value="{{ $val }}" class="d-none auto-submit" {{ in_array($val, $movementTypes) ? 'checked' : '' }}>
                        <i class="bi {{ $opt['icon'] }} fs-xs"></i>
                        {{ $opt['label'] }}
                    </label>
                @endforeach
                @if(!empty($movementTypes))
                    <span class="text-muted small ms-1 fs-xs">
                        ({{ count($movementTypes) }} seleccionado{{ count($movementTypes) > 1 ? 's' : '' }})
                    </span>
                @endif
            </div>

        </form>
    </div>
</div>

{{-- ─── TABLE ──────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Producto</th>
                        <th>Código</th>
                        <th>Categoria</th>
                        <th>Almacen</th>
                        <th class="text-center">Cantidad</th>
                        <th>Estado</th>
                        <th class="text-end">Precio</th>
                        <th class="text-end pe-3">Ultimo movimiento</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php
                            $categoryColor = $row->category_color ?: '#64748B';
                            $hex = ltrim((string) $categoryColor, '#');
                            $r = strlen($hex) === 6 ? hexdec(substr($hex, 0, 2)) : 100;
                            $g = strlen($hex) === 6 ? hexdec(substr($hex, 2, 2)) : 116;
                            $b = strlen($hex) === 6 ? hexdec(substr($hex, 4, 2)) : 139;
                            $categoryBg = "rgba({$r}, {$g}, {$b}, 0.16)";
                            $categoryBorder = "rgba({$r}, {$g}, {$b}, 0.28)";
                            $detailUrl = route('inventory.product', ['productId' => $row->product_id, 'warehouseId' => $row->warehouse_id]);
                        @endphp
                        @php
                            $isOrphan = ($orphanKeys ?? collect())->contains($row->product_id . ':' . $row->warehouse_id . ':' . $row->state);
                        @endphp
                        <tr class="inv-row" onclick="window.location='{{ $detailUrl }}'" title="Ver detalle">
                            <td class="ps-3">
                                <div class="fw-semibold">{{ $row->product_name }}</div>
                                @if(!$row->product_active)
                                    <span class="badge bg-secondary fs-xs">Inactivo</span>
                                @endif
                                @if($isOrphan)
                                    <span class="badge bg-warning text-dark mt-1 d-inline-flex align-items-center gap-1 fs-xs"
                                         
                                          title="Préstamo eliminado sin liberar. Entra al detalle para regularizar.">
                                        <i class="bi bi-exclamation-triangle-fill"></i> Requiere regularización
                                    </span>
                                @endif
                            </td>
                            <td class="small text-nowrap">
                                @if($row->code)<span class="code-pill">{{ $row->code }}</span>@else<span class="text-muted">—</span>@endif
                                @if($row->sku)<div class="text-muted fs-xs">{{ $row->sku }}</div>@endif
                            </td>
                            <td>
                                <span class="badge rounded-pill fw-normal fs-xs" style="padding:.2rem .48rem; background-color: {{ $categoryBg }}; color: {{ $categoryColor }}; border: 1px solid {{ $categoryBorder }};">
                                    {{ $row->category_name ?: 'Sin categoria' }}
                                </span>
                            </td>
                            <td class="small">{{ $row->warehouse_name }}</td>
                            <td class="text-center fw-semibold">
                                <span class="{{ $row->state === 'available' ? 'text-success' : 'text-muted' }}">{{ number_format($row->qty, 0) }}</span>
                                @if($row->state !== 'out' && $row->qty != $row->product_total_stock)
                                    <small class="text-muted d-block fs-xs">de {{ number_format($row->product_total_stock, 0) }} total</small>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-{{ $row->state_color }} stock-pill">{{ $row->state_label }}</span>
                            </td>
                            <td class="text-end" onclick="event.stopPropagation()">
                                @if($canEditPrice)
                                    <div class="price-edit d-inline-flex" data-product-id="{{ $row->product_id }}">
                                        <div class="input-group input-group-sm price-group" style="width:135px;">
                                            <span class="input-group-text px-2">{{ $currencySymbol }}</span>
                                            <input type="number" step="0.01" min="0"
                                                   class="form-control form-control-sm price-input text-end no-upper"
                                                   value="{{ number_format($row->price, 2, '.', '') }}"
                                                   aria-label="Precio">
                                            <span class="input-group-text px-2 price-spinner d-none">
                                                <span class="spinner-border spinner-border-sm" style="width:.8rem;height:.8rem;"></span>
                                            </span>
                                        </div>
                                    </div>
                                @else
                                    <span class="fw-semibold text-success">{{ $currencySymbol }} {{ number_format($row->price, 2, ',', '.') }}</span>
                                @endif
                            </td>
                            <td class="text-end pe-3 text-muted small">{{ $row->last_movement_at ? \Carbon\Carbon::parse($row->last_movement_at)->format('d/m/Y') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 opacity-30"></i>
                                No hay registros para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 px-3">
                <span class="text-muted small">
                    Mostrando {{ $rows->firstItem() }}–{{ $rows->lastItem() }} de {{ $rows->total() }}
                </span>
                {{ $rows->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('filterForm');
    if (!form) return;

    // Los pills envuelven un input oculto (checkbox/radio). En vez de depender del
    // evento 'change' del input oculto (poco fiable), manejamos el clic del label:
    // alternamos el estado a mano, actualizamos el color y enviamos el formulario.
    document.querySelectorAll('.toggle-pill').forEach(function (label) {
        label.addEventListener('click', function (e) {
            var input = label.querySelector('input.auto-submit');
            if (!input) return;

            e.preventDefault(); // evita el doble toggle del label nativo

            if (input.type === 'radio') {
                // Desmarcar los demás del mismo grupo (has_stock)
                form.querySelectorAll('input[name="' + input.name + '"]').forEach(function (r) {
                    r.checked = false;
                    var l = r.closest('label');
                    if (l) l.classList.remove('active');
                });
                input.checked = true;
                label.classList.add('active');
            } else {
                input.checked = !input.checked;
                label.classList.toggle('active', input.checked);
            }

            form.submit();
        });
    });

    // ─── Edición inline del precio (AJAX con spinner) ───────────────
    const csrfToken = '{{ csrf_token() }}';

    document.querySelectorAll('.price-edit').forEach(function (wrap) {
        const input   = wrap.querySelector('.price-input');
        const spinner = wrap.querySelector('.price-spinner');
        const pid     = wrap.dataset.productId;
        if (!input) return;

        let original = input.value;
        input.addEventListener('focus', function () { original = input.value; });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); input.blur(); }
        });

        input.addEventListener('change', function () {
            const val = parseFloat(input.value);
            input.classList.remove('is-saved', 'is-error');

            if (isNaN(val) || val < 0) {
                input.value = original;
                return;
            }
            // Sin cambios reales → no llamar al servidor
            if (parseFloat(original) === val) return;

            input.disabled = true;
            spinner.classList.remove('d-none');

            fetch(`{{ url('inventory/product') }}/${pid}/price`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ price: val }),
            })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
            .then(function (data) {
                if (data.success) {
                    input.value = data.price;
                    original = data.price;
                    input.classList.add('is-saved');
                    setTimeout(function () { input.classList.remove('is-saved'); }, 1400);
                } else {
                    throw new Error('fail');
                }
            })
            .catch(function () {
                input.value = original;
                input.classList.add('is-error');
                setTimeout(function () { input.classList.remove('is-error'); }, 1800);
            })
            .finally(function () {
                input.disabled = false;
                spinner.classList.add('d-none');
            });
        });
    });
});
</script>
@endpush
