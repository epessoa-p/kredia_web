-- ============================================================
-- Permiso: Ingreso a Caja (cash-registers.deposit)
-- Fecha: 2026-07-13
-- Permite registrar un ingreso de efectivo en una caja ABIERTA
-- desde el reporte de Movimientos (tab "Cierres de caja").
-- Caso de uso: la caja se queda sin efectivo y necesita fondos
-- para poder desembolsar un préstamo.
-- Idempotente: seguro de ejecutar aunque ya exista.
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ingreso a Caja', 'cash-registers.deposit', 'cash-registers', 'Registrar un ingreso de efectivo en una caja abierta', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cash-registers.deposit');

-- Otorgar a Super Admin (id=1)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.slug = 'cash-registers.deposit'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 1);

-- Otorgar a Admin (id=2)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p
WHERE p.slug = 'cash-registers.deposit'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 2);
