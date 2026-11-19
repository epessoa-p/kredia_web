<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual de usuario — Sistema de Préstamos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    @include('help.partials.content-styles')
    <style>
        body { background: #f3f6fa; color: #1f2d3d; }
        .help-top {
            background: linear-gradient(120deg, #0f3d68, #1677a8); color: #fff;
            position: sticky; top: 0; z-index: 20; box-shadow: 0 2px 10px rgba(0,0,0,.15);
        }
        .help-top .title { font-weight: 700; font-size: 1.05rem; }
        .help-top .sub { font-size: .78rem; opacity: .8; }
        .help-toc { position: sticky; top: 76px; max-height: calc(100vh - 92px); overflow-y: auto; }
        .help-toc a {
            display: flex; gap: .5rem; align-items: center; padding: .38rem .6rem; border-radius: 8px;
            color: #2b3d50; text-decoration: none; font-size: .9rem;
        }
        .help-toc a:hover { background: #e4edf6; }
        .help-toc a.active { background: #0f3d68; color: #fff; }
        .help-toc .num { width: 1.4rem; text-align: right; opacity: .65; font-size: .8rem; }
        .help-chapter {
            background: #fff; border-radius: 14px; padding: 1.4rem 1.6rem; margin-bottom: 1.2rem;
            box-shadow: 0 2px 10px rgba(15, 61, 104, .06); scroll-margin-top: 80px;
        }
        .help-chapter .cap-num { font-size: .78rem; font-weight: 700; color: #1677a8; text-transform: uppercase; letter-spacing: .05em; }
        .help-chapter .cap-icon { font-size: 1.3rem; color: #1677a8; margin-right: .4rem; }
        .search-box { max-width: 420px; }
        mark { background: #ffe58a; padding: 0; }
        #no-results { display: none; }
        @media (max-width: 991.98px) {
            .help-chapter { padding: 1.1rem 1rem; }
        }
        @media (max-width: 767.98px) {
            .help-chapter { scroll-margin-top: 150px; }
        }
        @media print {
            .help-top, .help-toc-col, #mobile-toc { display: none !important; }
            .help-chapter { box-shadow: none; page-break-before: always; }
        }
    </style>
</head>
<body>
<header class="help-top">
    <div class="container-xl py-2 d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-book fs-4 me-1"></i>
        <div class="me-auto">
            <div class="title">Manual de usuario</div>
            <div class="sub">Sistema de Préstamos · web y app móvil</div>
        </div>
        <div class="search-box flex-grow-1 order-3 order-md-0">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" id="help-search" class="form-control no-upper" placeholder="Buscar en el manual (ej. cerrar caja, multa, cuota)…" autocomplete="off">
            </div>
        </div>
        <a href="{{ route('help.pdf') }}" class="btn btn-sm btn-light fw-semibold" id="pdf-btn">
            <i class="bi bi-file-earmark-pdf text-danger me-1"></i>Descargar PDF
        </a>
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-sm btn-outline-light">
                <i class="bi bi-arrow-left me-1"></i>Volver al sistema
            </a>
        @endauth
    </div>
</header>

<div class="container-xl py-4">
    <div class="row g-4">
        <aside class="col-lg-3 help-toc-col d-none d-lg-block">
            <nav class="help-toc" id="toc">
                @foreach($chapters as $slug => [$title, $icon])
                    <a href="#{{ $slug }}" data-slug="{{ $slug }}">
                        <span class="num">{{ $loop->iteration }}.</span>
                        <i class="bi {{ $icon }}"></i>
                        <span>{{ $title }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <main class="col-lg-9">
            <div class="d-lg-none mb-3" id="mobile-toc">
                <select class="form-select" onchange="if (this.value) location.hash = this.value">
                    <option value="">Ir a un capítulo…</option>
                    @foreach($chapters as $slug => [$title, $icon])
                        <option value="#{{ $slug }}">{{ $loop->iteration }}. {{ $title }}</option>
                    @endforeach
                </select>
            </div>

            <div class="alert alert-warning" id="no-results">
                <i class="bi bi-search me-1"></i> No se encontró nada con esa búsqueda. Prueba con otra palabra.
            </div>

            @foreach($chapters as $slug => [$title, $icon])
                <section class="help-chapter manual" id="{{ $slug }}">
                    <div class="cap-num">Capítulo {{ $loop->iteration }}</div>
                    <h2><i class="bi {{ $icon }} cap-icon"></i>{{ $title }}</h2>
                    @include('help.chapters.' . $slug)
                </section>
            @endforeach

            <p class="text-center text-muted small mt-4">
                ¿No encuentras lo que buscas? Consulta con el administrador del sistema.
            </p>
        </main>
    </div>
</div>

<script>
(function () {
    // Índice: marcar el capítulo visible.
    const links = document.querySelectorAll('#toc a');
    const chapters = document.querySelectorAll('.help-chapter');
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            links.forEach(function (a) { a.classList.toggle('active', a.dataset.slug === entry.target.id); });
        });
    }, { rootMargin: '-80px 0px -70% 0px' });
    chapters.forEach(function (c) { observer.observe(c); });

    // Buscador: muestra solo las tareas (h3 + su contenido) que contienen el texto.
    // Cada capítulo se divide en bloques: el bloque inicial y uno por cada h3.
    chapters.forEach(function (chapter) {
        let block = null;
        Array.from(chapter.children).forEach(function (el) {
            if (el.classList.contains('cap-num') || el.tagName === 'H2') return;
            if (el.tagName === 'H3' || !block) {
                block = document.createElement('div');
                block.className = 'help-block';
                chapter.insertBefore(block, el);
            }
            block.appendChild(el);
        });
    });

    const normalize = function (s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
    const blocks = Array.from(document.querySelectorAll('.help-block')).map(function (el) {
        return { el: el, text: normalize(el.textContent) };
    });
    const input = document.getElementById('help-search');
    const noResults = document.getElementById('no-results');
    let timer = null;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            const words = normalize(input.value.trim()).split(/\s+/).filter(Boolean);
            let any = false;
            blocks.forEach(function (b) {
                const show = words.every(function (w) { return b.text.includes(w); });
                b.el.style.display = show ? '' : 'none';
            });
            chapters.forEach(function (c) {
                const visible = Array.from(c.querySelectorAll('.help-block')).some(function (b) { return b.style.display !== 'none'; });
                c.style.display = visible ? '' : 'none';
                any = any || visible;
            });
            noResults.style.display = any ? 'none' : 'block';
        }, 150);
    });

    // Evitar doble clic en la descarga del PDF (tarda unos segundos en generarse).
    const pdfBtn = document.getElementById('pdf-btn');
    pdfBtn.addEventListener('click', function () {
        if (pdfBtn.classList.contains('disabled')) return;
        pdfBtn.classList.add('disabled');
        pdfBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generando PDF…';
        setTimeout(function () {
            pdfBtn.classList.remove('disabled');
            pdfBtn.innerHTML = '<i class="bi bi-file-earmark-pdf text-danger me-1"></i>Descargar PDF';
        }, 6000);
    });
})();
</script>
</body>
</html>
