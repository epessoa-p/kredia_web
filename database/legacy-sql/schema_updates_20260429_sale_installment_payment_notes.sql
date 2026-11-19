-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: NO                                              ##
-- ##  Ejecutar UNA sola vez y solo si la columna                           ##
-- ##  sale_installments.payment_notes NO existe (si existe, da error).     ##
-- ############################################################################
--
-- =============================================================================
-- Migración: Columna payment_notes en sale_installments
-- Fecha: 2026-04-29
-- Descripción:
--   Agrega la columna `payment_notes` a `sale_installments` para guardar
--   observaciones al momento de registrar el pago de una cuota.
--   El registro contable del pago queda en `cash_movements`
--   (reference_type = 'App\Models\SaleInstallment', reference_id = installment_id).
-- =============================================================================

ALTER TABLE sale_installments
    ADD COLUMN payment_notes TEXT NULL AFTER notes;
