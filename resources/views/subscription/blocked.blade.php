@extends('layouts.auth')

@section('title', 'Suscripción no vigente')

@section('content')
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <div class="logo"><i class="bi bi-lock"></i></div>
            <h4>Acceso suspendido</h4>
            <p>{{ $company?->name }}</p>
        </div>

        <div class="alert alert-warning small mb-3">
            @if(!$subscription)
                Esta empresa todavía no tiene un plan asignado.
            @elseif($subscription->isBlocked())
                La suscripción de la empresa está <strong>{{ \App\Models\Subscription::STATUSES[$subscription->status] ?? $subscription->status }}</strong>.
            @else
                La suscripción de la empresa venció
                @if($subscription->periodEndsAt())
                    el <strong>{{ $subscription->periodEndsAt()->format('d/m/Y') }}</strong>
                @endif
                y terminó el periodo de gracia.
            @endif
            Comunícate con tu proveedor del sistema para reactivarla. Tus datos se conservan.
        </div>

        <div class="d-grid gap-2">
            @if($hasOtherCompanies)
                <x-btn variant="secondary" icon="building" :href="route('select-company')">Cambiar de empresa</x-btn>
            @endif
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <x-btn type="submit" icon="box-arrow-right" :block="true">Cerrar sesión</x-btn>
            </form>
        </div>
    </div>
</div>
@endsection
