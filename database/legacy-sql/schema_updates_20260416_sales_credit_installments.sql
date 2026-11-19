-- =============================================================
-- Ventas a credito: tipo de pago + plan de cuotas
-- Fecha: 2026-04-16
-- =============================================================

-- 1) Agregar tipo de pago en ventas
ALTER TABLE `sales`
    ADD COLUMN `payment_type` ENUM('cash','credit') NOT NULL DEFAULT 'cash' AFTER `amount`;

-- 2) Tabla de cuotas de venta
CREATE TABLE IF NOT EXISTS `sale_installments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sale_id` BIGINT UNSIGNED NOT NULL,
    `installment_no` INT UNSIGNED NOT NULL COMMENT 'Numero de cuota (1..n)',
    `due_date` DATE NOT NULL,
    `percentage` DECIMAL(7,4) NOT NULL COMMENT 'Porcentaje de la cuota sobre el total',
    `amount` DECIMAL(15,2) NOT NULL COMMENT 'Monto de la cuota',
    `status` ENUM('pending','paid','overdue') NOT NULL DEFAULT 'pending',
    `paid_at` DATETIME NULL,
    `notes` VARCHAR(255) NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `sale_installments_sale_idx` (`sale_id`),
    KEY `sale_installments_status_idx` (`status`),
    KEY `sale_installments_due_date_idx` (`due_date`),
    CONSTRAINT `fk_sale_installments_sale`
        FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
