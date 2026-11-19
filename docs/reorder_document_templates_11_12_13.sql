-- ============================================================
--  Reordenar plantillas de impresión de compras en producción
--  moviendo TODOS los datos de columna (sin tocar los IDs).
--
--  Estado ACTUAL en producción:
--    ID 11 → Comprobante Compra - Pequeño
--    ID 12 → Comprobante Compra - Media Carta
--    ID 13 → Contrato Resumen - Pequeño
--
--  Estado DESEADO (igual que local):
--    ID 11 → Contrato Resumen - Pequeño     (datos actuales de ID 13)
--    ID 12 → Comprobante Compra - Pequeño   (datos actuales de ID 11)
--    ID 13 → Comprobante Compra - Media Carta (datos actuales de ID 12)
--
--  Estrategia: tabla temporal con el mapeo correcto → UPDATE en una sola
--  operación JOIN, sin tocar los IDs para no romper claves foráneas.
-- ============================================================

-- 1. Verificar estado actual antes de aplicar
SELECT id, name, type, paper_size
FROM document_templates
WHERE id IN (11, 12, 13)
ORDER BY id;

-- 2. Guardar una copia con el mapeo destino en tabla temporal
CREATE TEMPORARY TABLE _dt_reorder AS
    -- ID 11 recibirá los datos que hoy están en ID 13
    SELECT 11 AS target_id,
           name, type, description, content, paper_size,
           active, created_by, deleted_at
    FROM document_templates WHERE id = 13
UNION ALL
    -- ID 12 recibirá los datos que hoy están en ID 11
    SELECT 12 AS target_id,
           name, type, description, content, paper_size,
           active, created_by, deleted_at
    FROM document_templates WHERE id = 11
UNION ALL
    -- ID 13 recibirá los datos que hoy están en ID 12
    SELECT 13 AS target_id,
           name, type, description, content, paper_size,
           active, created_by, deleted_at
    FROM document_templates WHERE id = 12;

-- 3. Aplicar el swap (los IDs 11, 12, 13 no cambian)
UPDATE document_templates t
JOIN _dt_reorder s ON t.id = s.target_id
SET
    t.name        = s.name,
    t.type        = s.type,
    t.description = s.description,
    t.content     = s.content,
    t.paper_size  = s.paper_size,
    t.active      = s.active,
    t.created_by  = s.created_by,
    t.deleted_at  = s.deleted_at;

-- 4. Limpiar
DROP TEMPORARY TABLE _dt_reorder;

-- 5. Verificar resultado final
SELECT id, name, type, paper_size
FROM document_templates
WHERE id IN (11, 12, 13)
ORDER BY id;
-- Debe mostrar:
--   11 | Contrato Resumen - Pequeño       | contrato  | pequeno
--   12 | Comprobante Compra - Pequeño     | recibo    | pequeno
--   13 | Comprobante Compra - Media Carta | recibo    | mediano
