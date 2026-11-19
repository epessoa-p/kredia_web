-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- Código del producto según su origen (reemplaza el uso del correlativo PD- para todos):
--   * producto creado por un préstamo  -> el código del préstamo (PR-00123)
--   * producto creado por una compra   -> el código de la compra (CO-00007)
--   * el resto (ingresos de inventario, alta manual y productos de stock que comparten varios préstamos,
--     como las garrafas) -> PD-00001, PD-00002... (correlativo por empresa; products.numero guarda ese número)
-- El código no se repite dentro de la misma sucursal (products.branch_id = sucursal donde entró el producto):
-- PR- y CO- son correlativos por sucursal, así que el mismo PR- puede existir en dos sucursales.
-- Idempotente: agrega columnas e índices solo si no existen y completa solo los productos sin código.
-- Ejecutar DESPUÉS de schema_updates_20261107_products_numero.sql y ANTES de subir el código.

-- 1) Columnas products.code y products.branch_id
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'code'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE products ADD COLUMN code VARCHAR(30) NULL AFTER numero', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'branch_id'
);
SET @sql := IF(@col_exists = 0, 'ALTER TABLE products ADD COLUMN branch_id BIGINT UNSIGNED NULL AFTER company_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) products.numero ya no es único (solo guarda el número del PD-)
SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'products_company_numero_unique'
);
SET @sql := IF(@idx_exists > 0, 'ALTER TABLE products DROP INDEX products_company_numero_unique', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3a) Productos de un solo préstamo (y sin compra): el código del préstamo
UPDATE products p
JOIN (
    SELECT product_id, MIN(id) AS loan_id
    FROM loans
    WHERE product_id IS NOT NULL
    GROUP BY product_id
    HAVING COUNT(*) = 1
) one_loan ON one_loan.product_id = p.id
JOIN loans l ON l.id = one_loan.loan_id
SET p.code = CONCAT('PR-', LPAD(l.numero, 5, '0')),
    p.branch_id = l.branch_id,
    p.numero = NULL
WHERE p.code IS NULL
  AND NOT EXISTS (SELECT 1 FROM purchases pu WHERE pu.product_id = p.id);

-- 3b) Productos de una sola compra (y sin préstamo): el código de la compra
UPDATE products p
JOIN (
    SELECT product_id, MIN(id) AS purchase_id
    FROM purchases
    WHERE product_id IS NOT NULL
    GROUP BY product_id
    HAVING COUNT(*) = 1
) one_purchase ON one_purchase.product_id = p.id
JOIN purchases pu ON pu.id = one_purchase.purchase_id
SET p.code = CONCAT('CO-', LPAD(pu.numero, 5, '0')),
    p.branch_id = pu.branch_id,
    p.numero = NULL
WHERE p.code IS NULL
  AND NOT EXISTS (SELECT 1 FROM loans l WHERE l.product_id = p.id);

-- 3c) El resto: sucursal de su primera entrada de inventario
UPDATE products p
JOIN (
    SELECT m.product_id, m.branch_id
    FROM inventory_movements m
    JOIN (
        SELECT product_id, MIN(id) AS first_id
        FROM inventory_movements
        WHERE type = 'in' AND branch_id IS NOT NULL
        GROUP BY product_id
    ) f ON f.first_id = m.id
) first_in ON first_in.product_id = p.id
SET p.branch_id = first_in.branch_id
WHERE p.code IS NULL AND p.branch_id IS NULL;

-- 3d) El resto: PD- correlativo por empresa en orden de creación, continuando desde el mayor PD- ya asignado
UPDATE products SET numero = NULL WHERE code IS NULL;

UPDATE products p
JOIN (
    SELECT id,
           ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY COALESCE(created_at, '1970-01-01'), id) AS rn
    FROM products
    WHERE code IS NULL
) pending ON pending.id = p.id
JOIN (
    SELECT company_id, COALESCE(MAX(numero), 0) AS max_numero
    FROM products
    GROUP BY company_id
) last_number ON last_number.company_id = p.company_id
SET p.numero = last_number.max_numero + pending.rn,
    p.code = CONCAT('PD-', LPAD(last_number.max_numero + pending.rn, 5, '0'));

-- 4) El código no se repite dentro de la misma sucursal
SET @idx_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND INDEX_NAME = 'products_company_branch_code_unique'
);
SET @sql := IF(@idx_exists = 0,
    'ALTER TABLE products ADD UNIQUE KEY products_company_branch_code_unique (company_id, branch_id, code)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
