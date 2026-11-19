@extends('layouts.auth')

@section('title', 'Login - Sistema de Préstamos')

@push('styles')
<style>
    .auth-logo-img {
        max-width: 280px;
        max-height: 200px;
        width: 100%;
        object-fit: contain;
        margin: 0 auto;
        display: block;
    }
</style>
@endpush

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <img src="{{ asset('images/login-logo.jpeg') }}" alt="Logo" class="auth-logo-img">
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form action="{{ route('login.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="login" class="form-label">Usuario o Email</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" id="login" name="login" class="form-control form-control-sm @error('login') is-invalid @enderror"
                           placeholder="usuario o correo@ejemplo.com" value="{{ old('login') }}" required>
                </div>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="password" name="password" class="form-control form-control-sm @error('password') is-invalid @enderror"
                           placeholder="••••••••" required>
                </div>
            </div>

            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember" value="1">
                <label for="remember" style="margin: 0; cursor: pointer;">Recuerda mi información</label>
            </div>

            <x-btn type="submit" size="md" icon="box-arrow-in-right" :block="true">Iniciar sesión</x-btn>
        </form>

    </div>
</div>
@endsection
