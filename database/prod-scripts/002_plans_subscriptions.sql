-- =====================================================================
-- 002 · Planes y suscripciones (SaaS)
-- Reejecutable. Equivale a la migración 2026_11_18_000002_create_plans_and_subscriptions_tables
-- Las empresas existentes quedan en el plan "Empresa", activas y SIN vencimiento.
-- =====================================================================

CREATE TABLE IF NOT EXISTS plans (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL,
    description     VARCHAR(255) NULL,
    price           DECIMAL(10,2) NOT NULL DEFAULT 0,
    billing_period  ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    trial_days      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_users       INT UNSIGNED NULL,
    max_branches    INT UNSIGNED NULL,
    max_products    INT UNSIGNED NULL,
    max_loans       INT UNSIGNED NULL,
    features        JSON NULL,
    active          TINYINT(1) NOT NULL DEFAULT 1,
    sort_order      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY plans_slug_unique (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id             BIGINT UNSIGNED NOT NULL,
    plan_id                BIGINT UNSIGNED NOT NULL,
    status                 ENUM('trial','active','past_due','suspended','cancelled') NOT NULL DEFAULT 'trial',
    trial_ends_at          TIMESTAMP NULL,
    current_period_end     TIMESTAMP NULL,
    grace_days             SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    notes                  TEXT NULL,
    created_by             BIGINT UNSIGNED NULL,
    max_users_override     INT UNSIGNED NULL,
    max_branches_override  INT UNSIGNED NULL,
    max_products_override  INT UNSIGNED NULL,
    max_loans_override     INT UNSIGNED NULL,
    features_override      JSON NULL,
    created_at             TIMESTAMP NULL,
    updated_at             TIMESTAMP NULL,
    UNIQUE KEY subscriptions_company_id_unique (company_id),
    KEY subscriptions_status_index (status),
    CONSTRAINT subscriptions_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT subscriptions_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES plans(id),
    CONSTRAINT subscriptions_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plans (slug, name, description, price, billing_period, trial_days, max_users, max_branches, max_products, max_loans, features, active, sort_order, created_at, updated_at)
SELECT 'basico', 'Básico', 'Empeño con 1 sucursal y app móvil.', 0, 'monthly', 15, 3, 1, 500, 300,
       '["pawn","mobile_app"]', 1, 1, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE slug = 'basico');

INSERT INTO plans (slug, name, description, price, billing_period, trial_days, max_users, max_branches, max_products, max_loans, features, active, sort_order, created_at, updated_at)
SELECT 'pro', 'Pro', 'Empeño + cuotas, cobranza, inventario y ventas.', 0, 'monthly', 15, 10, 3, 5000, NULL,
       '["pawn","installments","collections","inventory","sales","purchases","catalogs","mobile_app"]', 1, 2, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE slug = 'pro');

INSERT INTO plans (slug, name, description, price, billing_period, trial_days, max_users, max_branches, max_products, max_loans, features, active, sort_order, created_at, updated_at)
SELECT 'empresa', 'Empresa', 'Todo incluido y sin límites.', 0, 'monthly', 15, NULL, NULL, NULL, NULL,
       '["pawn","installments","collections","inventory","sales","purchases","catalogs","capital","notifications","mobile_app"]', 1, 3, NOW(), NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM plans WHERE slug = 'empresa');

-- Empresas existentes → plan Empresa, activas, sin vencimiento.
INSERT INTO subscriptions (company_id, plan_id, status, grace_days, notes, created_at, updated_at)
SELECT c.id, (SELECT id FROM plans WHERE slug = 'empresa'), 'active', 3,
       'Suscripción inicial (empresa existente antes del SaaS).', NOW(), NOW()
FROM companies c
WHERE NOT EXISTS (SELECT 1 FROM subscriptions s WHERE s.company_id = c.id);

INSERT INTO migrations (migration, batch)
SELECT '2026_11_18_000002_create_plans_and_subscriptions_tables', 3 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = '2026_11_18_000002_create_plans_and_subscriptions_tables');
