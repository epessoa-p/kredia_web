@extends('layouts.app')

@section('title', 'Sucursales')

@section('page')
<div class="container-fluid">
    <x-page-header title="Sucursales" icon="diagram-2" subtitle="Administra las sucursales disponibles por empresa.">
        <x-btn icon="plus-lg" :href="route('branches.create')">Nueva sucursal</x-btn>
    </x-page-header>
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Color</th>
                            <th>Código</th>
                            <th>Empresa</th>
                            <th>Teléfono</th>
                            <th>Encargado</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($branches as $branch)
                            <tr>
                                <td>
                                    <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:{{ $branch->color ?? '#0d6efd' }};margin-right:6px;vertical-align:middle;flex-shrink:0;"></span>
                                    {{ $branch->name }}
                                </td>
                                <td>
                                    @php $bc = $branch->color ?? '#0d6efd'; @endphp
                                    <span style="display:inline-flex;align-items:center;gap:6px;">
                                        <span style="width:22px;height:22px;border-radius:5px;background:{{ $bc }};display:inline-block;border:1px solid rgba(0,0,0,.1);flex-shrink:0;"></span>
                                        <code class="fs-xs text-muted">{{ $bc }}</code>
                                    </span>
                                </td>
                                <td>{{ $branch->code ?: '-' }}</td>
                                <td>{{ $branch->company?->name ?: '-' }}</td>
                                <td>{{ $branch->phone ?: '-' }}</td>
                                <td>{{ $branch->manager_name ?: '-' }}</td>
                                <td><span class="badge {{ $branch->active ? 'bg-success' : 'bg-secondary' }}">{{ $branch->active ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="text-end">
                                    <x-icon-btn icon="eye" label="Ver" :href="route('branches.show', $branch)" />
                                    <x-icon-btn icon="pencil" label="Editar" :href="route('branches.edit', $branch)" />
                                    <form action="{{ route('branches.destroy', $branch) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" onclick="return confirm('¿Eliminar sucursal?')" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-5 text-muted">No hay sucursales registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-4 d-flex justify-content-center">{{ $branches->links() }}</div>
</div>
@endsection