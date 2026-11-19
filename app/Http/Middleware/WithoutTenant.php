<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rutas públicas que muestran datos de una empresa concreta (p. ej. catálogo /c/{slug}):
 * se quita el filtro de empresa aunque el visitante tenga sesión en otra empresa.
 */
class WithoutTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        app(Tenancy::class)->set(null);

        return $next($request);
    }
}
