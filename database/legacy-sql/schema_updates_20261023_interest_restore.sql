-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- ============================================================
-- schema_updates_20261023_interest_restore.sql
-- Restaurar un periodo de interés eliminado.
--
-- 1) Permiso `loans.interest.restore` ("Restaurar interés eliminado"),
--    asignado a Super Admin (1), Administrador de Empresa (2) y al rol
--    "Gerente" (por nombre).
-- 2) Columnas para registrar quién y cuándo restauró:
--      loan_interest_payments.restored_by  (users.id)
--      loan_interest_payments.restored_at
--
-- Script idempotente: puede ejecutarse varias veces sin error.
-- ============================================================

-- 1) Permiso
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Restaurar interés eliminado', 'loans.interest.restore', 'loans', 'Restaurar un periodo de interés eliminado de un préstamo', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'loans.interest.restore');

INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permissions` p ON p.slug = 'loans.interest.restore'
WHERE (r.id IN (1, 2) OR r.name = 'Gerente')
  AND NOT EXISTS (
      SELECT 1 FROM `role_permission` rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );

-- 2) Columna restored_by
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'loan_interest_payments'
      AND COLUMN_NAME = 'restored_by'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `loan_interest_payments` ADD COLUMN `restored_by` BIGINT UNSIGNED NULL AFTER `deletion_reason`',
    'SELECT "columna restored_by ya existe" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Columna restored_at
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'loan_interest_payments'
      AND COLUMN_NAME = 'restored_at'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `loan_interest_payments` ADD COLUMN `restored_at` TIMESTAMP NULL AFTER `restored_by`',
    'SELECT "columna restored_at ya existe" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
