@extends('layouts.app')

@section('title', $plan->exists ? 'Editar plan' : 'Nuevo plan')

@section('page')
<x-page-header :title="$plan->exists ? 'Editar plan: ' . $plan->name : 'Nuevo plan'" icon="stack" :back="route('plans.index')" />

@if($errors->any())
    <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

<form action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}" method="POST" class="card">
    @csrf
    @if($plan->exists) @method('PUT') @endif

    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="name">Nombre</label>
                <input type="text" id="name" name="name" class="form-control form-control-sm" value="{{ old('name', $plan->name) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="slug">Identificador</label>
                <input type="text" id="slug" name="slug" class="form-control form-control-sm" value="{{ old('slug', $plan->slug) }}" placeholder="automático">
            </div>
            <div class="col-md-5">
                <label class="form-label" for="description">Descripción</label>
                <input type="text" id="description" name="description" class="form-control form-control-sm" value="{{ old('description', $plan->description) }}">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="price">Precio</label>
                <input type="number" step="0.01" min="0" id="price" name="price" class="form-control form-control-sm" value="{{ old('price', $plan->price ?? 0) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="billing_period">Periodo</label>
                <select id="billing_period" name="billing_period" class="form-select form-select-sm">
                    <option value="monthly" @selected(old('billing_period', $plan->billing_period) === 'monthly')>Mensual</option>
                    <option value="yearly" @selected(old('billing_period', $plan->billing_period) === 'yearly')>Anual</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="trial_days">Días de prueba</label>
                <input type="number" min="0" id="trial_days" name="trial_days" class="form-control form-control-sm" value="{{ old('trial_days', $plan->trial_days ?? 0) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="sort_order">Orden</label>
                <input type="number" min="0" id="sort_order" name="sort_order" class="form-control form-control-sm" value="{{ old('sort_order', $plan->sort_order ?? 0) }}">
            </div>

            <div class="col-12"><h6 class="mb-0 mt-2">Límites <span class="text-muted small fw-normal">(vacío = ilimitado)</span></h6></div>
            @foreach(['max_users' => 'Usuarios', 'max_branches' => 'Sucursales', 'max_products' => 'Productos', 'max_loans' => 'Préstamos vigentes'] as $col => $label)
                <div class="col-md-3">
                    <label class="form-label" for="{{ $col }}">{{ $label }}</label>
                    <input type="number" min="0" id="{{ $col }}" name="{{ $col }}" class="form-control form-control-sm" value="{{ old($col, $plan->{$col}) }}" placeholder="∞">
                </div>
            @endforeach

            <div class="col-12"><h6 class="mb-0 mt-2">Módulos incluidos</h6></div>
            @php $selected = old('features', $plan->features ?? []); @endphp
            @foreach(\App\Models\Plan::MODULES as $key => $label)
                <div class="col-md-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="feature_{{ $key }}" name="features[]" value="{{ $key }}" @checked(in_array($key, $selected, true))>
                        <label class="form-check-label" for="feature_{{ $key }}">{{ $label }}</label>
                    </div>
                </div>
            @endforeach

            <div class="col-12">
                <div class="form-check form-switch mt-2">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" class="form-check-input" id="active" name="active" value="1" @checked(old('active', $plan->active))>
                    <label class="form-check-label" for="active">Plan activo (disponible para nuevas empresas)</label>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-end gap-2">
        <x-btn variant="secondary" :href="route('plans.index')">Cancelar</x-btn>
        <x-btn type="submit" icon="check-lg">Guardar</x-btn>
    </div>
</form>
@endsection
