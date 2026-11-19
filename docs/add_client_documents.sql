-- ============================================================
--  SCRIPT: Documentos de Cliente
--  Descripción: Tabla para almacenar documentos adjuntos
--               (imágenes de CI, facturas, etc.) por cliente.
--  Fecha: 2026-05-14
-- ============================================================

CREATE TABLE `client_documents` (
  `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_id`     BIGINT UNSIGNED NOT NULL,
  `company_id`    BIGINT UNSIGNED NOT NULL,
  `label`         VARCHAR(120) NULL COMMENT 'Ej: CI Anverso, CI Reverso, Factura',
  `path`          VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime_type`     VARCHAR(100) NULL,
  `size_bytes`    INT UNSIGNED NULL,
  `created_at`    TIMESTAMP NULL,
  `updated_at`    TIMESTAMP NULL,
  CONSTRAINT fk_clidoc_client  FOREIGN KEY (`client_id`)  REFERENCES `clients`(`id`)    ON DELETE CASCADE,
  CONSTRAINT fk_clidoc_company FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`),
  INDEX idx_clidoc_client (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
