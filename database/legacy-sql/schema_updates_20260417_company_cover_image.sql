-- =============================================================
-- Agregar campo de imagen de portada en empresas
-- Fecha: 2026-04-17
-- =============================================================

ALTER TABLE companies
    ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255) NULL AFTER logo;
