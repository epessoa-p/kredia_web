@extends('layouts.app')

@section('title', 'Editar Empresa')

@push('styles')
<style>
    .company-form-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
    }

    .company-form-header {
        background: var(--mod-color);
        color: #fff;
        border-radius: 14px 14px 0 0;
        padding: 18px 20px;
    }

    .company-form-section {
        border: 1px solid #e6ebf2;
        border-radius: 12px;
        padding: 14px;
        background: #fbfdff;
    }

    .upload-dropzone {
        border: 2px dashed #9fb8dd;
        border-radius: 12px;
        padding: 18px;
        text-align: center;
        background: #f8fbff;
        transition: all .2s ease;
        cursor: pointer;
    }

    .upload-dropzone:hover,
    .upload-dropzone.is-dragover {
        border-color: #2563eb;
        background: #eef5ff;
    }

    .upload-preview {
        margin-top: 10px;
        display: block;
    }

    .upload-preview img {
        max-width: 100%;
        border-radius: 10px;
        border: 1px solid #d7deea;
        object-fit: cover;
        max-height: 130px;
    }

    .upload-preview.cover img {
        width: 100%;
        max-height: 150px;
    }

    .form-help {
        font-size: var(--fs-sm);
        color: #64748b;
    }
</style>
@endpush

@section('page')
<x-page-header title="Editar empresa" icon="pencil" :back="route('companies.show', $company)" />

<div class="card company-form-card">
    <div class="company-form-header">
        <div class="fw-semibold">Actualizando: {{ $company->name }}</div>
        <small>Modifica datos generales y recursos visuales de la empresa.</small>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('companies.update', $company) }}" method="POST" enctype="multipart/form-data" class="row g-4">
            @csrf
            @method('PUT')

            <div class="col-lg-8">
                <div class="company-form-section">
                    <h6 class="mb-3">Información general</h6>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $company->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="ruc" class="form-label">RUC</label>
                            <input type="text" id="ruc" name="ruc" class="form-control @error('ruc') is-invalid @enderror" value="{{ old('ruc', $company->ruc) }}">
                            @error('ruc')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $company->email) }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Telefono</label>
                            <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $company->phone) }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Direccion</label>
                            <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address', $company->address) }}">
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Descripcion</label>
                            <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="4">{{ old('description', $company->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="company-form-section mt-3">
                    <h6 class="mb-3">Moneda</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="currency_symbol" class="form-label">Símbolo de la moneda <span class="text-danger">*</span></label>
                            <input type="text" id="currency_symbol" name="currency_symbol" maxlength="10"
                                   class="form-control no-upper @error('currency_symbol') is-invalid @enderror"
                                   value="{{ old('currency_symbol', $company->currency_symbol ?: 'Bs') }}" required>
                            @error('currency_symbol')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-8 d-flex align-items-end">
                            <div class="form-help">Se muestra junto a los montos en el sistema y en la app móvil (ej. <strong>Bs</strong> para bolivianos, <strong>$us</strong> para dólares).</div>
                        </div>
                    </div>
                </div>

                <div class="company-form-section mt-3">
                    <h6 class="mb-3">Reglas de interés</h6>
                    <div class="form-check">
                        <input type="hidden" name="interest_next_day" value="0">
                        <input type="checkbox" id="interest_next_day" name="interest_next_day" value="1"
                               class="form-check-input"
                               {{ old('interest_next_day', $company->interest_next_day) ? 'checked' : '' }}>
                        <label for="interest_next_day" class="form-check-label fw-semibold">
                            Cobrar el siguiente interés al día siguiente del aniversario
                        </label>
                    </div>
                    <div class="form-help mt-1">
                        Si un préstamo se crea el 15, el siguiente interés recién se genera el 16; en la
                        fecha exacta del aniversario (el 15) el cliente aún debe un solo interés.
                        Si se desactiva, el interés aparece el mismo día del aniversario (comportamiento actual).
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="company-form-section mb-3">
                    <h6 class="mb-3">Logo de empresa</h6>
                    <div class="upload-dropzone" data-input-id="logo" data-preview-id="logo-preview">
                        <i class="bi bi-image fs-4 d-block mb-2 text-primary"></i>
                        <div class="fw-semibold">Arrastra el logo aqui</div>
                        <div class="form-help">o haz clic para reemplazar</div>
                        <div class="form-help mt-1">JPG, PNG, WEBP - max 2MB</div>
                    </div>
                    <input type="file" id="logo" name="logo" class="d-none @error('logo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp">
                    <div id="logo-preview" class="upload-preview {{ $company->logo ? '' : 'd-none' }}">
                        @if($company->logo)
                            <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo empresa">
                        @endif
                    </div>
                    @error('logo')
                        <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                    @enderror
                </div>

                <div class="company-form-section">
                    <h6 class="mb-3">Imagen de portada</h6>
                    <div class="upload-dropzone" data-input-id="cover_image" data-preview-id="cover-preview">
                        <i class="bi bi-card-image fs-4 d-block mb-2 text-primary"></i>
                        <div class="fw-semibold">Arrastra la portada aqui</div>
                        <div class="form-help">Ideal para banners y encabezados</div>
                        <div class="form-help mt-1">JPG, PNG, WEBP - max 4MB</div>
                    </div>
                    <input type="file" id="cover_image" name="cover_image" class="d-none @error('cover_image') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp">
                    <div id="cover-preview" class="upload-preview cover {{ $company->cover_image ? '' : 'd-none' }}">
                        @if($company->cover_image)
                            <img src="{{ asset('storage/' . $company->cover_image) }}" alt="Portada empresa">
                        @endif
                    </div>
                    @error('cover_image')
                        <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-12 d-flex gap-2 justify-content-end pt-2">
                <x-btn variant="secondary" size="md" :href="route('companies.show', $company)">Cancelar</x-btn>
                <x-btn type="submit" size="md" icon="check-lg">Guardar Cambios</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function bindDropzone(dropzone) {
        const inputId = dropzone.dataset.inputId;
        const previewId = dropzone.dataset.previewId;
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (!input || !preview) return;

        function renderPreview(file) {
            if (!file) {
                preview.classList.add('d-none');
                preview.innerHTML = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                preview.classList.remove('d-none');
                preview.innerHTML = '<img src="' + e.target.result + '" alt="preview">';
            };
            reader.readAsDataURL(file);
        }

        dropzone.addEventListener('click', function () {
            input.click();
        });

        input.addEventListener('change', function () {
            renderPreview(this.files && this.files[0] ? this.files[0] : null);
        });

        ['dragenter', 'dragover'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            dropzone.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone.addEventListener('drop', function (e) {
            const files = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files : null;
            if (!files || !files.length) return;
            input.files = files;
            renderPreview(files[0]);
        });
    }

    document.querySelectorAll('.upload-dropzone').forEach(bindDropzone);
});
</script>
@endpush
