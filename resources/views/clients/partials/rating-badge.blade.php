{{--
    Categoría del cliente (automática) + etiqueta manual destacada.
    Parámetros: $rating (fiel|puntual|regular|riesgo|nuevo), $tag (texto o null), $tagColor (opcional), $size ('sm' | 'lg').
--}}
@php
    $meta = \App\Services\ClientRating::meta($rating ?? null);
    $isLarge = ($size ?? 'sm') === 'lg';
@endphp
<div class="d-flex flex-wrap align-items-center gap-1">
    <span class="badge rounded-pill" title="Categoría automática según su historial de pagos"
          style="background:{{ $meta['bg'] }}; color:{{ $meta['color'] }}; border:1px solid {{ $meta['border'] }}; font-size:{{ $isLarge ? 'var(--fs-sm)' : 'var(--fs-xs)' }}; font-weight:600;">
        <i class="bi {{ $meta['icon'] }}"></i> {{ $meta['label'] }}
    </span>
    @if(!empty($tag))
        <span class="badge rounded-pill" title="Etiqueta asignada por el personal"
              style="background:{{ $tagColor ?? \App\Models\ClientTag::DEFAULT_COLOR }}; color:#fff; font-size:{{ $isLarge ? 'var(--fs-sm)' : 'var(--fs-xs)' }}; font-weight:600;">
            <i class="bi bi-tag-fill"></i> {{ $tag }}
        </span>
    @endif
</div>
