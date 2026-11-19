-- ============================================================
-- Autorización por única vez para eliminar una venta
-- Fecha: 2026-07-16
--
-- Permite que un responsable (con el permiso 'sales.delegate_delete')
-- autorice a OTRO usuario a eliminar UNA venta concreta, sin darle el
-- permiso permanente. La autorización:
--   · es de un solo uso (se consume al eliminar → used_at)
--   · caduca a las 24 horas (expires_at)
--   · se puede revocar (revoked_at)
--
-- Idempotente: seguro de ejecutar más de una vez.
-- ============================================================

-- ── 1) Tabla de autorizaciones ───────────────────────────────
CREATE TABLE IF NOT EXISTS `sale_delete_grants` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `sale_id`    BIGINT UNSIGNED NOT NULL,
    `granted_to` BIGINT UNSIGNED NOT NULL COMMENT 'Usuario autorizado a eliminar esta venta',
    `granted_by` BIGINT UNSIGNED NOT NULL COMMENT 'Quien otorgó la autorización',
    `reason`     VARCHAR(500) NULL,
    `expires_at` DATETIME NOT NULL COMMENT 'Caduca a las 24h de otorgada',
    `used_at`    DATETIME NULL COMMENT 'Cuándo se consumió (un solo uso)',
    `revoked_at` DATETIME NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_sdg_lookup` (`sale_id`, `granted_to`, `used_at`, `revoked_at`, `expires_at`),
    INDEX `idx_sdg_granted_to` (`granted_to`),
    CONSTRAINT `fk_sdg_company`    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sdg_sale`       FOREIGN KEY (`sale_id`)    REFERENCES `sales`(`id`)     ON DELETE CASCADE,
    CONSTRAINT `fk_sdg_granted_to` FOREIGN KEY (`granted_to`) REFERENCES `users`(`id`)     ON DELETE CASCADE,
    CONSTRAINT `fk_sdg_granted_by` FOREIGN KEY (`granted_by`) REFERENCES `users`(`id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2) Permiso nuevo: quién puede autorizar ──────────────────
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Autorizar Eliminar Venta', 'sales.delegate_delete', 'sales',
       'Permite autorizar a otro usuario a eliminar una venta puntual (24h, un solo uso)', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'sales.delegate_delete');

-- Otorgar a Super Admin (id=1)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 1, p.id FROM `permissions` p
WHERE p.slug = 'sales.delegate_delete'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 1);

-- Otorgar a Admin (id=2)
INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT 2, p.id FROM `permissions` p
WHERE p.slug = 'sales.delegate_delete'
  AND p.id NOT IN (SELECT permission_id FROM `role_permission` WHERE role_id = 2);

-- ── Verificación (opcional) ──────────────────────────────────
-- SELECT * FROM `permissions` WHERE slug = 'sales.delegate_delete';
-- SHOW CREATE TABLE `sale_delete_grants`;
