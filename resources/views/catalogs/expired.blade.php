<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo no disponible</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/kredia-ui.css') }}">
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center px-4">
        <div class="mb-4 fs-1" style="color:#dee2e6">
            <i class="bi bi-clock-history"></i>
        </div>
        <h3 class="fw-bold text-dark mb-2">Catálogo no disponible</h3>
        <p class="text-muted mb-0">
            Este catálogo ya no se encuentra disponible.
            @if($catalog->expires_at && $catalog->expires_at->isPast())
                <br>Venció el {{ $catalog->expires_at->format('d/m/Y') }}.
            @endif
        </p>
    </div>
</body>
</html>
