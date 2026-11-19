@extends('layouts.app')

@section('title', 'Nueva Compra')

@section('page')
@php
    $preselectedClientId = (int) old('client_id', request('client_id'));
    $preselectedClient = $preselectedClientId > 0 ? $clients->firstWhere('id', $preselectedClientId) : null;
    $hasPreselectedClient = $preselectedClientId > 0;
    $fromContext = old('from_context', $fromContext ?? request('from'));
    $backUrl = $backUrl ?? route('purchases.index');
    $backLabel = $backLabel ?? 'Volver a compras';
@endphp
<div>

    {{-- Header --}}
    <x-page-header title="Nueva Compra" icon="bag-check">
        <x-btn variant="secondary" icon="arrow-left" :href="$backUrl">{{ $backLabel }}</x-btn>
    </x-page-header>

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
                <span class="badge" style="background:{{ $sessionBranchColor }}22;color:{{ $sessionBranchColor }};border:1px solid {{ $sessionBranchColor }}44;font-size:var(--fs-base);">
                    {{ $currencySymbol }} {{ number_format((float) ($cashAvailable ?? 0), 2) }}
                </span>
            </div>
        </div>
    @else
        <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3">
            <i class="bi bi-exclamation-octagon me-1"></i>
            {{ $cashSessionError ?? 'Debes aperturar una caja para poder registrar compras.' }}
        </div>
    @endif

    <form action="{{ route('purchases.store') }}" method="POST" id="purchase-form" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="from_context" value="{{ $fromContext }}">

        @if($hasPreselectedClient)
            <input type="hidden" id="client_id" name="client_id" value="{{ $preselectedClientId }}">
            <div class="card border-0 shadow-sm mb-4 pc-client-locked">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap align-items-start gap-3">
                        @if($preselectedClient?->photo_url)
                            <img src="{{ $preselectedClient->photo_url }}" alt="{{ $preselectedClient->name }}"
                                class="rounded-3 object-fit-cover flex-shrink-0"
                                style="width:56px;height:56px;border:2px solid #d6e9ff;">
                        @else
                            <span class="pc-icon-badge pc-client-locked-icon">
                                <i class="bi bi-person-check-fill"></i>
                            </span>
                        @endif
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <h6 class="mb-0 fw-bold">Vendedor / Cliente seleccionado</h6>
                                <span class="badge rounded-pill text-bg-success">Listo</span>
                            </div>
                            <p class="text-muted small mb-3">Esta compra se registrará con este vendedor.</p>
                            <div class="row g-2 small">
                                <div class="col-md-6 col-lg-3">
                                    <div class="pc-client-data-item">
                                        <div class="text-muted mb-1">Nombre</div>
                                        <div class="fw-semibold">{{ $preselectedClient?->name ?? ('Cliente #' . $preselectedClientId) }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="pc-client-data-item">
                                        <div class="text-muted mb-1">CI / NIT</div>
                                        <div class="fw-semibold">{{ $preselectedClient?->id_number ?: '-' }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="pc-client-data-item">
                                        <div class="text-muted mb-1">Teléfono</div>
                                        <div class="fw-semibold">{{ $preselectedClient?->phone ?: '-' }}</div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="pc-client-data-item">
                                        <div class="text-muted mb-1">Email</div>
                                        <div class="fw-semibold">{{ $preselectedClient?->email ?: '-' }}</div>
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
                                <span class="pc-icon-badge bg-primary bg-opacity-10 text-primary">
                                    <i class="bi bi-person-fill"></i>
                                </span>
                                <h6 class="mb-0 fw-bold">Vendedor / Cliente</h6>
                            </div>

                            {{-- Segmented control --}}
                            <div class="pc-mode-toggle mb-3">
                                <button type="button" class="pc-mode-btn active" id="btn-existing">
                                    <i class="bi bi-person-check me-1"></i>Existente
                                </button>
                                <button type="button" class="pc-mode-btn" id="btn-new">
                                    <i class="bi bi-person-plus me-1"></i>Nuevo
                                </button>
                            </div>

                            {{-- Existing client --}}
                            <div id="existing-section">
                                <label class="form-label small fw-semibold">Seleccionar</label>
                                <select id="client_id" name="client_id" class="form-select form-select-sm @error('client_id') is-invalid @enderror">
                                    <option value="">Buscar vendedor / cliente...</option>
                                    @foreach($clients as $client)
                                        <option value="{{ $client->id }}" {{ (string) old('client_id') === (string) $client->id ? 'selected' : '' }}>
                                            {{ $client->name }} {{ $client->id_number ? '(' . $client->id_number . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('client_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            {{-- New client --}}
                            <div id="new-section" class="d-none">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" name="client_name" class="form-control form-control-sm @error('client_name') is-invalid @enderror"
                                            value="{{ old('client_name') }}" placeholder="Nombre completo">
                                        @error('client_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">CI / NIT</label>
                                        <input type="text" name="client_id_number" class="form-control form-control-sm"
                                            value="{{ old('client_id_number') }}" placeholder="Documento">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small fw-semibold">Teléfono</label>
                                        <input type="text" name="client_phone" class="form-control form-control-sm"
                                            value="{{ old('client_phone') }}" placeholder="Teléfono">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Email</label>
                                        <input type="email" name="client_email" class="form-control form-control-sm"
                                            value="{{ old('client_email') }}" placeholder="correo@ejemplo.com">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Dirección</label>
                                        <input type="text" name="client_address" class="form-control form-control-sm"
                                            value="{{ old('client_address') }}" placeholder="Dirección">
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            @endunless

            {{-- ── PURCHASE CARD ────────────────────────────── --}}
            <div class="{{ $hasPreselectedClient ? 'col-12' : 'col-lg-8' }}">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center gap-2 mb-4">
                            <span class="pc-icon-badge bg-success bg-opacity-10 text-success">
                                <i class="bi bi-bag-check"></i>
                            </span>
                            <h6 class="mb-0 fw-bold">Datos de la Compra</h6>
                        </div>

                        {{-- § 1 — Sucursal y Categoría --}}
                        <p class="pc-section-label"><i class="bi bi-geo-alt-fill"></i> Sucursal y categoría</p>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Sucursal <span class="text-danger">*</span></label>
                                @if($sessionBranch ?? null)
                                    @php $sb = $sessionBranch; $sbColor = $sb->color ?? '#0d6efd'; @endphp
                                    <input type="hidden" name="branch_id" value="{{ $sb->id }}">
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

                        {{-- § 2 — Producto por categoría --}}
                        <div id="product-card" class="pc-product-card mb-4 d-none">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <span class="pc-icon-badge bg-warning bg-opacity-10 text-warning">
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

                            {{-- Vehículos (cat 1) --}}
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

                            {{-- Joyas (cat 2) --}}
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

                            {{-- Garrafas (cat 3) --}}
                            <div class="product-category-fields d-none" id="product-fields-3">
                                <input type="hidden" name="product_name" id="product_name_garrafa" value="{{ old('product_name') }}">
                                <label class="form-label small fw-semibold">Detalles</label>
                                <textarea name="product_details" id="product_details_garrafa" rows="2"
                                    class="form-control form-control-sm @error('product_details') is-invalid @enderror"
                                    placeholder="Estado de la garrafa, capacidad, observaciones...">{{ old('product_details') }}</textarea>
                                @error('product_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            {{-- Artículos (cat 4) --}}
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
                                <p class="pc-section-label mb-3"><i class="bi bi-camera-fill"></i> Fotos e Imágenes</p>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Adjuntar fotos del producto o documentos</label>
                                        <div class="k-seg mb-2">
                                            <button type="button" class="btn btn-sm btn-ghost active" id="pi-btn-upload"><i class="bi bi-upload me-1"></i>Subir archivos</button>
                                            <button type="button" class="btn btn-sm btn-ghost" id="pi-btn-camera"><i class="bi bi-camera-fill me-1"></i>Usar cámara</button>
                                        </div>
                                        <div id="pi-upload-section">
                                            <div id="purchase-image-dropzone" class="purchase-image-dropzone">
                                                <div class="dropzone-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                                <div class="dropzone-title">Arrastra tus archivos aquí</div>
                                                <div class="dropzone-subtitle">o haz clic para seleccionarlos</div>
                                                <small id="purchase-image-dropzone-count" class="text-muted d-block mt-1">Sin archivos seleccionados</small>
                                            </div>
                                            <input type="file" id="purchase-images-input" name="images[]" class="d-none @error('images.*') is-invalid @enderror" multiple accept="image/*">
                                        </div>
                                        <div id="pi-camera-section" class="d-none">
                                            <div class="cp-camera-wrap">
                                                <div class="cp-flash" id="pi-flash"></div>
                                                <video id="pi-video" class="cp-video" autoplay playsinline muted></video>
                                                <div class="cp-camera-controls">
                                                    <x-icon-btn id="pi-capture-btn" icon="camera-fill" variant="primary" size="md" label="Capturar foto" />
                                                    <x-icon-btn id="pi-flip-btn" icon="arrow-repeat" variant="secondary" size="md" label="Cambiar cámara" />
                                                </div>
                                            </div>
                                            <canvas id="pi-canvas" class="d-none"></canvas>
                                            <small id="pi-camera-error" class="text-danger d-none"></small>
                                        </div>
                                        <div id="purchase-image-preview-grid" class="image-preview-grid mt-2" style="display:none;"></div>
                                        <small class="text-muted">Puedes seleccionar múltiples imágenes (JPG, PNG, WebP). Máx. 5MB cada una.</small>
                                        @error('images.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- § 3 — Monto --}}
                        <p class="pc-section-label"><i class="bi bi-calculator-fill"></i> Monto de compra</p>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Monto <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text fw-bold text-primary">$</span>
                                    <input type="number" step="1" min="1" name="amount" id="amount"
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
                                <label class="form-label small fw-semibold">Notas</label>
                                <textarea name="notes" rows="1" class="form-control form-control-sm @error('notes') is-invalid @enderror"
                                    placeholder="Observaciones opcionales...">{{ old('notes') }}</textarea>
                                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        {{-- Submit bar --}}
        <div class="mt-4 p-3 bg-white border rounded-3 shadow-sm d-flex align-items-center gap-3 flex-wrap">
            <x-btn type="submit" icon="check-lg" size="md" :disabled="!$activeCashSession">Registrar compra</x-btn>
            <x-btn variant="secondary" size="md" :href="route('purchases.index')">Cancelar</x-btn>
            <small class="text-muted ms-auto"><span class="text-danger">*</span> Campos obligatorios</small>
        </div>
    </form>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .pc-icon-badge {
        width: 34px;
        height: 34px;
        border-radius: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .pc-mode-toggle {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .4rem;
        background: #f1f3f5;
        padding: .25rem;
        border-radius: .6rem;
    }
    .pc-mode-btn {
        border: 0;
        background: transparent;
        border-radius: .45rem;
        padding: .45rem .5rem;
        font-size:var(--fs-base);
        color: #495057;
        font-weight: 600;
    }
    .pc-mode-btn.active {
        background: #fff;
        color: #0d6efd;
        box-shadow: 0 1px 3px rgba(0,0,0,.09);
    }
    .pc-section-label {
        font-size:var(--fs-sm);
        font-weight: 700;
        letter-spacing: .02em;
        color: #495057;
        margin-bottom: .6rem;
        display: flex;
        align-items: center;
        gap: .45rem;
    }
    .pc-product-card {
        border: 1px solid #d8e4f7;
        border-radius: 14px;
        padding: 1rem;
        background: linear-gradient(145deg, #f8fbff 0%, #f1f7ff 100%);
    }
    .pc-client-locked {
        background: linear-gradient(135deg, #f5fbff 0%, #edf7ff 100%);
        border: 1px solid #d6e9ff !important;
    }
    .pc-client-locked-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: linear-gradient(135deg, #e8f3ff 0%, #d8eaff 100%);
        color: #0d6efd;
        font-size:var(--fs-md);
        flex-shrink: 0;
    }
    .pc-client-data-item {
        background: rgba(255, 255, 255, .78);
        border: 1px solid #e3edf9;
        border-radius: .65rem;
        padding: .55rem .65rem;
        min-height: 62px;
    }
    .purchase-image-dropzone {
        border: 1px dashed #b9c6d8;
        background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 100%);
        border-radius: 12px;
        padding: 16px 12px;
        text-align: center;
        cursor: pointer;
        transition: all .2s ease;
        margin-bottom: .35rem;
    }
    .purchase-image-dropzone:hover,
    .purchase-image-dropzone.is-dragover {
        border-color: #0d6efd;
        background: #eaf2ff;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, .08);
    }
    .purchase-image-dropzone .dropzone-icon {
        font-size:var(--fs-lg);
        color: #0d6efd;
        line-height: 1;
        margin-bottom: .3rem;
    }
    .purchase-image-dropzone .dropzone-title {
        font-weight: 600;
        color: #1f2d3d;
        font-size:var(--fs-base);
        margin-bottom: .1rem;
    }
    .purchase-image-dropzone .dropzone-subtitle {
        color: #5b6573;
        font-size:var(--fs-sm);
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
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(function () {
        const $purchaseForm = $('#purchase-form');
        const $clientSelect = $('select[name="client_id"]');
        const $existingSection = $('#existing-section');
        const $newSection = $('#new-section');
        const $btnExisting = $('#btn-existing');
        const $btnNew = $('#btn-new');
        const $categorySelect = $('#credit_category_id');
        const $productCard = $('#product-card');
        const $productFields = $('.product-category-fields');
        const $productModelYear = $('#product_model_year');
        const $productPrice = $('#product_price');
        const $garrafaProductName = $('#product_name_garrafa');
        const $imagesInput = $('#purchase-images-input');
        const $imagesDropzone = $('#purchase-image-dropzone');
        const $imagesCount = $('#purchase-image-dropzone-count');
        const hasPreselectedClient = @json($hasPreselectedClient);

        function updateImageCount(files) {
            const count = files ? files.length : 0;
            const $grid = $('#purchase-image-preview-grid');
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
            $imagesDropzone.on('click', function () { $imagesInput.trigger('click'); });
            $imagesInput.on('change', function () { updateImageCount(this.files); });
            $imagesDropzone.on('dragenter dragover', function (e) {
                e.preventDefault(); e.stopPropagation();
                $imagesDropzone.addClass('is-dragover');
            });
            $imagesDropzone.on('dragleave dragend drop', function (e) {
                e.preventDefault(); e.stopPropagation();
                $imagesDropzone.removeClass('is-dragover');
            });
            $imagesDropzone.on('drop', function (e) {
                const files = e.originalEvent?.dataTransfer?.files;
                if (!files || !files.length) return;
                $imagesInput[0].files = files;
                updateImageCount(files);
            });
        }

        // ── Purchase images camera widget ──────────────────────────────
        (function () {
            const btnUpload  = document.getElementById('pi-btn-upload');
            const btnCamera  = document.getElementById('pi-btn-camera');
            const uploadSec  = document.getElementById('pi-upload-section');
            const cameraSec  = document.getElementById('pi-camera-section');
            const video      = document.getElementById('pi-video');
            const canvas     = document.getElementById('pi-canvas');
            const captureBtn = document.getElementById('pi-capture-btn');
            const flipBtn    = document.getElementById('pi-flip-btn');
            const flash      = document.getElementById('pi-flash');
            const errMsg     = document.getElementById('pi-camera-error');
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
            document.getElementById('purchase-form').addEventListener('submit', stopImgCamera);
        })();

        if ($clientSelect.length) {
            $clientSelect.select2({ placeholder: 'Buscar vendedor / cliente...', allowClear: true, width: '100%' });
        }

        function populateModelYears() {
            if (!$productModelYear.length) return;
            const currentYear = new Date().getFullYear();
            let options = '<option value="">Seleccionar...</option>';
            for (let year = currentYear; year >= 1980; year -= 1) {
                options += `<option value="${year}">${year}</option>`;
            }
            $productModelYear.html(options);
            const oldYear = '{{ old('product_model_year') }}';
            if (oldYear) $productModelYear.val(oldYear);
            $productModelYear.select2({ placeholder: 'Año', width: '100%' });
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
        }

        function setClientMode(mode) {
            if (hasPreselectedClient) return;
            const useNew = mode === 'new';
            $btnExisting.toggleClass('active', !useNew);
            $btnNew.toggleClass('active', useNew);
            $existingSection.toggleClass('d-none', useNew);
            $newSection.toggleClass('d-none', !useNew);
            if (useNew) $clientSelect.val('').trigger('change');
        }

        $btnExisting.on('click', function () { setClientMode('existing'); });
        $btnNew.on('click', function () { setClientMode('new'); });
        $clientSelect.on('change', function () {
            if ($(this).val()) setClientMode('existing');
        });

        $categorySelect.on('change', function () {
            applyProductLayoutByCategory($(this).val());
        });

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

        // Pre-open print window from user submit action to avoid popup blockers after redirect.
        if ($purchaseForm.length) {
            $purchaseForm.on('submit', function () {
                try {
                    window.sessionStorage?.setItem('purchase_auto_print_pending', '1');
                } catch (error) {
                    // ignore storage errors
                }

                const preOpenedWindow = window.open('about:blank', 'purchase_auto_print_window', 'width=430,height=760');
                if (!preOpenedWindow) {
                    return;
                }

                preOpenedWindow.document.open();
                preOpenedWindow.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Imprimiendo...</title></head><body style="font-family:Arial,sans-serif;padding:14px;">Preparando comprobante de compra...</body></html>');
                preOpenedWindow.document.close();
            });
        }

        // ── Validación de saldo de caja: no gastar más de lo disponible ──
        (function () {
            const cashAvailable = @json($cashAvailable ?? null);
            const form = document.getElementById('purchase-form');
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
        document.getElementById('purchase-form').addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            const btn = this.querySelector('button[type="submit"]');
            if (!btn || btn.dataset.submitting) return;
            btn.dataset.submitting = '1';
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Procesando...';
        });
    });
</script>
@endpush
