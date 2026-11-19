@extends('layouts.app')

@section('title', 'Editar Préstamo PR-' . str_pad($loan->numero, 5, '0', STR_PAD_LEFT))

@section('page')
@php
    $categoryId   = (int) $loan->credit_category_id;
    $lockInterest = in_array($categoryId, [3, 4]);
    $product      = $loan->product;
@endphp
<div>

    {{-- Header --}}
    <div class="mb-2">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 fs-sm">
                    <li class="breadcrumb-item"><a href="{{ route('loans.index') }}" class="text-decoration-none">Préstamos</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('loans.show', $loan) }}" class="text-decoration-none">PR-{{ str_pad($loan->numero, 5, '0', STR_PAD_LEFT) }}</a></li>
                    <li class="breadcrumb-item active">Editar</li>
                </ol>
            </nav>
    </div>
    <x-page-header title="Editar Préstamo PR-{{ str_pad($loan->numero, 5, '0', STR_PAD_LEFT) }}" icon="pencil-square" :back="route('loans.show', $loan)" />

    {{-- Errors --}}
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
        @php
            $sessionBranch = $activeCashSession->cashRegister?->branch;
            $sessionBranchColor = $sessionBranch?->color ?? '#198754';
        @endphp
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
            <span class="badge" style="background:{{ $sessionBranchColor }}22;color:{{ $sessionBranchColor }};border:1px solid {{ $sessionBranchColor }}44;">Caja abierta</span>
        </div>
    @endif

    {{-- Locked info bar --}}
    <div class="card border-0 shadow-sm mb-4"
         style="background:linear-gradient(135deg,#f5fbff,#edf7ff);border:1px solid #d6e9ff!important;">
        <div class="card-body py-3 px-4">
            <div class="d-flex flex-wrap align-items-center gap-4">
                <div class="d-flex align-items-center gap-2">
                    @if($loan->client?->photo_url)
                    <img src="{{ $loan->client->photo_url }}" alt=""
                         class="rounded-2 object-fit-cover flex-shrink-0"
                         style="width:36px;height:36px;border:1px solid #d6e9ff;">
                    @else
                    <span class="d-inline-flex align-items-center justify-content-center rounded-2 bg-primary bg-opacity-10 text-primary flex-shrink-0"
                          style="width:36px;height:36px;">
                        <i class="bi bi-person-fill"></i>
                    </span>
                    @endif
                    <div>
                        <div class="text-muted fs-xs" style="text-transform:uppercase;letter-spacing:.05em;">Cliente</div>
                        <div class="fw-semibold lh-1 fs-base" >{{ $loan->client?->name ?? '—' }}</div>
                    </div>
                </div>
                <div class="vr opacity-25 d-none d-sm-block"></div>
                <div>
                    <div class="text-muted fs-xs" style="text-transform:uppercase;letter-spacing:.05em;">Categoría</div>
                    <div class="fw-semibold lh-1 fs-base" >{{ $loan->creditCategory?->name ?? '—' }}</div>
                </div>
                <div class="vr opacity-25 d-none d-sm-block"></div>
                <div>
                    <div class="text-muted fs-xs" style="text-transform:uppercase;letter-spacing:.05em;">Período de interés</div>
                    <div class="fw-semibold lh-1 fs-base" >
                        {{ ['monthly'=>'Mensual','quarterly'=>'Trimestral','semiannual'=>'Semestral','annual'=>'Anual'][$loan->interest_period] ?? ucfirst($loan->interest_period) }}
                    </div>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-warning text-dark py-2 px-3 fs-sm" style="border-radius:999px;">
                        <i class="bi bi-lock-fill me-1"></i> Cliente y categoría no editables
                    </span>
                </div>
            </div>
        </div>
    </div>

    <form action="{{ route('loans.update', $loan) }}" method="POST" id="loan-edit-form" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-4">

            {{-- ── COLUMNA IZQUIERDA ────────────────────────────────────── --}}
            <div class="col-lg-8">

                {{-- Card: Sucursal + Producto --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center gap-2 mb-4">
                            <span class="le-icon-badge bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-geo-alt-fill"></i>
                            </span>
                            <h6 class="mb-0 fw-bold">Sucursal y Producto</h6>
                        </div>

                        {{-- Sucursal --}}
                        <p class="le-section-label"><i class="bi bi-building"></i> Sucursal</p>
                        <div class="row g-3 mb-4">
                            <div class="col-md-7">
                                <label class="form-label small fw-semibold">Sucursal <span class="text-danger">*</span></label>
                                {{-- La sucursal del préstamo no cambia al editar: la prenda está en su almacén. --}}
                                @php $sb = $loan->branch; $sbColor = $sb?->color ?? '#0d6efd'; @endphp
                                <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                                     style="border:1.5px solid {{ $sbColor }}33; background:{{ $sbColor }}0d;">
                                    <span style="width:10px;height:10px;border-radius:50%;background:{{ $sbColor }};flex-shrink:0;"></span>
                                    <span class="fw-semibold fs-base" >{{ $sb?->name ?? 'Sin sucursal' }}</span>
                                    <span class="ms-auto text-muted d-flex align-items-center gap-1 fs-xs" >
                                        <i class="bi bi-lock-fill"></i> sucursal del préstamo
                                    </span>
                                </div>
                                <small class="text-muted fs-xs" >
                                    La sucursal no se cambia al editar: la prenda se encuentra en el almacén de esta sucursal.
                                </small>
                            </div>
                        </div>

                        {{-- Datos del producto --}}
                        @if($product)
                        <hr class="my-3">
                        <p class="le-section-label"><i class="bi bi-box-seam"></i> Datos del producto</p>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Precio referencial</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="1" min="0" name="product_price"
                                        class="form-control @error('product_price') is-invalid @enderror"
                                        value="{{ old('product_price', $product->price) }}">
                                    @error('product_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>

                        {{-- Vehículo (cat 1) --}}
                        @if($categoryId === 1)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="product_name"
                                    class="form-control @error('product_name') is-invalid @enderror"
                                    value="{{ old('product_name', $product->name) }}" placeholder="Ej: Toyota Corolla">
                                @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Marca <span class="text-danger">*</span></label>
                                <input type="text" name="product_brand"
                                    class="form-control @error('product_brand') is-invalid @enderror"
                                    value="{{ old('product_brand', $product->brand) }}" placeholder="Ej: Toyota">
                                @error('product_brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Modelo (año) <span class="text-danger">*</span></label>
                                @php $selectedYear = old('product_model_year', $product->model_year); @endphp
                                <select name="product_model_year"
                                    class="form-select @error('product_model_year') is-invalid @enderror">
                                    <option value="">Seleccionar...</option>
                                    @for($y = date('Y') + 1; $y >= 1980; $y--)
                                    <option value="{{ $y }}" {{ (string) $selectedYear === (string) $y ? 'selected' : '' }}>{{ $y }}</option>
                                    @endfor
                                </select>
                                @error('product_model_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Placa <span class="text-danger">*</span></label>
                                <input type="text" name="product_plate"
                                    class="form-control @error('product_plate') is-invalid @enderror"
                                    value="{{ old('product_plate', $product->plate) }}" placeholder="AB123CD">
                                @error('product_plate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Chasis <span class="text-danger">*</span></label>
                                <input type="text" name="product_chassis"
                                    class="form-control @error('product_chassis') is-invalid @enderror"
                                    value="{{ old('product_chassis', $product->chassis) }}" placeholder="Número de chasis">
                                @error('product_chassis')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Detalles</label>
                                <textarea name="product_details" rows="2"
                                    class="form-control @error('product_details') is-invalid @enderror"
                                    placeholder="Condición general, observaciones...">{{ old('product_details', $product->description) }}</textarea>
                                @error('product_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Joyas (cat 2) --}}
                        @elseif($categoryId === 2)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Tipo de joya <span class="text-danger">*</span></label>
                                <input type="text" name="product_item_type"
                                    class="form-control @error('product_item_type') is-invalid @enderror"
                                    value="{{ old('product_item_type', $product->item_type) }}"
                                    placeholder="Ej: Collar, anillo, manilla...">
                                @error('product_item_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Kilataje <span class="text-danger">*</span></label>
                                <select name="product_karat"
                                    class="form-select @error('product_karat') is-invalid @enderror">
                                    <option value="">Seleccionar...</option>
                                    @foreach(['10K','14K','18K','21K','22K','24K'] as $karat)
                                    <option value="{{ $karat }}"
                                        {{ old('product_karat', $product->karat) === $karat ? 'selected' : '' }}>
                                        {{ $karat }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('product_karat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Detalles</label>
                                <textarea name="product_details" rows="2"
                                    class="form-control @error('product_details') is-invalid @enderror"
                                    placeholder="Color, estado, piedras, observaciones...">{{ old('product_details', $product->description) }}</textarea>
                                @error('product_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Garrafas (cat 3) --}}
                        @elseif($categoryId === 3)
                        <input type="hidden" name="product_name" value="{{ $product->name }}">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Detalles</label>
                                <textarea name="product_details" rows="2"
                                    class="form-control @error('product_details') is-invalid @enderror"
                                    placeholder="Estado, capacidad, observaciones...">{{ old('product_details', $product->description) }}</textarea>
                                @error('product_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Artículos (cat 4) --}}
                        @elseif($categoryId === 4)
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
                                <input type="text" name="product_name"
                                    class="form-control @error('product_name') is-invalid @enderror"
                                    value="{{ old('product_name', $product->name) }}" placeholder="Ej: Laptop Lenovo">
                                @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Marca <span class="text-danger">*</span></label>
                                <input type="text" name="product_brand"
                                    class="form-control @error('product_brand') is-invalid @enderror"
                                    value="{{ old('product_brand', $product->brand) }}" placeholder="Ej: Lenovo">
                                @error('product_brand')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Serial <span class="text-danger">*</span></label>
                                <input type="text" name="product_serial_number"
                                    class="form-control @error('product_serial_number') is-invalid @enderror"
                                    value="{{ old('product_serial_number', $product->serial_number) }}"
                                    placeholder="Número de serial">
                                @error('product_serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-semibold">Detalles</label>
                                <textarea name="product_details" rows="2"
                                    class="form-control @error('product_details') is-invalid @enderror"
                                    placeholder="Condición, accesorios, observaciones...">{{ old('product_details', $product->description) }}</textarea>
                                @error('product_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        @endif

                        {{-- Agregar imágenes --}}
                        <hr class="my-4">
                        <p class="le-section-label mb-3"><i class="bi bi-camera-fill"></i> Agregar imágenes</p>
                        <div class="k-seg w-100 mb-2">
                            <button type="button" class="btn btn-sm btn-secondary flex-fill cp-mode-btn active" id="lie-btn-upload"><i class="bi bi-upload me-1"></i>Subir archivos</button>
                            <button type="button" class="btn btn-sm btn-secondary flex-fill cp-mode-btn" id="lie-btn-camera"><i class="bi bi-camera-fill me-1"></i>Usar cámara</button>
                        </div>
                        <div id="lie-upload-section">
                            <div class="le-dropzone" id="loan-image-dropzone">
                                <div class="fs-lg" style="color:#0d6efd;margin-bottom:.3rem;"><i class="bi bi-cloud-arrow-up"></i></div>
                                <div class="fs-base" style="font-weight:600;color:#1f2d3d;margin-bottom:.1rem;">Arrastra tus archivos aquí</div>
                                <div class="fs-xs" style="color:#5b6573;">o haz clic para seleccionarlos</div>
                                <small id="loan-image-dropzone-count" class="text-muted d-block mt-1">Sin archivos seleccionados</small>
                            </div>
                            <input type="file" id="loan-images-input" name="images[]" class="d-none" multiple accept="image/*">
                        </div>
                        <div id="lie-camera-section" class="d-none">
                            <div class="cp-camera-wrap">
                                <div class="cp-flash" id="lie-flash"></div>
                                <video id="lie-video" class="cp-video" autoplay playsinline muted></video>
                                <div class="cp-camera-controls">
                                    <button type="button" class="cp-capture-btn" id="lie-capture-btn" title="Capturar foto"><span class="cp-capture-ring"></span></button>
                                    <button type="button" class="cp-flip-btn" id="lie-flip-btn" title="Cambiar cámara"><i class="bi bi-arrow-repeat"></i></button>
                                </div>
                            </div>
                            <canvas id="lie-canvas" class="d-none"></canvas>
                            <small id="lie-camera-error" class="text-danger d-none"></small>
                        </div>
                        <div id="loan-image-preview-grid" class="le-preview-grid mt-2" style="display:none;"></div>
                        <small class="text-muted">JPG, PNG, WebP · Máx. 5 MB cada una</small>
                        @endif

                    </div>
                </div>

                {{-- Card: Condiciones financieras --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">

                        <div class="d-flex align-items-center gap-2 mb-4">
                            <span class="le-icon-badge bg-success bg-opacity-10 text-success">
                                <i class="bi bi-calculator-fill"></i>
                            </span>
                            <h6 class="mb-0 fw-bold">Condiciones financieras</h6>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Monto <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text fw-bold text-primary">$</span>
                                    <input type="number" name="amount" id="le-amount" step="1"
                                        class="form-control @error('amount') is-invalid @enderror"
                                        value="{{ old('amount', $loan->amount) }}" required>
                                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-text">Actualiza el desembolso en caja si cambia.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Tasa de interés <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" name="interest_rate" id="le-rate" step="1" min="1" max="100"
                                        class="form-control @error('interest_rate') is-invalid @enderror {{ $lockInterest ? 'bg-light' : '' }}"
                                        value="{{ old('interest_rate', (int) $loan->interest_rate) }}"
                                        {{ $lockInterest ? 'readonly' : '' }}>
                                    <span class="input-group-text">%</span>
                                    @error('interest_rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                @if($lockInterest)
                                <div class="form-text"><i class="bi bi-lock-fill me-1"></i>Definida por categoría</div>
                                @endif
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Plazo (meses) <span class="text-danger">*</span></label>
                                <input type="number" name="term_months" id="le-term" min="1"
                                    class="form-control @error('term_months') is-invalid @enderror"
                                    value="{{ old('term_months', $loan->term_months) }}">
                                @error('term_months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Preview --}}
                        <div id="le-preview" class="le-preview-box" style="display:none;">
                            <div class="row g-0 align-items-center">
                                <div class="col">
                                    <div class="le-preview-label">Interés del primer periodo</div>
                                    <div class="le-preview-amount" id="le-preview-monthly">$0</div>
                                </div>
                                <div class="vr mx-3 opacity-25"></div>
                                <div class="col text-end">
                                    <div class="le-preview-label">Capital + primer interés</div>
                                    <div class="fw-bold" id="le-preview-total">$0</div>
                                    <small class="text-muted" id="le-preview-term"></small>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- ── COLUMNA DERECHA ──────────────────────────────────────── --}}
            <div class="col-lg-4">

                {{-- Card: Fechas --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-2 mb-4">
                            <span class="le-icon-badge bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-calendar-event-fill"></i>
                            </span>
                            <h6 class="mb-0 fw-bold">Fechas</h6>
                        </div>

                        @if($canSetStartDate)
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">
                                Fecha de inicio
                                <span class="badge bg-primary ms-1 fs-xs" >Permiso activo</span>
                            </label>
                            <input type="date" name="start_date" id="le-start-date"
                                class="form-control @error('start_date') is-invalid @enderror"
                                value="{{ old('start_date', $loan->start_date?->format('Y-m-d')) }}">
                            @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @else
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Fecha de inicio</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="bi bi-lock-fill"></i>
                                </span>
                                <input type="text" class="form-control bg-light border-start-0"
                                    value="{{ $loan->start_date?->format('d/m/Y') }}" readonly>
                            </div>
                            <div class="form-text">Se establece automáticamente.</div>
                        </div>
                        @endif

                        <div class="mb-0">
                            <label class="form-label small fw-semibold">Fecha de vencimiento</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">
                                    <i class="bi bi-lock-fill"></i>
                                </span>
                                <input type="text" id="le-end-date-display" class="form-control bg-light border-start-0"
                                    value="{{ $loan->end_date?->format('d/m/Y') }}" readonly>
                            </div>
                            <div class="form-text">Se recalcula automáticamente según fecha de inicio y plazo.</div>
                        </div>
                    </div>
                </div>

                {{-- Card: Notas --}}
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="le-icon-badge bg-info bg-opacity-10 text-info">
                                <i class="bi bi-journal-text"></i>
                            </span>
                            <h6 class="mb-0 fw-bold">Notas internas</h6>
                        </div>
                        <textarea name="notes" rows="7"
                            class="form-control @error('notes') is-invalid @enderror"
                            placeholder="Observaciones, acuerdos, condiciones especiales…"
                            style="resize:vertical;">{{ old('notes', $loan->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

            </div>
        </div>

        {{-- Submit bar --}}
        <div class="mt-4 p-3 bg-white border rounded-3 shadow-sm d-flex align-items-center gap-3 flex-wrap">
            <x-btn type="submit" size="md" icon="check2-circle">Guardar cambios</x-btn>
            <x-btn variant="secondary" size="md" icon="x" :href="route('loans.show', $loan)">Cancelar</x-btn>
            <small class="text-muted ms-auto"><span class="text-danger">*</span> Campos obligatorios</small>
        </div>
    </form>

</div>
@endsection

@push('styles')
<style>
    .le-icon-badge {
        width: 34px; height: 34px;
        border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .le-section-label {
        font-size: var(--fs-sm); font-weight: 700;
        letter-spacing: .02em; color: #495057;
        margin-bottom: .6rem;
        display: flex; align-items: center; gap: .45rem;
    }
    .le-preview-box {
        border: 1px solid #e6eefc;
        background: linear-gradient(135deg, #f7faff, #f3f9ff);
        border-radius: .8rem; padding: .9rem 1rem; margin-top: 1rem;
    }
    .le-preview-label {
        color: #6c757d; font-size: var(--fs-xs);
        text-transform: uppercase; letter-spacing: .04em;
    }
    .le-preview-amount {
        font-size: var(--fs-lg); font-weight: 700; line-height: 1.15; color: #0d6efd;
    }
    .le-dropzone {
        border: 1px dashed #b9c6d8;
        background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 100%);
        border-radius: 12px; padding: 16px 12px;
        text-align: center; cursor: pointer;
        transition: all .2s ease; margin-bottom: .35rem;
    }
    .le-dropzone:hover, .le-dropzone.is-dragover {
        border-color: #0d6efd; background: #eaf2ff;
        box-shadow: 0 0 0 3px rgba(13,110,253,.08);
    }
    .le-preview-grid {
        display: flex; flex-wrap: wrap; gap: .3rem;
        align-items: center; padding: .3rem 0;
    }
    .le-preview-thumb {
        width: 44px; height: 44px; border-radius: 7px;
        overflow: hidden; border: 1.5px solid #cfe2ff; flex-shrink: 0;
    }
    .le-preview-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .le-preview-clear {
        display: inline-flex; align-items: center; gap: .2rem;
        padding: .18rem .45rem; font-size: var(--fs-xs); border-radius: 999px;
        border: 1px solid #efc6cc; background: #fff5f6; color: #c23e4d;
        cursor: pointer; font-weight: 600; line-height: 1.4;
    }
    .cp-camera-wrap { position:relative; border-radius:12px; overflow:hidden; background:#0a0a0a; aspect-ratio:4/3; width:60%; margin:0 auto; }
    .cp-video { width:100%; height:100%; object-fit:cover; display:block; }
    .cp-flash { position:absolute; inset:0; background:#fff; opacity:0; pointer-events:none; z-index:10; transition:opacity .12s ease; }
    .cp-camera-controls { position:absolute; bottom:12px; left:0; right:0; display:flex; justify-content:center; align-items:center; gap:20px; z-index:5; }
    .cp-capture-btn { width:54px; height:54px; border-radius:50%; background:rgba(255,255,255,.9); border:none; box-shadow:0 2px 12px rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:transform .1s; }
    .cp-capture-btn:active { transform:scale(.9); }
    .cp-capture-ring { width:36px; height:36px; border-radius:50%; border:3px solid #0d6efd; display:block; }
    .cp-flip-btn { width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,.7); border:none; color:#333; font-size: var(--fs-md); display:flex; align-items:center; justify-content:center; cursor:pointer; transition:background .15s; }
    .cp-flip-btn:hover { background:rgba(255,255,255,.9); }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    const $amount  = $('#le-amount');
    const $rate    = $('#le-rate');
    const $term    = $('input[name="term_months"]');
    const $preview = $('#le-preview');
    const $monthly = $('#le-preview-monthly');
    const $total   = $('#le-preview-total');
    const $termLbl = $('#le-preview-term');
    const period   = '{{ $loan->interest_period }}';
    const periodLabel = { monthly:'mensual', quarterly:'trimestral', semiannual:'semestral', annual:'anual' };

    function fmt(v) {
        return '{{ $currencySymbol }} ' + (Number(v || 0)).toLocaleString('es-VE', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function updatePreview() {
        const amount = parseFloat($amount.val() || 0);
        const rate   = parseFloat($rate.val() || 0);
        const months = parseInt($term.val() || 0, 10);
        if (!amount || !months) { $preview.hide(); return; }
        const interest = amount * (rate / 100);
        $monthly.text(fmt(interest));
        $total.text(fmt(amount + interest));
        $termLbl.text(months + ' meses · Primer interés ' + (periodLabel[period] || period));
        $preview.show();
    }

    // Recalculate end date display from start_date + term_months
    const $startDate  = $('#le-start-date');
    const $endDisplay = $('#le-end-date-display');

    function updateEndDate() {
        const start  = $startDate.val();
        const months = parseInt($term.val() || 0, 10);
        if (!start || !months) return;
        const d = new Date(start);
        d.setMonth(d.getMonth() + months);
        const day   = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        $endDisplay.val(day + '/' + month + '/' + d.getFullYear());
    }

    $startDate.on('change', function () { updateEndDate(); updatePreview(); });
    $term.on('input', function () { updateEndDate(); updatePreview(); });

    $amount.on('input', updatePreview);
    $rate.on('input', updatePreview);
    updatePreview();
    updateEndDate();

    // Dropzone
    const $input    = $('#loan-images-input');
    const $dropzone = $('#loan-image-dropzone');
    const $count    = $('#loan-image-dropzone-count');

    function updateCount(files) {
        const n = files ? files.length : 0;
        const $grid = $('#loan-image-preview-grid');
        if (!n) { $count.text('Sin archivos seleccionados'); $grid.hide().empty(); return; }
        $count.text(n + ' archivo' + (n === 1 ? '' : 's') + ' seleccionado' + (n === 1 ? '' : 's'));
        $grid.empty();
        Array.from(files).forEach(function (f) {
            const $th  = $('<div class="le-preview-thumb"></div>');
            const $img = $('<img alt="">');
            const r = new FileReader();
            r.onload = function (e) { $img.attr('src', e.target.result); };
            r.readAsDataURL(f);
            $th.append($img); $grid.append($th);
        });
        const $cl = $('<button type="button" class="le-preview-clear"><i class="bi bi-x-circle me-1"></i>Limpiar selección</button>');
        $cl.on('click', function () { $input.val(''); updateCount(null); });
        $grid.append($cl); $grid.show();
    }

    if ($dropzone.length) {
        $dropzone.on('click', function () { $input.trigger('click'); });
        $input.on('change', function () { updateCount(this.files); });
        $dropzone.on('dragenter dragover', function (e) {
            e.preventDefault(); e.stopPropagation(); $dropzone.addClass('is-dragover');
        });
        $dropzone.on('dragleave dragend drop', function (e) {
            e.preventDefault(); e.stopPropagation(); $dropzone.removeClass('is-dragover');
        });
        $dropzone.on('drop', function (e) {
            const files = e.originalEvent?.dataTransfer?.files;
            if (files?.length) { $input[0].files = files; updateCount(files); }
        });
    }

    // ── Loan edit images camera widget ──────────────────────────────
    (function () {
        const btnUpload  = document.getElementById('lie-btn-upload');
        const btnCamera  = document.getElementById('lie-btn-camera');
        const uploadSec  = document.getElementById('lie-upload-section');
        const cameraSec  = document.getElementById('lie-camera-section');
        const video      = document.getElementById('lie-video');
        const canvas     = document.getElementById('lie-canvas');
        const captureBtn = document.getElementById('lie-capture-btn');
        const flipBtn    = document.getElementById('lie-flip-btn');
        const flash      = document.getElementById('lie-flash');
        const errMsg     = document.getElementById('lie-camera-error');
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
                Array.from($input[0].files).forEach(f => dt.items.add(f));
                dt.items.add(file);
                $input[0].files = dt.files;
                updateCount($input[0].files);
            }, 'image/jpeg', 0.92);
        });
        document.getElementById('loan-edit-form').addEventListener('submit', stopImgCamera);
    })();
});
</script>
@endpush
