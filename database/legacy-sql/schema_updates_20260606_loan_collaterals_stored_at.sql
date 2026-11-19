-- ============================================================
-- Agregar columna stored_at y estado 'stored' a loan_collaterals
-- Fecha: 2026-06-06
-- Equivalente a la migración:
--   2026_05_31_160000_add_stored_at_to_loan_collaterals.php
-- Idempotente: seguro de ejecutar aunque la columna ya exista.
-- ============================================================

-- ========================
-- 1. Agregar columna stored_at solo si NO existe
-- ========================
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'loan_collaterals'
      AND COLUMN_NAME  = 'stored_at'
);

SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE `loan_collaterals` ADD COLUMN `stored_at` DATETIME NULL AFTER `retained_at`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- 2. Expandir el ENUM status para incluir 'stored'
-- Flujo: retained -> stored -> sellable -> released / sold
-- ========================
ALTER TABLE `loan_collaterals`
    MODIFY COLUMN `status` ENUM('retained', 'stored', 'sellable', 'released', 'sold') NOT NULL DEFAULT 'retained';
