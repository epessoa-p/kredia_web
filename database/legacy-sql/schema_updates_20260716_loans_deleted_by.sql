-- ============================================================
-- Registrar QUIÉN eliminó un préstamo
-- Fecha: 2026-07-16
--
-- Hoy solo se guarda el motivo (en loans.notes como "[Eliminado] ...")
-- y la fecha (deleted_at), pero no el usuario que lo eliminó.
--
-- Idempotente: seguro de ejecutar más de una vez.
-- Los préstamos eliminados ANTES de este cambio quedan con deleted_by NULL
-- (se mostrarán como "usuario no registrado").
-- ============================================================

-- Columna deleted_by (guarda idempotente)
SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'loans'
          AND COLUMN_NAME = 'deleted_by'
    ),
    'SELECT "loans.deleted_by ya existe" AS info',
    'ALTER TABLE `loans` ADD COLUMN `deleted_by` BIGINT UNSIGNED NULL COMMENT ''Usuario que eliminó el préstamo'' AFTER `deleted_at`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Llave foránea (guarda idempotente)
SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'loans'
          AND CONSTRAINT_NAME = 'fk_loans_deleted_by'
    ),
    'SELECT "fk_loans_deleted_by ya existe" AS info',
    'ALTER TABLE `loans` ADD CONSTRAINT `fk_loans_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`) ON DELETE SET NULL'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── Verificación (opcional) ──────────────────────────────────
-- SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loans' AND COLUMN_NAME = 'deleted_by';
