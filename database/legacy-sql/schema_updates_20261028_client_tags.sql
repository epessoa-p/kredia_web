-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- ============================================================
-- schema_updates_20261028_client_tags.sql
-- Etiquetas de clientes administradas desde "Configurar categorías".
--
-- 1) Tabla `client_tags`: etiquetas de cada empresa (nombre + color).
--    El cliente sigue guardando el nombre en `clients.tag`
--    (ver schema_updates_20261028_clients_tag.sql, que debe ejecutarse antes).
-- 2) Carga en el catálogo las etiquetas que ya tengan los clientes.
-- 3) Permiso `clients.tag` ("Etiquetar clientes"): poner o quitar la etiqueta
--    de un cliente. Asignado a Super Admin (1), Administrador de Empresa (2)
--    y al rol "Gerente" (por nombre).
--
-- Script idempotente: puede ejecutarse varias veces sin error.
-- ============================================================

-- 1) Tabla
CREATE TABLE IF NOT EXISTS `client_tags` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(40) NOT NULL,
    `color` VARCHAR(7) NOT NULL DEFAULT '#4f46e5',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `client_tags_company_name_unique` (`company_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Etiquetas ya usadas por clientes
INSERT INTO `client_tags` (`company_id`, `name`, `color`, `created_at`, `updated_at`)
SELECT DISTINCT c.company_id, UPPER(TRIM(c.tag)), '#4f46e5', NOW(), NOW()
FROM `clients` c
WHERE c.company_id IS NOT NULL
  AND c.tag IS NOT NULL AND TRIM(c.tag) <> ''
  AND NOT EXISTS (
      SELECT 1 FROM `client_tags` t WHERE t.company_id = c.company_id AND t.name = UPPER(TRIM(c.tag))
  );

-- 3) Permiso
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Etiquetar clientes', 'clients.tag', 'clients', 'Poner o quitar la etiqueta de un cliente', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'clients.tag');

INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permissions` p ON p.slug = 'clients.tag'
WHERE (r.id IN (1, 2) OR r.name = 'Gerente')
  AND NOT EXISTS (
      SELECT 1 FROM `role_permission` rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
