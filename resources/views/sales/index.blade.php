@extends('layouts.app')

@section('title', 'Ventas')

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
@php
    $canDeleteSale = auth()->user()->is_super_admin
        || auth()->user()->hasPermissionInCompany('sales.delete', auth()->user()->getCurrentCompany());
    // Puede autorizar a otro usuario a eliminar una venta puntual.
    $canDelegate = auth()->user()->is_super_admin
        || auth()->user()->hasPermissionInCompany('sales.delegate_delete', auth()->user()->getCurrentCompany());
@endphp
@if(session('success'))
<div class="alert border-0 mb-3 d-flex align-items-center gap-3 p-3" style="background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border-radius:14px;border-left:4px solid #22c55e !important;">
    <i class="bi bi-check-circle-fill text-success fs-5 flex-shrink-0"></i>
    <span class="fw-semibold text-success small">{{ session('success') }}</span>
    <button type="button" class="btn-close ms-auto btn-sm" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('error') || $errors->any())
<div class="alert alert-danger alert-dismissible fade show mb-3 small" role="alert">
    {{ session('error') ?? $errors->first() }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Header --}}
<x-page-header title="Ventas" icon="cart-check" :count="$sales->total() . ' ventas'">
    @if($hasActiveCashSession)
        <x-btn icon="plus-lg" :href="route('sales.create')">Nueva Venta</x-btn>
    @else
        <x-btn variant="secondary" icon="lock" class="opacity-75" style="cursor:not-allowed;"
            data-bs-toggle="tooltip" data-bs-placement="left"
            title="Debes aperturar una caja antes de registrar una venta">Nueva Venta</x-btn>
    @endif
</x-page-header>

{{-- Branch Selector --}}
@if($branches->count() > 1)
<div class="mb-3">
    <span class="text-muted small me-2"><i class="bi bi-geo-alt me-1"></i>Sucursal:</span>
    <div class="chip-row d-inline-flex">
        <a class="chip {{ $selectedBranchId === 0 ? 'is-active' : '' }}"
           href="{{ route('sales.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => 0])) }}">
            <i class="bi bi-grid-3x3-gap-fill" style="font-size:var(--fs-xs);opacity:.8;"></i>
            Todos
        </a>
        @foreach($branches as $branch)
            @php $bColor = $branch->color ?? '#0d6efd'; @endphp
            <a class="chip {{ (int) $selectedBranchId === (int) $branch->id ? 'is-active' : '' }}"
               style="--branch-color: {{ $bColor }};"
               href="{{ route('sales.index', array_merge(request()->except(['page', 'sucursal_id']), ['sucursal_id' => $branch->id])) }}">
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

{{-- Filters --}}
<div class="card filter-card mb-3 border-0">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('sales.index') }}" class="row g-2 align-items-end">
            <input type="hidden" name="sucursal_id" value="{{ $selectedBranchId }}">

            <div class="col-md-3">
                <label class="form-label small fw-semibold mb-1">Búsqueda rápida</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control form-control-sm border-start-0" value="{{ $q ?? '' }}" placeholder="Código, cliente, producto...">
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Estado</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="completed" {{ ($status ?? '') === 'completed' ? 'selected' : '' }}>Completada</option>
                    <option value="cancelled" {{ ($status ?? '') === 'cancelled' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Personal</label>
                <select name="personal_id" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($personals as $p)
                        <option value="{{ $p->id }}" {{ (int) ($personalId ?? 0) === (int) $p->id ? 'selected' : '' }}>{{ $p->full_name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Desde</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-semibold mb-1">Hasta</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
            </div>

            <div class="col-md-3 d-flex gap-2 mt-1">
                <x-btn type="submit" icon="funnel">Filtrar</x-btn>
                <x-btn variant="secondary" icon="x-circle" :href="route('sales.index', ['sucursal_id' => $selectedBranchId])">Limpiar</x-btn>
            </div>
        </form>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 table-list">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">COD</th>
                        <th>Cliente</th>
                        <th>Producto</th>
                        <th class="text-end">Venta</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Origen</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-4">Fecha</th>
                        <th class="text-center pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sales as $sale)
                        <tr style="cursor:pointer" onclick="window.location='{{ route('sales.show', $sale) }}'">
                            <td class="ps-3" onclick="event.stopPropagation()">
                                <strong>VE-{{ str_pad((string) $sale->numero, 5, '0', STR_PAD_LEFT) }}</strong>
                                @if($sale->branch)
                                    @php $lbColor = $sale->branch->color ?? '#6c757d'; @endphp
                                    <div class="mt-1">
                                        <span style="display:inline-flex;align-items:center;gap:3px;font-size:var(--fs-xs);padding:1px 7px 1px 4px;border-radius:20px;background:{{ $lbColor }}18;color:{{ $lbColor }};border:1px solid {{ $lbColor }}44;line-height:1.6;white-space:nowrap;font-weight:500;">
                                            <span style="width:6px;height:6px;border-radius:50%;background:{{ $lbColor }};flex-shrink:0;"></span>
                                            {{ $sale->branch->name }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $sale->client?->name ?? '-' }}</div>
                                @php $registeredBy = $sale->createdBy?->personal?->full_name ?? $sale->createdBy?->name; @endphp
                                @if($registeredBy)
                                    <div class="text-muted d-inline-flex align-items-center gap-1 mt-1 fs-xs" title="Registrado por">
                                        <i class="bi bi-person-badge" style="color:#6f42c1;"></i>{{ $registeredBy }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div>{{ $sale->product?->name ?? '-' }}</div>
                                @php
                                    $catColor = $sale->creditCategory?->color
                                        ?? match((int) ($sale->credit_category_id ?? 0)) {
                                            1 => '#4F46E5', 2 => '#D9B646', 3 => '#16A34A', 4 => '#E68A2E', default => '#64748B',
                                        };
                                    $hex = ltrim((string) $catColor, '#');
                                    $r = strlen($hex) === 6 ? hexdec(substr($hex, 0, 2)) : 100;
                                    $g = strlen($hex) === 6 ? hexdec(substr($hex, 2, 2)) : 116;
                                    $b = strlen($hex) === 6 ? hexdec(substr($hex, 4, 2)) : 139;
                                @endphp
                                <div class="mt-1">
                                    <span class="badge rounded-pill fw-normal" style="font-size:var(--fs-xs); padding:.2rem .48rem; background: rgba({{ $r }},{{ $g }},{{ $b }},0.16); color: {{ $catColor }}; border: 1px solid rgba({{ $r }},{{ $g }},{{ $b }},0.28);">
                                        {{ $sale->creditCategory?->name ?? '-' }}
                                    </span>
                                </div>
                            </td>
                            <td class="text-end fw-semibold">{{ $currencySymbol }} {{ number_format((float) $sale->amount, 2) }}</td>
                            <td class="text-center">
                                @if($sale->isCredit())
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle fs-xs">
                                            <i class="bi bi-calendar2-week me-1"></i>Crédito
                                        </span>
                                        @if($sale->installments_total_count > 0)
                                            @php
                                                $allPaid = $sale->installments_paid_count >= $sale->installments_total_count;
                                                $pending = $sale->installments_total_count - $sale->installments_paid_count;
                                            @endphp
                                            @if($allPaid)
                                                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle fs-xs">
                                                    <i class="bi bi-check-all me-1"></i>Pagado
                                                </span>
                                            @else
                                                <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle fs-xs">
                                                    {{ $pending }} cuota{{ $pending !== 1 ? 's' : '' }} pendiente{{ $pending !== 1 ? 's' : '' }}
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                @else
                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle fs-xs">
                                        <i class="bi bi-cash-coin me-1"></i>Contado
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($sale->loan_collateral_id)
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-xs">Préstamo</span>
                                @elseif($sale->purchase_id)
                                    <span class="badge bg-info-subtle text-info border border-info-subtle fs-xs">Compra</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-xs">Inventario</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @php
                                    $saleStatusLabel = match($sale->status) {
                                        'completed' => 'Completada',
                                        'returned'  => 'Eliminada',
                                        'cancelled' => 'Cancelada',
                                        default     => ucfirst($sale->status),
                                    };
                                    $saleStatusBg = match($sale->status) {
                                        'completed' => 'success',
                                        'returned'  => 'warning',
                                        default     => 'dark',
                                    };
                                @endphp
                                <span class="badge bg-{{ $saleStatusBg }}">{{ $saleStatusLabel }}</span>
                            </td>
                            <td class="text-end pe-3">{{ $sale->sale_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="text-center pe-3" onclick="event.stopPropagation()">
                                @if($sale->status === 'completed')
                                    @php $saleCanDeleteNow = $canDeleteSale || in_array($sale->id, $grantedSaleIds ?? []); @endphp
                                    @if($saleCanDeleteNow || $canDelegate)
                                    <x-icon-btn icon="trash3" variant="danger" :label="$saleCanDeleteNow ? 'Eliminar venta' : 'Autorizar eliminación'"
                                        data-bs-toggle="modal"
                                        data-bs-target="#saleReturnModal"
                                        data-sale-id="{{ $sale->id }}"
                                        data-sale-code="VE-{{ str_pad((string) $sale->numero, 5, '0', STR_PAD_LEFT) }}"
                                        data-sale-product="{{ $sale->product?->name ?? '-' }}"
                                        data-sale-client="{{ $sale->client?->name ?? 'Sin cliente' }}"
                                        data-sale-amount="{{ number_format((float) $sale->amount, 2) }}"
                                        data-sale-type="{{ $sale->payment_type }}"
                                        data-can-delete="{{ $saleCanDeleteNow ? 1 : 0 }}"
                                        data-return-url="{{ route('sales.return', $sale) }}"
                                        data-grant-url="{{ route('sales.delete-grant.store', $sale) }}" />
                                    @endif
                                @elseif($sale->status === 'returned')
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-xs">
                                        <i class="bi bi-check2 me-1"></i>Eliminada
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                No hay ventas registradas
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($sales->hasPages())
    <div class="d-flex justify-content-center">
        {{ $sales->links() }}
    </div>
@endif

{{-- ── Modal: Devolución de Venta ─────────────────────────────────── --}}
<div class="modal fade" id="saleReturnModal" tabindex="-1" aria-labelledby="saleReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius:20px">

            {{-- Header --}}
            <div class="modal-header border-0 pb-0 px-4 pt-4" style="background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%)">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div style="width:46px;height:46px;border-radius:14px;background:#fde68a;color:#92400e;display:flex;align-items:center;justify-content:center;font-size:var(--fs-lg);flex-shrink:0;">
                        <i class="bi bi-arrow-return-left"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold fs-md">Eliminar Venta</div>
                        <div class="small text-warning-emphasis" id="srm-subtitle">Revertirá inventario y caja</div>
                    </div>
                    <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="modal"></button>
                </div>
                {{-- Sale summary card --}}
                <div class="w-100 mt-3 mb-1 p-3 rounded-3" style="background:rgba(255,255,255,.65);border:1px solid #fde68a;">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <div style="color:#92400e;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Código</div>
                            <div class="fw-bold" id="srm-code">—</div>
                        </div>
                        <div class="col-6">
                            <div style="color:#92400e;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Monto</div>
                            <div class="fw-bold text-danger" id="srm-amount">—</div>
                        </div>
                        <div class="col-6">
                            <div style="color:#92400e;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Producto</div>
                            <div class="fw-semibold text-truncate" id="srm-product">—</div>
                        </div>
                        <div class="col-6">
                            <div style="color:#92400e;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Cliente</div>
                            <div class="fw-semibold text-truncate" id="srm-client">—</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Body --}}
            <div class="modal-body px-4 pt-3 pb-4">

                {{-- Pestañas (se muestran/ocultan por JS según los permisos de la venta) --}}
                <ul class="nav nav-pills nav-fill mb-3 gap-2" id="srm-tabs" role="tablist">
                    <li class="nav-item" role="presentation" id="srm-tab-self-li">
                        <button class="nav-link active" id="srm-tab-self-btn" data-bs-toggle="pill" data-bs-target="#srm-tab-self" type="button" role="tab">
                            <i class="bi bi-trash3 me-1"></i> Eliminar yo mismo
                        </button>
                    </li>
                    @if($canDelegate)
                    <li class="nav-item" role="presentation" id="srm-tab-grant-li">
                        <button class="nav-link" id="srm-tab-grant-btn" data-bs-toggle="pill" data-bs-target="#srm-tab-grant" type="button" role="tab">
                            <i class="bi bi-person-check me-1"></i> Autorizar a otro
                        </button>
                    </li>
                    @endif
                </ul>

                <div class="tab-content">
                    {{-- Tab 1: eliminar yo mismo --}}
                    <div class="tab-pane fade show active" id="srm-tab-self" role="tabpanel">
                        <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:#fff7ed;border:1px solid #fed7aa;font-size:var(--fs-sm);color:#9a3412;">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1 fs-sm"></i>
                            <div>
                                <strong>Esta acción no se puede deshacer.</strong> Se reingresará el producto al inventario y se generará un egreso en caja por el monto cobrado.
                            </div>
                        </div>

                        {{-- Credit notice (hidden by default) --}}
                        <div id="srm-credit-notice" class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3 d-none" style="background:#eff6ff;border:1px solid #bfdbfe;font-size:var(--fs-sm);color:#1e40af;">
                            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1 fs-sm"></i>
                            <div>Venta al <strong>Crédito</strong>: solo se revertirán las cuotas <strong>ya cobradas</strong>. Las cuotas pendientes serán anuladas.</div>
                        </div>

                        <form id="saleReturnForm" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Motivo de eliminación <span class="text-danger">*</span></label>
                                <select name="return_reason" id="srm-reason" class="form-select form-select-sm" required>
                                    <option value="">Seleccionar motivo...</option>
                                    @foreach(\App\Models\Sale::RETURN_REASONS as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Observaciones <span class="text-muted fw-normal">(opcional)</span></label>
                                <textarea name="return_notes" class="form-control form-control-sm" rows="2" placeholder="Detalles adicionales sobre la devolución..."></textarea>
                            </div>
                            <div class="d-grid">
                                <x-btn variant="danger" type="submit" icon="trash3" size="md" :block="true">Confirmar eliminación</x-btn>
                            </div>
                        </form>
                    </div>

                    {{-- Tab 2: autorizar a otro --}}
                    @if($canDelegate)
                    <div class="tab-pane fade" id="srm-tab-grant" role="tabpanel">
                        <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:#eff6ff;border:1px solid #bfdbfe;font-size:var(--fs-sm);color:#1e40af;">
                            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                            <div>El usuario podrá eliminar <strong>solo esta venta</strong>, <strong>una única vez</strong>. La autorización <strong>vence en 24 horas</strong>.</div>
                        </div>
                        <form id="saleGrantForm" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Autorizar a <span class="text-danger">*</span></label>
                                <select name="personal_id" class="form-select form-select-sm" required>
                                    <option value="">Seleccionar personal...</option>
                                    @foreach($personals as $p)
                                        @if($p->user_id !== auth()->id())
                                            <option value="{{ $p->id }}">{{ $p->full_name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Motivo <span class="text-muted fw-normal">(opcional)</span></label>
                                <input type="text" name="reason" class="form-control form-control-sm" maxlength="500" placeholder="Ej. lo registró por error">
                            </div>
                            <div class="d-grid">
                                <x-btn type="submit" icon="person-check" size="md" :block="true">Autorizar por 24 horas</x-btn>
                            </div>
                        </form>
                    </div>
                    @endif
                </div>

                <x-btn variant="secondary" size="md" :block="true" class="mt-3" data-bs-dismiss="modal">Cancelar</x-btn>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('saleReturnModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        document.getElementById('srm-code').textContent    = btn.dataset.saleCode;
        document.getElementById('srm-amount').textContent  = '{{ $currencySymbol }} ' + btn.dataset.saleAmount;
        document.getElementById('srm-product').textContent = btn.dataset.saleProduct;
        document.getElementById('srm-client').textContent  = btn.dataset.saleClient;
        document.getElementById('saleReturnForm').action   = btn.dataset.returnUrl;
        const isCredit = btn.dataset.saleType === 'CREDITO';
        document.getElementById('srm-credit-notice').classList.toggle('d-none', !isCredit);
        document.getElementById('srm-subtitle').textContent = isCredit
            ? 'Revertirá cuotas cobradas e inventario'
            : 'Revertirá inventario y caja';
        document.getElementById('srm-reason').value = '';
        modal.querySelector('textarea[name="return_notes"]').value = '';

        // Autorizar a otro: fijar la URL de esta venta y limpiar el form.
        const grantForm = document.getElementById('saleGrantForm');
        if (grantForm) {
            grantForm.action = btn.dataset.grantUrl || '';
            grantForm.reset();
        }

        // Si el usuario NO puede eliminar esta venta (solo autorizar),
        // se oculta la pestaña de eliminar y se abre la de autorizar.
        const canDelete = btn.dataset.canDelete === '1';
        const selfLi  = document.getElementById('srm-tab-self-li');
        const grantLi = document.getElementById('srm-tab-grant-li');

        if (selfLi) selfLi.classList.toggle('d-none', !canDelete);

        if (!canDelete && grantLi) {
            bootstrap.Tab.getOrCreateInstance(document.getElementById('srm-tab-grant-btn')).show();
        } else if (canDelete) {
            bootstrap.Tab.getOrCreateInstance(document.getElementById('srm-tab-self-btn')).show();
        }

        // Sin pestaña de autorizar, la barra sobra.
        const tabs = document.getElementById('srm-tabs');
        if (tabs) tabs.classList.toggle('d-none', !grantLi);
    });
});
</script>
@endpush

@push('styles')
<style>
</style>
@endpush
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
