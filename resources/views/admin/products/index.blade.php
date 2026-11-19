@extends('layouts.app')

@section('title', 'Productos')

@section('page')
<div class="container-fluid">
    <x-page-header title="Productos" icon="box-seam" subtitle="Cada producto es un artículo único: se crea al ingresarlo al inventario, con un préstamo o con una compra.">
        @if(auth()->user()->hasPermissionInCompany('products.create', auth()->user()->getCurrentCompany()))
            <x-btn icon="plus-lg" :href="route('inventory.stock-entry')">Ingresar producto</x-btn>
        @endif
    </x-page-header>
    @include('admin.products._split-shared', ['sharedPreview' => $sharedPreview ?? null])
    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light"><tr><th>Código</th><th>Producto</th><th>SKU</th><th>Categoría crédito</th><th>Empresa</th><th>Unidad</th><th class="text-end">Costo</th><th class="text-end">Precio</th><th class="text-end">Acciones</th></tr></thead><tbody>@forelse($products as $product)<tr><td>@if($product->code)<span class="code-pill">{{ $product->code }}</span>@endif</td><td>{{ $product->name }}</td><td class="text-muted small">{{ $product->sku }}</td><td>{{ $product->creditCategory?->name ?: 'Sin categoría' }}</td><td>{{ $product->company?->name ?: '-' }}</td><td>{{ $product->unit }}</td><td class="text-end">{{ $currencySymbol }} {{ number_format($product->cost, 2) }}</td><td class="text-end">{{ $currencySymbol }} {{ number_format($product->price, 2) }}</td><td class="text-end"><x-icon-btn icon="pencil" label="Editar" :href="route('products.edit', $product)" /><form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline">@csrf @method('DELETE')<x-icon-btn icon="trash" label="Eliminar" variant="danger" type="submit" onclick="return confirm('¿Eliminar producto?')" /></form></td></tr>@empty<tr><td colspan="9" class="text-center py-5 text-muted">No hay productos registrados.</td></tr>@endforelse</tbody></table></div></div></div>
    <div class="mt-4 d-flex justify-content-center">{{ $products->links() }}</div>
</div>
@endsection
