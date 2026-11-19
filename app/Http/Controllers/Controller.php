<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * ¿La empresa ya alcanzó el tope de su plan para este recurso?
     * El superadmin nunca tiene tope. $key: 'users' | 'branches' | 'products' | 'loans'.
     */
    protected function planLimitReached(?int $companyId, string $key): bool
    {
        if (!$companyId || auth()->user()?->is_super_admin) {
            return false;
        }

        $company = Company::find($companyId);

        return $company && !$company->withinLimit($key);
    }

    /**
     * Mensaje del primer límite alcanzado entre $keys, o null si hay cupo en todos.
     * Ej.: $this->planLimitError($companyId, ['loans', 'products'])
     */
    protected function planLimitError(?int $companyId, array $keys): ?string
    {
        $labels = [
            'users' => 'usuarios',
            'branches' => 'sucursales',
            'products' => 'productos',
            'loans' => 'préstamos vigentes',
        ];

        foreach ($keys as $key) {
            if ($this->planLimitReached($companyId, $key)) {
                return 'Alcanzaste el límite de ' . ($labels[$key] ?? $key)
                    . ' de tu plan. Contacta a tu proveedor para ampliarlo.';
            }
        }

        return null;
    }

    /**
     * Estado del cupo para pintar/deshabilitar el botón "Crear".
     *
     * @return array{reached: bool, usage: int, max: ?int, unlimited: bool}
     */
    protected function planLimitStatus(?int $companyId, string $key): array
    {
        $company = $companyId ? Company::find($companyId) : null;

        if (!$company || auth()->user()?->is_super_admin) {
            return ['reached' => false, 'usage' => $company?->usageFor($key) ?? 0, 'max' => null, 'unlimited' => true];
        }

        $max = $company->effectiveLimit($key);
        $usage = $company->usageFor($key);

        return [
            'reached' => $max !== null && $usage >= $max,
            'usage' => $usage,
            'max' => $max,
            'unlimited' => $max === null,
        ];
    }
}
