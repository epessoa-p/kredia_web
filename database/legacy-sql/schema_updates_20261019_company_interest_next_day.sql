-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- ============================================================
-- schema_updates_20261019_company_interest_next_day.sql
-- Agrega la bandera `interest_next_day` a la tabla `companies`.
--
-- Cuando está activa (1), al generar los intereses de un préstamo
-- el SEGUNDO interés y los siguientes recién se generan al DÍA
-- SIGUIENTE de su fecha de inicio (aniversario). Así, en la fecha
-- exacta del aniversario el cliente aún debe un solo interés.
-- 0 = comportamiento actual (el interés aparece el mismo día).
--
-- Script idempotente: puede ejecutarse varias veces sin error.
-- ============================================================

-- 1) Columna interest_next_day en companies
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'companies'
      AND COLUMN_NAME = 'interest_next_day'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `companies` ADD COLUMN `interest_next_day` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`',
    'SELECT "columna interest_next_day ya existe" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) (OPCIONAL) Activar la regla para una empresa concreta.
--    Descomenta y ajusta el id si quieres dejarla encendida de entrada.
-- UPDATE `companies` SET `interest_next_day` = 1 WHERE `id` = 1;
