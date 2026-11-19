-- ============================================================
-- Permiso: Reiniciar Sucursal (branches.reset)
-- Fecha: 2026-07-17
-- Habilita el panel destructivo que borra todos los registros
-- operativos de una sucursal (préstamos, ventas, compras, caja,
-- inventario, etc.), conservando personal, cajas, clientes y productos.
-- Idempotente: seguro de ejecutar más de una vez.
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Reiniciar Sucursal', 'branches.reset', 'branches',
       'Borra todos los registros operativos de una sucursal (acción destructiva)', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'branches.reset');

-- Otorgar a Super Admin (id=1)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.slug = 'branches.reset'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 1);

-- Otorgar a Admin (id=2)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p
WHERE p.slug = 'branches.reset'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 2);

-- ── Verificación (opcional) ──────────────────────────────────
-- SELECT * FROM `permissions` WHERE slug = 'branches.reset';
