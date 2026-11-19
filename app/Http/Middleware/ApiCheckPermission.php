<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiCheckPermission
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = $request->user();
        if (!$user) {
            return $this->forbidden('No autenticado.');
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        $company = $request->attributes->get('apiCompany');
        if (!$company) {
            return $this->forbidden('No se encontró empresa para validar permisos.');
        }

        if (count($permissions) === 0) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermissionInCompany((string) $permission, $company)) {
                return $next($request);
            }
        }

        return $this->forbidden('No tienes permisos para esta acción.');
    }

    protected function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 403);
    }
}
