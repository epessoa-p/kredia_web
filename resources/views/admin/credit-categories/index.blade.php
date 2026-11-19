@extends('layouts.app')

@section('title', 'Categorías de Crédito')

@section('page')
<div class="container-fluid">
    <x-page-header title="Categorías de crédito" icon="tags" subtitle="Configura categorías como Vehículos, Joyas, Artículos y Garrafas con reglas flexibles.">
        <x-btn icon="plus-lg" :href="route('credit-categories.create')">Nueva categoría</x-btn>
    </x-page-header>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Color</th>
                            <th>Empresa</th>
                            <th class="text-end">Reglas</th>
                            <th>Monto Min/Max</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            @php
                                $chipColor = $category->color ?: '#64748B';
                                $chipTextClass = in_array(strtolower($chipColor), ['#d9b646', '#e68a2e', '#f4c542', '#f59e0b'], true) ? 'text-dark' : 'text-white';
                            @endphp
                            <tr>
                                <td><i class="bi {{ $category->icon_class }} me-1" style="color: {{ $chipColor }}"></i><strong>{{ $category->name }}</strong><br><small class="text-muted">{{ $category->slug }}</small></td>
                                <td>
                                    <span class="badge {{ $chipTextClass }}" style="background-color: {{ $chipColor }};">{{ $chipColor }}</span>
                                </td>
                                <td>{{ $category->company?->name ?: '-' }}</td>
                                <td class="text-end">{{ $category->rules->count() }}</td>
                                <td>{{ $currencySymbol }} {{ number_format((float) ($category->min_amount ?? 0), 2) }} / {{ $currencySymbol }} {{ number_format((float) ($category->max_amount ?? 0), 2) }}</td>
                                <td><span class="badge {{ $category->active ? 'bg-success' : 'bg-secondary' }}">{{ $category->active ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="text-end">
                                    <x-icon-btn icon="eye" label="Ver" :href="route('credit-categories.show', $category)" />
                                    <x-icon-btn icon="pencil" label="Editar" :href="route('credit-categories.edit', $category)" />
                                    <form action="{{ route('credit-categories.destroy', $category) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" onclick="return confirm('¿Eliminar categoría?')" />
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">No hay categorías registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">{{ $categories->links() }}</div>
</div>
@endsection
