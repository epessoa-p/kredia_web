-- ############################################################################
-- ##  SEGURO DE REEJECUTAR: SÍ                                              ##
-- ##  Se puede ejecutar varias veces sin error y sin dañar datos.           ##
-- ############################################################################
--
-- ============================================================
-- schema_updates_20261028_client_rating_settings.sql
-- Panel de configuración de las categorías de clientes.
--
-- 1) Columna `companies.client_rating_settings` (JSON en texto) con los
--    valores de cada empresa: días de tolerancia, atrasos permitidos,
--    préstamos / monto mínimo para "Cliente fiel", etc.
--    NULL = se usan los valores por defecto de config/clients.php.
-- 2) Permiso `clients.rating.settings` ("Configurar categorías de
--    clientes"), asignado a Super Admin (1), Administrador de Empresa (2)
--    y al rol "Gerente" (por nombre).
--
-- Script idempotente: puede ejecutarse varias veces sin error.
-- ============================================================

-- 1) Columna client_rating_settings
SET @col_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'companies'
      AND COLUMN_NAME = 'client_rating_settings'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `companies` ADD COLUMN `client_rating_settings` TEXT NULL AFTER `interest_next_day`',
    'SELECT "columna client_rating_settings ya existe" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Permiso
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Configurar categorías de clientes', 'clients.rating.settings', 'clients', 'Ajustar las reglas de las categorías de clientes (fiel, puntual, riesgo…)', NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'clients.rating.settings');

INSERT INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
JOIN `permissions` p ON p.slug = 'clients.rating.settings'
WHERE (r.id IN (1, 2) OR r.name = 'Gerente')
  AND NOT EXISTS (
      SELECT 1 FROM `role_permission` rp WHERE rp.role_id = r.id AND rp.permission_id = p.id
  );
