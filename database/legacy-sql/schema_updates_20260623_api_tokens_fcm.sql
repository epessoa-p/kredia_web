-- ============================================================
-- Agregar fcm_token y platform a api_tokens (notificaciones push)
-- Fecha: 2026-06-23
-- Equivalente a la migración:
--   2026_06_23_000001_add_fcm_token_to_api_tokens_table.php
-- Idempotente: seguro de ejecutar aunque las columnas/índice ya existan.
-- ============================================================

-- ========================
-- 1. Columna fcm_token (solo si NO existe)
-- ========================
SET @col_fcm = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'api_tokens'
      AND COLUMN_NAME  = 'fcm_token'
);
SET @sql = IF(
    @col_fcm = 0,
    'ALTER TABLE `api_tokens` ADD COLUMN `fcm_token` VARCHAR(255) NULL AFTER `name`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- 2. Columna platform (solo si NO existe)
-- ========================
SET @col_platform = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'api_tokens'
      AND COLUMN_NAME  = 'platform'
);
SET @sql = IF(
    @col_platform = 0,
    'ALTER TABLE `api_tokens` ADD COLUMN `platform` VARCHAR(20) NULL AFTER `fcm_token`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ========================
-- 3. Índice sobre fcm_token (solo si NO existe)
-- ========================
SET @idx_fcm = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'api_tokens'
      AND INDEX_NAME   = 'api_tokens_fcm_token_index'
);
SET @sql = IF(
    @idx_fcm = 0,
    'ALTER TABLE `api_tokens` ADD INDEX `api_tokens_fcm_token_index` (`fcm_token`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
