-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- ============================================================
-- schema_updates_20261019_interest_payments_deleted_by.sql
-- Registra QUIÉN eliminó un periodo de interés y POR QUÉ.
--
--   deleted_by       → usuario que eliminó (users.id)
--   deletion_reason  → 'manual'       : eliminado con el botón en la vista del préstamo
--                      'loan_edited'  : eliminado al editar el préstamo (se regeneran)
--                      'loan_deleted' : eliminado junto con el préstamo
--
-- Incluye una recuperación del historial: los intereses que se
-- eliminaron junto con su préstamo toman el motivo 'loan_deleted'
-- y, si el préstamo lo tiene, el usuario que lo eliminó.
--
-- Script idempotente: puede ejecutarse varias veces sin error.
-- ============================================================

-- 1) Columna deleted_by
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'loan_interest_payments'
      AND COLUMN_NAME = 'deleted_by'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `loan_interest_payments` ADD COLUMN `deleted_by` BIGINT UNSIGNED NULL AFTER `deleted_at`',
    'SELECT "columna deleted_by ya existe" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Columna deletion_reason
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'loan_interest_payments'
      AND COLUMN_NAME = 'deletion_reason'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `loan_interest_payments` ADD COLUMN `deletion_reason` VARCHAR(30) NULL AFTER `deleted_by`',
    'SELECT "columna deletion_reason ya existe" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Recuperar historial: intereses eliminados junto con su préstamo
--    (se eliminaron en el mismo instante que el préstamo).
UPDATE `loan_interest_payments` ip
JOIN `loans` l ON l.id = ip.loan_id
SET ip.deletion_reason = 'loan_deleted',
    ip.deleted_by = COALESCE(ip.deleted_by, l.deleted_by)
WHERE ip.deleted_at IS NOT NULL
  AND l.deleted_at IS NOT NULL
  AND ip.deletion_reason IS NULL
  AND ABS(TIMESTAMPDIFF(SECOND, ip.deleted_at, l.deleted_at)) <= 5;
