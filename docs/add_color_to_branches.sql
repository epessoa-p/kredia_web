-- ============================================================
--  Agregar color a la tabla branches
-- ============================================================

ALTER TABLE `branches`
    ADD COLUMN `color` VARCHAR(7) NOT NULL DEFAULT '#0d6efd'
    AFTER `active`;

-- Verificar
SELECT id, name, active, color FROM branches ORDER BY id;
