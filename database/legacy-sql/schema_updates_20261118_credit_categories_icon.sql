-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos            ##
-- ##  (solo completa las categorías que aún no tienen ícono).               ##
-- ############################################################################
--
-- Ícono de las categorías de crédito (se elige al crear o editar la categoría y se muestra, por ejemplo,
-- en las tarjetas de Inventario › Ingresar producto). Guarda la clase de Bootstrap Icons, ej. 'bi-phone'.
-- Las categorías existentes reciben el ícono que ya se mostraba (según su número o su nombre).

-- 1) Columna credit_categories.icon
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'credit_categories' AND COLUMN_NAME = 'icon'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE credit_categories ADD COLUMN icon VARCHAR(40) NULL AFTER color',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Ícono para las categorías que no tienen (la misma regla que se usaba al mostrarlas)
UPDATE credit_categories
SET icon = CASE
    WHEN id = 1 THEN 'bi-truck'
    WHEN id = 2 THEN 'bi-gem'
    WHEN id = 3 THEN 'bi-fire'
    WHEN UPPER(name) LIKE '%CELULAR%' OR UPPER(name) LIKE '%TELEF%' THEN 'bi-phone'
    WHEN UPPER(name) LIKE '%COMPU%' OR UPPER(name) LIKE '%LAPTOP%' THEN 'bi-laptop'
    WHEN UPPER(name) LIKE '%HELADER%' OR UPPER(name) LIKE '%REFRI%' THEN 'bi-snow'
    WHEN UPPER(name) LIKE '%ELECTRO%' THEN 'bi-plug'
    WHEN UPPER(name) LIKE '%HERRAM%' THEN 'bi-tools'
    WHEN UPPER(name) LIKE '%TELEV%' OR UPPER(name) LIKE 'TV%' OR UPPER(name) LIKE '% TV%' THEN 'bi-tv'
    WHEN UPPER(name) LIKE '%ROPA%' THEN 'bi-bag'
    ELSE 'bi-box-seam'
END
WHERE icon IS NULL OR icon = '';
