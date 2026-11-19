@extends('layouts.app')

@section('title', 'Cargos')

@section('page')
<div class="container-fluid">
    <x-page-header title="Cargos" icon="briefcase" subtitle="Define cargos y vincúlalos a roles para heredar permisos.">
        <x-btn icon="plus-lg" :href="route('cargos.create')">Nuevo cargo</x-btn>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Cargo</th>
                            <th>Rol asociado</th>
                            <th>Empresa</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cargos as $cargo)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $cargo->name }}</div>
                                    <small class="text-muted">{{ $cargo->description ?: 'Sin descripción' }}</small>
                                </td>
                                <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $cargo->role?->name ?? '-' }}</span></td>
                                <td>{{ $cargo->company?->name ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $cargo->active ? 'bg-success' : 'bg-secondary' }}">{{ $cargo->active ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <x-icon-btn icon="pencil" label="Editar" :href="route('cargos.edit', $cargo)" />
                                    <form action="{{ route('cargos.destroy', $cargo) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar cargo {{ addslashes($cargo->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5 text-muted">No hay cargos registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $cargos->links() }}</div>
    </div>
</div>
@endsection
