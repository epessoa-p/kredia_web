@php
    $selectedPermissions = old('permissions', $cargo?->role?->permissions?->pluck('slug')->toArray() ?? []);
@endphp

@push('styles')
<style>
    .perm-module { border: 1px solid #e5e7eb; border-radius: .6rem; background: #fff; height: 100%; }
    .perm-module-head {
        display: flex; align-items: center; justify-content: space-between; gap: .5rem;
        padding: .45rem .7rem; border-bottom: 1px solid #f0f1f3; background: #f9fafb;
        border-radius: .6rem .6rem 0 0;
    }
    .perm-module-title { font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #4b5563; }
    .perm-module-count { font-size: var(--fs-xs); color: #6b7280; }
    .perm-module-body { padding: .45rem .7rem .55rem; }
    .perm-module-body .form-check { margin-bottom: .15rem; }
    .perm-module-body .form-check-label { font-size: var(--fs-sm); }
    .perm-module.is-full { border-color: #9ec5fe; }
    .perm-module.is-full .perm-module-head { background: #eef5ff; }
    .cargo-save-bar {
        position: sticky; bottom: 0; z-index: 5;
        display: flex; justify-content: space-between; align-items: center; gap: .5rem; flex-wrap: wrap;
        padding: .55rem .9rem; margin-top: .75rem;
        background: rgba(255,255,255,.96); border: 1px solid #e5e7eb; border-radius: .6rem;
        box-shadow: 0 -4px 14px rgba(0,0,0,.05);
    }
</style>
@endpush

<div class="container-fluid">
    <x-page-header :title="$cargo ? 'Editar cargo' : 'Nuevo cargo'" icon="briefcase" :count="$cargo?->name" :back="route('cargos.index')" />

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

    <form action="{{ $action }}" method="POST" id="cargoForm">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        {{-- Datos del cargo --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="bi bi-briefcase"></i> Datos del cargo</h6>
                <div class="row g-2 align-items-end">
                    @if($companies->count() > 1)
                        <div class="col-lg-3 col-md-6">
                            <label class="form-label">Empresa <span class="text-danger">*</span></label>
                            <select name="company_id" class="form-select form-select-sm" required>
                                <option value="">Seleccionar empresa</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" @selected((string) old('company_id', $cargo?->company_id) === (string) $company->id)>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($companies->count() === 1)
                        <input type="hidden" name="company_id" value="{{ old('company_id', $cargo?->company_id ?? $companies->first()->id) }}">
                    @endif

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label">Nombre del cargo <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                               value="{{ old('name', $cargo?->name) }}" placeholder="Ej: CAJERO" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="description" class="form-control form-control-sm"
                               value="{{ old('description', $cargo?->description) }}" placeholder="Funciones principales del cargo...">
                    </div>

                    <div class="col-auto">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="active" value="1" id="cargoActive" @checked(old('active', $cargo?->active ?? true))>
                            <label class="form-check-label" for="cargoActive">Activo</label>
                        </div>
                    </div>
                </div>

                <div class="text-muted mt-2 fs-xs">
                    <i class="bi bi-info-circle"></i>
                    @if($cargo)
                        El personal con este cargo tiene los permisos marcados abajo
                        @if($cargo->role) (rol <strong>{{ $cargo->role->name }}</strong>)@endif.
                    @else
                        Se crea un rol propio para este cargo, con su mismo nombre. El personal con este cargo tendrá los permisos que marques abajo.
                    @endif
                </div>
                @if($cargo && ($sharedCargos ?? collect())->isNotEmpty())
                    <div class="alert alert-warning py-1 px-2 mt-2 mb-0 fs-xs">
                        <i class="bi bi-exclamation-triangle"></i>
                        Este rol también lo usan los cargos <strong>{{ $sharedCargos->join(', ', ' y ') }}</strong>: los cambios de permisos les afectan también.
                    </div>
                @endif
            </div>
        </div>

        {{-- Permisos --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-key"></i> Permisos del cargo</h6>
                    <div class="d-flex align-items-center gap-2">
                        <div class="input-group input-group-sm" style="width: 220px;">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control form-control-sm no-upper" id="permSearch" placeholder="Buscar permiso…">
                        </div>
                        <x-btn variant="secondary" icon="check-all" id="btn-select-all">Todos</x-btn>
                        <x-btn variant="secondary" icon="x-lg" id="btn-deselect-all">Ninguno</x-btn>
                    </div>
                </div>

                <div class="row g-2" id="permissions-container">
                    @foreach($permissions as $module => $modulePerms)
                        <div class="col-sm-6 col-lg-4 col-xxl-3 perm-col">
                            <div class="perm-module">
                                <div class="perm-module-head">
                                    <div class="form-check mb-0">
                                        <input class="form-check-input module-toggle" type="checkbox" id="mod_{{ $loop->index }}" title="Marcar todo el módulo">
                                        <label class="form-check-label perm-module-title" for="mod_{{ $loop->index }}">{{ \App\Models\Permission::moduleLabel($module) }}</label>
                                    </div>
                                    <span class="perm-module-count"></span>
                                </div>
                                <div class="perm-module-body">
                                    @foreach($modulePerms as $permission)
                                        <div class="form-check perm-item">
                                            <input class="form-check-input perm-check" type="checkbox" name="permissions[]"
                                                   value="{{ $permission->slug }}" id="perm_{{ $permission->id }}"
                                                   @checked(in_array($permission->slug, $selectedPermissions, true))>
                                            <label class="form-check-label" for="perm_{{ $permission->id }}" title="{{ $permission->slug }}">
                                                {{ $permission->name }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="cargo-save-bar">
            <span class="text-muted small">
                <i class="bi bi-shield-check me-1"></i><strong id="perm-count">{{ count($selectedPermissions) }}</strong> permisos seleccionados
            </span>
            <div class="d-flex gap-2">
                <x-btn variant="secondary" :href="route('cargos.index')">Cancelar</x-btn>
                <x-btn type="submit" id="cargoSubmit" icon="check-lg">{{ $cargo ? 'Guardar cambios' : 'Crear cargo' }}</x-btn>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const checks = Array.from(document.querySelectorAll('.perm-check'));
    const count = document.getElementById('perm-count');

    function refresh() {
        count.textContent = checks.filter(c => c.checked).length;
        document.querySelectorAll('.perm-module').forEach(function (mod) {
            const items = Array.from(mod.querySelectorAll('.perm-check'));
            const on = items.filter(c => c.checked).length;
            const toggle = mod.querySelector('.module-toggle');
            toggle.checked = on === items.length && items.length > 0;
            toggle.indeterminate = on > 0 && on < items.length;
            mod.querySelector('.perm-module-count').textContent = on + '/' + items.length;
            mod.classList.toggle('is-full', on === items.length && items.length > 0);
        });
    }

    checks.forEach(c => c.addEventListener('change', refresh));
    document.querySelectorAll('.module-toggle').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            toggle.closest('.perm-module').querySelectorAll('.perm-check').forEach(c => { c.checked = toggle.checked; });
            refresh();
        });
    });
    document.getElementById('btn-select-all').addEventListener('click', () => { checks.forEach(c => { c.checked = true; }); refresh(); });
    document.getElementById('btn-deselect-all').addEventListener('click', () => { checks.forEach(c => { c.checked = false; }); refresh(); });

    // Buscar: oculta los permisos (y módulos) que no coinciden. Enter no envía el formulario.
    document.getElementById('permSearch').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') e.preventDefault();
    });
    document.getElementById('permSearch').addEventListener('input', function () {
        const term = this.value.trim().toLowerCase();
        document.querySelectorAll('.perm-col').forEach(function (col) {
            let visible = 0;
            const moduleMatch = col.querySelector('.perm-module-title').textContent.toLowerCase().includes(term);
            col.querySelectorAll('.perm-item').forEach(function (item) {
                const show = !term || moduleMatch || item.textContent.toLowerCase().includes(term);
                item.classList.toggle('d-none', !show);
                if (show) visible++;
            });
            col.classList.toggle('d-none', visible === 0);
        });
    });

    // Evita el doble envío.
    document.getElementById('cargoForm').addEventListener('submit', function () {
        const btn = document.getElementById('cargoSubmit');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';
    });

    refresh();
});
</script>
@endpush
