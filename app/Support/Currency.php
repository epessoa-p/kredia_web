<?php

namespace App\Support;

use App\Models\Company;

/**
 * Símbolo de moneda de la empresa (companies.currency_symbol), para mostrar junto a los montos.
 * En las vistas está disponible como $currencySymbol (ver AppServiceProvider).
 */
class Currency
{
    public const DEFAULT_SYMBOL = 'Bs';

    /** Caché por petición del símbolo de la empresa actual del usuario. */
    private static ?string $current = null;

    public static function symbol(?Company $company = null): string
    {
        if ($company) {
            return self::clean($company->currency_symbol);
        }

        if (self::$current === null) {
            $company = auth()->check() ? auth()->user()->getCurrentCompany() : null;
            // El super admin no tiene empresa: se usa el símbolo por defecto.
            self::$current = self::clean($company?->currency_symbol);
        }

        return self::$current;
    }

    /** Monto con símbolo, ej. "Bs 1,250.00". */
    public static function format($amount, ?Company $company = null, int $decimals = 2): string
    {
        return self::symbol($company) . ' ' . number_format((float) $amount, $decimals);
    }

    public static function forget(): void
    {
        self::$current = null;
    }

    private static function clean(?string $symbol): string
    {
        $symbol = trim((string) $symbol);

        return $symbol !== '' ? $symbol : self::DEFAULT_SYMBOL;
    }
}
