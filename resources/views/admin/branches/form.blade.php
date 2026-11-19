<div class="container-fluid">
    <x-page-header :title="$branch ? 'Editar sucursal' : 'Nueva sucursal'" icon="diagram-2" subtitle="Configura la información operativa de la sucursal." :back="route('branches.index')" />
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ $action }}" method="POST" class="row g-3">
                @csrf
                @if($method !== 'POST') @method($method) @endif
                @if($companies->count() > 1)
                    <div class="col-md-6">
                        <label class="form-label">Empresa</label>
                        <select name="company_id" class="form-select form-select-sm">
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ (string) old('company_id', $branch?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="col-md-6"><label class="form-label">Nombre <span class="text-danger">*</span></label><input type="text" name="name" class="form-control form-control-sm" value="{{ old('name', $branch?->name) }}" required></div>
                <div class="col-md-6"><label class="form-label">Código</label><input type="text" name="code" class="form-control form-control-sm" value="{{ old('code', $branch?->code) }}"></div>
                <div class="col-md-6">
                    <label class="form-label">Almacén principal <span class="text-danger">*</span></label>
                    <select name="warehouse_id" class="form-select form-select-sm" required>
                        <option value="">Seleccionar almacén</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" {{ (string) old('warehouse_id', $branch?->warehouse_id) === (string) $warehouse->id ? 'selected' : '' }}>{{ $warehouse->name }} ({{ $warehouse->code }})</option>
                        @endforeach
                    </select>
                    @if($warehouses->isEmpty())
                        <small class="text-danger">No hay almacenes disponibles para esta empresa. Crea primero un almacén.</small>
                    @endif
                </div>
                <div class="col-md-6"><label class="form-label">Teléfono</label><input type="text" name="phone" class="form-control form-control-sm" value="{{ old('phone', $branch?->phone) }}"></div>
                <div class="col-md-6"><label class="form-label">Correo</label><input type="email" name="email" class="form-control form-control-sm" value="{{ old('email', $branch?->email) }}"></div>
                <div class="col-md-6"><label class="form-label">Encargado</label><input type="text" name="manager_name" class="form-control form-control-sm" value="{{ old('manager_name', $branch?->manager_name) }}"></div>
                <div class="col-12"><label class="form-label">Dirección</label><input type="text" name="address" class="form-control form-control-sm" value="{{ old('address', $branch?->address) }}"></div>

                {{-- Color picker --}}
                <div class="col-12">
                    <label class="form-label fw-semibold">Color de sucursal</label>
                    <p class="text-muted small mb-2">Este color se usará para identificar visualmente la sucursal en las vistas del sistema.</p>
                    <input type="hidden" name="color" id="colorValue" value="{{ old('color', $branch?->color ?? '#0d6efd') }}">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        {{-- Live preview swatch --}}
                        <div id="colorPreview" style="width:44px;height:44px;border-radius:10px;background:{{ old('color', $branch?->color ?? '#0d6efd') }};border:2px solid rgba(0,0,0,.12);flex-shrink:0;transition:background .2s;cursor:pointer;" title="Abrir selector de color" onclick="document.getElementById('nativeColorPicker').click()"></div>
                        {{-- Native color input (visually hidden, triggered by preview or custom button) --}}
                        <input type="color" id="nativeColorPicker" value="{{ old('color', $branch?->color ?? '#0d6efd') }}" style="width:0;height:0;opacity:0;position:absolute;pointer-events:none;">
                        {{-- Hex text input --}}
                        <div class="input-group input-group-sm" style="width:130px;">
                            <span class="input-group-text">#</span>
                            <input type="text" id="hexInput" class="form-control form-control-sm font-monospace text-uppercase" maxlength="6" placeholder="0d6efd" value="{{ ltrim(old('color', $branch?->color ?? '#0d6efd'), '#') }}">
                        </div>
                        {{-- Preset swatches --}}
                        <div class="d-flex gap-2 flex-wrap align-items-center" id="colorSwatches">
                            @php
                            $presetColors = [
                                '#0d6efd','#198754','#dc3545','#ffc107','#0dcaf0',
                                '#6f42c1','#fd7e14','#20c997','#6c757d','#212529',
                                '#e83e8c','#17a2b8',
                            ];
                            @endphp
                            @foreach($presetColors as $preset)
                            <button type="button"
                                class="color-swatch {{ old('color', $branch?->color ?? '#0d6efd') === $preset ? 'selected' : '' }}"
                                data-color="{{ $preset }}"
                                title="{{ $preset }}"
                                style="width:28px;height:28px;border-radius:6px;background:{{ $preset }};border:2px solid {{ old('color', $branch?->color ?? '#0d6efd') === $preset ? '#000' : 'rgba(0,0,0,.15)' }};cursor:pointer;transition:border .15s,transform .1s;outline:none;flex-shrink:0;"
                            ></button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $branch?->active ?? true) ? 'checked' : '' }}><label class="form-check-label">Activo</label></div></div>
                <div class="col-12 d-flex gap-2"><x-btn type="submit" icon="check-lg">Guardar</x-btn><x-btn variant="secondary" :href="route('branches.index')">Cancelar</x-btn></div>
            </form>
        </div>
    </div>
</div>

<style>
.color-swatch:hover { transform: scale(1.18); border-color: #000 !important; }
.color-swatch.selected { border-color: #000 !important; box-shadow: 0 0 0 2px #fff, 0 0 0 4px #000; }
</style>

<script>
(function () {
    const colorValue   = document.getElementById('colorValue');
    const colorPreview = document.getElementById('colorPreview');
    const nativePicker = document.getElementById('nativeColorPicker');
    const hexInput     = document.getElementById('hexInput');
    const swatches     = document.querySelectorAll('.color-swatch');

    function applyColor(hex) {
        hex = hex.startsWith('#') ? hex : '#' + hex;
        if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) return;
        colorValue.value   = hex;
        colorPreview.style.background = hex;
        nativePicker.value = hex;
        hexInput.value     = hex.slice(1).toUpperCase();
        swatches.forEach(s => {
            const match = s.dataset.color.toLowerCase() === hex.toLowerCase();
            s.classList.toggle('selected', match);
            s.style.borderColor = match ? '#000' : 'rgba(0,0,0,.15)';
        });
    }

    swatches.forEach(s => s.addEventListener('click', () => applyColor(s.dataset.color)));

    nativePicker.addEventListener('input', () => applyColor(nativePicker.value));

    hexInput.addEventListener('input', function () {
        const raw = this.value.replace(/[^0-9A-Fa-f]/g, '').slice(0, 6);
        this.value = raw.toUpperCase();
        if (raw.length === 6) applyColor('#' + raw);
    });

    hexInput.addEventListener('blur', function () {
        if (this.value.length < 6) applyColor(colorValue.value); // revert to last valid
    });
})();
</script>
