@extends('layouts.app')

@section('title', 'Detalle sucursal')

@section('page')
<div class="container-fluid">
    <x-page-header :title="$branch->name" icon="diagram-2" subtitle="Detalle general de la sucursal." :back="route('branches.index')">
        <x-btn icon="pencil" :href="route('branches.edit', $branch)">Editar</x-btn>
    </x-page-header>
    <div class="row g-4">
        <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body"><p><strong>Empresa:</strong> {{ $branch->company?->name }}</p><p><strong>Código:</strong> {{ $branch->code ?: '-' }}</p><p><strong>Almacén principal:</strong> {{ $branch->warehouse?->name ?: '-' }}</p><p><strong>Encargado:</strong> {{ $branch->manager_name ?: '-' }}</p><p><strong>Correo:</strong> {{ $branch->email ?: '-' }}</p><p><strong>Teléfono:</strong> {{ $branch->phone ?: '-' }}</p><p><strong>Dirección:</strong> {{ $branch->address ?: '-' }}</p></div></div></div>
        <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body"><h6 class="fw-bold">Almacén principal</h6><div class="mt-3"><p class="mb-1"><strong>Nombre:</strong> {{ $branch->warehouse?->name ?: '-' }}</p><p class="mb-1"><strong>Código:</strong> {{ $branch->warehouse?->code ?: '-' }}</p><p class="mb-0"><strong>Ubicación:</strong> {{ $branch->warehouse?->location ?: '-' }}</p></div></div></div></div>
    </div>

    {{-- ── Zona de peligro: reinicio de sucursal ─────────────────── --}}
    @php
        $canResetBranch = auth()->user()->is_super_admin
            || auth()->user()->hasPermissionInCompany('branches.reset', auth()->user()->getCurrentCompany());
    @endphp
    @if($canResetBranch)
    <div class="card border-danger shadow-sm mt-4">
        <div class="card-header bg-danger-subtle text-danger fw-bold">
            <i class="bi bi-exclamation-octagon-fill me-1"></i> Zona de peligro
        </div>
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div style="max-width:640px;">
                    <h6 class="fw-bold mb-1">Reiniciar sucursal</h6>
                    <p class="text-muted small mb-2">
                        Borra <strong>todos los registros operativos</strong> de esta sucursal, sin posibilidad de deshacer:
                        préstamos ({{ $resetCounts['loans'] }}), ventas ({{ $resetCounts['sales'] }}), compras ({{ $resetCounts['purchases'] }}),
                        con sus pagos, cuotas, intereses, contratos e imágenes; y el <strong>historial de caja</strong> (sesiones y movimientos).
                        @if($branch->warehouse_id && $warehouseExclusive)
                            También se <strong>vacía el stock</strong> del almacén de la sucursal.
                        @elseif($branch->warehouse_id && !$warehouseExclusive)
                            El <strong>inventario NO se borrará</strong> porque el almacén es compartido con otra sucursal.
                        @endif
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-shield-check me-1"></i>
                        Se conservan: el <strong>personal</strong>, las <strong>cajas</strong> configuradas, los <strong>clientes</strong> y los <strong>productos</strong>.
                        Los códigos correlativos (PR-, VE-, CO-) volverán a numerar desde 1.
                    </p>
                </div>
                <x-btn variant="danger" icon="trash3" data-bs-toggle="modal" data-bs-target="#branchResetModal">Reiniciar sucursal</x-btn>
            </div>
        </div>
    </div>

    {{-- Modal de confirmación --}}
    <div class="modal fade" id="branchResetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius:16px;overflow:hidden;">
                <div class="modal-header border-0 bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Reiniciar «{{ $branch->name }}»</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-danger small">
                        <strong>Esta acción es irreversible.</strong> Se borrarán préstamos, ventas, compras, sus pagos e historial de caja
                        @if($branch->warehouse_id && $warehouseExclusive) y el stock del almacén @endif de esta sucursal.
                    </div>
                    <form method="POST" action="{{ route('branches.reset', $branch) }}" id="branchResetForm">
                        @csrf
                        <label class="form-label small fw-semibold">
                            Para confirmar, escribe el nombre exacto de la sucursal:
                            <span class="text-danger">{{ $branch->name }}</span>
                        </label>
                        <input type="text" name="confirm_name" id="branchResetConfirm" class="form-control no-upper"
                               autocomplete="off" placeholder="{{ $branch->name }}">
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <x-btn variant="secondary" data-bs-dismiss="modal">Cancelar</x-btn>
                    <x-btn variant="danger" type="submit" form="branchResetForm" id="branchResetSubmit" icon="trash3" disabled>Reiniciar definitivamente</x-btn>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('branchResetConfirm');
    var submit = document.getElementById('branchResetSubmit');
    if (!input || !submit) return;
    var target = @json($branch->name);
    var norm = function (s) { return (s || '').trim().toLowerCase(); };
    input.addEventListener('input', function () {
        submit.disabled = norm(input.value) !== norm(target);
    });
});
</script>
@endpush