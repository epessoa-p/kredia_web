-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- ============================================================
-- schema_updates_20261022_catalogs_branch.sql
-- Asigna cada catálogo a una sucursal.
--
--   catalogs.branch_id → sucursal del catálogo (branches.id)
--                        NULL = todas las sucursales
--
-- Con sucursal, el catálogo solo lista productos de esa sucursal y
-- el contacto se completa con los datos de la sucursal.
-- Los catálogos existentes quedan como "Todas las sucursales".
--
-- Script idempotente: puede ejecutarse varias veces sin error.
-- ============================================================

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'catalogs'
      AND COLUMN_NAME = 'branch_id'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `catalogs` ADD COLUMN `branch_id` BIGINT UNSIGNED NULL AFTER `company_id`',
    'SELECT "columna branch_id ya existe" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
