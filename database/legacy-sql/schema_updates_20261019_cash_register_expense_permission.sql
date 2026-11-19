-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- ============================================================
-- Permiso: Registrar Gasto en Caja (cash-registers.expense)
-- Fecha: 2026-10-19
-- Permite registrar un EGRESO (gasto manual) en una caja ABIERTA
-- desde el botón "Registrar gasto" del navbar.
-- Tipos: servicio, pago a personal, otro gasto operativo.
-- Idempotente: seguro de ejecutar aunque ya exista.
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Registrar Gasto en Caja', 'cash-registers.expense', 'cash-registers', 'Registrar un gasto (egreso) en una caja abierta', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'cash-registers.expense');

-- Otorgar a Super Admin (id=1)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.slug = 'cash-registers.expense'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 1);

-- Otorgar a Admin (id=2)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p
WHERE p.slug = 'cash-registers.expense'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 2);
