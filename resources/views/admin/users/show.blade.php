@extends('layouts.app')

@section('page')
<x-page-header :title="$user->name" icon="person" :back="route('users.index')" />

<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Información del Usuario</h5>
    </div>
    <div class="card-body">
        <p><strong>Email:</strong> {{ $user->email }}</p>
        <p><strong>Teléfono:</strong> {{ $user->phone ?? '-' }}</p>
        <p><strong>Super Admin:</strong> <span class="badge {{ $user->is_super_admin ? 'bg-danger' : 'bg-secondary' }}">{{ $user->is_super_admin ? 'Sí' : 'No' }}</span></p>
        <p><strong>Estado:</strong> <span class="badge {{ $user->active ? 'bg-success' : 'bg-danger' }}">{{ $user->active ? 'Activo' : 'Inactivo' }}</span></p>
    </div>
</div>

@endsection
