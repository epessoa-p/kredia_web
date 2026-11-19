<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $catalog->title }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/kredia-ui.css') }}">
    <style>
        :root { --catalog-color: {{ $catalog->cover_color ?? '#0d6efd' }}; }
        body { background: #f5f6f8; min-height: 100vh; }
        .catalog-hero {
            background: var(--catalog-color);
            color: #fff;
            padding: 3rem 1.5rem;
            text-align: center;
        }
        .catalog-hero h1 { font-weight: 800; text-shadow: 0 2px 8px rgba(0,0,0,.2); }
        .catalog-hero p { opacity: .9; max-width: 600px; margin: .5rem auto 0; }
        .catalog-logo { max-height: 70px; margin-bottom: 1rem; border-radius: .5rem; }
        .product-card {
            background: #fff;
            border-radius: .75rem;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            transition: transform .18s, box-shadow .18s;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,.1);
        }
        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .product-card .no-img {
            width: 100%;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            color: #ccc;
            font-size: var(--fs-xl);
        }
        .product-card .card-body { padding: 1rem; }
        .product-card .price { color: var(--catalog-color); font-weight: 700; font-size: var(--fs-lg); }
        .contact-bar {
            background: #fff;
            border-top: 3px solid var(--catalog-color);
            padding: 1.5rem;
            text-align: center;
        }
        .contact-bar a {
            color: var(--catalog-color);
            text-decoration: none;
            font-weight: 600;
        }
        .contact-bar a:hover { text-decoration: underline; }
        .catalog-footer {
            text-align: center;
            padding: 1.5rem;
            color: #999;
            font-size: var(--fs-sm);
        }
        .whatsapp-btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem 1.2rem;
            background: #25d366;
            color: #fff;
            border-radius: 2rem;
            text-decoration: none;
            font-weight: 600;
            transition: opacity .15s;
        }
        .whatsapp-btn:hover { opacity: .85; color: #fff; }
    </style>
</head>
<body>

{{-- Hero --}}
<header class="catalog-hero">
    @if($catalog->logo_path)
        <img src="{{ asset('storage/' . $catalog->logo_path) }}" alt="" class="catalog-logo">
    @endif
    <h1>{{ $catalog->title }}</h1>
    @if($catalog->description)
        <p>{{ $catalog->description }}</p>
    @endif
</header>

{{-- Products --}}
<div class="container py-5">
    <div class="row g-4">
        @foreach($catalog->items as $item)
            @continue(!$item->product)
            @php
                $product = $item->product;
                $displayName = $item->custom_name ?: $product->name;
                $displayDesc = $item->custom_description ?: $product->description;
                $displayPrice = $item->custom_price ?? $product->price;
                $imageUrl = $productImages[$product->id] ?? null;
            @endphp
            <div class="col-sm-6 col-md-4 col-lg-3">
                <div class="product-card">
                    @if($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $displayName }}">
                    @else
                        <div class="no-img"><i class="bi bi-image"></i></div>
                    @endif
                    <div class="card-body">
                        <h6 class="fw-bold mb-1">{{ $displayName }}</h6>
                        @if($displayDesc)
                            <p class="text-muted mb-2 fs-base">{{ $displayDesc }}</p>
                        @endif
                        @if($catalog->show_prices && $displayPrice)
                            <div class="price">{{ $currencySymbol }} {{ number_format($displayPrice, 2) }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- Contact --}}
@if($catalog->show_contact && ($catalog->contact_phone || $catalog->contact_whatsapp || $catalog->contact_email || $catalog->branch))
    <div class="contact-bar">
        <h5 class="fw-bold mb-3">Contáctanos</h5>
        @if($catalog->branch)
            <div class="text-muted mb-3 fs-base">
                <i class="bi bi-geo-alt"></i> Sucursal <strong>{{ $catalog->branch->name }}</strong>
                @if($catalog->branch->address) &middot; {{ $catalog->branch->address }} @endif
            </div>
        @endif
        <div class="d-flex justify-content-center gap-4 flex-wrap">
            @if($catalog->contact_phone)
                <a href="tel:{{ $catalog->contact_phone }}"><i class="bi bi-telephone"></i> {{ $catalog->contact_phone }}</a>
            @endif
            @if($catalog->contact_whatsapp)
                <a href="https://wa.me/{{ preg_replace('/\D/', '', $catalog->contact_whatsapp) }}" target="_blank" class="whatsapp-btn">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
            @endif
            @if($catalog->contact_email)
                <a href="mailto:{{ $catalog->contact_email }}"><i class="bi bi-envelope"></i> {{ $catalog->contact_email }}</a>
            @endif
        </div>
    </div>
@endif

<footer class="catalog-footer">
    {{ $catalog->company->name ?? '' }} &middot; Catálogo generado automáticamente
</footer>

</body>
</html>
