-- ============================================================
-- Cash Registers (Cajas) Module
-- Created: 2026-03-24
-- ============================================================

-- Cajas: cada caja puede asignarse a un personal
CREATE TABLE IF NOT EXISTS `cash_registers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` VARCHAR(500) NULL,
    `assigned_personal_id` BIGINT UNSIGNED NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    CONSTRAINT `fk_cash_registers_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cash_registers_personal` FOREIGN KEY (`assigned_personal_id`) REFERENCES `personals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sesiones de apertura/cierre de caja
CREATE TABLE IF NOT EXISTS `cash_register_sessions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `cash_register_id` BIGINT UNSIGNED NOT NULL,
    `opened_by` BIGINT UNSIGNED NOT NULL,
    `closed_by` BIGINT UNSIGNED NULL,
    `opening_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `closing_amount` DECIMAL(12,2) NULL,
    `opening_notes` TEXT NULL,
    `closing_notes` TEXT NULL,
    `opened_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `closed_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_cash_sessions_register` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cash_sessions_opened_by` FOREIGN KEY (`opened_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cash_sessions_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
