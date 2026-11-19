@extends('layouts.app')

@section('page')
<x-page-header title="Nuevo Usuario" icon="people" :back="route('users.index')" />

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ route('users.store') }}" method="POST">
            @csrf

            <div class="form-group mb-3">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" id="password" name="password" class="form-control form-control-sm @error('password') is-invalid @enderror" required>
            </div>

            <div class="form-group mb-3">
                <label for="password_confirmation" class="form-label">Confirmar Contraseña</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control form-control-sm" required>
            </div>

            <div class="form-group mb-3">
                <label for="phone" class="form-label">Teléfono</label>
                <input type="text" id="phone" name="phone" class="form-control form-control-sm @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
            </div>

            @if(auth()->user()->is_super_admin)
                <div class="form-group mb-3">
                    <label for="company_id" class="form-label">Empresa</label>
                    <select id="company_id" name="company_id" class="form-select form-select-sm @error('company_id') is-invalid @enderror">
                        <option value="">— Sin empresa —</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id', $currentCompanyId) == $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="form-group mb-3">
                <label for="role_id" class="form-label">Rol en la empresa</label>
                <select id="role_id" name="role_id" class="form-select form-select-sm @error('role_id') is-invalid @enderror" @unless(auth()->user()->is_super_admin) required @endunless>
                    <option value="">— Selecciona —</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
                @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if(auth()->user()->is_super_admin)
                <div class="form-check mb-3">
                    <input type="hidden" name="is_super_admin" value="0">
                    <input type="checkbox" id="is_super_admin" name="is_super_admin" value="1" class="form-check-input" @checked(old('is_super_admin'))>
                    <label for="is_super_admin" class="form-check-label">Superadministrador de la plataforma</label>
                </div>
            @endif

            @error('error')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

            <div class="form-group">
                <x-btn type="submit" icon="check-lg">Crear Usuario</x-btn>
                <x-btn variant="secondary" :href="route('users.index')">Cancelar</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
