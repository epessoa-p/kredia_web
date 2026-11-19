-- ============================================================
--  SCRIPT: Capital de Empresa
--  Descripción: Crea la tabla company_capital_entries para
--               registrar aportes y retiros de capital del
--               propietario, separado de la caja diaria.
--  Fecha: 2026-05-13
-- ============================================================

CREATE TABLE `company_capital_entries` (
  `id`          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id`  BIGINT UNSIGNED NOT NULL,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `type`        ENUM('input','withdrawal') NOT NULL COMMENT 'input=aporte, withdrawal=retiro',
  `amount`      DECIMAL(15,2) NOT NULL,
  `description` VARCHAR(500) NULL,
  `entry_date`  DATE NOT NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  CONSTRAINT fk_capital_company FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_capital_user    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`),
  INDEX idx_capital_company_date (`company_id`, `entry_date`),
  INDEX idx_capital_type         (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  Permisos del módulo Capital
-- ============================================================

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Ver Capital', 'capital.view', 'capital', 'Permite ver el módulo de capital de empresa (aportes y retiros).', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'capital.view');

INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT 'Gestionar Capital', 'capital.create', 'capital', 'Permite registrar y eliminar aportes y retiros de capital.', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'capital.create');

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
