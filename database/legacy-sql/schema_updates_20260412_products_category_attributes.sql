USE prestamos_db;

START TRANSACTION;

ALTER TABLE products
    ADD COLUMN brand VARCHAR(255) NULL AFTER sku,
    ADD COLUMN model_year INT NULL AFTER brand,
    ADD COLUMN plate VARCHAR(120) NULL AFTER model_year,
    ADD COLUMN chassis VARCHAR(120) NULL AFTER plate,
    ADD COLUMN serial_number VARCHAR(120) NULL AFTER chassis,
    ADD COLUMN karat VARCHAR(20) NULL AFTER serial_number,
    ADD COLUMN item_type VARCHAR(120) NULL AFTER karat;

CREATE INDEX products_category_brand_index ON products (credit_category_id, brand);
CREATE INDEX products_category_serial_index ON products (credit_category_id, serial_number);
CREATE INDEX products_category_plate_index ON products (credit_category_id, plate);

COMMIT;
