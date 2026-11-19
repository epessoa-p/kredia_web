-- ============================================================
-- Permiso: Ajuste de Caja (cash-registers.adjust)
-- Fecha: 2026-07-07
-- Permite corregir un cierre de caja o registrar un movimiento
-- de ajuste (faltante/sobrante) desde el reporte de Movimientos.
-- Idempotente: seguro de ejecutar aunque ya exista.
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ajuste de Caja', 'cash-registers.adjust', 'cash-registers', 'Corregir cierre o registrar movimiento de ajuste', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cash-registers.adjust');

-- Otorgar a Super Admin (id=1)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.slug = 'cash-registers.adjust'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 1);

-- Otorgar a Admin (id=2)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p
WHERE p.slug = 'cash-registers.adjust'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 2);
