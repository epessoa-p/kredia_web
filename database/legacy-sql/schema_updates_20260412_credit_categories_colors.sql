USE prestamos_db;

START TRANSACTION;

ALTER TABLE credit_categories
    ADD COLUMN color VARCHAR(7) NULL AFTER slug;

-- Colores base por categoria existente (IDs solicitados y diferenciables)
-- id=2: amarillo medio
UPDATE credit_categories SET color = '#D9B646' WHERE id = 2;

-- id=4: naranja medio (segun requerimiento)
UPDATE credit_categories SET color = '#16A34A' WHERE id = 4;

-- Colores sugeridos para otras categorias
UPDATE credit_categories SET color = '#4F46E5' WHERE id = 1 AND (color IS NULL OR color = '');
UPDATE credit_categories SET color = '#E68A2E' WHERE id = 3 AND (color IS NULL OR color = '');

-- Fallback para cualquier otra categoria sin color
UPDATE credit_categories
SET color = '#64748B'
WHERE color IS NULL OR color = '';

COMMIT;
