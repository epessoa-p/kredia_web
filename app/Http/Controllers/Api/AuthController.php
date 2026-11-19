<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\CashRegister;
use App\Models\CashRegisterSession;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\LoginThrottle;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Controlador de autenticación para la API.
 *
 * Gestiona el inicio de sesión, la consulta del perfil propio
 * y el cierre de sesión mediante tokens de API personalizados.
 */
class AuthController extends Controller
{
    /**
     * Autentica un usuario y genera un token de acceso.
     *
     * Acepta email o nombre de usuario como login. Verifica la contraseña,
     * resuelve la empresa activa del usuario y emite un ApiToken con TTL
     * configurable (por defecto 720 horas). Devuelve el token, datos del
     * usuario, empresa y listado de permisos activos.
     *
     * @param  Request  $request  Campos: login, password, company_id (opcional), device_name (opcional)
     * @return JsonResponse  200 con token y perfil, 401 si las credenciales no son válidas,
     *                        403 si el usuario está inactivo o sin empresa activa.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'company_id' => 'nullable|integer|exists:companies,id',
            'device_name' => 'nullable|string|max:100',
        ]);

        $login = trim((string) $validated['login']);
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'name';

        $throttleKey = LoginThrottle::key($login, $request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, LoginThrottle::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
                'code' => 'too_many_attempts',
                'retry_after' => $seconds,
            ], 429);
        }

        $user = User::where($field, $login)->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, LoginThrottle::DECAY_SECONDS);
            return response()->json(['message' => 'Credenciales no válidas.'], 401);
        }

        RateLimiter::clear($throttleKey);

        if (!$user->active) {
            return response()->json(['message' => 'Usuario inactivo.'], 403);
        }

        $company = $this->resolveCompany($user, $validated['company_id'] ?? null);
        if (!empty($validated['company_id']) && !$company) {
            return response()->json(['message' => 'Empresa no disponible para este usuario.'], 403);
        }

        if (!$user->is_super_admin && !$company) {
            return response()->json(['message' => 'No tienes empresa activa asignada.'], 403);
        }

        if ($error = $this->companyAccessError($user, $company)) {
            return $error;
        }

        // Los datos del perfil se resuelven dentro de la empresa elegida.
        app(\App\Support\Tenancy::class)->set($company?->id);

        $plainToken = Str::random(80);
        $token = ApiToken::create([
            'user_id' => $user->id,
            'company_id' => $company?->id,
            'name' => $validated['device_name'] ?? 'mobile',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addHours((int) config('api.token_ttl_hours', 720)),
        ]);

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'expires_at' => optional($token->expires_at)->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => (bool) $user->is_super_admin,
            ],
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'ruc' => $company->ruc,
                'currency_symbol' => \App\Support\Currency::symbol($company),
            ] : null,
            'permissions' => $this->resolvePermissions($user, $company),
            'plan' => $this->planPayload($user, $company),
            'subscription' => $this->subscriptionPayload($user, $company),
            'cash_register' => $this->resolveCashRegisterInfo($user, $company),
            'companies' => $this->availableCompanies($user),
            'license' => \App\Support\MobileLicense::status($company),
        ]);
    }

    /**
     * Retorna el perfil del usuario autenticado.
     *
     * Incluye datos del usuario, empresa vinculada al token, permisos activos
     * e información del token (nombre, expiración, último uso).
     *
     * @param  Request  $request
     * @return JsonResponse  200 con perfil completo.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $request->attributes->get('apiCompany');
        $token = $request->attributes->get('apiToken');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_super_admin' => (bool) $user->is_super_admin,
                'active' => (bool) $user->active,
            ],
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'ruc' => $company->ruc,
                'phone' => $company->phone,
                'email' => $company->email,
                'currency_symbol' => \App\Support\Currency::symbol($company),
            ] : null,
            'permissions' => $this->resolvePermissions($user, $company),
            'plan' => $this->planPayload($user, $company),
            'subscription' => $this->subscriptionPayload($user, $company),
            'cash_register' => $this->resolveCashRegisterInfo($user, $company),
            'companies' => $this->availableCompanies($user),
            'license' => \App\Support\MobileLicense::status($company),
            'token' => [
                'name' => $token?->name,
                'expires_at' => optional($token?->expires_at)->toIso8601String(),
                'last_used_at' => optional($token?->last_used_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Cierra la sesión revocando el token actual.
     *
     * Marca el token como revocado (revoked_at = ahora). Las peticiones
     * posteriores con ese token serán rechazadas por el middleware de autenticación.
     *
     * @param  Request  $request
     * @return JsonResponse  200 con mensaje de confirmación.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('apiToken');
        if ($token) {
            $token->update([
                'revoked_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }

    /**
     * Registra/actualiza el token de Firebase (FCM) del dispositivo actual.
     *
     * Asocia el token push al ApiToken de la sesión en curso para poder enviar
     * notificaciones dirigidas a ese dispositivo. Si otro registro de token tenía
     * el mismo fcm_token (p.ej. reinstalación), se limpia para evitar duplicados.
     *
     * @param  Request  $request  Campos: fcm_token (requerido), platform (opcional)
     * @return JsonResponse  200 con confirmación.
     */
    public function updateDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:255',
            'platform' => 'nullable|string|max:20',
        ]);

        /** @var ApiToken|null $token */
        $token = $request->attributes->get('apiToken');
        if (!$token) {
            return response()->json(['message' => 'Token de sesión no encontrado.'], 401);
        }

        // Evita que el mismo fcm_token quede asignado a varias sesiones.
        ApiToken::where('fcm_token', $validated['fcm_token'])
            ->where('id', '!=', $token->id)
            ->update(['fcm_token' => null]);

        $token->update([
            'fcm_token' => $validated['fcm_token'],
            'platform' => $validated['platform'] ?? 'android',
        ]);

        return response()->json(['message' => 'Token de dispositivo registrado.']);
    }

    /**
     * Resuelve la empresa activa para el usuario en el momento del login.
     *
     * Para super-admin: usa la empresa indicada o null (acceso global).
     * Para usuarios normales: filtra entre sus empresas activas; si no se
     * especifica company_id, retorna la primera disponible.
     *
     * @param  User      $user
     * @param  int|null  $companyId  ID de empresa solicitado (puede ser null).
     * @return Company|null
     */
    protected function resolveCompany(User $user, ?int $companyId): ?Company
    {
        if ($user->is_super_admin) {
            if ($companyId) {
                return Company::where('active', true)->find($companyId);
            }

            return null;
        }

        $companies = $user->activeCompanies();

        if ($companyId) {
            return $companies->where('companies.id', $companyId)->first();
        }

        return $companies->first();
    }

    /**
     * Elige o cambia la empresa con la que trabaja el token (como el selector de empresa de la web).
     * El super admin no tiene empresa: la app le pide elegir una al ingresar.
     *
     * @param  Request  $request  Campos: company_id
     * @return JsonResponse  200 con el mismo perfil que /auth/me, 422 si no tiene acceso a esa empresa.
     */
    public function selectCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|integer',
        ], [
            'company_id.required' => 'Elige una empresa.',
            'company_id.integer' => 'La empresa elegida no es válida.',
        ]);

        $user = $request->user();
        $company = $this->resolveCompany($user, (int) $validated['company_id']);
        if (!$company) {
            return response()->json([
                'message' => 'No tienes acceso a esa empresa o está inactiva.',
                'errors' => ['company_id' => ['No tienes acceso a esa empresa o está inactiva.']],
            ], 422);
        }

        if ($error = $this->companyAccessError($user, $company)) {
            return $error;
        }

        $token = $request->attributes->get('apiToken');
        $token->forceFill(['company_id' => $company->id])->save();
        $request->attributes->set('apiCompany', $company);
        app(\App\Support\Tenancy::class)->set($company->id);

        return $this->me($request);
    }

    /**
     * Empresas que el usuario puede elegir: todas las activas para el super admin; para el resto,
     * aquellas en las que está activo.
     */
    /** Plan efectivo de la empresa (con overrides) para que la app adapte su menú. */
    protected function planPayload(User $user, ?Company $company): ?array
    {
        if (!$company) {
            return null;
        }

        $subscription = $company->subscription;

        return [
            'name' => $subscription?->plan?->name,
            'slug' => $subscription?->plan?->slug,
            // El superadmin ve todos los módulos (no está sujeto a plan).
            'features' => $user->is_super_admin ? array_keys(\App\Models\Plan::MODULES) : $company->effectiveFeatures(),
        ];
    }

    protected function subscriptionPayload(User $user, ?Company $company): ?array
    {
        if (!$company) {
            return null;
        }

        $status = $company->subscription?->toStatusArray() ?? [
            'status' => null, 'effective_status' => 'none', 'allows_write' => false, 'allows_read' => false,
            'period_ends_at' => null, 'grace_ends_at' => null, 'days_left' => null,
        ];

        if ($user->is_super_admin) {
            $status['allows_write'] = true;
            $status['allows_read'] = true;
        }

        return $status;
    }

    /**
     * ¿La empresa puede usar la app? Devuelve la respuesta de error o null si puede.
     * El superadmin siempre puede.
     */
    protected function companyAccessError(User $user, ?Company $company): ?JsonResponse
    {
        if ($user->is_super_admin || !$company) {
            return null;
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

        return null;
    }

    protected function availableCompanies(User $user): array
    {
        $query = $user->is_super_admin
            ? Company::query()->where('active', true)
            : $user->activeCompanies();

        return $query->orderBy('companies.name')->get(['companies.*'])
            ->map(fn (Company $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'ruc' => $c->ruc,
                'currency_symbol' => \App\Support\Currency::symbol($c),
            ])->values()->all();
    }

    /**
     * Obtiene el listado de slugs de permisos del usuario para la empresa indicada.
     *
     * Super-admin recibe todos los permisos del sistema. Usuarios normales
     * reciben solo los permisos que tienen asignados en esa empresa.
     *
     * @param  User         $user
     * @param  Company|null $company
     * @return string[]  Array de slugs de permisos ordenados alfabéticamente.
     */
    protected function resolvePermissions(User $user, ?Company $company): array
    {
        if ($user->is_super_admin) {
            return Permission::query()->orderBy('slug')->pluck('slug')->toArray();
        }

        if (!$company) {
            return [];
        }

        $permissions = $company->getPermissionsForUser($user);
        sort($permissions);

        return $permissions;
    }

    /**
     * Resuelve la caja asignada al usuario y su estado (abierta/cerrada).
     *
     * Busca el registro Personal del usuario, luego la caja activa asignada
     * a ese personal en la empresa. Si existe, indica si hay una sesión abierta.
     *
     * @param  User         $user
     * @param  Company|null $company
     * @return array|null  Datos de la caja, o null si el usuario no tiene caja asignada.
     */
    protected function resolveCashRegisterInfo(User $user, ?Company $company): ?array
    {
        if (!$company) {
            return null;
        }

        $personal = Personal::where('user_id', $user->id)->first();
        if (!$personal) {
            return null;
        }

        // Todas las cajas activas asignadas al usuario en esta empresa
        $registerIds = CashRegister::where('assigned_personal_id', $personal->id)
            ->where('company_id', $company->id)
            ->where('active', true)
            ->pluck('id');

        if ($registerIds->isEmpty()) {
            return null;
        }

        // Buscar la sesión abierta entre todas sus cajas
        $openSession = CashRegisterSession::whereIn('cash_register_id', $registerIds)
            ->whereNull('closed_at')
            ->with('cashRegister.branch')
            ->latest()
            ->first();

        // Ninguna caja abierta → null
        if (!$openSession) {
            return null;
        }

        $cashRegister = $openSession->cashRegister;

        return [
            'id'         => $cashRegister->id,
            'name'       => $cashRegister->name,
            'branch'     => $cashRegister->branch ? [
                'id'    => $cashRegister->branch->id,
                'name'  => $cashRegister->branch->name,
                'color' => $cashRegister->branch->color,
            ] : null,
            'is_open'    => true,
            'session_id' => $openSession->id,
        ];
    }
}
