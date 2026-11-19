-- =============================================================
-- Agregar campo receipt_path (comprobante) a tablas de pagos
-- y soporte para Pago QR
-- Fecha: 2026-03-30
-- =============================================================

ALTER TABLE loan_payments
    ADD COLUMN receipt_path VARCHAR(255) NULL COMMENT 'Ruta del comprobante adjunto'
    AFTER reference;

ALTER TABLE loan_amortizations
    ADD COLUMN receipt_path VARCHAR(255) NULL COMMENT 'Ruta del comprobante adjunto'
    AFTER reference;

ALTER TABLE loan_interest_payments
    ADD COLUMN receipt_path VARCHAR(255) NULL COMMENT 'Ruta del comprobante adjunto'
    AFTER reference;
