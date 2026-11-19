@extends('layouts.base')

@section('title', 'Acceso Denegado')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="text-center px-3">
        <h1 class="display-1 text-danger mb-2"><i class="bi bi-shield-exclamation"></i></h1>
        <h2 class="mb-2">Acceso Denegado</h2>
        <p class="text-muted mb-4">{{ $message ?? (isset($exception) && $exception->getMessage() ? $exception->getMessage() : 'No tienes permisos para acceder a esta sección.') }}</p>
        @auth
            <x-btn icon="arrow-left" :href="route('dashboard')">Volver al Dashboard</x-btn>
        @else
            <x-btn icon="box-arrow-in-right" :href="route('login')">Iniciar sesión</x-btn>
        @endauth
    </div>
</div>
@endsection
