@extends('layouts.app')

@section('title', 'Nuevo Prestamo')

@section('page')
@php
    $hasPreselectedClient = (bool) ($preselectedClient ?? null);
@endphp
<div>

    {{-- Header --}}
    <div class="mb-2">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 fs-sm">
                    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}" class="text-decoration-none">Préstamos</a></li>
                    <li class="breadcrumb-item active">Nuevo</li>
                </ol>
            </nav>
    </div>
    <x-page-header title="Nuevo Préstamo" icon="cash-coin" :back="route('loans.index')" />

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
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted">Saldo disponible:</span>
                <span class="badge fs-base" style="background:{{ $sessionBranchColor }}22;color:{{ $sessionBranchColor }};border:1px solid {{ $sessionBranchColor }}44;">
                    {{ $currencySymbol }} {{ number_format((float) ($cashAvailable ?? 0), 2) }}
                </span>
            </div>
        </div>
    @else
        <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3">
            <i class="bi bi-exclamation-octagon me-1"></i>
            {{ $cashSessionError ?? 'Debes aperturar una caja para poder registrar préstamos.' }}
        </div>
    @endif

    <form action="{{ route('loans.store') }}" method="POST" id="loan-form" enctype="multipart/form-data">
        @csrf

        @if($hasPreselectedClient)
            <input type="hidden" name="client_id" value="{{ $preselectedClient->id }}">
            <div class="card border-0 shadow-sm mb-4 lp-client-locked">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-start gap-3">
                        @if($preselectedClient->photo_url)
                            <img src="{{ $preselectedClient->photo_url }}" alt="{{ $preselectedClient->name }}"
                                class="rounded-3 object-fit-cover flex-shrink-0"
                                style="width:56px;height:56px;border:2px solid #d6e9ff;">
                        @else
                            <span class="lp-icon-badge lp-client-locked-icon">
                                <i class="bi bi-person-check-fill"></i>
                            </span>
                        @endif
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <h6 class="mb-0 fw-bold">Cliente seleccionado</h6>
                                <span class="badge rounded-pill text-bg-success">Listo para registrar</span>
                            </div>
                            <p class="text-muted small mb-3">Este préstamo se registrará para este cliente.</p>
                            <div class="row g-2 small">
                                <div class="col-md-6 col-lg-3">
                                    <div class="lp-client-data-item">
                                        <span class="text-muted">Nombre</span>
                                        <div class="fw-semibold">{{ $preselectedClient->name }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="lp-client-data-item">
                                        <span class="text-muted">Documento</span>
                                        <div class="fw-semibold">{{ $preselectedClient->id_number ?: '-' }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="lp-client-data-item">
                                        <span class="text-muted">Teléfono</span>
                                        <div class="fw-semibold">{{ $preselectedClient->phone ?: '-' }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="lp-client-data-item">
                                        <span class="text-muted">Email</span>
                                        <div class="fw-semibold text-truncate">{{ $preselectedClient->email ?: '-' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-4">

            {{-- ── CLIENT CARD ─────────────────────────────── --}}
            @unless($hasPreselectedClient)
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">

                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="lp-icon-badge bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-person-fill"></i>
                                </span>
                                <h6 class="mb-0 fw-bold">Cliente</h6>
                            </div>

                            {{-- Segmented control --}}
                            <div class="k-seg w-100 mb-3">
                                <button type="button" class="btn btn-sm btn-secondary flex-fill lp-mode-btn active" id="btn-existing">
                                    <i class="bi bi-person-check me-1"></i>Existente
                                </button>
                                <button type="button" class="btn btn-sm btn-secondary flex-fill lp-mode-btn" id="btn-new">
                                    <i class="bi bi-person-plus me-1"></i>Nuevo
                                </button>
                            </div>

                            {{-- Existing client --}}
                            <div id="existing-section">
                                <label class="form-label small fw-semibold">Seleccionar cliente</label>
                                <select id="client_id" name="client_id" class="form-select form-select-sm @error('client_id') is-invalid @enderror">
                                    <option value="">Buscar cliente...</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ (string) old('client_id', request('client_id')) === (string) $client->id ? 'selected' : '' }}>
                                            {{ $client->name }}{{ $client->id_number ? ' · ' . $client->id_number : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="mt-2">
                                    <a href="{{ route('clients.create') }}" class="text-decoration-none small text-muted" target="_blank">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>Abrir módulo de clientes
                                    </a>
                                </div>
                            </div>

                            {{-- New client --}}
                            <div id="new-section" class="d-none">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Nombre completo <span class="text-danger">*</span></label>
                                        <input type="text" name="client_name"
                                            class="form-control form-control-sm @error('client_name') is-invalid @enderror"
                                            value="{{ old('client_name') }}" placeholder="Ej: Juan Pérez">
                                        @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Documento</label>
                                        <input type="text" name="client_id_number"
                                            class="form-control form-control-sm @error('client_id_number') is-invalid @enderror"
                                            value="{{ old('client_id_number') }}" placeholder="Cédula / RIF">
                                        @error('client_id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Teléfono</label>
                                        <input type="text" name="client_phone"
                                            class="form-control form-control-sm @error('client_phone') is-invalid @enderror"
                                            value="{{ old('client_phone') }}" placeholder="04xx-xxx-xxxx">
                                        @error('client_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Correo electrónico</label>
                                        <input type="email" name="client_email"
                                            class="form-control form-control-sm @error('client_email') is-invalid @enderror"
                                            value="{{ old('client_email') }}" placeholder="correo@ejemplo.com">
                                        @error('client_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Dirección</label>
                                        <input type="text" name="client_address"
                                            class="form-control form-control-sm @error('client_address') is-invalid @enderror"
                                            value="{{ old('client_address') }}" placeholder="Calle, ciudad...">
                                        @error('client_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Foto del cliente</label>
                                        <div class="k-seg w-100 mb-2">
                                            <button type="button" class="btn btn-sm btn-secondary flex-fill cp-mode-btn active" id="lc-btn-upload">
                                                <i class="bi bi-upload me-1"></i>Subir archivo
                                            </button>
                                            <button type="button" class="btn btn-sm btn-secondary flex-fill cp-mode-btn" id="lc-btn-camera">
                                                <i class="bi bi-camera-fill me-1"></i>Usar cámara
                                            </button>
                                        </div>
                                        <div id="lc-upload-section">
                                            <input type="file" name="client_photo" id="lc-photo-input"
                                                class="form-control form-control-sm @error('client_photo') is-invalid @enderror"
                                                accept="image/*">
                                            @error('client_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small class="text-muted">JPG, PNG o WebP · Máx. 5 MB</small>
                                        </div>
                                        <div id="lc-camera-section" class="d-none">
                                            <div class="cp-camera-wrap">
                                                <div class="cp-flash" id="lc-flash"></div>
                                                <video id="lc-video" class="cp-video" autoplay playsinline muted></video>
                                                <div class="cp-camera-controls">
                                                    <button type="button" class="cp-capture-btn" id="lc-capture-btn" title="Capturar foto">
                                                        <span class="cp-capture-ring"></span>
                                                    </button>
                                                    <button type="button" class="cp-flip-btn" id="lc-flip-btn" title="Cambiar cámara">
                                                        <i class="bi bi-arrow-repeat"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <canvas id="lc-canvas" class="d-none"></canvas>
                                        </div>
                                        <div id="lc-preview-section" class="cp-preview d-none">
                                            <img id="lc-preview-img" class="cp-preview-img" src="" alt="Vista previa">
                                            <div class="cp-preview-actions">
                                                <span class="cp-preview-label">
                                                    <i class="bi bi-check-circle-fill text-success me-1"></i>Foto lista
                                                </span>
                                                <x-btn variant="secondary" icon="arrow-counterclockwise" id="lc-clear-btn">Volver a tomar</x-btn>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @endunless

            {{-- ── LOAN CARD ────────────────────────────────── --}}
            <div class="{{ $hasPreselectedClient ? 'col-12' : 'col-lg-8' }}">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center gap-2 mb-4">
                            <span class="lp-icon-badge bg-success bg-opacity-10 text-success">
                                <i class="bi bi-wallet2"></i>
                            </span>
                            <h6 class="mb-0 fw-bold">Datos del Préstamo</h6>
                        </div>

                        {{-- § 1 — Sucursal y Categoría --}}
                        <p class="lp-section-label"><i class="bi bi-geo-alt-fill"></i> Sucursal y categoría</p>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Sucursal <span class="text-danger">*</span></label>
                                @if($sessionBranch ?? null)
                                    {{-- Branch locked to the open cash register --}}
                                    @php $sb = $sessionBranch; $sbColor = $sb->color ?? '#0d6efd'; @endphp
                                    <input type="hidden" name="branch_id" value="{{ $sb->id }}">
                                    <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                                         style="border:1.5px solid {{ $sbColor }}33; background:{{ $sbColor }}0d;">
                                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $sbColor }};flex-shrink:0;"></span>
                                        <span class="fw-semibold fs-base" >{{ $sb->name }}</span>
                                        <span class="ms-auto text-muted d-flex align-items-center gap-1 fs-xs" >
                                            <i class="bi bi-lock-fill"></i> asignada por caja
                                        </span>
                                    </div>
                                    <small class="text-muted fs-xs" >
                                        La sucursal corresponde a la caja aperturada:
                                        <strong>{{ $activeCashSession->cashRegister?->name }}</strong>
                                    </small>
                                @else
                                    <select name="branch_id" class="form-select form-select-sm @error('branch_id') is-invalid @enderror" required>
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
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Categoría <span class="text-danger">*</span></label>
                                <select name="credit_category_id" id="credit_category_id"
                                    class="form-select form-select-sm @error('credit_category_id') is-invalid @enderror" required>
                                    <option value="">Seleccionar categoría...</option>
                                    @foreach($creditCategories as $category)
                                        <option value="{{ $category->id }}" {{ (string) old('credit_category_id') === (string) $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('credit_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <input type="hidden" name="credit_category_rule_id" id="credit_category_rule_id" value="{{ old('credit_category_rule_id') }}">
                        <input type="hidden" name="interest_period" id="interest_period" value="{{ old('interest_period', 'monthly') }}">
                        <input type="hidden" name="term_months" id="term_months" value="{{ old('term_months', 1) }}">

                        {{-- § 3 — Producto por categoría --}}
                        <div id="product-card" class="lp-product-card mb-4 d-none">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="lp-icon-badge bg-warning bg-opacity-10 text-warning">
                                    <i class="bi bi-box-seam"></i>
                                </span>
                                <div>
                                    <h6 class="mb-0 fw-bold">Datos del producto</h6>
                                    <small class="text-muted">Completa los datos según la categoría seleccionada.</small>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">Precio referencial</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="1" min="0" name="product_price" id="product_price"
                                            class="form-control form-control-sm @error('product_price') is-invalid @enderror"
                                            value="{{ old('product_price') }}" placeholder="0">
                                        @error('product_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="product-category-fields d-none" id="product-fields-1">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" name="product_name" id="product_name"
                                            class="form-control form-control-sm @error('product_name') is-invalid @enderror"
                                            value="{{ old('product_name') }}" placeholder="Ej: Toyota Corolla">
                                        @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Marca <span class="text-danger">*</span></label>
                                        <input type="text" name="product_brand" id="product_brand"
                                            class="form-control form-control-sm @error('product_brand') is-invalid @enderror"
                                            value="{{ old('product_brand') }}" placeholder="Ej: Toyota">
                                        @error('product_brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Modelo (año) <span class="text-danger">*</span></label>
                                        <select name="product_model_year" id="product_model_year"
                                            class="form-select form-select-sm @error('product_model_year') is-invalid @enderror">
                                        </select>
                                        @error('product_model_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Placa <span class="text-danger">*</span></label>
                                        <input type="text" name="product_plate" id="product_plate"
                                            class="form-control form-control-sm @error('product_plate') is-invalid @enderror"
                                            value="{{ old('product_plate') }}" placeholder="Ej: AB123CD">
                                        @error('product_plate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Chasis <span class="text-danger">*</span></label>
                                        <input type="text" name="product_chassis" id="product_chassis"
                                            class="form-control form-control-sm @error('product_chassis') is-invalid @enderror"
                                            value="{{ old('product_chassis') }}" placeholder="Número de chasis">
                                        @error('product_chassis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Detalles</label>
                                        <textarea name="product_details" id="product_details_vehicle" rows="2"
                                            class="form-control form-control-sm @error('product_details') is-invalid @enderror"
                                            placeholder="Condición general, observaciones...">{{ old('product_details') }}</textarea>
                                        @error('product_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="product-category-fields d-none" id="product-fields-2">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Tipo de joya <span class="text-danger">*</span></label>
                                        <input type="text" name="product_item_type" id="product_item_type"
                                            class="form-control form-control-sm @error('product_item_type') is-invalid @enderror"
                                            value="{{ old('product_item_type') }}" placeholder="Ej: Collar, anillo, manilla...">
                                        @error('product_item_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Kilataje <span class="text-danger">*</span></label>
                                        <select name="product_karat" id="product_karat"
                                            class="form-select form-select-sm @error('product_karat') is-invalid @enderror">
                                            <option value="">Seleccionar...</option>
                                            @foreach(['10K','14K','18K','21K','22K','24K'] as $karat)
                                                <option value="{{ $karat }}" {{ old('product_karat') === $karat ? 'selected' : '' }}>{{ $karat }}</option>
                                            @endforeach
                                        </select>
                                        @error('product_karat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Tipo de material</label>
                                        <select name="product_material_type" id="product_material_type"
                                            class="form-select form-select-sm @error('product_material_type') is-invalid @enderror">
                                            <option value="">Seleccionar...</option>
                                            @foreach(['Oro', 'Plata', 'Platino', 'Acero', 'Otro'] as $material)
                                                <option value="{{ $material }}" {{ old('product_material_type') === $material ? 'selected' : '' }}>{{ $material }}</option>
                                            @endforeach
                                        </select>
                                        @error('product_material_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Detalles</label>
                                        <textarea name="product_details" id="product_details_jewel" rows="2"
                                            class="form-control form-control-sm @error('product_details') is-invalid @enderror"
                                            placeholder="Color, estado, piedras, observaciones...">{{ old('product_details') }}</textarea>
                                        @error('product_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <div class="product-category-fields d-none" id="product-fields-3">
                                <input type="hidden" name="product_name" id="product_name_garrafa" value="{{ old('product_name') }}">
                                
                                <label class="form-label small fw-semibold">Detalles</label>
                                <textarea name="product_details" id="product_details_garrafa" rows="2"
                                    class="form-control form-control-sm @error('product_details') is-invalid @enderror"
                                    placeholder="Estado de la garrafa, capacidad, observaciones...">{{ old('product_details') }}</textarea>
                                @error('product_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="product-category-fields d-none" id="product-fields-4">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" name="product_name" id="product_name_article"
                                            class="form-control form-control-sm @error('product_name') is-invalid @enderror"
                                            value="{{ old('product_name') }}" placeholder="Ej: Laptop Lenovo">
                                        @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Marca <span class="text-danger">*</span></label>
                                        <input type="text" name="product_brand" id="product_brand_article"
                                            class="form-control form-control-sm @error('product_brand') is-invalid @enderror"
                                            value="{{ old('product_brand') }}" placeholder="Ej: Lenovo">
                                        @error('product_brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Modelo</label>
                                        <input type="text" name="product_item_model" id="product_item_model"
                                            class="form-control form-control-sm @error('product_item_model') is-invalid @enderror"
                                            value="{{ old('product_item_model') }}" placeholder="Ej: ThinkPad E15">
                                        @error('product_item_model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Serial <span class="text-danger">*</span></label>
                                        <input type="text" name="product_serial_number" id="product_serial_number"
                                            class="form-control form-control-sm @error('product_serial_number') is-invalid @enderror"
                                            value="{{ old('product_serial_number') }}" placeholder="Número de serial">
                                        @error('product_serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Detalles</label>
                                        <textarea name="product_details" id="product_details_article" rows="2"
                                            class="form-control form-control-sm @error('product_details') is-invalid @enderror"
                                            placeholder="Condición, accesorios, observaciones...">{{ old('product_details') }}</textarea>
                                        @error('product_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">
                            <div>
                                <p class="lp-section-label mb-3"><i class="bi bi-camera-fill"></i> Fotos e Imágenes</p>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Adjuntar fotos del producto o documentos</label>
                                        <div class="k-seg w-100 mb-2">
                                            <button type="button" class="btn btn-sm btn-secondary flex-fill cp-mode-btn active" id="li-btn-upload"><i class="bi bi-upload me-1"></i>Subir archivos</button>
                                            <button type="button" class="btn btn-sm btn-secondary flex-fill cp-mode-btn" id="li-btn-camera"><i class="bi bi-camera-fill me-1"></i>Usar cámara</button>
                                        </div>
                                        <div id="li-upload-section">
                                            <div id="loan-image-dropzone" class="loan-image-dropzone">
                                                <div class="dropzone-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                                <div class="dropzone-title">Arrastra tus archivos aquí</div>
                                                <div class="dropzone-subtitle">o haz clic para seleccionarlos</div>
                                                <small id="loan-image-dropzone-count" class="text-muted d-block mt-1">Sin archivos seleccionados</small>
                                            </div>
                                            <input type="file" id="loan-images-input" name="images[]" class="d-none @error('images.*') is-invalid @enderror" multiple accept="image/*">
                                        </div>
                                        <div id="li-camera-section" class="d-none">
                                            <div class="cp-camera-wrap">
                                                <div class="cp-flash" id="li-flash"></div>
                                                <video id="li-video" class="cp-video" autoplay playsinline muted></video>
                                                <div class="cp-camera-controls">
                                                    <button type="button" class="cp-capture-btn" id="li-capture-btn" title="Capturar foto"><span class="cp-capture-ring"></span></button>
                                                    <button type="button" class="cp-flip-btn" id="li-flip-btn" title="Cambiar cámara"><i class="bi bi-arrow-repeat"></i></button>
                                                </div>
                                            </div>
                                            <canvas id="li-canvas" class="d-none"></canvas>
                                            <small id="li-camera-error" class="text-danger d-none"></small>
                                        </div>
                                        <div id="loan-image-preview-grid" class="image-preview-grid mt-2" style="display:none;"></div>
                                        <small class="text-muted">Puedes seleccionar múltiples imágenes (JPG, PNG, WebP). Máx. 5MB cada una.</small>
                                        @error('images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- § 4 — Condiciones Financieras --}}
                        <p class="lp-section-label"><i class="bi bi-calculator-fill"></i> Condiciones financieras</p>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Monto <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text fw-bold text-primary">$</span>
                                    <input type="number" name="amount" id="amount" step="1" min="1"
                                        @if($activeCashSession)max="{{ (float) ($cashAvailable ?? 0) }}"@endif
                                        class="form-control form-control-sm @error('amount') is-invalid @enderror"
                                        value="{{ old('amount') }}" placeholder="0" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                @if($activeCashSession)
                                    <small class="text-muted" id="amount-hint">Máximo disponible en caja: {{ $currencySymbol }} {{ number_format((float) ($cashAvailable ?? 0), 2) }}</small>
                                    <div class="text-danger small mt-1 d-none" id="amount-over-warning">
                                        <i class="bi bi-exclamation-triangle-fill me-1"></i>El monto supera el saldo disponible en caja.
                                    </div>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Tasa de interés <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="number" name="interest_rate" id="interest_rate" step="1" min="1" max="100"
                                        class="form-control form-control-sm @error('interest_rate') is-invalid @enderror"
                                        value="{{ old('interest_rate') }}" placeholder="0" required>
                                    <span class="input-group-text">%</span>
                                    @error('interest_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        {{-- Live preview box --}}
                        <div id="loan-preview" class="lp-preview mb-4" style="display:none;">
                            <div class="row g-0 align-items-center">
                                <div class="col">
                                    <div class="lp-preview-label">Interés del primer periodo</div>
                                    <div class="lp-preview-amount" id="preview-monthly">$0.00</div>
                                </div>
                                <div class="vr mx-3 opacity-25"></div>
                                <div class="col text-end">
                                    <div class="lp-preview-label">Capital + primer interés</div>
                                    <div class="fw-bold" id="preview-total">$0.00</div>
                                    <small class="text-muted" id="preview-term"></small>
                                </div>
                            </div>
                        </div>

                        {{-- § 5 — Fecha de inicio --}}
                        <p class="lp-section-label mb-2"><i class="bi bi-calendar-event-fill"></i> Fecha de inicio</p>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                @if($canSetStartDate)
                                    <label class="form-label small fw-semibold">
                                        Fecha de inicio del préstamo
                                        <span class="badge bg-primary ms-1 fs-xs" >Permiso activo</span>
                                    </label>
                                    <input type="date" name="start_date" id="start_date"
                                        class="form-control form-control-sm @error('start_date') is-invalid @enderror"
                                        value="{{ old('start_date', now()->toDateString()) }}">
                                    @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <small class="text-muted">Puedes cambiar la fecha de inicio del préstamo.</small>
                                @else
                                    <label class="form-label small fw-semibold">Fecha de inicio del préstamo</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-end-0 text-muted">
                                            <i class="bi bi-lock-fill"></i>
                                        </span>
                                        <input type="text" class="form-control form-control-sm bg-light border-start-0"
                                            value="{{ now()->format('d/m/Y') }}" readonly>
                                    </div>
                                    <small class="text-muted">La fecha se establece automáticamente al día de hoy.</small>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- Submit bar --}}
        <div class="mt-4 p-3 bg-white border rounded-3 shadow-sm d-flex align-items-center gap-3 flex-wrap">
            <x-btn type="submit" size="md" icon="check2-circle" :disabled="!$activeCashSession">Registrar préstamo</x-btn>
            <x-btn variant="secondary" size="md" icon="x" :href="route('loans.index')">Cancelar</x-btn>
            <small class="text-muted ms-auto"><span class="text-danger">*</span> Campos obligatorios</small>
        </div>
    </form>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .lp-icon-badge {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .lp-section-label {
        font-size: var(--fs-sm);
        font-weight: 700;
        letter-spacing: .02em;
        color: #495057;
        margin-bottom: .6rem;
        display: flex;
        align-items: center;
        gap: .45rem;
    }
    .lp-preview {
        border: 1px solid #e6eefc;
        background: linear-gradient(135deg, #f7faff, #f3f9ff);
        border-radius: .8rem;
        padding: .9rem 1rem;
    }
    .lp-preview-label {
        color: #6c757d;
        font-size: var(--fs-xs);
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .lp-preview-amount {
        font-size: var(--fs-lg);
        font-weight: 700;
        line-height: 1.15;
        color: #0d6efd;
    }

    .lp-product-card {
        border: 1px solid #d8e4f7;
        border-radius: 14px;
        padding: 1rem;
        background: linear-gradient(145deg, #f8fbff 0%, #f1f7ff 100%);
    }

    .lp-product-hint {
        border: 1px dashed #bfd7fb;
        background: #f2f8ff;
        border-radius: .65rem;
        padding: .55rem .65rem;
        color: #355070;
        font-size: var(--fs-sm);
    }

    .select2-container .select2-selection--single {
        height: 38px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }

    .loan-image-dropzone {
        border: 1px dashed #b9c6d8;
        background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 100%);
        border-radius: 12px;
        padding: 16px 12px;
        text-align: center;
        cursor: pointer;
        transition: all .2s ease;
        margin-bottom: .35rem;
    }

    .loan-image-dropzone:hover,
    .loan-image-dropzone.is-dragover {
        border-color: #0d6efd;
        background: #eaf2ff;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, .08);
    }

    .loan-image-dropzone .dropzone-icon {
        font-size: var(--fs-lg);
        color: #0d6efd;
        line-height: 1;
        margin-bottom: .3rem;
    }

    .loan-image-dropzone .dropzone-title {
        font-weight: 600;
        color: #1f2d3d;
        font-size: var(--fs-base);
        margin-bottom: .1rem;
    }

    .loan-image-dropzone .dropzone-subtitle {
        color: #5b6573;
        font-size: var(--fs-xs);
    }

    .lp-client-locked {
        background: linear-gradient(135deg, #f5fbff 0%, #edf7ff 100%);
        border: 1px solid #d6e9ff !important;
    }

    .lp-client-locked-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: linear-gradient(135deg, #e8f3ff 0%, #d8eaff 100%);
        color: #0d6efd;
        font-size: var(--fs-md);
        flex-shrink: 0;
    }

    .lp-client-data-item {
        background: rgba(255, 255, 255, .78);
        border: 1px solid #e3edf9;
        border-radius: .65rem;
        padding: .55rem .65rem;
        min-height: 62px;
    }
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
        font-size: var(--fs-xs);
        border-radius: 999px;
        border: 1px solid #efc6cc;
        background: #fff5f6;
        color: #c23e4d;
        cursor: pointer;
        font-weight: 600;
        line-height: 1.4;
    }

    /* ── Camera / photo widget ─────────────────── */
    .cp-camera-wrap {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        background: #0a0a0a;
        aspect-ratio: 4/3;
        width: 60%;
        margin: 0 auto;
    }
    .cp-video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transform: scaleX(-1);
    }
    .cp-flash {
        position: absolute;
        inset: 0;
        background: #fff;
        opacity: 0;
        pointer-events: none;
        z-index: 10;
        transition: opacity .12s ease;
    }
    .cp-camera-controls {
        position: absolute;
        bottom: 12px;
        left: 0;
        right: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        z-index: 5;
    }
    .cp-capture-btn {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        background: rgba(255,255,255,.9);
        border: none;
        box-shadow: 0 2px 12px rgba(0,0,0,.35);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform .1s;
    }
    .cp-capture-btn:active { transform: scale(.9); }
    .cp-capture-ring {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 3px solid #0d6efd;
        display: block;
    }
    .cp-flip-btn {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: rgba(255,255,255,.7);
        border: none;
        color: #333;
        font-size: var(--fs-md);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background .15s;
    }
    .cp-flip-btn:hover { background: rgba(255,255,255,.9); }
    .cp-preview {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        background: #f8f9fa;
        border: 1.5px solid #d0e6ff;
    }
    .cp-preview-img {
        width: 100%;
        display: block;
        border-radius: 10px 10px 0 0;
        object-fit: cover;
        max-height: 200px;
    }
    .cp-preview-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: .5rem .75rem;
        background: #fff;
    }
    .cp-preview-label {
        font-size: var(--fs-sm);
        font-weight: 600;
        color: #1f2d3d;
    }
</style>
@endpush

@php
    $rulesMap = $creditCategories->mapWithKeys(function ($category) {
        return [
            $category->id => $category->rules->map(function ($rule) {
                return [
                    'id' => $rule->id,
                    'name' => $rule->name ?: ('Regla #' . $rule->id),
                    'interest_rate' => (float) $rule->interest_rate,
                    'interest_period' => $rule->interest_period,
                    'term_months_limit' => (int) $rule->term_months_limit,
                    'min_amount' => $rule->min_amount,
                    'max_amount' => $rule->max_amount,
                ];
            })->values()->all(),
        ];
    })->toArray();
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(function () {
        const $clientSelect = $('#client_id');
        const $existingSection = $('#existing-section');
        const $newSection = $('#new-section');
        const $btnExisting = $('#btn-existing');
        const $btnNew = $('#btn-new');
        const $categorySelect = $('#credit_category_id');
        const $ruleInput = $('#credit_category_rule_id');
        const $interestInput = $('input[name="interest_rate"]');
        const $periodInput = $('#interest_period');
        const $termInput = $('#term_months');
        const $amountInput = $('#amount');
        const $preview = $('#loan-preview');
        const $previewMonthly = $('#preview-monthly');
        const $previewTotal = $('#preview-total');
        const $previewTerm = $('#preview-term');
        const $productCard = $('#product-card');
        const $productFields = $('.product-category-fields');
        const $productModelYear = $('#product_model_year');
        const $productPrice = $('#product_price');
        const $garrafaProductName = $('#product_name_garrafa');
        const $imagesInput = $('#loan-images-input');
        const $imagesDropzone = $('#loan-image-dropzone');
        const $imagesCount = $('#loan-image-dropzone-count');
        const rulesMap = @json($rulesMap);
        const hasPreselectedClient = @json($hasPreselectedClient);

        function updateImageCount(files) {
            const count = files ? files.length : 0;
            const $grid = $('#loan-image-preview-grid');
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
            $imagesDropzone.on('click', function () {
                $imagesInput.trigger('click');
            });

            $imagesInput.on('change', function () {
                updateImageCount(this.files);
            });

            $imagesDropzone.on('dragenter dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $imagesDropzone.addClass('is-dragover');
            });

            $imagesDropzone.on('dragleave dragend drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $imagesDropzone.removeClass('is-dragover');
            });

            $imagesDropzone.on('drop', function (e) {
                const files = e.originalEvent?.dataTransfer?.files;
                if (!files || !files.length) {
                    return;
                }
                $imagesInput[0].files = files;
                updateImageCount(files);
            });
        }

        if ($clientSelect.length) {
            $clientSelect.select2({
                placeholder: 'Buscar cliente...',
                allowClear: true,
                width: '100%'
            });
        }

        function populateModelYears() {
            if (!$productModelYear.length) {
                return;
            }

            const currentYear = new Date().getFullYear();
            let options = '<option value="">Seleccionar...</option>';
            for (let year = currentYear; year >= 1980; year -= 1) {
                options += `<option value="${year}">${year}</option>`;
            }
            $productModelYear.html(options);

            const oldYear = '{{ old('product_model_year') }}';
            if (oldYear) {
                $productModelYear.val(oldYear);
            }

            $productModelYear.select2({
                placeholder: 'Año',
                width: '100%'
            });
        }

        function selectedCategoryName() {
            return $categorySelect.find(':selected').text().trim();
        }

        function applyProductLayoutByCategory(categoryId) {
            $productFields.addClass('d-none');
            $productFields.find('input,select,textarea').prop('disabled', true);

            if (!categoryId) {
                $productCard.addClass('d-none');
                $productPrice.prop('disabled', true);
                return;
            }

            $productCard.removeClass('d-none');
            $productPrice.prop('disabled', false);

            // Solo 1/2/3 tienen su propio layout; cualquier otra categoría usa el
            // genérico de Artículos (4).
            const layoutCat = ['1', '2', '3'].includes(String(categoryId)) ? String(categoryId) : '4';
            const $activeFields = $(`#product-fields-${layoutCat}`);
            $activeFields.removeClass('d-none');
            $activeFields.find('input,select,textarea').prop('disabled', false);

            if (layoutCat === '3' && $garrafaProductName.length) {
                $garrafaProductName.val(selectedCategoryName() || 'Garrafas');
            }

            const lockInterest = ['3', '4'].includes(layoutCat);
            $interestInput.prop('disabled', lockInterest).toggleClass('bg-light', lockInterest);
        }

        function applyFirstRuleByCategory(categoryId) {
            const rules = categoryId ? (rulesMap[categoryId] || []) : [];
            if (!rules.length) {
                $ruleInput.val('');
                return;
            }

            const oldRule = '{{ old('credit_category_rule_id') }}';
            const selectedRule = rules.find(rule => String(rule.id) === String(oldRule)) || rules[0];
            const safeInterest = Math.min(100, Math.max(1, parseInt(selectedRule.interest_rate, 10) || 1));

            $ruleInput.val(selectedRule.id);
            $interestInput.val(safeInterest);
            $periodInput.val(selectedRule.interest_period);
            $termInput.val(selectedRule.term_months_limit);
        }

        function sanitizeInterestRate() {
            const raw = String($interestInput.val() ?? '').trim();
            if (raw === '') {
                return;
            }

            const normalized = raw.replace(/[^0-9]/g, '');
            if (normalized === '') {
                $interestInput.val('');
                return;
            }

            const value = Math.min(100, Math.max(1, parseInt(normalized, 10) || 1));
            $interestInput.val(value);
        }

        function setClientMode(mode) {
            if (hasPreselectedClient) {
                return;
            }

            const useNew = mode === 'new';

            $btnExisting.toggleClass('active', !useNew);
            $btnNew.toggleClass('active', useNew);
            $existingSection.toggleClass('d-none', useNew);
            $newSection.toggleClass('d-none', !useNew);

            if (useNew) {
                $clientSelect.val('').trigger('change');
            }
        }

        $btnExisting.on('click', function () { setClientMode('existing'); });
        $btnNew.on('click', function () { setClientMode('new'); });

        $clientSelect.on('change', function () {
            const hasClient = !!$(this).val();
            if (hasClient) {
                setClientMode('existing');
            }
        });

        $categorySelect.on('change', function () {
            const categoryId = $(this).val();
            applyProductLayoutByCategory(categoryId);
            applyFirstRuleByCategory(categoryId);
            updatePreview();
        });

        function calculatePeriodInterest(amount, ratePercent) {
            const principal = parseFloat(amount || 0);
            const rate = parseFloat(ratePercent || 0);
            if (!principal || !rate) {
                return 0;
            }
            return principal * (rate / 100);
        }

        function fmtMoney(v) {
            return '{{ $currencySymbol }} ' + (Number(v || 0)).toLocaleString('es-VE', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        }

        function updatePreview() {
            const amount = parseFloat($amountInput.val() || 0);
            const rate = parseFloat($interestInput.val() || 0);
            const period = $periodInput.val();
            const months = parseInt($termInput.val() || 0, 10);

            if (!amount || !months) {
                $preview.hide();
                return;
            }

            const periodInterest = calculatePeriodInterest(amount, rate);
            const total = amount + periodInterest;

            $previewMonthly.text(fmtMoney(periodInterest));
            $previewTotal.text(fmtMoney(total));
            const periodLabel = period === 'monthly'
                ? 'mensual'
                : period === 'quarterly'
                    ? 'trimestral'
                    : period === 'semiannual'
                        ? 'semestral'
                        : 'anual';
            $previewTerm.text(`${months} meses · Primer interés ${periodLabel} sobre capital (${fmtMoney(amount)})`);
            $preview.show();
        }

        $amountInput.on('input', updatePreview);
        $interestInput.on('input', function () {
            sanitizeInterestRate();
            updatePreview();
        });
        $interestInput.on('blur', sanitizeInterestRate);
        $periodInput.on('change', updatePreview);
        $termInput.on('change', updatePreview);

        const oldClientId = '{{ old('client_id', request('client_id')) }}';
        const oldClientName = '{{ old('client_name') }}';
        if (!hasPreselectedClient) {
            setClientMode(oldClientId ? 'existing' : (oldClientName ? 'new' : 'existing'));
        }
        if ($clientSelect.length && oldClientId && !$clientSelect.val()) {
            $clientSelect.val(oldClientId).trigger('change');
        }

        populateModelYears();
        applyProductLayoutByCategory($categorySelect.val());
        applyFirstRuleByCategory($categorySelect.val());

        updatePreview();

        // ── Loan images camera widget ──────────────────────────────
        (function () {
            const btnUpload  = document.getElementById('li-btn-upload');
            const btnCamera  = document.getElementById('li-btn-camera');
            const uploadSec  = document.getElementById('li-upload-section');
            const cameraSec  = document.getElementById('li-camera-section');
            const video      = document.getElementById('li-video');
            const canvas     = document.getElementById('li-canvas');
            const captureBtn = document.getElementById('li-capture-btn');
            const flipBtn    = document.getElementById('li-flip-btn');
            const flash      = document.getElementById('li-flash');
            const errMsg     = document.getElementById('li-camera-error');
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
            document.getElementById('loan-form').addEventListener('submit', stopImgCamera);
        })();
    });

    // ── New-client photo / camera widget ──────────────────────────────
    (function () {
        const btnUpload   = document.getElementById('lc-btn-upload');
        const btnCamera   = document.getElementById('lc-btn-camera');
        const uploadSec   = document.getElementById('lc-upload-section');
        const cameraSec   = document.getElementById('lc-camera-section');
        const previewSec  = document.getElementById('lc-preview-section');
        const video       = document.getElementById('lc-video');
        const canvas      = document.getElementById('lc-canvas');
        const captureBtn  = document.getElementById('lc-capture-btn');
        const flipBtn     = document.getElementById('lc-flip-btn');
        const clearBtn    = document.getElementById('lc-clear-btn');
        const flash       = document.getElementById('lc-flash');
        const photoInput  = document.getElementById('lc-photo-input');
        const previewImg  = document.getElementById('lc-preview-img');

        if (!btnUpload) return; // not rendered (preselected client)

        let stream = null;
        let facingMode = 'user';
        let activeMode = 'upload'; // 'upload' | 'camera'

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(t => t.stop());
                stream = null;
            }
        }

        async function startCamera() {
            stopCamera();
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode }, audio: false });
                video.srcObject = stream;
            } catch (err) {
                alert('No se pudo acceder a la cámara: ' + err.message);
                setMode('upload');
            }
        }

        function setMode(mode) {
            activeMode = mode;
            btnUpload.classList.toggle('active', mode === 'upload');
            btnCamera.classList.toggle('active', mode === 'camera');
            uploadSec.classList.toggle('d-none', mode !== 'upload');
            cameraSec.classList.toggle('d-none', mode !== 'camera');
            previewSec.classList.add('d-none');

            if (mode === 'camera') {
                startCamera();
            } else {
                stopCamera();
            }
        }

        function showPreview(src) {
            previewImg.src = src;
            uploadSec.classList.add('d-none');
            cameraSec.classList.add('d-none');
            previewSec.classList.remove('d-none');
        }

        btnUpload.addEventListener('click', () => setMode('upload'));
        btnCamera.addEventListener('click', () => setMode('camera'));

        flipBtn.addEventListener('click', () => {
            facingMode = facingMode === 'user' ? 'environment' : 'user';
            startCamera();
        });

        captureBtn.addEventListener('click', () => {
            if (!stream) return;
            const w = video.videoWidth || 640;
            const h = video.videoHeight || 480;
            canvas.width  = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');
            ctx.save();
            ctx.translate(w, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(video, 0, 0, w, h);
            ctx.restore();

            flash.style.opacity = '1';
            setTimeout(() => { flash.style.opacity = '0'; }, 150);

            canvas.toBlob(function (blob) {
                const file = new File([blob], 'captura.jpg', { type: 'image/jpeg' });
                const dt = new DataTransfer();
                dt.items.add(file);
                photoInput.files = dt.files;

                const url = URL.createObjectURL(blob);
                showPreview(url);
                stopCamera();
            }, 'image/jpeg', 0.92);
        });

        clearBtn.addEventListener('click', () => {
            photoInput.value = '';
            previewImg.src = '';
            setMode(activeMode === 'upload' ? 'upload' : 'camera');
        });

        // Stop camera if new-client section is hidden (user switches to existing client)
        const observer = new MutationObserver(() => {
            const newSection = document.getElementById('new-section');
            if (newSection && newSection.classList.contains('d-none')) {
                stopCamera();
            }
        });
        const newSection = document.getElementById('new-section');
        if (newSection) {
            observer.observe(newSection, { attributes: true, attributeFilter: ['class'] });
        }
    })();

    // ── Validación de saldo de caja: no desembolsar más de lo disponible ──
    (function () {
        const cashAvailable = @json($cashAvailable ?? null);
        const form = document.getElementById('loan-form');
        const amountInput = document.getElementById('amount');
        if (cashAvailable === null || !form || !amountInput) return;
        const warning = document.getElementById('amount-over-warning');
        const submitBtn = form.querySelector('button[type="submit"]');

        function checkAmount() {
            const amt = parseFloat(amountInput.value || 0);
            const over = amt > cashAvailable;
            if (warning) warning.classList.toggle('d-none', !over);
            amountInput.classList.toggle('is-invalid', over);
            if (submitBtn) submitBtn.disabled = over;
            return !over;
        }

        amountInput.addEventListener('input', checkAmount);
        form.addEventListener('submit', function (e) {
            if (!checkAmount()) {
                e.preventDefault();
                amountInput.focus();
            }
        });
    })();

    // ── Anti doble-envío: deshabilita el botón al enviar el formulario ──
    (function () {
        const form = document.getElementById('loan-form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            const btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.dataset.submitting) return;
            btn.dataset.submitting = '1';
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
        });
    })();
</script>
@endpush
