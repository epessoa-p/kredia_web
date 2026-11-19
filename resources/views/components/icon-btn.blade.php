{{--
    Botón de SOLO ícono (acciones de fila de tabla, barra superior, cerrar…).
    Siempre lleva "label": se usa como tooltip y para lectores de pantalla.

    Uso:
      <x-icon-btn icon="pencil" label="Editar" :href="route('clients.edit', $client)" />
      <x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" />

    variant: ghost (por defecto) · secondary · danger · primary · success
--}}
@props([
    'icon',
    'label',
    'variant' => 'secondary',
    'size' => 'sm',
    'href' => null,
    'type' => 'button',
])

@php
    $variantClass = match ($variant) {
        'danger' => 'btn-outline-danger',
        'primary' => 'btn-outline-primary',
        'success' => 'btn-outline-success',
        'ghost' => 'btn-ghost',
        default => 'btn-secondary',
    };
    $classes = 'btn btn-icon-only ' . $variantClass . ($size === 'sm' ? ' btn-sm' : '');
@endphp

@if($href)
    <a href="{{ $href }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    </a>
@else
    <button type="{{ $type }}" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->merge(['class' => $classes]) }}>
        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
    </button>
@endif
