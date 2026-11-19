<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Manual de usuario — Sistema de Préstamos</title>
    @include('help.partials.content-styles')
    <style>
        @page { margin: 70px 55px 60px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1f2d3d; }
        header.pdf-head { position: fixed; top: -48px; left: 0; right: 0; font-size: 9px; color: #7a8a9a; border-bottom: 1px solid #d5dee8; padding-bottom: 4px; }
        footer.pdf-foot { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 9px; color: #7a8a9a; text-align: right; }
        footer.pdf-foot .page:after { content: counter(page); }
        .cover { text-align: center; padding-top: 180px; }
        .cover h1 { font-size: 30px; color: #0f3d68; margin: 0 0 8px; }
        .cover .sub { font-size: 14px; color: #4a5b6d; }
        .cover .fecha { margin-top: 40px; font-size: 11px; color: #7a8a9a; }
        .toc h2 { font-size: 18px; color: #0f3d68; }
        .toc ol { font-size: 12px; line-height: 1.9; }
        .chapter { page-break-before: always; }
        .cap-num { font-size: 9px; font-weight: bold; color: #1677a8; text-transform: uppercase; letter-spacing: 1px; }
        .manual h2 { font-size: 17px; }
        .manual h3 { font-size: 12.5px; page-break-after: avoid; }
        .manual h4 { font-size: 11px; }
        .manual .boton { font-family: 'DejaVu Sans', sans-serif; font-weight: bold; }
        .manual .nota, .manual .aviso, .manual .consejo, .manual table.tabla tr { page-break-inside: avoid; }
    </style>
</head>
<body>
<header class="pdf-head">Manual de usuario — Sistema de Préstamos</header>
<footer class="pdf-foot">Página <span class="page"></span></footer>

<div class="cover">
    <h1>Manual de usuario</h1>
    <div class="sub">Sistema de Préstamos · web y app móvil</div>
    <div class="fecha">Versión del {{ now()->format('d/m/Y') }}</div>
</div>

<div class="toc" style="page-break-before: always;">
    <h2>Contenido</h2>
    <ol>
        @foreach($chapters as $slug => [$title, $icon])
            <li>{{ $title }}</li>
        @endforeach
    </ol>
</div>

@foreach($chapters as $slug => [$title, $icon])
    <div class="chapter manual">
        <div class="cap-num">Capítulo {{ $loop->iteration }}</div>
        <h2>{{ $title }}</h2>
        @include('help.chapters.' . $slug)
    </div>
@endforeach
</body>
</html>
