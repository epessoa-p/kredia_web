-- =====================================================================
-- 001 · Roles por empresa (roles.company_id)
-- Reejecutable: puede correrse varias veces sin error.
-- Equivale a la migración 2026_11_18_000001_add_company_id_to_roles_table
-- =====================================================================

SET @col := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'roles' AND column_name = 'company_id');
SET @sql := IF(@col = 0,
    'ALTER TABLE roles ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id',
    'SELECT ''roles.company_id ya existe''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk := (SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema = DATABASE() AND table_name = 'roles' AND constraint_name = 'roles_company_id_foreign');
SET @sql := IF(@fk = 0,
    'ALTER TABLE roles ADD CONSTRAINT roles_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE',
    'SELECT ''FK roles_company_id_foreign ya existe''');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Roles creados por cargos → pertenecen a la empresa del cargo.
UPDATE roles r
JOIN (
    SELECT role_id, MIN(company_id) AS company_id
    FROM cargos
    WHERE role_id IS NOT NULL
    GROUP BY role_id
    HAVING COUNT(DISTINCT company_id) = 1
) c ON c.role_id = r.id
SET r.company_id = c.company_id
WHERE r.company_id IS NULL
  AND r.slug NOT IN ('super_admin','admin','manager','cashier','employee');

-- Registrar la migración como ejecutada (sin duplicar).
INSERT INTO migrations (migration, batch)
SELECT '2026_11_18_000001_add_company_id_to_roles_table', 3 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_18_000001_add_company_id_to_roles_table');
