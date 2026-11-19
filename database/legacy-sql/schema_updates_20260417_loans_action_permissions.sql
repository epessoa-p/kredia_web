-- =============================================================
-- PERMISOS GRANULARES PARA ACCIONES DE PRESTAMOS
-- Fecha: 2026-04-17
-- Objetivo:
--   1) Crear permisos separados para amortizar y liquidar
--   2) Asignarlos automaticamente a roles que ya pueden registrar pagos
--      o editar prestamos
-- =============================================================

-- 1) Crear permisos nuevos (idempotente)
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Amortizar Préstamos', 'loans.amortize', 'loans', 'Permite registrar amortizaciones de capital', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'loans.amortize'
);

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Liquidar Préstamos', 'loans.liquidate', 'loans', 'Permite liquidar préstamos', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions` WHERE `slug` = 'loans.liquidate'
);

-- 2) Asignar loans.amortize a roles que ya tienen payments.create o loans.edit
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT src.role_id, p_new.id
FROM (
    SELECT DISTINCT rp.role_id
    FROM `role_permission` rp
    INNER JOIN `permissions` p ON p.id = rp.permission_id
    WHERE p.slug IN ('payments.create', 'loans.edit')
) src
INNER JOIN `permissions` p_new ON p_new.slug = 'loans.amortize'
WHERE NOT EXISTS (
    SELECT 1
    FROM `role_permission` rp2
    WHERE rp2.role_id = src.role_id
      AND rp2.permission_id = p_new.id
);

-- 3) Asignar loans.liquidate a roles que ya tienen payments.create o loans.edit
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT src.role_id, p_new.id
FROM (
    SELECT DISTINCT rp.role_id
    FROM `role_permission` rp
    INNER JOIN `permissions` p ON p.id = rp.permission_id
    WHERE p.slug IN ('payments.create', 'loans.edit')
) src
INNER JOIN `permissions` p_new ON p_new.slug = 'loans.liquidate'
WHERE NOT EXISTS (
    SELECT 1
    FROM `role_permission` rp2
    WHERE rp2.role_id = src.role_id
      AND rp2.permission_id = p_new.id
);

-- 4) Verificacion rapida
-- SELECT id, name, slug FROM permissions WHERE slug IN ('loans.amortize', 'loans.liquidate');
-- SELECT rp.role_id, p.slug FROM role_permission rp
-- JOIN permissions p ON p.id = rp.permission_id
-- WHERE p.slug IN ('loans.amortize', 'loans.liquidate')
-- ORDER BY rp.role_id, p.slug;
