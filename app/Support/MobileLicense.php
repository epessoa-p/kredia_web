<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/**
 * Caducidad de la app móvil (config api.mobile_expires_at). Se calcula con la hora
 * del servidor, así que cambiar la fecha del celular no la evita.
 */
class MobileLicense
{
    public const EXPIRED_MESSAGE = 'El periodo de prueba de la aplicación terminó. Comuníquese con el proveedor del sistema.';

    /** Fecha en que vence (inicio del día), o null si no hay caducidad configurada. */
    public static function expiresAt(): ?Carbon
    {
        $value = trim((string) config('api.mobile_expires_at'));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    public static function expired(): bool
    {
        $expiresAt = self::expiresAt();

        return $expiresAt !== null && now()->greaterThanOrEqualTo($expiresAt);
    }

    /**
     * Estado para la app: expires_at, days_left, warning, expired, message.
     * Con empresa y sin fecha global configurada, se deriva de la SUSCRIPCIÓN de la empresa.
     */
    public static function status(?\App\Models\Company $company = null): array
    {
        $expiresAt = self::expiresAt();
        if (!$expiresAt) {
            return $company ? self::fromSubscription($company) : self::none();
        }

        $expired = self::expired();
        $daysLeft = $expired ? 0 : (int) now()->startOfDay()->diffInDays($expiresAt);
        $warning = !$expired && $daysLeft <= (int) config('api.mobile_warning_days', 10);

        $message = match (true) {
            $expired => self::EXPIRED_MESSAGE,
            $warning && $daysLeft <= 1 => 'La versión de prueba de la aplicación vence mañana (' . $expiresAt->format('d/m/Y') . ').',
            $warning => "La versión de prueba de la aplicación vence el {$expiresAt->format('d/m/Y')} (quedan {$daysLeft} días).",
            default => null,
        };

        return [
            'expires_at' => $expiresAt->toDateString(),
            'days_left' => $daysLeft,
            'warning' => $warning,
            'expired' => $expired,
            'message' => $message,
        ];
    }

    private static function none(): array
    {
        return ['expires_at' => null, 'days_left' => null, 'warning' => false, 'expired' => false, 'message' => null];
    }

    /** Aviso de vencimiento según la suscripción de la empresa. */
    private static function fromSubscription(\App\Models\Company $company): array
    {
        $subscription = $company->subscription;
        if (!$subscription) {
            return self::none();
        }

        $end = $subscription->periodEndsAt();
        $daysLeft = $subscription->daysLeft();
        $warningDays = (int) config('api.mobile_warning_days', 10);

        $expired = !$subscription->allowsRead();
        $inGrace = $subscription->inGrace();
        $warning = !$expired && ($inGrace || ($daysLeft !== null && $daysLeft <= $warningDays));

        $message = match (true) {
            $expired => 'La suscripción de la empresa no está vigente. Comuníquese con el proveedor del sistema.',
            $inGrace => 'La suscripción venció el ' . $end?->format('d/m/Y') . '. Solo puede consultar hasta el '
                . $subscription->graceEndsAt()?->format('d/m/Y') . '.',
            $warning && $daysLeft <= 1 => 'La suscripción de la empresa vence mañana (' . $end?->format('d/m/Y') . ').',
            $warning => "La suscripción de la empresa vence el {$end?->format('d/m/Y')} (quedan {$daysLeft} días).",
            default => null,
        };

        return [
            'expires_at' => $end?->toDateString(),
            'days_left' => $daysLeft,
            'warning' => $warning,
            'expired' => $expired,
            'message' => $message,
        ];
    }
}
