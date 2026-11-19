{{-- Aviso + botón "Separar productos" (solo super admin; $sharedPreview = ProductSplitService::preview() o null).
     Se usa en Productos e Inventario. --}}
@if(($sharedPreview['products'] ?? 0) > 0)
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2">
        <i class="bi bi-exclamation-triangle"></i>
        <div class="flex-grow-1 small">
            Hay <strong>{{ $sharedPreview['products'] }}</strong> productos compartidos por
            <strong>{{ $sharedPreview['origins'] }}</strong> préstamos, compras o ingresos (por ejemplo garrafas).
            Hoy <strong>{{ $sharedPreview['in_stock'] }}</strong> de esas unidades están en inventario; el resto ya salió
            (préstamos liquidados, ventas o préstamos eliminados).
            Al separarlos, cada préstamo o compra —también los que ya salieron, para que su historial quede con su propio
            producto y código— tendrá su producto: se crearán <strong>{{ $sharedPreview['new_products'] }}</strong> productos nuevos.
        </div>
        <form action="{{ route('products.split-shared') }}" method="POST" id="split-shared-form" class="m-0">
            @csrf
            <x-btn variant="warning" type="submit" icon="scissors" id="split-shared-btn">Separar productos</x-btn>
        </form>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('split-shared-form');
        if (!form) return;
        const btn = document.getElementById('split-shared-btn');
        let confirmed = false;

        form.addEventListener('submit', function (e) {
            if (confirmed) return;
            e.preventDefault();
            if (btn.disabled) return;

            const send = function () {
                confirmed = true;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Separando...';
                form.submit();
            };

            if (!window.Swal) {
                if (confirm('¿Separar los productos compartidos? Cada préstamo o compra tendrá su propio producto.')) send();
                return;
            }

            Swal.fire({
                title: '¿Separar productos compartidos?',
                html: 'Cada préstamo, compra o ingreso ({{ $sharedPreview['origins'] }} en total, incluidos los que ya salieron del inventario) tendrá su <strong>propio producto</strong> con cantidad 1, su costo y su código (PR-, CO- o PD-): se crearán <strong>{{ $sharedPreview['new_products'] }}</strong> productos nuevos.<br>Sus movimientos de inventario, garantías y ventas pasan al producto nuevo; el stock no cambia. No se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, separar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d97706',
            }).then(function (r) { if (r.isConfirmed) send(); });
        });
    });
    </script>
    @endpush
@endif
