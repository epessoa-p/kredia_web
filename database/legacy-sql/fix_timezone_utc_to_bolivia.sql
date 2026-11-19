-- ============================================================
-- Corrección de zona horaria: UTC -> America/La_Paz (Bolivia, UTC-4)
-- Fecha: 2026-07
--
-- Resta 4 horas a las columnas datetime/timestamp de SOLO las tablas
-- esenciales, para que los registros guardados previamente en UTC queden
-- en hora de Bolivia.
--
-- Tablas afectadas:
--   * loans                   (préstamos)
--   * sales                   (ventas)
--   * purchases               (compras)
--   * cash_register_sessions  (apertura y cierre de caja)
--   * cash_movements          (ingresos / egresos de caja)
--
-- Recorre dinámicamente las columnas de esas tablas vía INFORMATION_SCHEMA,
-- así que cubre created_at, updated_at, deleted_at, opened_at, closed_at,
-- approved_at, returned_at, movement_date, etc. (lo que exista en ellas).
--
-- NO toca columnas de tipo DATE (start_date, end_date, sale_date,
-- purchase_date, etc.): esas guardan un día calendario, no una hora.
--
-- ⚠️ IMPORTANTE:
--   1) Hacer BACKUP de la base de datos ANTES de ejecutar.
--   2) Ejecutar UNA SOLA VEZ. Correrlo dos veces restaría 8 horas.
--   3) Ejecutar sobre la base de datos correcta (USE tu_base;).
-- ============================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS shift_datetimes_to_bolivia$$

CREATE PROCEDURE shift_datetimes_to_bolivia()
BEGIN
    DECLARE done INT DEFAULT 0;
    DECLARE v_table  VARCHAR(255);
    DECLARE v_column VARCHAR(255);

    DECLARE cur CURSOR FOR
        SELECT TABLE_NAME, COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND DATA_TYPE IN ('datetime', 'timestamp')
          AND TABLE_NAME IN (
              'loans',
              'sales',
              'purchases',
              'cash_register_sessions',
              'cash_movements'
          );

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

    OPEN cur;

    read_loop: LOOP
        FETCH cur INTO v_table, v_column;
        IF done = 1 THEN
            LEAVE read_loop;
        END IF;

        SET @s = CONCAT(
            'UPDATE `', v_table, '` ',
            'SET `', v_column, '` = `', v_column, '` - INTERVAL 4 HOUR ',
            'WHERE `', v_column, '` IS NOT NULL'
        );

        PREPARE stmt FROM @s;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;

    CLOSE cur;
END$$

DELIMITER ;

CALL shift_datetimes_to_bolivia();

DROP PROCEDURE shift_datetimes_to_bolivia;
