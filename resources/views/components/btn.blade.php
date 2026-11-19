{{--
    Botón estándar de Kredia. ÚNICO diseño de botón con texto en todo el sistema.

    Uso:
      <x-btn icon="plus-lg" :href="route('clients.create')">Nuevo cliente</x-btn>
      <x-btn type="submit" icon="check-lg">Guardar</x-btn>
      <x-btn variant="secondary" :href="route('clients.index')" icon="arrow-left">Volver</x-btn>
      <x-btn variant="danger" type="submit" icon="trash">Eliminar</x-btn>

    variant: primary (guardar/crear) · secondary (volver/cancelar/filtros) · danger (eliminar)
             success (cobrar/confirmar) · ghost (acciones discretas) · module (color del módulo)
             outline-primary · outline-danger · outline-success · warning · info
    size:    sm (por defecto) · md
    href:    si se indica, se renderiza <a>; si no, <button type="...">
--}}
@props([
    'variant' => 'primary',
    'size' => 'sm',
    'icon' => null,
    'iconRight' => null,
    'href' => null,
    'type' => 'button',
    'block' => false,
])

@php
    $variantClass = match ($variant) {
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'danger' => 'btn-danger',
        'success' => 'btn-success',
        'ghost' => 'btn-ghost',
        'module' => 'btn-mod',
        'warning' => 'btn-warning',
        'info' => 'btn-info',
        'outline-primary' => 'btn-outline-primary',
        'outline-danger' => 'btn-outline-danger',
        'outline-success' => 'btn-outline-success',
        default => 'btn-' . $variant,
    };
    $classes = trim('btn ' . $variantClass . ($size === 'sm' ? ' btn-sm' : '') . ($block ? ' w-100' : ''));
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<i class="bi bi-{{ $icon }}" aria-hidden="true"></i>@endif
        <span>{{ $slot }}</span>
        @if($iconRight)<i class="bi bi-{{ $iconRight }}" aria-hidden="true"></i>@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<i class="bi bi-{{ $icon }}" aria-hidden="true"></i>@endif
        <span>{{ $slot }}</span>
        @if($iconRight)<i class="bi bi-{{ $iconRight }}" aria-hidden="true"></i>@endif
    </button>
@endif
