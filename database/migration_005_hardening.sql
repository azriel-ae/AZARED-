-- =====================================================================
-- AZARED - Migration 005: Security & Performance Hardening
-- Run this AFTER schema.sql + seed.sql + migration_002/003/004
--
-- This migration is purely additive (indexes + one permission grant) -
-- it changes no application data and is safe to run on a live database.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Composite indexes for the report/dashboard query patterns introduced
-- in Phase 2-4 (filtering by status + a date range together). The
-- existing single-column indexes on these tables still work, but a
-- composite index lets MySQL satisfy the whole WHERE clause from the
-- index alone instead of scanning every row of one status/date.
-- ---------------------------------------------------------------------
ALTER TABLE sales
    ADD INDEX idx_sales_status_created (status, created_at);

ALTER TABLE purchases
    ADD INDEX idx_purchases_status_date (status, purchase_date);

-- Product::inventoryReport() runs a correlated subquery per product
-- filtering stock_movements by (product_id, created_at range) - this
-- composite lets that resolve as a single index range scan.
ALTER TABLE stock_movements
    ADD INDEX idx_sm_product_created (product_id, created_at);

-- login_logs.username_attempted had no index at all; anything that ever
-- lists/searches login attempts by username (e.g. a future security
-- review screen) would otherwise force a full table scan.
ALTER TABLE login_logs
    ADD INDEX idx_login_logs_username (username_attempted, created_at);

-- ---------------------------------------------------------------------
-- audit.view existed as a seeded permission since Phase 1 but had no
-- page behind it until this hardening pass added /audit. Admin/Owner
-- already have it via their blanket grant; extend read access to
-- Accountant, who is best placed to review who changed tax/finance
-- settings.
-- ---------------------------------------------------------------------
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'accountant' AND p.slug = 'audit.view'
ON DUPLICATE KEY UPDATE role_id = role_id;
