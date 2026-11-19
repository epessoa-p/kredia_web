@extends('layouts.base')

@section('content')
@php
    $currentCompany = auth()->user()->getCurrentCompany();
    $activeCompanies = auth()->user()->activeCompanies()->get();
    $canUseQuickSaleCatalog = (bool) ($currentCompany && auth()->user()->hasPermissionInCompany('sales.create', $currentCompany));
    $quickSaleProducts = collect();

    if ($canUseQuickSaleCatalog) {
        $saleBranches = \App\Models\Branch::where('company_id', $currentCompany->id)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'warehouse_id']);

        $quickSaleRows = [];

        foreach ($saleBranches as $saleBranch) {
            if (!$saleBranch->warehouse_id) {
                continue;
            }

            $stockItems = \App\Models\InventoryMovement::where('warehouse_id', $saleBranch->warehouse_id)
                ->whereNull('deleted_at')
                ->selectRaw('product_id, SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) as stock')
                ->groupBy('product_id')
                ->havingRaw('SUM(CASE WHEN type = "in" THEN quantity ELSE -quantity END) > 0')
                ->pluck('stock', 'product_id');

            if ($stockItems->isEmpty()) {
                continue;
            }

            $heldCounts = \App\Models\LoanCollateral::where('company_id', $currentCompany->id)
                ->whereIn('status', ['retained', 'stored'])
                ->where('warehouse_id', $saleBranch->warehouse_id)
                ->whereNull('deleted_at')
                ->whereIn('product_id', $stockItems->keys())
                ->selectRaw('product_id, COUNT(*) as held')
                ->groupBy('product_id')
                ->pluck('held', 'product_id');

            $sellableCollaterals = \App\Models\LoanCollateral::where('company_id', $currentCompany->id)
                ->where('status', 'sellable')
                ->where('warehouse_id', $saleBranch->warehouse_id)
                ->whereNull('deleted_at')
                ->whereIn('product_id', $stockItems->keys())
                ->pluck('id', 'product_id');

            $availableIds = $stockItems->keys()->filter(function ($productId) use ($stockItems, $heldCounts, $sellableCollaterals) {
                $stock = (float) $stockItems[$productId];
                $held = (int) ($heldCounts[$productId] ?? 0);
                $freeStock = max(0, $stock - $held);

                return $freeStock > 0 || $sellableCollaterals->has($productId);
            });

            if ($availableIds->isEmpty()) {
                continue;
            }

            $products = \App\Models\Product::whereIn('id', $availableIds)
                ->where('company_id', $currentCompany->id)
                ->where('active', true)
                ->with(['creditCategory', 'inventoryMovements.images'])
                ->get();

            $purchaseOrigins = \App\Models\Purchase::where('company_id', $currentCompany->id)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->whereIn('product_id', $availableIds)
                ->pluck('id', 'product_id');

            foreach ($products as $product) {
                $origin = 'other';
                if ($sellableCollaterals->has($product->id)) {
                    $origin = 'collateral';
                } elseif ($purchaseOrigins->has($product->id)) {
                    $origin = 'purchase';
                }

                $totalStock = (float) ($stockItems[$product->id] ?? 0);
                $held = (int) ($heldCounts[$product->id] ?? 0);
                $availableStock = max(0, $totalStock - $held);
                if ($availableStock <= 0 && !$sellableCollaterals->has($product->id)) {
                    continue;
                }

                $firstImage = $product->inventoryMovements
                    ->where('warehouse_id', $saleBranch->warehouse_id)
                    ->sortByDesc('movement_date')
                    ->flatMap->images
                    ->first();

                $originLabel = match ($origin) {
                    'collateral' => 'Préstamo incumplido',
                    'purchase' => 'Compra',
                    default => 'Inventario',
                };

                $quickSaleRows[] = [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'brand' => $product->brand,
                    'category_name' => $product->creditCategory?->name ?? 'Sin categoría',
                    'branch_name' => $saleBranch->name,
                    'stock' => $availableStock,
                    'price' => (float) $product->price,
                    'image_url' => $firstImage ? asset('storage/' . $firstImage->path) : null,
                    'origin_label' => $originLabel,
                    'sale_url' => route('sales.create', ['branch_id' => $saleBranch->id, 'product_id' => $product->id]),
                    'search_text' => mb_strtolower(implode(' ', array_filter([
                        $product->code,
                        $product->name,
                        $product->brand,
                        $saleBranch->name,
                        $product->creditCategory?->name,
                        $originLabel,
                        $product->description,
                        $product->plate,
                        $product->serial_number,
                    ]))),
                ];
            }
        }

        $quickSaleProducts = collect($quickSaleRows)
            ->sortBy([['branch_name', 'asc'], ['name', 'asc']])
            ->values();
    }

    // Cash register for current user
    $userCashRegister = null;
    $userCashSession = null;
    $personalRecord = \App\Models\Personal::where('user_id', auth()->id())->with('cargo')->first();
    $userIsManager = $personalRecord?->isManager() ?? false;
    $managerOpenableCashRegisters = collect(); // closed registers the manager can open

    if ($personalRecord) {
        if ($userIsManager) {
            // Managers may be assigned to multiple cash registers
            $allManagerRegisters = \App\Models\CashRegister::where('assigned_personal_id', $personalRecord->id)
                ->where('active', true)
                ->with(['activeSession', 'branch'])
                ->get();
            // The first open register (for close button)
            $userCashRegister = $allManagerRegisters->first(fn($cr) => $cr->activeSession !== null);
            $userCashSession  = $userCashRegister?->activeSession;
            // All closed registers (for open button/modal)
            $managerOpenableCashRegisters = $allManagerRegisters->filter(fn($cr) => $cr->activeSession === null)->values();
        } else {
            $userCashRegister = \App\Models\CashRegister::where('assigned_personal_id', $personalRecord->id)
                ->where('active', true)
                ->with(['activeSession', 'branch'])
                ->first();
            if ($userCashRegister) {
                $userCashSession = $userCashRegister->activeSession;
            }
        }
    }

    // Sucursal que se muestra bajo el nombre de la empresa: la única relacionada con el personal
    // (sus cajas + su sucursal); si tiene varias, la de su caja abierta; si no tiene caja abierta, ninguna.
    $sidebarBranch = null;
    if ($personalRecord) {
        $relatedBranches = collect($userIsManager ? ($allManagerRegisters ?? collect()) : [$userCashRegister])
            ->filter()
            ->map(fn ($cr) => $cr->branch)
            ->push($personalRecord->branch)
            ->filter()
            ->unique('id')
            ->values();
        $sidebarBranch = $relatedBranches->count() === 1
            ? $relatedBranches->first()
            : ($userCashSession ? $userCashRegister?->branch : null);
    }

    // ¿Puede registrar gastos (egresos) en la caja abierta?
    $canRegisterExpense = auth()->user()->is_super_admin
        || auth()->user()->hasPermissionInCompany('cash-registers.expense', $currentCompany);

    // Personal de la empresa de la caja (para el gasto "Pago a personal").
    $expensePersonals = ($canRegisterExpense && $userCashRegister && $userCashSession)
        ? \App\Models\Personal::where('company_id', $userCashRegister->company_id)
            ->where('active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name'])
        : collect();
@endphp

<div class="app-shell d-flex" id="appShell">
    {{-- Menú lateral oculto (preferencia guardada): se aplica antes de pintar para que no parpadee. --}}
    <script>
        try {
            if (window.innerWidth >= 992 && localStorage.getItem('sidebarHidden') === '1') {
                document.getElementById('appShell').classList.add('sidebar-hidden');
            }
        } catch (e) {}
    </script>
    <aside class="app-sidebar">
        <div class="sidebar-brand">
            @if($currentCompany?->logo)
                <img src="{{ asset('storage/' . $currentCompany->logo) }}" alt="Logo {{ $currentCompany->name }}" class="brand-logo">
            @else
                <div class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></div>
            @endif
            <div>
                <div class="brand-title">{{ strtoupper($currentCompany?->name ?? 'SISTEMA DE PRESTAMOS') }}</div>
                @if($sidebarBranch)
                    <small class="sidebar-branch" title="Sucursal">
                        <span class="sidebar-branch-dot" style="background: {{ $sidebarBranch->color ?: '#6c757d' }};"></span>
                        {{ strtoupper($sidebarBranch->name) }}
                    </small>
                @endif
            </div>
        </div>

        @include('layouts.partials.sidebar-menu')
    </aside>
    <script>
        // Mantiene a la vista el menú en el que estás: recuerda el scroll del menú lateral entre páginas y, si la
        // opción activa quedó fuera de la vista, la centra. Va aquí (justo después del menú) para que no salte.
        (function () {
            var side = document.querySelector('.app-sidebar');
            if (!side) return;
            try {
                var saved = parseInt(sessionStorage.getItem('sidebarScroll') || '', 10);
                if (!isNaN(saved)) side.scrollTop = saved;
            } catch (e) {}

            var active = side.querySelector('.sidebar-link.active');
            if (active) {
                var s = side.getBoundingClientRect(), a = active.getBoundingClientRect();
                if (a.top < s.top + 40 || a.bottom > s.bottom - 20) {
                    side.scrollTop += (a.top - s.top) - (side.clientHeight / 2) + (a.height / 2);
                }
            }

            var save = function () { try { sessionStorage.setItem('sidebarScroll', String(side.scrollTop)); } catch (e) {} };
            side.addEventListener('scroll', save, { passive: true });
            window.addEventListener('pagehide', save);
        })();
    </script>

    @php $currentModule = \App\Support\Menu::currentModule(); @endphp
    <main class="app-main compact-page mod-{{ $currentModule }}">
        <nav class="navbar navbar-expand-lg app-topbar">
            <div class="container-fluid px-0">
                <div class="d-flex align-items-center gap-3">
                    {{-- Computadora: oculta/muestra el menú lateral. Celular: abre el menú deslizable. --}}
                    <button class="btn-icon" type="button" id="sidebarToggle" title="Mostrar u ocultar el menú" aria-label="Menú">
                        <i class="bi bi-list"></i>
                    </button>
                    @php
                        $displayName  = $personalRecord?->full_name ?? auth()->user()->name;
                        $displayCargo = $personalRecord?->cargo?->name ?? (auth()->user()->is_super_admin ? 'Administrador' : null);
                    @endphp
                    <span class="topbar-label">{{ $displayName }}</span>
                    @if($displayCargo)
                        <span class="topbar-separator">|</span>
                        <span class="topbar-role">{{ $displayCargo }}</span>
                    @endif

                    @if(!auth()->user()->is_super_admin && $activeCompanies->count() > 1)
                        <div class="dropdown">
                            <button class="btn btn-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-buildings"></i> Empresa
                            </button>
                            <ul class="dropdown-menu shadow border-0">
                                @foreach($activeCompanies as $company)
                                    <li>
                                        <form action="{{ route('set-company', $company->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center">
                                                <span>{{ $company->name }}</span>
                                                @if($currentCompany && $currentCompany->id === $company->id)
                                                    <i class="bi bi-check-lg text-success"></i>
                                                @endif
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="d-flex align-items-center gap-2">
                    @if($canUseQuickSaleCatalog)
                        <a href="{{ route('sales.quick-catalog') }}" class="btn btn-sm btn-secondary mod-sales quick-sale-trigger">
                            <i class="bi bi-lightning-charge-fill text-mod"></i> Venta rápida
                        </a>
                    @endif
                    @if($userCashRegister || $managerOpenableCashRegisters->isNotEmpty())
                        @if($userCashSession)
                            {{-- Caja abierta: registrar gasto --}}
                            @if($canRegisterExpense)
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#expenseCashModal">
                                    <i class="bi bi-dash-circle"></i> Registrar gasto
                                </button>
                            @endif
                            {{-- Caja abierta: mostrar botón de cerrar --}}
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#closeCashModal">
                                <i class="bi bi-lock"></i> Cerrar caja{{ $userCashRegister?->branch ? ' · ' . $userCashRegister->branch->name : '' }}
                            </button>
                        @endif
                        @if(!$userCashSession && ($managerOpenableCashRegisters->isNotEmpty() || (!$userIsManager && $userCashRegister)))
                            {{-- Caja cerrada: mostrar botón de aperturar --}}
                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#openCashModal">
                                <i class="bi bi-unlock"></i> Aperturar caja
                            </button>
                        @endif
                    @endif
                    <div class="dropdown">
                        <button class="btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Mi cuenta" aria-label="Mi cuenta">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li><span class="dropdown-item-text text-muted">{{ auth()->user()->name }}</span></li>
                            <li><span class="dropdown-item-text text-muted small">{{ auth()->user()->email }}</span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.show') }}">
                                    <i class="bi bi-person-gear me-1"></i> Mi Perfil
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST" class="m-0">
                                    @csrf
                                    <button class="dropdown-item text-danger" type="submit">
                                        <i class="bi bi-box-arrow-right me-1"></i> Cerrar sesión
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        @if(isset($breadcrumbs))
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    @foreach($breadcrumbs as $label => $url)
                        @if($loop->last)
                            <li class="breadcrumb-item active">{{ $label }}</li>
                        @else
                            <li class="breadcrumb-item"><a href="{{ $url }}" class="text-decoration-none">{{ $label }}</a></li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        @yield('page')
    </main>
</div>

@if($canUseQuickSaleCatalog && $quickSaleProducts->isNotEmpty())
<div class="modal fade" id="quickSaleCatalogModal" tabindex="-1" aria-labelledby="quickSaleCatalogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content quick-sale-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <span class="quick-sale-kicker">Acceso rápido</span>
                    <h5 class="modal-title fw-bold mt-2 mb-1" id="quickSaleCatalogModalLabel">Catálogo para venta inmediata</h5>
                    <p class="text-muted small mb-0">Selecciona un producto disponible y abre la venta con sucursal y producto ya cargados.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="quick-sale-toolbar mb-3">
                    <div class="input-group input-group-sm quick-sale-search-wrap">
                        <span class="input-group-text bg-transparent border-0"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-0" id="quick-sale-search" placeholder="Buscar por producto, marca, sucursal o categoría">
                    </div>
                    <span class="quick-sale-count" id="quick-sale-count">{{ $quickSaleProducts->count() }} producto{{ $quickSaleProducts->count() === 1 ? '' : 's' }}</span>
                </div>

                <div class="quick-sale-empty d-none" id="quick-sale-empty">
                    <div class="quick-sale-empty-icon"><i class="bi bi-search-heart"></i></div>
                    <h6 class="fw-bold mb-1">No encontramos productos con ese criterio</h6>
                    <p class="text-muted mb-0">Prueba con otro término para seguir con la venta rápida.</p>
                </div>

                <div class="row g-3" id="quick-sale-grid">
                    @foreach($quickSaleProducts as $saleProduct)
                        <div class="col-sm-6 col-lg-4 col-xl-3 quick-sale-card-col" data-search="{{ $saleProduct['search_text'] }}">
                            <article class="quick-sale-card h-100">
                                <div class="quick-sale-card-media">
                                    @if($saleProduct['image_url'])
                                        <img src="{{ $saleProduct['image_url'] }}" alt="{{ $saleProduct['name'] }}">
                                    @else
                                        <div class="quick-sale-card-placeholder"><i class="bi bi-box-seam"></i></div>
                                    @endif
                                    <span class="quick-sale-origin">{{ $saleProduct['origin_label'] }}</span>
                                </div>
                                <div class="quick-sale-card-body">
                                    <div class="quick-sale-card-title">{{ $saleProduct['name'] }}</div>
                                    <div class="quick-sale-card-subtitle">{{ $saleProduct['brand'] ?: $saleProduct['category_name'] }}</div>
                                    <div class="quick-sale-card-facts">
                                        <span><strong>Sucursal:</strong> {{ $saleProduct['branch_name'] }}</span>
                                        <span><strong>Categoría:</strong> {{ $saleProduct['category_name'] }}</span>
                                        <span><strong>Stock:</strong> {{ number_format((float) $saleProduct['stock'], 2) }} und.</span>
                                    </div>
                                    <div class="quick-sale-card-footer">
                                        <div class="quick-sale-price">
                                            <small>Precio sugerido</small>
                                            <strong>{{ $currencySymbol }} {{ number_format((float) $saleProduct['price'], 2) }}</strong>
                                        </div>
                                        <x-btn icon="cart-check" :href="$saleProduct['sale_url']" class="quick-sale-go">Vender</x-btn>
                                    </div>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<div class="offcanvas offcanvas-start" tabindex="-1" id="appSidebarMobile" aria-labelledby="appSidebarMobileLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="appSidebarMobileLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="p-2">
            @include('layouts.partials.sidebar-menu')
        </nav>
    </div>
</div>

{{-- Modal Aperturar Caja --}}
@if($managerOpenableCashRegisters->isNotEmpty() || (!$userIsManager && $userCashRegister && !$userCashSession))
<div class="modal fade" id="openCashModal" tabindex="-1" aria-labelledby="openCashModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('cash-register.open') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="openCashModalLabel"><i class="bi bi-unlock"></i> Aperturar caja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    @if($userIsManager)
                        @if($managerOpenableCashRegisters->count() === 1)
                            {{-- Gerente con una sola caja: mostrar info, enviar ID oculto --}}
                            @php $singleCr = $managerOpenableCashRegisters->first(); $singleColor = $singleCr->branch?->color ?? '#0d6efd'; @endphp
                            <input type="hidden" name="cash_register_id" value="{{ $singleCr->id }}">
                            <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-3" style="border:2px solid {{ $singleColor }}; background: {{ $singleColor }}18;">
                                <div style="width:14px;height:48px;border-radius:6px;background:{{ $singleColor }};flex-shrink:0;"></div>
                                <div>
                                    <div class="fw-semibold">{{ $singleCr->name }}</div>
                                    @if($singleCr->branch)
                                        <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>{{ $singleCr->branch->name }}</small>
                                    @endif
                                </div>
                            </div>
                        @else
                            {{-- Gerente con múltiples cajas: tarjetas seleccionables --}}
                            <p class="text-muted small mb-2">Selecciona la caja a aperturar <span class="text-danger">*</span></p>
                            <div class="d-flex flex-row flex-wrap gap-2 mb-3" id="cashRegisterCards">
                                @foreach($managerOpenableCashRegisters as $cr)
                                    @php $branchColor = $cr->branch?->color ?? '#0d6efd'; @endphp
                                    <label class="cash-register-card d-flex align-items-center gap-3 p-3 rounded-3 flex-grow-1" style="cursor:pointer;border:2px solid #dee2e6;background:#fff;transition:border .15s,background .15s;min-width:160px;" data-color="{{ $branchColor }}">
                                        <input type="radio" name="cash_register_id" value="{{ $cr->id }}" class="d-none cash-register-radio">
                                        <div class="cash-register-bar" style="width:10px;height:44px;border-radius:5px;background:#dee2e6;flex-shrink:0;transition:background .15s;"></div>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $cr->name }}</div>
                                            @if($cr->branch)
                                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>{{ $cr->branch->name }}</small>
                                            @endif
                                        </div>
                                        <div class="cash-register-check" style="width:22px;height:22px;border-radius:50%;border:2px solid #dee2e6;background:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;">
                                            <i class="bi bi-check2 fs-base" style="color:#fff;display:none;"></i>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    @else
                        @php $nrColor = $userCashRegister->branch?->color ?? '#0d6efd'; @endphp
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3 mb-3" style="border:2px solid {{ $nrColor }}; background: {{ $nrColor }}18;">
                            <div style="width:14px;height:48px;border-radius:6px;background:{{ $nrColor }};flex-shrink:0;"></div>
                            <div>
                                <div class="fw-semibold">{{ $userCashRegister->name }}</div>
                                @if($userCashRegister->branch)
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i>{{ $userCashRegister->branch->name }}</small>
                                @endif
                            </div>
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">Monto de apertura ({{ $currencySymbol }}) <span class="text-danger">*</span></label>
                        <input type="number" step="1" name="opening_amount" class="form-control form-control-sm" required min="0" value="0" placeholder="0">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Notas (opcional)</label>
                        <textarea name="opening_notes" class="form-control form-control-sm" rows="2" placeholder="Observaciones de apertura..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-btn variant="secondary" type="button" data-bs-dismiss="modal">Cancelar</x-btn>
                    <x-btn variant="success" icon="unlock" type="submit">Aperturar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(isset($managerOpenableCashRegisters) && $managerOpenableCashRegisters->count() > 1)
<style>
.cash-register-card:hover { border-color: #adb5bd !important; background: #f8f9fa !important; }
.cash-register-card.is-selected { background: var(--cr-color-bg, #f0f4ff) !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var cards = document.querySelectorAll('.cash-register-card');
    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            var color = card.dataset.color || '#0d6efd';
            // deselect all
            cards.forEach(function (c) {
                c.style.borderColor = '#dee2e6';
                c.style.background  = '#fff';
                c.querySelector('.cash-register-bar').style.background = '#dee2e6';
                var chk = c.querySelector('.cash-register-check');
                chk.style.borderColor  = '#dee2e6';
                chk.style.background   = '#fff';
                chk.querySelector('i').style.display = 'none';
                c.classList.remove('is-selected');
                c.querySelector('.cash-register-radio').checked = false;
            });
            // select this card
            card.style.borderColor = color;
            card.style.background  = color + '14';
            card.querySelector('.cash-register-bar').style.background = color;
            var chk = card.querySelector('.cash-register-check');
            chk.style.borderColor  = color;
            chk.style.background   = color;
            chk.querySelector('i').style.display = 'block';
            card.classList.add('is-selected');
            card.querySelector('.cash-register-radio').checked = true;
        });
    });

    // Validate a card is selected before submitting
    var openForm = document.querySelector('#openCashModal form');
    if (openForm) {
        openForm.addEventListener('submit', function (e) {
            var selected = openForm.querySelector('.cash-register-radio:checked');
            if (!selected) {
                e.preventDefault();
                var cardGroup = document.getElementById('cashRegisterCards');
                cardGroup.style.outline = '2px solid #dc3545';
                cardGroup.style.borderRadius = '8px';
                if (!document.getElementById('crSelectError')) {
                    var err = document.createElement('div');
                    err.id = 'crSelectError';
                    err.className = 'text-danger small mt-1';
                    err.textContent = 'Debes seleccionar una caja para aperturar.';
                    cardGroup.after(err);
                }
            }
        });
        document.querySelectorAll('.cash-register-card').forEach(function (c) {
            c.addEventListener('click', function () {
                var cg = document.getElementById('cashRegisterCards');
                if (cg) cg.style.outline = 'none';
                var err = document.getElementById('crSelectError');
                if (err) err.remove();
            });
        });
    }
});
</script>
@endif

{{-- Modal Cerrar Caja --}}
@if($userCashRegister && $userCashSession)
@php
    $sessIncome  = (float) $userCashSession->cashMovements()->where('type', 'income')->sum('amount');
    $sessExpense = (float) $userCashSession->cashMovements()->where('type', 'expense')->sum('amount');
    $expectedClosing = round((float) $userCashSession->opening_amount + $sessIncome - $sessExpense, 2);
@endphp
<div class="modal fade" id="closeCashModal" tabindex="-1" aria-labelledby="closeCashModalLabel" aria-hidden="true" data-expected="{{ $expectedClosing }}">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('cash-register.close') }}" method="POST">
                @csrf
                <div class="modal-header close-cash-header">
                    <h5 class="modal-title" id="closeCashModalLabel">
                        <i class="bi bi-lock"></i> Cerrar caja
                        @if($userCashRegister?->branch)
                            <small class="text-muted fw-normal ms-1 fs-xs">· {{ $userCashRegister->branch->name }}</small>
                        @endif
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    {{-- Info de la caja --}}
                    <div class="alert alert-light border mb-3 py-2 px-3 small mb-3">
                        <strong>{{ $userCashRegister->name }}</strong>
                        @if($userCashRegister->branch)
                            · <i class="bi bi-geo-alt me-1"></i>{{ $userCashRegister->branch->name }}
                        @endif
                        <br>
                        <span class="text-muted">Abierta desde: {{ $userCashSession->opened_at->format('d/m/Y H:i') }}</span>
                    </div>

                    {{-- Cuánto debería haber (esperado) --}}
                    <div class="rounded-3 p-3 mb-3" style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border:1px solid #bfdbfe;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-primary fw-semibold small text-uppercase" style="letter-spacing:.04em;">Deberías tener en caja</span>
                            <span class="fw-bold text-primary fs-lg">{{ $currencySymbol }} {{ number_format($expectedClosing, 2) }}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mt-2 small text-muted">
                            <span>Apertura: <strong>{{ $currencySymbol }} {{ number_format((float) $userCashSession->opening_amount, 2) }}</strong></span>
                            <span class="text-success">+ Ingresos: <strong>{{ $currencySymbol }} {{ number_format($sessIncome, 2) }}</strong></span>
                            <span class="text-danger">− Egresos: <strong>{{ $currencySymbol }} {{ number_format($sessExpense, 2) }}</strong></span>
                        </div>
                    </div>

                    {{-- Monto de cierre (contado) --}}
                    <div class="mb-2">
                        <label class="form-label mb-1 fw-semibold">Monto contado en caja ({{ $currencySymbol }}) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">{{ $currencySymbol }}</span>
                            <input type="number" step="0.01" name="closing_amount" id="closing_amount_input"
                                   class="form-control form-control-lg text-end fw-semibold no-upper"
                                   required min="0" placeholder="0.00">
                            <x-btn variant="secondary" icon="magic" type="button" id="close-use-expected" title="Usar el monto esperado">Usar esperado</x-btn>
                        </div>
                        <div class="small mt-2" id="close-cash-diff"></div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label mb-1">Notas (opcional)</label>
                        <textarea name="closing_notes" class="form-control form-control-sm" rows="2" placeholder="Observaciones de cierre..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <x-btn variant="secondary" type="button" data-bs-dismiss="modal">Cancelar</x-btn>
                    <x-btn variant="warning" icon="lock" type="submit">Cerrar caja</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Registrar Gasto --}}
@if($canRegisterExpense)
<div class="modal fade expense-modal" id="expenseCashModal" tabindex="-1" aria-hidden="true" data-available="{{ number_format($expectedClosing, 2, '.', '') }}">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius:14px; overflow:hidden;">
            <div class="modal-header border-0 text-white" style="background:linear-gradient(135deg,#dc2626,#b91c1c); padding:12px 16px;">
                <div>
                    <h6 class="modal-title fw-bold mb-0"><i class="bi bi-dash-circle me-1"></i>Registrar gasto</h6>
                    <small class="opacity-75 fs-xs">{{ $userCashRegister->name }}{{ $userCashRegister->branch ? ' · ' . $userCashRegister->branch->name : '' }}</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" style="padding:14px 16px;">
                {{-- Disponible en caja --}}
                <div class="d-flex justify-content-between align-items-center px-3 py-2 mb-2"
                     style="background:#fef2f2; border:1px solid #fecaca; border-radius:10px;">
                    <span class="text-danger fw-semibold text-uppercase fs-xs" style="letter-spacing:.04em;">Disponible en caja</span>
                    <span class="fw-bold text-danger fs-md">{{ $currencySymbol }} {{ number_format($expectedClosing, 2) }}</span>
                </div>

                <form method="POST" action="{{ route('cash-register.session.expense', $userCashSession) }}" id="expense-form">
                    @csrf
                    <input type="hidden" name="submission_token" value="{{ \Illuminate\Support\Str::uuid() }}">

                    <div class="mb-2">
                        <label class="form-label">Tipo de gasto <span class="text-danger">*</span></label>
                        <select name="category" id="exp-category" class="form-select form-select-sm" required>
                            <option value="">Selecciona…</option>
                            <option value="service_expense">Servicio (básico / externo)</option>
                            <option value="staff_payment">Pago a personal</option>
                            <option value="operating_expense">Otro gasto operativo</option>
                        </select>
                    </div>

                    {{-- Solo para "Pago a personal" --}}
                    <div class="mb-2 d-none" id="exp-personal-wrap">
                        <label class="form-label">Personal <span class="text-danger">*</span></label>
                        <select name="personal_id" id="exp-personal" class="form-select form-select-sm">
                            <option value="">Selecciona el personal…</option>
                            @foreach($expensePersonals as $p)
                                <option value="{{ $p->id }}">{{ $p->full_name }}</option>
                            @endforeach
                        </select>
                        @if($expensePersonals->isEmpty())
                            <div class="form-text text-danger">No hay personal activo registrado en la empresa.</div>
                        @endif
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Monto ({{ $currencySymbol }}) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" max="{{ number_format($expectedClosing, 2, '.', '') }}"
                               name="amount" id="exp-amount"
                               class="form-control form-control-sm no-upper" placeholder="0.00" required autocomplete="off">
                    </div>

                    <div id="exp-preview" class="alert alert-light border d-flex align-items-center gap-2 py-1 px-2 mb-2">
                        <i class="bi bi-arrow-right-circle text-muted"></i>
                        <span>Ingresa un monto para ver cómo queda la caja.</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2" maxlength="500"
                                  placeholder="Opcional (ej. pago de luz del mes)"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <x-btn variant="secondary" type="button" data-bs-dismiss="modal" id="exp-cancel">Cancelar</x-btn>
                        <x-btn variant="danger" icon="check-lg" type="submit" id="exp-submit" disabled>Registrar gasto</x-btn>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endif

@if($userCashRegister && $userCashSession && $canRegisterExpense)
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('expenseCashModal');
        if (!modal) return;

        const fmt = (n) => Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const available = parseFloat(modal.dataset.available || 0);
        const amountInput = document.getElementById('exp-amount');
        const preview = document.getElementById('exp-preview');
        const submitBtn = document.getElementById('exp-submit');
        const categorySelect = document.getElementById('exp-category');
        const personalWrap = document.getElementById('exp-personal-wrap');
        const personalSelect = document.getElementById('exp-personal');
        let submitting = false;

        // "Pago a personal" exige elegir a quién se paga.
        function isStaffPayment() {
            return categorySelect.value === 'staff_payment';
        }

        function togglePersonal() {
            const show = isStaffPayment();
            personalWrap.classList.toggle('d-none', !show);
            personalSelect.required = show;
            if (!show) personalSelect.value = '';
            updatePreview();
        }

        function updatePreview() {
            if (submitting) return; // no reactivar el botón mientras se envía
            const amount = parseFloat(amountInput.value || 0);
            preview.classList.remove('alert-light', 'alert-success', 'alert-danger');

            if (!amount || amount <= 0) {
                preview.classList.add('alert-light');
                preview.innerHTML = '<i class="bi bi-arrow-right-circle text-muted"></i> <span>Ingresa un monto para ver cómo queda la caja.</span>';
                submitBtn.disabled = true;
                return;
            }

            if (amount > available) {
                preview.classList.add('alert-danger');
                preview.innerHTML = '<i class="bi bi-exclamation-triangle"></i> <span>El gasto supera el efectivo disponible ({{ $currencySymbol }} ' + fmt(available) + ').</span>';
                submitBtn.disabled = true;
                return;
            }

            preview.classList.add('alert-success');
            preview.innerHTML = '<i class="bi bi-arrow-right-circle"></i> <span>Saldo actual <strong>{{ $currencySymbol }} ' + fmt(available)
                + '</strong> → <strong>{{ $currencySymbol }} ' + fmt(available - amount) + '</strong></span>';
            submitBtn.disabled = isStaffPayment() && !personalSelect.value;
        }

        amountInput.addEventListener('input', updatePreview);
        categorySelect.addEventListener('change', togglePersonal);
        personalSelect.addEventListener('change', updatePreview);

        // Al enviar: bloquear el botón y mostrar "en proceso" para evitar
        // registros duplicados por varios clics.
        const form = document.getElementById('expense-form');
        form.addEventListener('submit', function (e) {
            if (submitting) {
                e.preventDefault();
                return;
            }
            submitting = true;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Registrando…';
            document.getElementById('exp-cancel').disabled = true;
        });

        modal.addEventListener('show.bs.modal', function (e) {
            // Mientras se procesa, no reabrir/reiniciar el formulario.
            if (submitting) {
                e.preventDefault();
                return;
            }
            form.reset();
            togglePersonal();
        });
        modal.addEventListener('hide.bs.modal', function (e) {
            if (submitting) e.preventDefault();
        });
        modal.addEventListener('shown.bs.modal', () => amountInput.focus());
    });
</script>
@endpush
@endif

@push('styles')
<style>
    /* Modal "Registrar gasto": estilo compacto */
    .expense-modal .form-label { font-size: var(--fs-xs); font-weight: 600; margin-bottom: 2px; color: #374151; }
    .expense-modal .form-select-sm,
    .expense-modal .form-control-sm { font-size: var(--fs-sm); }
    .expense-modal #exp-preview { font-size: var(--fs-xs); }
    .expense-modal .form-text { font-size: var(--fs-xs); }

    .cash-breakdown-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
    }

    .close-cash-header {
        background: linear-gradient(135deg, #dbeafe, #fef3c7);
    }

    .close-cash-panel {
        border: 1px solid #e6e6e6;
        border-radius: 10px;
        padding: 10px;
        background: #fff;
    }

    .close-cash-summary {
        border-radius: 10px;
        margin-bottom: 0;
    }

    .cash-breakdown-item {
        border: 1px solid #e6e6e6;
        border-radius: 8px;
        padding: 8px;
        background: #fafafa;
    }

    .quick-sale-modal {
        border: 0;
        border-radius: 24px;
        overflow: hidden;
        background:
            radial-gradient(circle at top right, rgba(6, 182, 212, .12), transparent 22%),
            radial-gradient(circle at bottom left, rgba(37, 99, 235, .10), transparent 26%),
            linear-gradient(180deg, #f9fcff 0%, #f4f8ff 100%);
    }

    .quick-sale-kicker {
        display: inline-flex;
        align-items: center;
        padding: .35rem .7rem;
        border-radius: 999px;
        background: rgba(255,255,255,.88);
        border: 1px solid rgba(6, 182, 212, .14);
        color: #0f766e;
        font-size: var(--fs-xs);
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
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
        font-size: var(--fs-sm);
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
        font-size: var(--fs-lg);
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
        font-size: var(--fs-xs);
        font-weight: 700;
        backdrop-filter: blur(8px);
    }

    .quick-sale-card-body {
        padding: .8rem .85rem .9rem;
    }

    .quick-sale-card-title {
        font-size: var(--fs-base);
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
        min-height: 2rem;
    }

    .quick-sale-card-subtitle {
        margin-top: .15rem;
        color: #667085;
        font-size: var(--fs-xs);
        min-height: 1rem;
    }

    .quick-sale-card-facts {
        margin-top: .55rem;
        padding-top: .55rem;
        border-top: 1px dashed #dbe5f3;
        display: flex;
        flex-direction: column;
        gap: .2rem;
        color: #475467;
        font-size: var(--fs-xs);
    }

    .quick-sale-card-facts strong {
        color: #0f172a;
    }

    .quick-sale-card-footer {
        margin-top: .7rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
    }

    .quick-sale-price {
        display: flex;
        flex-direction: column;
    }

    .quick-sale-price small {
        color: #667085;
        font-size: var(--fs-xs);
    }

    .quick-sale-price strong {
        color: #0f766e;
        font-size: var(--fs-base);
        line-height: 1.1;
    }

    .quick-sale-empty {
        border: 1px dashed #d4e1f5;
        border-radius: 20px;
        background: rgba(255,255,255,.82);
        text-align: center;
        padding: 2rem 1rem;
    }

    .quick-sale-empty-icon {
        width: 64px;
        height: 64px;
        border-radius: 20px;
        margin: 0 auto .9rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #e0f2fe 0%, #eef5ff 100%);
        color: #0284c7;
        font-size: var(--fs-xl);
    }

    @media (max-width: 991.98px) {
        .cash-breakdown-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .quick-sale-card-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .quick-sale-go {
            width: 100%;
        }
    }

    @media (max-width: 575.98px) {
        .cash-breakdown-grid {
            grid-template-columns: 1fr;
        }

    }
</style>
@endpush

@push('scripts')
<script>
    // Botón de menú de la cabecera: en computadora oculta/muestra el menú lateral (se recuerda);
    // en pantallas chicas abre el menú deslizable.
    document.addEventListener('DOMContentLoaded', function () {
        // Menú del celular: al abrirse, muestra la opción activa.
        const mobilePanel = document.getElementById('appSidebarMobile');
        if (mobilePanel) {
            mobilePanel.addEventListener('shown.bs.offcanvas', function () {
                const active = mobilePanel.querySelector('.app-link.active');
                if (active) active.scrollIntoView({ block: 'center' });
            });
        }

        const toggle = document.getElementById('sidebarToggle');
        const shell = document.getElementById('appShell');
        if (!toggle || !shell) return;
        toggle.addEventListener('click', function () {
            if (window.innerWidth < 992) {
                const panel = document.getElementById('appSidebarMobile');
                if (panel && window.bootstrap) bootstrap.Offcanvas.getOrCreateInstance(panel).show();
                return;
            }
            const hidden = shell.classList.toggle('sidebar-hidden');
            try { localStorage.setItem('sidebarHidden', hidden ? '1' : '0'); } catch (e) {}
        });
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@php
    $validationToastFallbacks = [
        'validation.min.string' => 'La contraseña debe tener al menos 8 caracteres.',
        'validation.unique' => 'El correo electrónico ya está registrado.',
        'validation.required' => 'Completa los campos obligatorios.',
        'validation.email' => 'Ingresa un correo electrónico válido.',
        'validation.confirmed' => 'La confirmación de contraseña no coincide.',
    ];
    $firstErrorForToast = $errors->first();
    if (is_string($firstErrorForToast) && isset($validationToastFallbacks[$firstErrorForToast])) {
        $firstErrorForToast = $validationToastFallbacks[$firstErrorForToast];
    }
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    const quickSaleSearch = document.getElementById('quick-sale-search');
    const quickSaleGrid = document.getElementById('quick-sale-grid');
    const quickSaleEmpty = document.getElementById('quick-sale-empty');
    const quickSaleCount = document.getElementById('quick-sale-count');

    if (quickSaleSearch && quickSaleGrid && quickSaleEmpty && quickSaleCount) {
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
    }

    const closeCashModal = document.getElementById('closeCashModal');
    if (closeCashModal) {
        const expected = Number(closeCashModal.dataset.expected || 0);
        const input = closeCashModal.querySelector('input[name="closing_amount"]');
        const diffEl = document.getElementById('close-cash-diff');
        const useBtn = document.getElementById('close-use-expected');

        const fmt = (n) => '{{ $currencySymbol }} ' + Number(n).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const updateDiff = () => {
            if (!diffEl) return;
            if (input.value === '' || input.value === null) { diffEl.innerHTML = ''; return; }
            const declared = parseFloat(input.value || 0);
            const diff = Math.round((declared - expected) * 100) / 100;
            if (diff === 0) {
                diffEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Cuadra con lo esperado.</span>';
            } else if (diff > 0) {
                diffEl.innerHTML = '<span class="text-warning"><i class="bi bi-arrow-up-circle-fill me-1"></i>Sobra ' + fmt(diff) + ' respecto a lo esperado.</span>';
            } else {
                diffEl.innerHTML = '<span class="text-danger"><i class="bi bi-arrow-down-circle-fill me-1"></i>Falta ' + fmt(Math.abs(diff)) + ' respecto a lo esperado.</span>';
            }
        };

        if (input) input.addEventListener('input', updateDiff);
        if (useBtn && input) {
            useBtn.addEventListener('click', () => { input.value = expected.toFixed(2); updateDiff(); input.focus(); });
        }
        updateDiff();
    }

    if (typeof Swal === 'undefined') {
        return;
    }

    const toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4500,
        timerProgressBar: true,
        didOpen: (t) => {
            t.addEventListener('mouseenter', Swal.stopTimer);
            t.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    @if($message = session('success'))
        toast.fire({ icon: 'success', title: @json($message) });
    @endif

    @if($message = session('error'))
        toast.fire({ icon: 'error', title: @json($message) });
    @endif

    @if($errors->any())
        toast.fire({ icon: 'error', title: @json($firstErrorForToast) });
    @endif
});
</script>
@endpush
@endsection
