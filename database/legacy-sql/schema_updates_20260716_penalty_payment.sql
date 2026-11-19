-- ============================================================
-- Pago de multa
-- Fecha: 2026-07-16
-- 1) loans.penalty_paid_at: cuándo se pagó la multa (NULL = pendiente).
-- 2) loan_payments.payment_type: nuevo valor 'penalty'.
--
-- NO requiere backfill: el reloj de los días de gracia se recalcula
-- solo, derivado del period_end del periodo que alcanzó el plazo tope.
-- Idempotente: seguro de ejecutar más de una vez.
-- ============================================================

-- 1) loans.penalty_paid_at (guarda idempotente)
SET @sql = IF(
    EXISTS(
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'loans'
          AND COLUMN_NAME = 'penalty_paid_at'
    ),
    'SELECT "loans.penalty_paid_at ya existe" AS info',
    'ALTER TABLE `loans` ADD COLUMN `penalty_paid_at` DATETIME NULL COMMENT ''Cuándo se pagó la multa (NULL = pendiente)'' AFTER `penalty_amount`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) loan_payments.payment_type: agregar 'penalty' al ENUM
--    (MODIFY es idempotente: deja la columna en el estado deseado)
ALTER TABLE `loan_payments`
    MODIFY COLUMN `payment_type` ENUM('interest','capital','mixed','penalty') NOT NULL DEFAULT 'mixed';

-- ── Verificación (opcional) ──────────────────────────────────
-- SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loans' AND COLUMN_NAME LIKE 'penalty%';
-- SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
--   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'loan_payments' AND COLUMN_NAME = 'payment_type';
