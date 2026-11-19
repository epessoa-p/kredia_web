-- ============================================================
-- Relacionar pagos con caja/sesion + permiso de reporte de cajas
-- Fecha: 2026-04-06
-- ============================================================

-- 1) Relacion por caja/sesion en loan_payments (idempotente)
SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'loan_payments'
          AND COLUMN_NAME = 'cash_register_id'
    ),
    'SELECT 1',
    'ALTER TABLE loan_payments ADD COLUMN cash_register_id BIGINT UNSIGNED NULL AFTER loan_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'loan_payments'
          AND COLUMN_NAME = 'cash_register_session_id'
    ),
    'SELECT 1',
    'ALTER TABLE loan_payments ADD COLUMN cash_register_session_id BIGINT UNSIGNED NULL AFTER cash_register_id'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Indices
SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'loan_payments'
          AND INDEX_NAME = 'idx_loan_payments_cash_register_id'
    ),
    'SELECT 1',
    'CREATE INDEX idx_loan_payments_cash_register_id ON loan_payments (cash_register_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'loan_payments'
          AND INDEX_NAME = 'idx_loan_payments_cash_register_session_id'
    ),
    'SELECT 1',
    'CREATE INDEX idx_loan_payments_cash_register_session_id ON loan_payments (cash_register_session_id)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Foreign keys
SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'loan_payments'
          AND CONSTRAINT_NAME = 'fk_loan_payments_cash_register'
    ),
    'SELECT 1',
    'ALTER TABLE loan_payments ADD CONSTRAINT fk_loan_payments_cash_register FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id) ON DELETE SET NULL'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'loan_payments'
          AND CONSTRAINT_NAME = 'fk_loan_payments_cash_session'
    ),
    'SELECT 1',
    'ALTER TABLE loan_payments ADD CONSTRAINT fk_loan_payments_cash_session FOREIGN KEY (cash_register_session_id) REFERENCES cash_register_sessions(id) ON DELETE SET NULL'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Permiso para ver reporte de aperturas/cierres de caja
INSERT INTO permissions (name, slug, module, description, created_at, updated_at)
SELECT
    'Reporte de Cajas',
    'cash-registers.report',
    'cash-registers',
    'Permite visualizar reporte detallado de aperturas y cierres de caja con cobros vinculados.',
    NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE slug = 'cash-registers.report'
);

-- 5) Asignacion sugerida a roles (ajusta IDs si tu instalacion es distinta)
-- Super Admin (1)
INSERT INTO role_permission (role_id, permission_id)
SELECT 1, p.id
FROM permissions p
WHERE p.slug = 'cash-registers.report'
  AND NOT EXISTS (
      SELECT 1
      FROM role_permission rp
      WHERE rp.role_id = 1
        AND rp.permission_id = p.id
  );

-- Admin (2)
INSERT INTO role_permission (role_id, permission_id)
SELECT 2, p.id
FROM permissions p
WHERE p.slug = 'cash-registers.report'
  AND NOT EXISTS (
      SELECT 1
      FROM role_permission rp
      WHERE rp.role_id = 2
        AND rp.permission_id = p.id
  );

-- Manager (3)
INSERT INTO role_permission (role_id, permission_id)
SELECT 3, p.id
FROM permissions p
WHERE p.slug = 'cash-registers.report'
  AND NOT EXISTS (
      SELECT 1
      FROM role_permission rp
      WHERE rp.role_id = 3
        AND rp.permission_id = p.id
  );
