-- =============================================================
-- Nuevos permisos: dashboard gerencial y fechas personalizables
-- Fecha: 2026-04-06
-- =============================================================

-- 1. Permiso: ver el dashboard gerencial (estadísticas globales)
--    Los trabajadores sin este permiso verán el dashboard de cobros.
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT
    'Dashboard Gerencial',
    'dashboard.manager',
    'dashboard',
    'Permite ver el dashboard gerencial con estadísticas globales (préstamos, montos, distribución). Sin este permiso el usuario ve el panel de cobros del trabajador.',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'dashboard.manager'
);

-- 2. Permiso: ver el dashboard de cobros (panel del trabajador)
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT
    'Dashboard Cobros',
    'dashboard.worker',
    'dashboard',
    'Permite ver el panel de cobros del trabajador (cobros del día, próximos vencimientos, mora).',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'dashboard.worker'
);

-- Asignar dashboard.worker al rol Trabajador (role_id = 3) — ajusta según tu configuración
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 3, p.`id`
FROM `permissions` p
WHERE p.`slug` = 'dashboard.worker'
AND NOT EXISTS (
    SELECT 1 FROM `role_permission` rp
    WHERE rp.`role_id` = 3 AND rp.`permission_id` = p.`id`
);

-- 3. Permiso: establecer fecha de inicio de préstamo
--    Sin este permiso la fecha se fija automáticamente al día de hoy.
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT
    'Establecer Fecha de Inicio de Préstamo',
    'loans.set_start_date',
    'loans',
    'Permite elegir manualmente la fecha de inicio (start_date) al crear un préstamo. Sin este permiso la fecha siempre será la del día.',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'loans.set_start_date'
);

-- 3. Permiso: establecer fecha de pago manualmente
--    Sin este permiso la fecha del pago se fija automáticamente al día de hoy.
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT
    'Establecer Fecha de Pago',
    'loans.payments.set_date',
    'payments',
    'Permite elegir manualmente la fecha al registrar un pago. Sin este permiso la fecha siempre será la del día.',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'loans.payments.set_date'
);

-- =============================================================
-- Asignar los 3 permisos al rol Gerente / Admin (role_id = 2)
-- Ajusta el role_id según tu configuración.
-- =============================================================
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 2, p.`id`
FROM `permissions` p
WHERE p.`slug` IN (
    'dashboard.manager',
    'loans.set_start_date',
    'loans.payments.set_date'
)
AND NOT EXISTS (
    SELECT 1 FROM `role_permission` rp
    WHERE rp.`role_id` = 2
      AND rp.`permission_id` = p.`id`
);

-- =============================================================
-- (Opcional) También para role_id = 1 (Super Admin)
-- =============================================================
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 1, p.`id`
FROM `permissions` p
WHERE p.`slug` IN (
    'dashboard.manager',
    'loans.set_start_date',
    'loans.payments.set_date'
)
AND NOT EXISTS (
    SELECT 1 FROM `role_permission` rp
    WHERE rp.`role_id` = 1
      AND rp.`permission_id` = p.`id`
);
