-- =============================================================
-- Nuevos permisos: modulo Compras
-- Fecha: 2026-04-16
-- =============================================================

-- Permiso: ver compras
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT
    'Ver Compras',
    'purchases.view',
    'purchases',
    'Permite listar y ver el detalle de compras.',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'purchases.view'
);

-- Permiso: crear compras
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT
    'Crear Compras',
    'purchases.create',
    'purchases',
    'Permite registrar nuevas compras y su egreso en caja.',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'purchases.create'
);

-- Permiso: editar compras (reservado para futuras mejoras)
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT
    'Editar Compras',
    'purchases.edit',
    'purchases',
    'Permite modificar datos de compras.',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'purchases.edit'
);

-- Permiso: eliminar compras
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT
    'Eliminar Compras',
    'purchases.delete',
    'purchases',
    'Permite eliminar registros de compras.',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'purchases.delete'
);
