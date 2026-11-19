<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web: fija la empresa activa (Tenancy) a partir de la sesión.
 * Superadmin ⇒ null (sin filtro: ve y gestiona todas las empresas).
 * Se anexa al grupo 'web', así que corre en toda petición web.
 */
class SetTenant
{
    public function __construct(private Tenancy $tenancy)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_super_admin) {
            $this->tenancy->set($user->getCurrentCompany()?->id);
        } else {
            $this->tenancy->set(null);
        }

        return $next($request);
    }
}
