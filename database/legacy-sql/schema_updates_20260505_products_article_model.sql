USE prestamos_db;

START TRANSACTION;

ALTER TABLE products
    ADD COLUMN item_model VARCHAR(255) NULL AFTER brand;

COMMIT;
