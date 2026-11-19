{{--
    Encabezado estándar de página: ícono en el color del módulo + título + contador + acciones.
    El color sale del módulo de la ruta actual (config/menu.php); se puede forzar con module="loans".

    Uso:
      <x-page-header title="Clientes" icon="people" :count="$clients->total()">
          <x-btn icon="plus-lg" :href="route('clients.create')">Nuevo cliente</x-btn>
      </x-page-header>

      <x-page-header title="Préstamo PR-00012" icon="cash-coin" subtitle="Cliente: Juan Pérez" :back="route('loans.index')" />
--}}
@props([
    'title',
    'icon' => null,
    'count' => null,
    'subtitle' => null,
    'module' => null,
    'back' => null,
])

<div {{ $attributes->merge(['class' => 'page-head' . ($module ? ' mod-' . $module : '')]) }}>
    <div class="page-head-title">
        <h1 class="page-title">
            @if($icon)
                <span class="page-title-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
            @endif
            <span>
                {{ $title }}
                @isset($subtitle)
                    <span class="page-subtitle d-block">{{ $subtitle }}</span>
                @endisset
            </span>
        </h1>
        @if($count !== null)
            <span class="page-count">{{ $count }}</span>
        @endif
        @isset($meta)
            {{ $meta }}
        @endisset
    </div>

    @if($back || trim($slot) !== '')
        <div class="page-head-actions">
            {{ $slot }}
            @if($back)
                <x-btn variant="secondary" icon="arrow-left" :href="$back">Volver</x-btn>
            @endif
        </div>
    @endif
</div>
