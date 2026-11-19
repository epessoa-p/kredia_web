<?php

return [
    'token_ttl_hours' => (int) env('API_TOKEN_TTL_HOURS', 720),

    // Caducidad de la app móvil (periodo de prueba). Fecha AAAA-MM-DD: desde ese día la API rechaza
    // la app (403 app_expired); la web no se ve afectada. Vacío = sin caducidad.
    // Para extenderla: cambiar MOBILE_APP_EXPIRES_AT en el .env y ejecutar php artisan config:clear.
    'mobile_expires_at' => env('MOBILE_APP_EXPIRES_AT', '2026-12-10'),
    // Días antes del vencimiento en que la app muestra el aviso.
    'mobile_warning_days' => (int) env('MOBILE_APP_WARNING_DAYS', 10),
];
