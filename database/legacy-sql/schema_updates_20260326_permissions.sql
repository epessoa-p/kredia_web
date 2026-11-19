-- ============================================================
-- Insertar permisos faltantes (verificando que no existan)
-- Ejecutar sobre la base de datos del sistema de préstamos
-- Fecha: 2026-03-26
-- ============================================================

-- ========================
-- Módulo: branches
-- ========================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Sucursales', 'branches.view', 'branches', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'branches.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Sucursales', 'branches.create', 'branches', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'branches.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Sucursales', 'branches.edit', 'branches', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'branches.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Sucursales', 'branches.delete', 'branches', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'branches.delete');

-- ========================
-- Módulo: products
-- ========================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Productos', 'products.view', 'products', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'products.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Productos', 'products.create', 'products', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'products.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Productos', 'products.edit', 'products', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'products.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Productos', 'products.delete', 'products', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'products.delete');

-- ========================
-- Módulo: credit-categories
-- ========================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Categorías Crédito', 'credit-categories.view', 'credit-categories', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'credit-categories.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Categorías Crédito', 'credit-categories.create', 'credit-categories', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'credit-categories.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Categorías Crédito', 'credit-categories.edit', 'credit-categories', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'credit-categories.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Categorías Crédito', 'credit-categories.delete', 'credit-categories', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'credit-categories.delete');

-- ========================
-- Módulo: warehouses
-- ========================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Almacenes', 'warehouses.view', 'warehouses', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'warehouses.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Almacenes', 'warehouses.create', 'warehouses', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'warehouses.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Almacenes', 'warehouses.edit', 'warehouses', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'warehouses.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Almacenes', 'warehouses.delete', 'warehouses', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'warehouses.delete');

-- ========================
-- Módulo: cargos
-- ========================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Cargos', 'cargos.view', 'cargos', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cargos.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Cargos', 'cargos.create', 'cargos', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cargos.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Cargos', 'cargos.edit', 'cargos', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cargos.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Cargos', 'cargos.delete', 'cargos', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cargos.delete');

-- ========================
-- Módulo: personal
-- ========================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Personal', 'personal.view', 'personal', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'personal.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Personal', 'personal.create', 'personal', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'personal.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Personal', 'personal.edit', 'personal', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'personal.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Personal', 'personal.delete', 'personal', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'personal.delete');

-- ========================
-- Módulo: clients
-- ========================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Clientes', 'clients.view', 'clients', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'clients.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Clientes', 'clients.create', 'clients', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'clients.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Clientes', 'clients.edit', 'clients', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'clients.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Clientes', 'clients.delete', 'clients', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'clients.delete');

-- ========================
-- Módulo: cash-registers (Cajas)
-- ========================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Cajas', 'cash-registers.view', 'cash-registers', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cash-registers.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Cajas', 'cash-registers.create', 'cash-registers', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cash-registers.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Cajas', 'cash-registers.edit', 'cash-registers', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cash-registers.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Cajas', 'cash-registers.delete', 'cash-registers', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cash-registers.delete');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Aperturar/Cerrar Caja', 'cash-registers.operate', 'cash-registers', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cash-registers.operate');

-- ========================
-- Módulo: document-templates
-- ========================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Plantillas', 'document-templates.view', 'document-templates', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'document-templates.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Plantillas', 'document-templates.create', 'document-templates', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'document-templates.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Plantillas', 'document-templates.edit', 'document-templates', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'document-templates.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Plantillas', 'document-templates.delete', 'document-templates', NULL, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'document-templates.delete');

-- ============================================================
-- Asignar TODOS los permisos nuevos al rol Super Admin (id=1)
-- ============================================================
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 1);

-- ============================================================
-- Asignar permisos de administración al rol Admin (id=2)
-- ============================================================
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p
WHERE p.slug IN (
    'branches.view', 'branches.create', 'branches.edit',
    'products.view', 'products.create', 'products.edit',
    'credit-categories.view', 'credit-categories.create', 'credit-categories.edit',
    'warehouses.view', 'warehouses.create', 'warehouses.edit',
    'cargos.view', 'cargos.create', 'cargos.edit',
    'personal.view', 'personal.create', 'personal.edit',
    'clients.view', 'clients.create', 'clients.edit',
    'cash-registers.view', 'cash-registers.create', 'cash-registers.edit', 'cash-registers.delete', 'cash-registers.operate',
    'document-templates.view', 'document-templates.create', 'document-templates.edit'
)
AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 2);

-- ============================================================
-- Asignar permisos operativos al rol Manager (id=3)
-- ============================================================
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 3, p.id FROM `permissions` p
WHERE p.slug IN (
    'branches.view',
    'products.view',
    'credit-categories.view',
    'clients.view', 'clients.create', 'clients.edit',
    'cash-registers.view', 'cash-registers.operate',
    'document-templates.view'
)
AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 3);

-- ============================================================
-- Asignar permisos al rol Cajero (id=4)
-- ============================================================
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 4, p.id FROM `permissions` p
WHERE p.slug IN (
    'clients.view',
    'cash-registers.operate'
)
AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 4);
