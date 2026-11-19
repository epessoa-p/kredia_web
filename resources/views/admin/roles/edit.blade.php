@extends('layouts.app')

@section('page')
<x-page-header title="Editar Rol" icon="shield-lock" :back="route('roles.index')" />

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ route('roles.update', $role) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group mb-3">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror" value="{{ old('name', $role->name) }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="slug" class="form-label">Slug</label>
                <input type="text" id="slug" name="slug" class="form-control form-control-sm @error('slug') is-invalid @enderror" value="{{ old('slug', $role->slug) }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="description" class="form-label">Descripción</label>
                <textarea id="description" name="description" class="form-control form-control-sm @error('description') is-invalid @enderror" rows="2">{{ old('description', $role->description) }}</textarea>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Permisos</label>
                <div class="form-check-group" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                    @foreach($permissions as $module => $modulePerms)
                        <div class="mb-3">
                            <h6 class="text-capitalize">{{ $module }}</h6>
                            @foreach($modulePerms as $permission)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm_{{ $permission->id }}" {{ $role->permissions->contains($permission->id) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="perm_{{ $permission->id }}">
                                        {{ $permission->name }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="form-group">
                <x-btn type="submit" icon="check-lg">Actualizar</x-btn>
                <x-btn variant="secondary" :href="route('roles.index')">Cancelar</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
