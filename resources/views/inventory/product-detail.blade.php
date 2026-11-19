@extends('layouts.app')

@push('styles')
<style>
    .stat-card {
        border-radius: .85rem;
        padding: 1rem 1.2rem;
        display: flex;
        align-items: center;
        gap: .9rem;
        border: 1px solid transparent;
    }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        font-size: var(--fs-lg);
        flex-shrink: 0;
        color: #fff;
    }
    .stat-label {
        font-size: var(--fs-xs);
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #888;
        font-weight: 600;
    }
    .stat-value {
        font-size: var(--fs-xl);
        font-weight: 800;
        line-height: 1;
        margin-top: .1rem;
    }
    .move-badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        font-size: var(--fs-xs);
        padding: .18rem .5rem;
        border-radius: 2rem;
        font-weight: 600;
    }
    .mov-linkable {
        cursor: pointer;
        transition: background .12s;
    }
    .mov-linkable:hover td {
        background: #f0f5ff !important;
    }
    .mov-linkable:hover .move-badge {
        filter: brightness(.94);
    }
</style>
@endpush

@section('title', 'Detalle — ' . $product->name)

@section('page')

{{-- ─── BREADCRUMB ─────────────────────────────────── --}}
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}"><i class="bi bi-boxes me-1"></i>Inventario</a></li>
        <li class="breadcrumb-item active text-truncate" style="max-width:260px;">{{ $product->name }}</li>
    </ol>
</nav>

{{-- ─── HEADER ─────────────────────────────────────── --}}
<x-page-header :title="$product->name" icon="box-seam" :back="route('inventory.index')">
    <x-slot:meta>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            @if($product->code)
                <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle" title="Código del producto"><i class="bi bi-hash me-1"></i>{{ $product->code }}</span>
            @endif
            @if($product->sku)
                <span class="badge bg-light text-dark border" title="SKU"><i class="bi bi-upc me-1"></i>{{ $product->sku }}</span>
            @endif
            @if($product->creditCategory)
                @php
                    $cc = $product->creditCategory;
                    $hex = ltrim((string)($cc->color ?? '#64748b'), '#');
                    $cr = strlen($hex) === 6 ? hexdec(substr($hex, 0, 2)) : 100;
                    $cg = strlen($hex) === 6 ? hexdec(substr($hex, 2, 2)) : 116;
                    $cb = strlen($hex) === 6 ? hexdec(substr($hex, 4, 2)) : 139;
                @endphp
                <span class="badge rounded-pill fw-normal fs-xs"
                      style="background:rgba({{$cr}},{{$cg}},{{$cb}},.15);color:{{$cc->color}};border:1px solid rgba({{$cr}},{{$cg}},{{$cb}},.3);">
                    {{ $cc->name }}
                </span>
            @endif
            <span class="badge {{ $product->active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                {{ $product->active ? 'Activo' : 'Inactivo' }}
            </span>
        </div>
    </x-slot:meta>
    @php
            $canRegularize = auth()->user()->is_super_admin
                || auth()->user()->hasPermissionInCompany('warehouses.edit', auth()->user()->getCurrentCompany());
        @endphp
        @if(($orphanCount ?? 0) > 0 && $canRegularize)
            <x-btn variant="warning" icon="wrench-adjustable" type="button" data-bs-toggle="modal" data-bs-target="#regularizeModal"
                    title="Corrige el stock de préstamos eliminados que quedaron sin liberar">Regularizar ({{ $orphanCount }})</x-btn>
        @endif
</x-page-header>

{{-- ─── STOCK SUMMARY CARDS ─────────────────────────── --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card" style="background:#f0f7ff;border-color:#d0e4ff;">
            <div class="stat-icon" style="background:#1565c0;"><i class="bi bi-stack"></i></div>
            <div>
                <div class="stat-label">Stock total</div>
                <div class="stat-value" style="color:#1565c0;">{{ number_format($stock, 0) }}</div>
                <div class="fs-xs" style="color:#aaa;">unidades</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card" style="background:#f1f8f2;border-color:#c8e6c9;">
            <div class="stat-icon" style="background:#2e7d32;"><i class="bi bi-check-circle-fill"></i></div>
            <div>
                <div class="stat-label">Disponible</div>
                <div class="stat-value" style="color:#2e7d32;">{{ number_format($freeStock, 0) }}</div>
                <div class="fs-xs" style="color:#aaa;">libres</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card" style="background:#e8f0fe;border-color:#c5d8fc;">
            <div class="stat-icon" style="background:#1a73e8;"><i class="bi bi-person-badge-fill"></i></div>
            <div>
                <div class="stat-label">En préstamo</div>
                <div class="stat-value" style="color:#1a73e8;">{{ $retainedCount + $storedCount }}</div>
                <div class="fs-xs" style="color:#aaa;">como garantía</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- ─── LEFT: Product info + collaterals ─── --}}
    <div class="col-lg-4">

        {{-- Product details --}}
        <div class="card mb-3 border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom py-2 px-3">
                <span class="fw-semibold small"><i class="bi bi-info-circle me-1 text-primary"></i>Información del producto</span>
            </div>
            <div class="card-body p-3">
                <dl class="row mb-0 small" style="row-gap:.3rem;">
                    @if($product->brand)
                        <dt class="col-5 text-muted fw-normal">Marca</dt>
                        <dd class="col-7 mb-0 fw-semibold">{{ $product->brand }}</dd>
                    @endif
                    @if($product->model_year)
                        <dt class="col-5 text-muted fw-normal">Año/Modelo</dt>
                        <dd class="col-7 mb-0 fw-semibold">{{ $product->model_year }}</dd>
                    @endif
                    @if($product->plate)
                        <dt class="col-5 text-muted fw-normal">Placa</dt>
                        <dd class="col-7 mb-0 fw-semibold">{{ $product->plate }}</dd>
                    @endif
                    @if($product->chassis)
                        <dt class="col-5 text-muted fw-normal">Chasis</dt>
                        <dd class="col-7 mb-0 fw-semibold">{{ $product->chassis }}</dd>
                    @endif
                    @if($product->serial_number)
                        <dt class="col-5 text-muted fw-normal">N° Serie</dt>
                        <dd class="col-7 mb-0 fw-semibold">{{ $product->serial_number }}</dd>
                    @endif
                    @if($product->item_type)
                        <dt class="col-5 text-muted fw-normal">Tipo</dt>
                        <dd class="col-7 mb-0 fw-semibold">{{ $product->item_type }}</dd>
                    @endif
                    @if($product->karat)
                        <dt class="col-5 text-muted fw-normal">Karat</dt>
                        <dd class="col-7 mb-0 fw-semibold">{{ $product->karat }}</dd>
                    @endif
                    @if($product->material_type)
                        <dt class="col-5 text-muted fw-normal">Material</dt>
                        <dd class="col-7 mb-0 fw-semibold">{{ $product->material_type }}</dd>
                    @endif
                    <dt class="col-5 text-muted fw-normal">Costo</dt>
                    <dd class="col-7 mb-0 fw-semibold text-primary">{{ $currencySymbol }} {{ number_format($product->cost, 2, ',', '.') }}</dd>
                    <dt class="col-5 text-muted fw-normal">Precio</dt>
                    <dd class="col-7 mb-0 fw-semibold text-success">{{ $currencySymbol }} {{ number_format($product->price, 2, ',', '.') }}</dd>
                    <dt class="col-5 text-muted fw-normal">Almacén</dt>
                    <dd class="col-7 mb-0">{{ $warehouse->name }}</dd>
                    @if($product->description)
                        <dt class="col-5 text-muted fw-normal">Descripción</dt>
                        <dd class="col-7 mb-0">{{ $product->description }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Active collaterals --}}
        @if($collaterals->count() > 0)
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom py-2 px-3">
                <span class="fw-semibold small"><i class="bi bi-person-badge me-1 text-info"></i>Garantías activas ({{ $collaterals->count() }})</span>
            </div>
            <div class="list-group list-group-flush">
                @foreach($collaterals as $col)
                    @php
                        $colStatus = match($col->status) {
                            'retained' => ['label' => 'En préstamo', 'color' => 'info'],
                            'stored'   => ['label' => 'Almacenado',  'color' => 'warning'],
                            'sellable' => ['label' => 'Disponible',  'color' => 'success'],
                            default    => ['label' => $col->status,  'color' => 'secondary'],
                        };
                    @endphp
                    @php $loanDeleted = $col->loan && $col->loan->trashed(); @endphp
                    <div class="list-group-item px-3 py-2 border-0 border-bottom" @if($loanDeleted) style="background:rgba(220,53,69,.05);" @endif>
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold small">{{ $col->loan?->client?->name ?? '—' }}</div>
                                @if($col->loan)
                                    <a href="{{ route('loans.show', $col->loan->id) }}" class="text-decoration-none small {{ $loanDeleted ? 'text-danger' : 'text-primary' }}">
                                        PR-{{ str_pad($col->loan->numero ?? $col->loan->id, 5, '0', STR_PAD_LEFT) }}
                                    </a>
                                    @if($loanDeleted)
                                        <span class="badge bg-danger ms-1 fs-xs">
                                            <i class="bi bi-trash3 me-1"></i>Eliminado
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted small">Préstamo no disponible</span>
                                @endif
                            </div>
                            <span class="badge bg-{{ $colStatus['color'] }}-subtle text-{{ $colStatus['color'] }} border border-{{ $colStatus['color'] }}-subtle fs-xs">
                                {{ $colStatus['label'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- ─── RIGHT: Movement history ─── --}}
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom py-2 px-3 d-flex justify-content-between align-items-center">
                <span class="fw-semibold small"><i class="bi bi-clock-history me-1 text-primary"></i>Historial de movimientos</span>
                <span class="badge bg-light text-dark border">{{ $movements->total() }} registros</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-center">Cantidad</th>
                            <th>Referencia</th>
                            <th>Notas</th>
                            <th class="pe-3">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $mov)
                            @php
                                $isIn  = $mov->type === 'in';
                                $ref   = $mov->reference ?? '';

                                // Classify reference, build link and formatted code
                                $srcLink   = null;
                                $refCode   = null;   // formatted code to display (PR-00007, CO-00007, VE-00007)

                                if (str_starts_with($ref, 'LOAN-RET-')) {
                                    $srcLabel = 'Préstamo';
                                    $srcIcon  = 'bi-person-badge-fill';
                                    $srcColor = '#1a73e8';
                                    $srcBg    = '#e8f0fe';
                                    $refId    = (int) substr($ref, strlen('LOAN-RET-'));
                                    $srcLink  = $refId ? route('loans.show', $refId) : null;
                                    $refNum   = $refId ? ($loansNumeroMap->get($refId) ?? $refId) : null;
                                    $refCode  = $refNum ? 'PR-' . str_pad($refNum, 5, '0', STR_PAD_LEFT) : null;
                                } elseif (str_starts_with($ref, 'LOAN-REL-')) {
                                    $srcLabel = 'Liberación';
                                    $srcIcon  = 'bi-unlock-fill';
                                    $srcColor = '#2e7d32';
                                    $srcBg    = '#f1f8f2';
                                    $refId    = (int) substr($ref, strlen('LOAN-REL-'));
                                    $srcLink  = $refId ? route('loans.show', $refId) : null;
                                    $refNum   = $refId ? ($loansNumeroMap->get($refId) ?? $refId) : null;
                                    $refCode  = $refNum ? 'PR-' . str_pad($refNum, 5, '0', STR_PAD_LEFT) : null;
                                } elseif (str_starts_with($ref, 'LOAN-DEL-')) {
                                    $srcLabel = 'Eliminación préstamo';
                                    $srcIcon  = 'bi-trash3-fill';
                                    $srcColor = '#dc3545';
                                    $srcBg    = '#fdecec';
                                    $refId    = (int) substr($ref, strlen('LOAN-DEL-'));
                                    $srcLink  = $refId ? route('loans.show', $refId) : null;
                                    $refNum   = $refId ? ($loansNumeroMap->get($refId) ?? $refId) : null;
                                    $refCode  = $refNum ? 'PR-' . str_pad($refNum, 5, '0', STR_PAD_LEFT) : null;
                                } elseif (str_starts_with($ref, 'COMPRA-')) {
                                    $srcLabel = 'Compra';
                                    $srcIcon  = 'bi-bag-fill';
                                    $srcColor = '#e65100';
                                    $srcBg    = '#fef3e2';
                                    $refId    = (int) substr($ref, strlen('COMPRA-'));
                                    $srcLink  = $refId ? route('purchases.show', $refId) : null;
                                    $refNum   = $refId ? ($purchasesNumeroMap->get($refId) ?? $refId) : null;
                                    $refCode  = $refNum ? 'CO-' . str_pad($refNum, 5, '0', STR_PAD_LEFT) : null;
                                } elseif (str_starts_with($ref, 'VENTA-')) {
                                    $srcLabel = 'Venta';
                                    $srcIcon  = 'bi-cart-fill';
                                    $srcColor = '#0d6efd';
                                    $srcBg    = '#f0f5ff';
                                    $refId    = (int) substr($ref, strlen('VENTA-'));
                                    $srcLink  = $refId ? route('sales.show', $refId) : null;
                                    $refNum   = $refId ? ($salesNumeroMap->get($refId) ?? $refId) : null;
                                    $refCode  = $refNum ? 'VE-' . str_pad($refNum, 5, '0', STR_PAD_LEFT) : null;
                                } elseif (str_starts_with($ref, 'DEVOLUCION-')) {
                                    $srcLabel = 'Devolución';
                                    $srcIcon  = 'bi-arrow-return-left';
                                    $srcColor = '#6f42c1';
                                    $srcBg    = '#f3f0ff';
                                    $refId    = (int) substr($ref, strlen('DEVOLUCION-'));
                                    $srcLink  = $refId ? route('sales.show', $refId) : null;
                                    $refNum   = $refId ? ($salesNumeroMap->get($refId) ?? $refId) : null;
                                    $refCode  = $refNum ? 'VE-' . str_pad($refNum, 5, '0', STR_PAD_LEFT) : null;
                                } else {
                                    $srcLabel = 'Manual';
                                    $srcIcon  = 'bi-pencil-fill';
                                    $srcColor = '#495057';
                                    $srcBg    = '#f8f9fa';
                                    $srcLink  = null;
                                    $refCode  = null;
                                }
                            @endphp
                            <tr @if($srcLink) onclick="window.location='{{ $srcLink }}'" @endif
                                class="{{ $srcLink ? 'mov-linkable' : '' }}">
                                <td class="ps-3 text-nowrap small">
                                    {{ \Carbon\Carbon::parse($mov->movement_date)->format('d/m/Y') }}
                                    <div class="text-muted fs-xs">{{ \Carbon\Carbon::parse($mov->movement_date)->format('H:i') }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="move-badge" style="background:{{ $isIn ? '#d1fae5' : '#fee2e2' }};color:{{ $isIn ? '#065f46' : '#991b1b' }};">
                                        <i class="bi {{ $isIn ? 'bi-arrow-down-circle-fill' : 'bi-arrow-up-circle-fill' }}"></i>
                                        {{ $isIn ? 'Entrada' : 'Salida' }}
                                    </span>
                                </td>
                                <td class="text-center fw-bold {{ $isIn ? 'text-success' : 'text-danger' }}">
                                    {{ $isIn ? '+' : '-' }}{{ number_format($mov->quantity, 0) }}
                                </td>
                                <td>
                                    <span class="move-badge" style="background:{{ $srcBg }};color:{{ $srcColor }};">
                                        <i class="bi {{ $srcIcon }}"></i>
                                        {{ $srcLabel }}
                                        @if($srcLink)
                                            <i class="bi bi-box-arrow-up-right ms-1 fs-xs" style="opacity:.7;"></i>
                                        @endif
                                    </span>
                                    @if($refCode)
                                        <div class="fw-semibold mt-1 fs-xs" style="color:{{ $srcColor }};">{{ $refCode }}</div>
                                    @endif
                                </td>
                                <td class="small text-muted" style="max-width:160px;white-space:normal;">{{ $mov->notes ?? '—' }}</td>
                                <td class="pe-3 small text-muted text-nowrap">{{ $mov->user?->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-30"></i>
                                    Sin movimientos registrados
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($movements->hasPages())
                <div class="card-footer bg-transparent py-2 px-3">
                    {{ $movements->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ─── MODAL: Regularizar ───────────────────────────── --}}
@if(($orphanCount ?? 0) > 0 && ($canRegularize ?? false))
<div class="modal fade" id="regularizeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header border-0 text-white" style="background:linear-gradient(135deg,#f59e0b,#d97706);padding:18px 22px;">
                <h5 class="modal-title fw-bold mb-0"><i class="bi bi-wrench-adjustable me-2"></i>Regularizar inventario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" style="padding:22px;">
                <div class="d-flex gap-3 mb-3">
                    <div class="flex-shrink-0 fs-lg" style="width:46px;height:46px;border-radius:12px;background:#fff3cd;color:#b45309;display:grid;place-items:center;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div>
                        <p class="mb-2">Este producto tiene <strong>{{ $orphanCount }} garantía(s)</strong> de préstamos <strong>eliminados</strong> que quedaron sin liberar y siguen contando en el stock.</p>
                        <p class="mb-0 small text-muted">Al regularizar se generará la <strong>salida de inventario</strong> correspondiente y el colateral quedará <strong>liberado</strong>. No afecta la caja.</p>
                    </div>
                </div>
                <div class="p-3 rounded-3 fs-base" style="background:#f8f9fa;border:1px dashed #dee2e6;">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Producto</span>
                        <span class="fw-semibold text-truncate ms-2" style="max-width:230px;">{{ $product->name }}</span>
                    </div>
                    <div class="d-flex justify-content-between mt-2">
                        <span class="text-muted">Garantías a liberar</span>
                        <span class="fw-bold" style="color:#b45309;">{{ $orphanCount }}</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0" style="padding:0 22px 22px;">
                <x-btn variant="secondary" type="button" data-bs-dismiss="modal">Cancelar</x-btn>
                <form method="POST" action="{{ route('inventory.regularize') }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">
                    <x-btn variant="warning" icon="check-lg" type="submit">Regularizar</x-btn>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
