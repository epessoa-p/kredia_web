{{--
    Formulario unificado de catálogo (crear y editar).
    Variables: $company, $catalog (null al crear), $companies (solo super admin al crear),
               $branches, $lockedBranchId, $products (pool), $categories.
--}}
@php
    $isEdit = (bool) $catalog;
    $user = auth()->user();
    $isSuperAdmin = (bool) $user->is_super_admin;

    $formAction = $isEdit ? route('catalogs.update', $catalog) : route('catalogs.store');
    $cancelUrl = $isEdit ? route('catalogs.show', $catalog) : route('catalogs.index');

    // Productos ya elegidos: los del POST fallido, o los del catálogo al editar.
    $oldProducts = old('products');
    if (is_array($oldProducts)) {
        $initialSelected = collect($oldProducts)->map(fn ($p) => [
            'product_id' => (int) ($p['product_id'] ?? 0),
            'custom_name' => $p['custom_name'] ?? '',
            'custom_description' => $p['custom_description'] ?? '',
            'custom_price' => $p['custom_price'] ?? '',
        ])->values()->all();
    } elseif ($isEdit) {
        $initialSelected = $catalog->items->map(fn ($item) => [
            'product_id' => (int) $item->product_id,
            'custom_name' => $item->custom_name ?? '',
            'custom_description' => $item->custom_description ?? '',
            'custom_price' => $item->custom_price !== null ? (string) (float) $item->custom_price : '',
        ])->values()->all();
    } else {
        $initialSelected = [];
    }

    $initialBranchId = $lockedBranchId ?: old('branch_id', $catalog?->branch_id);
    $initialBranchId = $initialBranchId ? (int) $initialBranchId : null;

    $showPrices = (bool) old('show_prices', $isEdit ? $catalog->show_prices : true);
    $showContact = (bool) old('show_contact', $isEdit ? $catalog->show_contact : true);
    $coverColor = old('cover_color', $catalog?->cover_color ?? '#0d6efd');

    $cfConfig = [
        'mode' => $isEdit ? 'edit' : 'create',
        'products' => $products,
        'branches' => $branches->map(fn ($b) => [
            'id' => (int) $b->id, 'name' => $b->name, 'phone' => $b->phone, 'email' => $b->email,
            'address' => $b->address, 'color' => $b->color ?: '#6c757d',
        ])->values(),
        'selected' => $initialSelected,
        'lockedBranchId' => $lockedBranchId,
        'logoUrl' => $catalog?->logo_path ? asset('storage/' . $catalog->logo_path) : '',
        'companyName' => $company?->name ?? '',
        'createUrl' => route('catalogs.create'),
    ];
@endphp

@push('styles')
<style>
    .cf-page { padding-bottom: 84px; }
    .cf-card { border: 0; border-radius: 14px; box-shadow: 0 2px 10px rgba(16,24,40,.06); position: relative; overflow: hidden; }
    .cf-card > .card-header { background: #fff; border-bottom: 1px solid #eef1f5; border-radius: 14px 14px 0 0; font-weight: 600; font-size: var(--fs-base); }

    /* Color por tarjeta: franja izquierda + tinte suave en el encabezado.
       Siempre el mismo color para la misma función, para reconocerla de un vistazo. */
    .cf-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px; background: var(--cf-accent, #cbd5e1); }
    .cf-card > .card-header { background: linear-gradient(90deg, var(--cf-tint, #f8fafc), #fff 70%); }
    .cf-card > .card-header i:first-child, .cf-card .cf-card-icon { color: var(--cf-accent); }
    .cf-card--scope    { --cf-accent: #8b5cf6; --cf-tint: #f5f3ff; } /* violeta: empresa y sucursal */
    .cf-card--config   { --cf-accent: #0ea5e9; --cf-tint: #f0f9ff; } /* celeste: configuración */
    .cf-card--products { --cf-accent: #f87171; --cf-tint: #fef2f2; } /* rojo claro: productos */
    .cf-card--scope .card-body { background: linear-gradient(90deg, #faf8ff, #fff 45%); }

    /* Zonas dentro de la tarjeta de productos */
    .cf-panel { border-radius: 12px; padding: 12px 12px 12px 15px; position: relative; overflow: hidden; }
    .cf-panel::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--cf-accent); }
    .cf-panel .cf-section-label { color: var(--cf-accent-text); }
    .cf-panel--pool     { --cf-accent: #f87171; --cf-accent-text: #dc2626; background: #fff7f7; border: 1px solid #fde2e2; } /* para la venta */
    .cf-panel--selected { --cf-accent: #34d399; --cf-accent-text: #059669; background: #f3fdf8; border: 1px solid #d1fae5; } /* en el catálogo */
    .cf-panel--preview  { --cf-accent: #fbbf24; --cf-accent-text: #d97706; background: #fffbeb; border: 1px solid #fde68a; } /* vista previa */
    .cf-panel .cf-tile, .cf-panel .cf-sel { background: #fff; }
    .cf-card--products .nav-link { color: #6b7280; }
    .cf-card--products .nav-link.cf-pill--products.active { background: #f87171; color: #fff; }
    .cf-card--products .nav-link.cf-pill--preview.active  { background: #f59e0b; color: #fff; }
    .cf-section-label { font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #8a94a6; margin-bottom: .5rem; }
    .cf-page .form-label { font-size: var(--fs-sm); font-weight: 600; margin-bottom: .25rem; color: #374151; }

    /* Empresa / sucursal */
    .cf-scope { display: flex; flex-wrap: wrap; gap: 12px 24px; align-items: flex-end; }
    .cf-scope .cf-scope-item { min-width: 220px; flex: 1 1 220px; max-width: 360px; }
    .cf-fixed-value { display: flex; align-items: center; gap: 6px; padding: .3rem .6rem; background: #f6f8fb; border: 1px solid #e6ebf2; border-radius: 8px; font-size: var(--fs-base); font-weight: 600; min-height: 31px; }
    .cf-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; flex-shrink: 0; }

    /* Filtros */
    .cf-filters { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 8px; }
    .cf-filters.has-branch { grid-template-columns: 2fr 1fr 1fr 1fr; }
    @media (max-width: 767px) { .cf-filters, .cf-filters.has-branch { grid-template-columns: 1fr 1fr; } .cf-filters .cf-search { grid-column: 1 / -1; } }
    .cf-filter-bar { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-top: 8px; font-size: var(--fs-xs); color: #6b7280; }

    /* Grilla de productos */
    .cf-pool { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; max-height: 430px; overflow-y: auto; padding: 2px 4px 4px 2px; }
    .cf-tile { position: relative; border: 1px solid #e6ebf2; border-radius: 10px; background: #fff; overflow: hidden; cursor: pointer; transition: box-shadow .15s, border-color .15s, transform .15s; display: flex; flex-direction: column; }
    .cf-tile:hover { box-shadow: 0 4px 14px rgba(16,24,40,.10); transform: translateY(-1px); }
    .cf-tile.is-added { border-color: #16a34a; box-shadow: 0 0 0 2px rgba(22,163,74,.15); }
    .cf-tile-img { height: 92px; background: #f3f5f8; display: flex; align-items: center; justify-content: center; color: #b8c0cc; font-size: var(--fs-xl); }
    .cf-tile-img img { width: 100%; height: 100%; object-fit: cover; }
    .cf-tile-body { padding: 6px 8px 8px; display: flex; flex-direction: column; gap: 3px; flex: 1; }
    .cf-tile-name { font-size: var(--fs-xs); font-weight: 700; color: #1f2937; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .cf-tile-price { font-size: var(--fs-xs); font-weight: 700; color: #0d6efd; }
    .cf-tile-meta { display: flex; flex-wrap: wrap; gap: 3px; align-items: center; margin-top: auto; }
    .cf-tile-add { position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; border-radius: 50%; border: 0; background: rgba(255,255,255,.95); color: #0d6efd; box-shadow: 0 1px 4px rgba(0,0,0,.18); display: flex; align-items: center; justify-content: center; font-size: var(--fs-base); }
    .cf-tile.is-added .cf-tile-add { background: #16a34a; color: #fff; }
    .cf-empty { grid-column: 1 / -1; text-align: center; color: #98a2b3; font-size: var(--fs-base); padding: 28px 8px; }

    /* Etiquetas */
    .cf-badge { display: inline-flex; align-items: center; gap: 3px; font-size: var(--fs-xs); font-weight: 600; line-height: 1; padding: 3px 6px; border-radius: 999px; border: 1px solid transparent; white-space: nowrap; }
    .cf-badge--loan { background: #eef2ff; color: #3730a3; border-color: #c7d2fe; }
    .cf-badge--purchase { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
    .cf-badge--other { background: #f3f4f6; color: #6b7280; border-color: #e5e7eb; }
    .cf-badge--branch { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
    .cf-badge--warn { background: #fff7ed; color: #b45309; border-color: #fcd34d; }
    .cf-badge--danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }

    /* Seleccionados */
    .cf-selected { display: flex; flex-direction: column; gap: 6px; min-height: 40px; }
    .cf-sel { display: flex; gap: 8px; align-items: flex-start; border: 1px solid #e6ebf2; border-radius: 10px; padding: 6px 8px; background: #fff; }
    .cf-sel.sortable-ghost { opacity: .4; background: #eef2ff; }
    .cf-sel.sortable-chosen { box-shadow: 0 6px 18px rgba(16,24,40,.15); }
    .cf-handle { cursor: grab; color: #b0b8c4; padding: 6px 0; font-size: var(--fs-md); }
    .cf-handle:active { cursor: grabbing; }
    .cf-sel-num { width: 22px; height: 22px; border-radius: 50%; background: #eef2ff; color: #3730a3; font-size: var(--fs-xs); font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 4px; }
    .cf-sel-img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: #f3f5f8; display: flex; align-items: center; justify-content: center; color: #b8c0cc; flex-shrink: 0; }
    .cf-sel-body { flex: 1; min-width: 0; }
    .cf-sel-name { font-size: var(--fs-sm); font-weight: 700; color: #1f2937; }
    .cf-sel-sub { font-size: var(--fs-xs); color: #6b7280; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; margin-top: 2px; }
    .cf-sel-custom { margin-top: 6px; display: grid; grid-template-columns: 1fr 120px; gap: 6px; }
    .cf-sel-custom textarea { grid-column: 1 / -1; }
    .cf-sel-actions { display: flex; gap: 4px; flex-shrink: 0; }
    .cf-sel-actions .btn { padding: .15rem .45rem; font-size: var(--fs-xs); }
    .cf-price-old { text-decoration: line-through; color: #9ca3af; }

    /* Vista previa (réplica reducida del enlace público) */
    .cf-preview { border: 1px solid #e6ebf2; border-radius: 12px; overflow: hidden; background: #f5f6f8; max-height: 640px; overflow-y: auto; }
    .cf-preview-hero { color: #fff; text-align: center; padding: 22px 14px; }
    .cf-preview-hero img { max-height: 44px; border-radius: 6px; margin-bottom: 8px; }
    .cf-preview-hero h5 { font-weight: 800; margin: 0; text-shadow: 0 2px 8px rgba(0,0,0,.2); }
    .cf-preview-hero p { font-size: var(--fs-sm); opacity: .9; margin: 4px auto 0; max-width: 420px; }
    .cf-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; padding: 14px; }
    .cf-preview-card { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .cf-preview-card .ph { height: 90px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #ccc; font-size: var(--fs-xl); }
    .cf-preview-card img { width: 100%; height: 90px; object-fit: cover; }
    .cf-preview-card .b { padding: 8px; }
    .cf-preview-card .n { font-size: var(--fs-xs); font-weight: 700; }
    .cf-preview-card .d { font-size: var(--fs-xs); color: #6b7280; margin-top: 2px; }
    .cf-preview-card .p { font-size: var(--fs-sm); font-weight: 700; margin-top: 4px; }
    .cf-preview-contact { background: #fff; text-align: center; padding: 14px; font-size: var(--fs-sm); border-top: 3px solid; }
    .cf-preview-contact a, .cf-preview-contact span { font-weight: 600; margin: 0 8px; text-decoration: none; }
    .cf-preview-foot { text-align: center; color: #999; font-size: var(--fs-xs); padding: 10px; }

    /* Barra inferior fija */
    .cf-footer { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; background: rgba(255,255,255,.97); border-top: 1px solid #e6ebf2; box-shadow: 0 -4px 16px rgba(16,24,40,.06); padding: 10px 20px; display: flex; justify-content: flex-end; align-items: center; gap: 10px; }
    @media (min-width: 992px) { .cf-footer { left: 250px; } }
    .cf-footer-info { margin-right: auto; font-size: var(--fs-sm); color: #4b5563; }
</style>
@endpush

<div class="cf-page">
    <x-page-header :title="$isEdit ? 'Editar catálogo' : 'Nuevo catálogo'" :icon="$isEdit ? 'pencil' : 'megaphone'" :back="$cancelUrl"
        subtitle="Elige la sucursal, arma la lista de productos y revisa la vista previa antes de guardar." />

    @if($errors->any())
        <div class="alert alert-danger py-2 small">
            <i class="bi bi-exclamation-triangle me-1"></i> Revisa los datos:
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ $formAction }}" method="POST" enctype="multipart/form-data" id="catalogForm" novalidate>
        @csrf
        @if($isEdit) @method('PUT') @endif

        {{-- 1. Empresa y sucursal --}}
        <div class="card cf-card cf-card--scope mb-3">
            <div class="card-body py-3">
                <div class="cf-scope">
                    {{-- Empresa: solo el super admin la elige (al crear) --}}
                    @if($isSuperAdmin && !$isEdit)
                        <div class="cf-scope-item">
                            <label class="form-label" for="cfCompany">Empresa <span class="text-danger">*</span></label>
                            <select name="company_id" id="cfCompany" class="form-select form-select-sm @error('company_id') is-invalid @enderror">
                                <option value="">Selecciona una empresa…</option>
                                @foreach($companies as $c)
                                    <option value="{{ $c->id }}" @selected($company && $company->id === $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($company)
                        <div class="cf-scope-item">
                            <label class="form-label">Empresa</label>
                            <div class="cf-fixed-value"><i class="bi bi-building text-muted"></i> {{ $company->name }}</div>
                        </div>
                    @endif

                    @if($company)
                        <div class="cf-scope-item">
                            <label class="form-label" for="cfBranch">Sucursal</label>
                            @if($lockedBranchId)
                                @php $lockedBranch = $branches->firstWhere('id', $lockedBranchId); @endphp
                                <input type="hidden" name="branch_id" id="cfBranch" value="{{ $lockedBranchId }}">
                                <div class="cf-fixed-value">
                                    <span class="cf-dot" style="background:{{ $lockedBranch?->color ?? '#6c757d' }}"></span>
                                    {{ $lockedBranch?->name ?? 'Tu sucursal' }}
                                    <span class="text-muted fw-normal ms-auto fs-xs">tu sucursal</span>
                                </div>
                            @else
                                <select name="branch_id" id="cfBranch" class="form-select form-select-sm">
                                    <option value="">Todas las sucursales</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}" @selected($initialBranchId === (int) $b->id)>{{ $b->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>
                        <div class="text-muted fs-xs" style="flex: 2 1 260px;">
                            <i class="bi bi-info-circle"></i>
                            Con una sucursal, solo se listan sus productos y el contacto se completa con sus datos.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if(!$company)
            <div class="card cf-card cf-card--scope">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-building d-block mb-2 fs-xl" style="opacity:.4"></i>
                    Elige la empresa del catálogo para ver sus sucursales y productos.
                </div>
            </div>
        @else
            <div class="row g-3">
                {{-- 2. Configuración --}}
                <div class="col-xl-4 col-lg-5">
                    <div class="card cf-card cf-card--config">
                        <div class="card-header"><i class="bi bi-sliders"></i> Configuración</div>
                        <div class="card-body">
                            <div class="mb-2">
                                <label class="form-label" for="cfTitle">Título <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="cfTitle" maxlength="255" value="{{ old('title', $catalog?->title) }}"
                                       class="form-control form-control-sm @error('title') is-invalid @enderror" placeholder="Ej. Ofertas de octubre" required>
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-2">
                                <label class="form-label" for="cfDescription">Descripción</label>
                                <textarea name="description" id="cfDescription" rows="2" maxlength="2000" class="form-control form-control-sm"
                                          placeholder="Texto que verá el cliente debajo del título">{{ old('description', $catalog?->description) }}</textarea>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-5">
                                    <label class="form-label" for="cfColor">Color</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="color" name="cover_color" id="cfColor" value="{{ $coverColor }}" class="form-control form-control-color form-control-sm" style="width:42px;height:31px">
                                        <span class="text-muted fs-xs" id="cfColorHex">{{ $coverColor }}</span>
                                    </div>
                                </div>
                                <div class="col-7">
                                    <label class="form-label" for="cfLogo">Logo</label>
                                    <input type="file" name="logo" id="cfLogo" accept="image/*" class="form-control form-control-sm @error('logo') is-invalid @enderror">
                                    @if($catalog?->logo_path)
                                        <div class="form-text fs-xs">Si no eliges otro, se mantiene el actual.</div>
                                    @endif
                                </div>
                            </div>

                            <div class="d-flex gap-3 mb-2">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="show_prices" value="0">
                                    <input class="form-check-input" type="checkbox" id="cfShowPrices" name="show_prices" value="1" @checked($showPrices)>
                                    <label class="form-check-label small" for="cfShowPrices">Mostrar precios</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="show_contact" value="0">
                                    <input class="form-check-input" type="checkbox" id="cfShowContact" name="show_contact" value="1" @checked($showContact)>
                                    <label class="form-check-label small" for="cfShowContact">Mostrar contacto</label>
                                </div>
                            </div>

                            <div id="cfContactFields" class="{{ $showContact ? '' : 'd-none' }} p-2 mb-2" style="background:#f8fafc;border:1px solid #eef1f5;border-radius:10px;">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="cf-section-label mb-0">Contacto</span>
                                    <x-btn variant="ghost" icon="arrow-repeat" type="button" id="cfUseBranchContact" class="p-0 text-decoration-none d-none fs-xs">Usar datos de la sucursal</x-btn>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="form-label" for="cfPhone">Teléfono</label>
                                        <input type="text" name="contact_phone" id="cfPhone" maxlength="30" value="{{ old('contact_phone', $catalog?->contact_phone) }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="cfWhatsapp">WhatsApp</label>
                                        <input type="text" name="contact_whatsapp" id="cfWhatsapp" maxlength="30" value="{{ old('contact_whatsapp', $catalog?->contact_whatsapp) }}" class="form-control form-control-sm">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="cfEmail">Email</label>
                                        <input type="email" name="contact_email" id="cfEmail" maxlength="255" value="{{ old('contact_email', $catalog?->contact_email) }}" class="form-control form-control-sm @error('contact_email') is-invalid @enderror">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="form-label" for="cfExpires">Disponible hasta <span class="text-muted fw-normal">(opcional)</span></label>
                                <input type="datetime-local" name="expires_at" id="cfExpires"
                                       value="{{ old('expires_at', $catalog?->expires_at?->format('Y-m-d\TH:i')) }}"
                                       class="form-control form-control-sm @error('expires_at') is-invalid @enderror">
                                <div class="form-text fs-xs">Vacío = el enlace no vence.</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. Productos y vista previa --}}
                <div class="col-xl-8 col-lg-7">
                    <div class="card cf-card cf-card--products">
                        <div class="card-header d-flex align-items-center gap-2 flex-wrap">
                            <ul class="nav nav-pills nav-sm fs-sm" role="tablist">
                                <li class="nav-item">
                                    <button type="button" class="nav-link active py-1 px-3 cf-pill--products" data-bs-toggle="pill" data-bs-target="#cfTabProducts" role="tab">
                                        <i class="bi bi-grid-3x3-gap"></i> Productos
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button type="button" class="nav-link py-1 px-3 cf-pill--preview" data-bs-toggle="pill" data-bs-target="#cfTabPreview" role="tab" id="cfPreviewTabBtn">
                                        <i class="bi bi-eye"></i> Vista previa
                                    </button>
                                </li>
                            </ul>
                            <span class="ms-auto text-muted fs-xs">
                                <span id="cfSelectedCountTop">0</span> en el catálogo
                            </span>
                        </div>
                        <div class="card-body tab-content">
                            {{-- Pestaña: productos --}}
                            <div class="tab-pane fade show active" id="cfTabProducts" role="tabpanel">
                                <div class="cf-panel cf-panel--pool">
                                <div class="cf-section-label"><i class="bi bi-shop"></i> Productos disponibles para la venta</div>
                                <div class="cf-filters" id="cfFilters">
                                    <div class="input-group input-group-sm cf-search">
                                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                        <input type="text" id="cfSearch" class="form-control no-upper" placeholder="Nombre o código (ej. PR-00523)">
                                    </div>
                                    <select id="cfCategory" class="form-select form-select-sm">
                                        <option value="">Todas las categorías</option>
                                        @foreach($categories as $catId => $catName)
                                            <option value="{{ $catId }}">{{ $catName }}</option>
                                        @endforeach
                                    </select>
                                    <select id="cfOrigin" class="form-select form-select-sm">
                                        <option value="">Todo origen</option>
                                        <option value="loan">De préstamo</option>
                                        <option value="purchase">De compra</option>
                                        <option value="other">Otros</option>
                                    </select>
                                    <select id="cfBranchFilter" class="form-select form-select-sm d-none">
                                        <option value="">Todas las sucursales</option>
                                        @foreach($branches as $b)
                                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="cf-filter-bar">
                                    <span id="cfPoolCount">—</span>
                                    <x-btn variant="outline-primary" icon="plus-circle" id="cfAddAll">
                                        Agregar todos (<span id="cfAddAllCount">0</span>)
                                    </x-btn>
                                </div>
                                <div class="cf-pool mt-2" id="cfPool"></div>
                                </div>

                                <div class="cf-panel cf-panel--selected mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="cf-section-label mb-0">
                                        <i class="bi bi-check2-square"></i> Productos en el catálogo · <span id="cfSelectedCount">0</span>
                                        <span class="text-muted fw-normal text-lowercase" style="letter-spacing:0">— arrastra <i class="bi bi-grip-vertical"></i> para ordenar</span>
                                    </div>
                                    <x-btn variant="ghost" icon="trash" type="button" id="cfRemoveAll" class="text-danger p-0 text-decoration-none fs-xs">Quitar todos</x-btn>
                                </div>
                                <div id="cfBranchWarning" class="alert alert-warning py-1 px-2 small d-none mb-2"></div>
                                <div class="cf-selected" id="cfSelected"></div>
                                <div class="text-center text-muted py-3 small" id="cfSelectedEmpty">
                                    <i class="bi bi-hand-index"></i> Toca un producto de arriba para agregarlo al catálogo.
                                </div>
                                </div>
                            </div>

                            {{-- Pestaña: vista previa --}}
                            <div class="tab-pane fade" id="cfTabPreview" role="tabpanel">
                                <div class="cf-panel cf-panel--preview">
                                    <div class="cf-section-label"><i class="bi bi-eye"></i> Vista previa · así verá el cliente el enlace del catálogo</div>
                                    <div class="cf-preview" id="cfPreview"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cf-footer">
                <div class="cf-footer-info">
                    <i class="bi bi-box-seam"></i> <strong id="cfFooterCount">0</strong> producto(s) en el catálogo
                    <span id="cfFooterBranch" class="ms-2"></span>
                </div>
                <x-btn variant="secondary" :href="$cancelUrl">Cancelar</x-btn>
                <x-btn icon="check-lg" type="submit" id="cfSubmit">{{ $isEdit ? 'Guardar cambios' : 'Crear catálogo' }}</x-btn>
            </div>
        @endif
    </form>
</div>

@include('catalogs.partials.form-scripts', ['cfConfig' => $cfConfig])
