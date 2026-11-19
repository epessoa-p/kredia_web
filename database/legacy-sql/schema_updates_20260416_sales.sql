-- =============================================================
-- Módulo de Ventas: tablas + permisos
-- Fecha: 2026-04-16
-- =============================================================

-- ========================
-- Tabla: sales
-- ========================
CREATE TABLE IF NOT EXISTS `sales` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `warehouse_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `credit_category_id` BIGINT UNSIGNED NULL,
    `client_id` BIGINT UNSIGNED NULL COMMENT 'Comprador (opcional)',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `cash_register_id` BIGINT UNSIGNED NULL,
    `cash_register_session_id` BIGINT UNSIGNED NULL,
    `loan_collateral_id` BIGINT UNSIGNED NULL COMMENT 'Si el producto proviene de un préstamo incumplido',
    `purchase_id` BIGINT UNSIGNED NULL COMMENT 'Si el producto proviene de una compra',
    `amount` DECIMAL(15,2) NOT NULL COMMENT 'Precio de venta',
    `cost` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Costo original del producto',
    `profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Ganancia (amount - cost)',
    `notes` TEXT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'completed',
    `sale_date` DATE NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `sales_company_status_idx` (`company_id`, `status`),
    KEY `sales_branch_idx` (`branch_id`),
    KEY `sales_product_idx` (`product_id`),
    KEY `sales_client_idx` (`client_id`),
    KEY `sales_sale_date_idx` (`sale_date`),
    KEY `sales_collateral_idx` (`loan_collateral_id`),
    KEY `sales_purchase_idx` (`purchase_id`),
    CONSTRAINT `fk_sales_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sales_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sales_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sales_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sales_credit_category` FOREIGN KEY (`credit_category_id`) REFERENCES `credit_categories`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sales_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sales_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_sales_cash_register` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sales_cash_session` FOREIGN KEY (`cash_register_session_id`) REFERENCES `cash_register_sessions`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sales_collateral` FOREIGN KEY (`loan_collateral_id`) REFERENCES `loan_collaterals`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sales_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================
-- Tabla: sale_images
-- ========================
CREATE TABLE IF NOT EXISTS `sale_images` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `path` VARCHAR(500) NOT NULL,
    `original_name` VARCHAR(255) NULL,
    `mime_type` VARCHAR(100) NULL,
    `size_bytes` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `sale_images_sale_idx` (`sale_id`),
    CONSTRAINT `fk_sale_images_sale` FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Permisos del módulo Ventas
-- =============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Ventas', 'sales.view', 'sales', 'Permite listar y ver el detalle de ventas.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'sales.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Ventas', 'sales.create', 'sales', 'Permite registrar nuevas ventas y su ingreso en caja.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'sales.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Editar Ventas', 'sales.edit', 'sales', 'Permite modificar datos de ventas.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'sales.edit');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Ventas', 'sales.delete', 'sales', 'Permite eliminar registros de ventas.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'sales.delete');

-- =============================================================
-- Asignar permisos al rol Super Admin (id=1)
-- =============================================================
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.slug IN ('sales.view', 'sales.create', 'sales.edit', 'sales.delete')
AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 1);

-- =============================================================
-- Asignar permisos al rol Admin (id=2)
-- =============================================================
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p
WHERE p.slug IN ('sales.view', 'sales.create', 'sales.edit')
AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 2);

-- =============================================================
-- Asignar permisos al rol Manager (id=3)
-- =============================================================
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 3, p.id FROM `permissions` p
WHERE p.slug IN ('sales.view', 'sales.create')
AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 3);
