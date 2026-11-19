@extends('layouts.app')

@section('title', 'Mi Perfil')

@section('page')
@php
    $breadcrumbs = ['Dashboard' => route('dashboard'), 'Mi Perfil' => '#'];
@endphp

<div class="container-fluid" style="max-width: 900px;">
    <x-page-header title="Mi perfil" icon="person-circle" subtitle="Gestiona tu información personal y credenciales de acceso." />

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

    <div class="row g-4">
        {{-- Información del usuario --}}
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body p-4">
                    <div class="mb-3">
                        <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center fs-xl"
                             style="width:80px;height:80px;color:#fff;">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                    </div>
                    <h5 class="fw-bold mb-1">{{ $user->name }}</h5>
                    <p class="text-muted small mb-1">{{ $user->email }}</p>
                    @if($user->phone)
                        <p class="text-muted small mb-1"><i class="bi bi-telephone me-1"></i>{{ $user->phone }}</p>
                    @endif
                    <div class="mt-3">
                        @if($user->is_super_admin)
                            <span class="badge bg-danger">Super Admin</span>
                        @else
                            <span class="badge bg-secondary">Usuario</span>
                        @endif
                        <span class="badge {{ $user->active ? 'bg-success' : 'bg-warning text-dark' }} ms-1">
                            {{ $user->active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                    <hr>
                    <div class="text-start">
                        <p class="text-muted small mb-1">
                            <i class="bi bi-calendar me-1"></i>
                            Registrado: {{ $user->created_at->format('d/m/Y') }}
                        </p>
                        @if($user->personal)
                            <p class="text-muted small mb-1">
                                <i class="bi bi-briefcase me-1"></i>
                                Cargo: {{ $user->personal->cargo?->name ?? '—' }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Formulario de edición --}}
        <div class="col-lg-8">
            <form action="{{ route('profile.update') }}" method="POST">
                @csrf
                @method('PUT')

                {{-- Datos personales --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-person"></i> Datos personales</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                                       value="{{ old('name', $user->name) }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-7">
                                <label class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror"
                                       value="{{ old('email', $user->email) }}" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="phone" class="form-control form-control-sm @error('phone') is-invalid @enderror"
                                       value="{{ old('phone', $user->phone) }}" placeholder="+1 234 567 8900">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Cambiar contraseña --}}
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold mb-1"><i class="bi bi-lock"></i> Cambiar contraseña</h6>
                        <p class="text-muted small mb-3">Deja los campos en blanco si no deseas cambiar tu contraseña.</p>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Contraseña actual</label>
                                <input type="password" name="current_password"
                                       class="form-control form-control-sm @error('current_password') is-invalid @enderror"
                                       autocomplete="current-password">
                                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nueva contraseña</label>
                                <input type="password" name="password"
                                       class="form-control form-control-sm @error('password') is-invalid @enderror"
                                       autocomplete="new-password">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Confirmar nueva contraseña</label>
                                <input type="password" name="password_confirmation"
                                       class="form-control form-control-sm" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <x-btn type="submit" icon="check-lg">Guardar cambios</x-btn>
                    <x-btn variant="secondary" :href="route('dashboard')">Cancelar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
