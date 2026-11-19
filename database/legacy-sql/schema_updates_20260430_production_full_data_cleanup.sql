-- =============================================================
-- LIMPIEZA TOTAL DE DATOS OPERATIVOS (PRODUCCION)
-- Fecha: 2026-04-30
--
-- ESTE SCRIPT ELIMINA DATOS DE:
-- - Prestamos y tablas relacionadas
-- - Clientes (y por tanto referencias en prestamos/compras/ventas)
-- - Compras y ventas
-- - Aperturas/cierres de caja
-- - Movimientos de caja y de almacen
--
-- Recomendacion: realizar backup antes de ejecutar.
-- =============================================================

SET SQL_SAFE_UPDATES = 0;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;

-- -------------------------------------------------------------
-- 1) Adjuntos/imagenes (dependen de tablas operativas)
-- -------------------------------------------------------------
DELETE FROM loan_contract_attachments;
DELETE FROM loan_images;
DELETE FROM purchase_images;
DELETE FROM sale_images;
DELETE FROM movement_images;

-- -------------------------------------------------------------
-- 2) Detalle de operaciones financieras
-- -------------------------------------------------------------
DELETE FROM loan_interest_payments;
DELETE FROM loan_amortizations;
DELETE FROM loan_payments;
DELETE FROM sale_installments;
DELETE FROM cash_movements;

-- -------------------------------------------------------------
-- 3) Contratos, garantias y operaciones principales
-- -------------------------------------------------------------
DELETE FROM loan_contracts;
DELETE FROM loan_collaterals;
DELETE FROM sales;
DELETE FROM purchases;
DELETE FROM loans;

-- -------------------------------------------------------------
-- 4) Movimientos de inventario y sesiones de caja
-- -------------------------------------------------------------
DELETE FROM inventory_movements;
DELETE FROM cash_register_sessions;

-- -------------------------------------------------------------
-- 5) Clientes
--    (se eliminan al final por referencias historicas)
-- -------------------------------------------------------------
DELETE FROM clients;

-- -------------------------------------------------------------
-- 6) Reiniciar secuencias AUTO_INCREMENT (opcional)
-- -------------------------------------------------------------
ALTER TABLE loan_contract_attachments AUTO_INCREMENT = 1;
ALTER TABLE loan_images AUTO_INCREMENT = 1;
ALTER TABLE purchase_images AUTO_INCREMENT = 1;
ALTER TABLE sale_images AUTO_INCREMENT = 1;
ALTER TABLE movement_images AUTO_INCREMENT = 1;

ALTER TABLE loan_interest_payments AUTO_INCREMENT = 1;
ALTER TABLE loan_amortizations AUTO_INCREMENT = 1;
ALTER TABLE loan_payments AUTO_INCREMENT = 1;
ALTER TABLE sale_installments AUTO_INCREMENT = 1;
ALTER TABLE cash_movements AUTO_INCREMENT = 1;

ALTER TABLE loan_contracts AUTO_INCREMENT = 1;
ALTER TABLE loan_collaterals AUTO_INCREMENT = 1;
ALTER TABLE sales AUTO_INCREMENT = 1;
ALTER TABLE purchases AUTO_INCREMENT = 1;
ALTER TABLE loans AUTO_INCREMENT = 1;
ALTER TABLE inventory_movements AUTO_INCREMENT = 1;
ALTER TABLE cash_register_sessions AUTO_INCREMENT = 1;
ALTER TABLE clients AUTO_INCREMENT = 1;

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;
