-- =====================================================================
-- 003 · Préstamos en cuotas, garantes, cobranza, anulación, auditoría
-- Reejecutable. Equivale a la migración 2026_11_18_000003_installment_loans_collections
-- =====================================================================

DROP PROCEDURE IF EXISTS kredia_add_column;
DELIMITER $$
CREATE PROCEDURE kredia_add_column(IN p_table VARCHAR(64), IN p_column VARCHAR(64), IN p_definition TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_schema = DATABASE() AND table_name = p_table AND column_name = p_column) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS kredia_add_fk$$
CREATE PROCEDURE kredia_add_fk(IN p_table VARCHAR(64), IN p_name VARCHAR(64), IN p_definition TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints
                   WHERE table_schema = DATABASE() AND table_name = p_table AND constraint_name = p_name) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD CONSTRAINT `', p_name, '` ', p_definition);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$

DROP PROCEDURE IF EXISTS kredia_add_index$$
CREATE PROCEDURE kredia_add_index(IN p_table VARCHAR(64), IN p_name VARCHAR(64), IN p_definition TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.statistics
                   WHERE table_schema = DATABASE() AND table_name = p_table AND index_name = p_name) THEN
        SET @ddl = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
        PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ── Producto de crédito ─────────────────────────────────────────────
CALL kredia_add_column('credit_category_rules', 'loan_kind', "ENUM('pawn','installment') NOT NULL DEFAULT 'pawn' AFTER name");
CALL kredia_add_column('credit_category_rules', 'installment_method', "ENUM('flat','french','german') NULL AFTER loan_kind");
CALL kredia_add_column('credit_category_rules', 'payment_frequency', "ENUM('daily','weekly','biweekly','monthly') NULL AFTER installment_method");
CALL kredia_add_column('credit_category_rules', 'skip_sundays', "TINYINT(1) NOT NULL DEFAULT 1 AFTER payment_frequency");

-- ── Préstamo ────────────────────────────────────────────────────────
CALL kredia_add_column('loans', 'loan_kind', "ENUM('pawn','installment') NOT NULL DEFAULT 'pawn' AFTER credit_category_rule_id");
CALL kredia_add_column('loans', 'installment_method', "ENUM('flat','french','german') NULL AFTER loan_kind");
CALL kredia_add_column('loans', 'payment_frequency', "ENUM('daily','weekly','biweekly','monthly') NULL AFTER installment_method");
CALL kredia_add_column('loans', 'installments_count', "SMALLINT UNSIGNED NULL AFTER payment_frequency");
CALL kredia_add_column('loans', 'installment_amount', "DECIMAL(15,2) NULL AFTER installments_count");
CALL kredia_add_column('loans', 'skip_sundays', "TINYINT(1) NOT NULL DEFAULT 0 AFTER installment_amount");
CALL kredia_add_column('loans', 'collector_id', "BIGINT UNSIGNED NULL AFTER created_by");
CALL kredia_add_column('loans', 'parent_loan_id', "BIGINT UNSIGNED NULL AFTER collector_id");
CALL kredia_add_column('loans', 'refinanced_at', "TIMESTAMP NULL AFTER parent_loan_id");
CALL kredia_add_fk('loans', 'loans_collector_id_foreign', 'FOREIGN KEY (collector_id) REFERENCES personals(id) ON DELETE SET NULL');
CALL kredia_add_fk('loans', 'loans_parent_loan_id_foreign', 'FOREIGN KEY (parent_loan_id) REFERENCES loans(id) ON DELETE SET NULL');

-- ── Plan de pagos ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS loan_installments (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    loan_id       BIGINT UNSIGNED NOT NULL,
    number        SMALLINT UNSIGNED NOT NULL,
    due_date      DATE NOT NULL,
    capital       DECIMAL(15,2) NOT NULL,
    interest      DECIMAL(15,2) NOT NULL,
    amount        DECIMAL(15,2) NOT NULL,
    penalty       DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_capital  DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_interest DECIMAL(15,2) NOT NULL DEFAULT 0,
    paid_penalty  DECIMAL(15,2) NOT NULL DEFAULT 0,
    status        ENUM('pending','partial','paid') NOT NULL DEFAULT 'pending',
    paid_at       TIMESTAMP NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    UNIQUE KEY loan_installments_loan_id_number_unique (loan_id, number),
    KEY loan_installments_loan_id_status_index (loan_id, status),
    KEY loan_installments_due_date_index (due_date),
    CONSTRAINT loan_installments_loan_id_foreign FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pagos ───────────────────────────────────────────────────────────
ALTER TABLE loan_payments MODIFY payment_type ENUM('interest','capital','mixed','penalty','installment','refinance') NOT NULL;
CALL kredia_add_column('loan_payments', 'penalty', "DECIMAL(15,2) NULL AFTER interest");
CALL kredia_add_column('loan_payments', 'latitude', "DECIMAL(10,7) NULL AFTER notes");
CALL kredia_add_column('loan_payments', 'longitude', "DECIMAL(10,7) NULL AFTER latitude");
CALL kredia_add_column('loan_payments', 'request_id', "VARCHAR(64) NULL AFTER longitude");
CALL kredia_add_column('loan_payments', 'voided_at', "TIMESTAMP NULL AFTER request_id");
CALL kredia_add_column('loan_payments', 'voided_by', "BIGINT UNSIGNED NULL AFTER voided_at");
CALL kredia_add_column('loan_payments', 'void_reason', "VARCHAR(500) NULL AFTER voided_by");
CALL kredia_add_index('loan_payments', 'loan_payments_loan_id_request_id_unique', 'UNIQUE KEY loan_payments_loan_id_request_id_unique (loan_id, request_id)');
CALL kredia_add_fk('loan_payments', 'loan_payments_voided_by_foreign', 'FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE SET NULL');

CREATE TABLE IF NOT EXISTS loan_payment_allocations (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    loan_payment_id     BIGINT UNSIGNED NOT NULL,
    loan_installment_id BIGINT UNSIGNED NOT NULL,
    capital             DECIMAL(15,2) NOT NULL DEFAULT 0,
    interest            DECIMAL(15,2) NOT NULL DEFAULT 0,
    penalty             DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    CONSTRAINT loan_payment_allocations_loan_payment_id_foreign FOREIGN KEY (loan_payment_id) REFERENCES loan_payments(id) ON DELETE CASCADE,
    CONSTRAINT loan_payment_allocations_loan_installment_id_foreign FOREIGN KEY (loan_installment_id) REFERENCES loan_installments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Garantes ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS loan_guarantors (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id    BIGINT UNSIGNED NOT NULL,
    loan_id       BIGINT UNSIGNED NOT NULL,
    client_id     BIGINT UNSIGNED NULL,
    name          VARCHAR(255) NOT NULL,
    id_number     VARCHAR(50) NULL,
    phone         VARCHAR(30) NULL,
    address       VARCHAR(255) NULL,
    relationship  VARCHAR(100) NULL,
    notes         TEXT NULL,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    CONSTRAINT loan_guarantors_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT loan_guarantors_loan_id_foreign FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    CONSTRAINT loan_guarantors_client_id_foreign FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Rutas de cobranza ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS collection_routes (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id    BIGINT UNSIGNED NOT NULL,
    branch_id     BIGINT UNSIGNED NULL,
    collector_id  BIGINT UNSIGNED NULL,
    name          VARCHAR(255) NOT NULL,
    description   VARCHAR(500) NULL,
    active        TINYINT(1) NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NULL,
    updated_at    TIMESTAMP NULL,
    CONSTRAINT collection_routes_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT collection_routes_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    CONSTRAINT collection_routes_collector_id_foreign FOREIGN KEY (collector_id) REFERENCES personals(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL kredia_add_column('clients', 'collection_route_id', "BIGINT UNSIGNED NULL");
CALL kredia_add_column('clients', 'route_position', "SMALLINT UNSIGNED NULL");
CALL kredia_add_fk('clients', 'clients_collection_route_id_foreign', 'FOREIGN KEY (collection_route_id) REFERENCES collection_routes(id) ON DELETE SET NULL');

-- ── Auditoría ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id    BIGINT UNSIGNED NULL,
    user_id       BIGINT UNSIGNED NULL,
    action        VARCHAR(50) NOT NULL,
    subject_type  VARCHAR(150) NOT NULL,
    subject_id    BIGINT UNSIGNED NOT NULL,
    description   VARCHAR(500) NULL,
    properties    JSON NULL,
    ip            VARCHAR(45) NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY activity_logs_company_id_index (company_id),
    KEY activity_logs_user_id_index (user_id),
    KEY activity_logs_subject_type_subject_id_index (subject_type, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Tope de tasa por empresa ────────────────────────────────────────
CALL kredia_add_column('companies', 'max_interest_rate', "DECIMAL(8,2) NULL AFTER currency_symbol");

-- ── Permisos nuevos (se otorgan a los roles que ya tenían el permiso relacionado) ──
INSERT INTO permissions (slug, name, module, created_at, updated_at)
SELECT 'collections.view', 'Ver cobranza y rutas', 'collections', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'collections.view');
INSERT INTO permissions (slug, name, module, created_at, updated_at)
SELECT 'collections.manage', 'Gestionar rutas y cobradores', 'collections', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'collections.manage');
INSERT INTO permissions (slug, name, module, created_at, updated_at)
SELECT 'payments.void', 'Anular pagos de préstamos', 'payments', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'payments.void');
INSERT INTO permissions (slug, name, module, created_at, updated_at)
SELECT 'loans.refinance', 'Refinanciar préstamos', 'loans', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE slug = 'loans.refinance');

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT rp.role_id, (SELECT id FROM permissions WHERE slug = 'collections.view')
FROM role_permission rp JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'loans.view';
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT rp.role_id, (SELECT id FROM permissions WHERE slug = 'collections.manage')
FROM role_permission rp JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'loans.edit';
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT rp.role_id, (SELECT id FROM permissions WHERE slug = 'payments.void')
FROM role_permission rp JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'loans.delete';
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT rp.role_id, (SELECT id FROM permissions WHERE slug = 'loans.refinance')
FROM role_permission rp JOIN permissions p ON p.id = rp.permission_id WHERE p.slug = 'loans.edit';

DROP PROCEDURE IF EXISTS kredia_add_column;
DROP PROCEDURE IF EXISTS kredia_add_fk;
DROP PROCEDURE IF EXISTS kredia_add_index;

INSERT INTO migrations (migration, batch)
SELECT '2026_11_18_000003_installment_loans_collections', 3 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_18_000003_installment_loans_collections');
