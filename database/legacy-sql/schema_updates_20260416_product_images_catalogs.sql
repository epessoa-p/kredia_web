-- =============================================================
-- Imágenes de ingresos (movimientos) + Módulo de Catálogos
-- Fecha: 2026-04-16
-- =============================================================

-- ========================
-- Tabla: movement_images
-- Cada ingreso de stock es una unidad única (producto usado),
-- las fotos se relacionan al movimiento, no al producto genérico.
-- ========================
CREATE TABLE IF NOT EXISTS `movement_images` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inventory_movement_id` BIGINT UNSIGNED NOT NULL,
    `path` VARCHAR(500) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(100) NULL,
    `size_bytes` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `movement_images_movement_idx` (`inventory_movement_id`),
    CONSTRAINT `fk_movement_images_movement` FOREIGN KEY (`inventory_movement_id`) REFERENCES `inventory_movements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================
-- Tabla: catalogs
-- ========================
CREATE TABLE IF NOT EXISTS `catalogs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `slug` VARCHAR(100) NOT NULL COMMENT 'Token unico para URL publica',
    `cover_color` VARCHAR(20) NULL DEFAULT '#0d6efd',
    `logo_path` VARCHAR(500) NULL,
    `show_prices` TINYINT(1) NOT NULL DEFAULT 1,
    `show_contact` TINYINT(1) NOT NULL DEFAULT 1,
    `contact_phone` VARCHAR(30) NULL,
    `contact_whatsapp` VARCHAR(30) NULL,
    `contact_email` VARCHAR(255) NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `expires_at` DATETIME NULL COMMENT 'Fecha de expiracion del link publico',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `catalogs_slug_unique` (`slug`),
    KEY `catalogs_company_idx` (`company_id`),
    KEY `catalogs_active_expires_idx` (`active`, `expires_at`),
    CONSTRAINT `fk_catalogs_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_catalogs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================
-- Tabla: catalog_items (productos del catálogo)
-- ========================
CREATE TABLE IF NOT EXISTS `catalog_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `catalog_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `custom_name` VARCHAR(255) NULL COMMENT 'Nombre personalizado para mostrar',
    `custom_description` TEXT NULL,
    `custom_price` DECIMAL(15,2) NULL COMMENT 'Precio personalizado para el catalogo',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `catalog_items_catalog_idx` (`catalog_id`),
    KEY `catalog_items_product_idx` (`product_id`),
    CONSTRAINT `fk_catalog_items_catalog` FOREIGN KEY (`catalog_id`) REFERENCES `catalogs`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_catalog_items_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Permisos del módulo Catálogos
-- =============================================================
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Catálogos', 'catalogs.view', 'catalogs', 'Permite listar y ver catálogos.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'catalogs.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Crear Catálogos', 'catalogs.create', 'catalogs', 'Permite crear y editar catálogos.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'catalogs.create');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Eliminar Catálogos', 'catalogs.delete', 'catalogs', 'Permite eliminar catálogos.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'catalogs.delete');

-- Asignar permisos → Super Admin (id=1)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.slug IN ('catalogs.view', 'catalogs.create', 'catalogs.delete')
AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 1);

-- Asignar permisos → Admin (id=2)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p
WHERE p.slug IN ('catalogs.view', 'catalogs.create')
AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 2);

-- Asignar permisos → Manager (id=3)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 3, p.id FROM `permissions` p
WHERE p.slug IN ('catalogs.view')
AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 3);
