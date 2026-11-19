<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'ruc', 'address', 'phone', 'email', 'logo', 'cover_image', 'description', 'active',
        'interest_next_day', 'client_rating_settings', 'currency_symbol', 'max_interest_rate',
    ];

    protected $casts = [
        'active' => 'boolean',
        'interest_next_day' => 'boolean',
        'client_rating_settings' => 'array',
        'deleted_at' => 'datetime',
    ];

    /**
     * Usuarios que pertenecen a esta empresa
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
                    ->withPivot('role_id', 'active');
    }

    /**
     * Préstamos de esta empresa
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Tipos de préstamos de esta empresa
     */
    public function loanTypes(): HasMany
    {
        return $this->hasMany(LoanType::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function creditCategories(): HasMany
    {
        return $this->hasMany(CreditCategory::class);
    }

    public function creditCategoryRules(): HasMany
    {
        return $this->hasMany(CreditCategoryRule::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }

    public function personals(): HasMany
    {
        return $this->hasMany(Personal::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    // ── Plan / suscripción (fachada) ─────────────────────────────────────────

    /** Módulos reales de ESTA empresa (override de la suscripción ?? plan). Sin suscripción: ninguno. */
    public function effectiveFeatures(): array
    {
        return $this->subscription?->effectiveFeatures() ?? [];
    }

    /** ¿El plan de la empresa incluye el módulo? Acepta varios (OR). */
    public function planAllows(string ...$features): bool
    {
        return array_intersect($features, $this->effectiveFeatures()) !== [];
    }

    /** Tope real del recurso para ESTA empresa (null = ilimitado). Sin suscripción: 0. */
    public function effectiveLimit(string $key): ?int
    {
        if (!$this->subscription) {
            return 0;
        }

        return $this->subscription->effectiveLimitFor($key);
    }

    /** Uso actual del recurso limitado. */
    public function usageFor(string $key): int
    {
        return match ($key) {
            'users' => (int) DB::table('company_user')->where('company_id', $this->id)->where('active', true)->count(),
            'branches' => Branch::withoutGlobalScopes()->where('company_id', $this->id)->count(),
            'products' => Product::withoutGlobalScopes()->where('company_id', $this->id)->count(),
            'loans' => Loan::withoutGlobalScopes()->where('company_id', $this->id)
                ->whereIn('status', Loan::OPEN_STATUSES)->count(),
            default => 0,
        };
    }

    public function withinLimit(string $key): bool
    {
        $limit = $this->effectiveLimit($key);

        return $limit === null || $this->usageFor($key) < $limit;
    }

    public function subscriptionAllowsWrite(): bool
    {
        return (bool) $this->subscription?->allowsWrite();
    }

    public function subscriptionAllowsRead(): bool
    {
        return (bool) $this->subscription?->allowsRead();
    }

    /**
     * Obtener el rol de un usuario dentro de esta empresa
     */
    public function getRoleForUser(User $user): ?Role
    {
        $pivot = $this->users()
                      ->where('user_id', $user->id)
                      ->first()?->pivot;

        return $pivot ? Role::find($pivot->role_id) : null;
    }

    /**
     * Obtener permisos efectivos (del rol + permisos adicionales)
     */
    /**
     * Permisos efectivos = (rol ∪ permisos directos) filtrados por los módulos del plan.
     * Se memoriza por petición: el menú lo consulta decenas de veces.
     */
    public function getPermissionsForUser(User $user): array
    {
        if ($user->is_super_admin) {
            return Permission::all()->pluck('slug')->toArray();
        }

        // La memoria vive solo durante la petición actual.
        $requestId = app()->bound('request') ? spl_object_id(app('request')) : 0;
        if (self::$permissionCacheRequest !== $requestId) {
            self::$permissionCache = [];
            self::$permissionCacheRequest = $requestId;
        }

        $cacheKey = $this->id . ':' . $user->id;
        if (isset(self::$permissionCache[$cacheKey])) {
            return self::$permissionCache[$cacheKey];
        }

        $role = $this->getRoleForUser($user);

        $rows = collect();
        if ($role) {
            $rows = $role->permissions()->get(['permissions.slug', 'permissions.module']);
        }

        // Permisos adicionales directos (por empresa)
        $direct = DB::table('user_permission')
            ->where('user_id', $user->id)
            ->where('company_id', $this->id)
            ->join('permissions', 'user_permission.permission_id', '=', 'permissions.id')
            ->get(['permissions.slug', 'permissions.module']);

        $features = $this->effectiveFeatures();

        $permissions = $rows->concat($direct)
            ->filter(fn ($p) => Plan::permissionModuleAllowed($p->module, $features))
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();

        return self::$permissionCache[$cacheKey] = $permissions;
    }

    /** @var array<string, string[]> */
    private static array $permissionCache = [];

    private static ?int $permissionCacheRequest = null;

    /** Limpia la memoria de permisos (tests, cambios de rol en la misma petición). */
    public static function flushPermissionCache(): void
    {
        self::$permissionCache = [];
    }
}
