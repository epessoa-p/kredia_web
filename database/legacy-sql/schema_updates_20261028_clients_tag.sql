-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- ============================================================
-- schema_updates_20261028_clients_tag.sql
-- Etiqueta manual del cliente (ej. "Cliente fiel", "VIP").
--
--   clients.tag → texto corto que asigna el personal. Se muestra
--                 destacado junto a la categoría automática y se
--                 puede filtrar en la lista de clientes.
--
-- Script idempotente: puede ejecutarse varias veces sin error.
-- ============================================================

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'clients'
      AND COLUMN_NAME = 'tag'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `clients` ADD COLUMN `tag` VARCHAR(40) NULL AFTER `notes`',
    'SELECT "columna tag ya existe" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
