-- ============================================================
-- Cash Movements: Registro unificado de flujo de caja
-- Fecha: 2026-04-14
-- ============================================================

-- 1. Crear tabla cash_movements
CREATE TABLE IF NOT EXISTS `cash_movements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `cash_register_id` BIGINT UNSIGNED NOT NULL,
    `cash_register_session_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL COMMENT 'income = ingreso, expense = egreso',
    `category` VARCHAR(50) NOT NULL COMMENT 'loan_disbursement, loan_payment, interest_payment, amortization, liquidation, other',
    `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    `reference_type` VARCHAR(100) NULL COMMENT 'Modelo polimórfico: App\\Models\\Loan, App\\Models\\LoanPayment',
    `reference_id` BIGINT UNSIGNED NULL COMMENT 'ID del modelo referenciado',
    `description` VARCHAR(500) NULL,
    `movement_date` DATETIME NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cash_movements_company` (`company_id`),
    INDEX `idx_cash_movements_session` (`cash_register_session_id`),
    INDEX `idx_cash_movements_type` (`type`),
    INDEX `idx_cash_movements_category` (`category`),
    INDEX `idx_cash_movements_date` (`movement_date`),
    INDEX `idx_cash_movements_reference` (`reference_type`, `reference_id`),
    CONSTRAINT `fk_cash_movements_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cash_movements_register` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cash_movements_session` FOREIGN KEY (`cash_register_session_id`) REFERENCES `cash_register_sessions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cash_movements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Agregar columnas de caja de desembolso a loans
ALTER TABLE `loans`
    ADD COLUMN `cash_register_id` BIGINT UNSIGNED NULL AFTER `branch_id`,
    ADD COLUMN `cash_register_session_id` BIGINT UNSIGNED NULL AFTER `cash_register_id`;

ALTER TABLE `loans`
    ADD CONSTRAINT `fk_loans_cash_register` FOREIGN KEY (`cash_register_id`) REFERENCES `cash_registers` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_loans_cash_session` FOREIGN KEY (`cash_register_session_id`) REFERENCES `cash_register_sessions` (`id`) ON DELETE SET NULL;
