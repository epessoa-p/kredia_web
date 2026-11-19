@extends('layouts.app')

@section('title', 'Venta Rapida')

@section('page')
<div>
    <x-page-header title="Catalogo para venta inmediata" icon="lightning-charge-fill" subtitle="Selecciona un producto para abrir la venta con sucursal y producto precargados.">
        <x-btn icon="plus-lg" :href="route('sales.create')">Nueva venta manual</x-btn>
        <x-btn variant="secondary" icon="arrow-left" :href="route('sales.index')">Ventas</x-btn>
    </x-page-header>

    @if($hasCash)
        <div class="alert alert-info border-0 d-flex align-items-center gap-2 py-2 px-3 mb-3" style="border-radius:12px;">
            <i class="bi bi-shop"></i>
            <div class="small">Vendiendo desde <strong>{{ $sessionBranchName ?? 'tu sucursal' }}</strong>. Los productos de otras sucursales aparecen como referencia (para recomendarlos), sin poder venderlos aquí.</div>
        </div>
    @else
        <div class="alert alert-warning border-0 d-flex align-items-center gap-2 py-2 px-3 mb-3" style="border-radius:12px;">
            <i class="bi bi-lock-fill"></i>
            <div class="small">Estás viendo el catálogo. <strong>Aperturá una caja</strong> para poder vender.</div>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4 quick-sale-page-card">
        <div class="card-body p-3 p-md-4">
            <div class="quick-sale-toolbar mb-3">
                <div class="input-group input-group-sm quick-sale-search-wrap">
                    <span class="input-group-text bg-transparent border-0"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control border-0" id="quick-sale-search" placeholder="Buscar por producto, código (PD-12), marca, sucursal o categoría">
                </div>
                <span class="quick-sale-count" id="quick-sale-count">{{ $quickSaleProducts->count() }} producto{{ $quickSaleProducts->count() === 1 ? '' : 's' }}</span>
            </div>

            <div class="quick-sale-empty d-none" id="quick-sale-empty">
                <div class="quick-sale-empty-icon"><i class="bi bi-search-heart"></i></div>
                <h6 class="fw-bold mb-1">No encontramos productos con ese criterio</h6>
                <p class="text-muted mb-0">Prueba con otro termino para continuar con la venta.</p>
            </div>

            <div class="row g-3" id="quick-sale-grid">
                @forelse($quickSaleProducts as $saleProduct)
                    <div class="col-sm-6 col-lg-4 col-xl-3 quick-sale-card-col" data-search="{{ $saleProduct['search_text'] }}">
                        <article class="quick-sale-card h-100{{ (($saleProduct['own'] ?? false) && $hasCash) ? '' : ' quick-sale-other' }}">
                            <div class="quick-sale-card-media{{ count($saleProduct['image_urls'] ?? []) > 0 ? ' has-images' : '' }}"
                                @if(count($saleProduct['image_urls'] ?? []) > 0)
                                    role="button"
                                    tabindex="0"
                                    data-product-name="{{ $saleProduct['name'] }}"
                                    data-images="{{ json_encode($saleProduct['image_urls'] ?? []) }}"
                                    onclick="openProductGallery(this)"
                                    onkeydown="if(event.key==='Enter')openProductGallery(this)"
                                    title="Ver fotos"
                                @endif>
                                @if($saleProduct['image_url'])
                                    <img src="{{ $saleProduct['image_url'] }}" alt="{{ $saleProduct['name'] }}">
                                @else
                                    <div class="quick-sale-card-placeholder"><i class="bi bi-box-seam"></i></div>
                                @endif
                                @if(count($saleProduct['image_urls'] ?? []) > 1)
                                    <span class="quick-sale-photo-badge">
                                        <i class="bi bi-images"></i> {{ count($saleProduct['image_urls']) }} fotos
                                    </span>
                                @elseif(count($saleProduct['image_urls'] ?? []) === 1)
                                    <span class="quick-sale-photo-badge single">
                                        <i class="bi bi-image"></i>
                                    </span>
                                @endif
                                <span class="quick-sale-origin">{{ $saleProduct['origin_label'] }}</span>
                            </div>
                            <div class="quick-sale-card-body">
                                <div class="quick-sale-card-title">
                                    @if(!empty($saleProduct['code']))<span class="text-primary">{{ $saleProduct['code'] }}</span> · @endif{{ $saleProduct['name'] }}
                                </div>
                                <div class="quick-sale-card-subtitle">{{ $saleProduct['brand'] ?: $saleProduct['category_name'] }}</div>
                                <div class="quick-sale-card-facts">
                                    <span><strong>Sucursal:</strong> {{ $saleProduct['branch_name'] }}</span>
                                    <span><strong>Categoria:</strong> {{ $saleProduct['category_name'] }}</span>
                                    <span><strong>Stock:</strong> {{ number_format((float) $saleProduct['stock'], 2) }} und.</span>
                                </div>
                                <div class="quick-sale-card-footer">
                                    <div class="quick-sale-price">
                                        <small>Precio sugerido</small>
                                        <strong>{{ $currencySymbol }} {{ number_format((float) $saleProduct['price'], 2) }}</strong>
                                    </div>
                                    @if(($saleProduct['own'] ?? false) && $hasCash)
                                        <x-btn icon="cart-check" :href="$saleProduct['sale_url']" class="quick-sale-go">Vender</x-btn>
                                    @elseif(!$hasCash)
                                        <span class="badge bg-secondary-subtle text-secondary border fs-xs">
                                            <i class="bi bi-eye me-1"></i> Solo vista
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border text-end" style="font-size:var(--fs-xs);line-height:1.25;">
                                            <i class="bi bi-geo-alt me-1"></i>En {{ $saleProduct['branch_name'] }}<br>pídelo a esa sucursal
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </article>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info mb-0">No hay productos disponibles para venta rapida en este momento.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ── Product image gallery modal ─────────────────────── --}}
<div class="modal fade" id="productGalleryModal" tabindex="-1" aria-labelledby="productGalleryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content pgl-modal-content">
            <div class="modal-header border-0 pb-1 px-4 pt-3">
                <div>
                    <h6 class="modal-title fw-bold mb-0" id="productGalleryModalLabel">Fotos del producto</h6>
                    <small class="text-muted" id="pgl-counter">— / —</small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div id="productGalleryCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner pgl-carousel-inner" id="pglInner"></div>
                    <button class="carousel-control-prev pgl-nav-btn" type="button" data-bs-target="#productGalleryCarousel" data-bs-slide="prev" id="pgl-prev">
                        <span class="pgl-nav-icon"><i class="bi bi-chevron-left"></i></span>
                    </button>
                    <button class="carousel-control-next pgl-nav-btn" type="button" data-bs-target="#productGalleryCarousel" data-bs-slide="next" id="pgl-next">
                        <span class="pgl-nav-icon"><i class="bi bi-chevron-right"></i></span>
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 pt-2 px-4 pb-3 justify-content-center" id="pgl-thumbs-wrap">
                <div class="pgl-thumbs" id="pglThumbs"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .quick-sale-page-card {
        background:
            radial-gradient(circle at top right, rgba(6, 182, 212, .10), transparent 22%),
            radial-gradient(circle at bottom left, rgba(37, 99, 235, .08), transparent 26%),
            linear-gradient(180deg, #f9fcff 0%, #f4f8ff 100%);
    }

    .quick-sale-toolbar {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
    }

    .quick-sale-search-wrap {
        flex: 1 1 360px;
        border: 1px solid #d7e5fb;
        background: rgba(255,255,255,.92);
        border-radius: 999px;
        padding: .12rem .25rem;
        box-shadow: 0 8px 22px rgba(17, 24, 39, .05);
    }

    .quick-sale-count {
        display: inline-flex;
        align-items: center;
        min-height: 34px;
        padding: .35rem .8rem;
        border-radius: 999px;
        border: 1px solid #dde7f7;
        background: rgba(255,255,255,.88);
        color: #475467;
        font-size:var(--fs-sm);
        font-weight: 700;
    }

    .quick-sale-card {
        border: 1px solid #d9e6fb;
        border-radius: 18px;
        overflow: hidden;
        background: rgba(255,255,255,.94);
        box-shadow: 0 12px 26px rgba(17, 24, 39, .07);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .quick-sale-card:hover {
        transform: translateY(-3px);
        border-color: #b8d1fb;
        box-shadow: 0 18px 32px rgba(17, 24, 39, .12);
    }

    /* Producto de otra sucursal o sin caja: solo referencia. */
    .quick-sale-card.quick-sale-other { opacity: .72; }
    .quick-sale-card.quick-sale-other:hover { opacity: 1; }

    .quick-sale-card-media {
        position: relative;
        height: 138px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: linear-gradient(135deg, #dff3ff 0%, #edf4ff 55%, #f8fbff 100%);
    }

    .quick-sale-card-media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .quick-sale-card-placeholder {
        width: 60px;
        height: 60px;
        border-radius: 18px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: rgba(255,255,255,.78);
        color: #2563eb;
        font-size:var(--fs-lg);
    }

    .quick-sale-origin {
        position: absolute;
        top: 12px;
        right: 12px;
        padding: .35rem .65rem;
        border-radius: 999px;
        background: rgba(255,255,255,.82);
        border: 1px solid rgba(37, 99, 235, .12);
        color: #1d4ed8;
        font-size:var(--fs-xs);
        font-weight: 700;
        backdrop-filter: blur(8px);
    }

    .quick-sale-card-body {
        padding: .8rem .85rem .9rem;
    }

    .quick-sale-card-title {
        font-size:var(--fs-base);
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
        min-height: 2rem;
    }

    .quick-sale-card-subtitle {
        margin-top: .15rem;
        color: #667085;
        font-size:var(--fs-xs);
        min-height: 1rem;
    }

    .quick-sale-card-facts {
        margin-top: .55rem;
        padding-top: .55rem;
        border-top: 1px dashed #dbe5f3;
        display: flex;
        flex-direction: column;
        gap: .2rem;
        font-size:var(--fs-xs);
        color: #475467;
    }

    .quick-sale-card-footer {
        margin-top: .7rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .6rem;
    }

    .quick-sale-price {
        display: flex;
        flex-direction: column;
    }

    .quick-sale-price small {
        font-size:var(--fs-xs);
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .quick-sale-price strong {
        font-size:var(--fs-md);
        color: #14532d;
    }

    .quick-sale-card-media.has-images {
        cursor: zoom-in;
    }

    .quick-sale-card-media.has-images::after {
        content: '';
        position: absolute;
        inset: 0;
        background: rgba(0,0,0,0);
        transition: background .18s ease;
        pointer-events: none;
    }

    .quick-sale-card-media.has-images:hover::after {
        background: rgba(0,0,0,.12);
    }

    .quick-sale-photo-badge {
        position: absolute;
        bottom: 10px;
        left: 10px;
        padding: .28rem .6rem;
        border-radius: 999px;
        background: rgba(10,20,40,.58);
        color: #fff;
        font-size:var(--fs-xs);
        font-weight: 700;
        backdrop-filter: blur(6px);
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        pointer-events: none;
        line-height: 1;
    }

    .quick-sale-photo-badge.single {
        padding: .28rem .45rem;
    }

    /* Gallery modal */
    .pgl-modal-content {
        border: 0;
        border-radius: 22px;
        overflow: hidden;
        background: #0e1522;
        color: #fff;
    }

    .pgl-modal-content .modal-header {
        background: #0e1522;
    }

    .pgl-modal-content .modal-title,
    .pgl-modal-content .text-muted {
        color: rgba(255,255,255,.85) !important;
    }

    .pgl-modal-content .btn-close {
        filter: invert(1) brightness(1.5);
    }

    .pgl-carousel-inner {
        background: #0e1522;
        min-height: 340px;
        max-height: 62vh;
        display: flex;
        align-items: center;
    }

    .pgl-carousel-inner .carousel-item {
        text-align: center;
    }

    .pgl-carousel-inner img {
        max-height: 62vh;
        max-width: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        display: inline-block;
    }

    .pgl-nav-btn {
        width: 48px;
        opacity: 1;
    }

    .pgl-nav-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: rgba(255,255,255,.12);
        backdrop-filter: blur(6px);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size:var(--fs-md);
        transition: background .15s ease;
    }

    .pgl-nav-btn:hover .pgl-nav-icon {
        background: rgba(255,255,255,.24);
    }

    .pgl-modal-content .modal-footer {
        background: #0e1522;
    }

    .pgl-thumbs {
        display: flex;
        gap: .45rem;
        flex-wrap: wrap;
        justify-content: center;
    }

    .pgl-thumb {
        width: 52px;
        height: 52px;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid transparent;
        cursor: pointer;
        transition: border-color .15s ease, opacity .15s ease;
        opacity: .55;
        flex-shrink: 0;
    }

    .pgl-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .pgl-thumb.active {
        border-color: #4a90e2;
        opacity: 1;
    }

    .pgl-thumb:hover {
        opacity: .85;
    }

    .quick-sale-empty {
        border: 1px dashed #dbe6f8;
        border-radius: 18px;
        background: rgba(255,255,255,.75);
        text-align: center;
        padding: 2rem 1rem;
    }

    .quick-sale-empty-icon {
        width: 62px;
        height: 62px;
        border-radius: 20px;
        margin: 0 auto .75rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #e8f3ff 0%, #f1f7ff 100%);
        color: #2563eb;
        font-size:var(--fs-lg);
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const quickSaleSearch = document.getElementById('quick-sale-search');
    const quickSaleGrid = document.getElementById('quick-sale-grid');
    const quickSaleEmpty = document.getElementById('quick-sale-empty');
    const quickSaleCount = document.getElementById('quick-sale-count');

    if (!quickSaleSearch || !quickSaleGrid || !quickSaleEmpty || !quickSaleCount) {
        return;
    }

    const quickSaleCards = Array.from(quickSaleGrid.querySelectorAll('.quick-sale-card-col'));

    const renderQuickSaleFilter = () => {
        const term = (quickSaleSearch.value || '').trim().toLowerCase();
        let visible = 0;

        quickSaleCards.forEach((card) => {
            const haystack = card.dataset.search || '';
            const matches = !term || haystack.includes(term);
            card.classList.toggle('d-none', !matches);
            if (matches) {
                visible += 1;
            }
        });

        quickSaleCount.textContent = visible + ' producto' + (visible === 1 ? '' : 's');
        quickSaleEmpty.classList.toggle('d-none', visible !== 0);
    };

    quickSaleSearch.addEventListener('input', renderQuickSaleFilter);
    renderQuickSaleFilter();
});

/* ── Product image gallery ─────────────────────────────── */
let _galleryCarousel = null;
let _galleryImages = [];

function openProductGallery(el) {
    const images = JSON.parse(el.dataset.images || '[]');
    const name   = el.dataset.productName || 'Producto';

    if (!images.length) return;

    _galleryImages = images;

    // Populate carousel
    const inner  = document.getElementById('pglInner');
    const thumbs = document.getElementById('pglThumbs');
    const counter = document.getElementById('pgl-counter');
    const prevBtn = document.getElementById('pgl-prev');
    const nextBtn = document.getElementById('pgl-next');

    inner.innerHTML = images.map((url, i) =>
        `<div class="carousel-item${i === 0 ? ' active' : ''}">
            <img src="${url}" alt="Foto ${i + 1} - ${name}">
        </div>`
    ).join('');

    thumbs.innerHTML = images.map((url, i) =>
        `<div class="pgl-thumb${i === 0 ? ' active' : ''}" data-index="${i}" onclick="galleryGoTo(${i})" title="Foto ${i+1}">
            <img src="${url}" alt="Miniatura ${i + 1}">
        </div>`
    ).join('');

    // Show/hide nav buttons
    prevBtn.classList.toggle('d-none', images.length < 2);
    nextBtn.classList.toggle('d-none', images.length < 2);
    document.getElementById('pgl-thumbs-wrap').classList.toggle('d-none', images.length < 2);

    // Title and counter
    document.getElementById('productGalleryModalLabel').textContent = name;
    counter.textContent = images.length > 1 ? `1 / ${images.length}` : '1 foto';

    // Init or re-init Bootstrap carousel
    const carouselEl = document.getElementById('productGalleryCarousel');
    if (_galleryCarousel) {
        _galleryCarousel.dispose();
    }
    _galleryCarousel = new bootstrap.Carousel(carouselEl, { interval: false, wrap: true });

    carouselEl.addEventListener('slid.bs.carousel', function handler(e) {
        const idx = e.to;
        counter.textContent = `${idx + 1} / ${images.length}`;
        thumbs.querySelectorAll('.pgl-thumb').forEach((t, i) => t.classList.toggle('active', i === idx));
        thumbs.querySelectorAll('.pgl-thumb')[idx]?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }, { once: false });
    carouselEl._galleryHandler = true;

    bootstrap.Modal.getOrCreateInstance(document.getElementById('productGalleryModal')).show();
}

function galleryGoTo(index) {
    if (_galleryCarousel) {
        _galleryCarousel.to(index);
    }
}</script>
@endpush
