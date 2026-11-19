{{-- JavaScript del formulario unificado de catálogo (crear / editar). --}}
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const cfg = @json($cfConfig);
    const form = document.getElementById('catalogForm');
    if (!form) return;

    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (n) => '{{ $currencySymbol }} ' + Number(n || 0).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // ── Empresa (super admin, al crear): recarga el formulario con sus productos ──
    const companySelect = $('cfCompany');
    if (companySelect) {
        companySelect.addEventListener('change', function () {
            const go = () => { window.location.href = cfg.createUrl + (this.value ? '?company_id=' + encodeURIComponent(this.value) : ''); };
            const hasSelection = document.querySelectorAll('#cfSelected .cf-sel').length > 0;
            if (!hasSelection) return go();
            const msg = 'Al cambiar de empresa se quitan los productos elegidos. ¿Continuar?';
            if (window.Swal) {
                Swal.fire({ icon: 'warning', text: msg, showCancelButton: true, confirmButtonText: 'Cambiar', cancelButtonText: 'Cancelar' })
                    .then((r) => { if (r.isConfirmed) go(); else this.value = this.dataset.prev || ''; });
            } else if (confirm(msg)) { go(); } else { this.value = this.dataset.prev || ''; }
        });
        companySelect.dataset.prev = companySelect.value;
    }

    const pool = $('cfPool');
    if (!pool) return; // sin empresa elegida todavía

    const productsById = {};
    cfg.products.forEach((p) => { productsById[String(p.id)] = p; });
    const branchesById = {};
    cfg.branches.forEach((b) => { branchesById[String(b.id)] = b; });

    const selectedEl = $('cfSelected');
    const branchInput = $('cfBranch');
    const branchFilter = $('cfBranchFilter');
    const filtersEl = $('cfFilters');
    const searchInput = $('cfSearch');
    const categorySelect = $('cfCategory');
    const originSelect = $('cfOrigin');

    const catalogBranchId = () => (branchInput && branchInput.value ? String(branchInput.value) : '');
    const selectedIds = () => Array.from(selectedEl.querySelectorAll('.cf-sel')).map((el) => el.dataset.id);

    // ── Etiquetas ──
    function originBadges(p, max = 2) {
        if (!p.origins.length) return '<span class="cf-badge cf-badge--other">Sin préstamo</span>';
        const shown = p.origins.slice(0, max).map((o) =>
            `<span class="cf-badge cf-badge--${o.type === 'loan' ? 'loan' : 'purchase'}"><i class="bi ${o.type === 'loan' ? 'bi-file-earmark-text' : 'bi-bag'}"></i>${esc(o.code)}</span>`
        ).join('');
        const rest = p.origins.length - max;
        return shown + (rest > 0 ? `<span class="cf-badge cf-badge--other" title="${esc(p.origins.slice(max).map((o) => o.code).join(', '))}">+${rest}</span>` : '');
    }
    function branchBadges(p) {
        return p.branch_ids.map((id) => {
            const b = branchesById[String(id)];
            return b ? `<span class="cf-badge cf-badge--branch"><span class="cf-dot" style="background:${esc(b.color)};width:6px;height:6px"></span>${esc(b.name)}</span>` : '';
        }).join('');
    }
    const imgOrIcon = (url, cls) => url ? `<img src="${esc(url)}" alt="" class="${cls}" loading="lazy">` : `<div class="${cls}"><i class="bi bi-image"></i></div>`;

    // ── Grilla de productos con filtros ──
    function matchesFilters(p) {
        if (!p.available) return false;
        const q = searchInput.value.trim().toLowerCase();
        if (q) {
            const hay = (p.name + ' ' + p.detail + ' ' + p.origins.map((o) => o.code).join(' ')).toLowerCase();
            if (!hay.includes(q)) return false;
        }
        if (categorySelect.value && String(p.category_id) !== categorySelect.value) return false;
        if (originSelect.value && p.origin_type !== originSelect.value) return false;
        const branch = catalogBranchId() || (branchFilter ? branchFilter.value : '');
        if (branch && !p.branch_ids.map(String).includes(branch)) return false;
        return true;
    }

    let lastFiltered = [];
    function renderPool() {
        const chosen = new Set(selectedIds());
        lastFiltered = cfg.products.filter(matchesFilters);

        pool.innerHTML = lastFiltered.length
            ? lastFiltered.map((p) => {
                const added = chosen.has(String(p.id));
                return `<div class="cf-tile ${added ? 'is-added' : ''}" data-id="${p.id}" title="${added ? 'Quitar del catálogo' : 'Agregar al catálogo'}">
                    <div class="cf-tile-img">${p.image ? `<img src="${esc(p.image)}" alt="" loading="lazy">` : '<i class="bi bi-image"></i>'}</div>
                    <button type="button" class="cf-tile-add" tabindex="-1"><i class="bi ${added ? 'bi-check-lg' : 'bi-plus-lg'}"></i></button>
                    <div class="cf-tile-body">
                        <div class="cf-tile-name">${esc(p.name)}</div>
                        ${p.price ? `<div class="cf-tile-price">${money(p.price)}</div>` : ''}
                        <div class="cf-tile-meta">${originBadges(p)}${catalogBranchId() ? '' : branchBadges(p)}</div>
                    </div>
                </div>`;
            }).join('')
            : '<div class="cf-empty"><i class="bi bi-search d-block mb-1 fs-lg" style="opacity:.5"></i>No hay productos con estos filtros.</div>';

        const pending = lastFiltered.filter((p) => !chosen.has(String(p.id))).length;
        const totalAvailable = cfg.products.filter((p) => p.available).length;
        $('cfPoolCount').textContent = `${lastFiltered.length} de ${totalAvailable} producto(s)`;
        $('cfAddAllCount').textContent = pending;
        $('cfAddAll').disabled = pending === 0;
    }

    pool.addEventListener('click', function (e) {
        const tile = e.target.closest('.cf-tile');
        if (!tile) return;
        const id = tile.dataset.id;
        if (selectedIds().includes(id)) {
            selectedEl.querySelector(`.cf-sel[data-id="${id}"]`)?.remove();
        } else {
            addSelected({ product_id: id });
        }
        refresh();
    });

    $('cfAddAll').addEventListener('click', function () {
        const chosen = new Set(selectedIds());
        lastFiltered.filter((p) => !chosen.has(String(p.id))).forEach((p) => addSelected({ product_id: p.id }));
        refresh();
    });

    [searchInput, categorySelect, originSelect, branchFilter].forEach((el) => {
        if (el) el.addEventListener(el.tagName === 'INPUT' ? 'input' : 'change', renderPool);
    });

    // ── Productos elegidos (lista ordenable) ──
    function addSelected(item) {
        const p = productsById[String(item.product_id)];
        if (!p || selectedIds().includes(String(p.id))) return;

        const hasCustom = !!(item.custom_name || item.custom_description || (item.custom_price !== undefined && item.custom_price !== ''));
        const row = document.createElement('div');
        row.className = 'cf-sel';
        row.dataset.id = String(p.id);
        row.innerHTML = `
            <span class="cf-handle" title="Arrastrar para ordenar"><i class="bi bi-grip-vertical"></i></span>
            <span class="cf-sel-num">1</span>
            ${p.image ? `<img src="${esc(p.image)}" alt="" class="cf-sel-img" loading="lazy">` : '<div class="cf-sel-img"><i class="bi bi-image"></i></div>'}
            <div class="cf-sel-body">
                <input type="hidden" data-field="product_id" value="${p.id}">
                <div class="cf-sel-name"><span class="js-name"></span></div>
                <div class="cf-sel-sub">
                    <span class="js-price"></span>
                    ${originBadges(p, 1)}
                    ${!p.available ? '<span class="cf-badge cf-badge--danger" title="El producto ya no está disponible para la venta"><i class="bi bi-x-circle"></i>No disponible</span>' : ''}
                    <span class="js-branch-warn"></span>
                </div>
                <div class="cf-sel-custom ${hasCustom ? '' : 'd-none'}">
                    <input type="text" data-field="custom_name" maxlength="255" class="form-control form-control-sm" placeholder="Nombre a mostrar (${esc(p.name)})">
                    <input type="number" data-field="custom_price" step="0.01" min="0" class="form-control form-control-sm" placeholder="Precio (${p.price ? Number(p.price).toFixed(2) : '—'})">
                    <textarea data-field="custom_description" rows="1" maxlength="2000" class="form-control form-control-sm" placeholder="Descripción a mostrar"></textarea>
                </div>
            </div>
            <div class="cf-sel-actions">
                <button type="button" class="btn btn-secondary btn-sm js-customize" title="Personalizar nombre, precio y descripción"><i class="bi bi-pencil"></i></button>
                <button type="button" class="btn btn-outline-danger btn-sm js-remove" title="Quitar"><i class="bi bi-x-lg"></i></button>
            </div>`;
        row.querySelector('[data-field="custom_name"]').value = item.custom_name || '';
        row.querySelector('[data-field="custom_price"]').value = item.custom_price ?? '';
        row.querySelector('[data-field="custom_description"]').value = item.custom_description || '';
        selectedEl.appendChild(row);
        updateRowSummary(row);
    }

    function updateRowSummary(row) {
        const p = productsById[row.dataset.id];
        const customName = row.querySelector('[data-field="custom_name"]').value.trim();
        const customPrice = row.querySelector('[data-field="custom_price"]').value;
        row.querySelector('.js-name').textContent = customName || p.name;
        row.querySelector('.js-price').innerHTML = customPrice !== ''
            ? `<strong>${money(customPrice)}</strong>${p.price ? ` <span class="cf-price-old">${money(p.price)}</span>` : ''}`
            : (p.price ? money(p.price) : '<span class="text-muted">Sin precio</span>');
    }

    selectedEl.addEventListener('click', function (e) {
        const row = e.target.closest('.cf-sel');
        if (!row) return;
        if (e.target.closest('.js-remove')) {
            row.remove();
            refresh();
        } else if (e.target.closest('.js-customize')) {
            const box = row.querySelector('.cf-sel-custom');
            box.classList.toggle('d-none');
            if (!box.classList.contains('d-none')) box.querySelector('input').focus();
        }
    });
    selectedEl.addEventListener('input', function (e) {
        const row = e.target.closest('.cf-sel');
        if (row) { updateRowSummary(row); schedulePreview(); }
    });

    $('cfRemoveAll').addEventListener('click', function () {
        if (!selectedIds().length) return;
        const doIt = () => { selectedEl.innerHTML = ''; refresh(); };
        if (window.Swal) {
            Swal.fire({ icon: 'question', text: '¿Quitar todos los productos del catálogo?', showCancelButton: true, confirmButtonText: 'Quitar todos', cancelButtonText: 'Cancelar' })
                .then((r) => { if (r.isConfirmed) doIt(); });
        } else if (confirm('¿Quitar todos los productos del catálogo?')) { doIt(); }
    });

    if (window.Sortable) {
        Sortable.create(selectedEl, { handle: '.cf-handle', animation: 150, onEnd: refresh });
    }

    // Nombres de los campos según el orden actual (el orden = sort_order al guardar).
    function renumber() {
        selectedEl.querySelectorAll('.cf-sel').forEach((row, i) => {
            row.querySelector('.cf-sel-num').textContent = i + 1;
            row.querySelectorAll('[data-field]').forEach((input) => {
                input.name = `products[${i}][${input.dataset.field}]`;
            });
        });
    }

    // ── Sucursal del catálogo ──
    function branchWarnings() {
        const branch = catalogBranchId();
        let outside = 0;
        selectedEl.querySelectorAll('.cf-sel').forEach((row) => {
            const p = productsById[row.dataset.id];
            const isOutside = branch && !p.branch_ids.map(String).includes(branch);
            if (isOutside) outside++;
            row.querySelector('.js-branch-warn').innerHTML = isOutside
                ? '<span class="cf-badge cf-badge--warn"><i class="bi bi-exclamation-triangle"></i>Otra sucursal</span>' : '';
        });
        const warn = $('cfBranchWarning');
        if (outside > 0) {
            warn.innerHTML = `<i class="bi bi-exclamation-triangle"></i> ${outside} producto(s) no están en la sucursal elegida.
                <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="cfRemoveOutside">Quitarlos</button>`;
            warn.classList.remove('d-none');
            $('cfRemoveOutside').addEventListener('click', function () {
                selectedEl.querySelectorAll('.cf-sel').forEach((row) => {
                    if (!productsById[row.dataset.id].branch_ids.map(String).includes(branch)) row.remove();
                });
                refresh();
            });
        } else {
            warn.classList.add('d-none');
        }

        // El filtro de sucursal solo tiene sentido para catálogos de "Todas las sucursales".
        if (branchFilter) {
            const showBranchFilter = !branch && cfg.branches.length > 1;
            branchFilter.classList.toggle('d-none', !showBranchFilter);
            filtersEl.classList.toggle('has-branch', showBranchFilter);
            if (!showBranchFilter) branchFilter.value = '';
        }

        const b = branchesById[branch];
        $('cfFooterBranch').innerHTML = b
            ? `<span class="cf-dot" style="background:${esc(b.color)}"></span> ${esc(b.name)}`
            : '<span class="text-muted">· Todas las sucursales</span>';
        $('cfUseBranchContact').classList.toggle('d-none', !b);
    }

    const contactInputs = { phone: $('cfPhone'), whatsapp: $('cfWhatsapp'), email: $('cfEmail') };
    function fillBranchContact(overwrite) {
        const b = branchesById[catalogBranchId()];
        if (!b) return;
        const values = { phone: b.phone, whatsapp: b.phone, email: b.email };
        Object.entries(values).forEach(([key, value]) => {
            const input = contactInputs[key];
            if (value && (overwrite || !input.value.trim())) input.value = value;
        });
        schedulePreview();
    }
    $('cfUseBranchContact').addEventListener('click', () => fillBranchContact(true));

    if (branchInput && branchInput.tagName === 'SELECT') {
        branchInput.addEventListener('change', function () {
            fillBranchContact(false);
            refresh();
        });
    }

    // ── Configuración ──
    const colorInput = $('cfColor');
    colorInput.addEventListener('input', () => { $('cfColorHex').textContent = colorInput.value; schedulePreview(); });
    const showContact = $('cfShowContact');
    showContact.addEventListener('change', () => { $('cfContactFields').classList.toggle('d-none', !showContact.checked); schedulePreview(); });
    ['cfTitle', 'cfDescription', 'cfShowPrices', 'cfPhone', 'cfWhatsapp', 'cfEmail'].forEach((id) => {
        $(id).addEventListener(id === 'cfShowPrices' ? 'change' : 'input', schedulePreview);
    });

    let logoUrl = cfg.logoUrl;
    $('cfLogo').addEventListener('change', function () {
        const file = this.files && this.files[0];
        if (!file) { logoUrl = cfg.logoUrl; schedulePreview(); return; }
        const reader = new FileReader();
        reader.onload = (ev) => { logoUrl = ev.target.result; schedulePreview(); };
        reader.readAsDataURL(file);
    });

    // ── Vista previa en vivo (réplica reducida del enlace público) ──
    let previewTimer = null;
    function schedulePreview() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(renderPreview, 120);
    }
    function renderPreview() {
        const color = colorInput.value || '#0d6efd';
        const title = $('cfTitle').value.trim() || 'Título del catálogo';
        const description = $('cfDescription').value.trim();
        const withPrices = $('cfShowPrices').checked;

        const cards = Array.from(selectedEl.querySelectorAll('.cf-sel')).map((row) => {
            const p = productsById[row.dataset.id];
            const name = row.querySelector('[data-field="custom_name"]').value.trim() || p.name;
            const desc = row.querySelector('[data-field="custom_description"]').value.trim() || p.description;
            const customPrice = row.querySelector('[data-field="custom_price"]').value;
            const price = customPrice !== '' ? Number(customPrice) : p.price;
            return `<div class="cf-preview-card">
                ${p.image ? `<img src="${esc(p.image)}" alt="">` : '<div class="ph"><i class="bi bi-image"></i></div>'}
                <div class="b">
                    <div class="n">${esc(name)}</div>
                    ${desc ? `<div class="d">${esc(desc.length > 70 ? desc.slice(0, 70) + '…' : desc)}</div>` : ''}
                    ${withPrices && price ? `<div class="p" style="color:${esc(color)}">${money(price)}</div>` : ''}
                </div>
            </div>`;
        }).join('');

        let contact = '';
        const b = branchesById[catalogBranchId()];
        const phone = $('cfPhone').value.trim(), wa = $('cfWhatsapp').value.trim(), email = $('cfEmail').value.trim();
        if (showContact.checked && (phone || wa || email || b)) {
            contact = `<div class="cf-preview-contact" style="border-top-color:${esc(color)}">
                <div class="fw-bold mb-1">Contáctanos</div>
                ${b ? `<div class="text-muted mb-1 fs-xs"><i class="bi bi-geo-alt"></i> Sucursal <strong>${esc(b.name)}</strong>${b.address ? ' · ' + esc(b.address) : ''}</div>` : ''}
                ${phone ? `<span style="color:${esc(color)}"><i class="bi bi-telephone"></i> ${esc(phone)}</span>` : ''}
                ${wa ? '<span class="badge rounded-pill" style="background:#25d366"><i class="bi bi-whatsapp"></i> WhatsApp</span>' : ''}
                ${email ? `<span style="color:${esc(color)}"><i class="bi bi-envelope"></i> ${esc(email)}</span>` : ''}
            </div>`;
        }

        $('cfPreview').innerHTML = `
            <div class="cf-preview-hero" style="background:${esc(color)}">
                ${logoUrl ? `<img src="${esc(logoUrl)}" alt="">` : ''}
                <h5>${esc(title)}</h5>
                ${description ? `<p>${esc(description)}</p>` : ''}
            </div>
            ${cards ? `<div class="cf-preview-grid">${cards}</div>` : '<div class="cf-empty">Todavía no hay productos en el catálogo.</div>'}
            ${contact}
            <div class="cf-preview-foot">${esc(cfg.companyName)} · Catálogo generado automáticamente</div>`;
    }

    // ── Refresco general ──
    function refresh() {
        renumber();
        branchWarnings();
        renderPool();
        const count = selectedIds().length;
        ['cfSelectedCount', 'cfSelectedCountTop', 'cfFooterCount'].forEach((id) => { $(id).textContent = count; });
        $('cfSelectedEmpty').classList.toggle('d-none', count > 0);
        schedulePreview();
    }

    // ── Envío: validación rápida y bloqueo contra doble clic ──
    let submitting = false;
    form.addEventListener('submit', function (e) {
        if (submitting) { e.preventDefault(); return; }
        const problems = [];
        if (!$('cfTitle').value.trim()) problems.push('Escribe el título del catálogo.');
        if (!selectedIds().length) problems.push('Agrega al menos un producto.');
        if (problems.length) {
            e.preventDefault();
            if (window.Swal) Swal.fire({ icon: 'warning', html: problems.join('<br>') }); else alert(problems.join('\n'));
            if (!$('cfTitle').value.trim()) $('cfTitle').focus();
            return;
        }
        renumber();
        submitting = true;
        const btn = $('cfSubmit');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Guardando…';
    });

    // ── Estado inicial ──
    cfg.selected.forEach(addSelected);
    if (cfg.mode === 'create') fillBranchContact(false);
    refresh();
});
</script>
@endpush
