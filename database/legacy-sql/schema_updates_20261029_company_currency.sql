-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- Moneda por empresa: símbolo que se muestra junto a los montos en la web y en la app móvil.
-- Idempotente: solo agrega la columna si no existe. Santa Ana (y las demás) quedan en 'Bs'.

SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'companies' AND COLUMN_NAME = 'currency_symbol'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE companies ADD COLUMN currency_symbol VARCHAR(10) NOT NULL DEFAULT ''Bs'' AFTER interest_next_day',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
