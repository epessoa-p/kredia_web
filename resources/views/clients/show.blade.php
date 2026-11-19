@extends('layouts.app')

@section('title', $client->name . ' - Detalle del Cliente')

@section('page')
@php
    $totalBorrowed  = (float) ($client->total_borrowed ?? 0);
    $totalPaid      = (float) ($client->total_paid_sum ?? 0);
    $totalPending   = $loans->filter(fn($l) => !in_array($l->status, [\App\Models\Loan::STATUS_FINISHED, \App\Models\Loan::STATUS_CANCELLED]))
                            ->sum(fn($l) => (float) $l->current_capital);
    $totalPurchased = (float) ($client->total_purchased ?? 0);
    $totalSold     = (float) ($client->total_sold ?? 0);

    $saleStatusColors = ['completed' => 'success', 'cancelled' => 'dark'];
    $saleStatusLabels = ['completed' => 'Completada', 'cancelled' => 'Cancelada'];

    $statusColors = \App\Models\Loan::STATUS_COLORS;
    $statusLabels = \App\Models\Loan::STATUS_LABELS;
    $purchaseStatusColors = ['completed' => 'success', 'cancelled' => 'dark'];
    $purchaseStatusLabels = ['completed' => 'Completada', 'cancelled' => 'Cancelada'];

    $canDeleteSale = auth()->user()->is_super_admin
        || auth()->user()->hasPermissionInCompany('sales.delete', auth()->user()->getCurrentCompany());
@endphp

{{-- Header --}}
<x-page-header :title="$client->name" icon="person-fill">
    <x-slot:meta>
        @include('clients.partials.rating-badge', ['rating' => $clientRating->rating, 'tag' => $client->tag, 'tagColor' => $clientTags->firstWhere('name', $client->tag)?->color, 'size' => 'lg'])
    </x-slot:meta>
        @if($canTag)
            {{-- Etiqueta rápida (permiso "Etiquetar clientes") --}}
            <form method="POST" action="{{ route('clients.tag.update', $client) }}" class="cs-tag-form" title="Etiqueta del cliente">
                @csrf
                @method('PATCH')
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white"><i class="bi bi-tag-fill text-primary"></i></span>
                    <select name="tag" class="form-select form-select-sm" aria-label="Etiqueta del cliente"
                            @disabled($clientTags->isEmpty() && !$client->tag)
                            onchange="this.form.querySelector('.cs-tag-spinner').classList.remove('d-none'); this.form.submit(); this.disabled = true;">
                        <option value="">{{ $clientTags->isEmpty() && !$client->tag ? 'Sin etiquetas creadas' : 'Sin etiqueta' }}</option>
                        @foreach($clientTags as $option)
                            <option value="{{ $option->name }}" @selected($client->tag === $option->name)>{{ $option->name }}</option>
                        @endforeach
                        @if($client->tag && !$clientTags->contains('name', $client->tag))
                            <option value="{{ $client->tag }}" selected>{{ $client->tag }}</option>
                        @endif
                    </select>
                    <span class="input-group-text bg-white cs-tag-spinner d-none"><span class="spinner-border spinner-border-sm"></span></span>
                </div>
            </form>
        @endif
    <x-btn variant="secondary" icon="pencil" :href="route('clients.edit', $client)">Editar</x-btn>
    <x-btn variant="secondary" icon="arrow-left" :href="route('clients.index')">Volver</x-btn>
</x-page-header>

{{-- Client info + stats --}}
<div class="row g-3 mb-3">
    <div class="col-md-5 d-flex flex-column gap-2">
        <div class="card border-0 shadow-sm cs-info-card">
            <div class="card-body p-3">
                <div class="cs-stats-label mb-2"><i class="bi bi-person-lines-fill me-1"></i> Información del cliente</div>

                {{-- Photo + name --}}
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="client-avatar-lg flex-shrink-0">
                        @if($client->photo_url)
                            <img src="{{ $client->photo_url }}" alt="{{ $client->name }}" class="client-avatar-lg__img"
                                 data-lightbox="{{ $client->photo_url }}" data-label="Foto del cliente" style="cursor:zoom-in;">
                        @else
                            <i class="bi bi-person-fill text-primary"></i>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <div class="fw-bold cs-info-name">{{ $client->name }}</div>
                        <div class="text-muted cs-info-sub">{{ $client->email ?? $client->phone ?? 'Cliente registrado' }}</div>
                        <span class="badge mt-1 {{ $client->active ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' }} fs-xs">
                            {{ $client->active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>
                </div>

                {{-- Info rows --}}
                <dl class="cs-info-rows mb-0">
                    <dt>Documento</dt>
                    <dd>{{ $client->id_number ?: '-' }}</dd>
                    <dt>Teléfono</dt>
                    <dd>{{ $client->phone ?: '-' }}</dd>
                    <dt>Email</dt>
                    <dd>{{ $client->email ?: '-' }}</dd>
                    <dt>Dirección</dt>
                    <dd>{{ $client->address ?: '-' }}</dd>
                    <dt>Ubicación</dt>
                    <dd>
                        @if($client->location_url || ($client->location_lat && $client->location_lng))
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                @if($client->location_lat && $client->location_lng)
                                    <span>{{ number_format($client->location_lat, 7) }}, {{ number_format($client->location_lng, 7) }}</span>
                                @endif
                                @php
                                    $mapUrl = $client->location_url ?: ('https://www.google.com/maps?q=' . $client->location_lat . ',' . $client->location_lng);
                                @endphp
                                <x-btn variant="outline-primary" icon="geo-alt" :href="$mapUrl" target="_blank" rel="noopener">Ver en mapa</x-btn>
                            </div>
                        @else
                            -
                        @endif
                    </dd>
                    @if($client->notes)
                        <dt>Notas</dt>
                        <dd>{{ $client->notes }}</dd>
                    @endif
                    <dt>Registrado</dt>
                    <dd>{{ $client->created_at?->format('d/m/Y') }}</dd>
                </dl>

            </div>
        </div>

        @include('clients.partials.payment-history', ['clientRating' => $clientRating, 'ratingSettings' => $ratingSettings])
    </div>

    <div class="col-md-7 d-flex flex-column gap-2">

        {{-- Documents --}}
        @if($client->documents->isNotEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 pb-2">
                <div class="cs-stats-label mb-2">
                    <i class="bi bi-paperclip me-1"></i> Documentos adjuntos
                    <span class="cs-section-count ms-1">{{ $client->documents->count() }}</span>
                </div>
                <div class="cs-doc-gallery">
                    @foreach($client->documents as $doc)
                        @if($doc->isImage())
                            <button type="button" class="cs-doc-tile"
                                    data-lightbox="{{ $doc->url }}"
                                    data-label="{{ $doc->label ?: $doc->original_name }}"
                                    title="{{ $doc->label ?: $doc->original_name }}">
                                <img src="{{ $doc->url }}" alt="{{ $doc->label }}" class="cs-doc-tile__img">
                                @if($doc->label)
                                <div class="cs-doc-tile__label">{{ $doc->label }}</div>
                                @endif
                            </button>
                        @else
                            <a href="{{ $doc->url }}" target="_blank" rel="noopener"
                               class="cs-doc-tile cs-doc-tile--pdf"
                               title="{{ $doc->label ?: $doc->original_name }}">
                                <i class="bi bi-file-earmark-pdf-fill text-danger"></i>
                                <div class="cs-doc-tile__label">{{ $doc->label ?: 'PDF' }}</div>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        @endif


        {{-- Financial summary --}}
        <div class="card border-0 shadow-sm flex-grow-1">
            <div class="card-body p-3 pb-2">
                <div class="cs-stats-label mb-2">
                    <i class="bi bi-bar-chart-fill me-1"></i> Resumen financiero
                </div>
                <div class="cs-stats-grid">
                    <div class="cs-stat cs-stat--blue">
                        <div class="cs-stat__icon"><i class="bi bi-cash-coin"></i></div>
                        <div class="cs-stat__val">{{ $client->loans_count ?? 0 }}</div>
                        <div class="cs-stat__lbl">Préstamos</div>
                    </div>
                    <div class="cs-stat cs-stat--green">
                        <div class="cs-stat__icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="cs-stat__val">{{ $currencySymbol }} {{ number_format($totalPaid, 0) }}</div>
                        <div class="cs-stat__lbl">Total pagado</div>
                    </div>
                    <div class="cs-stat cs-stat--red">
                        <div class="cs-stat__icon"><i class="bi bi-hourglass-split"></i></div>
                        <div class="cs-stat__val">{{ $currencySymbol }} {{ number_format($totalPending, 0) }}</div>
                        <div class="cs-stat__lbl">Pendiente</div>
                    </div>
                    <div class="cs-stat cs-stat--cyan">
                        <div class="cs-stat__icon"><i class="bi bi-bag-check-fill"></i></div>
                        <div class="cs-stat__val">{{ $client->purchases_count ?? 0 }}</div>
                        <div class="cs-stat__lbl">Compras</div>
                    </div>
                    <div class="cs-stat cs-stat--amber">
                        <div class="cs-stat__icon"><i class="bi bi-cart-check-fill"></i></div>
                        <div class="cs-stat__val">{{ $client->sales_count ?? 0 }}</div>
                        <div class="cs-stat__lbl">Ventas</div>
                    </div>
                    <div class="cs-stat cs-stat--slate">
                        <div class="cs-stat__icon"><i class="bi bi-box-seam-fill"></i></div>
                        <div class="cs-stat__val">{{ $currencySymbol }} {{ number_format($totalPurchased, 0) }}</div>
                        <div class="cs-stat__lbl">Tot. comprado</div>
                    </div>
                    <div class="cs-stat cs-stat--slate">
                        <div class="cs-stat__icon"><i class="bi bi-tag-fill"></i></div>
                        <div class="cs-stat__val">{{ $currencySymbol }} {{ number_format($totalSold, 0) }}</div>
                        <div class="cs-stat__lbl">Tot. vendido</div>
                    </div>
                    <div class="cs-stat cs-stat--actions">
                        <x-btn icon="plus-lg" :block="true" :href="route('loans.create', ['client_id' => $client->id])">Préstamo</x-btn>
                        @if($hasActiveCashSession)
                            <x-btn variant="warning" icon="plus-lg" :block="true" :href="route('sales.create', ['client_id' => $client->id])">Venta</x-btn>
                        @else
                            <x-btn variant="warning" icon="lock" :block="true" disabled title="Aperturá una caja para vender">Venta</x-btn>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Tabs --}}
<div class="card border-0 shadow-sm">
    <div class="card-header p-0 border-bottom-0 cs-tabs-header">
        <ul class="nav nav-tabs cs-client-tabs px-3 pt-2" id="clientTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="loans-tab" data-bs-toggle="tab" data-bs-target="#loans-pane" type="button" role="tab" aria-controls="loans-pane" aria-selected="true">
                    <i class="bi bi-cash-coin me-1"></i> Préstamos
                    <span class="cs-tab-count bg-primary bg-opacity-10 text-primary">{{ $loans->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="purchases-tab" data-bs-toggle="tab" data-bs-target="#purchases-pane" type="button" role="tab" aria-controls="purchases-pane" aria-selected="false">
                    <i class="bi bi-bag-check me-1"></i> Compras
                    <span class="cs-tab-count bg-info bg-opacity-10 text-info">{{ $purchases->count() }}</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales-pane" type="button" role="tab" aria-controls="sales-pane" aria-selected="false">
                    <i class="bi bi-cart-check me-1"></i> Ventas
                    <span class="cs-tab-count bg-warning bg-opacity-10 text-warning">{{ $sales->count() }}</span>
                </button>
            </li>
        </ul>
    </div>

    <div class="tab-content" id="clientTabsContent">
        {{-- ═══ TAB: Préstamos ═══ --}}
        <div class="tab-pane fade show active" id="loans-pane" role="tabpanel" aria-labelledby="loans-tab">
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <div class="small text-muted">
                    <strong class="text-dark">{{ $loans->count() }}</strong> préstamo{{ $loans->count() !== 1 ? 's' : '' }} registrado{{ $loans->count() !== 1 ? 's' : '' }}
                    @if($totalBorrowed > 0)
                        &middot; Total prestado: <strong class="text-primary">{{ $currencySymbol }} {{ number_format($totalBorrowed, 2) }}</strong>
                    @endif
                </div>
                <div class="d-flex gap-2">
                    @include('components.export-dropdown', ['baseUrl' => route('exports.client-loans', $client), 'params' => []])
                    <x-btn icon="plus-lg" :href="route('loans.create', ['client_id' => $client->id])">Nuevo préstamo</x-btn>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">COD</th>
                            <th>Producto</th>
                            <th>Sucursal</th>
                            <th class="text-end">Monto</th>
                            <th class="text-center">Tasa</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Pendiente</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loans as $loan)
                            @php
                                $loanPending = in_array($loan->status, [\App\Models\Loan::STATUS_FINISHED, \App\Models\Loan::STATUS_CANCELLED])
                                    ? 0
                                    : round((float) $loan->current_capital + $loan->getUnpaidInterestTotal(), 2);
                            @endphp
                            <tr class="cs-clickable-row" onclick="window.location='{{ route('loans.show', ['loan' => $loan, 'from' => 'client']) }}'">
                                <td class="ps-4 fw-semibold">
                                    PR-{{ str_pad((string) $loan->numero, 5, '0', STR_PAD_LEFT) }}
                                    @php $loanBy = $loan->createdBy?->personal?->full_name ?? $loan->createdBy?->name; @endphp
                                    @if($loanBy)
                                        <div class="text-muted d-flex align-items-center gap-1 mt-1 fw-normal fs-xs" title="Registrado por">
                                            <i class="bi bi-person-badge" style="color:#6f42c1;"></i>{{ $loanBy }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $loan->product?->name ?? '-' }}</div>
                                    @php
                                        $categoryColor = $loan->creditCategory?->color
                                            ?? match((int) ($loan->credit_category_id ?? 0)) {
                                                1 => '#4F46E5',
                                                2 => '#D9B646',
                                                3 => '#16A34A',
                                                4 => '#E68A2E',
                                                default => '#64748B',
                                            };
                                        $hex = ltrim((string) $categoryColor, '#');
                                        $r = strlen($hex) === 6 ? hexdec(substr($hex, 0, 2)) : 100;
                                        $g = strlen($hex) === 6 ? hexdec(substr($hex, 2, 2)) : 116;
                                        $b = strlen($hex) === 6 ? hexdec(substr($hex, 4, 2)) : 139;
                                        $categoryBg = "rgba({$r}, {$g}, {$b}, 0.16)";
                                        $categoryBorder = "rgba({$r}, {$g}, {$b}, 0.28)";
                                    @endphp
                                    <div class="mt-1">
                                        <span class="badge rounded-pill fw-normal" style="font-size:var(--fs-xs); padding:.2rem .48rem; background-color: {{ $categoryBg }}; color: {{ $categoryColor }}; border: 1px solid {{ $categoryBorder }};">
                                            {{ $loan->creditCategory?->name ?? 'Sin categoría' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="small">
                                    @if($loan->branch)
                                        @php $lbColor = $loan->branch->color ?? '#0d6efd'; @endphp
                                        <span class="badge" style="background:{{ $lbColor }}20; color:{{ $lbColor }}; border:1px solid {{ $lbColor }}50; font-weight:500;">
                                            {{ $loan->branch->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ $currencySymbol }} {{ number_format($loan->amount, 2) }}</td>
                                <td class="text-center">{{ $loan->interest_rate }}%</td>
                                <td class="text-end text-success">{{ $currencySymbol }} {{ number_format($loan->total_paid, 2) }}</td>
                                <td class="text-end text-danger">{{ $currencySymbol }} {{ number_format($loanPending, 2) }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $statusColors[$loan->status] ?? 'secondary' }}">{{ $statusLabels[$loan->status] ?? ucfirst($loan->status) }}</span>
                                    @if(($loan->unpaid_interests_count ?? 0) > 0)
                                        <div class="mt-1">
                                            <span class="badge bg-light text-danger border border-danger-subtle fw-semibold fs-xs"
                                                  title="Intereses generados sin pagar">
                                                <i class="bi bi-hourglass-split me-1"></i>{{ $loan->unpaid_interests_count }} {{ $loan->unpaid_interests_count === 1 ? 'interés' : 'intereses' }} sin pagar
                                            </span>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">{{ $loan->created_at?->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-cash-coin fs-3 d-block mb-2 opacity-25"></i>
                                        Este cliente no tiene préstamos todavía.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══ TAB: Compras ═══ --}}
        <div class="tab-pane fade" id="purchases-pane" role="tabpanel" aria-labelledby="purchases-tab">
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <div class="small text-muted">
                    <strong class="text-dark">{{ $purchases->count() }}</strong> compra{{ $purchases->count() !== 1 ? 's' : '' }} registrada{{ $purchases->count() !== 1 ? 's' : '' }}
                    @if($totalPurchased > 0)
                        &middot; Total comprado: <strong class="text-info">{{ $currencySymbol }} {{ number_format($totalPurchased, 2) }}</strong>
                    @endif
                </div>
                <x-btn variant="info" icon="plus-lg" :href="route('purchases.create', ['client_id' => $client->id, 'from' => 'client'])">Nueva compra</x-btn>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">COD</th>
                            <th>Producto</th>
                            <th class="text-end">Monto</th>
                            <th>Sucursal</th>
                            <th>Caja</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($purchases as $purchase)
                            <tr class="cs-clickable-row" onclick="window.location='{{ route('purchases.show', ['purchase' => $purchase, 'from' => 'client']) }}'">
                                <td class="ps-4 fw-semibold">
                                    CO-{{ str_pad((string) $purchase->numero, 5, '0', STR_PAD_LEFT) }}
                                    @php $purchaseBy = $purchase->createdBy?->personal?->full_name ?? $purchase->createdBy?->name; @endphp
                                    @if($purchaseBy)
                                        <div class="text-muted d-flex align-items-center gap-1 mt-1 fw-normal fs-xs" title="Registrado por">
                                            <i class="bi bi-person-badge" style="color:#6f42c1;"></i>{{ $purchaseBy }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $purchase->product?->name ?? '-' }}</div>
                                    @php
                                        $pCatColor = $purchase->creditCategory?->color
                                            ?? match((int) ($purchase->credit_category_id ?? 0)) {
                                                1 => '#4F46E5',
                                                2 => '#D9B646',
                                                3 => '#16A34A',
                                                4 => '#E68A2E',
                                                default => '#64748B',
                                            };
                                        $pHex = ltrim((string) $pCatColor, '#');
                                        $pR = strlen($pHex) === 6 ? hexdec(substr($pHex, 0, 2)) : 100;
                                        $pG = strlen($pHex) === 6 ? hexdec(substr($pHex, 2, 2)) : 116;
                                        $pB = strlen($pHex) === 6 ? hexdec(substr($pHex, 4, 2)) : 139;
                                    @endphp
                                    <div class="mt-1">
                                        <span class="badge rounded-pill fw-normal" style="font-size:var(--fs-xs); padding:.2rem .48rem; background-color: rgba({{ $pR }},{{ $pG }},{{ $pB }},0.16); color: {{ $pCatColor }}; border: 1px solid rgba({{ $pR }},{{ $pG }},{{ $pB }},0.28);">
                                            {{ $purchase->creditCategory?->name ?? 'Sin categoría' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="text-end fw-semibold">{{ $currencySymbol }} {{ number_format((float) $purchase->amount, 2) }}</td>
                                <td class="small">{{ $purchase->branch?->name ?? '-' }}</td>
                                <td class="small">{{ $purchase->cashRegister?->name ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $purchaseStatusColors[$purchase->status] ?? 'secondary' }}">
                                        {{ $purchaseStatusLabels[$purchase->status] ?? ucfirst($purchase->status) }}
                                    </span>
                                </td>
                                <td class="text-end pe-4">{{ $purchase->purchase_date ? \Carbon\Carbon::parse($purchase->purchase_date)->format('d/m/Y') : ($purchase->created_at?->format('d/m/Y') ?? '-') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-bag-check fs-3 d-block mb-2 opacity-25"></i>
                                        Este cliente no tiene compras todavía.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ═══ TAB: Ventas ═══ --}}
        <div class="tab-pane fade" id="sales-pane" role="tabpanel" aria-labelledby="sales-tab">
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <div class="small text-muted">
                    <strong class="text-dark">{{ $sales->count() }}</strong> venta{{ $sales->count() !== 1 ? 's' : '' }} registrada{{ $sales->count() !== 1 ? 's' : '' }}
                    @if($totalSold > 0)
                        &middot; Total vendido: <strong class="text-warning">{{ $currencySymbol }} {{ number_format($totalSold, 2) }}</strong>
                    @endif
                </div>
                @if($hasActiveCashSession)
                    <x-btn variant="warning" icon="plus-lg" :href="route('sales.create', ['client_id' => $client->id])">Nueva venta</x-btn>
                @else
                    <x-btn variant="warning" icon="lock" disabled title="Aperturá una caja para vender">Nueva venta</x-btn>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">COD</th>
                            <th>Producto</th>
                            <th>Sucursal</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-end">Venta</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end">Ganancia</th>
                            <th class="text-center">Origen</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sales as $sale)
                            <tr class="cs-clickable-row" onclick="window.location='{{ route('sales.show', $sale) }}'">
                                <td class="ps-4 fw-semibold" onclick="event.stopPropagation()">
                                    VE-{{ str_pad((string) $sale->numero, 5, '0', STR_PAD_LEFT) }}
                                    @php $saleBy = $sale->createdBy?->personal?->full_name ?? $sale->createdBy?->name; @endphp
                                    @if($saleBy)
                                        <div class="text-muted d-flex align-items-center gap-1 mt-1 fw-normal fs-xs" title="Registrado por">
                                            <i class="bi bi-person-badge" style="color:#6f42c1;"></i>{{ $saleBy }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $sale->product?->name ?? '-' }}</div>
                                    @php
                                        $sCatColor = $sale->creditCategory?->color
                                            ?? match((int) ($sale->credit_category_id ?? 0)) {
                                                1 => '#4F46E5', 2 => '#D9B646', 3 => '#16A34A', 4 => '#E68A2E', default => '#64748B',
                                            };
                                        $sHex = ltrim((string) $sCatColor, '#');
                                        $sR = strlen($sHex) === 6 ? hexdec(substr($sHex, 0, 2)) : 100;
                                        $sG = strlen($sHex) === 6 ? hexdec(substr($sHex, 2, 2)) : 116;
                                        $sB = strlen($sHex) === 6 ? hexdec(substr($sHex, 4, 2)) : 139;
                                    @endphp
                                    <div class="mt-1">
                                        <span class="badge rounded-pill fw-normal" style="font-size:var(--fs-xs); padding:.2rem .48rem; background-color: rgba({{ $sR }},{{ $sG }},{{ $sB }},0.16); color: {{ $sCatColor }}; border: 1px solid rgba({{ $sR }},{{ $sG }},{{ $sB }},0.28);">
                                            {{ $sale->creditCategory?->name ?? 'Sin categoría' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="small">
                                    @if($sale->branch)
                                        @php $sbColor = $sale->branch->color ?? '#0d6efd'; @endphp
                                        <span class="badge" style="background:{{ $sbColor }}20; color:{{ $sbColor }}; border:1px solid {{ $sbColor }}50; font-weight:500;">
                                            {{ $sale->branch->name }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($sale->isCredit())
                                        <div class="d-flex flex-column align-items-center gap-1">
                                            <span class="badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle fs-xs">
                                                <i class="bi bi-calendar2-week me-1"></i>Crédito
                                            </span>
                                            @if(($sale->installments_total_count ?? 0) > 0)
                                                @php
                                                    $sAllPaid = ($sale->installments_paid_count ?? 0) >= $sale->installments_total_count;
                                                    $sPending = $sale->installments_total_count - ($sale->installments_paid_count ?? 0);
                                                @endphp
                                                @if($sAllPaid)
                                                    <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle fs-xs">
                                                        <i class="bi bi-check-all me-1"></i>Pagado
                                                    </span>
                                                @else
                                                    <span class="badge rounded-pill bg-warning-subtle text-warning border border-warning-subtle fs-xs">
                                                        {{ $sPending }} pend.
                                                    </span>
                                                @endif
                                            @endif
                                        </div>
                                    @else
                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle fs-xs">
                                            <i class="bi bi-cash-coin me-1"></i>Contado
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">{{ $currencySymbol }} {{ number_format((float) $sale->amount, 2) }}</td>
                                <td class="text-end text-muted">{{ $currencySymbol }} {{ number_format((float) $sale->cost, 2) }}</td>
                                <td class="text-end {{ (float) $sale->profit >= 0 ? 'text-success' : 'text-danger' }} fw-semibold">{{ $currencySymbol }} {{ number_format((float) $sale->profit, 2) }}</td>
                                <td class="text-center">
                                    @if($sale->loan_collateral_id)
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-xs">Préstamo</span>
                                    @elseif($sale->purchase_id)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle fs-xs">Compra</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-xs">Inventario</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $saleStatusColors[$sale->status] ?? 'secondary' }}">
                                        {{ $saleStatusLabels[$sale->status] ?? ucfirst($sale->status) }}
                                    </span>
                                </td>
                                <td class="text-end pe-4">{{ $sale->sale_date ? \Carbon\Carbon::parse($sale->sale_date)->format('d/m/Y') : ($sale->created_at?->format('d/m/Y') ?? '-') }}</td>
                                <td class="pe-3" onclick="event.stopPropagation()">
                                    @if($sale->status === 'completed')
                                        @if($canDeleteSale)
                                        <x-icon-btn icon="trash3" label="Eliminar venta" variant="danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#saleReturnModal"
                                            data-sale-id="{{ $sale->id }}"
                                            data-sale-code="VE-{{ str_pad((string) $sale->numero, 5, '0', STR_PAD_LEFT) }}"
                                            data-sale-product="{{ $sale->product?->name ?? '-' }}"
                                            data-sale-client="{{ $sale->client?->name ?? 'Sin cliente' }}"
                                            data-sale-amount="{{ number_format((float) $sale->amount, 2) }}"
                                            data-sale-type="{{ $sale->payment_type }}"
                                            data-return-url="{{ route('sales.return', $sale) }}" />
                                        @endif
                                    @elseif($sale->status === 'returned')
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size:var(--fs-xs);white-space:nowrap;">
                                            <i class="bi bi-check2 me-1"></i>Eliminada
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="bi bi-cart-check fs-3 d-block mb-2 opacity-25"></i>
                                        Este cliente no tiene ventas todavía.
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- ── Lightbox ────────────────────────────────────────────── --}}
<div id="cs-lightbox" class="cs-lightbox" role="dialog" aria-modal="true" style="display:none;">
    <div class="cs-lightbox__backdrop"></div>
    <div class="cs-lightbox__content">
        <button type="button" class="cs-lightbox__close" id="cs-lb-close" aria-label="Cerrar">
            <i class="bi bi-x-lg"></i>
        </button>
        <img id="cs-lb-img" src="" alt="" class="cs-lightbox__img">
        <div id="cs-lb-label" class="cs-lightbox__label"></div>
        <div class="cs-lightbox__nav">
            <button type="button" id="cs-lb-prev" class="cs-lightbox__nav-btn" title="Anterior">
                <i class="bi bi-chevron-left"></i>
            </button>
            <span id="cs-lb-counter" class="cs-lightbox__counter"></span>
            <button type="button" id="cs-lb-next" class="cs-lightbox__nav-btn" title="Siguiente">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

{{-- ── Modal: Devolución de Venta ────────────────────────────── --}}
<div class="modal fade" id="saleReturnModal" tabindex="-1" aria-labelledby="saleReturnModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="border-radius:20px">
            <div class="modal-header border-0 pb-0 px-4 pt-4" style="background:linear-gradient(135deg,#fffbeb 0%,#fef3c7 100%)">
                <div class="d-flex align-items-center gap-3 w-100">
                    <div style="width:46px;height:46px;border-radius:14px;background:#fde68a;color:#92400e;display:flex;align-items:center;justify-content:center;font-size:var(--fs-lg);flex-shrink:0;">
                        <i class="bi bi-arrow-return-left"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-bold fs-md">Eliminar Venta</div>
                        <div class="small text-warning-emphasis" id="srm-subtitle">Revertirá inventario y caja</div>
                    </div>
                    <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="modal"></button>
                </div>
                <div class="w-100 mt-3 mb-1 p-3 rounded-3" style="background:rgba(255,255,255,.65);border:1px solid #fde68a;">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <div style="color:#92400e;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Código</div>
                            <div class="fw-bold" id="srm-code">—</div>
                        </div>
                        <div class="col-6">
                            <div style="color:#92400e;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Monto</div>
                            <div class="fw-bold text-danger" id="srm-amount">—</div>
                        </div>
                        <div class="col-6">
                            <div style="color:#92400e;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Producto</div>
                            <div class="fw-semibold text-truncate" id="srm-product">—</div>
                        </div>
                        <div class="col-6">
                            <div style="color:#92400e;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Cliente</div>
                            <div class="fw-semibold text-truncate" id="srm-client">—</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-body px-4 pt-3 pb-0">
                <div class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3" style="background:#fff7ed;border:1px solid #fed7aa;font-size:var(--fs-sm);color:#9a3412;">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1 fs-sm"></i>
                    <div><strong>Esta acción no se puede deshacer.</strong> Se reingresará el producto al inventario y se generará un egreso en caja por el monto cobrado.</div>
                </div>
                <div id="srm-credit-notice" class="d-flex align-items-start gap-2 p-3 rounded-3 mb-3 d-none" style="background:#eff6ff;border:1px solid #bfdbfe;font-size:var(--fs-sm);color:#1e40af;">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1 fs-sm"></i>
                    <div>Venta al <strong>Crédito</strong>: solo se revertirán las cuotas <strong>ya cobradas</strong>. Las cuotas pendientes serán anuladas.</div>
                </div>
                <form id="saleReturnForm" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Motivo de eliminación <span class="text-danger">*</span></label>
                        <select name="return_reason" id="srm-reason" class="form-select form-select-sm" required>
                            <option value="">Seleccionar motivo...</option>
                            @foreach(\App\Models\Sale::RETURN_REASONS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Observaciones <span class="text-muted fw-normal">(opcional)</span></label>
                        <textarea name="return_notes" class="form-control form-control-sm" rows="2" placeholder="Detalles adicionales sobre la devolución..."></textarea>
                    </div>
                </form>
            </div>
            <div class="px-4 pb-4">
                <div class="d-grid gap-2">
                    <x-btn variant="danger" type="submit" form="saleReturnForm" icon="trash3" size="md" :block="true">Confirmar eliminación</x-btn>
                    <x-btn variant="secondary" size="md" :block="true" data-bs-dismiss="modal">Cancelar</x-btn>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // ── Sale return modal ─────────────────────────────────
    const modal = document.getElementById('saleReturnModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            document.getElementById('srm-code').textContent    = btn.dataset.saleCode;
            document.getElementById('srm-amount').textContent  = '{{ $currencySymbol }} ' + btn.dataset.saleAmount;
            document.getElementById('srm-product').textContent = btn.dataset.saleProduct;
            document.getElementById('srm-client').textContent  = btn.dataset.saleClient;
            document.getElementById('saleReturnForm').action   = btn.dataset.returnUrl;
            const isCredit = btn.dataset.saleType === 'CREDITO';
            document.getElementById('srm-credit-notice').classList.toggle('d-none', !isCredit);
            document.getElementById('srm-subtitle').textContent = isCredit ? 'Revertirá cuotas cobradas e inventario' : 'Revertirá inventario y caja';
            document.getElementById('srm-reason').value = '';
            modal.querySelector('textarea[name="return_notes"]').value = '';
        });
    }

    // ── Lightbox ──────────────────────────────────────────
    const lightbox   = document.getElementById('cs-lightbox');
    const lbImg      = document.getElementById('cs-lb-img');
    const lbLabel    = document.getElementById('cs-lb-label');
    const lbCounter  = document.getElementById('cs-lb-counter');
    const lbClose    = document.getElementById('cs-lb-close');
    const lbPrev     = document.getElementById('cs-lb-prev');
    const lbNext     = document.getElementById('cs-lb-next');
    const backdrop   = lightbox.querySelector('.cs-lightbox__backdrop');

    // Collect all image triggers (photo + doc tiles)
    let items  = [];
    let cursor = 0;

    function buildItems() {
        items = Array.from(document.querySelectorAll('[data-lightbox]'));
    }

    function open(index) {
        buildItems();
        cursor = Math.max(0, Math.min(index, items.length - 1));
        render();
        lightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function close() {
        lightbox.style.display = 'none';
        document.body.style.overflow = '';
        lbImg.src = '';
    }

    function render() {
        const el = items[cursor];
        lbImg.src   = el.dataset.lightbox;
        lbImg.alt   = el.dataset.label || '';
        lbLabel.textContent  = el.dataset.label || '';
        lbCounter.textContent = items.length > 1 ? (cursor + 1) + ' / ' + items.length : '';
        lbPrev.disabled = cursor === 0;
        lbNext.disabled = cursor === items.length - 1;
    }

    document.addEventListener('click', function (e) {
        const el = e.target.closest('[data-lightbox]');
        if (!el) return;
        e.preventDefault();
        buildItems();
        open(items.indexOf(el));
    });

    lbClose.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    lbPrev.addEventListener('click', function () { if (cursor > 0) { cursor--; render(); } });
    lbNext.addEventListener('click', function () { if (cursor < items.length - 1) { cursor++; render(); } });

    document.addEventListener('keydown', function (e) {
        if (lightbox.style.display === 'none') return;
        if (e.key === 'Escape')      close();
        if (e.key === 'ArrowLeft'  && cursor > 0)              { cursor--; render(); }
        if (e.key === 'ArrowRight' && cursor < items.length - 1) { cursor++; render(); }
    });
});
</script>
@endpush

@push('styles')
<style>
    /* ── Client show tabs ─────────────────── */
    .cs-tabs-header {
        background: linear-gradient(135deg, #1f6fe8 0%, #185dc0 100%);
        border-radius: .6rem .6rem 0 0;
        border-bottom: 1px solid rgba(255, 255, 255, .16);
    }
    .cs-client-tabs {
        border-bottom: none;
        gap: .3rem;
    }
    .cs-client-tabs .nav-link {
        border: 1px solid transparent;
        border-bottom: none;
        color: rgba(255, 255, 255, .82);
        font-weight: 600;
        font-size:var(--fs-base);
        padding: .65rem 1rem;
        border-radius: .45rem .45rem 0 0;
        margin-bottom: 0;
        transition: all .2s ease;
        display: inline-flex;
        align-items: center;
        gap: .4rem;
    }
    .cs-client-tabs .nav-link:hover {
        color: #fff;
        background: rgba(255, 255, 255, .14);
        border-color: rgba(255, 255, 255, .2);
    }
    .cs-client-tabs .nav-link.active {
        color: #0f4fae;
        background: #fff;
        border-color: rgba(255, 255, 255, .45);
        box-shadow: 0 -1px 0 rgba(255, 255, 255, .65);
    }
    .cs-tab-count {
        font-size:var(--fs-xs);
        font-weight: 700;
        padding: .15rem .45rem;
        border-radius: 50rem;
        line-height: 1;
    }
    .cs-client-tabs .nav-link .cs-tab-count {
        background: rgba(255, 255, 255, .18) !important;
        color: #fff !important;
        border: 1px solid rgba(255, 255, 255, .24);
    }
    .cs-client-tabs .nav-link.active .cs-tab-count {
        background: rgba(13, 110, 253, .14) !important;
        color: #0d6efd !important;
        border-color: rgba(13, 110, 253, .2);
    }
    .cs-clickable-row {
        cursor: pointer;
        transition: background-color .15s ease;
    }
    .cs-clickable-row:hover {
        background-color: rgba(13, 110, 253, .04) !important;
    }

    /* ── Client avatar (large) ───────────── */
    .client-avatar-lg {
        width: 88px; height: 88px;
        border-radius: 20px; overflow: hidden;
        background: #eef5ff;
        border: 2px solid rgba(13,110,253,.18);
        display: inline-flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 14px rgba(13,110,253,.12);
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .client-avatar-lg:hover { transform: scale(1.04); box-shadow: 0 6px 20px rgba(13,110,253,.2); }
    .client-avatar-lg__img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .client-avatar-lg i { font-size:var(--fs-xl); color: #0d6efd; }

    /* ── Client info card (compact) ─────── */
    .cs-info-card .min-w-0 { min-width: 0; }
    .cs-info-name { font-size:var(--fs-base); line-height: 1.2; }
    .cs-info-sub { font-size:var(--fs-xs); }
    .cs-info-rows {
        display: grid; grid-template-columns: 82px 1fr;
        column-gap: .5rem; row-gap: .3rem;
        font-size:var(--fs-sm);
    }
    .cs-info-rows dt { font-weight: 400; color: #6c757d; }
    .cs-info-rows dd { margin: 0; font-weight: 600; color: #212529; word-break: break-word; }

    /* ── Section label ──────────────────── */
    .cs-section-label {
        font-size:var(--fs-xs); font-weight: 700; text-transform: uppercase;
        letter-spacing: .05em; color: #6c757d;
        display: flex; align-items: center; gap: .4rem;
    }
    .cs-section-count {
        background: #e9ecef; color: #495057;
        border-radius: 999px; padding: .05rem .45rem;
        font-size:var(--fs-xs); font-weight: 700;
    }

    /* ── Document gallery ───────────────── */
    .cs-doc-gallery {
        display: flex; flex-wrap: wrap; gap: .4rem;
    }
    .cs-doc-tile {
        width: 68px; position: relative;
        border-radius: 9px; overflow: hidden;
        border: 1.5px solid #e2e8f0;
        background: #f8fafc;
        cursor: pointer; display: flex; flex-direction: column;
        align-items: center; padding: 0;
        transition: all .18s ease;
        text-decoration: none;
    }
    .cs-doc-tile:hover {
        border-color: #0d6efd;
        box-shadow: 0 0 0 3px rgba(13,110,253,.12);
        transform: translateY(-2px);
    }
    .cs-doc-tile__img {
        width: 68px; height: 54px; object-fit: cover; display: block;
    }
    .cs-doc-tile--pdf {
        height: 68px; justify-content: center; gap: .15rem;
        border-color: #fecdd3;
        background: linear-gradient(135deg, #fff5f5 0%, #fee2e2 100%);
    }
    .cs-doc-tile--pdf i { font-size:var(--fs-xl); }
    .cs-doc-tile__label {
        font-size:var(--fs-xs); font-weight: 600; color: #374151;
        padding: .18rem .3rem; text-align: center;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        width: 100%; background: rgba(255,255,255,.9);
    }

    /* ── Lightbox ───────────────────────── */
    .cs-lightbox {
        position: fixed; inset: 0; z-index: 9999;
        display: flex; align-items: center; justify-content: center;
    }
    .cs-lightbox__backdrop {
        position: absolute; inset: 0;
        background: rgba(0,0,0,.88);
        backdrop-filter: blur(4px);
        cursor: zoom-out;
    }
    .cs-lightbox__content {
        position: relative; z-index: 1;
        display: flex; flex-direction: column; align-items: center;
        max-width: 90vw; max-height: 90vh;
        animation: lb-in .2s ease;
    }
    @keyframes lb-in {
        from { opacity: 0; transform: scale(.93); }
        to   { opacity: 1; transform: scale(1); }
    }
    .cs-lightbox__img {
        max-width: 88vw; max-height: 78vh;
        object-fit: contain; border-radius: 14px;
        box-shadow: 0 8px 48px rgba(0,0,0,.5);
        display: block;
    }
    .cs-lightbox__close {
        position: fixed; top: 18px; right: 22px;
        width: 42px; height: 42px; border-radius: 50%;
        background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25);
        color: #fff; font-size:var(--fs-md); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background .15s ease; z-index: 2;
        backdrop-filter: blur(6px);
    }
    .cs-lightbox__close:hover { background: rgba(255,255,255,.25); }
    .cs-lightbox__label {
        color: rgba(255,255,255,.85); font-size:var(--fs-base); font-weight: 600;
        margin-top: .75rem; text-align: center;
        text-shadow: 0 1px 3px rgba(0,0,0,.5);
    }
    .cs-lightbox__nav {
        display: flex; align-items: center; gap: 1rem; margin-top: .6rem;
    }
    .cs-lightbox__nav-btn {
        width: 40px; height: 40px; border-radius: 50%;
        background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.25);
        color: #fff; font-size:var(--fs-md); cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: background .15s ease;
    }
    .cs-lightbox__nav-btn:hover { background: rgba(255,255,255,.28); }
    .cs-lightbox__nav-btn:disabled { opacity: .3; cursor: default; }
    .cs-lightbox__counter { color: rgba(255,255,255,.65); font-size:var(--fs-sm); min-width: 56px; text-align: center; }
    /* ── Stats grid ──────────────────────── */
    .cs-stats-label {
        font-size:var(--fs-xs); font-weight: 700; text-transform: uppercase;
        letter-spacing: .06em; color: #94a3b8;
    }
    .cs-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: .45rem;
    }
    .cs-stat {
        border-radius: 10px; padding: .65rem .5rem .55rem;
        text-align: center; border: 1px solid transparent;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
    }
    .cs-stat__icon { font-size:var(--fs-md); margin-bottom: .2rem; line-height: 1; }
    .cs-stat__val  { font-size:var(--fs-md); font-weight: 800; line-height: 1.1; }
    .cs-stat__lbl  { font-size:var(--fs-xs); margin-top: .15rem; color: inherit; opacity: .7; white-space: nowrap; }
    .cs-stat--blue  { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
    .cs-stat--green { background:#f0fdf4; border-color:#bbf7d0; color:#15803d; }
    .cs-stat--red   { background:#fff5f5; border-color:#fecaca; color:#dc2626; }
    .cs-stat--cyan  { background:#ecfeff; border-color:#a5f3fc; color:#0e7490; }
    .cs-stat--amber { background:#fffbeb; border-color:#fde68a; color:#b45309; }
    .cs-stat--slate { background:#f8fafc; border-color:#e2e8f0; color:#334155; }
    .cs-stat--actions {
        background: #f8fafc; border: 1.5px dashed #cbd5e1; border-radius: 12px;
        padding: .65rem .4rem; display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: .4rem;
    }
</style>
@endpush
