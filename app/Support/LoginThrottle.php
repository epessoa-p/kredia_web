<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Límite de intentos de inicio de sesión (web y API): 5 fallos por usuario+IP, bloqueo 60 s. */
final class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;
    public const DECAY_SECONDS = 60;

    public static function key(string $login, ?string $ip): string
    {
        return 'login:' . Str::lower(trim($login)) . '|' . ($ip ?? 'unknown');
    }
}
