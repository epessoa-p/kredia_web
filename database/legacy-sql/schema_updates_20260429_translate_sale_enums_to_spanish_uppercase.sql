-- =============================================================================
-- Migración: Traducción de ENUM de ventas a español en mayúsculas
-- Fecha: 2026-04-29
-- Descripción:
--   Cambia los valores ENUM de:
--     sales.payment_type: 'cash' → 'CONTADO', 'credit' → 'CREDITO'
--     sale_installments.status: 'pending' → 'PENDIENTE', 'paid' → 'PAGADA',
--                               'overdue' → 'VENCIDA'
--
-- Se ejecuta en 3 fases para evitar errores de integridad en producción:
--   Fase 1: Ampliar el ENUM para aceptar tanto valores antiguos como nuevos
--   Fase 2: Actualizar los datos existentes
--   Fase 3: Contraer el ENUM a sólo los nuevos valores en español
-- =============================================================================

-- ─── FASE 1: Ampliar los ENUMs (acepta ambos conjuntos de valores) ────────────

ALTER TABLE sales
    MODIFY payment_type ENUM('cash', 'credit', 'CONTADO', 'CREDITO') NOT NULL DEFAULT 'CONTADO';

ALTER TABLE sale_installments
    MODIFY status ENUM('pending', 'paid', 'overdue', 'PENDIENTE', 'PAGADA', 'VENCIDA') NOT NULL DEFAULT 'PENDIENTE';

-- ─── FASE 2: Actualizar datos existentes ──────────────────────────────────────

UPDATE sales SET payment_type = 'CONTADO' WHERE payment_type = 'cash';
UPDATE sales SET payment_type = 'CREDITO' WHERE payment_type = 'credit';

UPDATE sale_installments SET status = 'PENDIENTE' WHERE status = 'pending';
UPDATE sale_installments SET status = 'PAGADA'    WHERE status = 'paid';
UPDATE sale_installments SET status = 'VENCIDA'   WHERE status = 'overdue';

-- ─── FASE 3: Contraer los ENUMs a sólo los valores nuevos en español ──────────

ALTER TABLE sales
    MODIFY payment_type ENUM('CONTADO', 'CREDITO') NOT NULL DEFAULT 'CONTADO';

ALTER TABLE sale_installments
    MODIFY status ENUM('PENDIENTE', 'PAGADA', 'VENCIDA') NOT NULL DEFAULT 'PENDIENTE';

-- ─── Verificación (opcional, ejecutar manualmente) ────────────────────────────
-- SELECT DISTINCT payment_type FROM sales;
-- SELECT DISTINCT status FROM sale_installments;
