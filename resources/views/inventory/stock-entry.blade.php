@extends('layouts.app')

@section('title', 'Ingresar producto')

@php
    // Diseño de campos por categoría (igual que en el préstamo): 1 vehículo, 2 joya, 3 garrafa; el resto, artículo.
    $layoutOf = fn ($id) => in_array((int) $id, [1, 2, 3], true) ? (string) (int) $id : '4';
    $oldBranch = (string) old('branch_id', $defaultBranchId ?: '');
    $oldCategory = (string) old('credit_category_id');
    $hasErr = fn (array $fields) => collect($fields)->contains(fn ($f) => $errors->has($f));
    $moreOpen = $hasErr(['movement_date', 'reference', 'notes']) || old('reference') || old('notes');
@endphp

@section('page')
<div class="pe-page">
    {{-- Encabezado --}}
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1 small">
            <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}" class="text-decoration-none">Inventario</a></li>
            <li class="breadcrumb-item active">Ingresar producto</li>
        </ol>
    </nav>
    <x-page-header title="Ingresar producto" icon="box-arrow-in-down" :back="url()->previous() !== url()->current() ? url()->previous() : route('inventory.index')" />
    <p class="text-muted small mb-3">
        Registra un artículo que ya tienes y no viene de un préstamo ni de una compra.
        Cada ingreso es <strong>un producto único</strong> (cantidad 1) con su propio costo y código.
    </p>

    @if($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start py-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
            <div class="small">
                <strong>Revisa los datos marcados en rojo:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        </div>
    @endif

    <form action="{{ route('inventory.stock-entry.store') }}" method="POST" id="pe-form" enctype="multipart/form-data" novalidate>
        @csrf

        <div class="row g-3">
            <div class="col-lg-8">

                {{-- 1. Sucursal --}}
                <section class="card border-0 shadow-sm pe-step {{ $hasErr(['branch_id']) ? 'has-error' : '' }}" id="pe-step-branch">
                    <div class="card-body">
                        <div class="pe-step-head">
                            <span class="pe-step-num">1</span>
                            <div>
                                <h6 class="mb-0 fw-bold">¿En qué sucursal entra?</h6>
                                <small class="text-muted">El producto queda en el almacén de esa sucursal.</small>
                            </div>
                        </div>

                        @if($branches->isEmpty())
                            <div class="alert alert-warning small mb-0">No tienes sucursales activas disponibles para ingresar productos.</div>
                        @elseif($branches->count() === 1)
                            @php $onlyBranch = $branches->first(); @endphp
                            <input type="hidden" name="branch_id" value="{{ $onlyBranch->id }}">
                            <span class="pe-branch is-fixed" style="--branch-color: {{ $onlyBranch->color ?: '#4f46e5' }}" data-name="{{ $onlyBranch->name }}">
                                <span class="pe-dot"></span> {{ $onlyBranch->name }}
                                <i class="bi bi-lock-fill ms-1 opacity-50" title="Tu sucursal"></i>
                            </span>
                        @else
                            <div class="d-flex flex-wrap gap-2" role="radiogroup" aria-label="Sucursal">
                                @foreach($branches as $branch)
                                    <label class="pe-branch" style="--branch-color: {{ $branch->color ?: '#4f46e5' }}">
                                        <input type="radio" name="branch_id" value="{{ $branch->id }}" class="visually-hidden"
                                               data-name="{{ $branch->name }}" {{ $oldBranch === (string) $branch->id ? 'checked' : '' }}>
                                        <span class="pe-dot"></span> {{ $branch->name }}
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        @error('branch_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        <div class="pe-hint text-danger small mt-2 d-none" data-for="branch_id">Elige la sucursal.</div>
                    </div>
                </section>

                {{-- 2. Categoría --}}
                <section class="card border-0 shadow-sm pe-step mt-3 {{ $hasErr(['credit_category_id']) ? 'has-error' : '' }}" id="pe-step-category">
                    <div class="card-body">
                        <div class="pe-step-head">
                            <span class="pe-step-num">2</span>
                            <div>
                                <h6 class="mb-0 fw-bold">¿Qué es?</h6>
                                <small class="text-muted">Según la categoría se piden sus datos (vehículo, joya, garrafa o artículo).</small>
                            </div>
                        </div>

                        <div class="pe-cat-grid" role="radiogroup" aria-label="Categoría">
                            @foreach($categories as $category)
                                <label class="pe-cat" style="--cat-color: {{ $category->color ?: '#4f46e5' }}">
                                    <input type="radio" name="credit_category_id" value="{{ $category->id }}" class="visually-hidden"
                                           data-layout="{{ $layoutOf($category->id) }}" data-name="{{ $category->name }}"
                                           {{ $oldCategory === (string) $category->id ? 'checked' : '' }}>
                                    <i class="bi {{ $category->icon_class }}"></i>
                                    <span>{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @if($categories->isEmpty())
                            <div class="alert alert-warning small mb-0">No hay categorías activas. Créalas en Categorías de crédito.</div>
                        @endif
                        @error('credit_category_id')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        <div class="pe-hint text-danger small mt-2 d-none" data-for="credit_category_id">Elige la categoría.</div>
                    </div>
                </section>

                {{-- 3. Datos del producto --}}
                <section class="card border-0 shadow-sm pe-step mt-3 {{ $hasErr(['product_name', 'product_code', 'product_brand', 'product_item_model', 'product_serial_number', 'product_plate', 'product_chassis', 'product_karat', 'product_material_type', 'product_item_type', 'product_description']) ? 'has-error' : '' }}" id="pe-step-data">
                    <div class="card-body">
                        <div class="pe-step-head">
                            <span class="pe-step-num">3</span>
                            <div>
                                <h6 class="mb-0 fw-bold">Datos del producto</h6>
                                <small class="text-muted">Mientras más datos, más fácil identificarlo y venderlo.</small>
                            </div>
                        </div>

                        <div id="pe-data-empty" class="pe-empty">
                            <i class="bi bi-arrow-up-circle"></i> Elige primero la categoría.
                        </div>

                        <div id="pe-data-body" class="d-none">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-semibold" for="product_name">Nombre del producto <span class="text-danger">*</span></label>
                                    <input type="text" name="product_name" id="product_name" maxlength="255"
                                           class="form-control form-control-sm @error('product_name') is-invalid @enderror"
                                           value="{{ old('product_name') }}" placeholder="Ej: Celular Xiaomi Poco X3">
                                    @error('product_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="pe-hint invalid-feedback d-none" data-for="product_name">Escribe el nombre del producto.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold" for="product_code">Código</label>
                                    <input type="text" name="product_code" id="product_code" maxlength="30"
                                           class="form-control form-control-sm @error('product_code') is-invalid @enderror"
                                           value="{{ old('product_code', $nextProductCode) }}" placeholder="{{ $nextProductCode }}">
                                    @error('product_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text pe-help">Sugerido: el siguiente correlativo. Se puede cambiar; no se repite en la sucursal.</div>
                                </div>
                            </div>

                            {{-- Vehículo --}}
                            <div class="pe-fields d-none" data-layout="1">
                                <div class="row g-3 mt-0">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Marca</label>
                                        <input type="text" name="product_brand" class="form-control form-control-sm" value="{{ old('product_brand') }}" placeholder="Ej: Toyota">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Modelo (año)</label>
                                        <select name="product_model_year" id="product_model_year" class="form-select form-select-sm" data-old="{{ old('product_model_year') }}"></select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Placa</label>
                                        <input type="text" name="product_plate" class="form-control form-control-sm" value="{{ old('product_plate') }}" placeholder="Ej: 1234ABC">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold">Chasis</label>
                                        <input type="text" name="product_chassis" class="form-control form-control-sm" value="{{ old('product_chassis') }}" placeholder="Número de chasis">
                                    </div>
                                </div>
                            </div>

                            {{-- Joya --}}
                            <div class="pe-fields d-none" data-layout="2">
                                <div class="row g-3 mt-0">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Tipo de joya</label>
                                        <input type="text" name="product_item_type" class="form-control form-control-sm" value="{{ old('product_item_type') }}" placeholder="Collar, anillo, manilla…">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Kilataje</label>
                                        <select name="product_karat" class="form-select form-select-sm">
                                            <option value="">Seleccionar…</option>
                                            @foreach(['10K', '14K', '18K', '21K', '22K', '24K'] as $karat)
                                                <option value="{{ $karat }}" {{ old('product_karat') === $karat ? 'selected' : '' }}>{{ $karat }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Material</label>
                                        <select name="product_material_type" class="form-select form-select-sm">
                                            <option value="">Seleccionar…</option>
                                            @foreach(['Oro', 'Plata', 'Platino', 'Acero', 'Otro'] as $material)
                                                <option value="{{ $material }}" {{ old('product_material_type') === $material ? 'selected' : '' }}>{{ $material }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- Artículo (cualquier otra categoría: celular, computadora, heladera…) --}}
                            <div class="pe-fields d-none" data-layout="4">
                                <div class="row g-3 mt-0">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Marca</label>
                                        <input type="text" name="product_brand" class="form-control form-control-sm" value="{{ old('product_brand') }}" placeholder="Ej: Samsung, Lenovo…">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">Modelo</label>
                                        <input type="text" name="product_item_model" class="form-control form-control-sm" value="{{ old('product_item_model') }}" placeholder="Ej: Galaxy A54">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold">N.º de serie / IMEI</label>
                                        <input type="text" name="product_serial_number" class="form-control form-control-sm" value="{{ old('product_serial_number') }}" placeholder="Serie del producto">
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label small fw-semibold" for="product_description">Descripción</label>
                                <textarea name="product_description" id="product_description" rows="2" maxlength="2000"
                                          class="form-control form-control-sm @error('product_description') is-invalid @enderror"
                                          placeholder="Estado, color, accesorios…">{{ old('product_description') }}</textarea>
                                @error('product_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </section>

                {{-- 4. Valores --}}
                <section class="card border-0 shadow-sm pe-step mt-3 {{ $hasErr(['unit_cost', 'product_price']) ? 'has-error' : '' }}" id="pe-step-values">
                    <div class="card-body">
                        <div class="pe-step-head">
                            <span class="pe-step-num">4</span>
                            <div>
                                <h6 class="mb-0 fw-bold">Valores</h6>
                                <small class="text-muted">El costo es lo que se pagó por el artículo; el precio, a cuánto se venderá.</small>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold" for="unit_cost">Costo <span class="text-danger">*</span></label>
                                <div class="input-group input-group-sm has-validation">
                                    <span class="input-group-text">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" min="0" name="unit_cost" id="unit_cost" inputmode="decimal"
                                           class="form-control @error('unit_cost') is-invalid @enderror" value="{{ old('unit_cost') }}" placeholder="0.00">
                                    @error('unit_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="pe-hint invalid-feedback d-none" data-for="unit_cost">Indica el costo (0 si no costó nada).</div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small fw-semibold" for="product_price">Precio de venta</label>
                                <div class="input-group input-group-sm has-validation">
                                    <span class="input-group-text">{{ $currencySymbol }}</span>
                                    <input type="number" step="0.01" min="0" name="product_price" id="product_price" inputmode="decimal"
                                           class="form-control @error('product_price') is-invalid @enderror" value="{{ old('product_price') }}" placeholder="0.00">
                                    @error('product_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-text pe-help">Opcional: se puede poner después desde el inventario.</div>
                            </div>
                        </div>
                        <div id="pe-profit" class="pe-profit d-none mt-3"></div>
                    </div>
                </section>

                {{-- 5. Fotos --}}
                <section class="card border-0 shadow-sm pe-step mt-3 {{ $hasErr(['images', 'images.*']) ? 'has-error' : '' }}">
                    <div class="card-body">
                        <div class="pe-step-head">
                            <span class="pe-step-num">5</span>
                            <div>
                                <h6 class="mb-0 fw-bold">Fotos <span class="text-muted fw-normal small">(opcional)</span></h6>
                                <small class="text-muted">Del estado actual del artículo. Hasta 20 fotos (JPG, PNG o WebP, 5 MB cada una).</small>
                            </div>
                        </div>

                        <div class="pe-media-tabs mb-3">
                            <button type="button" class="pe-media-tab active" id="pe-tab-upload"><i class="bi bi-upload me-1"></i> Subir archivos</button>
                            <button type="button" class="pe-media-tab" id="pe-tab-camera"><i class="bi bi-camera me-1"></i> Usar cámara</button>
                        </div>

                        <div id="pe-panel-upload">
                            <div id="pe-dropzone" class="pe-dropzone" role="button" tabindex="0">
                                <div class="pe-dz-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                                <div class="fw-bold small">Arrastra las fotos aquí</div>
                                <div class="text-muted small">o haz clic para elegirlas</div>
                                <small id="pe-image-count" class="text-muted d-block mt-1">Sin fotos</small>
                            </div>
                            <input type="file" id="pe-images-input" name="images[]" class="d-none" multiple accept="image/*">
                            @error('images')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @error('images.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div id="pe-panel-camera" class="d-none">
                            <video id="pe-cam-video" autoplay playsinline muted class="pe-cam"></video>
                            <canvas id="pe-cam-canvas" class="d-none"></canvas>
                            <div class="mt-2">
                                <x-btn icon="camera-fill" type="button" id="pe-cam-capture">Capturar foto</x-btn>
                            </div>
                        </div>

                        <div id="pe-preview-grid" class="pe-preview-grid mt-3"></div>
                    </div>
                </section>

                {{-- 6. Más detalles --}}
                <section class="card border-0 shadow-sm pe-step mt-3 {{ $hasErr(['movement_date', 'reference', 'notes']) ? 'has-error' : '' }}">
                    <div class="card-body py-2">
                        <button type="button" class="pe-more-toggle {{ $moreOpen ? '' : 'collapsed' }}" data-bs-toggle="collapse" data-bs-target="#pe-more" aria-expanded="{{ $moreOpen ? 'true' : 'false' }}">
                            <span class="pe-step-num">6</span>
                            <span class="fw-bold small">Más detalles</span>
                            <span class="text-muted small d-none d-sm-inline">— fecha, referencia y notas (opcional)</span>
                            <i class="bi bi-chevron-down ms-auto pe-chevron"></i>
                        </button>
                        <div id="pe-more" class="collapse {{ $moreOpen ? 'show' : '' }}">
                            <div class="row g-3 pt-2 pb-2">
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold" for="movement_date">Fecha del ingreso</label>
                                    <input type="datetime-local" name="movement_date" id="movement_date"
                                           class="form-control form-control-sm @error('movement_date') is-invalid @enderror"
                                           value="{{ old('movement_date', now()->format('Y-m-d\TH:i')) }}">
                                    @error('movement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold" for="reference">Referencia</label>
                                    <input type="text" name="reference" id="reference" maxlength="100"
                                           class="form-control form-control-sm @error('reference') is-invalid @enderror"
                                           value="{{ old('reference') }}" placeholder="Ej: FACTURA-001, DONACIÓN">
                                    @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold" for="notes">Notas</label>
                                    <textarea name="notes" id="notes" rows="2" maxlength="2000"
                                              class="form-control form-control-sm @error('notes') is-invalid @enderror"
                                              placeholder="Observaciones…">{{ old('notes') }}</textarea>
                                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            {{-- Resumen --}}
            <div class="col-lg-4">
                <aside class="card border-0 shadow-sm pe-summary">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-1"></i> Resumen</h6>
                        <dl class="pe-sum-list mb-3">
                            <dt>Sucursal</dt><dd id="sum-branch" class="text-muted">—</dd>
                            <dt>Categoría</dt><dd id="sum-category" class="text-muted">—</dd>
                            <dt>Producto</dt><dd id="sum-name" class="text-muted">—</dd>
                            <dt>Código</dt><dd><span id="sum-code" class="code-pill">{{ old('product_code', $nextProductCode) }}</span></dd>
                            <dt>Cantidad</dt><dd>1 unidad <i class="bi bi-info-circle text-muted" title="Cada ingreso es un artículo único: si tienes varios, ingrésalos por separado."></i></dd>
                            <dt>Costo</dt><dd id="sum-cost" class="text-muted">—</dd>
                            <dt>Precio de venta</dt><dd id="sum-price" class="text-muted">—</dd>
                            <dt>Fotos</dt><dd id="sum-photos" class="text-muted">Sin fotos</dd>
                        </dl>

                        <x-btn icon="box-arrow-in-down" :block="true" type="submit" id="pe-submit">Ingresar producto</x-btn>
                        <x-btn variant="ghost" :block="true" :href="route('inventory.index')" class="text-muted mt-1">Cancelar</x-btn>
                    </div>
                </aside>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .pe-step { transition: box-shadow .2s ease; }
    .pe-step.has-error { box-shadow: 0 0 0 2px rgba(220, 53, 69, .45) !important; }
    .pe-step-head { display: flex; align-items: flex-start; gap: .65rem; margin-bottom: .9rem; }
    .pe-step-num {
        width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0;
        display: inline-flex; align-items: center; justify-content: center;
        background: #eef2ff; color: #4f46e5; font-weight: 700; font-size: var(--fs-sm);
    }
    .pe-step.is-done .pe-step-num { background: #16a34a; color: #fff; }
    .pe-step.has-error .pe-step-num { background: #dc3545; color: #fff; }
    .pe-help { font-size: var(--fs-xs); }

    /* Sucursal */
    .pe-branch {
        --branch-color: #4f46e5;
        display: inline-flex; align-items: center; gap: .45rem; cursor: pointer; user-select: none;
        padding: .45rem .9rem; border-radius: 999px; font-size: var(--fs-base); font-weight: 600;
        border: 1.5px solid color-mix(in srgb, var(--branch-color) 35%, #dee2e6);
        background: #fff; color: #343a40; transition: all .15s ease;
    }
    .pe-branch:hover { background: color-mix(in srgb, var(--branch-color) 8%, #fff); }
    .pe-branch .pe-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--branch-color); }
    .pe-branch:has(input:checked), .pe-branch.is-fixed {
        background: color-mix(in srgb, var(--branch-color) 14%, #fff);
        border-color: var(--branch-color); color: color-mix(in srgb, var(--branch-color) 75%, #000);
    }
    .pe-branch:has(input:focus-visible) { outline: 2px solid var(--branch-color); outline-offset: 2px; }
    .pe-branch.is-fixed { cursor: default; }

    /* Categorías */
    .pe-cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(118px, 1fr)); gap: .5rem; }
    .pe-cat {
        --cat-color: #4f46e5;
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .3rem;
        min-height: 78px; padding: .55rem .4rem; border-radius: .75rem; cursor: pointer; text-align: center;
        border: 1.5px solid #e5e7eb; background: #fff; transition: all .15s ease; user-select: none;
    }
    .pe-cat i { font-size: var(--fs-lg); color: var(--cat-color); }
    .pe-cat span { font-size: var(--fs-xs); font-weight: 600; color: #495057; line-height: 1.15; }
    .pe-cat:hover { border-color: color-mix(in srgb, var(--cat-color) 50%, #e5e7eb); transform: translateY(-1px); }
    .pe-cat:has(input:checked) {
        border-color: var(--cat-color); background: color-mix(in srgb, var(--cat-color) 10%, #fff);
        box-shadow: 0 0 0 3px color-mix(in srgb, var(--cat-color) 18%, transparent);
    }
    .pe-cat:has(input:checked) span { color: #212529; }
    .pe-cat:has(input:focus-visible) { outline: 2px solid var(--cat-color); outline-offset: 2px; }

    .pe-empty {
        border: 1.5px dashed #dee2e6; border-radius: .75rem; padding: 1rem; text-align: center;
        color: #6c757d; font-size: var(--fs-base);
    }

    .pe-profit { border-radius: .6rem; padding: .5rem .75rem; font-size: var(--fs-base); }
    .pe-profit.is-gain { background: #ecfdf5; color: #047857; }
    .pe-profit.is-loss { background: #fef2f2; color: #b91c1c; }

    /* Fotos */
    .pe-media-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: .3rem; background: #f1f3f5; padding: .25rem; border-radius: .6rem; }
    .pe-media-tab { border: 0; background: transparent; border-radius: .45rem; padding: .45rem .5rem; font-size: var(--fs-sm); color: #495057; font-weight: 600; }
    .pe-media-tab.active { background: #fff; color: #0d6efd; box-shadow: 0 1px 3px rgba(0,0,0,.09); }
    .pe-dropzone {
        border: 2px dashed #b9c6d8; background: #f8fbff; border-radius: 12px; padding: 16px 12px;
        text-align: center; cursor: pointer; transition: all .2s ease;
    }
    .pe-dropzone:hover, .pe-dropzone.is-dragover { border-color: #0d6efd; background: #eaf2ff; }
    .pe-dz-icon { font-size: var(--fs-lg); color: #0d6efd; }
    .pe-cam { width: 260px; max-width: 100%; border-radius: 10px; border: 2px solid #dee2e6; background: #000; display: block; }
    .pe-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); gap: .5rem; }
    .pe-preview-grid:empty { display: none; }
    .pe-preview-item { position: relative; border-radius: 10px; overflow: hidden; border: 2px solid #e9ecef; aspect-ratio: 1; }
    .pe-preview-item img { width: 100%; height: 100%; object-fit: cover; }
    .pe-preview-item button {
        position: absolute; top: 3px; right: 3px; width: 22px; height: 22px; border-radius: 50%;
        background: rgba(220,53,69,.9); color: #fff; border: 0; font-size: var(--fs-xs);
        display: flex; align-items: center; justify-content: center;
    }

    /* Más detalles */
    .pe-more-toggle {
        display: flex; align-items: center; gap: .6rem; width: 100%; border: 0; background: transparent;
        padding: .35rem 0; text-align: left;
    }
    .pe-more-toggle .pe-chevron { transition: transform .2s ease; }
    .pe-more-toggle:not(.collapsed) .pe-chevron { transform: rotate(180deg); }

    /* Resumen */
    .pe-summary { position: sticky; top: 1rem; }
    .pe-sum-list { display: grid; grid-template-columns: auto 1fr; gap: .35rem .75rem; font-size: var(--fs-base); margin: 0; }
    .pe-sum-list dt { color: #6c757d; font-weight: 500; }
    .pe-sum-list dd { margin: 0; text-align: right; font-weight: 600; word-break: break-word; }
    .pe-sum-list dd.text-muted { font-weight: 400; }

    @media (max-width: 991.98px) {
        .pe-summary { position: static; }
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    const $form = $('#pe-form');
    const $submit = $('#pe-submit');
    const $name = $('#product_name');
    const $code = $('#product_code');
    const $cost = $('#unit_cost');
    const $price = $('#product_price');
    const $modelYear = $('#product_model_year');
    const currency = @json($currencySymbol);
    const money = v => currency + ' ' + Number(v).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    /* ── Año del vehículo ── */
    (function () {
        const year = new Date().getFullYear();
        let html = '<option value="">Seleccionar…</option>';
        for (let y = year + 1; y >= 1980; y--) html += `<option value="${y}">${y}</option>`;
        $modelYear.html(html).val(String($modelYear.data('old') || ''));
    })();

    /* ── Categoría → campos ── */
    let autoName = '';
    function applyCategory() {
        const $cat = $('input[name="credit_category_id"]:checked');
        const layout = $cat.length ? String($cat.data('layout')) : null;

        $('#pe-data-empty').toggleClass('d-none', !!layout);
        $('#pe-data-body').toggleClass('d-none', !layout);

        $('.pe-fields').each(function () {
            const on = $(this).data('layout') == layout;
            $(this).toggleClass('d-none', !on).find('input,select,textarea').prop('disabled', !on);
        });

        // Garrafa: el nombre es la categoría (se puede cambiar).
        if (layout === '3') {
            if (!$name.val() || $name.val() === autoName) {
                autoName = String($cat.data('name') || 'GARRAFA');
                $name.val(autoName);
            }
        } else if ($name.val() === autoName && autoName) {
            $name.val('');
            autoName = '';
        }
        refresh();
    }
    $('input[name="credit_category_id"]').on('change', function () {
        applyCategory();
        clearHint('credit_category_id');
        if ($name.is(':visible') && !$name.val()) setTimeout(() => $name.trigger('focus'), 50);
    });

    /* ── Resumen y estado de los pasos ── */
    function setSum(id, text) {
        const $el = $('#' + id);
        $el.text(text || '—').toggleClass('text-muted', !text);
    }
    function refresh() {
        const $branch = $('input[name="branch_id"]:checked');
        const branchName = $branch.length ? $branch.data('name') : ($('.pe-branch.is-fixed').data('name') || '');
        const $cat = $('input[name="credit_category_id"]:checked');
        setSum('sum-branch', branchName);
        setSum('sum-category', $cat.length ? $cat.data('name') : '');
        setSum('sum-name', $.trim($name.val()));
        $('#sum-code').text($.trim($code.val()) || @json($nextProductCode));

        const cost = $cost.val() === '' ? null : parseFloat($cost.val());
        const price = $price.val() === '' ? null : parseFloat($price.val());
        setSum('sum-cost', cost === null || isNaN(cost) ? '' : money(cost));
        setSum('sum-price', price === null || isNaN(price) ? '' : money(price));

        const $profit = $('#pe-profit');
        if (cost !== null && price !== null && !isNaN(cost) && !isNaN(price) && price > 0) {
            const gain = price - cost;
            const pct = cost > 0 ? ` (${Math.round(gain / cost * 100)}%)` : '';
            $profit.removeClass('d-none is-gain is-loss').addClass(gain >= 0 ? 'is-gain' : 'is-loss')
                .html(gain >= 0
                    ? `<i class="bi bi-graph-up-arrow me-1"></i> Ganancia estimada: <strong>${money(gain)}</strong>${pct}`
                    : `<i class="bi bi-exclamation-triangle me-1"></i> El precio es menor que el costo: pérdida de <strong>${money(-gain)}</strong>`);
        } else {
            $profit.addClass('d-none');
        }

        $('#pe-step-branch').toggleClass('is-done', !!branchName);
        $('#pe-step-category').toggleClass('is-done', $cat.length > 0);
        $('#pe-step-data').toggleClass('is-done', $cat.length > 0 && $.trim($name.val()) !== '');
        $('#pe-step-values').toggleClass('is-done', cost !== null && !isNaN(cost) && cost >= 0);
    }
    $form.on('input change', 'input,select,textarea', refresh);
    $('input[name="branch_id"]').on('change', () => clearHint('branch_id'));
    $name.on('input', function () { if ($.trim(this.value)) clearHint('product_name'); });
    $cost.on('input', function () { if (this.value !== '') clearHint('unit_cost'); });

    /* ── Validación antes de enviar + evitar doble envío ── */
    function showHint(field) {
        $(`.pe-hint[data-for="${field}"]`).removeClass('d-none').show();
        $(`[name="${field}"]`).not('[type=radio]').addClass('is-invalid');
    }
    function clearHint(field) {
        $(`.pe-hint[data-for="${field}"]`).addClass('d-none').hide();
        $(`[name="${field}"]`).not('[type=radio]').removeClass('is-invalid');
        $(`[name="${field}"]`).closest('.pe-step').removeClass('has-error');
    }

    $form.on('submit', function (e) {
        if ($submit.prop('disabled')) { e.preventDefault(); return; }

        const missing = [];
        if (!$('[name="branch_id"]').filter(function () { return this.type !== 'radio' || this.checked; }).length) missing.push('branch_id');
        if (!$('input[name="credit_category_id"]:checked').length) missing.push('credit_category_id');
        else if (!$.trim($name.val())) missing.push('product_name');
        if ($cost.val() === '' || parseFloat($cost.val()) < 0) missing.push('unit_cost');

        if (missing.length) {
            e.preventDefault();
            missing.forEach(f => {
                showHint(f);
                $(`[name="${f}"]`).first().closest('.pe-step').addClass('has-error');
            });
            const $first = $(`[name="${missing[0]}"]`).first().closest('.pe-step');
            $('html, body').animate({ scrollTop: $first.offset().top - 80 }, 250);
            const $focus = $(`[name="${missing[0]}"]`).not('[type=radio]').first();
            if ($focus.length) setTimeout(() => $focus.trigger('focus'), 260);
            return;
        }

        $submit.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Ingresando…');
    });

    /* ── Fotos ── */
    const $input = $('#pe-images-input');
    const $dropzone = $('#pe-dropzone');
    const $grid = $('#pe-preview-grid');
    let files = new DataTransfer();

    function renderPhotos() {
        $grid.empty();
        Array.from(files.files).forEach((file, idx) => {
            $grid.append(`<div class="pe-preview-item"><img src="${URL.createObjectURL(file)}" alt="">
                <button type="button" data-idx="${idx}" title="Quitar"><i class="bi bi-x"></i></button></div>`);
        });
        const n = files.files.length;
        const label = n ? `${n} foto${n === 1 ? '' : 's'}` : 'Sin fotos';
        $('#pe-image-count').text(label);
        setSum('sum-photos', n ? label : '');
        $input[0].files = files.files;
    }
    function addFiles(list) {
        Array.from(list).forEach(f => {
            if (f.type.startsWith('image/') && files.files.length < 20) files.items.add(f);
        });
        renderPhotos();
    }
    $dropzone.on('click keydown', function (e) {
        if (e.type === 'click' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $input.trigger('click'); }
    });
    $input.on('change', function () { addFiles(this.files); });
    $dropzone.on('dragenter dragover', e => { e.preventDefault(); $dropzone.addClass('is-dragover'); });
    $dropzone.on('dragleave dragend drop', e => { e.preventDefault(); $dropzone.removeClass('is-dragover'); });
    $dropzone.on('drop', e => { const f = e.originalEvent?.dataTransfer?.files; if (f?.length) addFiles(f); });
    $grid.on('click', 'button', function () {
        const idx = parseInt($(this).data('idx'), 10);
        const dt = new DataTransfer();
        Array.from(files.files).forEach((f, i) => { if (i !== idx) dt.items.add(f); });
        files = dt;
        renderPhotos();
    });

    /* ── Cámara ── */
    const video = document.getElementById('pe-cam-video');
    const canvas = document.getElementById('pe-cam-canvas');
    let stream = null;
    function stopCam() {
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
        if (video) video.srcObject = null;
    }
    $('#pe-tab-upload').on('click', function () {
        $(this).addClass('active'); $('#pe-tab-camera').removeClass('active');
        $('#pe-panel-upload').removeClass('d-none'); $('#pe-panel-camera').addClass('d-none');
        stopCam();
    });
    $('#pe-tab-camera').on('click', function () {
        if (!navigator.mediaDevices?.getUserMedia) { alert('Este navegador no permite usar la cámara.'); return; }
        $(this).addClass('active'); $('#pe-tab-upload').removeClass('active');
        $('#pe-panel-camera').removeClass('d-none'); $('#pe-panel-upload').addClass('d-none');
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then(s => { stream = s; video.srcObject = s; })
            .catch(() => { alert('No se pudo acceder a la cámara.'); $('#pe-tab-upload').trigger('click'); });
    });
    $('#pe-cam-capture').on('click', function () {
        if (!stream) return;
        canvas.width = video.videoWidth; canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(blob => {
            if (!blob) return;
            addFiles([new File([blob], 'captura-' + Date.now() + '.jpg', { type: 'image/jpeg' })]);
            $('#pe-tab-upload').trigger('click');
        }, 'image/jpeg', 0.92);
    });

    applyCategory();
    refresh();
});
</script>
@endpush
