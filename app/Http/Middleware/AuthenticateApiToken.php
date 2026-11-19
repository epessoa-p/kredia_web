<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();
        if (!$plainToken) {
            return $this->unauthorized('Token no proporcionado.');
        }

        $tokenHash = hash('sha256', $plainToken);

        $apiToken = ApiToken::with(['user', 'company'])
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->first();

        if (!$apiToken || $apiToken->isExpired()) {
            return $this->unauthorized('Token inválido o expirado.');
        }

        $user = $apiToken->user;
        if (!$user || !$user->active) {
            return $this->unauthorized('Usuario no autorizado.');
        }

        $company = $apiToken->company;
        if ($company && !$company->active) {
            return $this->unauthorized('Empresa del token inactiva.');
        }

        if (!$user->is_super_admin && !$company) {
            return $this->unauthorized('El token no tiene empresa asociada.');
        }

        if (!$user->is_super_admin && $company) {
            $belongsToCompany = $user->activeCompanies()->where('companies.id', $company->id)->exists();
            if (!$belongsToCompany) {
                return $this->unauthorized('Token sin acceso válido a la empresa.');
            }
        }

        // El token ya va ligado a una empresa. X-Company-Id es opcional: si llega, debe coincidir.
        $headerCompanyId = $request->header('X-Company-Id');
        if ($headerCompanyId !== null && $headerCompanyId !== '' && (int) $headerCompanyId !== (int) $company?->id) {
            return response()->json([
                'message' => 'La empresa indicada no coincide con la sesión. Cambia de empresa desde la app.',
                'code' => 'company_mismatch',
            ], 409);
        }

        $apiToken->forceFill(['last_used_at' => now()])->save();

        // Empresa activa para el filtro automático de datos (también para el superadmin en la app,
        // que siempre trabaja dentro de la empresa elegida).
        app(Tenancy::class)->set($company?->id);

        Auth::shouldUse('web');
        Auth::setUser($user);

        $request->attributes->set('apiToken', $apiToken);
        $request->attributes->set('apiCompany', $company);

        return $next($request);
    }

    protected function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }
}
