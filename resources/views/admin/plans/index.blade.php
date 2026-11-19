@extends('layouts.app')

@section('title', 'Planes')

@section('page')
<x-page-header title="Planes" icon="stack" :count="$plans->count()">
    <x-btn icon="plus-lg" :href="route('plans.create')">Nuevo plan</x-btn>
</x-page-header>

<div class="card">
    <div class="table-responsive">
        <table class="table table-list align-middle mb-0">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Precio</th>
                    <th>Usuarios</th>
                    <th>Sucursales</th>
                    <th>Productos</th>
                    <th>Préstamos</th>
                    <th>Módulos</th>
                    <th>Empresas</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $plan->name }}</div>
                            <div class="text-muted small">{{ $plan->description }}</div>
                        </td>
                        <td>{{ number_format((float) $plan->price, 2) }} / {{ $plan->billing_period === 'yearly' ? 'año' : 'mes' }}</td>
                        @foreach(['max_users', 'max_branches', 'max_products', 'max_loans'] as $col)
                            <td>{{ $plan->{$col} ?? '∞' }}</td>
                        @endforeach
                        <td>
                            @foreach($plan->features ?? [] as $feature)
                                <span class="badge text-bg-light border">{{ \App\Models\Plan::MODULES[$feature] ?? $feature }}</span>
                            @endforeach
                        </td>
                        <td>{{ $plan->subscriptions_count }}</td>
                        <td>
                            <span class="badge {{ $plan->active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $plan->active ? 'Activo' : 'Inactivo' }}</span>
                        </td>
                        <td class="text-end text-nowrap">
                            <x-icon-btn icon="pencil" label="Editar" :href="route('plans.edit', $plan)" />
                            @if($plan->subscriptions_count === 0)
                                <form action="{{ route('plans.destroy', $plan) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar el plan {{ $plan->name }}?')">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" />
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">No hay planes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
