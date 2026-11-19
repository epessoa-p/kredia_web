-- ============================================================
-- Fix: loans.status no permite 'defaulted'
-- Fecha: 2026-04-22
-- Problema:
--   SQLSTATE[01000]: Warning: 1265 Data truncated for column 'status'
-- Causa:
--   El ENUM de loans.status no incluye 'defaulted' en algunas BD.
-- ============================================================

SET @col_type = (
    SELECT COLUMN_TYPE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'loans'
      AND COLUMN_NAME = 'status'
);

-- Si es ENUM, asegurar que incluya defaulted
SET @sql = IF(
    @col_type LIKE 'enum%',
    'ALTER TABLE `loans` MODIFY COLUMN `status` ENUM(''pending'',''approved'',''active'',''overdue'',''defaulted'',''finished'',''cancelled'') NOT NULL DEFAULT ''pending''',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
