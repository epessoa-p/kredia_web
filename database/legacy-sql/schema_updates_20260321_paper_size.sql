-- =============================================================
-- Agregar tamaño de papel a plantillas de documentos
-- Fecha: 2026-03-21
-- grande = Carta (215.9 × 279.4 mm)
-- mediano = Media carta (215.9 × 139.7 mm)
-- pequeno = Recibo térmico 80mm (72mm print width)
-- =============================================================

ALTER TABLE document_templates
    ADD COLUMN paper_size ENUM('grande','mediano','pequeno') NOT NULL DEFAULT 'grande'
    COMMENT 'Tamaño de papel: grande=carta, mediano=media carta, pequeno=recibo 80mm'
    AFTER content;
