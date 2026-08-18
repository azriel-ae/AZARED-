-- =====================================================================
-- AZARED - Migration 003: Modul Keuangan & Laporan Keuangan
-- Run this AFTER schema.sql + seed.sql + migration_002_pos.sql
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- HPP (COGS) foundation: moving weighted-average cost per product,
-- and a snapshot of that cost on every sale line so historical margin
-- reports never drift even if the product's average cost changes later.
-- ---------------------------------------------------------------------
ALTER TABLE products
    ADD COLUMN avg_cost DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER cost_price;

-- Seed avg_cost from the existing cost_price so HPP is sensible from day 1.
UPDATE products SET avg_cost = cost_price WHERE avg_cost = 0;

ALTER TABLE sale_items
    ADD COLUMN cost_price DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER unit_price
    COMMENT 'snapshot of product avg_cost at the moment of sale - HPP basis, never recalculated retroactively';

-- ---------------------------------------------------------------------
-- Table: expense_categories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expense_categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    slug          VARCHAR(120)  NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME      NULL,
    UNIQUE KEY uq_expense_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: expenses (biaya operasional & lainnya)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS expenses (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    expense_no     VARCHAR(40)     NOT NULL,
    store_id       BIGINT UNSIGNED NULL,
    category_id    INT UNSIGNED    NOT NULL,
    description    VARCHAR(255)    NOT NULL,
    amount         DECIMAL(15,2)   NOT NULL,
    payment_method ENUM('cash','transfer','debit','credit','ewallet','qris','other') NOT NULL DEFAULT 'cash',
    expense_date   DATE            NOT NULL,
    notes          VARCHAR(500)    NULL,
    user_id        BIGINT UNSIGNED NOT NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME        NULL,
    UNIQUE KEY uq_expenses_no (expense_no),
    KEY idx_expenses_date (expense_date),
    KEY idx_expenses_category (category_id),
    KEY idx_expenses_store (store_id),
    CONSTRAINT fk_expenses_category FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE RESTRICT,
    CONSTRAINT fk_expenses_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    CONSTRAINT fk_expenses_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: cash_accounts (kas & bank - deliberately minimal, single-entry
-- balance tracking rather than full double-entry bookkeeping)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cash_accounts (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name              VARCHAR(100)   NOT NULL,
    type              ENUM('cash','bank') NOT NULL,
    account_number    VARCHAR(60)    NULL,
    opening_balance   DECIMAL(15,2)  NOT NULL DEFAULT 0,
    status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: cash_other_entries (pemasukan/pengeluaran kas lain-lain yang
-- bukan penjualan/pembelian/expense - mis. setoran modal, penarikan
-- pribadi, pelunasan piutang manual). Kept intentionally small.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cash_other_entries (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    account_id     INT UNSIGNED    NOT NULL,
    direction      ENUM('in','out') NOT NULL,
    amount         DECIMAL(15,2)   NOT NULL,
    description    VARCHAR(255)    NOT NULL,
    entry_date     DATE            NOT NULL,
    user_id        BIGINT UNSIGNED NOT NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_coe_date (entry_date),
    CONSTRAINT fk_coe_account FOREIGN KEY (account_id) REFERENCES cash_accounts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_coe_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Seed: default cash accounts
-- ---------------------------------------------------------------------
INSERT INTO cash_accounts (name, type, opening_balance, status) VALUES
    ('Kas Utama',  'cash', 0, 'active'),
    ('Bank Utama', 'bank', 0, 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- Seed: default expense categories
-- ---------------------------------------------------------------------
INSERT INTO expense_categories (name, slug, status) VALUES
    ('Listrik',        'listrik',        'active'),
    ('Internet',       'internet',       'active'),
    ('Transportasi',   'transportasi',   'active'),
    ('Gaji',           'gaji',           'active'),
    ('Sewa',           'sewa',           'active'),
    ('Perlengkapan',   'perlengkapan',   'active'),
    ('Operasional',    'operasional',    'active'),
    ('Lainnya',        'lainnya',        'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =====================================================================
-- Permissions (Phase 3)
-- =====================================================================
INSERT INTO permissions (slug, group_name, description) VALUES
    ('finance.view',      'finance', 'Melihat dashboard & laporan keuangan'),
    ('finance.manage',    'finance', 'Mengelola data keuangan (kas/bank/kategori biaya)'),
    ('expenses.view',     'expenses', 'Melihat data pengeluaran'),
    ('expenses.create',   'expenses', 'Menambah pengeluaran'),
    ('expenses.edit',     'expenses', 'Mengubah pengeluaran'),
    ('expenses.delete',   'expenses', 'Menghapus pengeluaran'),
    ('reports.sales',     'reports',  'Melihat laporan penjualan'),
    ('reports.purchase',  'reports',  'Melihat laporan pembelian'),
    ('reports.inventory', 'reports',  'Melihat laporan inventory'),
    ('reports.finance',   'reports',  'Melihat laporan laba rugi & cash flow')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ADMIN & OWNER: everything (re-run cross join so the new permissions above are included)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('admin','owner')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- MANAGER: day-to-day finance visibility + expense handling, no account/category management
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'manager'
  AND p.slug IN (
    'finance.view','expenses.view','expenses.create','expenses.edit',
    'reports.sales','reports.purchase','reports.inventory','reports.finance'
  )
ON DUPLICATE KEY UPDATE role_id = role_id;

-- CASHIER: can log small day-to-day expenses (e.g. galon air, parkir) but not view full finance
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'cashier'
  AND p.slug IN ('expenses.create')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ACCOUNTANT: full finance + report access, including managing categories/accounts
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'accountant'
  AND p.slug IN (
    'finance.view','finance.manage',
    'expenses.view','expenses.create','expenses.edit','expenses.delete',
    'reports.sales','reports.purchase','reports.inventory','reports.finance'
  )
ON DUPLICATE KEY UPDATE role_id = role_id;

-- TAX_OFFICER: finance + report visibility only (read-only)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'tax_officer'
  AND p.slug IN ('finance.view','reports.sales','reports.purchase','reports.finance')
ON DUPLICATE KEY UPDATE role_id = role_id;
