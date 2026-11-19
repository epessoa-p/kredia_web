USE prestamos_db;

-- Step 2: Backfill numero for existing records (sequential per branch, ordered by id)
-- Run this AFTER add_numero_field.sql

-- Loans
UPDATE loans l
JOIN (
  SELECT id,
    ROW_NUMBER() OVER (PARTITION BY COALESCE(branch_id, 0) ORDER BY id) AS rn
  FROM loans
) ranked ON l.id = ranked.id
SET l.numero = ranked.rn;

-- Purchases
UPDATE purchases p
JOIN (
  SELECT id,
    ROW_NUMBER() OVER (PARTITION BY COALESCE(branch_id, 0) ORDER BY id) AS rn
  FROM purchases
) ranked ON p.id = ranked.id
SET p.numero = ranked.rn;

-- Sales
UPDATE sales s
JOIN (
  SELECT id,
    ROW_NUMBER() OVER (PARTITION BY COALESCE(branch_id, 0) ORDER BY id) AS rn
  FROM sales
) ranked ON s.id = ranked.id
SET s.numero = ranked.rn;

-- Mark columns NOT NULL and add unique index per branch
ALTER TABLE `loans`
  MODIFY COLUMN `numero` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE INDEX `loans_branch_numero_unique` (`branch_id`, `numero`);

ALTER TABLE `purchases`
  MODIFY COLUMN `numero` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE INDEX `purchases_branch_numero_unique` (`branch_id`, `numero`);

ALTER TABLE `sales`
  MODIFY COLUMN `numero` BIGINT UNSIGNED NOT NULL,
  ADD UNIQUE INDEX `sales_branch_numero_unique` (`branch_id`, `numero`);
