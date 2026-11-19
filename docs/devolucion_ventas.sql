-- ============================================================
--  SCRIPT: Devolución de Ventas
--  Descripción: Cambios de estructura necesarios para soportar
--               la funcionalidad de devolución/reversión de ventas.
--  Fecha: 2026-05-07
-- ============================================================

-- ------------------------------------------------------------
-- 1. Nuevos campos de auditoría en la tabla `sales`
-- ------------------------------------------------------------
ALTER TABLE `sales`
    ADD COLUMN `returned_at`   TIMESTAMP    NULL DEFAULT NULL   AFTER `sale_date`,
    ADD COLUMN `returned_by`   BIGINT UNSIGNED NULL DEFAULT NULL AFTER `returned_at`,
    ADD COLUMN `return_reason` VARCHAR(255) NULL DEFAULT NULL   AFTER `returned_by`,
    ADD COLUMN `return_notes`  TEXT         NULL DEFAULT NULL   AFTER `return_reason`;

-- Clave foránea: returned_by → users.id (NULL si el usuario es eliminado)
ALTER TABLE `sales`
    ADD CONSTRAINT `fk_sales_returned_by`
    FOREIGN KEY (`returned_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

-- ------------------------------------------------------------
-- 2. Ampliar ENUM de sale_installments para incluir 'ANULADA'
-- ------------------------------------------------------------
ALTER TABLE `sale_installments`
    MODIFY COLUMN `status`
    ENUM('PENDIENTE','PAGADA','VENCIDA','ANULADA')
    NOT NULL DEFAULT 'PENDIENTE';

-- ------------------------------------------------------------
-- 3. (Opcional) Índice para consultas frecuentes por estado
-- ------------------------------------------------------------
-- Acelera filtros de ventas devueltas en listados y reportes
CREATE INDEX `idx_sales_status`      ON `sales` (`status`);
CREATE INDEX `idx_sales_returned_by` ON `sales` (`returned_by`);

-- ------------------------------------------------------------
-- 4. Verificación post-migración
-- ------------------------------------------------------------
-- Confirmar que los campos fueron añadidos correctamente:
-- SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
-- FROM information_schema.COLUMNS
-- WHERE TABLE_NAME = 'sales'
--   AND COLUMN_NAME IN ('returned_at','returned_by','return_reason','return_notes');

-- Confirmar el nuevo ENUM en sale_installments:
-- SELECT COLUMN_TYPE FROM information_schema.COLUMNS
-- WHERE TABLE_NAME = 'sale_installments' AND COLUMN_NAME = 'status';

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
