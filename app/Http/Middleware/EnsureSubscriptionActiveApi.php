<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API: igual que EnsureSubscriptionActive pero responde JSON con `code`.
 * Corre después de 'auth-api' (necesita la empresa del token).
 *  - 403 subscription_inactive        → no permite ni lectura
 *  - 403 subscription_grace_readonly  → en gracia: lectura sí, escritura no
 *  - 403 plan_module_disabled         → el plan no incluye la app móvil
 */
class EnsureSubscriptionActiveApi
{
    /** Endpoints que siempre responden (para que la app pueda mostrar el estado y salir). */
    private const ALWAYS_ALLOWED_PATHS = ['api/v1/auth/me', 'api/v1/auth/logout', 'api/v1/auth/company'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');

        if (!$user || $user->is_super_admin || !$company || $request->is(...self::ALWAYS_ALLOWED_PATHS)) {
            return $next($request);
        }

        $subscription = $company->subscription;

        if (!$subscription || !$subscription->allowsRead()) {
            return response()->json([
                'message' => 'La suscripción de la empresa no está vigente. Contacta a tu proveedor.',
                'code' => 'subscription_inactive',
                'subscription' => $subscription?->toStatusArray(),
            ], 403);
        }

        if (!$company->planAllows('mobile_app')) {
            return response()->json([
                'message' => 'El plan de la empresa no incluye la app móvil.',
                'code' => 'plan_module_disabled',
                'module' => 'mobile_app',
            ], 403);
        }

        if (!$subscription->allowsWrite() && !$request->isMethodSafe()) {
            return response()->json([
                'message' => 'Suscripción vencida: en periodo de gracia solo puedes consultar.',
                'code' => 'subscription_grace_readonly',
                'subscription' => $subscription->toStatusArray(),
            ], 403);
        }

        return $next($request);
    }
}
