@extends('layouts.app')

@section('page')
<x-page-header title="Editar Usuario" icon="people" :back="route('users.index')" />

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ route('users.update', $user) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-group mb-3">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" disabled>
            </div>

            <div class="form-group mb-3">
                <label for="phone" class="form-label">Teléfono</label>
                <input type="text" id="phone" name="phone" class="form-control form-control-sm @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}">
            </div>

            <div class="form-group">
                <x-btn type="submit" icon="check-lg">Actualizar</x-btn>
                <x-btn variant="secondary" :href="route('users.index')">Cancelar</x-btn>
            </div>
        </form>
    </div>
</div>
@endsection
