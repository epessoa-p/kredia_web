@extends('layouts.app')

@section('title', 'Nueva Venta')

@section('page')
@php
    $preselectedClientId = old('client_id', request('client_id'));
    $preselectedClient   = $preselectedClientId ? $clients->firstWhere('id', (int) $preselectedClientId) : null;
    $hasPreselectedClient = (bool) $preselectedClient;
    $preselectedProductId = old('product_id', request('product_id'));
@endphp
<div>
    {{-- Header --}}
    <x-page-header title="Nueva Venta" icon="cart-check" :back="route('sales.index')" />

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
            <div>
                <strong>Por favor corrige los siguientes errores:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Cash session banner --}}
    @if($activeCashSession)
        @php $sessionBranchColor = ($sessionBranch ?? null)?->color ?? '#198754'; @endphp
        <div class="d-flex align-items-center justify-content-between py-2 px-3 mb-3 rounded-3"
             style="border:1px solid {{ $sessionBranchColor }}44; background:{{ $sessionBranchColor }}12;">
            <div class="small d-flex align-items-center gap-2">
                <span style="width:8px;height:8px;border-radius:50%;background:{{ $sessionBranchColor }};flex-shrink:0;"></span>
                <span>
                    <i class="bi bi-safe2-fill me-1" style="color:{{ $sessionBranchColor }};"></i>
                    Caja activa: <strong>{{ $activeCashSession->cashRegister?->name ?? ('#' . $activeCashSession->cash_register_id) }}</strong>
                    @if($sessionBranch ?? null)
                        · <span style="color:{{ $sessionBranchColor }};">{{ $sessionBranch->name }}</span>
                    @endif
                    · Aperturada: {{ $activeCashSession->opened_at?->format('d/m/Y H:i') }}
                </span>
            </div>
            <span class="badge" style="background:{{ $sessionBranchColor }}22;color:{{ $sessionBranchColor }};border:1px solid {{ $sessionBranchColor }}44;">Lista para ventas</span>
        </div>
    @else
        <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3">
            <i class="bi bi-exclamation-octagon me-1"></i>
            {{ $cashSessionError ?? 'Debes aperturar una caja para poder registrar ventas.' }}
        </div>
    @endif

    <form action="{{ route('sales.store') }}" method="POST" id="sale-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="payment_type" id="payment_type" value="{{ old('payment_type', \App\Models\Sale::PAYMENT_CASH) }}">

        {{-- Pre-selected client banner --}}
        @if($hasPreselectedClient)
            <input type="hidden" id="preselected_client_id" name="client_id" value="{{ $preselectedClient->id }}">
            <div class="card border-0 shadow-sm mb-4 sv-client-locked">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-start gap-3">
                        @if($preselectedClient->photo_url)
                            <img src="{{ $preselectedClient->photo_url }}" alt="{{ $preselectedClient->name }}"
                                class="rounded-3 object-fit-cover flex-shrink-0"
                                style="width:56px;height:56px;border:2px solid #d6e9ff;">
                        @else
                            <span class="sv-icon-badge sv-client-locked-icon">
                                <i class="bi bi-person-check-fill"></i>
                            </span>
                        @endif
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <h6 class="mb-0 fw-bold">Comprador seleccionado</h6>
                                <span class="badge rounded-pill text-bg-success">Listo</span>
                            </div>
                            <p class="text-muted small mb-3">Esta venta se registrará con este cliente.</p>
                            <div class="row g-2 small">
                                <div class="col-md-6 col-lg-3">
                                    <div class="sv-client-data-item"><div class="text-muted mb-1">Nombre</div><div class="fw-semibold">{{ $preselectedClient->name }}</div></div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="sv-client-data-item"><div class="text-muted mb-1">CI / NIT</div><div class="fw-semibold">{{ $preselectedClient->id_number ?: '-' }}</div></div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="sv-client-data-item"><div class="text-muted mb-1">Teléfono</div><div class="fw-semibold">{{ $preselectedClient->phone ?: '-' }}</div></div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="sv-client-data-item"><div class="text-muted mb-1">Email</div><div class="fw-semibold">{{ $preselectedClient->email ?: '-' }}</div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-4">
            {{-- ── CLIENT CARD ─────────────────────── --}}
            @unless($hasPreselectedClient)
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="sv-icon-badge bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-fill"></i></span>
                                <h6 class="mb-0 fw-bold">Comprador</h6>
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-xs">Opcional</span>
                            </div>

                            <div class="k-seg mb-3">
                                <button type="button" class="btn btn-sm btn-ghost active" id="btn-existing"><i class="bi bi-person-check me-1"></i>Existente</button>
                                <button type="button" class="btn btn-sm btn-ghost" id="btn-new"><i class="bi bi-person-plus me-1"></i>Nuevo</button>
                            </div>

                            <div id="existing-section">
                                <label class="form-label small fw-semibold">Seleccionar</label>
                                <select id="client_id" name="client_id" class="form-select form-select-sm @error('client_id') is-invalid @enderror">
                                    <option value="">Buscar comprador...</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ (string) old('client_id') === (string) $client->id ? 'selected' : '' }}>
                                            {{ $client->name }} {{ $client->id_number ? '(' . $client->id_number . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div id="new-section" class="d-none">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" name="client_name" class="form-control form-control-sm @error('client_name') is-invalid @enderror" value="{{ old('client_name') }}" placeholder="Nombre completo">
                                        @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">CI / NIT</label>
                                        <input type="text" name="client_id_number" class="form-control form-control-sm" value="{{ old('client_id_number') }}" placeholder="Documento">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Teléfono</label>
                                        <input type="text" name="client_phone" class="form-control form-control-sm" value="{{ old('client_phone') }}" placeholder="Teléfono">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Email</label>
                                        <input type="email" name="client_email" class="form-control form-control-sm" value="{{ old('client_email') }}" placeholder="correo@ejemplo.com">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Dirección</label>
                                        <input type="text" name="client_address" class="form-control form-control-sm" value="{{ old('client_address') }}" placeholder="Dirección">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endunless

            {{-- ── SALE CARD ──────────────────────── --}}
            <div class="{{ $hasPreselectedClient ? 'col-12' : 'col-lg-8' }}">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center gap-2 mb-4">
                            <span class="sv-icon-badge bg-success bg-opacity-10 text-success"><i class="bi bi-cart-check"></i></span>
                            <h6 class="mb-0 fw-bold">Datos de la Venta</h6>
                        </div>

                        {{-- § 1 — Sucursal --}}
                        <p class="sv-section-label"><i class="bi bi-geo-alt-fill"></i> Sucursal</p>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Sucursal <span class="text-danger">*</span></label>
                                @if($sessionBranch ?? null)
                                    @php $sb = $sessionBranch; $sbColor = $sb->color ?? '#0d6efd'; @endphp
                                    {{-- Keep id="branch_id" so JS populateProducts() still reads it --}}
                                    <input type="hidden" id="branch_id" name="branch_id" value="{{ $sb->id }}">
                                    <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                                         style="border:1.5px solid {{ $sbColor }}33; background:{{ $sbColor }}0d;">
                                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $sbColor }};flex-shrink:0;"></span>
                                        <span class="fw-semibold fs-base">{{ $sb->name }}</span>
                                        <span class="ms-auto text-muted d-flex align-items-center gap-1 fs-sm">
                                            <i class="bi bi-lock-fill"></i> asignada por caja
                                        </span>
                                    </div>
                                    <small class="text-muted fs-xs">
                                        La sucursal corresponde a la caja aperturada:
                                        <strong>{{ $activeCashSession->cashRegister?->name }}</strong>
                                    </small>
                                @else
                                    <select name="branch_id" id="branch_id" class="form-select form-select-sm @error('branch_id') is-invalid @enderror" required>
                                        <option value="">Seleccionar...</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ (string) old('branch_id', $defaultBranchId) === (string) $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                                @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- § 2 — Producto del inventario --}}
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <p class="sv-section-label mb-0"><i class="bi bi-box-seam-fill"></i> Producto disponible</p>
                            <x-btn variant="secondary" icon="grid-1x2-fill" id="open-product-catalog">Ver productos con detalle</x-btn>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Producto <span class="text-danger">*</span></label>
                                <select name="product_id" id="product_id" class="form-select form-select-sm @error('product_id') is-invalid @enderror" required>
                                    <option value="">Selecciona una sucursal primero...</option>
                                </select>
                                @error('product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <small class="text-muted" id="product-count-hint"></small>
                                <div class="small text-primary-emphasis mt-1 d-flex align-items-center gap-1">
                                    <i class="bi bi-info-circle"></i>
                                    Cada venta descuenta <strong>1 unidad</strong> del producto seleccionado.
                                </div>
                            </div>
                        </div>

                        {{-- Product detail card (read-only, filled by JS) --}}
                        <div id="product-detail-card" class="sv-product-detail d-none mb-4">
                            <div class="d-flex gap-3 align-items-start">

                                {{-- Image column (hidden when no images) --}}
                                <div id="pd-gallery-wrap" class="d-none flex-shrink-0 pd-gallery-col">
                                    <div class="pd-gallery-main" id="pd-gallery-main-wrap">
                                        <img id="pd-gallery-main-img" src="" alt="" class="pd-gallery-main-img">
                                        <div class="pd-gallery-overlay d-none" id="pd-gallery-overlay">
                                            <button class="pd-gallery-nav pd-gallery-prev" id="pd-gallery-prev" type="button"><i class="bi bi-chevron-left"></i></button>
                                            <button class="pd-gallery-nav pd-gallery-next" id="pd-gallery-next" type="button"><i class="bi bi-chevron-right"></i></button>
                                            <span class="pd-gallery-counter" id="pd-gallery-counter">1 / 1</span>
                                        </div>
                                    </div>
                                    <div class="pd-gallery-thumbs mt-1" id="pd-gallery-thumbs"></div>
                                </div>

                                {{-- Info column --}}
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <span class="sv-icon-badge bg-warning bg-opacity-10 text-warning"><i class="bi bi-box-seam"></i></span>
                                        <div class="min-w-0">
                                            <h6 class="mb-0 fw-bold text-truncate" id="pd-name">—</h6>
                                            <small class="text-muted" id="pd-category">—</small>
                                        </div>
                                        <span class="badge ms-auto flex-shrink-0" id="pd-origin-badge">—</span>
                                    </div>
                                    <div class="row g-2 small">
                                        <div class="col-6 col-md-4" id="pd-brand-wrap">
                                            <div class="sv-detail-item"><div class="text-muted">Marca</div><div class="fw-semibold" id="pd-brand">—</div></div>
                                        </div>
                                        <div class="col-6 col-md-4" id="pd-model-wrap">
                                            <div class="sv-detail-item"><div class="text-muted">Modelo</div><div class="fw-semibold" id="pd-model">—</div></div>
                                        </div>
                                        <div class="col-6 col-md-4" id="pd-plate-wrap">
                                            <div class="sv-detail-item"><div class="text-muted">Placa</div><div class="fw-semibold" id="pd-plate">—</div></div>
                                        </div>
                                        <div class="col-6 col-md-4" id="pd-chassis-wrap">
                                            <div class="sv-detail-item"><div class="text-muted">Chasis</div><div class="fw-semibold" id="pd-chassis">—</div></div>
                                        </div>
                                        <div class="col-6 col-md-4" id="pd-serial-wrap">
                                            <div class="sv-detail-item"><div class="text-muted">Serial</div><div class="fw-semibold" id="pd-serial">—</div></div>
                                        </div>
                                        <div class="col-6 col-md-4" id="pd-type-wrap">
                                            <div class="sv-detail-item"><div class="text-muted">Tipo</div><div class="fw-semibold" id="pd-type">—</div></div>
                                        </div>
                                        <div class="col-6 col-md-4" id="pd-karat-wrap">
                                            <div class="sv-detail-item"><div class="text-muted">Kilataje</div><div class="fw-semibold" id="pd-karat">—</div></div>
                                        </div>
                                        <div class="col-6 col-md-4" id="pd-stock-wrap">
                                            <div class="sv-detail-item"><div class="text-muted">Disponibles</div><div class="fw-semibold text-success" id="pd-stock">—</div></div>
                                        </div>
                                        <div class="col-12" id="pd-desc-wrap">
                                            <div class="sv-detail-item"><div class="text-muted">Descripción</div><div class="fw-semibold" id="pd-desc">—</div></div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="modal fade" id="productCatalogModal" tabindex="-1" aria-labelledby="productCatalogModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content sv-catalog-modal">
                                    <div class="modal-header border-0 pb-0">
                                        <div>
                                            <span class="sv-modal-kicker">Ayuda de seleccion</span>
                                            <h5 class="modal-title fw-bold mt-1" id="productCatalogModalLabel">Catalogo de productos disponibles</h5>
                                            <p class="text-muted small mb-0">Explora productos con imagen, datos tecnicos, origen y stock disponible. Al elegir uno se completara la sucursal y el selector automaticamente.</p>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body pt-3">
                                        <div class="sv-catalog-toolbar mb-3">
                                            <div class="input-group input-group-sm sv-catalog-search-wrap">
                                                <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
                                                <input type="text" class="form-control border-0" id="catalog-search" placeholder="Buscar por nombre, marca, categoria, placa, serial o sucursal">
                                            </div>
                                            <div class="sv-catalog-toolbar-meta">
                                                <span class="sv-catalog-branch-pill" id="catalog-branch-pill">Todas las sucursales</span>
                                                <span class="sv-catalog-count" id="catalog-result-count">0 productos</span>
                                            </div>
                                        </div>
                                        <div id="catalog-empty-state" class="sv-catalog-empty d-none">
                                            <div class="sv-catalog-empty-icon"><i class="bi bi-search-heart"></i></div>
                                            <h6 class="fw-bold mb-1">No encontramos productos con ese criterio</h6>
                                            <p class="text-muted mb-0">Prueba otra busqueda o cambia la sucursal para ver mas inventario disponible.</p>
                                        </div>
                                        <div class="row g-3" id="catalog-products-grid"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- § 3 — Monto de venta --}}
                        <p class="sv-section-label"><i class="bi bi-calculator-fill"></i> Precio y ganancia</p>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Precio de venta <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text fw-bold text-primary">$</span>
                                    <input type="number" step="1" min="1" name="amount" id="amount"
                                        class="form-control form-control-sm @error('amount') is-invalid @enderror"
                                        value="{{ old('amount') }}" placeholder="0" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Costo original</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">$</span>
                                    <input type="text" id="cost-display" class="form-control form-control-sm bg-light" value="0" readonly>
                                </div>
                                <small class="text-muted">Se obtiene automáticamente del producto.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Ganancia estimada</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="profit-display" class="form-control form-control-sm bg-light fw-bold" value="0%" readonly>
                                    <span class="input-group-text">%</span>
                                    <x-icon-btn icon="eye-fill" label="Ver análisis de ganancia" id="profit-detail-btn" data-bs-toggle="modal" data-bs-target="#profitDetailModal" />
                                </div>
                                <small class="text-muted">Markup sobre costo del producto.</small>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Notas</label>
                                <textarea name="notes" rows="2" class="form-control form-control-sm @error('notes') is-invalid @enderror"
                                    placeholder="Observaciones opcionales...">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- § 4 — Tipo de pago y cuotas --}}
                        <p class="sv-section-label"><i class="bi bi-wallet2"></i> Tipo de venta</p>
                        <div class="k-seg mb-3">
                            <button type="button" class="btn btn-sm btn-ghost active" id="btn-cash-payment">
                                <i class="bi bi-cash-coin me-1"></i> Contado
                            </button>
                            <button type="button" class="btn btn-sm btn-ghost" id="btn-credit-payment">
                                <i class="bi bi-calendar2-week me-1"></i> Crédito
                            </button>
                        </div>

                        <div id="credit-plan-section" class="d-none">
                            <div class="sv-credit-panel">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                    <div>
                                        <h6 class="mb-1 fw-bold">Plan de cuotas</h6>
                                        <p class="small text-muted mb-0">Define la cuota inicial y el recargo. Las cuotas cubren el saldo restante.</p>
                                    </div>
                                    <x-btn icon="plus-lg" id="add-installment-btn">Agregar cuota</x-btn>
                                </div>

                                {{-- Recargo y monto adicional --}}
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Porcentaje adicional de crédito</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" min="0" step="0.01" class="form-control" id="credit_additional_percentage" name="credit_additional_percentage" value="{{ old('credit_additional_percentage', '0') }}" placeholder="0.00">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted">Se aplica sobre el precio de venta ingresado.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Monto adicional calculado</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control bg-light" id="credit_additional_amount" name="credit_additional_amount" value="{{ old('credit_additional_amount', '0.00') }}" readonly>
                                        </div>
                                        <small class="text-muted">Se guarda automáticamente junto con la venta.</small>
                                    </div>
                                </div>

                                {{-- Cuota inicial --}}
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">
                                            <i class="bi bi-cash-stack text-success me-1"></i>
                                            Cuota inicial / Pago inicial
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text fw-bold text-success">$</span>
                                            <input type="number" min="0" step="1" class="form-control" id="down_payment" name="down_payment"
                                                value="{{ old('down_payment', '0') }}" placeholder="0">
                                        </div>
                                        <small class="text-muted">Abono inicial al contado. Se resta del total a financiar.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">
                                            <i class="bi bi-arrow-right-circle text-primary me-1"></i>
                                            Saldo a financiar en cuotas
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text fw-bold text-primary">$</span>
                                            <input type="text" class="form-control bg-light fw-semibold" id="balance_to_finance" readonly value="0">
                                        </div>
                                        <small class="text-muted">Total financiado − cuota inicial. Las cuotas deben cubrir este monto.</small>
                                    </div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-2">
                                        <thead class="table-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Fecha</th>
                                                <th>Modo</th>
                                                <th>Valor</th>
                                                <th class="text-end">%</th>
                                                <th class="text-end">Monto</th>
                                                <th class="text-end text-muted fs-sm">Principal</th>
                                                <th class="text-end text-muted fs-sm">Interés</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="installments-body"></tbody>
                                    </table>
                                </div>

                                <div class="alert alert-danger py-2 px-3 d-none" id="credit-error-box"></div>

                                <div class="sv-credit-summary">
                                    <div class="small text-muted">Precio base</div>
                                    <div class="fw-semibold" id="summary-base-total">$ 0</div>
                                    <div class="small text-muted">Recargo crédito</div>
                                    <div class="fw-semibold" id="summary-credit-extra">$ 0</div>
                                    <div class="small text-muted">Total financiado</div>
                                    <div class="fw-semibold" id="summary-total">$ 0</div>
                                    <div class="small text-muted">Cuota inicial</div>
                                    <div class="fw-semibold text-success" id="summary-down-payment">$ 0</div>
                                    <div class="small text-muted">Saldo en cuotas</div>
                                    <div class="fw-semibold text-primary" id="summary-balance">$ 0</div>
                                    <div class="small text-muted">Cuotas (%)</div>
                                    <div class="fw-semibold" id="summary-percent">0.00%</div>
                                    <div class="small text-muted">Cuotas ($)</div>
                                    <div class="fw-semibold" id="summary-amount">$ 0</div>
                                    <div class="small text-muted">Estado</div>
                                    <div class="fw-semibold" id="summary-state">Incompleto</div>
                                </div>
                                @error('installments')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <hr class="my-3">

                        {{-- § 5 — Imágenes --}}
                        <p class="sv-section-label"><i class="bi bi-camera-fill"></i> Fotos e Imágenes</p>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Adjuntar fotos del producto o documentos</label>
                                <div class="k-seg mb-2">
                                    <button type="button" class="btn btn-sm btn-ghost active" id="si-btn-upload"><i class="bi bi-upload me-1"></i>Subir archivos</button>
                                    <button type="button" class="btn btn-sm btn-ghost" id="si-btn-camera"><i class="bi bi-camera-fill me-1"></i>Usar cámara</button>
                                </div>
                                <div id="si-upload-section">
                                    <div id="sale-image-dropzone" class="sv-image-dropzone">
                                        <div class="dropzone-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                        <div class="dropzone-title">Arrastra tus archivos aquí</div>
                                        <div class="dropzone-subtitle">o haz clic para seleccionarlos</div>
                                        <small id="sale-image-count" class="text-muted d-block mt-1">Sin archivos seleccionados</small>
                                    </div>
                                    <input type="file" id="sale-images-input" name="images[]" class="d-none @error('images.*') is-invalid @enderror" multiple accept="image/*">
                                </div>
                                <div id="si-camera-section" class="d-none">
                                    <div class="cp-camera-wrap">
                                        <div class="cp-flash" id="si-flash"></div>
                                        <video id="si-video" class="cp-video" autoplay playsinline muted></video>
                                        <div class="cp-camera-controls">
                                            <x-icon-btn id="si-capture-btn" icon="camera-fill" variant="primary" size="md" label="Capturar foto" />
                                            <x-icon-btn id="si-flip-btn" icon="arrow-repeat" variant="secondary" size="md" label="Cambiar cámara" />
                                        </div>
                                    </div>
                                    <canvas id="si-canvas" class="d-none"></canvas>
                                    <small id="si-camera-error" class="text-danger d-none"></small>
                                </div>
                                <div id="sale-image-preview-grid" class="image-preview-grid mt-2" style="display:none;"></div>
                                <small class="text-muted">Puedes seleccionar múltiples imágenes (JPG, PNG, WebP). Máx. 5MB cada una.</small>
                                @error('images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- Submit bar --}}
        <div class="mt-4 p-3 bg-white border rounded-3 shadow-sm d-flex align-items-center gap-3 flex-wrap">
            <x-btn type="submit" icon="check-lg" size="md" :disabled="!$activeCashSession">Registrar venta</x-btn>
            <x-btn variant="secondary" size="md" :href="route('sales.index')">Cancelar</x-btn>
            <small class="text-muted ms-auto"><span class="text-danger">*</span> Campos obligatorios</small>
        </div>
    </form>
</div>

{{-- ── Profit detail modal ───────────────────────── --}}
<div class="modal fade" id="profitDetailModal" tabindex="-1" aria-labelledby="profitDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content border-0 shadow-lg" style="border-radius:20px;overflow:hidden">
            {{-- Header --}}
            <div class="modal-header border-0 pb-0 px-4 pt-4" id="pdm-header" style="background:linear-gradient(135deg,#f0faf5 0%,#e6f7ef 100%)">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div id="pdm-icon-wrap" style="width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:var(--fs-lg);flex-shrink:0;background:#d1fae5;color:#065f46">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold fs-md" id="pdm-title">Análisis de ganancia</div>
                        <div class="small" id="pdm-subtitle" style="color:#6b7280">Selecciona un producto y precio para ver el análisis.</div>
                    </div>
                    <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                {{-- Big number --}}
                <div class="text-center w-100 py-3">
                    <div id="pdm-big-pct" style="font-size:var(--fs-xl);font-weight:900;line-height:1;letter-spacing:-.03em">—</div>
                    <div class="small fw-semibold mt-1" id="pdm-big-label" style="color:#6b7280">markup sobre costo</div>
                </div>
            </div>
            {{-- Visual bar --}}
            <div class="px-4 py-2" style="background:#f8fafc">
                <div class="d-flex justify-content-between small fw-semibold mb-1" style="color:#64748b">
                    <span>Costo</span>
                    <span>Precio de venta</span>
                </div>
                <div class="position-relative" style="height:14px;border-radius:999px;overflow:hidden;background:#e2e8f0">
                    <div id="pdm-bar-cost" style="position:absolute;left:0;top:0;height:100%;background:#94a3b8;border-radius:999px 0 0 999px;transition:width .35s ease"></div>
                    <div id="pdm-bar-profit" style="position:absolute;top:0;height:100%;background:#22c55e;border-radius:0 999px 999px 0;transition:left .35s ease,width .35s ease"></div>
                </div>
                <div class="d-flex justify-content-between mt-1" style="font-size:var(--fs-xs);color:#94a3b8">
                    <span id="pdm-bar-cost-lbl">$ 0</span>
                    <span id="pdm-bar-profit-lbl" style="color:#16a34a;font-weight:700">+ $ 0</span>
                </div>
            </div>
            {{-- Metrics --}}
            <div class="px-4 pt-3 pb-1">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="pdm-metric-card" style="background:#f1f5f9;border-radius:12px;padding:.65rem .8rem">
                            <div style="font-size:var(--fs-xs);color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Precio venta</div>
                            <div id="pdm-sale" class="fw-bold mt-1" style="font-size:var(--fs-md);color:#0f172a">$ 0</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="pdm-metric-card" style="background:#f1f5f9;border-radius:12px;padding:.65rem .8rem">
                            <div style="font-size:var(--fs-xs);color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Costo original</div>
                            <div id="pdm-cost" class="fw-bold mt-1" style="font-size:var(--fs-md);color:#0f172a">$ 0</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="pdm-metric-card" style="background:#f1f5f9;border-radius:12px;padding:.65rem .8rem">
                            <div style="font-size:var(--fs-xs);color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Ganancia bruta $</div>
                            <div id="pdm-profit-abs" class="fw-bold mt-1 fs-md">$ 0</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="pdm-metric-card" style="background:#f1f5f9;border-radius:12px;padding:.65rem .8rem">
                            <div style="font-size:var(--fs-xs);color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Margen sobre venta</div>
                            <div id="pdm-margin" class="fw-bold mt-1" style="font-size:var(--fs-md);color:#0f172a">0%</div>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Contextual tip --}}
            <div class="px-4 pt-3 pb-4">
                <div id="pdm-tip" class="d-flex align-items-start gap-2 px-3 py-2 rounded-3" style="font-size:var(--fs-sm);background:#f0fdf4;border:1px solid #bbf7d0;color:#166534">
                    <i class="bi bi-lightbulb-fill flex-shrink-0 mt-1 fs-sm"></i>
                    <span id="pdm-tip-text">Ingresa el precio de venta para ver el análisis.</span>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Shared fullscreen image gallery modal ─────── --}}
<div class="modal fade" id="svProductGalleryModal" tabindex="-1" aria-labelledby="svProductGalleryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content sv-pgl-modal-content">
            <div class="modal-header border-0 pb-1 px-4 pt-3">
                <div>
                    <h6 class="modal-title fw-bold mb-0" id="svProductGalleryModalLabel">Fotos del producto</h6>
                    <small id="svPglCounter">— / —</small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div id="svProductGalleryCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner sv-pgl-carousel-inner" id="svPglInner"></div>
                    <button class="carousel-control-prev sv-pgl-nav-btn" type="button" data-bs-target="#svProductGalleryCarousel" data-bs-slide="prev" id="svPgl-prev">
                        <span class="sv-pgl-nav-icon"><i class="bi bi-chevron-left"></i></span>
                    </button>
                    <button class="carousel-control-next sv-pgl-nav-btn" type="button" data-bs-target="#svProductGalleryCarousel" data-bs-slide="next" id="svPgl-next">
                        <span class="sv-pgl-nav-icon"><i class="bi bi-chevron-right"></i></span>
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 pt-2 px-4 pb-3 justify-content-center" id="svPgl-thumbs-wrap">
                <div class="pgl-thumbs" id="svPglThumbs"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .sv-icon-badge {
        width: 34px; height: 34px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .sv-section-label {
        font-size:var(--fs-sm); font-weight: 700; letter-spacing: .02em;
        color: #495057; margin-bottom: .6rem;
        display: flex; align-items: center; gap: .45rem;
    }
    .sv-product-detail {
        border: 1px solid #d8e4f7; border-radius: 14px; padding: 1rem;
        background: linear-gradient(145deg, #f8fbff 0%, #f1f7ff 100%);
    }
    /* Product detail gallery — side column */
    .pd-gallery-col {
        width: 180px;
    }
    .pd-gallery-main {
        position: relative;
        width: 100%;
        border-radius: 10px;
        overflow: hidden;
        background: #0d1117;
        aspect-ratio: 1 / 1;
        cursor: zoom-in;
    }
    .pd-gallery-main-img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
        transition: opacity .2s ease;
    }
    .pd-gallery-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        pointer-events: none;
    }
    .pd-gallery-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 34px;
        height: 34px;
        border-radius: 50%;
        border: 0;
        background: rgba(0,0,0,.38);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size:var(--fs-md);
        cursor: pointer;
        pointer-events: all;
        transition: background .15s ease;
        z-index: 2;
    }
    .pd-gallery-nav:hover { background: rgba(0,0,0,.62); }
    .pd-gallery-prev { left: 8px; }
    .pd-gallery-next { right: 8px; }
    .pd-gallery-counter {
        position: absolute;
        bottom: 8px;
        right: 10px;
        padding: .2rem .55rem;
        border-radius: 999px;
        background: rgba(0,0,0,.5);
        color: #fff;
        font-size:var(--fs-xs);
        font-weight: 700;
        pointer-events: none;
    }
    .pd-gallery-thumbs {
        display: flex;
        gap: .4rem;
        flex-wrap: wrap;
    }
    .pd-gallery-thumb {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        overflow: hidden;
        border: 2px solid transparent;
        cursor: pointer;
        opacity: .55;
        transition: opacity .15s ease, border-color .15s ease;
        flex-shrink: 0;
    }
    .pd-gallery-thumb img {
        width: 100%; height: 100%; object-fit: cover;
    }
    .pd-gallery-thumb.active { border-color: #0d6efd; opacity: 1; }
    .pd-gallery-thumb:hover { opacity: .85; }
    .sv-detail-item {
        background: rgba(255,255,255,.78); border: 1px solid #e3edf9;
        border-radius: .65rem; padding: .45rem .55rem; min-height: 52px;
    }
    .sv-client-locked {
        background: linear-gradient(135deg, #f5fbff 0%, #edf7ff 100%);
        border: 1px solid #d6e9ff !important;
    }
    .sv-client-locked-icon {
        width: 44px; height: 44px; border-radius: 14px;
        background: linear-gradient(135deg, #e8f3ff 0%, #d8eaff 100%);
        color: #0d6efd; font-size:var(--fs-md); flex-shrink: 0;
    }
    .sv-client-data-item {
        background: rgba(255,255,255,.78); border: 1px solid #e3edf9;
        border-radius: .65rem; padding: .55rem .65rem; min-height: 62px;
    }
    .sv-image-dropzone {
        border: 1px dashed #b9c6d8;
        background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 100%);
        border-radius: 12px; padding: 16px 12px; text-align: center;
        cursor: pointer; transition: all .2s ease; margin-bottom: .35rem;
    }
    .sv-image-dropzone:hover,
    .sv-image-dropzone.is-dragover {
        border-color: #0d6efd; background: #eaf2ff;
        box-shadow: 0 0 0 3px rgba(13,110,253,.08);
    }
    .sv-image-dropzone .dropzone-icon { font-size:var(--fs-lg); color: #0d6efd; line-height: 1; margin-bottom: .3rem; }
    .sv-image-dropzone .dropzone-title { font-weight: 600; color: #1f2d3d; font-size:var(--fs-base); margin-bottom: .1rem; }
    .sv-image-dropzone .dropzone-subtitle { color: #5b6573; font-size:var(--fs-sm); }
    .image-preview-grid {
        display: flex;
        flex-wrap: wrap;
        gap: .3rem;
        align-items: center;
        padding: .3rem 0;
    }
    .image-preview-thumb {
        width: 44px;
        height: 44px;
        border-radius: 7px;
        overflow: hidden;
        border: 1.5px solid #cfe2ff;
        flex-shrink: 0;
    }
    .image-preview-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .image-preview-clear {
        display: inline-flex;
        align-items: center;
        gap: .2rem;
        padding: .18rem .45rem;
        font-size:var(--fs-xs);
        border-radius: 999px;
        border: 1px solid #efc6cc;
        background: #fff5f6;
        color: #c23e4d;
        cursor: pointer;
        font-weight: 600;
        line-height: 1.4;
    }
    .cp-camera-wrap { position:relative; border-radius:12px; overflow:hidden; background:#0a0a0a; aspect-ratio:4/3; width:60%; margin:0 auto; }
    .cp-video { width:100%; height:100%; object-fit:cover; display:block; }
    .cp-flash { position:absolute; inset:0; background:#fff; opacity:0; pointer-events:none; z-index:10; transition:opacity .12s ease; }
    .cp-camera-controls { position:absolute; bottom:12px; left:0; right:0; display:flex; justify-content:center; align-items:center; gap:20px; z-index:5; }
    .sv-credit-panel {
        border: 1px solid #dce7ff;
        background: linear-gradient(145deg, #f9fbff 0%, #f0f6ff 100%);
        border-radius: 14px;
        padding: .9rem;
    }
    .sv-credit-summary {
        display: grid;
        grid-template-columns: repeat(4, auto);
        gap: .45rem 1.25rem;
        align-items: center;
        background: rgba(255,255,255,.75);
        border: 1px solid #e3edf9;
        border-radius: .65rem;
        padding: .55rem .7rem;
    }
    @media (max-width: 767px) {
        .sv-credit-summary {
            grid-template-columns: repeat(2, auto);
            gap: .35rem .9rem;
        }
    }
    .select2-container .select2-selection--single {
        height: 38px; border: 1px solid #ced4da; border-radius: 0.375rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 36px; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 36px; }
    #profit-display.text-success { color: #198754 !important; }
    #profit-display.text-danger  { color: #dc3545 !important; }
    .sv-catalog-modal {
        border: 0;
        border-radius: 24px;
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(120, 170, 255, .18), transparent 22%),
            linear-gradient(180deg, #f8fbff 0%, #f4f8ff 100%);
    }
    .sv-modal-kicker {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .3rem .65rem;
        border-radius: 999px;
        font-size:var(--fs-xs);
        font-weight: 700;
        color: #2457c5;
        background: rgba(255,255,255,.75);
        border: 1px solid rgba(36,87,197,.14);
        text-transform: uppercase;
        letter-spacing: .08em;
    }
    .sv-catalog-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: .75rem;
        align-items: center;
        justify-content: space-between;
    }
    .sv-catalog-search-wrap {
        flex: 1 1 360px;
        background: rgba(255,255,255,.9);
        border: 1px solid #dce7ff;
        border-radius: 999px;
        padding: .12rem .2rem;
        box-shadow: 0 8px 24px rgba(20, 47, 96, .06);
    }
    .sv-catalog-search-wrap .form-control,
    .sv-catalog-search-wrap .input-group-text {
        background: transparent;
        box-shadow: none;
    }
    .sv-catalog-toolbar-meta {
        display: flex;
        flex-wrap: wrap;
        gap: .5rem;
        align-items: center;
        justify-content: flex-end;
    }
    .sv-catalog-branch-pill,
    .sv-catalog-count {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: .4rem .75rem;
        border-radius: 999px;
        font-size:var(--fs-sm);
        font-weight: 700;
        border: 1px solid #dde7f6;
        background: rgba(255,255,255,.85);
        color: #334155;
    }
    .sv-catalog-grid-card {
        height: 100%;
        border: 1px solid #d9e6fb;
        border-radius: 18px;
        background: rgba(255,255,255,.92);
        box-shadow: 0 10px 22px rgba(25, 52, 94, .08);
        overflow: hidden;
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }
    .sv-catalog-grid-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 34px rgba(25, 52, 94, .14);
        border-color: #b9d0fb;
    }
    .sv-catalog-card-media {
        position: relative;
        height: 136px;
        background: linear-gradient(135deg, #dce9ff 0%, #eef4ff 52%, #f9fbff 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    .sv-catalog-card-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .sv-catalog-card-media-clickable {
        cursor: zoom-in;
    }
    .sv-catalog-card-media-clickable::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0);
        transition: background .18s ease;
        pointer-events: none;
    }
    .sv-catalog-card-media-clickable:hover::after {
        background: rgba(0,0,0,.10);
    }
    .sv-catalog-photo-badge {
        position: absolute;
        bottom: 8px;
        left: 8px;
        padding: .25rem .55rem;
        border-radius: 999px;
        background: rgba(10,20,40,.55);
        color: #fff;
        font-size:var(--fs-xs);
        font-weight: 700;
        backdrop-filter: blur(6px);
        display: inline-flex;
        align-items: center;
        gap: .28rem;
        cursor: pointer;
        z-index: 2;
        line-height: 1;
        transition: background .15s ease;
    }
    .sv-catalog-photo-badge:hover { background: rgba(10,20,40,.8); }
    .sv-catalog-photo-badge.single { padding: .25rem .4rem; }
    /* Shared fullscreen gallery modal */
    .sv-pgl-modal-content {
        border: 0;
        border-radius: 22px;
        overflow: hidden;
        background: #0e1522;
        color: #fff;
    }
    .sv-pgl-modal-content .modal-header { background: #0e1522; }
    .sv-pgl-modal-content .modal-title,
    .sv-pgl-modal-content #svPglCounter { color: rgba(255,255,255,.85) !important; }
    .sv-pgl-modal-content .btn-close { filter: invert(1) brightness(1.5); }
    .sv-pgl-carousel-inner {
        background: #0e1522;
        min-height: 300px;
        max-height: 58vh;
        display: flex;
        align-items: center;
    }
    .sv-pgl-carousel-inner .carousel-item { text-align: center; }
    .sv-pgl-carousel-inner img {
        max-height: 58vh;
        max-width: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        display: inline-block;
    }
    .sv-pgl-nav-btn { width: 48px; opacity: 1; }
    .sv-pgl-nav-icon {
        width: 34px; height: 34px; border-radius: 50%;
        background: rgba(255,255,255,.12); backdrop-filter: blur(6px);
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-size:var(--fs-md); transition: background .15s ease;
    }
    .sv-pgl-nav-btn:hover .sv-pgl-nav-icon { background: rgba(255,255,255,.24); }
    .sv-pgl-modal-content .modal-footer { background: #0e1522; }
    .pgl-thumbs {
        display: flex;
        gap: .45rem;
        flex-wrap: wrap;
        justify-content: center;
    }
    .pgl-thumb {
        width: 52px;
        height: 52px;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid transparent;
        cursor: pointer;
        opacity: .55;
        transition: opacity .15s ease, border-color .15s ease;
        flex-shrink: 0;
    }
    .pgl-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .pgl-thumb.active { border-color: #4a90e2; opacity: 1; }
    .pgl-thumb:hover { opacity: .85; }
    .sv-catalog-card-placeholder {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        background: rgba(255,255,255,.72);
        color: #3563c7;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size:var(--fs-lg);
        box-shadow: inset 0 0 0 1px rgba(53, 99, 199, .08);
    }
    .sv-catalog-origin-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        padding: .4rem .65rem;
        border-radius: 999px;
        font-size:var(--fs-xs);
        font-weight: 700;
        backdrop-filter: blur(8px);
    }
    .sv-catalog-card-body {
        padding: .65rem .75rem .72rem;
    }
    .sv-catalog-card-title {
        font-size:var(--fs-base);
        font-weight: 800;
        color: #10213c;
        margin-bottom: .05rem;
        min-height: 1.9rem;
        line-height: 1.12;
    }
    .sv-catalog-card-subtitle {
        color: #60708a;
        font-size:var(--fs-xs);
        min-height: 1.25rem;
        line-height: 1.2;
    }
    .sv-catalog-card-facts {
        display: flex;
        flex-wrap: wrap;
        gap: .12rem .45rem;
        margin-top: .4rem;
        padding-top: .45rem;
        border-top: 1px dashed #dbe5f7;
        font-size:var(--fs-xs);
        line-height: 1.15;
        color: #58677d;
    }
    .sv-catalog-card-facts span {
        display: inline-flex;
        align-items: baseline;
        gap: .14rem;
        min-width: 0;
    }
    .sv-catalog-card-facts strong {
        color: #21324c;
        font-size:var(--fs-xs);
        font-weight: 700;
    }
    .sv-catalog-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-top: .45rem;
    }
    .sv-catalog-price {
        display: flex;
        flex-direction: column;
    }
    .sv-catalog-price small {
        color: #7a8798;
        font-size:var(--fs-xs);
        line-height: 1.05;
    }
    .sv-catalog-price strong {
        font-size:var(--fs-sm);
        color: #0f5132;
        line-height: 1.05;
    }
    /* Producto de otra sucursal: solo referencia (no vendible). */
    .sv-catalog-grid-card.sv-catalog-other { opacity: .72; }
    .sv-catalog-grid-card.sv-catalog-other:hover { opacity: 1; }
    .sv-catalog-empty {
        border: 1px dashed #d3def2;
        border-radius: 20px;
        background: rgba(255,255,255,.78);
        padding: 2.2rem 1rem;
        text-align: center;
    }
    .sv-catalog-empty-icon {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        margin: 0 auto .9rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #e7f0ff 0%, #f4f8ff 100%);
        color: #3563c7;
        font-size:var(--fs-xl);
    }
    @media (max-width: 991px) {
        .sv-catalog-card-footer {
            flex-direction: column;
            align-items: stretch;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function () {
    /* ── Data ──────────────────────────────── */
    const availableProducts = @json($availableProducts);
    const branchesData      = @json($branchesJson);
    const sessionWarehouseId = @json($sessionWarehouseId);
    const hasPreselectedClient = @json($hasPreselectedClient);
    const oldInstallments = @json(old('installments', []));

    /* ── Selectors ────────────────────────── */
    const $form           = $('#sale-form');
    const $branchSelect   = $('#branch_id');
    const $productSelect  = $('#product_id');
    const $amountInput    = $('#amount');
    const $costDisplay    = $('#cost-display');
    const $profitDisplay  = $('#profit-display');
    const $detailCard     = $('#product-detail-card');
    const $countHint      = $('#product-count-hint');
    const $clientSelect   = $('select[name="client_id"]');
    const $existingSection= $('#existing-section');
    const $newSection     = $('#new-section');
    const $btnExisting    = $('#btn-existing');
    const $btnNew         = $('#btn-new');
    const $imagesInput    = $('#sale-images-input');
    const $imagesDropzone = $('#sale-image-dropzone');
    const $imagesCount    = $('#sale-image-count');
    const $paymentType    = $('#payment_type');
    const $btnCashPayment = $('#btn-cash-payment');
    const $btnCreditPayment = $('#btn-credit-payment');
    const $creditPlan     = $('#credit-plan-section');
    const $installmentsBody = $('#installments-body');
    const $addInstallmentBtn = $('#add-installment-btn');
    const $creditErrorBox = $('#credit-error-box');
    const $creditAdditionalPct = $('#credit_additional_percentage');
    const $creditAdditionalAmount = $('#credit_additional_amount');
    const $summaryBaseTotal    = $('#summary-base-total');
    const $summaryCreditExtra  = $('#summary-credit-extra');
    const $summaryTotal        = $('#summary-total');
    const $summaryDownPayment  = $('#summary-down-payment');
    const $summaryBalance      = $('#summary-balance');
    const $summaryPercent      = $('#summary-percent');
    const $summaryAmount       = $('#summary-amount');
    const $summaryState        = $('#summary-state');
    const $downPayment         = $('#down_payment');
    const $balanceToFinance    = $('#balance_to_finance');
    const $catalogSearch  = $('#catalog-search');
    const $catalogGrid    = $('#catalog-products-grid');
    const $catalogEmpty   = $('#catalog-empty-state');
    const $catalogCount   = $('#catalog-result-count');
    const $catalogBranchPill = $('#catalog-branch-pill');
    const productCatalogModalEl = document.getElementById('productCatalogModal');
    const productCatalogModal = productCatalogModalEl ? new bootstrap.Modal(productCatalogModalEl) : null;

    let currentCost = 0;

    /* ── Client mode toggle ───────────────── */
    function setClientMode(mode) {
        const useNew = mode === 'new';
        $btnExisting.toggleClass('active', !useNew);
        $btnNew.toggleClass('active', useNew);
        $existingSection.toggleClass('d-none', useNew);
        $newSection.toggleClass('d-none', !useNew);
        if (useNew && $clientSelect.length) $clientSelect.val('').trigger('change');
    }
    $btnExisting.on('click', () => setClientMode('existing'));
    $btnNew.on('click', () => setClientMode('new'));

    if ($clientSelect.length) {
        $clientSelect.select2({ placeholder: 'Buscar comprador...', allowClear: true, width: '100%' });
    }
    if (!hasPreselectedClient) {
        const oldClientId   = '{{ old('client_id', request('client_id')) }}';
        const oldClientName = '{{ old('client_name') }}';
        setClientMode(oldClientId ? 'existing' : (oldClientName ? 'new' : 'existing'));
        if ($clientSelect.length && oldClientId && !$clientSelect.val()) {
            $clientSelect.val(oldClientId).trigger('change');
        }
    }

    /* ── Branch → Products filter ─────────── */
    function populateProducts() {
        // Solo se puede vender lo que está en el almacén de la caja abierta.
        const filtered = sessionWarehouseId
            ? availableProducts.filter(p => parseInt(p.warehouse_id) === parseInt(sessionWarehouseId))
            : [];

        let html = '<option value="">Seleccionar producto...</option>';
        filtered.forEach(function (p) {
            const label = (p.code ? p.code + ' · ' : '') + p.name
                + (p.brand ? ' - ' + p.brand : '')
                + ' (' + p.category_name + ')'
                + ' | Unidades disponibles: ' + p.stock
                + ' | ' + p.origin_label;
            const sel = (String(p.id) === '{{ $preselectedProductId }}') ? ' selected' : '';
            html += `<option value="${p.id}"${sel}>${label}</option>`;
        });

        $productSelect.html(html);
        if ($productSelect.data('select2')) $productSelect.select2('destroy');
        $productSelect.select2({ placeholder: 'Seleccionar producto...', allowClear: true, width: '100%' });

        const totalUnits = filtered.reduce((sum, p) => sum + (parseFloat(p.stock) || 0), 0);
        $countHint.text(filtered.length
            ? filtered.length + ' tipo' + (filtered.length !== 1 ? 's' : '') + ' de producto · ' + totalUnits.toFixed(2) + ' unidades disponibles para vender'
            : (sessionWarehouseId
                ? 'No hay productos disponibles en el almacén de tu caja.'
                : 'Necesitas una caja abierta para vender.'));

        showProductDetail();
    }

    $branchSelect.on('change', populateProducts);
    populateProducts();

    /* ── Product detail card ──────────────── */
    function showProductDetail() {
        const pid  = parseInt($productSelect.val());
        const prod = availableProducts.find(p => p.id === pid);

        if (!prod) {
            $detailCard.addClass('d-none');
            currentCost = 0;
            $costDisplay.val('0.00');
            $amountInput.val('');
            updateProfit();
            return;
        }

        $detailCard.removeClass('d-none');
        $('#pd-name').text(prod.name || '—');
        $('#pd-category').text(prod.category_name || '—');

        const originColors = { collateral: 'bg-warning-subtle text-warning border border-warning-subtle', purchase: 'bg-info-subtle text-info border border-info-subtle', other: 'bg-secondary-subtle text-secondary border border-secondary-subtle' };
        $('#pd-origin-badge').attr('class', 'badge ms-auto ' + (originColors[prod.origin] || originColors.other)).text(prod.origin_label);

        function showField(wrapId, valueId, value) {
            if (value) { $(wrapId).removeClass('d-none'); $(valueId).text(value); }
            else { $(wrapId).addClass('d-none'); }
        }

        showField('#pd-brand-wrap', '#pd-brand', prod.brand);
        showField('#pd-model-wrap', '#pd-model', prod.model_year);
        showField('#pd-plate-wrap', '#pd-plate', prod.plate);
        showField('#pd-chassis-wrap', '#pd-chassis', prod.chassis);
        showField('#pd-serial-wrap', '#pd-serial', prod.serial_number);
        showField('#pd-type-wrap', '#pd-type', prod.item_type);
        showField('#pd-karat-wrap', '#pd-karat', prod.karat);
        showField('#pd-desc-wrap', '#pd-desc', prod.description);
        $('#pd-stock').text(prod.stock);

        // Product image gallery
        const imageUrls = prod.image_urls || (prod.image_url ? [prod.image_url] : []);
        renderPdGallery(imageUrls, prod.name || 'Producto');

        currentCost = parseFloat(prod.cost) || 0;
        $costDisplay.val(currentCost.toFixed(0));

        // Suggest the price as default sale amount
        if (!$amountInput.val() && prod.price > 0) {
            $amountInput.val(parseFloat(prod.price).toFixed(0));
        }
        updateProfit();
    }

    /* ── Product detail image gallery ────── */
    let _pdGalleryImages = [];
    let _pdGalleryCurrent = 0;

    function renderPdGallery(images, productName) {
        const wrap = document.getElementById('pd-gallery-wrap');
        if (!images || !images.length) {
            wrap.classList.add('d-none');
            return;
        }

        wrap.classList.remove('d-none');
        _pdGalleryImages = images;
        _pdGalleryCurrent = 0;

        const mainImg = document.getElementById('pd-gallery-main-img');
        const overlay = document.getElementById('pd-gallery-overlay');
        const counter = document.getElementById('pd-gallery-counter');
        const thumbsEl = document.getElementById('pd-gallery-thumbs');
        const prevBtn = document.getElementById('pd-gallery-prev');
        const nextBtn = document.getElementById('pd-gallery-next');
        const mainWrap = document.getElementById('pd-gallery-main-wrap');

        mainImg.src = images[0];
        mainImg.alt = productName;

        const multipleImages = images.length > 1;
        overlay.classList.toggle('d-none', !multipleImages);
        counter.textContent = `1 / ${images.length}`;

        thumbsEl.innerHTML = images.map((url, i) =>
            `<div class="pd-gallery-thumb${i === 0 ? ' active' : ''}" data-idx="${i}" title="Foto ${i + 1}">
                <img src="${url}" alt="Foto ${i + 1}">
            </div>`
        ).join('');

        // Thumb click
        thumbsEl.querySelectorAll('.pd-gallery-thumb').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                pdGalleryGoTo(parseInt(this.dataset.idx, 10));
            });
        });

        // Nav buttons
        prevBtn.onclick = function () { pdGalleryGoTo((_pdGalleryCurrent - 1 + _pdGalleryImages.length) % _pdGalleryImages.length); };
        nextBtn.onclick = function () { pdGalleryGoTo((_pdGalleryCurrent + 1) % _pdGalleryImages.length); };

        // Click main image — open fullscreen gallery modal
        mainWrap.onclick = function () { openSvGalleryModal(_pdGalleryImages, _pdGalleryCurrent, productName); };
    }

    function pdGalleryGoTo(index) {
        _pdGalleryCurrent = index;
        const mainImg = document.getElementById('pd-gallery-main-img');
        const counter = document.getElementById('pd-gallery-counter');
        const thumbsEl = document.getElementById('pd-gallery-thumbs');

        mainImg.style.opacity = '0';
        setTimeout(function () {
            mainImg.src = _pdGalleryImages[index];
            mainImg.style.opacity = '1';
        }, 120);
        counter.textContent = `${index + 1} / ${_pdGalleryImages.length}`;
        thumbsEl.querySelectorAll('.pd-gallery-thumb').forEach(function (t, i) {
            t.classList.toggle('active', i === index);
        });
    }

    $productSelect.on('change', showProductDetail);

    /* ── Product catalog modal ───────────── */
    function formatMoney(value) {
        return '{{ $currencySymbol }} ' + (parseFloat(value || 0).toFixed(0));
    }

    function catalogOriginClass(origin) {
        if (origin === 'collateral') return 'bg-warning-subtle text-warning border border-warning-subtle';
        if (origin === 'purchase') return 'bg-info-subtle text-info border border-info-subtle';
        return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    function getCatalogFilteredProducts() {
        const branchId = parseInt($branchSelect.val() || '0', 10);
        const search = ($catalogSearch.val() || '').trim().toLowerCase();

        let filtered = availableProducts.slice();

        if (search) {
            filtered = filtered.filter(function (product) {
                const haystack = [
                    product.code,
                    product.name,
                    product.brand,
                    product.category_name,
                    product.branch_name,
                    product.plate,
                    product.serial_number,
                    product.chassis,
                    product.item_type,
                    product.origin_label,
                    product.description,
                ].filter(Boolean).join(' ').toLowerCase();

                return haystack.includes(search);
            });
        }

        filtered.sort(function (a, b) {
            const branchScoreA = branchId && a.branch_id === branchId ? 0 : 1;
            const branchScoreB = branchId && b.branch_id === branchId ? 0 : 1;
            if (branchScoreA !== branchScoreB) {
                return branchScoreA - branchScoreB;
            }

            return String(a.name || '').localeCompare(String(b.name || ''));
        });

        return filtered;
    }

    function renderProductCatalog() {
        if (!$catalogGrid.length) {
            return;
        }

        const branchId = parseInt($branchSelect.val() || '0', 10);
        const activeBranch = branchesData.find(branch => branch.id === branchId);
        const filtered = getCatalogFilteredProducts();

        $catalogBranchPill.text(activeBranch ? 'Sucursal actual: ' + activeBranch.name : 'Todas las sucursales');
        $catalogCount.text(filtered.length + ' producto' + (filtered.length === 1 ? '' : 's'));

        if (!filtered.length) {
            $catalogGrid.empty();
            $catalogEmpty.removeClass('d-none');
            return;
        }

        $catalogEmpty.addClass('d-none');

        const html = filtered.map(function (product) {
            const imageUrls = product.image_urls || (product.image_url ? [product.image_url] : []);
            const hasImages = imageUrls.length > 0;
            const imageHtml = product.image_url
                ? `<img src="${product.image_url}" alt="${product.name}">`
                : `<div class="sv-catalog-card-placeholder"><i class="bi bi-image"></i></div>`;

            const photoBadge = imageUrls.length > 1
                ? `<span class="sv-catalog-photo-badge" data-images='${JSON.stringify(imageUrls)}' data-name="${product.name}" onclick="event.stopPropagation();openSvGalleryModal(JSON.parse(this.dataset.images),0,this.dataset.name)"><i class="bi bi-images"></i> ${imageUrls.length} fotos</span>`
                : (imageUrls.length === 1 ? `<span class="sv-catalog-photo-badge single" data-images='${JSON.stringify(imageUrls)}' data-name="${product.name}" onclick="event.stopPropagation();openSvGalleryModal(JSON.parse(this.dataset.images),0,this.dataset.name)"><i class="bi bi-image"></i></span>` : '');

            const facts = [
                ['Sucursal', product.branch_name || 'Sin sucursal'],
                ['Stock', Number(product.stock || 0).toFixed(2) + ' und.'],
                ['Marca', product.brand || 'No registrada'],
                ['Categoria', product.category_name || 'Sin categoria'],
                ['Placa / Serie', product.plate || product.serial_number || 'No disponible'],
                ['Modelo / Tipo', product.model_year || product.item_type || 'No disponible'],
            ];

            const factsHtml = facts.map(function (fact) {
                return `<span><strong>${fact[0]}:</strong> ${fact[1]}</span>`;
            }).join('');

            const subtitleParts = [product.origin_label, product.description].filter(Boolean);
            const mediaClass = hasImages ? ' sv-catalog-card-media-clickable' : '';
            const mediaAttrs = hasImages
                ? ` role="button" tabindex="0" data-images='${JSON.stringify(imageUrls)}' data-name="${product.name}" onclick="openSvGalleryModal(JSON.parse(this.dataset.images),0,this.dataset.name)"`
                : '';

            // Solo es vendible si está en el almacén de la caja. El resto es referencia.
            const own = sessionWarehouseId && parseInt(product.warehouse_id) === parseInt(sessionWarehouseId);
            const action = own
                ? `<button type="button" class="btn btn-primary btn-sm sv-catalog-select-btn" data-select-product="${product.id}">
                        <i class="bi bi-check2-circle me-1"></i> Elegir producto
                   </button>`
                : `<span class="badge bg-secondary-subtle text-secondary border" style="font-size:var(--fs-xs);line-height:1.3;text-align:right;">
                        <i class="bi bi-geo-alt me-1"></i>En ${product.branch_name || 'otra sucursal'}<br>pídelo a esa sucursal
                   </span>`;

            return `
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <article class="sv-catalog-grid-card${own ? '' : ' sv-catalog-other'}">
                        <div class="sv-catalog-card-media${mediaClass}"${mediaAttrs}>
                            ${imageHtml}
                            ${photoBadge}
                            <span class="sv-catalog-origin-badge ${catalogOriginClass(product.origin)}">${product.origin_label}</span>
                        </div>
                        <div class="sv-catalog-card-body">
                            <div class="sv-catalog-card-title">${product.code ? `<span class="text-primary">${product.code}</span> · ` : ''}${product.name || 'Producto sin nombre'}</div>
                            <div class="sv-catalog-card-subtitle">${subtitleParts.join(' · ') || 'Disponible'}</div>
                            <div class="sv-catalog-card-facts">${factsHtml}</div>
                            <div class="sv-catalog-card-footer">
                                <div class="sv-catalog-price">
                                    <small>Precio sugerido</small>
                                    <strong>${formatMoney(product.price)}</strong>
                                </div>
                                ${action}
                            </div>
                        </div>
                    </article>
                </div>
            `;
        }).join('');

        $catalogGrid.html(html);
    }

    function selectProductFromCatalog(productId) {
        // Solo se pueden elegir productos del almacén de la caja (vendibles).
        const prod = availableProducts.find(p => String(p.id) === String(productId));
        if (!prod || !sessionWarehouseId || parseInt(prod.warehouse_id) !== parseInt(sessionWarehouseId)) {
            return;
        }

        $productSelect.val(String(productId)).trigger('change');

        if (productCatalogModal) {
            productCatalogModal.hide();
        }
    }

    $('#open-product-catalog').on('click', function () {
        renderProductCatalog();
        if (productCatalogModal) {
            productCatalogModal.show();
        }
    });

    $catalogSearch.on('input', renderProductCatalog);
    $branchSelect.on('change.catalog', renderProductCatalog);
    $catalogGrid.on('click', '[data-select-product]', function () {
        selectProductFromCatalog($(this).data('select-product'));
    });
    productCatalogModalEl?.addEventListener('shown.bs.modal', function () {
        $catalogSearch.trigger('focus');
    });

    /* ── Profit calculation ─────────────────  */
    function updateProfit() {
        const sale   = parseFloat($amountInput.val()) || 0;
        const profit = sale - currentCost;
        const markup = currentCost > 0 ? (profit / currentCost) * 100 : (sale > 0 ? 100 : 0);
        $profitDisplay.val(markup.toFixed(1) + '%');
        $profitDisplay.removeClass('text-success text-danger');
        $profitDisplay.addClass(profit >= 0 ? 'text-success' : 'text-danger');
        updateProfitModal(sale, currentCost, profit, markup);
        recalcInstallments();
    }

    function updateProfitModal(sale, cost, profit, markup) {
        const margin = sale > 0 ? (profit / sale) * 100 : 0;
        const fmt = n => '{{ $currencySymbol }} ' + Math.round(n).toLocaleString('es-BO');
        const fmtPct = n => (n >= 0 ? '' : '') + n.toFixed(1) + '%';
        const isLoss = profit < 0;
        const isZero = sale === 0 && cost === 0;

        // Big number
        document.getElementById('pdm-big-pct').textContent = isZero ? '—' : fmtPct(markup);
        document.getElementById('pdm-big-pct').style.color = isZero ? '#94a3b8' : (isLoss ? '#dc2626' : '#16a34a');

        // Header background & icon
        const header = document.getElementById('pdm-header');
        const iconWrap = document.getElementById('pdm-icon-wrap');
        if (isLoss) {
            header.style.background = 'linear-gradient(135deg,#fff5f5 0%,#fee2e2 100%)';
            iconWrap.style.background = '#fee2e2'; iconWrap.style.color = '#991b1b';
            iconWrap.innerHTML = '<i class="bi bi-graph-down-arrow"></i>';
        } else {
            header.style.background = 'linear-gradient(135deg,#f0faf5 0%,#e6f7ef 100%)';
            iconWrap.style.background = '#d1fae5'; iconWrap.style.color = '#065f46';
            iconWrap.innerHTML = '<i class="bi bi-graph-up-arrow"></i>';
        }

        // Subtitle
        const sub = document.getElementById('pdm-subtitle');
        sub.textContent = isZero ? 'Selecciona un producto y precio para ver el análisis.'
            : (isLoss ? 'Estás vendiendo por debajo del costo.' : 'Resumen de rentabilidad estimada.');

        // Metrics
        document.getElementById('pdm-sale').textContent = fmt(sale);
        document.getElementById('pdm-cost').textContent = fmt(cost);
        const profitEl = document.getElementById('pdm-profit-abs');
        profitEl.textContent = (profit >= 0 ? '+ ' : '- ') + fmt(Math.abs(profit));
        profitEl.style.color = isLoss ? '#dc2626' : '#16a34a';
        document.getElementById('pdm-margin').textContent = fmtPct(margin);

        // Bar
        const costPct = sale > 0 ? Math.min(100, Math.max(0, (cost / sale) * 100)) : 100;
        const profPct = Math.max(0, 100 - costPct);
        document.getElementById('pdm-bar-cost').style.width = costPct + '%';
        document.getElementById('pdm-bar-profit').style.left = costPct + '%';
        document.getElementById('pdm-bar-profit').style.width = profPct + '%';
        document.getElementById('pdm-bar-profit').style.background = isLoss ? '#ef4444' : '#22c55e';
        document.getElementById('pdm-bar-cost-lbl').textContent = fmt(cost);
        document.getElementById('pdm-bar-profit-lbl').textContent = (profit >= 0 ? '+ ' : '- ') + fmt(Math.abs(profit));
        document.getElementById('pdm-bar-profit-lbl').style.color = isLoss ? '#dc2626' : '#16a34a';

        // Contextual tip
        const tip = document.getElementById('pdm-tip');
        const tipText = document.getElementById('pdm-tip-text');
        let msg, tipStyle;
        if (isZero) {
            msg = 'Ingresa el precio de venta para ver el análisis completo.';
            tipStyle = 'background:#f8fafc;border-color:#e2e8f0;color:#64748b';
        } else if (isLoss) {
            msg = 'El precio de venta es menor al costo. Considera subir el precio para no perder dinero en esta transacción.';
            tipStyle = 'background:#fff5f5;border-color:#fecaca;color:#991b1b';
        } else if (markup < 10) {
            msg = 'Ganancia muy baja (menos del 10%). Considera si cubre los gastos operativos del negocio.';
            tipStyle = 'background:#fffbeb;border-color:#fde68a;color:#92400e';
        } else if (markup < 30) {
            msg = 'Ganancia moderada. Está dentro de márgenes aceptables para la mayoría de productos.';
            tipStyle = 'background:#f0fdf4;border-color:#bbf7d0;color:#166534';
        } else {
            msg = 'Excelente rentabilidad. El precio de venta genera un margen muy favorable sobre el costo.';
            tipStyle = 'background:#f0fdf4;border-color:#86efac;color:#14532d';
        }
        tip.style.cssText += ';' + tipStyle;
        tipText.textContent = msg;
        const tipIcon = tip.querySelector('i');
        if (tipIcon) tipIcon.className = isLoss ? 'bi bi-exclamation-triangle-fill flex-shrink-0 mt-1' : 'bi bi-lightbulb-fill flex-shrink-0 mt-1';
    }
    $amountInput.on('input', updateProfit);

    /* ── Payment type + Installments ─────── */
    function setPaymentType(type) {
        const isCredit = type === 'CREDITO';
        $paymentType.val(type);
        $btnCashPayment.toggleClass('active', !isCredit);
        $btnCreditPayment.toggleClass('active', isCredit);
        $creditPlan.toggleClass('d-none', !isCredit);

        if (isCredit && $installmentsBody.find('tr').length === 0) {
            addInstallmentRow();
        }
        toggleInstallmentRequirements(isCredit);
        recalcInstallments();
    }

    function toggleInstallmentRequirements(isCredit) {
        $installmentsBody.find('.js-inst-date').prop('required', isCredit);
        $installmentsBody.find('.js-inst-value').prop('required', isCredit);
    }

    function escapeHtml(text) {
        return String(text ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }

    function reindexInstallments() {
        $installmentsBody.find('tr').each(function (idx) {
            $(this).find('.js-inst-no').text(idx + 1);
            $(this).find('.js-inst-date').attr('name', `installments[${idx}][due_date]`);
            $(this).find('.js-inst-percentage').attr('name', `installments[${idx}][percentage]`);
            $(this).find('.js-inst-amount').attr('name', `installments[${idx}][amount]`);
        });
        toggleInstallmentRequirements($paymentType.val() === 'CREDITO');
    }

    function addInstallmentRow(data = {}) {
        const mode = data.mode === 'amount' ? 'amount' : 'percentage';
        const value = Number(data.value ?? (mode === 'percentage' ? 100 : 0));
        const dueDate = escapeHtml(data.due_date ?? '');

        const row = `
            <tr>
                <td class="js-inst-no fw-semibold">1</td>
                <td>
                    <input type="date" class="form-control form-control-sm js-inst-date" value="${dueDate}">
                </td>
                <td>
                    <select class="form-select form-select-sm js-inst-mode">
                        <option value="percentage" ${mode === 'percentage' ? 'selected' : ''}>Porcentaje</option>
                        <option value="amount" ${mode === 'amount' ? 'selected' : ''}>Monto</option>
                    </select>
                </td>
                <td>
                    <input type="number" min="0.01" step="0.01" class="form-control form-control-sm js-inst-value" value="${value > 0 ? value : ''}" placeholder="0.00">
                </td>
                <td class="text-end fw-semibold js-inst-percent-view">0.00%</td>
                <td class="text-end fw-semibold js-inst-amount-view">$ 0</td>
                <td class="text-end js-inst-principal-view" style="font-size:var(--fs-sm);color:#0d6efd;">—</td>
                <td class="text-end js-inst-interest-view"  style="font-size:var(--fs-sm);color:#dc3545;">—</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-icon-only js-remove-installment" title="Quitar cuota" aria-label="Quitar cuota">
                        <i class="bi bi-x"></i>
                    </button>
                </td>
                <td class="d-none">
                    <input type="hidden" class="js-inst-percentage" value="0">
                    <input type="hidden" class="js-inst-amount" value="0">
                </td>
            </tr>
        `;

        $installmentsBody.append(row);
        reindexInstallments();
        recalcInstallments();
    }

    function parseNumber(v) {
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : 0;
    }

    function getCreditAdditionalPercentage() {
        return Math.max(0, parseNumber($creditAdditionalPct.val()));
    }

    function getCreditAdditionalAmount(baseAmount) {
        const pct = getCreditAdditionalPercentage();
        return baseAmount * (pct / 100);
    }

    function getDownPayment() {
        return Math.max(0, parseNumber($downPayment.val()));
    }

    function getCreditTargetTotal() {
        const baseAmount = parseNumber($amountInput.val());
        if ($paymentType.val() !== 'CREDITO') return baseAmount;
        return baseAmount + getCreditAdditionalAmount(baseAmount);
    }

    function recalcInstallments() {
        const baseAmount   = parseNumber($amountInput.val());
        const additionalAmount = getCreditAdditionalAmount(baseAmount);
        const total        = getCreditTargetTotal();
        const downPmt      = getDownPayment();
        const financed     = Math.max(0, total - downPmt);

        if ($creditAdditionalAmount.length) {
            $creditAdditionalAmount.val(additionalAmount.toFixed(0));
        }
        if ($balanceToFinance.length) {
            $balanceToFinance.val(financed.toFixed(0));
        }

        const interestRatio = financed > 0 && additionalAmount > 0
            ? additionalAmount / financed
            : 0;

        let sumPercent = 0;
        let sumAmount  = 0;

        $installmentsBody.find('tr').each(function () {
            const $row = $(this);
            const mode  = $row.find('.js-inst-mode').val();
            const value = parseNumber($row.find('.js-inst-value').val());

            let pct = 0;
            let amt = 0;

            if (mode === 'percentage') {
                pct = value;
                amt = financed > 0 ? (financed * pct / 100) : 0;
            } else {
                amt = value;
                pct = financed > 0 ? (amt * 100 / financed) : 0;
            }

            const interest  = Math.round(amt * interestRatio * 100) / 100;
            const principal = Math.round((amt - interest) * 100) / 100;

            $row.find('.js-inst-percent-view').text(`${pct.toFixed(2)}%`);
            $row.find('.js-inst-amount-view').text(`{{ $currencySymbol }} ${amt.toFixed(0)}`);
            $row.find('.js-inst-percentage').val(pct.toFixed(4));
            $row.find('.js-inst-amount').val(amt.toFixed(2));

            if (amt > 0 && interestRatio > 0) {
                $row.find('.js-inst-principal-view').text(`{{ $currencySymbol }} ${principal.toFixed(0)}`);
                $row.find('.js-inst-interest-view').text(`{{ $currencySymbol }} ${interest.toFixed(0)}`);
            } else if (amt > 0) {
                $row.find('.js-inst-principal-view').text(`{{ $currencySymbol }} ${amt.toFixed(0)}`);
                $row.find('.js-inst-interest-view').text('—');
            } else {
                $row.find('.js-inst-principal-view').text('—');
                $row.find('.js-inst-interest-view').text('—');
            }

            sumPercent += pct;
            sumAmount  += amt;
        });

        const hasRows   = $installmentsBody.find('tr').length > 0;
        const noInstallmentsNeeded = financed <= 0;
        const percentOk = noInstallmentsNeeded ? true : Math.abs(sumPercent - 100) <= 0.1;
        const amountOk  = noInstallmentsNeeded ? true : Math.abs(sumAmount - financed) <= 0.1;
        const ready     = total > 0 && (noInstallmentsNeeded || (hasRows && percentOk && amountOk));

        if ($summaryBaseTotal.length) $summaryBaseTotal.text(`{{ $currencySymbol }} ${baseAmount.toFixed(0)}`);
        if ($summaryCreditExtra.length) $summaryCreditExtra.text(`{{ $currencySymbol }} ${additionalAmount.toFixed(0)}`);
        $summaryTotal.text(`{{ $currencySymbol }} ${total.toFixed(0)}`);
        if ($summaryDownPayment.length) $summaryDownPayment.text(`{{ $currencySymbol }} ${downPmt.toFixed(0)}`);
        if ($summaryBalance.length) $summaryBalance.text(`{{ $currencySymbol }} ${financed.toFixed(0)}`);
        $summaryPercent.text(`${sumPercent.toFixed(2)}%`);
        $summaryAmount.text(`{{ $currencySymbol }} ${sumAmount.toFixed(0)}`);
        $summaryState
            .text(ready ? 'Listo ✓' : 'Incompleto')
            .removeClass('text-danger text-success')
            .addClass(ready ? 'text-success' : 'text-danger');

        if ($paymentType.val() === 'CREDITO' && !ready) {
            const msg = financed <= 0
                ? 'El total financiado es cero o menor. Revisa el precio y la cuota inicial.'
                : `Las cuotas deben sumar 100% del saldo a financiar ({{ $currencySymbol }} ${financed.toFixed(0)}).`;
            $creditErrorBox.removeClass('d-none').text(msg);
        } else {
            $creditErrorBox.addClass('d-none').text('');
        }
    }

    $btnCashPayment.on('click', function () { setPaymentType('CONTADO'); });
    $btnCreditPayment.on('click', function () { setPaymentType('CREDITO'); });
    $addInstallmentBtn.on('click', function () {
        addInstallmentRow({ mode: 'percentage', value: 0 });
    });
    $installmentsBody.on('input change', '.js-inst-mode, .js-inst-value, .js-inst-date', recalcInstallments);
    $creditAdditionalPct.on('input', recalcInstallments);
    $downPayment.on('input', recalcInstallments);
    $installmentsBody.on('click', '.js-remove-installment', function () {
        $(this).closest('tr').remove();
        reindexInstallments();
        recalcInstallments();
    });

    if (oldInstallments.length) {
        oldInstallments.forEach(function (it) {
            const p = parseNumber(it.percentage);
            const a = parseNumber(it.amount);
            const useAmount = a > 0 && p <= 0;
            addInstallmentRow({
                mode: useAmount ? 'amount' : 'percentage',
                value: useAmount ? a : (p > 0 ? p : 0),
                due_date: it.due_date || '',
            });
        });
    }

    setPaymentType('{{ old('payment_type', \App\Models\Sale::PAYMENT_CASH) }}');

    /* ── Image dropzone ───────────────────── */
    function updateImageCount(files) {
        const count = files ? files.length : 0;
        const $grid = $('#sale-image-preview-grid');
        if (!count) {
            $imagesCount.text('Sin archivos seleccionados');
            $grid.hide().empty();
            return;
        }
        $imagesCount.text(`${count} archivo${count === 1 ? '' : 's'} seleccionado${count === 1 ? '' : 's'}`);
        $grid.empty();
        Array.from(files).forEach(function (file) {
            const $thumb = $('<div class="image-preview-thumb"></div>');
            const $img = $('<img alt="">');
            const reader = new FileReader();
            reader.onload = function (e) { $img.attr('src', e.target.result); };
            reader.readAsDataURL(file);
            $thumb.append($img);
            $grid.append($thumb);
        });
        const $clear = $('<button type="button" class="image-preview-clear"><i class="bi bi-x-circle me-1"></i>Limpiar selección</button>');
        $clear.on('click', function () { $imagesInput.val(''); updateImageCount(null); });
        $grid.append($clear);
        $grid.show();
    }
    if ($imagesDropzone.length && $imagesInput.length) {
        $imagesDropzone.on('click', () => $imagesInput.trigger('click'));
        $imagesInput.on('change', function () { updateImageCount(this.files); });
        $imagesDropzone.on('dragenter dragover', function (e) { e.preventDefault(); e.stopPropagation(); $imagesDropzone.addClass('is-dragover'); });
        $imagesDropzone.on('dragleave dragend drop', function (e) { e.preventDefault(); e.stopPropagation(); $imagesDropzone.removeClass('is-dragover'); });
        $imagesDropzone.on('drop', function (e) {
            const files = e.originalEvent?.dataTransfer?.files;
            if (files?.length) { $imagesInput[0].files = files; updateImageCount(files); }
        });
    }

    /* ── Sale images camera widget ─────────── */
    (function () {
        const btnUpload  = document.getElementById('si-btn-upload');
        const btnCamera  = document.getElementById('si-btn-camera');
        const uploadSec  = document.getElementById('si-upload-section');
        const cameraSec  = document.getElementById('si-camera-section');
        const video      = document.getElementById('si-video');
        const canvas     = document.getElementById('si-canvas');
        const captureBtn = document.getElementById('si-capture-btn');
        const flipBtn    = document.getElementById('si-flip-btn');
        const flash      = document.getElementById('si-flash');
        const errMsg     = document.getElementById('si-camera-error');
        if (!btnCamera) return;
        let stream = null, facingMode = 'environment';
        function stopImgCamera() {
            if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        }
        async function startImgCamera() {
            stopImgCamera();
            errMsg.classList.add('d-none');
            video.style.transform = facingMode === 'user' ? 'scaleX(-1)' : 'none';
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode }, audio: false });
                video.srcObject = stream;
            } catch (err) {
                errMsg.textContent = 'No se pudo acceder a la cámara: ' + err.message;
                errMsg.classList.remove('d-none');
                setImgMode('upload');
            }
        }
        function setImgMode(mode) {
            btnUpload.classList.toggle('active', mode === 'upload');
            btnCamera.classList.toggle('active', mode === 'camera');
            uploadSec.classList.toggle('d-none', mode !== 'upload');
            cameraSec.classList.toggle('d-none', mode !== 'camera');
            if (mode === 'camera') startImgCamera(); else stopImgCamera();
        }
        btnUpload.addEventListener('click', () => setImgMode('upload'));
        btnCamera.addEventListener('click', () => setImgMode('camera'));
        flipBtn.addEventListener('click', () => { facingMode = facingMode === 'user' ? 'environment' : 'user'; startImgCamera(); });
        captureBtn.addEventListener('click', () => {
            if (!stream) return;
            const w = video.videoWidth || 640, h = video.videoHeight || 480;
            canvas.width = w; canvas.height = h;
            const ctx = canvas.getContext('2d');
            if (facingMode === 'user') { ctx.save(); ctx.translate(w, 0); ctx.scale(-1, 1); ctx.drawImage(video, 0, 0, w, h); ctx.restore(); }
            else { ctx.drawImage(video, 0, 0, w, h); }
            flash.style.opacity = '1';
            setTimeout(() => { flash.style.opacity = '0'; }, 150);
            canvas.toBlob(function (blob) {
                const file = new File([blob], 'foto-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                const dt = new DataTransfer();
                Array.from($imagesInput[0].files).forEach(f => dt.items.add(f));
                dt.items.add(file);
                $imagesInput[0].files = dt.files;
                updateImageCount($imagesInput[0].files);
            }, 'image/jpeg', 0.92);
        });
        document.getElementById('sale-form').addEventListener('submit', stopImgCamera);
    })();

    /* ── Final form guard for credit mode ─── */
    $form.on('submit', function (e) {
        if ($paymentType.val() !== 'CREDITO') return;

        // Validar porcentaje adicional de crédito
        const creditPct = parseNumber($creditAdditionalPct.val());
        if (creditPct <= 0) {
            e.preventDefault();
            $creditAdditionalPct.addClass('is-invalid').trigger('focus');
            $creditErrorBox.removeClass('d-none').text('El porcentaje adicional de crédito es obligatorio y debe ser mayor a 0.');
            $creditPlan.removeClass('d-none');
            $('html, body').animate({ scrollTop: $creditAdditionalPct.closest('.sv-credit-panel').offset().top - 100 }, 250);
            return;
        }
        $creditAdditionalPct.removeClass('is-invalid');

        recalcInstallments();
        const total    = getCreditTargetTotal();
        const downPmt  = getDownPayment();
        const financed = Math.max(0, total - downPmt);
        const sumPercent = parseNumber($summaryPercent.text().replace('%', ''));
        const sumAmount  = parseNumber($summaryAmount.text().replace(/[^0-9.,-]/g, '').trim());
        const hasRows    = $installmentsBody.find('tr').length > 0;
        const noInstallmentsNeeded = financed <= 0;

        const valid = total > 0 && (noInstallmentsNeeded || (hasRows && Math.abs(sumPercent - 100) <= 0.1 && Math.abs(sumAmount - financed) <= 0.1));
        if (!valid) {
            e.preventDefault();
            const msg = financed <= 0
                ? 'El total financiado es cero o menor. Revisa el precio y la cuota inicial.'
                : `Revisa las cuotas: deben sumar 100% del saldo a financiar ({{ $currencySymbol }} ${financed.toFixed(0)}).`;
            $creditErrorBox.removeClass('d-none').text(msg);
            $creditPlan.removeClass('d-none');
            $('html, body').animate({ scrollTop: $creditPlan.offset().top - 100 }, 250);
        }
    });

    // Limpiar error is-invalid del porcentaje al corregir
    $creditAdditionalPct.on('input', function () {
        if (parseNumber($(this).val()) > 0) $(this).removeClass('is-invalid');
    });

    // ── Anti doble-envío: deshabilita el botón al enviar (tras validación) ──
    document.getElementById('sale-form').addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        const btn = this.querySelector('button[type="submit"]');
        if (!btn || btn.dataset.submitting) return;
        btn.dataset.submitting = '1';
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
    });
});

/* ── Shared fullscreen gallery (global — called from inline onclick) ── */
let _svGalleryCarousel = null;

function openSvGalleryModal(images, startIndex, name) {
    if (!images || !images.length) return;

    const modalEl = document.getElementById('svProductGalleryModal');
    if (!modalEl) return;

    const inner     = document.getElementById('svPglInner');
    const thumbs    = document.getElementById('svPglThumbs');
    const counter   = document.getElementById('svPglCounter');
    const prevBtn   = document.getElementById('svPgl-prev');
    const nextBtn   = document.getElementById('svPgl-next');
    const thumbsWrap= document.getElementById('svPgl-thumbs-wrap');

    document.getElementById('svProductGalleryModalLabel').textContent = name || 'Fotos del producto';

    inner.innerHTML = images.map((url, i) =>
        `<div class="carousel-item${i === startIndex ? ' active' : ''}">
            <img src="${url}" alt="Foto ${i + 1} - ${name}">
        </div>`
    ).join('');

    thumbs.innerHTML = images.map((url, i) =>
        `<div class="pgl-thumb${i === startIndex ? ' active' : ''}" data-index="${i}" title="Foto ${i + 1}">
            <img src="${url}" alt="Miniatura ${i + 1}">
        </div>`
    ).join('');

    thumbs.querySelectorAll('.pgl-thumb').forEach(function (t) {
        t.addEventListener('click', function () {
            if (_svGalleryCarousel) _svGalleryCarousel.to(parseInt(this.dataset.index, 10));
        });
    });

    const multipleImages = images.length > 1;
    prevBtn.classList.toggle('d-none', !multipleImages);
    nextBtn.classList.toggle('d-none', !multipleImages);
    thumbsWrap.classList.toggle('d-none', !multipleImages);
    counter.textContent = multipleImages ? `${startIndex + 1} / ${images.length}` : '1 foto';

    const carouselEl = document.getElementById('svProductGalleryCarousel');
    if (_svGalleryCarousel) _svGalleryCarousel.dispose();
    _svGalleryCarousel = new bootstrap.Carousel(carouselEl, { interval: false, wrap: true });

    carouselEl.addEventListener('slid.bs.carousel', function (e) {
        const idx = e.to;
        counter.textContent = `${idx + 1} / ${images.length}`;
        thumbs.querySelectorAll('.pgl-thumb').forEach(function (t, i) { t.classList.toggle('active', i === idx); });
        thumbs.querySelectorAll('.pgl-thumb')[idx]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    });

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
}
</script>
@endpush
