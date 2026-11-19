<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Permission extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'module', 'description'];

    /** Nombre en español de cada módulo (columna permissions.module), en el orden en que se muestran. */
    public const MODULE_LABELS = [
        'dashboard' => 'Dashboard',
        'clients' => 'Clientes',
        'loans' => 'Préstamos',
        'payments' => 'Pagos',
        'purchases' => 'Compras',
        'sales' => 'Ventas',
        'catalogs' => 'Catálogos',
        'cash-registers' => 'Cajas',
        'products' => 'Productos',
        'warehouses' => 'Almacenes',
        'reports' => 'Reportes',
        'capital' => 'Capital',
        'document-templates' => 'Plantillas de documentos',
        'credit-categories' => 'Categorías de crédito',
        'branches' => 'Sucursales',
        'personal' => 'Personal',
        'cargos' => 'Cargos',
        'users' => 'Usuarios',
        'roles' => 'Roles',
        'companies' => 'Empresas',
    ];

    public static function moduleLabel(?string $module): string
    {
        return self::MODULE_LABELS[$module] ?? ucfirst(str_replace('-', ' ', (string) $module));
    }

    /** Permisos agrupados por módulo, en el orden de MODULE_LABELS (los módulos desconocidos al final). */
    public static function groupedByModule(): \Illuminate\Support\Collection
    {
        $order = array_flip(array_keys(self::MODULE_LABELS));

        return self::orderBy('id')->get()->groupBy('module')
            ->sortBy(fn ($perms, $module) => $order[$module] ?? 999);
    }

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }
}
