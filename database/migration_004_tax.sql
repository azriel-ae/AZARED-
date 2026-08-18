-- =====================================================================
-- AZARED - Migration 004: Modul Perpajakan
-- Run this AFTER schema.sql + seed.sql + migration_002_pos.sql + migration_003_finance.sql
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
--
-- IMPORTANT DISCLAIMER (also enforced in the UI/reports):
-- This module is an internal bookkeeping/reporting aid. It is NOT an
-- official government tax filing system and has NO integration with
-- DJP/e-Faktur or any other government system. Nomor faktur pajak
-- recorded here is a manual reference field only.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Table: taxes - the tax "definition" (name, code, type, inclusive/
-- exclusive, active status). The RATE itself lives in tax_rates so it
-- can change over time without rewriting history - never hardcoded.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS taxes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100)  NOT NULL,
    code           VARCHAR(30)   NOT NULL,
    tax_type       ENUM('ppn','pph','other') NOT NULL DEFAULT 'ppn',
    tax_inclusive  TINYINT(1)    NOT NULL DEFAULT 0,
    status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME      NULL,
    UNIQUE KEY uq_taxes_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: tax_rates - rate HISTORY per tax. Only one row per tax may have
-- effective_to = NULL (the currently active rate); changing the rate
-- closes the old row and opens a new one, so past transactions that
-- already snapshotted a rate are never affected retroactively.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tax_rates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tax_id          INT UNSIGNED    NOT NULL,
    rate            DECIMAL(6,3)    NOT NULL COMMENT 'percent, e.g. 11.000 = 11%',
    effective_from  DATE            NOT NULL,
    effective_to    DATE            NULL COMMENT 'NULL = currently active',
    created_by      BIGINT UNSIGNED NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tax_rates_tax (tax_id, effective_from),
    CONSTRAINT fk_tax_rates_tax FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE CASCADE,
    CONSTRAINT fk_tax_rates_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: tax_periods - bookkeeping periods (bulanan/tahunan) that can be
-- closed to prevent further backdated edits to tax invoice data.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tax_periods (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    period_type  ENUM('monthly','yearly') NOT NULL DEFAULT 'monthly',
    start_date   DATE         NOT NULL,
    end_date     DATE         NOT NULL,
    status       ENUM('open','closed') NOT NULL DEFAULT 'open',
    closed_by    BIGINT UNSIGNED NULL,
    closed_at    DATETIME     NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tax_periods_range (start_date, end_date),
    CONSTRAINT fk_tax_periods_user FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Link taxes to products (optional - a product without a tax_id still
-- uses its own tax_percent for pricing as before; tax_id is additional
-- tagging so the tax reports can group by tax name/code).
-- ---------------------------------------------------------------------
ALTER TABLE products
    ADD COLUMN tax_id INT UNSIGNED NULL AFTER tax_percent,
    ADD CONSTRAINT fk_products_tax FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE SET NULL;

-- Purchases collect tax per line from the form (not from the product
-- record), so purchase_items needs its own tax_id reference.
ALTER TABLE purchase_items
    ADD COLUMN tax_id INT UNSIGNED NULL AFTER tax_amount,
    ADD CONSTRAINT fk_purchase_items_tax FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- Table: tax_transactions - the authoritative, immutable-once-written
-- record every tax report reads from. One row per taxed line item.
-- tax_rate/tax_inclusive/taxable_amount/tax_amount are SNAPSHOTS taken
-- at the moment of the sale/purchase, so a later change to the tax's
-- rate never rewrites historical reports.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tax_transactions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tax_id              INT UNSIGNED    NULL,
    tax_name            VARCHAR(100)    NOT NULL COMMENT 'snapshot of tax name at time of transaction',
    tax_rate            DECIMAL(6,3)    NOT NULL,
    taxable_amount       DECIMAL(15,2)   NOT NULL COMMENT 'DPP - dasar pengenaan pajak',
    tax_amount          DECIMAL(15,2)   NOT NULL,
    tax_type            ENUM('output','input') NOT NULL COMMENT 'output = pajak keluaran (penjualan), input = pajak masukan (pembelian)',
    tax_inclusive       TINYINT(1)      NOT NULL DEFAULT 0,
    transaction_type    ENUM('sale','purchase') NOT NULL,
    transaction_id      BIGINT UNSIGNED NOT NULL,
    store_id            BIGINT UNSIGNED NULL,
    -- Manual tax-invoice reference fields only - NOT synced with any
    -- government system. See disclaimer at the top of this file.
    invoice_no          VARCHAR(60)     NULL,
    invoice_date         DATE            NULL,
    invoice_status       ENUM('none','draft','issued') NOT NULL DEFAULT 'none',
    created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tt_type_ref (transaction_type, transaction_id),
    KEY idx_tt_tax_type_date (tax_type, created_at),
    KEY idx_tt_store (store_id),
    CONSTRAINT fk_tt_tax FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE SET NULL,
    CONSTRAINT fk_tt_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Customer & supplier tax data
-- ---------------------------------------------------------------------
ALTER TABLE customers
    ADD COLUMN legal_name  VARCHAR(150) NULL AFTER name,
    ADD COLUMN tax_status  ENUM('pkp','non_pkp') NULL AFTER nik,
    ADD COLUMN tax_address VARCHAR(255) NULL AFTER tax_status;

ALTER TABLE suppliers
    ADD COLUMN legal_name  VARCHAR(150) NULL AFTER name,
    ADD COLUMN nik         VARCHAR(20)  NULL AFTER npwp,
    ADD COLUMN tax_status  ENUM('pkp','non_pkp') NULL AFTER nik,
    ADD COLUMN tax_address VARCHAR(255) NULL AFTER tax_status;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Seed: default PPN 11% tax + its current rate (a common Indonesian
-- default at the time of writing - fully editable from /tax/settings,
-- never hardcoded into application logic).
-- ---------------------------------------------------------------------
INSERT INTO taxes (name, code, tax_type, tax_inclusive, status) VALUES
    ('PPN', 'PPN11', 'ppn', 0, 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO tax_rates (tax_id, rate, effective_from, effective_to)
SELECT t.id, 11.000, '2022-04-01', NULL FROM taxes t
WHERE t.code = 'PPN11'
  AND NOT EXISTS (SELECT 1 FROM tax_rates tr WHERE tr.tax_id = t.id);

-- =====================================================================
-- Permissions (Phase 4)
-- =====================================================================
INSERT INTO permissions (slug, group_name, description) VALUES
    ('tax.view',     'tax', 'Melihat dashboard & data perpajakan'),
    ('tax.manage',   'tax', 'Mengelola data transaksi pajak (nomor faktur, status, periode)'),
    ('tax.settings', 'tax', 'Mengelola pengaturan jenis pajak & tarif'),
    ('tax.report',   'tax', 'Melihat & mengekspor laporan pajak')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ADMIN & OWNER: everything (re-run cross join so the new permissions above are included)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('admin','owner')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- MANAGER: read-only visibility into tax position and reports
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'manager' AND p.slug IN ('tax.view','tax.report')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ACCOUNTANT: full tax module access
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'accountant' AND p.slug IN ('tax.view','tax.manage','tax.settings','tax.report')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- TAX_OFFICER: this role exists specifically for tax work - full access
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'tax_officer' AND p.slug IN ('tax.view','tax.manage','tax.settings','tax.report')
ON DUPLICATE KEY UPDATE role_id = role_id;
