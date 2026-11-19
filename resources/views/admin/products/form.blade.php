<div class="container-fluid">
    <x-page-header :title="$product ? 'Editar producto' : 'Nuevo producto'" icon="box-seam" subtitle="Define catálogo, costos y categoría de crédito asociada." :back="route('products.index')" />

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ $action }}" method="POST" class="row g-3">
                @csrf
                @if($method !== 'POST') @method($method) @endif

                @if($companies->count() > 1)
                    <div class="col-md-6">
                        <label class="form-label">Empresa</label>
                        <select name="company_id" class="form-select form-select-sm">
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ (string) old('company_id', $product?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-6">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $product?->name) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Código del producto</label>
                    <input type="text" name="code" maxlength="30" class="form-control form-control-sm @error('code') is-invalid @enderror"
                           value="{{ old('code', $product?->code ?? ($nextProductCode ?? '')) }}"
                           placeholder="{{ $nextProductCode ?? 'PD-00001' }}" @if($product) required @endif>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text fs-xs">
                        @if($product)
                            Los productos de préstamos llevan el código del préstamo (PR-) y los de compras el de la compra (CO-). Se puede cambiar; no se repite en la sucursal.
                        @else
                            Sugerido: el siguiente correlativo PD-. Se puede cambiar; no se repite.
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" class="form-control form-control-sm" value="{{ old('sku', $product?->sku) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Categoría de crédito</label>
                    <select name="credit_category_id" class="form-select form-select-sm">
                        <option value="">Sin categoría</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" {{ (string) old('credit_category_id', $product?->credit_category_id) === (string) $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <small class="text-muted">Si se define, los créditos de este producto heredarán sus reglas.</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Unidad</label>
                    <input type="text" name="unit" class="form-control form-control-sm" value="{{ old('unit', $product?->unit ?? 'unidad') }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Modelo de artículo</label>
                    <input type="text" name="item_model" class="form-control form-control-sm" value="{{ old('item_model', $product?->item_model) }}" placeholder="Solo para artículos (categoría 4)">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Tipo de material</label>
                    <input type="text" name="material_type" class="form-control form-control-sm" value="{{ old('material_type', $product?->material_type) }}" placeholder="Solo para joyas (categoría 2)">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Costo</label>
                    <input type="number" step="0.01" name="cost" class="form-control form-control-sm" value="{{ old('cost', $product?->cost) }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Precio</label>
                    <input type="number" step="0.01" name="price" class="form-control form-control-sm" value="{{ old('price', $product?->price) }}" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <textarea name="description" class="form-control form-control-sm" rows="3">{{ old('description', $product?->description) }}</textarea>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $product?->active ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label">Activo</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <x-btn type="submit" icon="check-lg">Guardar</x-btn>
                    <x-btn variant="secondary" :href="route('products.index')">Cancelar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>