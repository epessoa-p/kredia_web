<?php

/*
|--------------------------------------------------------------------------
| Menú lateral (definido UNA sola vez: escritorio y celular usan lo mismo)
|--------------------------------------------------------------------------
| Cada opción:
|   label       Texto visible
|   route       Nombre de la ruta
|   icon        Bootstrap Icon (sin el prefijo "bi-")
|   module      Clave de color (ver "modules" abajo y kredia-ui.css .mod-*)
|   active      Patrones de ruta que la marcan como activa (routeIs)
|   except      Patrones que NO la marcan aunque coincidan con "active"
|   permission  Uno o varios permisos (OR). null = siempre visible
|   super_admin true = solo el superadmin
|
| El color de módulo también pinta el título y los íconos de la página
| (se resuelve con App\Support\Menu::currentModule()).
*/

return [

    // Color de cada módulo (debe coincidir con kredia-ui.css y la app móvil).
    'modules' => [
        'dashboard' => ['label' => 'Inicio', 'color' => '#2563eb'],
        'loans' => ['label' => 'Préstamos', 'color' => '#7c3aed'],
        'collections' => ['label' => 'Cobranza', 'color' => '#db2777'],
        'clients' => ['label' => 'Clientes', 'color' => '#0891b2'],
        'cash' => ['label' => 'Caja y movimientos', 'color' => '#16a34a'],
        'inventory' => ['label' => 'Inventario', 'color' => '#ea580c'],
        'sales' => ['label' => 'Ventas', 'color' => '#ca8a04'],
        'purchases' => ['label' => 'Compras', 'color' => '#dc2626'],
        'catalogs' => ['label' => 'Catálogos', 'color' => '#0d9488'],
        'reports' => ['label' => 'Reportes', 'color' => '#4f46e5'],
        'capital' => ['label' => 'Capital', 'color' => '#059669'],
        'admin' => ['label' => 'Administración', 'color' => '#475569'],
        'platform' => ['label' => 'Plataforma', 'color' => '#0f172a'],
    ],

    'sections' => [
        [
            'title' => 'Operación',
            'items' => [
                ['label' => 'Inicio', 'route' => 'dashboard', 'icon' => 'house-door', 'module' => 'dashboard', 'active' => ['dashboard'], 'permission' => null],
                ['label' => 'Clientes', 'route' => 'clients.index', 'icon' => 'people', 'module' => 'clients', 'active' => ['clients.*'], 'permission' => 'clients.view'],
                ['label' => 'Préstamos', 'route' => 'loans.index', 'icon' => 'cash-coin', 'module' => 'loans', 'active' => ['loans.*'], 'except' => ['loans.payments.*'], 'permission' => 'loans.view'],
                ['label' => 'Pagos de préstamos', 'route' => 'loans.payments.index', 'icon' => 'receipt-cutoff', 'module' => 'collections', 'active' => ['loans.payments.*'], 'permission' => 'payments.view'],
                ['label' => 'Cobranza del día', 'route' => 'collections.today', 'icon' => 'geo', 'module' => 'collections', 'active' => ['collections.today'], 'permission' => 'collections.view'],
                ['label' => 'Rutas de cobranza', 'route' => 'collections.routes.index', 'icon' => 'signpost-split', 'module' => 'collections', 'active' => ['collections.routes.*'], 'permission' => 'collections.manage'],
                ['label' => 'Ventas', 'route' => 'sales.index', 'icon' => 'cart-check', 'module' => 'sales', 'active' => ['sales.*'], 'permission' => 'sales.view'],
                ['label' => 'Compras', 'route' => 'purchases.index', 'icon' => 'bag-check', 'module' => 'purchases', 'active' => ['purchases.*'], 'permission' => 'purchases.view'],
                ['label' => 'Inventario', 'route' => 'inventory.index', 'icon' => 'boxes', 'module' => 'inventory', 'active' => ['inventory.*'], 'permission' => 'warehouses.view'],
                ['label' => 'Catálogos', 'route' => 'catalogs.index', 'icon' => 'megaphone', 'module' => 'catalogs', 'active' => ['catalogs.*'], 'permission' => 'catalogs.view'],
                ['label' => 'Movimientos de caja', 'route' => 'reports.movements', 'icon' => 'activity', 'module' => 'cash', 'active' => ['reports.movements'], 'permission' => 'reports.view'],
                ['label' => 'Reportes', 'route' => 'reports.index', 'icon' => 'bar-chart-line', 'module' => 'reports', 'active' => ['reports.*'], 'except' => ['reports.movements', 'reports.aging'], 'permission' => 'reports.view'],
                ['label' => 'Morosidad', 'route' => 'reports.aging', 'icon' => 'hourglass-split', 'module' => 'reports', 'active' => ['reports.aging'], 'permission' => 'reports.view'],
                ['label' => 'Capital', 'route' => 'capital.index', 'icon' => 'bank2', 'module' => 'capital', 'active' => ['capital.*'], 'permission' => 'capital.view'],
            ],
        ],
        [
            'title' => 'Configuración',
            'items' => [
                ['label' => 'Categorías de crédito', 'route' => 'credit-categories.index', 'icon' => 'tags', 'module' => 'loans', 'active' => ['credit-categories.*'], 'permission' => 'credit-categories.view'],
                ['label' => 'Productos', 'route' => 'products.index', 'icon' => 'box-seam', 'module' => 'inventory', 'active' => ['products.*'], 'permission' => 'products.view'],
                ['label' => 'Almacenes', 'route' => 'warehouses.index', 'icon' => 'building-add', 'module' => 'inventory', 'active' => ['warehouses.*'], 'permission' => 'warehouses.view'],
                ['label' => 'Cajas', 'route' => 'cash-registers.index', 'icon' => 'safe', 'module' => 'cash', 'active' => ['cash-registers.*'], 'permission' => 'cash-registers.view'],
                ['label' => 'Plantillas', 'route' => 'document-templates.index', 'icon' => 'file-earmark-ruled', 'module' => 'admin', 'active' => ['document-templates.*'], 'permission' => 'document-templates.view'],
                ['label' => 'Sucursales', 'route' => 'branches.index', 'icon' => 'diagram-2', 'module' => 'admin', 'active' => ['branches.*'], 'permission' => 'branches.view'],
                ['label' => 'Usuarios', 'route' => 'users.index', 'icon' => 'person-gear', 'module' => 'admin', 'active' => ['users.*'], 'permission' => 'users.view'],
                ['label' => 'Cargos', 'route' => 'cargos.index', 'icon' => 'briefcase', 'module' => 'admin', 'active' => ['cargos.*'], 'permission' => 'cargos.view'],
                ['label' => 'Personal', 'route' => 'personal.index', 'icon' => 'person-vcard', 'module' => 'admin', 'active' => ['personal.*'], 'permission' => 'personal.view'],
            ],
        ],
        [
            'title' => 'Plataforma',
            'items' => [
                ['label' => 'Empresas', 'route' => 'companies.index', 'icon' => 'buildings', 'module' => 'platform', 'active' => ['companies.*'], 'super_admin' => true],
                ['label' => 'Planes', 'route' => 'plans.index', 'icon' => 'stack', 'module' => 'platform', 'active' => ['plans.*'], 'super_admin' => true],
                ['label' => 'Roles', 'route' => 'roles.index', 'icon' => 'shield-lock', 'module' => 'platform', 'active' => ['roles.*'], 'super_admin' => true],
            ],
        ],
        [
            'title' => 'Ayuda',
            'items' => [
                ['label' => 'Manual de usuario', 'route' => 'help.index', 'icon' => 'book', 'module' => 'admin', 'active' => [], 'permission' => null, 'external' => true],
            ],
        ],
    ],

    // Rutas que no están en el menú pero pertenecen a un módulo (para colorear su página).
    'route_modules' => [
        'profile.*' => 'admin',
        'select-company' => 'admin',
        'cash-register.*' => 'cash',
        'exports.*' => 'reports',
    ],
];
