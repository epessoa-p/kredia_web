@extends('layouts.app')

@section('title', $catalog->title)

@push('styles')
<style>
    .catalog-show-header {
        border-radius: .75rem;
        overflow: hidden;
        position: relative;
        color: #fff;
        padding: 2rem;
    }
    .catalog-show-header h3 {
        text-shadow: 0 1px 4px rgba(0,0,0,.25);
    }
    .share-link-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        padding: .6rem .85rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .share-link-box input {
        border: none;
        background: transparent;
        flex: 1;
        font-size: var(--fs-base);
        color: #495057;
        outline: none;
    }
    .product-grid-item {
        border: 1px solid #e9ecef;
        border-radius: .5rem;
        overflow: hidden;
        height: 100%;
    }
    .product-grid-item img {
        width: 100%;
        height: 160px;
        object-fit: cover;
    }
    .product-grid-item .no-img-placeholder {
        width: 100%;
        height: 160px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        color: #adb5bd;
        font-size: var(--fs-xl);
    }
    .stat-box {
        background: #f8f9fa;
        border-radius: .5rem;
        padding: .5rem .75rem;
        text-align: center;
        font-size: var(--fs-sm);
    }
    .stat-box .stat-val {
        font-size: var(--fs-md);
        font-weight: 700;
    }
</style>
@endpush

@section('page')
<div class="container-fluid py-4">
    <x-page-header title="Catálogo" icon="megaphone" :back="route('catalogs.index')" />

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Header --}}
    <div class="catalog-show-header mb-4" style="background: {{ $catalog->cover_color ?? '#0d6efd' }}">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h3 class="fw-bold mb-1">{{ $catalog->title }}</h3>
                @if($catalog->description)
                    <p class="mb-0" style="opacity:.9">{{ $catalog->description }}</p>
                @endif
            </div>
            <div>
                @if($catalog->isAccessible())
                    <span class="badge bg-light text-success"><i class="bi bi-check-circle"></i> Activo</span>
                @elseif(!$catalog->active)
                    <span class="badge bg-light text-warning"><i class="bi bi-pause-circle"></i> Pausado</span>
                @else
                    <span class="badge bg-light text-danger"><i class="bi bi-x-circle"></i> Vencido</span>
                @endif
            </div>
        </div>
    </div>

    @php
        $canManageCatalog = auth()->user()->hasPermissionInCompany('catalogs.create', auth()->user()->getCurrentCompany());
    @endphp

    {{-- Stats & Actions --}}
    <div class="row g-3 mb-4">
        <div class="col-auto">
            <div class="stat-box">
                <div class="stat-val">{{ $catalog->items->count() }}</div>
                <div class="text-muted">Productos</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="stat-box">
                <div class="stat-val">
                    @if($catalog->branch)
                        <span class="d-inline-block rounded-circle me-1" style="width:8px;height:8px;background:{{ $catalog->branch->color ?? '#0d6efd' }}"></span>{{ $catalog->branch->name }}
                    @else
                        Todas
                    @endif
                </div>
                <div class="text-muted">Sucursal</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="stat-box">
                <div class="stat-val">{{ $catalog->show_prices ? 'Sí' : 'No' }}</div>
                <div class="text-muted">Precios</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="stat-box">
                <div class="stat-val">{{ $catalog->expires_at ? $catalog->expires_at->format('d/m/Y') : '∞' }}</div>
                <div class="text-muted">Vencimiento</div>
            </div>
        </div>
        <div class="col-auto">
            <div class="stat-box">
                <div class="stat-val">{{ $catalog->createdBy->name ?? '—' }}</div>
                <div class="text-muted">Creado por</div>
            </div>
        </div>
    </div>

    {{-- Enlace público --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <label class="form-label fw-semibold mb-2"><i class="bi bi-link-45deg"></i> Enlace público</label>
            <div class="share-link-box">
                <input type="text" value="{{ $catalog->getPublicUrl() }}" id="publicUrl" readonly>
                <x-btn variant="outline-primary" icon="clipboard" type="button" onclick="copyLink()">Copiar</x-btn>
                <x-icon-btn icon="box-arrow-up-right" label="Abrir enlace" variant="secondary" :href="$catalog->getPublicUrl()" target="_blank" />
            </div>
            <div class="mt-2 d-flex gap-2">
                @if($canManageCatalog)
                    <form action="{{ route('catalogs.regenerate-slug', $catalog) }}" method="POST" onsubmit="return confirm('¿Regenerar el enlace? El anterior dejará de funcionar.')">
                        @csrf
                        <x-btn variant="outline-warning" icon="arrow-repeat" type="submit">Regenerar enlace</x-btn>
                    </form>
                    <form action="{{ route('catalogs.toggle-active', $catalog) }}" method="POST">
                        @csrf
                        <x-btn :variant="$catalog->active ? 'outline-warning' : 'outline-success'" :icon="$catalog->active ? 'pause-fill' : 'play-fill'" type="submit">
                            {{ $catalog->active ? 'Pausar' : 'Activar' }}
                        </x-btn>
                    </form>
                @endif
                <x-btn variant="secondary" icon="file-pdf" :href="route('catalogs.export-pdf', $catalog)">Exportar PDF</x-btn>
                @if($canManageCatalog)
                    <x-btn variant="outline-primary" icon="pencil" :href="route('catalogs.edit', $catalog)">Editar</x-btn>
                @endif
            </div>
        </div>
    </div>

    {{-- Productos --}}
    <h5 class="fw-bold mb-3"><i class="bi bi-box-seam"></i> Productos del catálogo</h5>
    <div class="row g-3">
        @foreach($catalog->items as $item)
            @continue(!$item->product)
            @php
                $product = $item->product;
                $displayName = $item->custom_name ?: $product->name;
                $displayDesc = $item->custom_description ?: $product->description;
                $displayPrice = $item->custom_price ?? $product->price;
                $imageUrl = $productImages[$product->id] ?? null;
            @endphp
            <div class="col-md-4 col-lg-3">
                <div class="product-grid-item bg-white">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $displayName }}">
                    @else
                        <div class="no-img-placeholder"><i class="bi bi-image"></i></div>
                    @endif
                    <div class="p-2">
                        <div class="fw-semibold fs-base">{{ $displayName }}</div>
                        @include('catalogs.partials.origin-badges', ['origins' => $productOrigins[$product->id] ?? [], 'linked' => true])
                        @if($displayDesc)
                            <p class="text-muted mb-1 fs-sm">{{ Str::limit($displayDesc, 60) }}</p>
                        @endif
                        @if($catalog->show_prices && $displayPrice)
                            <div class="fw-bold text-primary">{{ $currencySymbol }} {{ number_format($displayPrice, 2) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
function copyLink() {
    const input = document.getElementById('publicUrl');
    const btn = input.nextElementSibling;

    const showCopied = () => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i> Copiado';
        btn.classList.replace('btn-outline-primary', 'btn-success');
        setTimeout(() => { btn.innerHTML = original; btn.classList.replace('btn-success', 'btn-outline-primary'); }, 2000);
    };

    // Método moderno (solo en contextos seguros: HTTPS o localhost)
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(showCopied).catch(() => fallbackCopy(input, showCopied));
    } else {
        fallbackCopy(input, showCopied);
    }
}

function fallbackCopy(input, onSuccess) {
    input.removeAttribute('readonly');
    input.select();
    input.setSelectionRange(0, 99999); // móviles
    try {
        const ok = document.execCommand('copy');
        if (ok) {
            onSuccess();
        } else {
            window.prompt('Copia el enlace manualmente:', input.value);
        }
    } catch (e) {
        window.prompt('Copia el enlace manualmente:', input.value);
    }
    input.setAttribute('readonly', '');
    window.getSelection().removeAllRanges();
}
</script>
@endpush
