-- =============================================================
-- Fix: Hacer SKU nullable para permitir productos sin código
-- Fecha: 2026-04-16
-- =============================================================

-- Remover constraint UNIQUE de sku para permitir valores NULL
ALTER TABLE `products` DROP INDEX `products_sku_unique`;

-- Hacer sku nullable
ALTER TABLE `products` MODIFY COLUMN `sku` VARCHAR(100) NULL;

-- Opcionalmente, recrear unique constraint pero permitiendo múltiples NULLs
-- (MySQL trata múltiples NULL como distintos en índices UNIQUE)
ALTER TABLE `products` ADD UNIQUE KEY `products_sku_unique` (`sku`);
