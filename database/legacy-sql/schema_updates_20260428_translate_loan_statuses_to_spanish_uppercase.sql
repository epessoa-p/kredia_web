-- =============================================================================
-- Traduccion de estados de prestamos a espanol en mayusculas
-- Fecha: 2026-04-28
-- Descripcion:
--   Migracion en 3 fases para minimizar riesgos:
--   1) Expandir ENUM (ingles + espanol)
--   2) Migrar datos existentes a espanol
--   3) Contraer ENUM (solo espanol)
--
-- Estados finales:
--   ACTIVO, FINALIZADO, CANCELADO, EN_MORA, INCUMPLIDO
-- =============================================================================

-- FASE 1: Expandir ENUM para aceptar ambos idiomas temporalmente
ALTER TABLE loans
MODIFY COLUMN status ENUM(
    'pending', 'approved', 'active', 'finished', 'cancelled', 'overdue', 'defaulted',
    'ACTIVO', 'FINALIZADO', 'CANCELADO', 'EN_MORA', 'INCUMPLIDO'
)
NOT NULL DEFAULT 'ACTIVO';

-- FASE 2: Migrar registros legacy a estados finales en espanol
UPDATE loans
SET status = CASE status
    WHEN 'pending' THEN 'ACTIVO'
    WHEN 'approved' THEN 'ACTIVO'
    WHEN 'active' THEN 'ACTIVO'
    WHEN 'finished' THEN 'FINALIZADO'
    WHEN 'cancelled' THEN 'CANCELADO'
    WHEN 'overdue' THEN 'EN_MORA'
    WHEN 'defaulted' THEN 'INCUMPLIDO'
    ELSE status
END
WHERE status IN ('pending', 'approved', 'active', 'finished', 'cancelled', 'overdue', 'defaulted');

-- FASE 3: Dejar solo estados finales en espanol
ALTER TABLE loans
MODIFY COLUMN status ENUM('ACTIVO', 'FINALIZADO', 'CANCELADO', 'EN_MORA', 'INCUMPLIDO')
NOT NULL DEFAULT 'ACTIVO';

-- Verificaciones opcionales
-- SELECT status, COUNT(*) FROM loans GROUP BY status ORDER BY status;
-- SELECT COUNT(*) AS legacy_restantes FROM loans
-- WHERE status IN ('pending', 'approved', 'active', 'finished', 'cancelled', 'overdue', 'defaulted');
