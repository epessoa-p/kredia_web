<?php

namespace App\Http\Middleware;

use App\Support\MobileLicense;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rechaza la app móvil cuando venció su periodo de prueba (config api.mobile_expires_at). */
class CheckMobileLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        if (MobileLicense::expired()) {
            return response()->json([
                'message' => MobileLicense::EXPIRED_MESSAGE,
                'code' => 'app_expired',
                'license' => MobileLicense::status(),
            ], 403);
        }

        return $next($request);
    }
}
