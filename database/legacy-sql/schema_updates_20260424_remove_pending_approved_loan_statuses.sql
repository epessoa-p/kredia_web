-- =============================================================================
-- Eliminación de estados 'pending' y 'approved' de préstamos
-- Fecha: 2026-04-24
-- Descripción: Se eliminan los estados 'pendiente' y 'aprobado' del flujo de
--              préstamos. Los registros existentes con esos estados se migran
--              al estado 'active'. El ENUM de la tabla loans se actualiza para
--              reflejar solo los estados válidos restantes.
-- =============================================================================

-- Paso 1: Migrar préstamos con estado 'pending' o 'approved' → 'active'
UPDATE loans
SET status = 'active'
WHERE status IN ('pending', 'approved');

-- Paso 2: Alterar el ENUM eliminando 'pending' y 'approved'
ALTER TABLE loans
MODIFY COLUMN status ENUM('active', 'finished', 'cancelled', 'overdue', 'defaulted')
NOT NULL DEFAULT 'active';

-- Verificación (opcional, para confirmar que no quedan registros con estados eliminados)
-- SELECT COUNT(*) AS total_invalidos FROM loans WHERE status IN ('pending', 'approved');
