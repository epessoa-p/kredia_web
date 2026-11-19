-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces, antes o después de                    ##
-- ##  schema_updates_20261111_products_code.sql, sin dañar datos.           ##
-- ############################################################################
--
-- Correlativo de productos por empresa (código PD-00001).
-- Todos los productos reciben número, incluidos los eliminados y los devueltos: el número nunca se reutiliza.
-- Agrega la columna y el índice solo si no existen, y numera solo los productos sin número
-- (continuando desde el mayor número ya asignado en cada empresa).
-- Si ya se ejecutó schema_updates_20261111_products_code.sql (existe products.code), los pasos 2 y 3 NO hacen
-- nada: ese script deja products.numero solo para los códigos PD- y quita el índice único.
-- Ejecutar ANTES de subir el código (el sistema asigna el número al crear cada producto nuevo).

SET @code_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'code'
);

-- 1) Columna products.numero
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'numero'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE products ADD COLUMN numero INT UNSIGNED NULL AFTER company_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Numerar los productos existentes por empresa, en orden de creación (solo si aún no existe products.code)
SET @sql := IF(@code_exists = 0,
    'UPDATE products p
     JOIN (
         SELECT id,
                ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY COALESCE(created_at, ''1970-01-01''), id) AS rn
         FROM products
         WHERE numero IS NULL
     ) pending ON pending.id = p.id
     JOIN (
         SELECT company_id, COALESCE(MAX(numero), 0) AS max_numero
         FROM products
         GROUP BY company_id
     ) last_number ON last_number.company_id = p.company_id
     SET p.numero = last_number.max_numero + pending.rn',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3) Índice único por empresa (solo si aún no existe products.code)
SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'products_company_numero_unique'
);
SET @sql := IF(@idx_exists = 0 AND @code_exists = 0,
    'ALTER TABLE products ADD UNIQUE KEY products_company_numero_unique (company_id, numero)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
