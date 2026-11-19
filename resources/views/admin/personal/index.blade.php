@extends('layouts.app')

@section('title', 'Personal')

@section('page')
<div class="container-fluid">
    <x-page-header title="Personal" icon="person-vcard" subtitle="Gestiona al personal y su acceso al sistema mediante cargos.">
        <x-btn icon="plus-lg" :href="route('personal.create')">Nuevo personal</x-btn>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Nombre</th>
                            <th>Cargo</th>
                            <th>Usuario</th>
                            <th>Empresa</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($personals as $personal)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $personal->full_name }}</div>
                                    <small class="text-muted">{{ $personal->id_number ?: 'Sin documento' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $personal->cargo?->name ?? '-' }}</span>
                                    <div><small class="text-muted">Rol: {{ $personal->cargo?->role?->name ?? '-' }}</small></div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $personal->user?->name ?? '-' }}</div>
                                    <small class="text-muted">{{ $personal->user?->email ?? '-' }}</small>
                                </td>
                                <td>{{ $personal->company?->name ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $personal->active ? 'bg-success' : 'bg-secondary' }}">{{ $personal->active ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <x-icon-btn icon="pencil" label="Editar" :href="route('personal.edit', $personal)" />
                                    <form action="{{ route('personal.destroy', $personal) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar registro de {{ addslashes($personal->full_name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">No hay personal registrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $personals->links() }}</div>
    </div>
</div>
@endsection
