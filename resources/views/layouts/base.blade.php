<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Kredia')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    {{-- Estándar visual único (tokens, escala de letra, botones, colores por módulo) --}}
    <link rel="stylesheet" href="{{ asset('css/kredia-ui.css') }}?v={{ filemtime(public_path('css/kredia-ui.css')) }}">
    @stack('styles')
</head>
<body>
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    @stack('scripts')
    <script>
    document.addEventListener('wheel', function () {
        if (document.activeElement && document.activeElement.type === 'number') {
            document.activeElement.blur();
        }
    }, { passive: true });

    // ── Forzar MAYÚSCULAS en inputs de texto y textareas de todo el proyecto ──
    // Convierte el valor (no solo visual) para que se guarde en mayúsculas.
    // Excluye: email, contraseña, búsqueda/url/tel, y cualquier campo con
    // clase .no-upper, atributo data-no-upper, o cuyo name contenga
    // email/password/login/slug/url.
    (function () {
        // radio/checkbox: su valor lo fija el sistema (no lo escribe el usuario), no se debe cambiar.
        const EXCLUDE_TYPES = ['email', 'password', 'search', 'url', 'tel', 'number', 'date', 'datetime-local', 'time', 'color', 'file', 'range', 'hidden', 'radio', 'checkbox'];
        const EXCLUDE_NAME  = /(email|correo|password|contrase|login|slug|url)/i;

        function shouldUpper(el) {
            if (!el) return false;
            const tag = el.tagName;
            if (tag !== 'INPUT' && tag !== 'TEXTAREA') return false;
            if (el.classList.contains('no-upper') || el.hasAttribute('data-no-upper')) return false;
            if (tag === 'INPUT') {
                const type = (el.getAttribute('type') || 'text').toLowerCase();
                if (EXCLUDE_TYPES.includes(type)) return false;
            }
            const name = el.getAttribute('name') || '';
            if (EXCLUDE_NAME.test(name)) return false;
            return true;
        }

        document.addEventListener('input', function (e) {
            const el = e.target;
            if (!shouldUpper(el)) return;
            const upper = el.value.toUpperCase();
            if (upper === el.value) return;
            const start = el.selectionStart;
            const end = el.selectionEnd;
            el.value = upper;
            try { el.setSelectionRange(start, end); } catch (err) { /* algunos inputs no soportan selección */ }
        }, true);
    })();
    </script>
</body>
</html>
