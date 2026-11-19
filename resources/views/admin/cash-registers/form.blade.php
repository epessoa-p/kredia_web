<div class="container-fluid">
    <x-page-header :title="$cashRegister ? 'Editar caja' : 'Nueva caja'" icon="cash-stack" :subtitle="$cashRegister ? 'Modifica los datos de la caja.' : 'Registra una nueva caja y asígnala a un personal.'" :back="route('cash-registers.index')" />

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ $action }}" method="POST">
                @csrf
                @if($method !== 'POST') @method($method) @endif

                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-3 mb-4">
                    @if($companies->count() > 1)
                        <div class="col-md-6">
                            <label class="form-label">Empresa</label>
                            <select name="company_id" class="form-select form-select-sm">
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ (string) old('company_id', $cashRegister?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label">Sucursal <span class="text-danger">*</span></label>
                        <select name="branch_id" class="form-select form-select-sm" required>
                            <option value="">— Seleccionar sucursal —</option>
                            @foreach($branches ?? [] as $branch)
                                <option value="{{ $branch->id }}" {{ (string) old('branch_id', $cashRegister?->branch_id) === (string) $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nombre de la caja <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $cashRegister?->name) }}" required placeholder="Ej: Caja Principal">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Personal asignado</label>
                        <select name="assigned_personal_id" class="form-select form-select-sm @error('assigned_personal_id') is-invalid @enderror">
                            <option value="">— Sin asignar —</option>
                            @foreach($personals as $personal)
                                <option value="{{ $personal->id }}" {{ (string) old('assigned_personal_id', $cashRegister?->assigned_personal_id) === (string) $personal->id ? 'selected' : '' }}>
                                    {{ $personal->full_name }} {{ $personal->cargo ? '(' . $personal->cargo->name . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('assigned_personal_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Descripción opcional...">{{ old('description', $cashRegister?->description) }}</textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $cashRegister?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Caja activa</label>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <x-btn type="submit" icon="check-lg">Guardar caja</x-btn>
                    <x-btn variant="secondary" :href="route('cash-registers.index')">Cancelar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>
