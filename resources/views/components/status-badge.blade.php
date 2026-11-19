{{--
    Estado con color fijo por significado (el mismo estado se ve igual en todo el sistema).

    Uso:
      <x-status-badge :status="$loan->status" />                 (estados de préstamo)
      <x-status-badge tone="warning">Pendiente</x-status-badge>   (libre)

    tone: success · danger · warning · info · muted · primary
--}}
@props([
    'status' => null,
    'tone' => null,
])

@php
    $map = [
        // Préstamos
        'ACTIVO' => ['success', 'Activo'],
        'EN_MORA' => ['warning', 'En mora'],
        'INCUMPLIDO' => ['danger', 'Incumplido'],
        'FINALIZADO' => ['muted', 'Finalizado'],
        'CANCELADO' => ['muted', 'Cancelado'],
        // Ventas / genéricos
        'PAGADO' => ['success', 'Pagado'],
        'PENDIENTE' => ['warning', 'Pendiente'],
        'PARCIAL' => ['info', 'Parcial'],
        'VENCIDO' => ['danger', 'Vencido'],
        'DEVUELTO' => ['muted', 'Devuelto'],
        // Suscripción (estado efectivo)
        'trial' => ['info', 'En prueba'],
        'active' => ['success', 'Vigente'],
        'grace' => ['warning', 'En gracia'],
        'expired' => ['danger', 'Vencida'],
        'suspended' => ['danger', 'Suspendida'],
        'cancelled' => ['muted', 'Cancelada'],
    ];
    [$resolvedTone, $label] = $map[$status] ?? [$tone ?? 'muted', $status];
    $resolvedTone = $tone ?? $resolvedTone;
@endphp

<span {{ $attributes->merge(['class' => 'status-badge status-' . $resolvedTone]) }}>{{ trim($slot) !== '' ? $slot : $label }}</span>
