@extends('layouts.app')

@section('page')
<x-page-header title="Usuarios" icon="people">
    <x-btn icon="plus-lg" :href="route('users.create')">Nuevo usuario</x-btn>
</x-page-header>

<div class="card">
    <div class="card-body">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td><strong>{{ $user->name }}</strong></td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->phone ?? '-' }}</td>
                        <td>
                            @if($user->is_super_admin)
                                <span class="badge bg-danger">Super Admin</span>
                            @else
                                <span class="badge bg-primary">Usuario</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $user->active ? 'bg-success' : 'bg-danger' }}">
                                {{ $user->active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td>
                            <x-icon-btn icon="pencil" label="Editar" :href="route('users.edit', $user)" />
                            @if(auth()->user()->id !== $user->id)
                                <form action="{{ route('users.destroy', $user) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" onclick="return confirm('¿Estás seguro?')" />
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="d-flex justify-content-center">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
