-- ============================================================
--  SCRIPT: Desglose Principal/Interés en Cuotas de Venta
--  Descripción: Agrega principal_amount e interest_amount a
--               sale_installments para separar la porción de
--               precio base del recargo de crédito por cuota.
--  Fecha: 2026-05-11
-- ============================================================

ALTER TABLE `sale_installments`
    ADD COLUMN `principal_amount` DECIMAL(15,2) NOT NULL DEFAULT 0
        AFTER `amount`,
    ADD COLUMN `interest_amount`  DECIMAL(15,2) NOT NULL DEFAULT 0
        AFTER `principal_amount`;

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
