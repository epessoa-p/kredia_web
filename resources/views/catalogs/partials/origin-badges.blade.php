{{--
    Códigos de origen de un producto del catálogo (revisión interna).
    Parámetros:
      - $origins: [['code' => 'PR-00523', 'type' => 'loan'|'purchase', 'url' => ...], ...]
      - $linked:  true = cada código enlaza al préstamo/compra (pestaña nueva)
    No usar en el enlace público ni en el PDF.
--}}
@php
    $origins = $origins ?? [];
    $linked = $linked ?? false;
    $maxVisible = 3;
    $visible = array_slice($origins, 0, $maxVisible);
    $hidden = array_slice($origins, $maxVisible);
@endphp
<div class="catalog-origins d-flex flex-wrap align-items-center gap-1 my-1">
    @forelse($visible as $origin)
        @php
            $isLoan = $origin['type'] === 'loan';
            $cls = $isLoan ? 'catalog-origin--loan' : 'catalog-origin--purchase';
            $title = $isLoan ? 'Préstamo con la prenda en venta' : 'Compra';
        @endphp
        @if($linked)
            <a href="{{ $origin['url'] }}" target="_blank" rel="noopener" class="catalog-origin {{ $cls }}" title="{{ $title }}">
                <i class="bi {{ $isLoan ? 'bi-file-earmark-text' : 'bi-bag' }}"></i>{{ $origin['code'] }}
            </a>
        @else
            <span class="catalog-origin {{ $cls }}" title="{{ $title }}">
                <i class="bi {{ $isLoan ? 'bi-file-earmark-text' : 'bi-bag' }}"></i>{{ $origin['code'] }}
            </span>
        @endif
    @empty
        <span class="catalog-origin catalog-origin--none" title="Producto sin préstamo ni compra asociada">Sin préstamo</span>
    @endforelse

    @if(count($hidden) > 0)
        <span class="catalog-origin catalog-origin--more" title="{{ collect($hidden)->pluck('code')->implode(', ') }}">+{{ count($hidden) }}</span>
    @endif
</div>

@once
    @push('styles')
    <style>
        .catalog-origin {
            display: inline-flex; align-items: center; gap: 3px;
            font-size: var(--fs-xs); font-weight: 600; line-height: 1;
            padding: 3px 6px; border-radius: 999px;
            border: 1px solid transparent; text-decoration: none; white-space: nowrap;
        }
        .catalog-origin--loan     { background: #eef2ff; color: #3730a3; border-color: #c7d2fe; }
        .catalog-origin--purchase { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
        .catalog-origin--none     { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; font-weight: 500; }
        .catalog-origin--more     { background: #f3f4f6; color: #374151; border-color: #e5e7eb; cursor: help; }
        a.catalog-origin:hover    { filter: brightness(.95); text-decoration: underline; }
    </style>
    @endpush
@endonce
