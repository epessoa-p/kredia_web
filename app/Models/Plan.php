<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan comercial del operador (GLOBAL: no lleva company_id ni scope de empresa).
 * Define qué MÓDULOS incluye (features) y qué LÍMITES impone (max_*; NULL = ilimitado).
 */
class Plan extends Model
{
    /** Catálogo de módulos que un plan puede incluir. */
    public const MODULES = [
        'pawn' => 'Empeño (préstamo con prenda)',
        'installments' => 'Préstamos en cuotas',
        'collections' => 'Cobradores y rutas',
        'inventory' => 'Inventario',
        'sales' => 'Ventas',
        'purchases' => 'Compras',
        'catalogs' => 'Catálogos públicos',
        'capital' => 'Capital',
        'notifications' => 'Notificaciones y recordatorios',
        'mobile_app' => 'App móvil',
    ];

    /**
     * permissions.module → feature(s) de plan que lo habilitan (array = OR).
     * Los módulos que NO están aquí son administrativos / datos maestros y están SIEMPRE disponibles:
     * users, roles, cargos, personal, branches, companies, clients, dashboard, reports,
     * cash-registers (la caja es núcleo: todo cobro pasa por ella), document-templates.
     */
    public const PERMISSION_MODULE_FEATURES = [
        'loans' => ['pawn', 'installments'],
        'payments' => ['pawn', 'installments'],
        'credit-categories' => ['pawn', 'installments'],
        'collections' => 'collections',
        // Productos y almacenes: los usa el empeño (prendas), inventario, ventas y compras.
        'products' => ['inventory', 'pawn', 'sales', 'purchases'],
        'warehouses' => ['inventory', 'pawn', 'sales', 'purchases'],
        'purchases' => 'purchases',
        'sales' => 'sales',
        'catalogs' => 'catalogs',
        'capital' => 'capital',
    ];

    /** Recursos con tope por plan → columna. */
    public const LIMITS = [
        'users' => 'max_users',
        'branches' => 'max_branches',
        'products' => 'max_products',
        'loans' => 'max_loans',
    ];

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'billing_period', 'trial_days',
        'max_users', 'max_branches', 'max_products', 'max_loans',
        'features', 'active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'trial_days' => 'integer',
        'max_users' => 'integer',
        'max_branches' => 'integer',
        'max_products' => 'integer',
        'max_loans' => 'integer',
        'features' => 'array',
        'active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function allows(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    /** Tope del plan para el recurso (null = ilimitado). */
    public function limitFor(string $key): ?int
    {
        $column = self::LIMITS[$key] ?? null;

        return $column ? $this->{$column} : null;
    }

    /** Features que habilitan un módulo de permisos ([] = administrativo, siempre disponible). */
    public static function featuresForPermissionModule(?string $module): array
    {
        $value = self::PERMISSION_MODULE_FEATURES[$module] ?? [];

        return (array) $value;
    }

    /** ¿El módulo de permisos está disponible con estas features? */
    public static function permissionModuleAllowed(?string $module, array $features): bool
    {
        $required = self::featuresForPermissionModule($module);

        return $required === [] || array_intersect($required, $features) !== [];
    }
}
