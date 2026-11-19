@extends('layouts.app')

@section('title', 'Venta VE-' . str_pad((string) $sale->numero, 5, '0', STR_PAD_LEFT))

@push('styles')
<style>
    .sale-stat { border-left: 3px solid; padding-left: 12px; }
    .sale-stat.primary { border-color: #0d6efd; }
    .sale-stat.success { border-color: #198754; }
    .sale-stat.info    { border-color: #0dcaf0; }
    .sale-stat.warning { border-color: #ffc107; }
    .sale-stat.danger  { border-color: #dc3545; }
    .sale-stat .stat-label { font-size:var(--fs-sm); text-transform: uppercase; letter-spacing: .03em; color: #6c757d; }
    .sale-stat .stat-value { font-size:var(--fs-lg); font-weight: 700; }

    .sale-gallery-thumb {
        width: 80px; height: 80px; object-fit: cover;
        border-radius: 8px; border: 2px solid #e9ecef;
        cursor: pointer; transition: border-color .2s;
    }
    .sale-gallery-thumb:hover { border-color: #0d6efd; }

    .client-avatar {
        width: 62px;
        height: 62px;
        border-radius: 18px;
        overflow: hidden;
        background: #eef5ff;
        border: 1px solid rgba(13, 110, 253, .12);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .client-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .client-avatar i {
        font-size:var(--fs-lg);
        color: #0d6efd;
    }

    .sale-origin-card {
        border: 1px solid #e3edf9;
        border-radius: 12px;
        padding: .75rem 1rem;
        background: linear-gradient(145deg, #f8fbff 0%, #f1f7ff 100%);
    }
</style>
@endpush

@section('page')
@php
    $statusColors = ['completed' => 'success', 'cancelled' => 'dark', 'returned' => 'warning'];
    $statusLabels = ['completed' => 'Completada', 'cancelled' => 'Cancelada', 'returned' => 'Eliminada'];
    $categoryColors = [1 => 'primary', 2 => 'warning', 3 => 'info', 4 => 'success'];
    $categoryIcons  = [1 => 'bi-truck', 2 => 'bi-gem', 3 => 'bi-fire', 4 => 'bi-box-seam'];
    $canDeleteSale = auth()->user()->is_super_admin
        || auth()->user()->hasPermissionInCompany('sales.delete', auth()->user()->getCurrentCompany());
    // Puede autorizar a otro usuario a eliminar esta venta (permiso dedicado).
    $canDelegate = auth()->user()->is_super_admin
        || auth()->user()->hasPermissionInCompany('sales.delegate_delete', auth()->user()->getCurrentCompany());
    // Puede eliminar YA: por su cargo o por una autorización puntual vigente.
    $canDeleteNow = $canDeleteSale || (bool) ($myGrant ?? null);
@endphp

{{-- Header --}}
<x-page-header :title="'Venta VE-' . str_pad((string) $sale->numero, 5, '0', STR_PAD_LEFT)" icon="cart-check">
    <x-slot:meta>
        <span class="badge bg-{{ $statusColors[$sale->status] ?? 'secondary' }}">
            {{ $statusLabels[$sale->status] ?? ucfirst($sale->status) }}
        </span>
    </x-slot:meta>
    <x-btn variant="success" icon="printer-fill" onclick="printSaleReceipt()" title="80 mm · térmico">Imprimir recibo</x-btn>
    @if($sale->status === 'completed')
        @if($canDeleteNow || $canDelegate)
            <x-btn variant="outline-danger" icon="trash3" data-bs-toggle="modal" data-bs-target="#saleDeleteModal">{{ $canDeleteNow ? 'Eliminar venta' : 'Autorizar eliminación' }}</x-btn>
        @endif
    @elseif($sale->status === 'returned')
        <span class="badge bg-warning-subtle text-warning border border-warning-subtle align-self-center px-3 py-2">
            <i class="bi bi-trash3 me-1"></i> Venta eliminada
        </span>
    @endif
    <x-btn variant="secondary" icon="arrow-left" :href="route('sales.index')">Volver</x-btn>
</x-page-header>

{{-- Flash: venta recién registrada --}}
@if(session('success'))
<div class="alert border-0 mb-4 d-flex align-items-center gap-3 p-3" style="background:linear-gradient(135deg,#f0fdf4 0%,#dcfce7 100%);border-radius:14px;border-left:4px solid #22c55e !important;">
    <div style="width:40px;height:40px;border-radius:12px;background:#22c55e;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <i class="bi bi-check-lg text-white fs-5"></i>
    </div>
    <div class="flex-grow-1">
        <div class="fw-bold text-success fs-base">{{ session('success') }}</div>
        <div class="small text-success opacity-75">Puedes imprimir el comprobante ahora o en cualquier momento.</div>
    </div>
    <x-btn variant="success" icon="printer-fill" onclick="printSaleReceipt()">Imprimir ahora</x-btn>
</div>
@endif

{{-- Stats bar --}}
<div class="row g-3 mb-4">
    <div class="col-md col-6">
        <div class="sale-stat primary">
            <div class="stat-label">Precio de venta</div>
            <div class="stat-value text-primary">{{ $currencySymbol }} {{ number_format((float) $sale->amount, 2) }}</div>
        </div>
    </div>
    <div class="col-md col-6">
        <div class="sale-stat info">
            <div class="stat-label">Costo original</div>
            <div class="stat-value text-info">{{ $currencySymbol }} {{ number_format((float) $sale->cost, 2) }}</div>
        </div>
    </div>
    <div class="col-md col-6">
        <div class="sale-stat {{ (float) $sale->profit >= 0 ? 'success' : 'danger' }}">
            <div class="stat-label">Ganancia</div>
            <div class="stat-value {{ (float) $sale->profit >= 0 ? 'text-success' : 'text-danger' }}">{{ $currencySymbol }} {{ number_format((float) $sale->profit, 2) }}</div>
        </div>
    </div>
    <div class="col-md col-6">
        <div class="sale-stat warning">
            <div class="stat-label">Fecha de venta</div>
            <div class="stat-value text-warning">{{ $sale->sale_date?->format('d/m/Y') ?? '-' }}</div>
        </div>
    </div>
    <div class="col-md col-6">
        <div class="sale-stat {{ $sale->isCredit() ? 'primary' : 'success' }}">
            <div class="stat-label">Tipo de pago</div>
            <div class="stat-value text-{{ $sale->isCredit() ? 'primary' : 'success' }}">
                {{ $sale->paymentLabel() }}
            </div>
        </div>
    </div>
</div>

{{-- Detail cards --}}
<div class="row g-4 mb-4">
    {{-- Client info --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-person-fill text-primary me-1"></i> Comprador</h6>
                @if($sale->client)
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="client-avatar bg-light rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                            @if($sale->client->photo_url)
                                <img src="{{ $sale->client->photo_url }}" alt="{{ $sale->client->name }}" class="client-avatar-img">
                            @else
                                <i class="bi bi-person-fill text-primary"></i>
                            @endif
                        </div>
                        <div>
                            <div class="mb-1"><strong>{{ $sale->client->name }}</strong></div>
                            <div class="text-muted small">{{ $sale->client->email ?? $sale->client->phone ?? 'Cliente registrado' }}</div>
                        </div>
                    </div>
                    <div class="small text-muted mb-1"><i class="bi bi-card-text me-1"></i> {{ $sale->client->id_number ?? '-' }}</div>
                    <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i> {{ $sale->client->phone ?? '-' }}</div>
                    <div class="small text-muted mb-1"><i class="bi bi-envelope me-1"></i> {{ $sale->client->email ?? '-' }}</div>
                    <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i> {{ $sale->client->address ?? '-' }}</div>
                @else
                    <p class="text-muted small mb-0">Venta sin comprador registrado.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Product details --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-box-seam text-success me-1"></i> Producto</h6>
                <div class="row g-2 small">
                    <div class="col-5"><span class="text-muted">Nombre:</span></div>
                    <div class="col-7 fw-semibold">{{ $sale->product?->name ?? '-' }}</div>
                    @if($sale->product?->code)
                        <div class="col-5"><span class="text-muted">Código:</span></div>
                        <div class="col-7 fw-semibold">{{ $sale->product->code }}</div>
                    @endif

                    @if($sale->product?->brand)
                        <div class="col-5"><span class="text-muted">Marca:</span></div>
                        <div class="col-7 fw-semibold">{{ $sale->product->brand }}</div>
                    @endif
                    @if($sale->product?->model_year)
                        <div class="col-5"><span class="text-muted">Modelo (año):</span></div>
                        <div class="col-7 fw-semibold">{{ $sale->product->model_year }}</div>
                    @endif
                    @if($sale->product?->plate)
                        <div class="col-5"><span class="text-muted">Placa:</span></div>
                        <div class="col-7 fw-semibold">{{ $sale->product->plate }}</div>
                    @endif
                    @if($sale->product?->chassis)
                        <div class="col-5"><span class="text-muted">Chasis:</span></div>
                        <div class="col-7 fw-semibold">{{ $sale->product->chassis }}</div>
                    @endif
                    @if($sale->product?->serial_number)
                        <div class="col-5"><span class="text-muted">Serial:</span></div>
                        <div class="col-7 fw-semibold">{{ $sale->product->serial_number }}</div>
                    @endif
                    @if($sale->product?->item_type)
                        <div class="col-5"><span class="text-muted">Tipo:</span></div>
                        <div class="col-7 fw-semibold">{{ $sale->product->item_type }}</div>
                    @endif
                    @if($sale->product?->karat)
                        <div class="col-5"><span class="text-muted">Kilataje:</span></div>
                        <div class="col-7 fw-semibold">{{ $sale->product->karat }}</div>
                    @endif

                    @if($sale->creditCategory)
                        <div class="col-5"><span class="text-muted">Categoría:</span></div>
                        <div class="col-7 fw-semibold">
                            <i class="bi {{ $categoryIcons[(int) $sale->credit_category_id] ?? 'bi-tag' }} me-1"></i>
                            {{ $sale->creditCategory->name }}
                        </div>
                    @endif

                    @if($sale->product?->description)
                        <div class="col-12 mt-2">
                            <span class="text-muted d-block mb-1">Detalles:</span>
                            <div class="bg-light rounded p-2 small">{{ $sale->product->description }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Sale info --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-info-circle text-warning me-1"></i> Información</h6>
                <div class="row g-2 small">
                    <div class="col-5"><span class="text-muted">Sucursal:</span></div>
                    <div class="col-7 fw-semibold">{{ $sale->branch?->name ?? '-' }}</div>
                    <div class="col-5"><span class="text-muted">Almacén:</span></div>
                    <div class="col-7 fw-semibold">{{ $sale->warehouse?->name ?? '-' }}</div>
                    <div class="col-5"><span class="text-muted">Creado por:</span></div>
                    <div class="col-7 fw-semibold">{{ $sale->createdBy?->name ?? '-' }}</div>
                    <div class="col-5"><span class="text-muted">Fecha registro:</span></div>
                    <div class="col-7 fw-semibold">{{ $sale->created_at?->format('d/m/Y H:i') }}</div>

                    @if($sale->cashRegister)
                        <div class="col-5"><span class="text-muted">Caja:</span></div>
                        <div class="col-7 fw-semibold">{{ $sale->cashRegister->name }}</div>
                    @endif
                    @if($sale->cashRegisterSession)
                        <div class="col-5"><span class="text-muted">Sesión:</span></div>
                        <div class="col-7 fw-semibold">#{{ $sale->cashRegisterSession->id }}</div>
                    @endif

                    @if($sale->notes)
                        <div class="col-12 mt-2">
                            <span class="text-muted d-block mb-1">Notas:</span>
                            <div class="bg-light rounded p-2 small">{{ $sale->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* ── Installment plan card ── */
    .si-header {
        background: linear-gradient(135deg, #f0f5ff 0%, #e8f0ff 100%);
        border-radius: 14px 14px 0 0;
        padding: 1rem 1.25rem .85rem;
        border-bottom: 1px solid #dce7ff;
    }
    .si-progress-track {
        height: 8px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .si-progress-bar {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%);
        transition: width .5s ease;
    }
    .si-row {
        transition: background .15s ease;
    }
    .si-row:hover {
        background: rgba(13, 110, 253, .03);
    }
    .si-row.is-paid {
        background: rgba(25, 135, 84, .04);
    }
    .si-row.is-overdue {
        background: rgba(220, 53, 69, .04);
    }
    .si-badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .3rem .65rem;
        border-radius: 999px;
        font-size:var(--fs-xs);
        font-weight: 700;
        white-space: nowrap;
    }
    /* modal confirm */
    .si-modal-amount {
        font-size:var(--fs-xl);
        font-weight: 800;
        color: #16a34a;
        letter-spacing: -.01em;
    }
    .si-modal-meta {
        background: #f8fbff;
        border: 1px solid #dce7ff;
        border-radius: 10px;
        padding: .65rem .9rem;
    }
</style>
@endpush

@if($sale->isCredit())
@php
    $totalInstallments = $sale->installments->count();
    $paidInstallments  = $sale->installments->where('status', \App\Models\SaleInstallment::STATUS_PAGADA)->count();
    $progressPct       = $totalInstallments > 0 ? round($paidInstallments / $totalInstallments * 100) : 0;
    $totalAmount       = $sale->installments->sum('amount');
    $paidAmount        = $sale->installments->where('status', \App\Models\SaleInstallment::STATUS_PAGADA)->sum('amount');
    $pendingAmount     = $totalAmount - $paidAmount;
    $allPaid           = $paidInstallments >= $totalInstallments && $totalInstallments > 0;

    // Liquidación anticipada
    $unpaidInstallments   = $sale->installments->whereIn('status', [
        \App\Models\SaleInstallment::STATUS_PENDIENTE,
        \App\Models\SaleInstallment::STATUS_VENCIDA,
    ]);
    $hasUnpaid            = $unpaidInstallments->isNotEmpty();
    $withBreakdown        = $unpaidInstallments->filter(fn($i) => (float) $i->principal_amount > 0);
    $withoutBreakdown     = $unpaidInstallments->filter(fn($i) => (float) $i->principal_amount == 0);
    $liquidationPrincipal = (float) $withBreakdown->sum('principal_amount') + (float) $withoutBreakdown->sum('amount');
    $dueForInterest       = $withBreakdown->filter(
        fn($i) => $i->status === \App\Models\SaleInstallment::STATUS_VENCIDA
               || ($i->due_date && $i->due_date->lte(now()->startOfDay()))
    );
    $liquidationInterest  = (float) $dueForInterest->sum('interest_amount');
    $liquidationTotal     = round($liquidationPrincipal + $liquidationInterest, 2);
@endphp
<div class="card border-0 shadow-sm mb-4 overflow-hidden">

    {{-- Header con progreso --}}
    <div class="si-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h6 class="fw-bold mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-calendar2-week text-primary"></i>
                    Plan de cuotas
                    @if($allPaid)
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-xs">
                            <i class="bi bi-check-all me-1"></i>Completado
                        </span>
                    @endif
                </h6>
                <div class="small text-muted d-flex flex-wrap gap-3">
                    <span><i class="bi bi-list-check me-1"></i>{{ $paidInstallments }}/{{ $totalInstallments }} cuotas pagadas</span>
                    <span><i class="bi bi-cash me-1 text-success"></i><strong class="text-success">{{ $currencySymbol }} {{ number_format($paidAmount, 2) }}</strong> cobrado</span>
                    @if($pendingAmount > 0)
                        <span><i class="bi bi-hourglass-split me-1 text-warning"></i><strong class="text-warning">{{ $currencySymbol }} {{ number_format($pendingAmount, 2) }}</strong> pendiente</span>
                    @endif
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                @if($hasUnpaid && !$allPaid && $sale->status === \App\Models\Sale::STATUS_COMPLETED)
                <x-btn variant="success" icon="lightning-charge-fill" data-bs-toggle="modal" data-bs-target="#siLiquidateModal">Liquidar · {{ $currencySymbol }} {{ number_format($liquidationTotal, 0) }}</x-btn>
                @endif
                <div class="text-end" style="min-width:72px;">
                    <div class="fw-bold fs-5 {{ $allPaid ? 'text-success' : 'text-primary' }}">{{ $progressPct }}%</div>
                    <div class="small text-muted">cobrado</div>
                </div>
            </div>
        </div>
        <div class="si-progress-track mt-3">
            <div class="si-progress-bar" style="width: {{ $progressPct }}%"></div>
        </div>
    </div>

    {{-- Tabla de cuotas --}}
    @if($sale->installments->isNotEmpty())
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr class="border-bottom" style="background:#fafbfd;">
                    <th class="ps-4 py-2 small text-muted fw-semibold" style="width:48px;">#</th>
                    <th class="py-2 small text-muted fw-semibold">Vencimiento</th>
                    <th class="py-2 text-end small text-muted fw-semibold">Monto</th>
                    <th class="py-2 text-end small text-muted fw-semibold" style="width:80px;">%</th>
                    <th class="py-2 text-end small fw-semibold" style="color:#0d6efd;">Principal</th>
                    <th class="py-2 text-end small fw-semibold" style="color:#dc3545;">Interés</th>
                    <th class="py-2 text-center small text-muted fw-semibold">Estado</th>
                    <th class="py-2 text-center small text-muted fw-semibold">Pagado el</th>
                    <th class="pe-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->installments as $installment)
                @php
                    $isPaid    = $installment->status === \App\Models\SaleInstallment::STATUS_PAGADA;
                    $isOverdue = $installment->status === \App\Models\SaleInstallment::STATUS_VENCIDA;
                    $isDue     = !$isPaid && $installment->due_date && $installment->due_date->isPast();
                @endphp
                <tr class="si-row border-bottom {{ $isPaid ? 'is-paid' : ($isOverdue ? 'is-overdue' : '') }}">
                    <td class="ps-4">
                        @if($isPaid)
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success text-white fw-bold" style="width:26px;height:26px;font-size:var(--fs-xs);">
                                <i class="bi bi-check2"></i>
                            </span>
                        @elseif($isOverdue)
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger text-white fw-bold" style="width:26px;height:26px;font-size:var(--fs-xs);">
                                <i class="bi bi-exclamation"></i>
                            </span>
                        @else
                            <span class="d-inline-flex align-items-center justify-content-center rounded-circle border fw-bold text-secondary" style="width:26px;height:26px;font-size:var(--fs-xs);">
                                {{ $installment->installment_no }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="fw-semibold small">{{ $installment->due_date?->format('d/m/Y') ?? '-' }}</div>
                        @if(!$isPaid && $isDue)
                            <div class="text-danger fs-xs"><i class="bi bi-alarm me-1"></i>Vencida hace {{ $installment->due_date->diffForHumans() }}</div>
                        @elseif(!$isPaid && $installment->due_date)
                            <div class="text-muted fs-xs"><i class="bi bi-clock me-1"></i>{{ $installment->due_date->diffForHumans() }}</div>
                        @endif
                    </td>
                    <td class="text-end">
                        <span class="fw-bold {{ $isPaid ? 'text-success' : '' }}">{{ $currencySymbol }} {{ number_format((float) $installment->amount, 2) }}</span>
                    </td>
                    <td class="text-end small text-muted">{{ number_format((float) $installment->percentage, 1) }}%</td>
                    @php $hasBkd = (float)$installment->principal_amount > 0; @endphp
                    <td class="text-end small" style="color:#0d6efd;">
                        @if($hasBkd)
                            {{ $currencySymbol }} {{ number_format((float) $installment->principal_amount, 2) }}
                        @else
                            <span class="opacity-40">—</span>
                        @endif
                    </td>
                    <td class="text-end small" style="color:{{ $isOverdue || (!$isPaid && $installment->due_date && $installment->due_date->lte(now())) ? '#dc3545' : '#6c757d' }};">
                        @if($hasBkd && (float)$installment->interest_amount > 0)
                            {{ $currencySymbol }} {{ number_format((float) $installment->interest_amount, 2) }}
                        @else
                            <span class="opacity-40">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="si-badge
                            @if($isPaid) bg-success-subtle text-success border border-success-subtle
                            @elseif($isOverdue) bg-danger-subtle text-danger border border-danger-subtle
                            @else bg-secondary-subtle text-secondary border border-secondary-subtle
                            @endif">
                            @if($isPaid)<i class="bi bi-check-circle-fill"></i>
                            @elseif($isOverdue)<i class="bi bi-exclamation-circle-fill"></i>
                            @else<i class="bi bi-clock"></i>
                            @endif
                            {{ $installment->statusLabel() }}
                        </span>
                    </td>
                    <td class="text-center small">
                        @if($isPaid)
                            <div class="text-success fw-semibold">{{ $installment->paid_at?->format('d/m/Y') }}</div>
                            <div class="text-muted fs-xs">{{ $installment->paid_at?->format('H:i') }}</div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="pe-4 text-end">
                        @if(!$isPaid)
                            <x-btn variant="success" icon="cash-coin"
                                data-bs-toggle="modal"
                                data-bs-target="#siPayModal"
                                data-installment-id="{{ $installment->id }}"
                                data-installment-no="{{ $installment->installment_no }}"
                                data-installment-total="{{ $totalInstallments }}"
                                data-installment-amount="{{ number_format((float) $installment->amount, 2) }}"
                                data-installment-due="{{ $installment->due_date?->format('d/m/Y') ?? '-' }}"
                                data-pay-url="{{ route('sales.installments.pay', $installment) }}">Pagar</x-btn>
                        @else
                            <span class="small text-success opacity-75">
                                <i class="bi bi-check2-circle"></i> Pagada
                            </span>
                        @endif
                    </td>
                </tr>
                @if($isPaid && $installment->payment_notes)
                <tr class="is-paid border-bottom" style="background:rgba(25,135,84,.04);">
                    <td colspan="9" class="ps-5 py-1">
                        <small class="text-muted fst-italic"><i class="bi bi-chat-left-text me-1"></i>{{ $installment->payment_notes }}</small>
                    </td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <div class="p-4 text-muted small">No hay cuotas registradas para esta venta.</div>
    @endif
</div>

{{-- Payment modal --}}
<div class="modal fade" id="siPayModal" tabindex="-1" aria-labelledby="siPayModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-4 px-4">
                <div>
                    <p class="text-muted small mb-1 text-uppercase fw-bold" style="letter-spacing:.05em; font-size:var(--fs-xs);">Confirmar pago</p>
                    <h5 class="modal-title fw-bold" id="siPayModalLabel">Cuota <span id="siModal-no">—</span></h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body px-4 pt-3 pb-4">

                {{-- Amount highlight --}}
                <div class="text-center mb-4">
                    <div class="text-muted small mb-1">Monto a cobrar</div>
                    <div class="si-modal-amount" id="siModal-amount">$ —</div>
                </div>

                {{-- Meta info --}}
                <div class="si-modal-meta mb-4 d-flex flex-column gap-2 small">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Vencimiento</span>
                        <span class="fw-semibold" id="siModal-due">—</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Cuota</span>
                        <span class="fw-semibold" id="siModal-fraction">—</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">Fecha de pago</span>
                        <span class="fw-semibold text-primary">{{ now()->format('d/m/Y H:i') }}</span>
                    </div>
                </div>

                {{-- Form --}}
                <form id="siPayForm" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Notas del pago <span class="text-muted fw-normal">(opcional)</span></label>
                        <textarea name="payment_notes" rows="2" class="form-control form-control-sm rounded-3"
                            placeholder="Ej: Pagado con transferencia, referencia #123..."></textarea>
                    </div>
                    <div class="d-grid">
                        <x-btn variant="success" type="submit" icon="check-circle" size="md" :block="true">Confirmar pago</x-btn>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('siPayModal');
    if (!modal) return;

    modal.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('siModal-no').textContent       = btn.dataset.installmentNo + ' de ' + btn.dataset.installmentTotal;
        document.getElementById('siModal-amount').textContent   = '{{ $currencySymbol }} ' + btn.dataset.installmentAmount;
        document.getElementById('siModal-due').textContent      = btn.dataset.installmentDue;
        document.getElementById('siModal-fraction').textContent = btn.dataset.installmentNo + ' / ' + btn.dataset.installmentTotal;
        document.getElementById('siPayForm').action             = btn.dataset.payUrl;
    });
});
</script>
@endpush

@if($hasUnpaid && !$allPaid && $sale->status === \App\Models\Sale::STATUS_COMPLETED)
{{-- ── Liquidation modal ───────────────────────────── --}}
<div class="modal fade" id="siLiquidateModal" tabindex="-1" aria-labelledby="siLiquidateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:500px;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

            {{-- Header --}}
            <div class="modal-header border-0 px-4 pt-4 pb-3"
                style="background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%);">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div style="width:44px;height:44px;border-radius:14px;background:#fbbf24;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="bi bi-lightning-charge-fill text-white fs-lg"></i>
                    </div>
                    <div>
                        <p class="mb-0 text-uppercase fw-bold" style="font-size:var(--fs-xs);letter-spacing:.06em;color:#92400e;">Cancelación anticipada</p>
                        <h5 class="mb-0 fw-bold" id="siLiquidateModalLabel" style="color:#1c1917;">Liquidar saldo pendiente</h5>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
            </div>

            <div class="modal-body px-4 pt-3 pb-4">

                {{-- Desglose financiero --}}
                <div class="rounded-3 overflow-hidden border mb-3">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr style="background:#f8fafc;">
                                <td class="py-2 px-3 small">
                                    <i class="bi bi-cash me-1 text-primary"></i>
                                    <strong>Principal</strong>
                                    <span class="text-muted ms-1">(todas las cuotas)</span>
                                </td>
                                <td class="py-2 px-3 text-end fw-bold" style="color:#0d6efd;">
                                    {{ $currencySymbol }} {{ number_format($liquidationPrincipal, 2) }}
                                </td>
                            </tr>
                            <tr>
                                <td class="py-2 px-3 small">
                                    <i class="bi bi-percent me-1 text-danger"></i>
                                    <strong>Interés</strong>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1"
                                        style="font-size:var(--fs-xs);font-weight:600;">Solo vencidas / vigentes</span>
                                </td>
                                <td class="py-2 px-3 text-end fw-bold text-danger">
                                    + {{ $currencySymbol }} {{ number_format($liquidationInterest, 2) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);">
                                <td class="py-2 px-3 fw-bold">
                                    <i class="bi bi-check2-circle text-success me-1"></i>TOTAL A PAGAR
                                </td>
                                <td class="py-2 px-3 text-end fw-bold text-success" style="font-size:var(--fs-lg);letter-spacing:-.01em;">
                                    {{ $currencySymbol }} {{ number_format($liquidationTotal, 2) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Nota informativa --}}
                <div class="d-flex gap-2 align-items-start px-3 py-2 mb-3 rounded-3 small"
                    style="background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;font-size:var(--fs-sm);">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                    <span>Las cuotas <strong>futuras</strong> no generan interés en la liquidación. Solo se cobra interés sobre cuotas vencidas o con vencimiento hoy.</span>
                </div>

                {{-- Detalle por cuota --}}
                <div class="mb-4">
                    <div class="small fw-semibold text-muted mb-2 text-uppercase"
                        style="font-size:var(--fs-xs);letter-spacing:.06em;">Detalle por cuota</div>
                    @foreach($unpaidInstallments->sortBy('installment_no') as $uInst)
                    @php
                        $applyInterest = $uInst->status === \App\Models\SaleInstallment::STATUS_VENCIDA
                            || ($uInst->due_date && $uInst->due_date->lte(now()->startOfDay()));
                        $hasBkd2 = (float) $uInst->principal_amount > 0;
                    @endphp
                    <div class="d-flex align-items-center gap-2 py-2 border-bottom">
                        <span class="badge {{ $applyInterest ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }}"
                            style="font-size:var(--fs-xs);min-width:68px;text-align:center;">
                            @if($applyInterest)
                                <i class="bi bi-exclamation-circle me-1"></i>Con interés
                            @else
                                <i class="bi bi-clock me-1"></i>Sin interés
                            @endif
                        </span>
                        <div class="flex-grow-1 small">
                            <span class="fw-semibold">Cuota #{{ $uInst->installment_no }}</span>
                            <span class="text-muted ms-2">{{ $uInst->due_date?->format('d/m/Y') ?? '-' }}</span>
                        </div>
                        <div class="text-end small">
                            @if($hasBkd2)
                                <span style="color:#0d6efd;">{{ $currencySymbol }} {{ number_format((float)$uInst->principal_amount, 2) }}</span>
                                @if($applyInterest && (float)$uInst->interest_amount > 0)
                                    <span class="text-danger ms-1">+ {{ $currencySymbol }} {{ number_format((float)$uInst->interest_amount, 2) }}</span>
                                @endif
                            @else
                                <span class="text-muted">{{ $currencySymbol }} {{ number_format((float)$uInst->amount, 2) }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- Confirm form --}}
                <form action="{{ route('sales.liquidate', $sale) }}" method="POST">
                    @csrf
                    <div class="d-grid">
                        <x-btn variant="success" type="submit" icon="lightning-charge-fill" size="md" :block="true">Confirmar liquidación · {{ $currencySymbol }} {{ number_format($liquidationTotal, 2) }}</x-btn>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
@endif

@endif

{{-- Origin card --}}
@if($sale->loan_collateral_id || $sale->purchase_id)
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-link-45deg text-primary me-1"></i> Origen del producto</h6>
        <div class="sale-origin-card">
            @if($sale->loan_collateral_id && $sale->loanCollateral)
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2">
                        <i class="bi bi-cash-coin me-1"></i> Préstamo incumplido
                    </span>
                    <div class="small">
                        @if($sale->loanCollateral->loan)
                            Préstamo <strong>PR-{{ str_pad($sale->loanCollateral->loan->id, 5, '0', STR_PAD_LEFT) }}</strong>
                            · Cliente: <strong>{{ $sale->loanCollateral->loan->client?->name ?? '-' }}</strong>
                            <a href="{{ route('loans.show', $sale->loanCollateral->loan) }}" class="ms-2 text-decoration-none small">
                                <i class="bi bi-box-arrow-up-right"></i> Ver préstamo
                            </a>
                        @else
                            Garantía #{{ $sale->loan_collateral_id }}
                        @endif
                    </div>
                </div>
            @elseif($sale->purchase_id && $sale->purchase)
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-2">
                        <i class="bi bi-bag-check me-1"></i> Compra
                    </span>
                    <div class="small">
                        Compra <strong>CO-{{ str_pad($sale->purchase->id, 5, '0', STR_PAD_LEFT) }}</strong>
                        · Vendedor: <strong>{{ $sale->purchase->client?->name ?? '-' }}</strong>
                        <a href="{{ route('purchases.show', $sale->purchase) }}" class="ms-2 text-decoration-none small">
                            <i class="bi bi-box-arrow-up-right"></i> Ver compra
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endif

{{-- Image gallery --}}
@if($sale->images->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-images text-info me-1"></i> Fotos e Imágenes</h6>
        <div class="d-flex flex-wrap gap-2">
            @foreach($sale->images as $image)
                <a href="{{ asset('storage/' . $image->path) }}" target="_blank" title="{{ $image->original_name }}">
                    <img src="{{ asset('storage/' . $image->path) }}"
                        alt="{{ $image->original_name }}"
                        class="sale-gallery-thumb">
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- ── Thermal receipt print ──────────────────────── --}}
@push('scripts')
<script>
(function () {
    /* Data from server */
    const sale = {
        id:          '{{ str_pad($sale->numero, 5, '0', STR_PAD_LEFT) }}',
        date:        '{{ $sale->sale_date?->format('d/m/Y') ?? $sale->created_at?->format('d/m/Y') ?? '-' }}',
        time:        '{{ $sale->created_at?->format('H:i') ?? '' }}',
        paymentType: '{{ $sale->paymentLabel() }}',
        isCredit:    {{ $sale->isCredit() ? 'true' : 'false' }},
        amount:      {{ (float) $sale->amount }},
        notes:       {{ Js::from($sale->notes) }},
        client: {
            name:  {{ Js::from($sale->client?->name ?? 'Sin cliente') }},
            ci:    {{ Js::from($sale->client?->id_number ?? '') }},
            phone: {{ Js::from($sale->client?->phone ?? '') }},
        },
        product: {
            name:   {{ Js::from($sale->product?->name ?? '-') }},
            brand:  {{ Js::from($sale->product?->brand ?? '') }},
            model:  {{ Js::from($sale->product?->model_year ?? '') }},
            serial: {{ Js::from($sale->product?->serial_number ?? '') }},
        },
        branch:      {{ Js::from($sale->branch?->name ?? '') }},
        branchPhone: {{ Js::from($sale->branch?->phone ?? '') }},
        company:     {{ Js::from($sale->company?->name ?? '') }},
        createdBy: {{ Js::from($sale->createdBy?->name ?? '') }},
        downPayment:          {{ (float) ($sale->down_payment ?? 0) }},
        creditAdditionalPct:  {{ (float) ($sale->credit_additional_percentage ?? 0) }},
        creditAdditionalAmt:  {{ (float) ($sale->credit_additional_amount ?? 0) }},
        creditTotal:          {{ (float) ($sale->credit_additional_amount ?? 0) > 0 ? round((float)$sale->amount + (float)$sale->credit_additional_amount, 2) : (float)$sale->amount }},
        installments: @json($sale->isCredit() ? $sale->installments->map(fn($i) => ['no' => $i->installment_no, 'due' => $i->due_date?->format('d/m/Y') ?? '-', 'amount' => (float) $i->amount]) : []),
    };

    window.printSaleReceipt = function () {
        const fmt = n => '{{ $currencySymbol }} ' + Number(n).toLocaleString('es-BO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

        /* ── credit breakdown + installments block ── */
        let creditBlock = '';
        if (sale.isCredit) {
            const balance = Math.max(0, sale.creditTotal - sale.downPayment);

            // Desglose financiero
            const finRows = `
                <tr>
                    <td colspan="2" style="font-size:12px;padding:1.5px 0;">Precio base</td>
                    <td style="font-size:12px;padding:1.5px 0;text-align:right;">${fmt(sale.amount)}</td>
                </tr>
                ${sale.creditAdditionalAmt > 0 ? `
                <tr>
                    <td colspan="2" style="font-size:12px;padding:1.5px 0;">Recargo crédito (${sale.creditAdditionalPct}%)</td>
                    <td style="font-size:12px;padding:1.5px 0;text-align:right;">+ ${fmt(sale.creditAdditionalAmt)}</td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size:12px;padding:1.5px 0;font-weight:700;">Total financiado</td>
                    <td style="font-size:12px;padding:1.5px 0;text-align:right;font-weight:700;">${fmt(sale.creditTotal)}</td>
                </tr>` : ''}
                ${sale.downPayment > 0 ? `
                <tr>
                    <td colspan="2" style="font-size:12px;padding:1.5px 0;">Cuota inicial (al contado)</td>
                    <td style="font-size:12px;padding:1.5px 0;text-align:right;font-weight:700;">- ${fmt(sale.downPayment)}</td>
                </tr>
                <tr>
                    <td colspan="2" style="font-size:12px;padding:1.5px 0;font-weight:700;">Saldo en cuotas</td>
                    <td style="font-size:12px;padding:1.5px 0;text-align:right;font-weight:700;">${fmt(balance)}</td>
                </tr>` : ''}`;

            // Plan de cuotas
            const instRows = sale.installments.length ? `
                <tr><td colspan="3" style="padding:3px 0 2px;border-top:1px dashed #000;"></td></tr>
                <tr><td colspan="3" style="font-size:12px;font-weight:700;text-align:center;padding-bottom:2px;letter-spacing:.05em;">PLAN DE CUOTAS</td></tr>
                <tr>
                    <td style="font-size:12px;color:#555;padding-bottom:1px;">Cuota</td>
                    <td style="font-size:12px;color:#555;padding-bottom:1px;text-align:center;">Vencimiento</td>
                    <td style="font-size:12px;color:#555;padding-bottom:1px;text-align:right;">Monto</td>
                </tr>
                ${sale.installments.map(i => `
                    <tr>
                        <td style="font-size:12px;padding:1.5px 0;">Cuota ${esc(i.no)}</td>
                        <td style="font-size:12px;padding:1.5px 0;text-align:center;">${esc(i.due)}</td>
                        <td style="font-size:12px;padding:1.5px 0;text-align:right;">${fmt(i.amount)}</td>
                    </tr>`).join('')}` : '';

            creditBlock = `<table>${finRows}${instRows}</table><hr class="sep">`;
        }

        const html = `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Recibo VE-${esc(sale.id)}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; font-weight:900 !important; color:#000 !important; }
  @page { margin: 4mm 3mm; size: 80mm auto; }

  /* ── Pantalla: fondo gris, recibo centrado con sombra ── */
  @media screen {
    html { background: #e5e7eb; min-height: 100%; }
    body {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      color: #000;
      width: 76mm;
      margin: 24px auto;
      background: #fff;
      padding: 10px 10px 18px;
      box-shadow: 0 4px 24px rgba(0,0,0,.22);
      border-radius: 3px;
    }
  }

  /* ── Impresión: sin adornos ── */
  @media print {
    html, body { background: #fff !important; box-shadow: none !important; }
    body {
      font-family: 'Courier New', Courier, monospace;
      font-size: 12px;
      color: #000;
      width: 72mm;
      margin: 0;
      padding: 0;
    }
  }

  .center  { text-align: center; }
  .right   { text-align: right; }
  .bold    { font-weight: 700; }
  .sep     { border:none; border-top: 1px dashed #000; margin: 5px 0; }
  .sep-sol { border:none; border-top: 1px solid #000; margin: 5px 0; }
  .row     { display: flex; justify-content: space-between; margin: 1.5px 0; }
  .row .lbl{ color: #444; }
  .total-box {
    border: 1px solid #000;
    border-radius: 3px;
    padding: 5px 6px;
    margin: 6px 0;
    text-align: center;
  }
  .total-box .lbl { font-size: 12px; letter-spacing: .06em; text-transform: uppercase; }
  .total-box .amt { font-size: 22px; font-weight: 900; letter-spacing: -.01em; }
  table { width: 100%; border-collapse: collapse; }
  .sig-line { border-top: 1px solid #000; margin-top: 2px; width: 60%; display: inline-block; }
</style>
</head>
<body>

  {{-- Company / header --}}
  <div class="center bold" style="font-size:15px;letter-spacing:.04em;">${sale.company ? esc(sale.company).toUpperCase() : 'RECIBO DE VENTA'}</div>
  ${sale.branch ? `<div class="center" style="font-size:12px;margin-top:1px;">${esc(sale.branch)}</div>` : ''}
  ${sale.branchPhone ? `<div class="center" style="font-size:12px;">${esc(sale.branchPhone)}</div>` : ''}
  <hr class="sep-sol" style="margin-top:4px;">

  <div class="center bold" style="font-size:13px;letter-spacing:.08em;">RECIBO DE VENTA</div>
  <div class="center" style="font-size:12px;margin-top:1px;">N° VE-${esc(sale.id)}</div>
  <div class="center" style="font-size:12px;">${esc(sale.date)}${sale.time ? '  ' + esc(sale.time) : ''}</div>
  <hr class="sep">

  {{-- Client --}}
  <div class="bold" style="font-size:12px;letter-spacing:.05em;margin-bottom:2px;">CLIENTE</div>
  <div class="row"><span>${esc(sale.client.name)}</span></div>
  ${sale.client.ci    ? `<div class="row"><span class="lbl">CI/NIT:</span><span>${esc(sale.client.ci)}</span></div>` : ''}
  ${sale.client.phone ? `<div class="row"><span class="lbl">Tel:</span><span>${esc(sale.client.phone)}</span></div>` : ''}
  <hr class="sep">

  {{-- Product --}}
  <div class="bold" style="font-size:12px;letter-spacing:.05em;margin-bottom:2px;">PRODUCTO</div>
  <div style="font-size:12px;font-weight:700;">${esc(sale.product.name)}</div>
  ${sale.product.brand  ? `<div class="row"><span class="lbl">Marca:</span><span>${esc(sale.product.brand)}</span></div>` : ''}
  ${sale.product.model  ? `<div class="row"><span class="lbl">Modelo:</span><span>${esc(sale.product.model)}</span></div>` : ''}
  ${sale.product.serial ? `<div class="row"><span class="lbl">Serial:</span><span>${esc(sale.product.serial)}</span></div>` : ''}
  <hr class="sep">

  {{-- Total --}}
  <div class="total-box">
    <div class="lbl">${sale.isCredit ? 'TOTAL FINANCIADO' : 'TOTAL A PAGAR'}</div>
    <div class="amt">${fmt(sale.isCredit ? sale.creditTotal : sale.amount)}</div>
    <div style="font-size:12px;margin-top:2px;">Forma de pago: <strong>${esc(sale.paymentType)}</strong></div>
  </div>

  {{-- Desglose crédito + cuotas --}}
  ${creditBlock}

  {{-- Notes --}}
  ${sale.notes ? `<div class="row" style="margin-bottom:3px;"><span class="lbl" style="font-size:12px;">Notas:</span></div><div style="font-size:12px;">${esc(sale.notes)}</div><hr class="sep">` : ''}

  {{-- Footer --}}
  ${sale.createdBy ? `<div class="row"><span class="lbl">Atendido por:</span><span>${esc(sale.createdBy)}</span></div>` : ''}
  <hr class="sep" style="margin-top:8px;">
  <div class="center" style="font-size:12px;margin:4px 0 6px;">¡Gracias por su compra!</div>

  <div class="center" style="margin-top:10px;">
    <div class="sig-line"></div>
    <div style="font-size:12px;margin-top:2px;">Firma del cliente</div>
  </div>

</body>
</html>`;

        const win = window.open('', '_blank', 'width=760,height=680,toolbar=0,menubar=0,status=0,scrollbars=1');
        if (!win) { alert('Permite las ventanas emergentes para imprimir el recibo.'); return; }
        win.document.write(html);
        win.document.close();
        win.focus();
        setTimeout(function () { win.print(); }, 350);
    };
})();
</script>
@endpush

{{-- ── Modal: Eliminar Venta (reingreso a inventario + reversión de caja) ── --}}
@if($sale->status === 'completed' && ($canDeleteNow || $canDelegate))
<div class="modal fade" id="saleDeleteModal" tabindex="-1" aria-labelledby="saleDeleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius:20px">
            <div class="modal-header border-0 pb-0 px-4 pt-4" style="background:linear-gradient(135deg,#fff5f5 0%,#fee2e2 100%)">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div style="width:46px;height:46px;border-radius:14px;background:#fecaca;color:#991b1b;display:flex;align-items:center;justify-content:center;font-size:var(--fs-lg);flex-shrink:0;">
                        <i class="bi bi-trash3-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold fs-md">Eliminar Venta</div>
                        <div class="small text-danger">Revertirá inventario y caja</div>
                    </div>
                    <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="modal"></button>
                </div>
                <div class="w-100 mt-3 mb-1 p-3 rounded-3" style="background:rgba(255,255,255,.7);border:1px solid #fecaca;">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <div style="color:#991b1b;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Código</div>
                            <div class="fw-bold">VE-{{ str_pad((string) $sale->numero, 5, '0', STR_PAD_LEFT) }}</div>
                        </div>
                        <div class="col-6">
                            <div style="color:#991b1b;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Monto</div>
                            <div class="fw-bold text-danger">{{ $currencySymbol }} {{ number_format((float) $sale->amount, 2) }}</div>
                        </div>
                        <div class="col-6">
                            <div style="color:#991b1b;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Producto</div>
                            <div class="fw-semibold text-truncate">{{ $sale->product?->name ?? '-' }}</div>
                        </div>
                        <div class="col-6">
                            <div style="color:#991b1b;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Cliente</div>
                            <div class="fw-semibold text-truncate">{{ $sale->client?->name ?? 'Sin cliente' }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-body px-4 pt-3 pb-4">

                {{-- Aviso: el usuario actual está autorizado puntualmente --}}
                @if($myGrant && !$canDeleteSale)
                    <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:#f0fdf4;border:1px solid #bbf7d0;font-size:var(--fs-sm);color:#166534;">
                        <i class="bi bi-shield-check flex-shrink-0 mt-1"></i>
                        <div>
                            <strong>{{ $myGrant->grantedBy?->personal?->full_name ?? $myGrant->grantedBy?->name ?? 'Un responsable' }}</strong>
                            te autorizó a eliminar esta venta.
                            <div class="text-muted fs-sm">Válido por única vez · vence el {{ $myGrant->expires_at->format('d/m/Y H:i') }}</div>
                        </div>
                    </div>
                @endif

                {{-- Pestañas: solo si el usuario puede ambas cosas --}}
                @if($canDeleteNow && $canDelegate)
                    <ul class="nav nav-pills nav-fill mb-3 gap-2" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#sd-tab-self" type="button" role="tab">
                                <i class="bi bi-trash3 me-1"></i> Eliminar yo mismo
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#sd-tab-grant" type="button" role="tab">
                                <i class="bi bi-person-check me-1"></i> Autorizar a otro
                            </button>
                        </li>
                    </ul>
                @endif

                <div class="tab-content">
                    {{-- Tab 1: eliminar yo mismo --}}
                    @if($canDeleteNow)
                    <div class="tab-pane fade show active" id="sd-tab-self" role="tabpanel">
                        <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:#fff7ed;border:1px solid #fed7aa;font-size:var(--fs-sm);color:#9a3412;">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1 fs-sm"></i>
                            <div><strong>Esta acción no se puede deshacer.</strong> Se reingresará el producto al inventario y se generará un egreso en caja por el monto cobrado.</div>
                        </div>
                        @if($sale->isCredit())
                        <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:#eff6ff;border:1px solid #bfdbfe;font-size:var(--fs-sm);color:#1e40af;">
                            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1 fs-sm"></i>
                            <div>Venta al <strong>Crédito</strong>: solo se revertirán las cuotas <strong>ya cobradas</strong>. Las cuotas pendientes serán anuladas.</div>
                        </div>
                        @endif
                        <form id="saleDeleteForm" method="POST" action="{{ route('sales.return', $sale) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Motivo de eliminación <span class="text-danger">*</span></label>
                                <select name="return_reason" class="form-select form-select-sm" required>
                                    <option value="">Seleccionar motivo...</option>
                                    @foreach(\App\Models\Sale::RETURN_REASONS as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Observaciones <span class="text-muted fw-normal">(opcional)</span></label>
                                <textarea name="return_notes" class="form-control form-control-sm" rows="2" placeholder="Detalles adicionales sobre la eliminación..."></textarea>
                            </div>
                            <div class="d-grid gap-2">
                                <x-btn variant="danger" type="submit" icon="trash3" size="md" :block="true">Confirmar eliminación</x-btn>
                            </div>
                        </form>
                    </div>
                    @endif

                    {{-- Tab 2: autorizar a otro usuario --}}
                    @if($canDelegate)
                    <div class="tab-pane fade {{ $canDeleteNow ? '' : 'show active' }}" id="sd-tab-grant" role="tabpanel">
                        <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:#eff6ff;border:1px solid #bfdbfe;font-size:var(--fs-sm);color:#1e40af;">
                            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                            <div>El usuario podrá eliminar <strong>solo esta venta</strong>, <strong>una única vez</strong>. La autorización <strong>vence en 24 horas</strong>.</div>
                        </div>

                        <form method="POST" action="{{ route('sales.delete-grant.store', $sale) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Autorizar a <span class="text-danger">*</span></label>
                                <select name="personal_id" class="form-select form-select-sm" required>
                                    <option value="">Seleccionar personal...</option>
                                    @foreach($personals as $p)
                                        <option value="{{ $p->id }}">{{ $p->full_name }}</option>
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

                        {{-- Autorizaciones vigentes --}}
                        @if($activeGrants->isNotEmpty())
                            <div class="mt-4">
                                <div class="small fw-bold text-muted text-uppercase mb-2" style="font-size:var(--fs-xs);letter-spacing:.05em;">
                                    Autorizaciones vigentes ({{ $activeGrants->count() }})
                                </div>
                                @foreach($activeGrants as $g)
                                    <div class="d-flex align-items-center justify-content-between gap-2 p-2 mb-2 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
                                        <div class="small">
                                            <div class="fw-semibold">
                                                <i class="bi bi-person-badge me-1" style="color:#6f42c1;"></i>
                                                {{ $g->grantedTo?->personal?->full_name ?? $g->grantedTo?->name ?? '—' }}
                                            </div>
                                            <div class="text-muted fs-xs">Vence el {{ $g->expires_at->format('d/m/Y H:i') }}</div>
                                        </div>
                                        <form method="POST" action="{{ route('sales.delete-grant.revoke', $g) }}"
                                              onsubmit="return confirm('¿Revocar esta autorización?');">
                                            @csrf @method('DELETE')
                                            <x-icon-btn type="submit" icon="x-lg" label="Revocar" />
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @endif
                </div>

                <x-btn variant="secondary" size="md" :block="true" class="mt-3" data-bs-dismiss="modal">Cancelar</x-btn>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
