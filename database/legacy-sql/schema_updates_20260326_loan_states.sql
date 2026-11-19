-- ============================================================
-- Ajustes estados de préstamo y producto en almacén
-- Fecha: 2026-03-26
-- ============================================================

-- ========================
-- 1. Agregar estado 'stored' (almacenado/retención) al ENUM de loan_collaterals
-- Flujo: retained → stored → sellable → released / sold
-- ========================
ALTER TABLE `loan_collaterals`
    MODIFY COLUMN `status` ENUM('retained', 'stored', 'sellable', 'released', 'sold') NOT NULL DEFAULT 'retained';

-- ========================
-- 2. Agregar columna stored_at para registrar cuándo se almacenó
-- ========================
ALTER TABLE `loan_collaterals`
    ADD COLUMN `stored_at` DATETIME NULL AFTER `retained_at`;

-- ========================
-- 3. Si loans.status es ENUM, expandirlo (si es VARCHAR no afecta)
-- Flujo: pending → approved → active → overdue → defaulted → finished / cancelled
-- ========================
-- Verificar tipo actual y alterar si es ENUM
SET @col_type = (
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'loans'
      AND COLUMN_NAME = 'status'
);

SET @sql = IF(
    @col_type LIKE 'enum%',
    'ALTER TABLE `loans` MODIFY COLUMN `status` ENUM(''pending'',''approved'',''active'',''overdue'',''defaulted'',''finished'',''cancelled'') NOT NULL DEFAULT ''pending''',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
