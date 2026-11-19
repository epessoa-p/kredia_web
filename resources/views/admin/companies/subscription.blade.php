@extends('layouts.app')

@section('title', 'Suscripción · ' . $company->name)

@section('page')
@php
    $plan = $subscription->plan;
    $customFeatures = old('customize_features', $subscription->features_override !== null);
    $selectedFeatures = old('features_override', $subscription->features_override ?? ($plan?->features ?? []));
    $limitLabels = ['users' => 'Usuarios', 'branches' => 'Sucursales', 'products' => 'Productos', 'loans' => 'Préstamos vigentes'];
@endphp

<x-page-header :title="'Suscripción · ' . $company->name" icon="credit-card" :back="route('companies.show', $company)" />

@if($errors->any())
    <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

@if($subscription->exists)
    <div class="alert alert-light border small">
        Estado efectivo: <strong>{{ ['trial' => 'En prueba', 'active' => 'Vigente', 'grace' => 'En gracia (solo lectura)', 'expired' => 'Vencida', 'suspended' => 'Suspendida', 'cancelled' => 'Cancelada'][$subscription->effectiveStatus()] ?? $subscription->effectiveStatus() }}</strong>
        @if($subscription->periodEndsAt())
            · Fin del periodo: {{ $subscription->periodEndsAt()->format('d/m/Y') }}
            · Gracia hasta: {{ $subscription->graceEndsAt()?->format('d/m/Y') }}
        @else
            · Sin vencimiento
        @endif
    </div>
@endif

<form action="{{ route('companies.subscription.update', $company) }}" method="POST" class="card">
    @csrf @method('PUT')
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="plan_id">Plan</label>
                <select id="plan_id" name="plan_id" class="form-select form-select-sm" required>
                    @foreach($plans as $p)
                        <option value="{{ $p->id }}" @selected(old('plan_id', $subscription->plan_id) == $p->id)>{{ $p->name }}{{ $p->active ? '' : ' (inactivo)' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Estado</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    @foreach(\App\Models\Subscription::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $subscription->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="grace_days">Días de gracia (solo lectura)</label>
                <input type="number" min="0" max="60" id="grace_days" name="grace_days" class="form-control form-control-sm" value="{{ old('grace_days', $subscription->grace_days ?? 3) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="trial_ends_at">Fin de la prueba</label>
                <input type="date" id="trial_ends_at" name="trial_ends_at" class="form-control form-control-sm" value="{{ old('trial_ends_at', $subscription->trial_ends_at?->toDateString()) }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="current_period_end">Fin del periodo pagado</label>
                <input type="date" id="current_period_end" name="current_period_end" class="form-control form-control-sm" value="{{ old('current_period_end', $subscription->current_period_end?->toDateString()) }}">
                <div class="form-text">Vacío con estado "Activa" = sin vencimiento.</div>
            </div>

            <div class="col-12"><h6 class="mb-0 mt-2">Límites de esta empresa <span class="text-muted small fw-normal">(vacío = usar el del plan)</span></h6></div>
            @foreach($limitLabels as $key => $label)
                <div class="col-md-3">
                    <label class="form-label" for="max_{{ $key }}_override">{{ $label }}</label>
                    <input type="number" min="0" id="max_{{ $key }}_override" name="max_{{ $key }}_override" class="form-control form-control-sm"
                           value="{{ old("max_{$key}_override", $subscription->{"max_{$key}_override"}) }}"
                           placeholder="Plan: {{ $plan?->limitFor($key) ?? '∞' }}">
                    <div class="form-text">En uso: {{ $usage[$key] ?? 0 }}</div>
                </div>
            @endforeach

            <div class="col-12">
                <div class="form-check form-switch mt-2">
                    <input type="hidden" name="customize_features" value="0">
                    <input type="checkbox" class="form-check-input" id="customize_features" name="customize_features" value="1" @checked($customFeatures)
                           onchange="document.getElementById('features-box').classList.toggle('d-none', !this.checked)">
                    <label class="form-check-label" for="customize_features">Personalizar módulos para esta empresa (reemplaza los del plan)</label>
                </div>
            </div>
            <div id="features-box" class="col-12 {{ $customFeatures ? '' : 'd-none' }}">
                <div class="row g-2">
                    @foreach(\App\Models\Plan::MODULES as $key => $label)
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="fo_{{ $key }}" name="features_override[]" value="{{ $key }}" @checked(in_array($key, $selectedFeatures, true))>
                                <label class="form-check-label" for="fo_{{ $key }}">{{ $label }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="col-12">
                <label class="form-label" for="notes">Notas internas</label>
                <textarea id="notes" name="notes" rows="2" class="form-control form-control-sm">{{ old('notes', $subscription->notes) }}</textarea>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2">
        <x-btn variant="secondary" :href="route('companies.show', $company)">Cancelar</x-btn>
        <x-btn type="submit" icon="check-lg">Guardar suscripción</x-btn>
    </div>
</form>
@endsection
