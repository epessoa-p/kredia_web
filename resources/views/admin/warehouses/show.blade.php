@extends('layouts.app')

@section('title', 'Detalle almacén')

@push('styles')
<style>
    .collateral-status-badge { font-size: var(--fs-sm); padding: .35em .7em; border-radius: 2rem; }
    .collateral-card { border-radius: .75rem; border: 1px solid #e9ecef; transition: box-shadow .2s; }
    .collateral-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .product-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: var(--fs-md); }
</style>
@endpush

@section('page')
<div class="container-fluid">
    <x-page-header :title="$warehouse->name" icon="building-add" subtitle="Registro de ingresos, salidas y productos en custodia del almacén." :back="route('warehouses.index')">
        <x-btn variant="success" icon="box-arrow-in-down" :href="route('inventory.stock-entry', ['warehouse_id' => $warehouse->id])">Ingresar producto</x-btn>
        <x-btn icon="pencil" :href="route('warehouses.edit', $warehouse)">Editar</x-btn>
    </x-page-header>

    {{-- Productos en custodia --}}
    @if($collaterals->count() > 0)
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0"><i class="bi bi-box-seam text-mod me-1"></i> Productos en custodia</h6>
                <span class="badge bg-primary rounded-pill">{{ $collaterals->count() }} productos</span>
            </div>
            <div class="row g-3">
                @foreach($collaterals as $col)
                    @php
                        $statusConfig = match($col->status) {
                            'retained' => ['label' => 'En préstamo', 'bg' => 'bg-info', 'icon' => 'bi-lock-fill', 'iconBg' => 'bg-info bg-opacity-10 text-info'],
                            'stored' => ['label' => 'En retención (mora)', 'bg' => 'bg-warning text-dark', 'icon' => 'bi-archive-fill', 'iconBg' => 'bg-warning bg-opacity-10 text-warning'],
                            'sellable' => ['label' => 'Disponible', 'bg' => 'bg-success', 'icon' => 'bi-check-circle-fill', 'iconBg' => 'bg-success bg-opacity-10 text-success'],
                            default => ['label' => ucfirst($col->status), 'bg' => 'bg-secondary', 'icon' => 'bi-question-circle', 'iconBg' => 'bg-secondary bg-opacity-10'],
                        };
                    @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="collateral-card p-3 d-flex align-items-start gap-3">
                            <div class="product-icon {{ $statusConfig['iconBg'] }} flex-shrink-0">
                                <i class="bi {{ $statusConfig['icon'] }}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <strong class="small">{{ $col->product?->name ?? 'Producto' }}</strong>
                                    <span class="collateral-status-badge {{ $statusConfig['bg'] }}">{{ $statusConfig['label'] }}</span>
                                </div>
                                <div class="small text-muted">
                                    <div><i class="bi bi-person me-1"></i>{{ $col->loan?->client?->name ?? '-' }}</div>
                                    <div><i class="bi bi-hash me-1"></i>Préstamo #{{ $col->loan_id }}</div>
                                    <div><i class="bi bi-calendar me-1"></i>Ingreso: {{ $col->retained_at?->format('d/m/Y') ?? '-' }}</div>
                                    @if($col->expected_release_date)
                                        <div><i class="bi bi-calendar-check me-1"></i>Vence: {{ $col->expected_release_date->format('d/m/Y') }}</div>
                                    @endif
                                    @if($col->status === 'stored' && $col->loan?->creditCategory)
                                        @php $graceEnd = $col->expected_release_date->copy()->addDays((int) $col->loan->creditCategory->penalty_grace_days); @endphp
                                        <div class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Gracia vence: {{ $graceEnd->format('d/m/Y') }}</div>
                                    @endif
                                    @if($col->status === 'sellable')
                                        <div class="text-success fw-semibold"><i class="bi bi-check-circle me-1"></i>Disponible desde {{ $col->sellable_at?->format('d/m/Y') ?? '-' }}</div>
                                    @endif
                                </div>
                                <x-btn variant="secondary" icon="eye" class="mt-2" :href="route('loans.show', $col->loan_id)">Ver préstamo</x-btn>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <p><strong>Empresa:</strong> {{ $warehouse->company?->name }}</p>
                    <p><strong>Sucursal principal:</strong> {{ $warehouse->primaryBranch?->name ?: 'No asignada' }}</p>
                    <p><strong>Código:</strong> {{ $warehouse->code }}</p>
                    <p><strong>Ubicación:</strong> {{ $warehouse->location ?: '-' }}</p>
                    <p><strong>Descripción:</strong> {{ $warehouse->description ?: '-' }}</p>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Nuevo movimiento</h6>
                    <form action="{{ route('warehouses.movements.store', $warehouse) }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Producto</label>
                            <select name="product_id" class="form-select form-select-sm" required>
                                <option value="">Seleccionar</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}">{{ $product->code }} · {{ $product->name }} ({{ $product->sku }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select name="type" class="form-select form-select-sm" required>
                                <option value="in">Ingreso</option>
                                <option value="out">Salida</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cantidad</label>
                            <input type="number" step="0.01" name="quantity" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Costo unitario</label>
                            <input type="number" step="0.01" name="unit_cost" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha</label>
                            <input type="datetime-local" name="movement_date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Referencia</label>
                            <input type="text" name="reference" class="form-control form-control-sm">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea name="notes" class="form-control form-control-sm" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <x-btn type="submit" icon="check-lg" :block="true">Guardar movimiento</x-btn>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history text-muted me-1"></i> Historial de movimientos</h6>
                        <span class="badge bg-light text-dark">{{ $warehouse->inventoryMovements->count() }} movimientos</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Producto</th>
                                    <th>Tipo</th>
                                    <th>Referencia</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Costo</th>
                                    <th>Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($warehouse->inventoryMovements as $movement)
                                    <tr>
                                        <td>{{ $movement->movement_date?->format('d/m/Y H:i') }}</td>
                                        <td>{{ $movement->product?->name ?: '-' }}</td>
                                        <td>
                                            <span class="badge {{ $movement->type === 'in' ? 'bg-success' : 'bg-warning text-dark' }}">
                                                {{ $movement->type === 'in' ? 'Ingreso' : 'Salida' }}
                                            </span>
                                        </td>
                                        <td class="small text-muted">{{ $movement->reference ?: '-' }}</td>
                                        <td class="text-end">{{ number_format($movement->quantity, 2) }}</td>
                                        <td class="text-end">{{ $currencySymbol }} {{ number_format($movement->unit_cost ?? 0, 2) }}</td>
                                        <td>{{ $movement->user?->name ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">Sin movimientos registrados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection