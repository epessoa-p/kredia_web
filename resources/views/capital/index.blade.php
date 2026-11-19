@extends('layouts.app')

@section('title', 'Capital de Empresa')

@push('styles')
<style>
    .cap-stat {
        border-radius: 14px;
        padding: 20px 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        background: #fff;
        box-shadow: 0 2px 10px rgba(0,0,0,.07);
        transition: box-shadow .2s;
    }
    .cap-stat:hover { box-shadow: 0 4px 18px rgba(0,0,0,.12); }
    .cap-stat-icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        display: grid; place-items: center;
        font-size: var(--fs-lg);
        flex-shrink: 0;
    }
    .cap-stat-label { font-size: var(--fs-xs); color: #888; text-transform: uppercase; letter-spacing: .04em; }
    .cap-stat-value { font-size: var(--fs-lg); font-weight: 700; line-height: 1.2; }
    .cap-stat-sub   { font-size: var(--fs-xs); color: #aaa; margin-top: 2px; }

    .entry-row { transition: background .15s; }
    .entry-row:hover { background: #f8f9ff; }

    .type-badge-input      { background: #e8f5e9; color: #2e7d32; }
    .type-badge-withdrawal { background: #fce4ec; color: #c62828; }
    .type-badge {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 10px; border-radius: 999px;
        font-size: var(--fs-xs); font-weight: 600;
    }
</style>
@endpush

@section('page')
<div class="container-fluid">

    {{-- Header --}}
    <x-page-header title="Capital de Empresa" icon="bank2" subtitle="Registro de aportes y retiros del propietario">
        <x-btn icon="plus-lg" data-bs-toggle="modal" data-bs-target="#modalCapital">Registrar movimiento</x-btn>
    </x-page-header>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    @endif

    {{-- KPI Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="cap-stat">
                <div class="cap-stat-icon" style="background:#e8f5e9; color:#2e7d32;">
                    <i class="bi bi-arrow-down-circle-fill"></i>
                </div>
                <div>
                    <div class="cap-stat-label">Total Aportes</div>
                    <div class="cap-stat-value text-success">{{ $currencySymbol }} {{ number_format($totalInput, 2, ',', '.') }}</div>
                    <div class="cap-stat-sub">Capital ingresado</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="cap-stat">
                <div class="cap-stat-icon" style="background:#fce4ec; color:#c62828;">
                    <i class="bi bi-arrow-up-circle-fill"></i>
                </div>
                <div>
                    <div class="cap-stat-label">Total Retiros</div>
                    <div class="cap-stat-value text-danger">{{ $currencySymbol }} {{ number_format($totalWithdrawal, 2, ',', '.') }}</div>
                    <div class="cap-stat-sub">Capital retirado</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="cap-stat" style="background: {{ $netCapital >= 0 ? 'linear-gradient(135deg,#f0f7ff,#e8f0fe)' : 'linear-gradient(135deg,#fff0f0,#ffe0e0)' }};">
                <div class="cap-stat-icon" style="background: {{ $netCapital >= 0 ? '#e3f2fd' : '#ffebee' }}; color: {{ $netCapital >= 0 ? '#1565c0' : '#c62828' }};">
                    <i class="bi bi-bank2"></i>
                </div>
                <div>
                    <div class="cap-stat-label">Capital Neto</div>
                    <div class="cap-stat-value" style="color: {{ $netCapital >= 0 ? '#1565c0' : '#c62828' }};">
                        {{ $currencySymbol }} {{ number_format(abs($netCapital), 2, ',', '.') }}
                    </div>
                    <div class="cap-stat-sub">{{ $netCapital >= 0 ? 'Disponible' : 'Déficit' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
                <h6 class="fw-bold mb-0">Historial de movimientos</h6>
                <span class="text-muted small">{{ $entries->total() }} registros</span>
            </div>

            @if($entries->isEmpty())
            <div class="text-center py-5">
                <div class="fs-xl" style="color:#dee2e6;"><i class="bi bi-bank2"></i></div>
                <p class="text-muted mt-2 mb-3">No hay movimientos registrados.</p>
                <x-btn icon="plus-lg" data-bs-toggle="modal" data-bs-target="#modalCapital">Registrar primer aporte</x-btn>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="px-4">Fecha</th>
                            <th>Tipo</th>
                            <th class="text-end">Monto</th>
                            <th>Descripción</th>
                            <th>Registrado por</th>
                            <th class="text-end px-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entries as $entry)
                        <tr class="entry-row">
                            <td class="px-4">
                                <span class="fw-semibold">{{ $entry->entry_date->format('d/m/Y') }}</span>
                                <div class="text-muted fs-xs" >{{ $entry->created_at->format('H:i') }}</div>
                            </td>
                            <td>
                                <span class="type-badge {{ $entry->type === 'input' ? 'type-badge-input' : 'type-badge-withdrawal' }}">
                                    <i class="bi {{ $entry->type === 'input' ? 'bi-arrow-down' : 'bi-arrow-up' }}"></i>
                                    {{ $entry->typeLabel() }}
                                </span>
                            </td>
                            <td class="text-end fw-bold {{ $entry->type === 'input' ? 'text-success' : 'text-danger' }}">
                                {{ $entry->type === 'withdrawal' ? '-' : '+' }}{{ $currencySymbol }} {{ number_format((float)$entry->amount, 2, ',', '.') }}
                            </td>
                            <td class="text-muted fs-sm">{{ $entry->description ?? '—' }}</td>
                            <td class="fs-sm">{{ $entry->user?->name ?? '—' }}</td>
                            <td class="text-end px-4">
                                <form action="{{ route('capital.destroy', $entry) }}" method="POST"
                                    onsubmit="return confirm('¿Eliminar este registro?');">
                                    @csrf @method('DELETE')
                                    <x-icon-btn icon="trash3" label="Eliminar" variant="danger" type="submit" />
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($entries->hasPages())
            <div class="px-4 py-3 border-top">
                {{ $entries->links() }}
            </div>
            @endif
            @endif
        </div>
    </div>
</div>

{{-- Modal: Registrar movimiento --}}
<div class="modal fade" id="modalCapital" tabindex="-1" aria-labelledby="modalCapitalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('capital.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalCapitalLabel">
                        <i class="bi bi-bank2 me-2 text-mod"></i>Registrar movimiento de capital
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-3">

                    {{-- Tipo selector --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Tipo de movimiento</label>
                        <div class="d-flex gap-2" id="type-selector">
                            <label class="flex-fill text-center" style="cursor:pointer;">
                                <input type="radio" name="type" value="input" class="d-none type-radio" {{ old('type','input') === 'input' ? 'checked' : '' }}>
                                <div class="type-opt rounded-3 border p-3 h-100" data-type="input">
                                    <div class="fs-xl" style="color:#2e7d32;"><i class="bi bi-arrow-down-circle-fill"></i></div>
                                    <div class="fw-semibold mt-1 fs-base" >Aporte</div>
                                    <div class="text-muted fs-xs" >Ingreso de capital</div>
                                </div>
                            </label>
                            <label class="flex-fill text-center" style="cursor:pointer;">
                                <input type="radio" name="type" value="withdrawal" class="d-none type-radio" {{ old('type') === 'withdrawal' ? 'checked' : '' }}>
                                <div class="type-opt rounded-3 border p-3 h-100" data-type="withdrawal">
                                    <div class="fs-xl" style="color:#c62828;"><i class="bi bi-arrow-up-circle-fill"></i></div>
                                    <div class="fw-semibold mt-1 fs-base" >Retiro</div>
                                    <div class="text-muted fs-xs" >Salida de capital</div>
                                </div>
                            </label>
                        </div>
                        @error('type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-7">
                            <label class="form-label fw-semibold small">Monto ({{ $currencySymbol }})</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">{{ $currencySymbol }}</span>
                                <input type="number" name="amount" class="form-control border-start-0 @error('amount') is-invalid @enderror"
                                    placeholder="0" step="1" min="1" value="{{ old('amount') }}" required autofocus>
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-semibold small">Fecha</label>
                            <input type="date" name="entry_date" class="form-control @error('entry_date') is-invalid @enderror"
                                value="{{ old('entry_date', date('Y-m-d')) }}" max="{{ date('Y-m-d') }}" required>
                            @error('entry_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Descripción <span class="text-muted fw-normal">(opcional)</span></label>
                            <input type="text" name="description" class="form-control @error('description') is-invalid @enderror"
                                placeholder="Ej: Aporte inicial del socio, pago de deuda..." maxlength="500"
                                value="{{ old('description') }}">
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-0 pt-0">
                    <x-btn variant="secondary" size="md" data-bs-dismiss="modal">Cancelar</x-btn>
                    <x-btn type="submit" size="md" icon="check-lg">Guardar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    function updateTypeUI() {
        document.querySelectorAll('.type-radio').forEach(function (radio) {
            const opt = radio.closest('label').querySelector('.type-opt');
            if (radio.checked) {
                const isInput = radio.value === 'input';
                opt.style.borderColor = isInput ? '#2e7d32' : '#c62828';
                opt.style.background  = isInput ? '#f1f8f2' : '#fff5f5';
                opt.style.boxShadow   = isInput ? '0 0 0 3px rgba(46,125,50,.15)' : '0 0 0 3px rgba(198,40,40,.15)';
            } else {
                opt.style.borderColor = '';
                opt.style.background  = '';
                opt.style.boxShadow   = '';
            }
        });
    }

    document.querySelectorAll('.type-radio').forEach(function (r) {
        r.addEventListener('change', updateTypeUI);
    });

    // Open modal if validation failed
    @if($errors->any())
    var modalEl = document.getElementById('modalCapital');
    if (modalEl) { new bootstrap.Modal(modalEl).show(); }
    @endif

    updateTypeUI();
})();
</script>
@endpush

@endsection
