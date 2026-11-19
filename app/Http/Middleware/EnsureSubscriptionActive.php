<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web: corta el acceso si la suscripción de la empresa activa no está vigente.
 *  - !allowsRead()  → pantalla "suscripción bloqueada"
 *  - inGrace() + método de escritura → vuelve atrás con aviso (solo lectura)
 * Superadmin, invitados y rutas de sesión/selección de empresa pasan siempre.
 */
class EnsureSubscriptionActive
{
    /** Rutas que nunca se bloquean (salir, elegir empresa, ver el bloqueo, archivos firmados). */
    private const ALWAYS_ALLOWED = [
        'logout', 'login', 'login.store', 'select-company', 'set-company',
        'subscription.blocked', 'catalog.public', 'client-documents.file', 'help.index', 'help.pdf',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->is_super_admin || $request->routeIs(...self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        $company = $user->getCurrentCompany();
        if (!$company) {
            return $next($request); // CheckPermission/CheckCompany se encargan.
        }

        $subscription = $company->subscription;

        if (!$subscription || !$subscription->allowsRead()) {
            return redirect()->route('subscription.blocked');
        }

        if (!$subscription->allowsWrite() && !$request->isMethodSafe()) {
            $message = 'La suscripción de la empresa venció: estás en periodo de gracia y solo puedes consultar. Contacta a tu proveedor para renovarla.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'code' => 'subscription_grace_readonly'], 403)
                : back()->withInput()->withErrors(['error' => $message])->with('error', $message);
        }

        return $next($request);
    }
}
