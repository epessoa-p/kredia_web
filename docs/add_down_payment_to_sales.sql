-- ============================================================
--  SCRIPT: Cuota Inicial en Ventas al Crédito
--  Descripción: Agrega el campo down_payment a la tabla sales
--               para registrar el pago inicial en ventas al crédito.
--  Fecha: 2026-05-07
-- ============================================================

ALTER TABLE `sales`
    ADD COLUMN `down_payment` DECIMAL(12,2) NOT NULL DEFAULT 0
    AFTER `credit_additional_amount`;

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
