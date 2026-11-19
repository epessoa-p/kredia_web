@extends('layouts.app')

@section('page')
<x-page-header title="Roles" icon="shield-lock">
    <x-btn icon="plus-lg" :href="route('roles.create')">Nuevo rol</x-btn>
</x-page-header>

<div class="card">
    <div class="card-body">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Slug</th>
                    <th>Descripción</th>
                    <th>Permisos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td><strong>{{ $role->name }}</strong></td>
                        <td><code>{{ $role->slug }}</code></td>
                        <td>{{ $role->description ?? '-' }}</td>
                        <td>{{ $role->permissions->count() }} permisos</td>
                        <td>
                            <x-icon-btn icon="pencil" label="Editar" :href="route('roles.edit', $role)" />
                            <form action="{{ route('roles.destroy', $role) }}" method="POST" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" onclick="return confirm('¿Estás seguro?')" />
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="d-flex justify-content-center">
            {{ $roles->links() }}
        </div>
    </div>
</div>
@endsection
