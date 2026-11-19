<?php

namespace App\Services;

use App\Models\ApiToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envía notificaciones push a dispositivos mediante la API FCM HTTP v1.
 *
 * No depende de paquetes externos: genera el access token OAuth2 firmando un
 * JWT (RS256) con la clave privada de la cuenta de servicio de Firebase, usando
 * la extensión openssl, y envía los mensajes con el cliente HTTP de Laravel.
 */
class PushNotificationService
{
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const CACHE_KEY = 'fcm_access_token';

    /** Datos de la cuenta de servicio (decodificados del JSON). */
    private array $serviceAccount;

    public function __construct()
    {
        $this->serviceAccount = $this->loadServiceAccount();
    }

    /**
     * Envía una notificación a todos los dispositivos registrados de los usuarios dados.
     *
     * @param  int[]                 $userIds  IDs de usuarios destinatarios.
     * @param  string                $title    Título de la notificación.
     * @param  string                $body     Cuerpo de la notificación.
     * @param  array<string,mixed>   $data     Datos extra (se envían como strings).
     * @return int  Número de mensajes enviados con éxito.
     */
    public function sendToUsers(array $userIds, string $title, string $body, array $data = []): int
    {
        if (empty($userIds)) {
            return 0;
        }

        $tokens = ApiToken::query()
            ->whereIn('user_id', $userIds)
            ->whereNotNull('fcm_token')
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->pluck('fcm_token')
            ->unique()
            ->values()
            ->all();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Envía una notificación a una lista explícita de tokens FCM.
     *
     * @param  string[]              $tokens
     * @param  array<string,mixed>   $data
     * @return int  Número de mensajes enviados con éxito.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): int
    {
        if (empty($tokens)) {
            return 0;
        }

        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return 0;
        }

        $projectId = $this->serviceAccount['project_id'];
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $stringData = array_map(static fn ($v) => (string) $v, $data);

        $sent = 0;
        foreach ($tokens as $token) {
            $response = Http::withToken($accessToken)->acceptJson()->post($url, [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => $stringData,
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'prestamos_default_channel',
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $sent++;
                continue;
            }

            // Token inválido / desinstalado: lo limpiamos para no reintentar.
            $status = $response->json('error.status');
            if ($response->status() === 404 || in_array($status, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
                ApiToken::where('fcm_token', $token)->update(['fcm_token' => null]);
                continue;
            }

            Log::warning('FCM envío fallido', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        return $sent;
    }

    /**
     * Devuelve un access token OAuth2 válido (cacheado ~55 min), o null si falla.
     */
    private function accessToken(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $jwt = $this->buildSignedJwt();
        if ($jwt === null) {
            return null;
        }

        $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            Log::error('FCM: no se pudo obtener access token', ['body' => $response->json()]);
            return null;
        }

        $accessToken = (string) $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 3600);
        Cache::put(self::CACHE_KEY, $accessToken, now()->addSeconds(max(60, $expiresIn - 300)));

        return $accessToken;
    }

    /**
     * Construye y firma el JWT (RS256) usado para solicitar el access token.
     */
    private function buildSignedJwt(): ?string
    {
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64Url(json_encode([
            'iss' => $this->serviceAccount['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::OAUTH_TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = "{$header}.{$claims}";
        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $this->serviceAccount['private_key'], OPENSSL_ALGO_SHA256);
        if (!$ok) {
            Log::error('FCM: no se pudo firmar el JWT con la clave de servicio.');
            return null;
        }

        return "{$signingInput}.{$this->base64Url($signature)}";
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Carga y valida el JSON de la cuenta de servicio.
     *
     * @throws \RuntimeException si el archivo no existe o es inválido.
     */
    private function loadServiceAccount(): array
    {
        $path = config('services.firebase.credentials');
        if (!$path || !is_file($path)) {
            throw new \RuntimeException("No se encontró el archivo de credenciales de Firebase en: {$path}");
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || empty($data['private_key']) || empty($data['client_email']) || empty($data['project_id'])) {
            throw new \RuntimeException('El JSON de cuenta de servicio de Firebase es inválido o está incompleto.');
        }

        return $data;
    }
}
