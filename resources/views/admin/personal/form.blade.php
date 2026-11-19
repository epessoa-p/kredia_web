<div class="container-fluid" style="max-width: 1100px;">
    <x-page-header :title="$personal ? 'Editar personal' : 'Nuevo personal'" icon="person-vcard" subtitle="Al crear personal se genera usuario automáticamente y se asigna rol según el cargo." :back="route('personal.index')" />

    @if($errors->any())
        @php
            $errorFallbacks = [
                'validation.min.string' => 'La contraseña debe tener al menos 8 caracteres.',
                'validation.unique' => 'El correo electrónico ya está registrado.',
                'validation.required' => 'Completa los campos obligatorios.',
                'validation.email' => 'Ingresa un correo electrónico válido.',
                'validation.confirmed' => 'La confirmación de contraseña no coincide.',
            ];
            $friendlyErrors = collect($errors->all())->map(function ($error) use ($errorFallbacks) {
                return $errorFallbacks[$error] ?? $error;
            });
        @endphp
        <div class="alert fade show border border-danger-subtle bg-danger bg-opacity-10 rounded-3 px-3 py-2 mb-3 shadow-sm" role="alert">
            <div class="d-flex gap-2 align-items-start">
                <i class="bi bi-exclamation-octagon-fill text-danger mt-1"></i>
                <div class="flex-grow-1">
                    <strong class="d-block text-danger">Por favor corrige los siguientes errores:</strong>
                    <ul class="mb-0 mt-1 ps-3 small">
                        @foreach($friendlyErrors as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        </div>
    @endif

    <form action="{{ $action }}" method="POST" class="row g-4">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-badge"></i> Datos del personal</h6>
                    <div class="row g-3">
                        @if($companies->count() > 1)
                            <div class="col-md-6">
                                <label class="form-label">Empresa</label>
                                <select name="company_id" id="company_id" class="form-select form-select-sm" required>
                                    <option value="">Seleccionar empresa</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ (string) old('company_id', $personal?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-md-6">
                            <label class="form-label">Cargo <span class="text-danger">*</span></label>
                            <select name="cargo_id" id="personal_cargo_id" class="form-select form-select-sm" required>
                                <option value="">Seleccionar cargo</option>
                                @foreach($cargos as $cargo)
                                    <option value="{{ $cargo->id }}" {{ (string) old('cargo_id', $personal?->cargo_id) === (string) $cargo->id ? 'selected' : '' }}>
                                        {{ $cargo->name }} · Rol: {{ $cargo->role?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6" id="personal_branch_wrap">
                            <label class="form-label" id="personal_branch_label">
                                Sucursal <span class="text-danger" id="personal_branch_required_mark">*</span>
                            </label>
                            <select name="branch_id" id="personal_branch_id" class="form-select form-select-sm">
                                <option value="">— Seleccionar sucursal —</option>
                                @foreach($branches ?? [] as $branch)
                                    <option value="{{ $branch->id }}" {{ (string) old('branch_id', $personal?->branch_id) === (string) $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted" id="personal_branch_hint" style="display:none;">
                                El gerente puede dejar este campo vacío.
                            </div>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control form-control-sm" value="{{ old('full_name', $personal?->full_name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Documento</label>
                            <input type="text" name="id_number" class="form-control form-control-sm" value="{{ old('id_number', $personal?->id_number) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="phone" class="form-control form-control-sm" value="{{ old('phone', $personal?->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de ingreso</label>
                            <input type="date" name="hire_date" class="form-control form-control-sm" value="{{ old('hire_date', optional($personal?->hire_date)->toDateString()) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="address" class="form-control form-control-sm" value="{{ old('address', $personal?->address) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea name="notes" rows="2" class="form-control form-control-sm">{{ old('notes', $personal?->notes) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $personal?->active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-gear"></i> Datos del usuario</h6>
                    <div class="alert alert-info small">
                        El nombre de usuario se genera automáticamente con base en el nombre completo.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email de acceso <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror" value="{{ old('email', $personal?->user?->email ?? $personal?->email) }}" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Contraseña {{ $personal ? '(opcional para cambiar)' : '' }} <span class="text-danger">{{ $personal ? '' : '*' }}</span></label>
                        <input type="password" name="password" class="form-control form-control-sm @error('password') is-invalid @enderror" {{ $personal ? '' : 'required' }}>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar contraseña {{ $personal ? '(si cambiaste)' : '' }}</label>
                        <input type="password" name="password_confirmation" class="form-control form-control-sm" {{ $personal ? '' : 'required' }}>
                    </div>

                    <x-btn type="submit" icon="check-lg" :block="true">{{ $personal ? 'Guardar cambios' : 'Crear personal y usuario' }}</x-btn>
                    <x-btn variant="secondary" :block="true" class="mt-2" :href="route('personal.index')">Cancelar</x-btn>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const MANAGER_CARGO_ID = '1';
    const cargoSelect = document.getElementById('personal_cargo_id');
    const branchSelect = document.getElementById('personal_branch_id');
    const requiredMark = document.getElementById('personal_branch_required_mark');
    const hint = document.getElementById('personal_branch_hint');

    function toggleBranchRequired() {
        const isManager = cargoSelect.value === MANAGER_CARGO_ID;
        branchSelect.required = !isManager;
        requiredMark.style.display = isManager ? 'none' : '';
        hint.style.display = isManager ? '' : 'none';
    }

    if (cargoSelect) {
        cargoSelect.addEventListener('change', toggleBranchRequired);
        toggleBranchRequired();
    }
})();
</script>
