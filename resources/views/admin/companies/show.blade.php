@extends('layouts.app')

@section('title', 'Detalle Empresa')

@push('styles')
<style>
    .company-hero {
        border-radius: 14px;
        padding: 22px;
        background: var(--mod-color);
        color: #fff;
        position: relative;
        overflow: hidden;
    }

    .company-hero.has-cover::before {
        content: '';
        position: absolute;
        inset: 0;
        background: var(--mod-color);
        opacity: .85;
    }

    .company-hero > * {
        position: relative;
        z-index: 1;
    }

    .company-logo-large {
        width: 96px;
        height: 96px;
        border-radius: 16px;
        object-fit: cover;
        border: 2px solid rgba(255, 255, 255, .6);
        background: rgba(255, 255, 255, .15);
    }

    .company-card {
        border: 0;
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .08);
    }

    .info-item {
        border: 1px solid #e7edf6;
        border-radius: 10px;
        padding: 10px 12px;
        background: #fbfdff;
        margin-bottom: 10px;
    }

    .info-item .label {
        font-size: var(--fs-xs);
        color: #64748b;
        text-transform: uppercase;
    }

    .info-item .value {
        font-weight: 600;
        color: #0f172a;
    }

    .cover-preview {
        width: 100%;
        max-height: 190px;
        object-fit: cover;
        border-radius: 12px;
        border: 1px solid #d7deea;
    }
</style>
@endpush

@section('page')
@php
    $coverUrl = $company->cover_image ? asset('storage/' . $company->cover_image) : null;
@endphp

<div class="company-hero {{ $coverUrl ? 'has-cover' : '' }} mb-4" @if($coverUrl) style="background-image: url('{{ $coverUrl }}'); background-size: cover; background-position: center;" @endif>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            @if($company->logo)
                <img src="{{ asset('storage/' . $company->logo) }}" alt="Logo {{ $company->name }}" class="company-logo-large">
            @else
                <div class="company-logo-large d-grid place-items-center">
                    <i class="bi bi-building fs-2"></i>
                </div>
            @endif
            <div>
                <h1 class="fs-lg mb-1"><i class="bi bi-building me-1"></i> {{ $company->name }}</h1>
                <div class="fs-sm opacity-75">Gestion visual y administrativa de la empresa</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <x-btn variant="secondary" icon="pencil" :href="route('companies.edit', $company)">Editar</x-btn>
            <x-btn variant="secondary" icon="arrow-left" :href="route('companies.index')">Volver</x-btn>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card company-card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información</h5>
            </div>
            <div class="card-body">
                @if($company->cover_image)
                    <div class="mb-3 text-center text-md-start">
                        <img src="{{ asset('storage/' . $company->cover_image) }}" alt="Portada {{ $company->name }}" class="cover-preview">
                    </div>
                @endif

                <div class="info-item">
                    <div class="label">RUC</div>
                    <div class="value">{{ $company->ruc ?? '-' }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Email</div>
                    <div class="value">{{ $company->email ?? '-' }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Telefono</div>
                    <div class="value">{{ $company->phone ?? '-' }}</div>
                </div>
                <div class="info-item">
                    <div class="label">Direccion</div>
                    <div class="value">{{ $company->address ?? '-' }}</div>
                </div>
                <div class="info-item mb-0">
                    <div class="label">Estado</div>
                    <div class="value">
                        <span class="badge {{ $company->active ? 'bg-success' : 'bg-danger' }}">{{ $company->active ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        @php $sub = $company->subscription; @endphp
        <div class="card company-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Plan y suscripción</h5>
                <x-btn icon="pencil" :href="route('companies.subscription.edit', $company)">Gestionar</x-btn>
            </div>
            <div class="card-body small">
                @if($sub)
                    <p class="mb-1"><strong>Plan:</strong> {{ $sub->plan?->name }}</p>
                    <p class="mb-1"><strong>Estado:</strong>
                        {{ ['trial' => 'En prueba', 'active' => 'Vigente', 'grace' => 'En gracia (solo lectura)', 'expired' => 'Vencida', 'suspended' => 'Suspendida', 'cancelled' => 'Cancelada'][$sub->effectiveStatus()] ?? $sub->effectiveStatus() }}
                        @if($sub->periodEndsAt()) · vence {{ $sub->periodEndsAt()->format('d/m/Y') }} @else · sin vencimiento @endif
                    </p>
                    <p class="mb-2"><strong>Módulos:</strong>
                        @foreach($sub->effectiveFeatures() as $f)
                            <span class="badge text-bg-light border">{{ \App\Models\Plan::MODULES[$f] ?? $f }}</span>
                        @endforeach
                        @if($sub->features_override !== null)<span class="text-muted">(personalizados)</span>@endif
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach(['users' => 'Usuarios', 'branches' => 'Sucursales', 'products' => 'Productos', 'loans' => 'Préstamos'] as $key => $label)
                            <span>{{ $label }}: <strong>{{ $usage[$key]['usage'] }}</strong> / {{ $usage[$key]['max'] ?? '∞' }}</span>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-warning py-2 mb-0">Sin suscripción: la empresa no puede ingresar. Asígnale un plan.</div>
                @endif
            </div>
        </div>

        <div class="card company-card">
            <div class="card-header">
                <h5 class="mb-0">Acciones</h5>
            </div>
            <div class="card-body">
                <x-btn variant="secondary" icon="pencil" :block="true" class="mb-2" :href="route('companies.edit', $company)">Editar</x-btn>
                <form action="{{ route('companies.destroy', $company) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <x-btn variant="danger" type="submit" icon="trash" :block="true" onclick="return confirm('¿Estás seguro?')">Eliminar</x-btn>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card company-card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Usuarios de la Empresa</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->pivot->role_id ? \App\Models\Role::find($user->pivot->role_id)->name : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted">No hay usuarios en esta empresa</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
