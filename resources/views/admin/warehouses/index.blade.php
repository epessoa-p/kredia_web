@extends('layouts.app')

@section('title', 'Almacenes')

@section('page')
<div class="container-fluid">
    <x-page-header title="Almacenes" icon="building-add" subtitle="Controla ubicaciones físicas y movimientos de inventario.">@include('components.export-dropdown', ['baseUrl' => route('exports.warehouses'), 'params' => request()->all()])<x-btn icon="plus-lg" :href="route('warehouses.create')">Nuevo almacén</x-btn></x-page-header>
    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light"><tr><th>Almacén</th><th>Código</th><th>Sucursal principal</th><th>Empresa</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>@forelse($warehouses as $warehouse)<tr><td>{{ $warehouse->name }}</td><td>{{ $warehouse->code }}</td><td>{{ $warehouse->primaryBranch?->name ?: 'No asignada' }}</td><td>{{ $warehouse->company?->name ?: '-' }}</td><td><span class="badge {{ $warehouse->active ? 'bg-success' : 'bg-secondary' }}">{{ $warehouse->active ? 'Activo' : 'Inactivo' }}</span></td><td class="text-end"><x-icon-btn icon="eye" label="Ver" :href="route('warehouses.show', $warehouse)" /><x-icon-btn icon="pencil" label="Editar" :href="route('warehouses.edit', $warehouse)" /><form action="{{ route('warehouses.destroy', $warehouse) }}" method="POST" class="d-inline">@csrf @method('DELETE')<x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" onclick="return confirm('¿Eliminar almacén?')" /></form></td></tr>@empty<tr><td colspan="6" class="text-center py-5 text-muted">No hay almacenes registrados.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="mt-4 d-flex justify-content-center">{{ $warehouses->links() }}</div>
</div>
@endsection