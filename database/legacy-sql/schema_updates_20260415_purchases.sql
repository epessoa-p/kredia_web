-- ============================================================
-- Módulo de Compras (Purchases)
-- Fecha: 2026-04-15
-- ============================================================

-- Tabla principal de compras
CREATE TABLE IF NOT EXISTS `purchases` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NULL,
    `product_id` BIGINT UNSIGNED NULL,
    `credit_category_id` BIGINT UNSIGNED NULL,
    `client_id` BIGINT UNSIGNED NULL,
    `created_by` BIGINT UNSIGNED NULL,
    `cash_register_id` BIGINT UNSIGNED NULL,
    `cash_register_session_id` BIGINT UNSIGNED NULL,
    `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `notes` TEXT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'completed',
    `purchase_date` DATE NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_purchases_company` (`company_id`),
    INDEX `idx_purchases_branch` (`branch_id`),
    INDEX `idx_purchases_client` (`client_id`),
    INDEX `idx_purchases_product` (`product_id`),
    INDEX `idx_purchases_status` (`status`),
    INDEX `idx_purchases_date` (`purchase_date`),
    CONSTRAINT `fk_purchases_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_purchases_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_purchases_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_purchases_credit_category` FOREIGN KEY (`credit_category_id`) REFERENCES `credit_categories`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_purchases_client` FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_purchases_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_purchases_cash_register` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_purchases_cash_session` FOREIGN KEY (`cash_register_session_id`) REFERENCES `cash_register_sessions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Imágenes de compras
CREATE TABLE IF NOT EXISTS `purchase_images` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_id` BIGINT UNSIGNED NOT NULL,
    `path` VARCHAR(500) NOT NULL,
    `original_name` VARCHAR(255) NULL,
    `mime_type` VARCHAR(100) NULL,
    `size_bytes` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_purchase_images_purchase` (`purchase_id`),
    CONSTRAINT `fk_purchase_images_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
