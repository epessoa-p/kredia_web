<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo el operador de la plataforma (users.is_super_admin = 1).
 * No se puede obtener por rol asignado en una empresa.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No autenticado.'], 401)
                : redirect('login');
        }

        if (!$user->is_super_admin) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Solo el superadministrador.', 'code' => 'permission_denied'], 403)
                : response()->view('errors.403', [], 403);
        }

        return $next($request);
    }
}
