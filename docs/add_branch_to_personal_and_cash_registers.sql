-- ============================================================
--  Agregar branch_id a personals y cash_registers
--
--  El campo es NULL para el gerente (cargo_id = 1) y
--  se recomienda llenar para el resto del personal.
-- ============================================================

-- 1. Verificar estado actual
SELECT 'personals' AS tabla, COUNT(*) AS registros FROM personals;
SELECT 'cash_registers' AS tabla, COUNT(*) AS registros FROM cash_registers;

-- 2. Agregar branch_id a personals
ALTER TABLE `personals`
    ADD COLUMN `branch_id` BIGINT UNSIGNED NULL
    AFTER `company_id`;

ALTER TABLE `personals`
    ADD CONSTRAINT `fk_personals_branch_id`
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

CREATE INDEX `idx_personals_branch_id` ON `personals`(`branch_id`);

-- 3. Agregar branch_id a cash_registers
ALTER TABLE `cash_registers`
    ADD COLUMN `branch_id` BIGINT UNSIGNED NULL
    AFTER `company_id`;

ALTER TABLE `cash_registers`
    ADD CONSTRAINT `fk_cash_registers_branch_id`
    FOREIGN KEY (`branch_id`) REFERENCES `branches`(`id`)
    ON DELETE SET NULL ON UPDATE CASCADE;

CREATE INDEX `idx_cash_registers_branch_id` ON `cash_registers`(`branch_id`);

-- 4. Verificar resultado
SELECT id, company_id, branch_id, full_name FROM personals ORDER BY id;
SELECT id, company_id, branch_id, name FROM cash_registers ORDER BY id;
-- Asignar sucursales manualmente con UPDATE según corresponda, ej:
-- UPDATE personals SET branch_id = 1 WHERE id IN (2, 3);
-- UPDATE cash_registers SET branch_id = 1 WHERE id IN (1, 2);
