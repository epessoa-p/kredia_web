@extends('layouts.app')

@section('title', 'Nuevo Cliente')

@section('page')
<div class="container-fluid">
    <x-page-header title="Nuevo Cliente" icon="person-plus" subtitle="Registra un cliente para reutilizarlo en préstamos." :back="route('clients.index')" />

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('clients.store') }}" method="POST" enctype="multipart/form-data" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Nombre completo *</label>
                    <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Documento</label>
                    <input type="text" name="id_number" class="form-control form-control-sm @error('id_number') is-invalid @enderror" value="{{ old('id_number') }}" placeholder="DUI, NIT, Cédula">
                    @error('id_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="phone" class="form-control form-control-sm @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Correo</label>
                    <input type="email" name="email" class="form-control form-control-sm @error('email') is-invalid @enderror" value="{{ old('email') }}">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Dirección</label>
                    <input type="text" name="address" class="form-control form-control-sm @error('address') is-invalid @enderror" value="{{ old('address') }}">
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                {{-- ── Foto + Documentos ────────────────────────────── --}}
                <div class="col-12">
                    <div class="row g-3">

                        {{-- Foto del cliente (col 50%) --}}
                        <div class="col-md-6">
                            <div class="cd-panel h-100">
                                <div class="cd-panel__header">
                                    <span class="cd-panel__icon" style="background:#e8f3ff;color:#0d6efd;"><i class="bi bi-person-bounding-box"></i></span>
                                    <span class="cd-panel__title">Foto del cliente</span>
                                </div>

                                <div class="k-seg mb-3">
                                    <button type="button" class="btn btn-sm btn-ghost active" id="cp-btn-upload">
                                        <i class="bi bi-upload me-1"></i> Subir
                                    </button>
                                    <button type="button" class="btn btn-sm btn-ghost" id="cp-btn-camera">
                                        <i class="bi bi-camera-video-fill me-1"></i> Cámara
                                    </button>
                                </div>

                                <input type="file" id="photo-input" name="photo" accept="image/*" style="display:none;">

                                <div id="cp-upload-section">
                                    <div class="client-photo-dropzone" id="client-photo-dropzone">
                                        <i class="bi bi-person-circle dropzone-icon"></i>
                                        <div class="dropzone-title">Arrastra aquí</div>
                                        <div class="dropzone-subtitle">JPG, PNG, WebP · máx. 2MB</div>
                                    </div>
                                </div>

                                <div id="cp-camera-section" style="display:none;">
                                    <div class="cp-camera-wrap">
                                        <video id="cp-video" class="cp-video" autoplay playsinline muted></video>
                                        <div id="cp-flash" class="cp-flash"></div>
                                        <div class="cp-camera-controls">
                                            <x-icon-btn id="cp-capture-btn" icon="camera-fill" variant="primary" size="md" label="Tomar foto" />
                                            <x-icon-btn id="cp-flip-btn" icon="arrow-repeat" variant="secondary" size="md" label="Cambiar cámara" />
                                        </div>
                                    </div>
                                    <p id="cp-camera-error" class="text-danger small mt-2 d-none">
                                        <i class="bi bi-exclamation-triangle me-1"></i> No se pudo acceder a la cámara.
                                    </p>
                                    <canvas id="cp-canvas" style="display:none;"></canvas>
                                </div>

                                <div id="photo-preview" class="cp-preview mt-3" style="display:none;">
                                    <img id="preview-img" src="" alt="Vista previa" class="cp-preview-img">
                                    <div class="cp-preview-actions">
                                        <span class="cp-preview-label"><i class="bi bi-check-circle-fill text-success me-1"></i>Foto lista</span>
                                        <x-btn variant="outline-danger" id="remove-photo" icon="x">Quitar</x-btn>
                                    </div>
                                </div>

                                @error('photo')<div class="invalid-feedback d-block mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        {{-- Documentos (col 50%) --}}
                        <div class="col-md-6">
                            <div class="cd-panel h-100">
                                <div class="cd-panel__header mb-2">
                                    <span class="cd-panel__icon" style="background:#f0f0ff;color:#6610f2;"><i class="bi bi-file-earmark-text"></i></span>
                                    <span class="cd-panel__title">Documentos adjuntos</span>
                                </div>
                                <p class="text-muted fs-sm">CI, facturas, contratos u otros archivos del cliente.</p>

                                {{-- Mode toggle --}}
                                <div class="k-seg mb-3">
                                    <button type="button" class="btn btn-sm btn-ghost active" id="doc-btn-upload">
                                        <i class="bi bi-upload me-1"></i> Archivo
                                    </button>
                                    <button type="button" class="btn btn-sm btn-ghost" id="doc-btn-camera">
                                        <i class="bi bi-camera-video-fill me-1"></i> Cámara
                                    </button>
                                </div>

                                {{-- Quick label pills (shared for both modes) --}}
                                <div class="d-flex flex-wrap gap-1 mb-3" id="doc-quick-labels">
                                    <button type="button" class="btn btn-sm btn-secondary doc-quick-btn" data-label="CI Anverso">
                                        <i class="bi bi-person-vcard me-1"></i>CI Anverso
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary doc-quick-btn" data-label="CI Reverso">
                                        <i class="bi bi-person-vcard-fill me-1"></i>CI Reverso
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary doc-quick-btn" data-label="Factura">
                                        <i class="bi bi-receipt me-1"></i>Factura
                                    </button>
                                    <button type="button" class="btn btn-sm btn-secondary doc-quick-btn" data-label="">
                                        <i class="bi bi-plus me-1"></i>Otro
                                    </button>
                                </div>

                                {{-- Upload section --}}
                                <div id="doc-upload-section">
                                    <div class="doc-dropzone" id="doc-dropzone">
                                        <i class="bi bi-cloud-arrow-up" style="font-size:var(--fs-lg);color:#6610f2;display:block;margin-bottom:.3rem;"></i>
                                        <div style="font-weight:600;font-size:var(--fs-sm);color:#1f2d3d;">Arrastra archivos aquí</div>
                                        <div style="font-size:var(--fs-xs);color:#6c757d;">o usa los botones de arriba · JPG, PNG, PDF · máx. 5MB</div>
                                    </div>
                                </div>

                                {{-- Camera section --}}
                                <div id="doc-camera-section" style="display:none;">
                                    <div class="cp-camera-wrap">
                                        <video id="doc-video" class="cp-video" autoplay playsinline muted></video>
                                        <div id="doc-flash" class="cp-flash"></div>
                                        <div class="cp-camera-controls">
                                            <x-icon-btn id="doc-capture-btn" icon="camera-fill" variant="primary" size="md" label="Capturar foto" />
                                            <x-icon-btn id="doc-flip-btn" icon="arrow-repeat" variant="secondary" size="md" label="Cambiar cámara" />
                                        </div>
                                    </div>
                                    <p id="doc-camera-error" class="text-danger small mt-2 d-none">
                                        <i class="bi bi-exclamation-triangle me-1"></i> No se pudo acceder a la cámara.
                                    </p>
                                    <canvas id="doc-canvas" style="display:none;"></canvas>
                                    <p class="text-muted mt-2 fs-sm">
                                        <i class="bi bi-info-circle me-1"></i>Selecciona una etiqueta arriba antes de capturar, o edítala después.
                                    </p>
                                </div>

                                <input type="file" id="doc-file-input" accept="image/*,.pdf" style="display:none;">

                                {{-- File list --}}
                                <div id="doc-list" class="mt-2" style="display:none;"></div>

                                {{-- Hidden inputs synced on submit --}}
                                <div id="doc-hidden-files"></div>
                                <div id="doc-hidden-labels"></div>
                                <input type="file" id="doc-files-submit" name="doc_files[]" multiple style="display:none;" aria-hidden="true">

                                @error('doc_files.*')<div class="invalid-feedback d-block mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>

                    </div>
                </div>
                <div class="col-12">
                    <div class="card border-0 location-card">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                                <div>
                                    <h6 class="mb-1 fw-bold"><i class="bi bi-geo-alt-fill text-danger me-1"></i> Ubicación del cliente</h6>
                                    <p class="text-muted mb-0 small">Guarda un link o selecciona el punto en el mapa para facilitar visitas y entregas.</p>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <x-btn variant="outline-primary" href="#" id="open-maps-link" class="disabled" target="_blank" rel="noopener" icon="box-arrow-up-right">Abrir ubicación</x-btn>
                                    <x-btn variant="outline-success" icon="map" data-bs-toggle="modal" data-bs-target="#mapModal">Seleccionar en mapa</x-btn>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Link de ubicación (Google Maps u otro)</label>
                                    <input
                                        type="url"
                                        id="location_url"
                                        name="location_url"
                                        class="form-control form-control-sm @error('location_url') is-invalid @enderror"
                                        value="{{ old('location_url') }}"
                                        placeholder="https://maps.google.com/..."
                                    >
                                    @error('location_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <input type="hidden" id="location_lat" name="location_lat" value="{{ old('location_lat') }}">
                            <input type="hidden" id="location_lng" name="location_lng" value="{{ old('location_lng') }}">

                            <div class="d-flex gap-2 mt-3 flex-wrap">
                                <x-btn variant="outline-success" id="use-current-location" icon="crosshair">Usar mi ubicación actual</x-btn>
                                <x-btn variant="secondary" id="clear-location" icon="eraser">Limpiar ubicación</x-btn>
                            </div>
                        </div>
                    </div>
                </div>
                @include('clients.partials.tag-field', ['value' => null, 'companyId' => null])
                <div class="col-12">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" rows="3" class="form-control form-control-sm @error('notes') is-invalid @enderror">{{ old('notes') }}</textarea>
                    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" {{ old('active', 1) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">Cliente activo</label>
                    </div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <x-btn type="submit" icon="check-lg">Guardar cliente</x-btn>
                    <x-btn variant="secondary" :href="route('clients.index')">Cancelar</x-btn>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- Map Modal --}}
<div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title mb-0" id="mapModalLabel">
                    <i class="bi bi-geo-alt-fill text-danger me-1"></i> Seleccionar ubicación
                </h6>
                <div class="d-flex align-items-center gap-2 ms-3 me-auto">
                    <div class="k-seg" role="group" aria-label="Tipo de mapa">
                        <button type="button" id="map-type-satellite" class="btn btn-sm btn-ghost active">
                            <i class="bi bi-globe-americas me-1"></i>Satelital
                        </button>
                        <button type="button" id="map-type-standard" class="btn btn-sm btn-ghost">
                            <i class="bi bi-map me-1"></i>Estándar
                        </button>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div id="client-location-map" style="height:520px; width:100%;"></div>
            </div>
            <div class="modal-footer py-2">
                <small class="text-muted me-auto">
                    <i class="bi bi-info-circle me-1"></i>Haz clic en el mapa para fijar la ubicación exacta.
                </small>
                <x-btn variant="success" id="map-confirm-btn" icon="check-lg" data-bs-dismiss="modal">Confirmar ubicación</x-btn>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<style>
    /* ── Document panel ────────────────────── */
    .cd-panel {
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 14px;
        padding: 1.1rem 1.1rem .9rem;
    }
    .cd-panel__header {
        display: flex; align-items: center; gap: .6rem; margin-bottom: .85rem;
    }
    .cd-panel__icon {
        width: 32px; height: 32px; border-radius: 9px;
        display: grid; place-items: center; font-size:var(--fs-md); flex-shrink: 0;
    }
    .cd-panel__title { font-weight: 700; font-size:var(--fs-base); }

    .doc-quick-btn--active { background: var(--mod-color); border-color: var(--mod-color); color: #fff; }

    .doc-dropzone {
        border: 1.5px dashed #c5b8f8;
        background: linear-gradient(135deg, #f9f7ff 0%, #f3eeff 100%);
        border-radius: 11px; padding: 14px 12px;
        text-align: center; cursor: pointer;
        transition: all .18s ease;
    }
    .doc-dropzone:hover, .doc-dropzone.is-over {
        border-color: #6610f2; background: #ede8ff;
        box-shadow: 0 0 0 3px rgba(102,16,242,.1);
    }

    .doc-item {
        display: flex; align-items: center; gap: .7rem;
        padding: .55rem .7rem;
        background: #fafafa; border: 1px solid #eee;
        border-radius: 10px; margin-bottom: .45rem;
        transition: box-shadow .15s;
    }
    .doc-item:hover { box-shadow: 0 2px 8px rgba(0,0,0,.08); }
    .doc-item__thumb {
        width: 42px; height: 42px; border-radius: 8px;
        object-fit: cover; flex-shrink: 0;
        border: 1px solid #e2e2e2;
    }
    .doc-item__icon {
        width: 42px; height: 42px; border-radius: 8px; flex-shrink: 0;
        background: #f0f0f0; display: grid; place-items: center;
        font-size:var(--fs-lg); color: #6c757d;
    }
    .doc-item__label {
        flex: 1; min-width: 0;
        border: none; background: transparent;
        font-size:var(--fs-sm); font-weight: 600; color: #1f2d3d;
        outline: none; padding: 0;
    }
    .doc-item__label:focus {
        background: #f0f0ff; border-radius: 5px; padding: 2px 6px;
    }
    .doc-item__size { font-size:var(--fs-xs); color: #aaa; flex-shrink: 0; }
    .doc-item__remove {
        flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%;
        background: #fff0f0; border: 1px solid #ffc9c9; color: #c0392b;
        display: grid; place-items: center; cursor: pointer; font-size:var(--fs-sm);
        transition: all .14s ease;
    }
    .doc-item__remove:hover { background: #c0392b; color: #fff; border-color: #c0392b; }

    .location-card {
        background: linear-gradient(135deg, #f8fbff 0%, #eef6ff 100%);
        border: 1px solid #d8e8ff;
        border-radius: 12px;
    }

    .client-location-map {
        width: 100%;
        height: 260px;
        border-radius: 10px;
        border: 1px solid #d5dbe4;
        overflow: hidden;
    }

    .client-photo-dropzone {
        border: 1.5px dashed #b9c6d8;
        background: linear-gradient(135deg, #f8fbff 0%, #eef5ff 100%);
        border-radius: 12px;
        padding: 16px 12px;
        text-align: center;
        cursor: pointer;
        transition: all .2s ease;
        margin-bottom: .35rem;
    }
    .client-photo-dropzone:hover,
    .client-photo-dropzone.is-dragover {
        border-color: #0d6efd;
        background: #eaf2ff;
        box-shadow: 0 0 0 3px rgba(13,110,253,.08);
    }
    .client-photo-dropzone .dropzone-icon {
        font-size:var(--fs-xl); color: #0d6efd; line-height: 1; margin-bottom: .35rem; display: block;
    }
    .client-photo-dropzone .dropzone-title {
        font-weight: 600; color: #1f2d3d; font-size:var(--fs-base); margin-bottom: .1rem;
    }
    .client-photo-dropzone .dropzone-subtitle {
        color: #5b6573; font-size:var(--fs-xs);
    }


    /* ── Camera wrap ────────────────────── */
    .cp-camera-wrap {
        position: relative; border-radius: 14px; overflow: hidden;
        background: #0a0a0a; aspect-ratio: 4/3; max-width: 480px;
        box-shadow: 0 4px 20px rgba(0,0,0,.18);
    }
    .cp-video {
        width: 100%; height: 100%; object-fit: cover; display: block;
        transform: scaleX(-1);
    }
    .cp-flash {
        position: absolute; inset: 0; background: #fff;
        opacity: 0; pointer-events: none;
        transition: opacity .05s ease;
    }
    .cp-flash.active { opacity: .85; }

    /* ── Camera controls ────────────────── */
    .cp-camera-controls {
        position: absolute; bottom: 14px; left: 0; right: 0;
        display: flex; align-items: center; justify-content: center; gap: 18px;
    }

    /* ── Preview ────────────────────────── */
    .cp-preview {
        display: flex; align-items: center; gap: 1rem;
        padding: .75rem 1rem; background: #f0fdf4;
        border: 1px solid #bbf7d0; border-radius: 12px;
        max-width: 480px;
    }
    .cp-preview-img {
        width: 72px; height: 72px; object-fit: cover;
        border-radius: 10px; border: 2px solid #86efac;
        flex-shrink: 0;
    }
    .cp-preview-actions { display: flex; flex-direction: column; gap: .4rem; }
    .cp-preview-label { font-size:var(--fs-sm); font-weight: 600; color: #166534; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    const latInput      = document.getElementById('location_lat');
    const lngInput      = document.getElementById('location_lng');
    const urlInput      = document.getElementById('location_url');
    const openLink      = document.getElementById('open-maps-link');
    const useCurrentBtn = document.getElementById('use-current-location');
    const clearBtn      = document.getElementById('clear-location');
    const mapModal      = document.getElementById('mapModal');

    if (!latInput || !lngInput) return;

    const defaultLat      = latInput.value ? parseFloat(latInput.value) : -17.783327;
    const defaultLng      = lngInput.value ? parseFloat(lngInput.value) : -63.18214;
    const hasInitialPoint = !!(latInput.value && lngInput.value);

    const satelliteLayer = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        { maxZoom: 19, attribution: 'Tiles &copy; Esri' }
    );
    const standardLayer = L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }
    );

    const map = L.map('client-location-map', {
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView([defaultLat, defaultLng], hasInitialPoint ? 15 : 12);

    satelliteLayer.addTo(map);

    const btnSat = document.getElementById('map-type-satellite');
    const btnStd = document.getElementById('map-type-standard');

    btnSat.addEventListener('click', function () {
        if (!map.hasLayer(satelliteLayer)) { map.removeLayer(standardLayer); satelliteLayer.addTo(map); }
        btnSat.classList.add('active');
        btnStd.classList.remove('active');
    });
    btnStd.addEventListener('click', function () {
        if (!map.hasLayer(standardLayer)) { map.removeLayer(satelliteLayer); standardLayer.addTo(map); }
        btnStd.classList.add('active');
        btnSat.classList.remove('active');
    });

    let marker = null;

    function updateOpenLink() {
        let href = '';
        if (urlInput.value.trim()) {
            href = urlInput.value.trim();
        } else if (latInput.value && lngInput.value) {
            href = 'https://www.google.com/maps?q=' + latInput.value + ',' + lngInput.value;
        }
        if (href) { openLink.href = href; openLink.classList.remove('disabled'); }
        else { openLink.href = '#'; openLink.classList.add('disabled'); }
    }

    function setPoint(lat, lng, center) {
        center = center !== false;
        latInput.value = Number(lat).toFixed(7);
        lngInput.value = Number(lng).toFixed(7);
        if (marker) { marker.setLatLng([lat, lng]); }
        else { marker = L.marker([lat, lng]).addTo(map); }
        if (center) map.setView([lat, lng], 16);
        updateOpenLink();
    }

    function clearPoint() {
        latInput.value = ''; lngInput.value = '';
        if (marker) { map.removeLayer(marker); marker = null; }
        updateOpenLink();
    }

    if (hasInitialPoint) setPoint(parseFloat(latInput.value), parseFloat(lngInput.value), false);

    map.on('click', function (e) { setPoint(e.latlng.lat, e.latlng.lng); });
    urlInput.addEventListener('input', updateOpenLink);

    useCurrentBtn.addEventListener('click', function () {
        if (!navigator.geolocation) { alert('Tu navegador no soporta geolocalización.'); return; }
        navigator.geolocation.getCurrentPosition(
            function (p) { setPoint(p.coords.latitude, p.coords.longitude); },
            function () { alert('No se pudo obtener tu ubicación actual.'); },
            { enableHighAccuracy: true, timeout: 8000 }
        );
    });

    clearBtn.addEventListener('click', clearPoint);

    mapModal.addEventListener('shown.bs.modal', function () {
        map.invalidateSize();
        if (latInput.value && lngInput.value) {
            var lat = parseFloat(latInput.value), lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) map.setView([lat, lng], map.getZoom() >= 12 ? map.getZoom() : 15);
        }
    });

    document.getElementById('map-confirm-btn').addEventListener('click', function () {
        if (!latInput.value || !lngInput.value) return;
        var current = urlInput.value.trim();
        var autoUrl = 'https://www.google.com/maps?q=' + latInput.value + ',' + lngInput.value;
        if (!current || current.startsWith('https://www.google.com/maps?q=')) {
            urlInput.value = autoUrl;
            updateOpenLink();
        }
    });

    updateOpenLink();
})();

document.querySelector('form').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') e.preventDefault();
});
</script>
<script>
(function () {
    const input        = document.getElementById('photo-input');
    const dropzone     = document.getElementById('client-photo-dropzone');
    const preview      = document.getElementById('photo-preview');
    const previewImg   = document.getElementById('preview-img');
    const removeBtn    = document.getElementById('remove-photo');
    const btnUpload    = document.getElementById('cp-btn-upload');
    const btnCamera    = document.getElementById('cp-btn-camera');
    const uploadSect   = document.getElementById('cp-upload-section');
    const cameraSect   = document.getElementById('cp-camera-section');
    const video        = document.getElementById('cp-video');
    const canvas       = document.getElementById('cp-canvas');
    const captureBtn   = document.getElementById('cp-capture-btn');
    const flipBtn      = document.getElementById('cp-flip-btn');
    const flash        = document.getElementById('cp-flash');
    const cameraError  = document.getElementById('cp-camera-error');

    let stream = null;
    let facingMode = 'user';

    /* ── Preview helpers ─────────────── */
    function showPreview(src) {
        previewImg.src = src;
        preview.style.display = 'flex';
        uploadSect.style.display = 'none';
        cameraSect.style.display = 'none';
    }

    function clearPreview() {
        preview.style.display = 'none';
        previewImg.src = '';
        input.value = '';
        if (btnUpload.classList.contains('active')) {
            uploadSect.style.display = 'block';
        } else {
            cameraSect.style.display = 'block';
            startCamera();
        }
    }

    /* ── File input / dropzone ───────── */
    dropzone.addEventListener('click', () => input.click());

    input.addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => showPreview(e.target.result);
        reader.readAsDataURL(file);
    });

    ['dragenter','dragover'].forEach(ev => dropzone.addEventListener(ev, e => {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.add('is-dragover');
    }));
    ['dragleave','dragend','drop'].forEach(ev => dropzone.addEventListener(ev, e => {
        e.preventDefault(); e.stopPropagation();
        dropzone.classList.remove('is-dragover');
    }));
    dropzone.addEventListener('drop', e => {
        const files = e.dataTransfer?.files;
        if (files?.length) { input.files = files; input.dispatchEvent(new Event('change')); }
    });

    removeBtn.addEventListener('click', clearPreview);

    /* ── Camera ──────────────────────── */
    async function startCamera() {
        cameraError.classList.add('d-none');
        stopCamera();
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode, width: { ideal: 1280 }, height: { ideal: 960 } },
                audio: false,
            });
            video.srcObject = stream;
            flipBtn.style.display = 'flex';
        } catch {
            cameraError.classList.remove('d-none');
            flipBtn.style.display = 'none';
        }
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(t => t.stop());
            stream = null;
            video.srcObject = null;
        }
    }

    captureBtn.addEventListener('click', () => {
        if (!stream) return;

        // Flash effect
        flash.classList.add('active');
        setTimeout(() => flash.classList.remove('active'), 150);

        // Capture frame
        canvas.width  = video.videoWidth  || 640;
        canvas.height = video.videoHeight || 480;
        const ctx = canvas.getContext('2d');
        ctx.save();
        ctx.scale(-1, 1);
        ctx.drawImage(video, -canvas.width, 0, canvas.width, canvas.height);
        ctx.restore();

        canvas.toBlob(blob => {
            const file = new File([blob], 'camara.jpg', { type: 'image/jpeg' });
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;

            const url = URL.createObjectURL(blob);
            showPreview(url);
            stopCamera();
        }, 'image/jpeg', 0.92);
    });

    flipBtn.addEventListener('click', () => {
        facingMode = facingMode === 'user' ? 'environment' : 'user';
        startCamera();
    });

    /* ── Mode toggle ─────────────────── */
    btnUpload.addEventListener('click', () => {
        btnUpload.classList.add('active');
        btnCamera.classList.remove('active');
        stopCamera();
        cameraSect.style.display = 'none';
        if (preview.style.display === 'none') uploadSect.style.display = 'block';
    });

    btnCamera.addEventListener('click', () => {
        btnCamera.classList.add('active');
        btnUpload.classList.remove('active');
        uploadSect.style.display = 'none';
        if (preview.style.display === 'none') {
            cameraSect.style.display = 'block';
            startCamera();
        }
    });
})();
</script>
<script>
(function () {
    const dropzone       = document.getElementById('doc-dropzone');
    const fileInput      = document.getElementById('doc-file-input');
    const submitInput    = document.getElementById('doc-files-submit');
    const listEl         = document.getElementById('doc-list');
    const labelsBox      = document.getElementById('doc-hidden-labels');
    const quickBtns      = document.querySelectorAll('.doc-quick-btn');
    const uploadSection  = document.getElementById('doc-upload-section');
    const cameraSection  = document.getElementById('doc-camera-section');
    const btnUpload      = document.getElementById('doc-btn-upload');
    const btnCamera      = document.getElementById('doc-btn-camera');
    const docVideo       = document.getElementById('doc-video');
    const docCanvas      = document.getElementById('doc-canvas');
    const docCaptureBtn  = document.getElementById('doc-capture-btn');
    const docFlipBtn     = document.getElementById('doc-flip-btn');
    const docFlash       = document.getElementById('doc-flash');
    const docCameraError = document.getElementById('doc-camera-error');

    let docItems     = []; // [{file, label}]
    let pendingLabel = '';
    let docStream    = null;
    let docFacing    = 'environment'; // prefer rear camera for documents

    /* ── Helpers ─────────────────────── */
    function fmtSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function isImage(file) {
        return file.type.startsWith('image/');
    }

    /* ── Sync hidden inputs ──────────── */
    function syncSubmit() {
        const dt = new DataTransfer();
        docItems.forEach(d => dt.items.add(d.file));
        submitInput.files = dt.files;

        labelsBox.innerHTML = '';
        docItems.forEach(d => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'doc_labels[]';
            inp.value = d.label;
            labelsBox.appendChild(inp);
        });
    }

    /* ── Render file list ────────────── */
    function renderList() {
        if (!docItems.length) { listEl.style.display = 'none'; listEl.innerHTML = ''; return; }
        listEl.style.display = 'block';
        listEl.innerHTML = '';
        docItems.forEach(function (item, idx) {
            const div = document.createElement('div');
            div.className = 'doc-item';

            if (isImage(item.file)) {
                const img = document.createElement('img');
                img.className = 'doc-item__thumb';
                img.src = URL.createObjectURL(item.file);
                div.appendChild(img);
            } else {
                const icon = document.createElement('div');
                icon.className = 'doc-item__icon';
                icon.innerHTML = '<i class="bi bi-file-earmark-pdf text-danger"></i>';
                div.appendChild(icon);
            }

            const lbl = document.createElement('input');
            lbl.type = 'text';
            lbl.className = 'doc-item__label';
            lbl.placeholder = 'Etiqueta (opcional)';
            lbl.value = item.label;
            lbl.addEventListener('input', function () {
                docItems[idx].label = this.value;
                syncSubmit();
            });
            div.appendChild(lbl);

            const sz = document.createElement('span');
            sz.className = 'doc-item__size';
            sz.textContent = fmtSize(item.file.size);
            div.appendChild(sz);

            const rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'doc-item__remove';
            rm.innerHTML = '<i class="bi bi-x"></i>';
            rm.addEventListener('click', function () {
                docItems.splice(idx, 1);
                renderList();
                syncSubmit();
            });
            div.appendChild(rm);

            listEl.appendChild(div);
        });
    }

    function addFile(file, label) {
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) { alert('El archivo "' + file.name + '" supera los 5MB.'); return; }
        docItems.push({ file: file, label: label || '' });
        renderList();
        syncSubmit();
    }

    /* ── Camera ──────────────────────── */
    async function startDocCamera() {
        docCameraError.classList.add('d-none');
        stopDocCamera();
        try {
            docStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: docFacing, width: { ideal: 1280 }, height: { ideal: 960 } },
                audio: false,
            });
            docVideo.srcObject = docStream;
            docFlipBtn.style.display = 'flex';
        } catch {
            docCameraError.classList.remove('d-none');
            docFlipBtn.style.display = 'none';
        }
    }

    function stopDocCamera() {
        if (docStream) {
            docStream.getTracks().forEach(t => t.stop());
            docStream = null;
            docVideo.srcObject = null;
        }
    }

    docCaptureBtn.addEventListener('click', function () {
        if (!docStream) return;

        docFlash.classList.add('active');
        setTimeout(() => docFlash.classList.remove('active'), 150);

        docCanvas.width  = docVideo.videoWidth  || 640;
        docCanvas.height = docVideo.videoHeight || 480;
        const ctx = docCanvas.getContext('2d');
        // Rear camera — no horizontal flip needed; front camera would need flip
        if (docFacing === 'user') {
            ctx.save();
            ctx.scale(-1, 1);
            ctx.drawImage(docVideo, -docCanvas.width, 0, docCanvas.width, docCanvas.height);
            ctx.restore();
        } else {
            ctx.drawImage(docVideo, 0, 0, docCanvas.width, docCanvas.height);
        }

        const label = pendingLabel;
        docCanvas.toBlob(function (blob) {
            const ts = Date.now();
            const file = new File([blob], 'camara_' + ts + '.jpg', { type: 'image/jpeg' });
            addFile(file, label);
            pendingLabel = '';
            // Highlight captured pill briefly
            quickBtns.forEach(b => b.classList.remove('doc-quick-btn--active'));
        }, 'image/jpeg', 0.92);
    });

    docFlipBtn.addEventListener('click', function () {
        docFacing = docFacing === 'user' ? 'environment' : 'user';
        startDocCamera();
    });

    /* ── Mode toggle ─────────────────── */
    btnUpload.addEventListener('click', function () {
        btnUpload.classList.add('active');
        btnCamera.classList.remove('active');
        stopDocCamera();
        cameraSection.style.display = 'none';
        uploadSection.style.display = 'block';
    });

    btnCamera.addEventListener('click', function () {
        btnCamera.classList.add('active');
        btnUpload.classList.remove('active');
        uploadSection.style.display = 'none';
        cameraSection.style.display = 'block';
        startDocCamera();
    });

    /* ── Quick label buttons ─────────── */
    quickBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingLabel = btn.dataset.label;
            // In camera mode: just set label; in upload mode: open file picker
            if (btnCamera.classList.contains('active')) {
                quickBtns.forEach(b => b.classList.remove('doc-quick-btn--active'));
                btn.classList.add('doc-quick-btn--active');
            } else {
                fileInput.value = '';
                fileInput.click();
            }
        });
    });

    /* ── Drop zone click ─────────────── */
    dropzone.addEventListener('click', function () {
        pendingLabel = '';
        fileInput.value = '';
        fileInput.click();
    });

    /* ── File selected ───────────────── */
    fileInput.addEventListener('change', function () {
        Array.from(this.files).forEach(function (f) { addFile(f, pendingLabel); });
        pendingLabel = '';
    });

    /* ── Drag & drop ─────────────────── */
    dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('is-over'); });
    dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('is-over'); });
    dropzone.addEventListener('drop', function (e) {
        e.preventDefault(); dropzone.classList.remove('is-over');
        Array.from(e.dataTransfer.files).forEach(function (f) { addFile(f, ''); });
    });

    /* ── Stop camera on form submit ──── */
    document.querySelector('form').addEventListener('submit', stopDocCamera);
})();
</script>
@endpush
