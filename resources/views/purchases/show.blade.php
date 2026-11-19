@extends('layouts.app')

@section('title', 'Compra CO-' . str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT))

@push('styles')
<style>
    .purchase-stat { border-left: 3px solid; padding-left: 12px; }
    .purchase-stat.primary { border-color: #0d6efd; }
    .purchase-stat.success { border-color: #198754; }
    .purchase-stat.info    { border-color: #0dcaf0; }
    .purchase-stat.warning { border-color: #ffc107; }
    .purchase-stat .stat-label { font-size:var(--fs-sm); text-transform: uppercase; letter-spacing: .03em; color: #6c757d; }
    .purchase-stat .stat-value { font-size:var(--fs-lg); font-weight: 700; }

    .purchase-gallery-thumb {
        width: 80px; height: 80px; object-fit: cover;
        border-radius: 8px; border: 2px solid #e9ecef;
        cursor: pointer; transition: border-color .2s;
    }
    .purchase-gallery-thumb:hover { border-color: #0d6efd; }

    .client-avatar {
        width: 62px;
        height: 62px;
        border-radius: 18px;
        overflow: hidden;
        background: #eef5ff;
        border: 1px solid rgba(13, 110, 253, .12);
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .client-avatar-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .client-avatar i {
        font-size:var(--fs-lg);
        color: #0d6efd;
    }

    .purchase-print-card {
        border: 1px solid #d8e4f7;
        background: linear-gradient(145deg, #f7fbff 0%, #eef6ff 100%);
    }
    .purchase-print-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(135deg, #e7f0ff 0%, #d8e8ff 100%);
        color: #0d6efd;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size:var(--fs-md);
    }
</style>
@endpush

@section('page')
@php
    $statusColors = [
        'completed' => 'success', 'cancelled' => 'dark',
    ];
    $statusLabels = [
        'completed' => 'Completada', 'cancelled' => 'Cancelada',
    ];

    $categoryColors = [
        1 => 'primary', 2 => 'warning', 3 => 'info', 4 => 'success',
    ];
    $categoryIcons = [
        1 => 'bi-truck', 2 => 'bi-gem', 3 => 'bi-fire', 4 => 'bi-box-seam',
    ];

    $fromContext = request('from');
    $backUrl = route('purchases.index');
    $backLabel = 'Volver a compras';
    if ($fromContext === 'client' && $purchase->client_id) {
        $backUrl = route('clients.show', $purchase->client_id);
        $backLabel = 'Volver al cliente';
    }

    $defaultPrintTemplateId = $defaultPurchasePrintTemplateId ?? optional(($purchasePrintTemplates ?? collect())->first())->id;
@endphp

{{-- Header --}}
<x-page-header :title="'Compra CO-' . str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT)" icon="bag-check">
    <x-slot:meta>
        <span class="badge bg-{{ $statusColors[$purchase->status] ?? 'secondary' }}">
            {{ $statusLabels[$purchase->status] ?? ucfirst($purchase->status) }}
        </span>
    </x-slot:meta>
    <x-btn variant="secondary" icon="arrow-left" :href="$backUrl">{{ $backLabel }}</x-btn>
</x-page-header>

{{-- Print actions row --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm purchase-print-card h-100">
            <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row align-items-md-center gap-3 justify-content-between">
                <div class="d-flex align-items-start gap-3">
                    <span class="purchase-print-icon">
                        <i class="bi bi-receipt"></i>
                    </span>
                    <div>
                        <h6 class="mb-1 fw-bold">Comprobante de compra</h6>
                        <p class="text-muted small mb-0">Imprime el ticket térmico de la compra.</p>
                    </div>
                </div>
                <x-btn icon="printer-fill" id="btn-print-purchase" title="80 mm · térmico">Imprimir ticket</x-btn>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row align-items-md-center gap-3 justify-content-between">
                <div class="d-flex align-items-start gap-3">
                    <span class="purchase-print-icon" style="background:linear-gradient(135deg,#d1f7e0 0%,#b7edca 100%);color:#155724;">
                        <i class="bi bi-file-earmark-text-fill"></i>
                    </span>
                    <div>
                        <h6 class="mb-1 fw-bold">Documento Privado de Compra</h6>
                        <p class="text-muted small mb-0">Imprime el contrato legal con los datos del cliente y producto registrados.</p>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <x-btn variant="success" icon="printer" id="btn-doc-privado">Imprimir Documento</x-btn>
                    <x-btn variant="outline-success" icon="file-earmark-text" id="btn-doc-privado-v2">Imprimir documento v2</x-btn>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Stats bar --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="purchase-stat primary">
            <div class="stat-label">Monto de compra</div>
            <div class="stat-value text-primary">{{ $currencySymbol }} {{ number_format((float) $purchase->amount, 2) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="purchase-stat success">
            <div class="stat-label">Fecha de compra</div>
            <div class="stat-value text-success">{{ $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d/m/Y') : '-' }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="purchase-stat info">
            <div class="stat-label">Sucursal</div>
            <div class="stat-value text-info">{{ $purchase->branch?->name ?? '-' }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="purchase-stat warning">
            <div class="stat-label">Categoría</div>
            <div class="stat-value text-warning">
                <i class="bi {{ $categoryIcons[(int) $purchase->credit_category_id] ?? 'bi-tag' }} me-1"></i>
                {{ $purchase->creditCategory?->name ?? '-' }}
            </div>
        </div>
    </div>
</div>

{{-- Detail cards --}}
<div class="row g-4 mb-4">
    {{-- Client info --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-person-fill text-primary me-1"></i> Vendedor / Cliente</h6>
                @if($purchase->client)
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <div class="client-avatar bg-light rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                            @if($purchase->client->photo_url)
                                <img src="{{ $purchase->client->photo_url }}" alt="{{ $purchase->client->name }}" class="client-avatar-img">
                            @else
                                <i class="bi bi-person-fill text-primary"></i>
                            @endif
                        </div>
                        <div>
                            <div class="mb-1"><strong>{{ $purchase->client->name }}</strong></div>
                            <div class="text-muted small">{{ $purchase->client->email ?? $purchase->client->phone ?? 'Cliente registrado' }}</div>
                        </div>
                    </div>
                    <div class="small text-muted mb-1"><i class="bi bi-card-text me-1"></i> {{ $purchase->client->id_number ?? '-' }}</div>
                    <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i> {{ $purchase->client->phone ?? '-' }}</div>
                    <div class="small text-muted mb-1"><i class="bi bi-envelope me-1"></i> {{ $purchase->client->email ?? '-' }}</div>
                    <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i> {{ $purchase->client->address ?? '-' }}</div>
                @else
                    <p class="text-muted small mb-0">Sin cliente registrado.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Product details --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-box-seam text-success me-1"></i> Producto</h6>
                <div class="row g-2 small">
                    <div class="col-5"><span class="text-muted">Nombre:</span></div>
                    <div class="col-7 fw-semibold">{{ $purchase->product?->name ?? '-' }}</div>
                    @if($purchase->product?->code)
                        <div class="col-5"><span class="text-muted">Código:</span></div>
                        <div class="col-7 fw-semibold">{{ $purchase->product->code }}</div>
                    @endif

                    @if($purchase->product?->brand)
                        <div class="col-5"><span class="text-muted">Marca:</span></div>
                        <div class="col-7 fw-semibold">{{ $purchase->product->brand }}</div>
                    @endif

                    @if($purchase->product?->model_year)
                        <div class="col-5"><span class="text-muted">Modelo (año):</span></div>
                        <div class="col-7 fw-semibold">{{ $purchase->product->model_year }}</div>
                    @endif

                    @if($purchase->product?->plate)
                        <div class="col-5"><span class="text-muted">Placa:</span></div>
                        <div class="col-7 fw-semibold">{{ $purchase->product->plate }}</div>
                    @endif

                    @if($purchase->product?->chassis)
                        <div class="col-5"><span class="text-muted">Chasis:</span></div>
                        <div class="col-7 fw-semibold">{{ $purchase->product->chassis }}</div>
                    @endif

                    @if($purchase->product?->serial_number)
                        <div class="col-5"><span class="text-muted">Serial:</span></div>
                        <div class="col-7 fw-semibold">{{ $purchase->product->serial_number }}</div>
                    @endif

                    @if($purchase->product?->item_type)
                        <div class="col-5"><span class="text-muted">Tipo:</span></div>
                        <div class="col-7 fw-semibold">{{ $purchase->product->item_type }}</div>
                    @endif

                    @if($purchase->product?->karat)
                        <div class="col-5"><span class="text-muted">Kilataje:</span></div>
                        <div class="col-7 fw-semibold">{{ $purchase->product->karat }}</div>
                    @endif

                    @if($purchase->product?->price && (float) $purchase->product->price > 0)
                        <div class="col-5"><span class="text-muted">Precio ref.:</span></div>
                        <div class="col-7 fw-semibold">{{ $currencySymbol }} {{ number_format((float) $purchase->product->price, 2) }}</div>
                    @endif

                    @if($purchase->product?->description)
                        <div class="col-12 mt-2">
                            <span class="text-muted d-block mb-1">Detalles:</span>
                            <div class="bg-light rounded p-2 small">{{ $purchase->product->description }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Purchase info --}}
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="bi bi-info-circle text-warning me-1"></i> Información</h6>
                <div class="row g-2 small">
                    <div class="col-5"><span class="text-muted">Creado por:</span></div>
                    <div class="col-7 fw-semibold">{{ $purchase->createdBy?->name ?? '-' }}</div>

                    <div class="col-5"><span class="text-muted">Fecha registro:</span></div>
                    <div class="col-7 fw-semibold">{{ $purchase->created_at?->format('d/m/Y H:i') }}</div>

                    @if($purchase->cashRegister)
                        <div class="col-5"><span class="text-muted">Caja:</span></div>
                        <div class="col-7 fw-semibold">{{ $purchase->cashRegister->name }}</div>
                    @endif

                    @if($purchase->cashRegisterSession)
                        <div class="col-5"><span class="text-muted">Sesión:</span></div>
                        <div class="col-7 fw-semibold">#{{ $purchase->cashRegisterSession->id }}</div>
                    @endif

                    @if($purchase->notes)
                        <div class="col-12 mt-2">
                            <span class="text-muted d-block mb-1">Notas:</span>
                            <div class="bg-light rounded p-2 small">{{ $purchase->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Image gallery --}}
@if($purchase->images->isNotEmpty())
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="fw-bold mb-3"><i class="bi bi-images text-info me-1"></i> Fotos e Imágenes</h6>
        <div class="d-flex flex-wrap gap-2">
            @foreach($purchase->images as $image)
                <a href="{{ asset('storage/' . $image->path) }}" target="_blank" title="{{ $image->original_name }}">
                    <img src="{{ asset('storage/' . $image->path) }}"
                        alt="{{ $image->original_name }}"
                        class="purchase-gallery-thumb">
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Delete action --}}
@php
    $canDeletePurchase = auth()->user()->is_super_admin
        || auth()->user()->hasPermissionInCompany('purchases.delete', auth()->user()->getCurrentCompany());
@endphp
@if($canDeletePurchase)
<div class="card border-0 shadow-sm mb-4 border-danger">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h6 class="fw-bold mb-1 text-danger"><i class="bi bi-trash3 me-1"></i> Eliminar compra</h6>
            <p class="small text-muted mb-0">
                Se devolverán <strong>{{ $currencySymbol }} {{ number_format((float) $purchase->amount, 2) }}</strong> a tu caja aperturada
                y el producto <strong>saldrá del inventario</strong>. Necesitas una caja abierta.
            </p>
        </div>
        <form action="{{ route('purchases.destroy', $purchase) }}" method="POST"
            onsubmit="return confirm('¿Eliminar esta compra? {{ $currencySymbol }} {{ number_format((float) $purchase->amount, 2) }} volverán a tu caja y el producto saldrá del inventario.');">
            @csrf @method('DELETE')
            <x-btn variant="outline-danger" type="submit" icon="trash3">Eliminar</x-btn>
        </form>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
    /* ── Documento Privado de Compra ───────────── */
    (function () {
        const dpData = {
            companyName:    @json($purchase->company?->name ?? ''),
            companyAddress: @json($purchase->company?->address ?? ''),
            companyPhone:   @json($purchase->company?->phone ?? ''),
            branchPhone:    @json($purchase->branch?->phone ?? ''),
            docNum:         "{{ str_pad((string) $purchase->numero, 6, '0', STR_PAD_LEFT) }}",
            clientName:     @json($purchase->client?->name ?? ''),
            clientCI:       @json($purchase->client?->id_number ?? ''),
            clientAddr:     @json($purchase->client?->address ?? ''),
            clientPhone:    @json($purchase->client?->phone ?? ''),
            productName:    @json($purchase->product?->name ?? ''),
            brand:          @json($purchase->product?->brand ?? ''),
            modelYear:      @json($purchase->product?->model_year ?? ''),
            itemModel:      @json($purchase->product?->item_model ?? ''),
            itemType:       @json($purchase->product?->item_type ?? ''),
            karat:          @json($purchase->product?->karat ?? ''),
            serialNumber:   @json($purchase->product?->serial_number ?? ''),
            plate:          @json($purchase->product?->plate ?? ''),
            chassis:        @json($purchase->product?->chassis ?? ''),
            categoryName:   @json($purchase->creditCategory?->name ?? ''),
            categoryId:     @json((int) ($purchase->credit_category_id ?? 0)),
            materialType:   @json($purchase->product?->material_type ?? ''),
            description:    @json($purchase->product?->description ?? ''),
            amount:         @json($purchase->amount ?? 0),
            purchaseDate:   @json($purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d/m/Y') : ($purchase->created_at?->format('d/m/Y') ?? '')),
        };

        function escHtml(s) {
            return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function openDocumentoPrivadoCompra() {
            const d = dpData;

            // Determine MODELO display
            let docModelo = d.modelYear || d.itemModel || '';

            // Determine SERIAL display
            let docSerial = d.serialNumber || '';

            function buildProductTable(data, modelo, serial) {
                const fields = [];
                if (data.productName)   fields.push(['NOMBRE',    data.productName]);
                if (data.brand)         fields.push(['MARCA',     data.brand]);
                const modeloVal = data.categoryId === 4 ? (data.itemModel || '') : modelo;
                if (modeloVal)          fields.push(['MODELO',    modeloVal]);
                if (serial)             fields.push(['SERIAL',    serial]);
                if (data.plate)         fields.push(['PLACA',     data.plate]);
                if (data.chassis)       fields.push(['CHASIS',    data.chassis]);
                if (data.karat)         fields.push(['KILATAJE',  data.karat]);
                if (data.materialType)  fields.push(['MATERIAL',  data.materialType]);
                if (data.description)   fields.push(['DETALLES',  data.description]);
                if (!fields.length) return '';
                while (fields.length % 3 !== 0) fields.push(['', '']);
                const th = 'border:1px solid #006600;background:#f0f7f0;color:#006600;width:33.33%;padding:3px 6px;font-size:8pt;';
                const td = (v) => `border:1px solid #006600;font-size:${v.length > 40 ? '8pt' : '10.5pt'};font-weight:bold;padding:5px 7px;`;
                let rows = `<tr><th colspan="3" style="border:1px solid #006600;background:#e8f5e9;color:#006600;text-align:center;padding:4px 6px;font-size:8.5pt;">DESCRIPCIÓN DEL ARTÍCULO</th></tr>`;
                for (let i = 0; i < fields.length; i += 3) {
                    const chunk = fields.slice(i, i + 3);
                    rows += `<tr>${chunk.map(([lbl]) => `<th style="${th}">${escHtml(lbl)}</th>`).join('')}</tr>`;
                    rows += `<tr>${chunk.map(([, val]) => `<td style="${td(val)}">${escHtml(val)}</td>`).join('')}</tr>`;
                }
                return `<table style="width:100%;border-collapse:collapse;margin-bottom:4px;">${rows}</table>`;
            }

            const amt = new Intl.NumberFormat('es-VE', {minimumFractionDigits:0,maximumFractionDigits:0}).format(parseFloat(d.amount)||0);

            const parts = (d.purchaseDate || '').split('/');
            const day = parts[0] || '___'; const mon = parts[1] || '___'; const yr2 = (parts[2] || '____').slice(-2);

            const uline = (val, w = '120px') => `<span style="border-bottom:1px solid #000;display:inline-block;min-width:${w};padding:0 3px;">${escHtml(val)}</span>`;

            const html = `<!doctype html><html><head><meta charset="utf-8">
<title>Documento Privado de Compra Nº ${escHtml(d.docNum)}</title>
<style>
@page{size:letter portrait;margin:8mm 10mm;}
*{box-sizing:border-box;}
body{font-family:Arial,sans-serif;font-size:9pt;color:#000;margin:0;padding:0;}
table{width:100%;border-collapse:collapse;}
td,th{border:1px solid #006600;padding:3px 6px;vertical-align:middle;}
.g{color:#006600;}.b{font-weight:bold;}.c{text-align:center;}
</style></head><body>

<table style="margin-bottom:4px;">
<tr>
  <td style="width:46%;border:1px solid #006600;padding:6px 8px;vertical-align:top;">
    <div class="g b" style="font-size:11pt;">${escHtml(d.companyName)}</div>
    <div class="g" style="font-size:8pt;">${escHtml(d.companyAddress)}</div>
    <div class="g" style="font-size:8pt;">Cel: ${escHtml(d.branchPhone || d.companyPhone)}</div>
  </td>
  <td style="border:1px solid #006600;padding:6px 8px;text-align:center;vertical-align:middle;">
    <div class="g b" style="font-size:13pt;line-height:1.2;">DOCUMENTO<br>PRIVADO DE COMPRA</div>
    <div style="text-align:right;margin-top:6px;">
      <span class="g b" style="font-size:8.5pt;">Nº&nbsp;</span>
      <span style="color:#cc0000;font-weight:bold;font-size:11pt;border-bottom:1.5px solid #cc0000;">&nbsp;${escHtml(d.docNum)}&nbsp;</span>
    </div>
  </td>
</tr>
</table>

<div style="border:1px solid #006600;padding:4px 7px;margin-bottom:4px;font-size:8.5pt;">
  Conste por el presente Documento Privado, que al solo reconocimiento de firma y Rubricas podra ser elevado a instrumento Público suscrito al tenor de las siguientes:
</div>

<div style="border:1px solid #006600;padding:5px 7px;margin-bottom:4px;font-size:8.5pt;">
  <span class="g b">PRIMERA.-</span> Yo ${uline(d.clientName,'220px')} con C.I. ${uline(d.clientCI,'110px')}<br>
  Domicilio: ${uline(d.clientAddr,'200px')}&nbsp;&nbsp;&nbsp;Telf.: ${uline(d.clientPhone,'110px')}<br>
  En la fecha, declaro ser único y exclusivo propietario de las joyas y/o artículos que describo a continuación
</div>

${buildProductTable(d, docModelo, docSerial)}

<div style="border:1px solid #006600;padding:5px 7px;margin-bottom:4px;font-size:8.5pt;">
  <span class="g b">SEGUNDA.-</span> <span class="b">Transferencia de derechos.-</span> Al presente por así convenir a mis intereses,
  almandato del <span class="b">Art. 105 del Codigo Civil</span>, de mi libre y espontaneo voluntad, sin que medie violencia error ni dolo
  de ninguna naturaleza, que pueda invalidar mi consentimiento, transfiero bajo la modalidad de compra con Pacto de Rescate y
  enajenación perpetua de los bienes especificados en la clausula anterior por el precio libremente convenido y pactado de:
  <div style="text-align:center;font-size:15pt;font-weight:bold;margin:10px 0 4px;border-bottom:1.5px solid #000;padding-bottom:6px;">
    ${amt} Bs.
  </div>
</div>

<div style="border:1px solid #006600;padding:5px 7px;margin-bottom:4px;font-size:8.5pt;">
  <span class="g b">CUARTA.-</span> nosotros <span class="b">${escHtml(d.companyName)}</span> en calidad de comprador y el (la)Señor(a): ${uline(d.clientName,'200px')}
  <br><br>
  Propietario(a) vendedor (a). damos nuestra conformidad con toda y cada una de las clausulas que anteceden por el resultado de lo pactado y por convenir a nuestros intereses.
</div>

<table style="margin-bottom:8px;">
<tr>
  <td style="width:33%;border:1px solid #006600;padding:5px;text-align:center;font-size:8pt;font-weight:bold;color:#006600;">
    PRESTAMOS DE GARRAFAS<br>SOLO POR 1 MES
  </td>
  <td style="width:34%;border:1px solid #006600;padding:5px;text-align:center;font-size:8pt;">
    <span class="b">NOTA - RECUERDE</span><br>SE LE RUEGA TOMAR EN CUENTA<br>
    <span class="b">LA FECHA DE VENCIMIENTO</span><br>No se aceptan reclamos posteriores
  </td>
  <td style="width:33%;border:1px solid #006600;padding:5px;text-align:center;font-size:8pt;font-weight:bold;">
    SEÑOR: CLIENTE<br>EL RECOJO ES PERSONAL<br>CON CARNET IDENTIDAD
  </td>
</tr>
</table>

<div style="font-size:9pt;margin-bottom:18px;">
  Santa Cruz, <span style="border-bottom:1px solid #000;display:inline-block;min-width:22px;text-align:center;">${day}</span> de
  <span style="border-bottom:1px solid #000;display:inline-block;min-width:55px;text-align:center;">${mon}</span> del 20
  <span style="border-bottom:1px solid #000;display:inline-block;min-width:22px;text-align:center;">${yr2}</span>
</div>

<table style="border:none;">
<tr>
  <td style="border:none;width:50%;text-align:center;padding-top:28px;vertical-align:bottom;">
    <div style="border-top:1px solid #000;display:inline-block;width:180px;margin-bottom:3px;"></div><br>
    <span class="b">PROPIETARIO / VENDEDOR</span><br>
    <div style="margin-top:5px;font-size:8.5pt;">C.I. ${uline(d.clientCI,'120px')}</div>
  </td>
  <td style="border:none;width:50%;text-align:center;padding-top:28px;vertical-align:bottom;">
    <div style="border-top:1px solid #000;display:inline-block;width:180px;margin-bottom:3px;"></div><br>
    <span class="b">PRESTAMOS &ldquo;${escHtml(d.companyName)}&rdquo;</span><br>
    <div style="margin-top:5px;font-size:8.5pt;">C.I. ${uline('','120px')}</div>
  </td>
</tr>
</table>

</body></html>`;

            const win = window.open('', '_blank', 'width=820,height=1020');
            if (!win) { alert('Tu navegador bloqueó la ventana emergente. Habilita pop-ups para imprimir.'); return; }
            win.document.open();
            win.document.write(html);
            win.document.close();
            win.focus();
            setTimeout(() => win.print(), 380);
        }

        function numToWordsEs(n) {
            n = Math.round(n);
            if (n === 0) return 'CERO';
            if (n < 0) return 'MENOS ' + numToWordsEs(-n);
            let res = '';
            if (n >= 1000000) {
                const m = Math.floor(n / 1000000);
                res += (m === 1 ? 'UN MILLÓN' : numToWordsEs(m) + ' MILLONES');
                n %= 1000000;
                if (n > 0) res += ' ';
            }
            if (n >= 1000) {
                const t = Math.floor(n / 1000);
                res += (t === 1 ? 'MIL' : numToWordsEs(t) + ' MIL');
                n %= 1000;
                if (n > 0) res += ' ';
            }
            if (n >= 100) {
                const hs = ['','CIENTO','DOSCIENTOS','TRESCIENTOS','CUATROCIENTOS','QUINIENTOS','SEISCIENTOS','SETECIENTOS','OCHOCIENTOS','NOVECIENTOS'];
                if (n === 100) { res += 'CIEN'; n = 0; }
                else { res += hs[Math.floor(n/100)] + ' '; n %= 100; }
            }
            if (n >= 21 && n <= 29) {
                const vt = ['','VEINTIUNO','VEINTIDÓS','VEINTITRÉS','VEINTICUATRO','VEINTICINCO','VEINTISÉIS','VEINTISIETE','VEINTIOCHO','VEINTINUEVE'];
                res += vt[n - 20];
                n = 0;
            } else if (n >= 30) {
                const ts = ['','','','TREINTA','CUARENTA','CINCUENTA','SESENTA','SETENTA','OCHENTA','NOVENTA'];
                const os = ['','UNO','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE'];
                res += ts[Math.floor(n/10)];
                const r = n % 10;
                if (r > 0) res += ' Y ' + os[r];
                n = 0;
            } else if (n > 0) {
                const sm = ['','UNO','DOS','TRES','CUATRO','CINCO','SEIS','SIETE','OCHO','NUEVE','DIEZ','ONCE','DOCE','TRECE','CATORCE','QUINCE','DIECISÉIS','DIECISIETE','DIECIOCHO','DIECINUEVE','VEINTE'];
                res += sm[n];
                n = 0;
            }
            return res.trim();
        }

        function openDocumentoPrivadoV2() {
            const d = dpData;
            const amtNum = parseFloat(d.amount) || 0;
            const amt = new Intl.NumberFormat('es-VE', {minimumFractionDigits:0,maximumFractionDigits:0}).format(amtNum);
            const amtWords = numToWordsEs(amtNum) + ' 00/100 BOLIVIANOS';

            const parts = (d.purchaseDate || '').split('/');
            const day = parts[0] || '___'; const mon = parts[1] || '___'; const yr2 = (parts[2] || '____').slice(-2);

            const uline = (val, w) => `<span style="border-bottom:1px solid #000;display:inline-block;min-width:${w || '120px'};padding:0 3px;">${escHtml(val)}</span>`;

            let docModelo = d.categoryId === 4 ? (d.itemModel || '') : (d.modelYear || d.itemModel || '');
            const prodPairs = [];
            if (d.productName)  prodPairs.push(['Nombre', d.productName]);
            if (d.brand)        prodPairs.push(['Marca', d.brand]);
            if (docModelo)      prodPairs.push(['Modelo', docModelo]);
            if (d.serialNumber) prodPairs.push(['Serial', d.serialNumber]);
            if (d.plate)        prodPairs.push(['Placa', d.plate]);
            if (d.chassis)      prodPairs.push(['Chasis', d.chassis]);
            if (d.karat)        prodPairs.push(['Kilataje', d.karat]);
            if (d.materialType) prodPairs.push(['Material', d.materialType]);
            if (d.description)  prodPairs.push(['Detalles', d.description]);
            const prodDetail = prodPairs.map(([lbl, val]) =>
                `<span style="background:#f0f0f0;border-radius:3px;padding:1px 6px;margin:2px 4px 2px 0;display:inline-block;font-size:8.5pt;"><span style="color:#555;font-size:7.5pt;margin-right:3px;">${escHtml(lbl)}:</span><strong>${escHtml(val)}</strong></span>`
            ).join('');
            const prodBlock = prodPairs.length ? `<div style="margin:6px 0 4px;">${prodDetail}</div>` : '';

            const branchName = @json($purchase->branch?->name ?? '');

            const html = `<!doctype html><html><head><meta charset="utf-8">
<title></title>
<style>
@page{size:letter portrait;margin:14mm 16mm;}
*{box-sizing:border-box;}
body{font-family:'Times New Roman',serif;font-size:10pt;color:#000;margin:0;padding:0;text-align:justify;}
p{margin:0 0 10px;line-height:1.55;}
.cl{margin-bottom:10px;line-height:1.55;}
</style></head><body>

<div style="display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:14px;">
  <div style="font-family:Arial,sans-serif;">
    <div style="font-size:12pt;font-weight:bold;">${escHtml(d.companyName)}</div>
    <div style="font-size:8.5pt;color:#444;">${escHtml(branchName)}${(branchName && (d.branchPhone || d.companyPhone)) ? ' &nbsp;·&nbsp; ' : ''}Tel: ${escHtml(d.branchPhone || d.companyPhone)}</div>
  </div>
  <div style="font-family:Arial,sans-serif;font-size:9pt;">Fecha: <strong>${escHtml(d.purchaseDate)}</strong></div>
</div>

<p style="text-align:center;font-size:12pt;font-weight:bold;text-decoration:underline;margin-bottom:12px;font-family:Arial,sans-serif;">
  DOCUMENTO PRIVADO DE COMPRA VENTA
</p>

<p>
  Conste por el presente <u>documento privado</u> de compra venta, que a solo reconocimiento de sus firmas y rúbricas surtirá los efectos de documento público bajo el tenor de las siguientes cláusulas y condiciones que se detallan a continuación:
</p>

<div class="cl">
  <u><strong>PRIMERA.- (DE LAS PARTES)</strong></u> Yo, <strong>${escHtml(d.clientName)}</strong>,
  con C.I. Nº ${uline(d.clientCI,'90px')}, con domicilio real en ${uline(d.clientAddr,'200px')},
  Telf. ${uline(d.clientPhone,'90px')}, en plena capacidad de ejercicio de mis derechos civiles,
  a la presente declaro ser propietario <u>legítimo</u> del siguiente bien:
  ${prodBlock}
  a la fecha, por así convenir a mis intereses, doy en calidad de venta real enajenación perpetua
  a <strong>${escHtml(d.companyName)}</strong>.
</div>

<div class="cl">
  <u><strong>SEGUNDA.- (DEL PRECIO)</strong></u> El precio libremente convenido entre partes es en la
  suma de <strong>{{ $currencySymbol }} ${amt} (${amtWords})</strong>, monto que se hace efectivo al vendedor al momento
  de suscribir el presente documento privado ………………………………………………………………
</div>

<div class="cl">
  <u><strong>TERCERA.- (DE LA EVICCIÓN Y SANEAMIENTO)</strong></u> Se deja en constancia que el bien objeto
  de la presente transferencia no reconoce gravamen o hipoteca alguna siendo libre, alodial, sin que
  medie violencia, error ni dolo de ninguna naturaleza que pueda invalidar el consentimiento, en términos del
  <strong>Art. 641 del Código Civil</strong>. <strong>El vendedor puede recuperar el derecho propietario
  del bien en el término máximo de 30 Días, plazo a partir de la fecha de suscripción del presente contrato.</strong>
</div>

<div class="cl">
  <u><strong>CUARTA.- (CONFORMIDAD)</strong></u> Nosotros, <strong>${escHtml(d.companyName)}</strong> en calidad de comprador
  y <strong>${escHtml(d.clientName)}</strong> en calidad de propietario(a) vendedor(a), damos nuestra
  total conformidad con toda y cada una de las cláusulas que anteceden, por el resultado de lo pactado
  y por convenir a nuestros intereses.
</div>

<p style="margin-top:14px;">
  Santa Cruz,
  <span style="border-bottom:1px solid #000;display:inline-block;min-width:22px;text-align:center;">${day}</span> de
  <span style="border-bottom:1px solid #000;display:inline-block;min-width:70px;text-align:center;">${mon}</span> del 20
  <span style="border-bottom:1px solid #000;display:inline-block;min-width:22px;text-align:center;">${yr2}</span>
</p>

<table style="border:none;width:100%;margin-top:30px;">
<tr>
  <td style="border:none;width:50%;text-align:center;vertical-align:bottom;padding:0 10px;">
    <div style="border-top:1px solid #000;display:inline-block;width:180px;margin-bottom:4px;"></div><br>
    <strong>PROPIETARIO / VENDEDOR</strong><br>
    <span style="font-size:8.5pt;">C.I. ${uline(d.clientCI,'120px')}</span>
  </td>
  <td style="border:none;width:50%;text-align:center;vertical-align:bottom;padding:0 10px;">
    <div style="border-top:1px solid #000;display:inline-block;width:180px;margin-bottom:4px;"></div><br>
    <strong>${escHtml(d.companyName)}</strong><br>
    <span style="font-size:8.5pt;">COMPRADOR</span>
  </td>
</tr>
</table>

</body></html>`;

            const win = window.open('', '_blank', 'width=820,height=1020');
            if (!win) { alert('Tu navegador bloqueó la ventana emergente. Habilita pop-ups para imprimir.'); return; }
            win.document.open();
            win.document.write(html);
            win.document.close();
            win.document.title = '';
            try { win.history.replaceState({}, '', ' '); } catch(e) {}
            win.focus();
            setTimeout(() => win.print(), 380);
        }

        document.getElementById('btn-doc-privado')?.addEventListener('click', openDocumentoPrivadoCompra);
        document.getElementById('btn-doc-privado-v2')?.addEventListener('click', openDocumentoPrivadoV2);
    })();

    /* ── Template-based print ───────────────────── */
    (function () {
        const templateCatalog = @json($purchasePrintTemplateCatalog ?? []);
        const defaultTemplateId = @json($defaultPrintTemplateId);
        const shouldAutoPrint = @json((bool) request()->boolean('auto_print'));
        const autoPrintTemplateId = @json(request()->integer('template_id'));
        const $printButton = $('#btn-print-purchase');

        function findTemplate(templateId) {
            if (!templateCatalog.length) return null;
            const id = String(templateId ?? '');
            return templateCatalog.find((template) => String(template.id) === id) || null;
        }

        function fallbackReceiptHtml() {
            return `
                <div style="text-align:center; font-family:'Courier New', monospace; font-size:10px; color:#000;">
                    <strong style="font-size:13px;">{{ e($purchase->company?->name ?? '') }}</strong><br>
                    <span>{{ e($purchase->branch?->name ?? '') }}</span><br>
                    @if($purchase->branch?->phone)<span style="font-size:9px;">{{ e($purchase->branch->phone) }}</span><br>@endif
                    <hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
                    <strong>COMPROBANTE DE COMPRA</strong><br>
                    <span>{{ $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d/m/Y') : ($purchase->created_at?->format('d/m/Y') ?? '') }}</span>
                    <hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
                </div>
                <table style="width:100%; border-collapse:collapse; font-family:'Courier New', monospace; font-size:10px; color:#000;">
                    <tr><td style="padding:2px 0;"><strong>Compra:</strong></td><td style="padding:2px 0; text-align:right;"><strong>CO-{{ str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT) }}</strong></td></tr>
                    <tr><td style="padding:2px 0;"><strong>Cliente:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{ e($purchase->client?->name ?? '') }}</strong></td></tr>
                    <tr><td style="padding:2px 0;"><strong>Producto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{ e($purchase->product?->name ?? '') }}</strong></td></tr>
                    <tr><td style="padding:2px 0;"><strong>Monto:</strong></td><td style="padding:2px 0; text-align:right;"><strong>{{ $currencySymbol }} {{ number_format((float) $purchase->amount, 2) }}</strong></td></tr>
                </table>
                <hr style="border:none; border-top:1px dashed #000; margin:6px 0;">
                <div style="text-align:center; font-family:'Courier New', monospace; font-size:9px; color:#000;">
                    Gracias por su preferencia.<br>
                    Conserve este comprobante.
                </div>
            `;
        }

        function consumePendingAutoPrintFlag() {
            try {
                const pending = window.sessionStorage?.getItem('purchase_auto_print_pending') === '1';
                if (pending) {
                    window.sessionStorage?.removeItem('purchase_auto_print_pending');
                }
                return pending;
            } catch (error) {
                return false;
            }
        }

        function openPrintWindowFromHtml(rawHtml, paperSize, targetWindowName = null) {
            const paper = paperSize || 'pequeno';
            let pageCss = 'size: letter portrait;';
            let pageMargin = '6mm';
            let bodyWidth = '210mm';
            if (paper === 'mediano') {
                pageCss = 'size: 215.9mm 139.7mm;';
                bodyWidth = '215.9mm';
            }
            if (paper === 'pequeno') {
                pageCss = 'size: 80mm auto;';
                pageMargin = '4mm 2mm';
                bodyWidth = '76mm';
            }

            const printWindow = targetWindowName
                ? window.open('', targetWindowName)
                : window.open('', '_blank', 'width=820,height=1020');
            if (!printWindow) {
                alert('Tu navegador bloqueó la ventana de impresión. Habilita pop-ups para continuar.');
                return;
            }

            const html = `
                <!doctype html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <title>Comprobante Compra CO-{{ str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT) }}</title>
                    <style>
                        @page { ${pageCss} margin: ${pageMargin}; }
                        html, body {
                            margin: 0;
                            padding: 0;
                            background: #fff;
                            color: #000;
                            font-family: Arial, sans-serif;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        body { width: ${bodyWidth}; margin: 0 auto; }
                        table { width: 100%; border-collapse: collapse; }
                        strong, b { font-weight: 700 !important; color: #000 !important; }
                        * { color: #000 !important; }
                    </style>
                </head>
                <body>${rawHtml}</body>
                </html>
            `;

            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
            }, 220);
        }

        function printTicket() {
            const template = findTemplate(12) || findTemplate(defaultTemplateId);
            const html = (template && template.html) ? template.html : fallbackReceiptHtml();
            const paperSize = (template && template.paper_size) ? template.paper_size : 'pequeno';
            openPrintWindowFromHtml(html, paperSize);
        }

        if ($printButton.length) {
            $printButton.on('click', printTicket);
        }

        if (shouldAutoPrint) {
            const template = findTemplate(12) || findTemplate(defaultTemplateId);
            if (template) {
                const usePreOpenedWindow = consumePendingAutoPrintFlag();
                setTimeout(() => {
                    openPrintWindowFromHtml(
                        template.html,
                        template.paper_size || 'pequeno',
                        usePreOpenedWindow ? 'purchase_auto_print_window' : null
                    );
                }, 300);
            }
        }
    })();
</script>
@endpush
