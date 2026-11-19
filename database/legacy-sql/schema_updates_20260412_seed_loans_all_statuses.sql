USE prestamos_db;

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET collation_connection = 'utf8mb4_unicode_ci';

START TRANSACTION;

-- ============================================================
-- Seed de ejemplos de prestamos por estado
-- Crea 2 ejemplos por cada estado:
-- pending, approved, active, overdue, defaulted, finished, cancelled
-- Incluye datos relacionados: clients, products, loans, loan_collaterals, loan_payments
-- ============================================================

SET @company_id := (SELECT id FROM companies ORDER BY id LIMIT 1);
SET @user_id := (SELECT id FROM users ORDER BY id LIMIT 1);

-- Sucursal y almacen base
SET @branch_id := (
    SELECT b.id
    FROM branches b
    WHERE b.company_id = @company_id
      AND b.active = 1
      AND b.deleted_at IS NULL
    ORDER BY b.id
    LIMIT 1
);

SET @warehouse_id := (
    SELECT b.warehouse_id
    FROM branches b
    WHERE b.id = @branch_id
    LIMIT 1
);

INSERT INTO warehouses (company_id, name, code, location, description, active, created_at, updated_at)
SELECT
    @company_id,
    'Almacen Seed Estados',
    'WH-SEED-STATUS',
    'Principal',
    'Almacen auxiliar para datos seed de estados',
    1,
    NOW(),
    NOW()
WHERE @branch_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM warehouses WHERE code = 'WH-SEED-STATUS');

SET @warehouse_id := COALESCE(
    @warehouse_id,
    (SELECT w.id FROM warehouses w WHERE w.company_id = @company_id AND w.deleted_at IS NULL ORDER BY w.id LIMIT 1),
    (SELECT id FROM warehouses WHERE code = 'WH-SEED-STATUS' LIMIT 1)
);

INSERT INTO branches (company_id, warehouse_id, name, code, phone, email, address, manager_name, active, created_at, updated_at)
SELECT
    @company_id,
    @warehouse_id,
    'Sucursal Seed Estados',
    'BR-SEED-STATUS',
    NULL,
    NULL,
    'Sucursal para datos seed de estados',
    NULL,
    1,
    NOW(),
    NOW()
WHERE @branch_id IS NULL
  AND NOT EXISTS (SELECT 1 FROM branches WHERE code = 'BR-SEED-STATUS');

SET @branch_id := COALESCE(@branch_id, (SELECT id FROM branches WHERE code = 'BR-SEED-STATUS' LIMIT 1));

-- Categorias y reglas base
SET @cat_1 := COALESCE((SELECT id FROM credit_categories WHERE id = 1 LIMIT 1), (SELECT id FROM credit_categories WHERE company_id = @company_id ORDER BY id LIMIT 1));
SET @cat_2 := COALESCE((SELECT id FROM credit_categories WHERE id = 2 LIMIT 1), @cat_1);
SET @cat_3 := COALESCE((SELECT id FROM credit_categories WHERE id = 3 LIMIT 1), @cat_1);
SET @cat_4 := COALESCE((SELECT id FROM credit_categories WHERE id = 4 LIMIT 1), @cat_1);

CREATE TEMPORARY TABLE tmp_status_samples (
    sample_code VARCHAR(40) PRIMARY KEY,
    loan_status VARCHAR(20) NOT NULL,
    penalty_status VARCHAR(20) NOT NULL,
    collateral_status VARCHAR(20) NOT NULL,
    category_pref TINYINT NOT NULL,
    client_name VARCHAR(150) NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    product_sku VARCHAR(80) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    interest_rate INT NOT NULL,
    term_months INT NOT NULL,
    total_paid DECIMAL(12,2) NOT NULL,
    start_days_ago INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_status_samples VALUES
('PENDING_A',   'pending',   'normal',    'retained', 1, 'Cliente Estado Pending A',   'Producto Pending A',   'SEED-PENDING-A',   500.00, 10, 3,   0.00,   3),
('PENDING_B',   'pending',   'normal',    'retained', 2, 'Cliente Estado Pending B',   'Producto Pending B',   'SEED-PENDING-B',   420.00,  8, 2,   0.00,   2),
('APPROVED_A',  'approved',  'normal',    'retained', 3, 'Cliente Estado Approved A',  'Producto Approved A',  'SEED-APPROVED-A',  750.00, 12, 4,   0.00,   6),
('APPROVED_B',  'approved',  'normal',    'retained', 4, 'Cliente Estado Approved B',  'Producto Approved B',  'SEED-APPROVED-B',  640.00,  9, 3,   0.00,   5),
('ACTIVE_A',    'active',    'normal',    'retained', 1, 'Cliente Estado Active A',    'Producto Active A',    'SEED-ACTIVE-A',    900.00, 11, 4, 180.00,  25),
('ACTIVE_B',    'active',    'normal',    'retained', 2, 'Cliente Estado Active B',    'Producto Active B',    'SEED-ACTIVE-B',    680.00,  7, 3, 120.00,  22),
('OVERDUE_A',   'overdue',   'overdue',   'stored',   3, 'Cliente Estado Overdue A',   'Producto Overdue A',   'SEED-OVERDUE-A',   820.00, 10, 2, 100.00, 130),
('OVERDUE_B',   'overdue',   'overdue',   'stored',   4, 'Cliente Estado Overdue B',   'Producto Overdue B',   'SEED-OVERDUE-B',   560.00,  8, 2,  80.00, 120),
('DEFAULTED_A', 'defaulted', 'defaulted', 'sellable', 1, 'Cliente Estado Defaulted A', 'Producto Defaulted A', 'SEED-DEFAULTED-A', 880.00, 12, 2,  60.00, 180),
('DEFAULTED_B', 'defaulted', 'defaulted', 'sellable', 2, 'Cliente Estado Defaulted B', 'Producto Defaulted B', 'SEED-DEFAULTED-B', 740.00, 10, 2,  40.00, 170),
('FINISHED_A',  'finished',  'normal',    'released', 3, 'Cliente Estado Finished A',  'Producto Finished A',  'SEED-FINISHED-A',  610.00,  9, 3,   0.00, 110),
('FINISHED_B',  'finished',  'normal',    'released', 4, 'Cliente Estado Finished B',  'Producto Finished B',  'SEED-FINISHED-B',  530.00,  7, 2,   0.00,  95),
('CANCELLED_A', 'cancelled', 'normal',    'retained', 1, 'Cliente Estado Cancelled A', 'Producto Cancelled A', 'SEED-CANCELLED-A', 470.00,  8, 3,   0.00,  12),
('CANCELLED_B', 'cancelled', 'normal',    'retained', 2, 'Cliente Estado Cancelled B', 'Producto Cancelled B', 'SEED-CANCELLED-B', 390.00,  6, 2,   0.00,  10);

-- Limpieza de seeds anteriores
DELETE FROM loan_payments
WHERE loan_id IN (
    SELECT id FROM loans WHERE notes LIKE '[SEED-STATUS] %'
);

DELETE FROM loan_collaterals
WHERE loan_id IN (
    SELECT id FROM loans WHERE notes LIKE '[SEED-STATUS] %'
);

DELETE FROM loans
WHERE notes LIKE '[SEED-STATUS] %';

-- Clients seed
INSERT INTO clients (company_id, name, id_number, phone, email, address, notes, active, created_at, updated_at)
SELECT
    @company_id,
    t.client_name,
    CONCAT('SEED-', t.sample_code),
    CONCAT('04', LPAD(FLOOR(RAND() * 99999999), 8, '0')),
    CONCAT(LOWER(REPLACE(t.sample_code, '_', '.')), '@seed.local'),
    'Direccion de prueba',
    CONCAT('[SEED-STATUS] ', t.sample_code),
    1,
    NOW(),
    NOW()
FROM tmp_status_samples t
WHERE NOT EXISTS (
    SELECT 1
    FROM clients c
    WHERE c.company_id = @company_id
    AND c.id_number COLLATE utf8mb4_unicode_ci = CONCAT('SEED-', t.sample_code) COLLATE utf8mb4_unicode_ci
);

-- Products seed
INSERT INTO products (
    company_id,
    credit_category_id,
    name,
    sku,
    description,
    unit,
    cost,
    price,
    active,
    created_at,
    updated_at
)
SELECT
    @company_id,
    CASE t.category_pref
        WHEN 1 THEN @cat_1
        WHEN 2 THEN @cat_2
        WHEN 3 THEN @cat_3
        ELSE @cat_4
    END,
    t.product_name,
    t.product_sku,
    CONCAT('[SEED-STATUS] ', t.sample_code),
    'unidad',
    ROUND(t.amount * 0.55, 2),
    ROUND(t.amount * 0.90, 2),
    1,
    NOW(),
    NOW()
FROM tmp_status_samples t
WHERE NOT EXISTS (
    SELECT 1
    FROM products p
    WHERE p.sku COLLATE utf8mb4_unicode_ci = t.product_sku COLLATE utf8mb4_unicode_ci
);

-- Loans seed (2 por estado)
INSERT INTO loans (
    company_id,
    branch_id,
    product_id,
    credit_category_id,
    credit_category_rule_id,
    client_id,
    created_by,
    approved_by,
    amount,
    current_capital,
    interest_rate,
    term_months,
    monthly_payment,
    total_to_pay,
    total_paid,
    start_date,
    end_date,
    notes,
    status,
    penalty_status,
    penalty_amount,
    last_interest_generated_at,
    interest_period,
    approved_at,
    created_at,
    updated_at
)
SELECT
    @company_id,
    @branch_id,
    p.id,
    p.credit_category_id,
    (
        SELECT r.id
        FROM credit_category_rules r
        WHERE r.credit_category_id = p.credit_category_id
          AND r.active = 1
        ORDER BY r.interest_rate, r.id
        LIMIT 1
    ) AS credit_category_rule_id,
    c.id,
    @user_id,
    CASE WHEN t.loan_status = 'pending' THEN NULL ELSE @user_id END,
    t.amount,
    CASE
        WHEN t.loan_status = 'finished' THEN 0
        ELSE t.amount
    END,
    t.interest_rate,
    t.term_months,
    ROUND((t.amount * (1 + t.interest_rate / 100)) / t.term_months, 2),
    ROUND(t.amount * (1 + t.interest_rate / 100), 2),
    CASE
        WHEN t.loan_status = 'finished' THEN ROUND(t.amount * (1 + t.interest_rate / 100), 2)
        ELSE t.total_paid
    END,
    DATE_SUB(CURDATE(), INTERVAL t.start_days_ago DAY),
    DATE_ADD(DATE_SUB(CURDATE(), INTERVAL t.start_days_ago DAY), INTERVAL t.term_months MONTH),
    CONCAT('[SEED-STATUS] ', t.sample_code),
    t.loan_status,
    t.penalty_status,
    CASE WHEN t.loan_status = 'defaulted' THEN ROUND(t.amount * 0.06, 2) ELSE 0 END,
    CASE
        WHEN t.loan_status IN ('overdue', 'defaulted') THEN DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ELSE NULL
    END,
    'monthly',
    CASE WHEN t.loan_status = 'pending' THEN NULL ELSE NOW() END,
    NOW(),
    NOW()
FROM tmp_status_samples t
JOIN clients c
  ON c.company_id = @company_id
 AND c.id_number COLLATE utf8mb4_unicode_ci = CONCAT('SEED-', t.sample_code) COLLATE utf8mb4_unicode_ci
JOIN products p
    ON p.sku COLLATE utf8mb4_unicode_ci = t.product_sku COLLATE utf8mb4_unicode_ci;

-- Collaterals relacionados
INSERT INTO loan_collaterals (
    company_id,
    loan_id,
    branch_id,
    warehouse_id,
    product_id,
    inventory_movement_id,
    status,
    retained_at,
    expected_release_date,
    sellable_at,
    released_at,
    notes,
    created_at,
    updated_at
)
SELECT
    l.company_id,
    l.id,
    l.branch_id,
    @warehouse_id,
    l.product_id,
    NULL,
    t.collateral_status,
    l.created_at,
    l.end_date,
    CASE WHEN t.collateral_status = 'sellable' THEN NOW() ELSE NULL END,
    CASE WHEN t.collateral_status = 'released' THEN NOW() ELSE NULL END,
    CONCAT('[SEED-STATUS] ', t.sample_code),
    NOW(),
    NOW()
FROM loans l
JOIN tmp_status_samples t
    ON l.notes COLLATE utf8mb4_unicode_ci = CONCAT('[SEED-STATUS] ', t.sample_code) COLLATE utf8mb4_unicode_ci;

-- Payments para ejemplos con monto abonado
INSERT INTO loan_payments (
    loan_id,
    registered_by,
    amount,
    payment_type,
    capital,
    interest,
    payment_date,
    payment_method,
    reference,
    notes,
    created_at,
    updated_at
)
SELECT
    l.id,
    @user_id,
    l.total_paid,
    'mixed',
    ROUND(l.total_paid * 0.70, 2),
    ROUND(l.total_paid * 0.30, 2),
    CURDATE(),
    'efectivo',
    CONCAT('SEED-PAY-', l.id),
    CONCAT('[SEED-STATUS] Pago inicial para ', t.sample_code),
    NOW(),
    NOW()
FROM loans l
JOIN tmp_status_samples t
    ON l.notes COLLATE utf8mb4_unicode_ci = CONCAT('[SEED-STATUS] ', t.sample_code) COLLATE utf8mb4_unicode_ci
WHERE l.total_paid > 0;

DROP TEMPORARY TABLE IF EXISTS tmp_status_samples;

COMMIT;
