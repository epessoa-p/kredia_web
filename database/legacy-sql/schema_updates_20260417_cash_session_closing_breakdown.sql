-- =============================================================
-- Agregar desglose de cierre por denominaciones en caja
-- Fecha: 2026-04-17
-- =============================================================

ALTER TABLE cash_register_sessions
    ADD COLUMN IF NOT EXISTS closing_breakdown JSON NULL AFTER closing_amount;
