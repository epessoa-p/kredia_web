@extends('layouts.app')

@section('title', 'Nueva Empresa')

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
        display: none;
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
<x-page-header title="Nueva empresa" icon="plus-circle" :back="route('companies.index')" />

<div class="card company-form-card">
    <div class="company-form-header">
        <div class="fw-semibold">Registro de empresa</div>
        <small>Completa la información general y configura los recursos visuales.</small>
    </div>
    <div class="card-body p-4">
        <form action="{{ route('companies.store') }}" method="POST" enctype="multipart/form-data" class="row g-4">
            @csrf

            <div class="col-lg-8">
                <div class="company-form-section">
                    <h6 class="mb-3">Información general</h6>

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="name" class="form-label">Nombre</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label for="ruc" class="form-label">RUC</label>
                            <input type="text" id="ruc" name="ruc" class="form-control @error('ruc') is-invalid @enderror" value="{{ old('ruc') }}">
                            @error('ruc')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">Telefono</label>
                            <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                            @error('phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">Direccion</label>
                            <input type="text" id="address" name="address" class="form-control @error('address') is-invalid @enderror" value="{{ old('address') }}">
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="description" class="form-label">Descripcion</label>
                            <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="4">{{ old('description') }}</textarea>
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
                                   value="{{ old('currency_symbol', 'Bs') }}" required>
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
                               {{ old('interest_next_day') ? 'checked' : '' }}>
                        <label for="interest_next_day" class="form-check-label fw-semibold">
                            Cobrar el siguiente interés al día siguiente del aniversario
                        </label>
                    </div>
                    <div class="form-help mt-1">
                        Si un préstamo se crea el 15, el siguiente interés recién se genera el 16; en la
                        fecha exacta del aniversario (el 15) el cliente aún debe un solo interés.
                    </div>
                </div>

                <div class="company-form-section mt-3">
                    <h6 class="mb-3">Plan y suscripción</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="plan_id" class="form-label">Plan</label>
                            <select id="plan_id" name="plan_id" class="form-select form-select-sm @error('plan_id') is-invalid @enderror" required>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected(old('plan_id') == $plan->id)>{{ $plan->name }}</option>
                                @endforeach
                            </select>
                            @error('plan_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label for="subscription_status" class="form-label">Inicio</label>
                            <select id="subscription_status" name="subscription_status" class="form-select form-select-sm">
                                <option value="trial" @selected(old('subscription_status', 'trial') === 'trial')>Prueba (días del plan)</option>
                                <option value="active" @selected(old('subscription_status') === 'active')>Activa (periodo pagado)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="current_period_end" class="form-label">Fin del periodo pagado</label>
                            <input type="date" id="current_period_end" name="current_period_end" class="form-control form-control-sm @error('current_period_end') is-invalid @enderror" value="{{ old('current_period_end') }}">
                            <div class="form-help">Solo si inicia activa. Vacío = sin vencimiento.</div>
                        </div>
                    </div>
                </div>

                <div class="company-form-section mt-3">
                    <h6 class="mb-3">Usuario administrador de la empresa</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="admin_name" class="form-label">Usuario</label>
                            <input type="text" id="admin_name" name="admin_name" class="form-control form-control-sm @error('admin_name') is-invalid @enderror" value="{{ old('admin_name') }}" required>
                            @error('admin_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="admin_email" class="form-label">Email</label>
                            <input type="email" id="admin_email" name="admin_email" class="form-control form-control-sm @error('admin_email') is-invalid @enderror" value="{{ old('admin_email') }}" required>
                            @error('admin_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="admin_password" class="form-label">Contraseña</label>
                            <input type="password" id="admin_password" name="admin_password" class="form-control form-control-sm @error('admin_password') is-invalid @enderror" required minlength="8" autocomplete="new-password">
                            @error('admin_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="company-form-section mb-3">
                    <h6 class="mb-3">Logo de empresa</h6>
                    <div class="upload-dropzone" data-input-id="logo" data-preview-id="logo-preview">
                        <i class="bi bi-image fs-4 d-block mb-2 text-primary"></i>
                        <div class="fw-semibold">Arrastra el logo aqui</div>
                        <div class="form-help">o haz clic para seleccionar</div>
                        <div class="form-help mt-1">JPG, PNG, WEBP - max 2MB</div>
                    </div>
                    <input type="file" id="logo" name="logo" class="d-none @error('logo') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp">
                    <div id="logo-preview" class="upload-preview"></div>
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
                    <div id="cover-preview" class="upload-preview cover"></div>
                    @error('cover_image')
                        <div class="invalid-feedback d-block mt-2">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-12 d-flex gap-2 justify-content-end pt-2">
                <x-btn variant="secondary" size="md" :href="route('companies.index')">Cancelar</x-btn>
                <x-btn type="submit" size="md" icon="check-lg">Crear Empresa</x-btn>
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
                preview.style.display = 'none';
                preview.innerHTML = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                preview.style.display = 'block';
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
