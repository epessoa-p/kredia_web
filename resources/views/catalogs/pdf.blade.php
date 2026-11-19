<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $catalog->title }} — Catálogo PDF</title>
    <style>
        @page { margin: 1.5cm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #333; font-size: 11px; }

        .header {
            text-align: center;
            padding: 20px 0 15px;
            border-bottom: 4px solid {{ $catalog->cover_color ?? '#0d6efd' }};
            margin-bottom: 20px;
        }
        .header h1 { font-size: 24px; margin-bottom: 5px; color: {{ $catalog->cover_color ?? '#0d6efd' }}; }
        .header p { color: #666; font-size: 13px; }
        .header .company { font-size: 11px; color: #999; margin-top: 4px; }

        .products-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px;
        }
        .products-grid td {
            width: 33.33%;
            vertical-align: top;
            background: #fafafa;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 10px;
        }
        .img-box { height: 150px; text-align: center; margin-bottom: 8px; background: #fff; border-radius: 4px; }
        .product-img { max-width: 100%; max-height: 150px; }
        .no-img-box {
            width: 100%;
            height: 88px;
            background: #f0f0f0;
            border-radius: 4px;
            margin-bottom: 8px;
            text-align: center;
            padding-top: 62px;
            color: #bbb;
            font-size: 11px;
        }
        .product-name { font-weight: 700; font-size: 13px; margin-bottom: 3px; }
        .product-desc { color: #666; font-size: 11px; margin-bottom: 4px; }
        .product-price { font-weight: 700; color: {{ $catalog->cover_color ?? '#0d6efd' }}; font-size: 14px; }

        .contact-section {
            margin-top: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #eee;
        }
        .contact-section h3 { font-size: 14px; margin-bottom: 8px; }
        .contact-section span { margin: 0 12px; font-size: 12px; color: #555; }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #aaa;
        }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="header">
    @if(!empty($logo))
        <img src="{{ $logo }}" alt="" style="max-height:50px;margin-bottom:8px">
    @endif
    <h1>{{ $catalog->title }}</h1>
    @if($catalog->description)
        <p>{{ $catalog->description }}</p>
    @endif
    <div class="company">{{ $catalog->company->name ?? '' }}</div>
</div>

@php
    $items = $catalog->items->filter(fn ($item) => $item->product)->values();
    $chunks = $items->chunk(3);
@endphp

@foreach($chunks as $row)
    <table class="products-grid">
        <tr>
            @foreach($row as $item)
                @php
                    $product = $item->product;
                    $displayName = $item->custom_name ?: $product->name;
                    $displayDesc = $item->custom_description ?: $product->description;
                    $displayPrice = $item->custom_price ?? $product->price;
                    $imageUrl = $productImages[$product->id] ?? null;
                @endphp
                <td>
                    @if($imageUrl)
                        <div class="img-box"><img src="{{ $imageUrl }}" alt="" class="product-img"></div>
                    @else
                        <div class="no-img-box">Sin foto</div>
                    @endif
                    <div class="product-name">{{ $displayName }}</div>
                    @if($displayDesc)
                        <div class="product-desc">{{ Str::limit($displayDesc, 80) }}</div>
                    @endif
                    @if($catalog->show_prices && $displayPrice)
                        <div class="product-price">{{ $currencySymbol }} {{ number_format($displayPrice, 2) }}</div>
                    @endif
                </td>
            @endforeach
            @for($i = $row->count(); $i < 3; $i++)
                <td style="border:none;background:transparent"></td>
            @endfor
        </tr>
    </table>
@endforeach

@if($catalog->show_contact && ($catalog->contact_phone || $catalog->contact_whatsapp || $catalog->contact_email))
    <div class="contact-section">
        <h3>Contáctanos</h3>
        @if($catalog->contact_phone)
            <span>Tel.: {{ $catalog->contact_phone }}</span>
        @endif
        @if($catalog->contact_whatsapp)
            <span>WhatsApp: {{ $catalog->contact_whatsapp }}</span>
        @endif
        @if($catalog->contact_email)
            <span>Email: {{ $catalog->contact_email }}</span>
        @endif
    </div>
@endif

<div class="footer">
    Catálogo generado el {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
