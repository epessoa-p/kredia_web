-- ============================================================
-- Regularizar préstamos con la gracia vencida
--   EN_MORA  -> INCUMPLIDO   (loans.status / penalty_status)
--   prenda   -> 'sellable'   (queda DISPONIBLE y se puede vender)
-- Fecha: 2026-07-16
--
-- ¿POR QUÉ ESTE SCRIPT?
-- El fix de código corrige el reloj de gracia (ahora se cuenta desde el
-- period_end del periodo que alcanzó el plazo tope, no desde cuándo el
-- sistema se enteró). Pero los registros YA guardados en el servidor siguen
-- con el estado viejo hasta que la lógica vuelva a evaluarlos.
--
-- ⚠️ ALTERNATIVA RECOMENDADA (hace exactamente lo mismo, sin SQL):
--       php artisan loans:check-overdue
--    Este script existe por si no puedes ejecutar artisan en el servidor.
--
-- NOTA: el cambio de "Disponible para venta" -> "Disponible" es SOLO CÓDIGO
--       (PHP/Blade). No requiere script: se aplica al subir el código.
--
-- Idempotente: se puede ejecutar varias veces sin efecto adicional.
-- Requiere MariaDB 10.2+ / MySQL 8+ (usa ROW_NUMBER).
-- Hacer BACKUP antes.
-- ============================================================

-- ── PASO 0 — DIAGNÓSTICO (solo lectura, ejecútalo primero) ──
-- Muestra qué préstamos serían afectados y por qué.
/*
SELECT l.numero AS prestamo, l.status, l.penalty_status,
       t.plazo_tope, t.grace_days,
       p.period_end AS alcanzo_tope_el,
       DATE_ADD(TIMESTAMP(p.period_end, '23:59:59'), INTERVAL t.grace_days DAY) AS gracia_vence,
       CASE WHEN NOW() > DATE_ADD(TIMESTAMP(p.period_end, '23:59:59'), INTERVAL t.grace_days DAY)
            THEN 'SE ACTUALIZA' ELSE 'aun en gracia' END AS accion
FROM (
    SELECT l.id AS loan_id,
           COALESCE(cc.penalty_grace_days, 0) AS grace_days,
           GREATEST(1, ROUND(l.term_months / CASE l.interest_period
                WHEN 'quarterly'  THEN 3
                WHEN 'semiannual' THEN 6
                WHEN 'annual'     THEN 12
                ELSE 1 END)) AS plazo_tope
    FROM loans l
    LEFT JOIN credit_categories cc ON cc.id = l.credit_category_id
    WHERE l.deleted_at IS NULL AND l.penalty_status = 'overdue'
) t
JOIN loans l ON l.id = t.loan_id
JOIN (
    SELECT ip.loan_id, ip.period_end,
           ROW_NUMBER() OVER (PARTITION BY ip.loan_id ORDER BY ip.period_end) AS rn
    FROM loan_interest_payments ip
    WHERE ip.status <> 'paid' AND ip.deleted_at IS NULL AND ip.period_end < CURDATE()
) p ON p.loan_id = t.loan_id AND p.rn = t.plazo_tope
ORDER BY l.numero;
*/

START TRANSACTION;

-- ── PASO 1 — Identificar los préstamos cuya gracia YA venció ──
-- Réplica exacta de Loan::checkOverdueStatus():
--   plazo tope     = max(1, round(term_months / meses_del_periodo))
--   alcanzó el tope = period_end del periodo #plazo_tope (vencidos impagos, asc)
--   gracia vence    = ese period_end (fin del día) + penalty_grace_days
DROP TEMPORARY TABLE IF EXISTS tmp_loans_defaulted;

CREATE TEMPORARY TABLE tmp_loans_defaulted AS
SELECT t.loan_id
FROM (
    SELECT l.id AS loan_id,
           COALESCE(cc.penalty_grace_days, 0) AS grace_days,
           GREATEST(1, ROUND(l.term_months / CASE l.interest_period
                WHEN 'quarterly'  THEN 3
                WHEN 'semiannual' THEN 6
                WHEN 'annual'     THEN 12
                ELSE 1 END)) AS plazo_tope
    FROM loans l
    LEFT JOIN credit_categories cc ON cc.id = l.credit_category_id
    WHERE l.deleted_at IS NULL
      AND l.penalty_status = 'overdue'              -- solo los que ya tienen multa
      AND l.status IN ('ACTIVO', 'EN_MORA')         -- aún no incumplidos
) t
JOIN (
    SELECT ip.loan_id, ip.period_end,
           ROW_NUMBER() OVER (PARTITION BY ip.loan_id ORDER BY ip.period_end) AS rn
    FROM loan_interest_payments ip
    WHERE ip.status <> 'paid'
      AND ip.deleted_at IS NULL
      AND ip.period_end < CURDATE()
) p ON p.loan_id = t.loan_id AND p.rn = t.plazo_tope
WHERE NOW() > DATE_ADD(TIMESTAMP(p.period_end, '23:59:59'), INTERVAL t.grace_days DAY);

-- ── PASO 2 — Préstamo: EN_MORA -> INCUMPLIDO ──
UPDATE loans l
  JOIN tmp_loans_defaulted d ON d.loan_id = l.id
SET l.status         = 'INCUMPLIDO',
    l.penalty_status = 'defaulted',
    l.updated_at     = NOW();

-- ── PASO 3 — Prenda: retenida/en retención -> DISPONIBLE (sellable) ──
UPDATE loan_collaterals c
  JOIN tmp_loans_defaulted d ON d.loan_id = c.loan_id
SET c.status      = 'sellable',
    c.sellable_at = NOW(),
    c.updated_at  = NOW()
WHERE c.deleted_at IS NULL
  AND c.status IN ('retained', 'stored');

DROP TEMPORARY TABLE IF EXISTS tmp_loans_defaulted;

COMMIT;

-- ── VERIFICACIÓN (ejecutar después) ──────────────────────────
/*
SELECT l.numero AS prestamo, l.status, l.penalty_status,
       c.status AS prenda, c.sellable_at
FROM loans l
LEFT JOIN loan_collaterals c ON c.loan_id = l.id AND c.deleted_at IS NULL
WHERE l.penalty_status IN ('overdue','defaulted') AND l.deleted_at IS NULL
ORDER BY l.numero;
*/
