USE prestamos_db;

-- Step 1: Add nullable numero column to the three tables
-- Run this BEFORE backfill_numero.sql

ALTER TABLE `loans`
  ADD COLUMN `numero` BIGINT UNSIGNED NULL AFTER `branch_id`;

ALTER TABLE `purchases`
  ADD COLUMN `numero` BIGINT UNSIGNED NULL AFTER `branch_id`;

ALTER TABLE `sales`
  ADD COLUMN `numero` BIGINT UNSIGNED NULL AFTER `branch_id`;
