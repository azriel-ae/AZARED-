-- =====================================================================
-- AZARED - DEVELOPMENT SEED DATA (OPTIONAL)
-- =====================================================================
-- Run this AFTER schema.sql + seed.sql + migration_002..006.sql, on a
-- LOCAL / STAGING database only.
--
-- >>> DO NOT RUN THIS ON A PRODUCTION DATABASE. <<<
-- Every account below uses the same publicly-documented demo password
-- and every product/customer/supplier is fictional sample data, purely
-- so a fresh clone has something to click through immediately.
--
-- Demo login password for EVERY user in this file: Azared#2026!
-- (identical bcrypt hash to the admin user seeded in seed.sql)
-- CHANGE OR DELETE these accounts before ever exposing the app publicly.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- One demo user per system role (besides the `admin` user from seed.sql)
-- ---------------------------------------------------------------------
INSERT INTO users (full_name, username, email, password_hash, status, must_change_password) VALUES
    ('Owner Demo',      'owner',      'owner@azared.local',      '$2b$10$gbm4pZydV7Hy.lqgIG1KmeC1p5nui5sJlmOw2n74gQmYCjgjwy1wi', 'active', 1),
    ('Manager Demo',    'manager',    'manager@azared.local',    '$2b$10$gbm4pZydV7Hy.lqgIG1KmeC1p5nui5sJlmOw2n74gQmYCjgjwy1wi', 'active', 1),
    ('Kasir Demo',      'cashier',    'cashier@azared.local',    '$2b$10$gbm4pZydV7Hy.lqgIG1KmeC1p5nui5sJlmOw2n74gQmYCjgjwy1wi', 'active', 1),
    ('Akuntan Demo',    'accountant', 'accountant@azared.local', '$2b$10$gbm4pZydV7Hy.lqgIG1KmeC1p5nui5sJlmOw2n74gQmYCjgjwy1wi', 'active', 1),
    ('Petugas Pajak Demo', 'taxofficer', 'taxofficer@azared.local', '$2b$10$gbm4pZydV7Hy.lqgIG1KmeC1p5nui5sJlmOw2n74gQmYCjgjwy1wi', 'active', 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u JOIN roles r ON (
    (u.username = 'owner'      AND r.slug = 'owner') OR
    (u.username = 'manager'    AND r.slug = 'manager') OR
    (u.username = 'cashier'    AND r.slug = 'cashier') OR
    (u.username = 'accountant' AND r.slug = 'accountant') OR
    (u.username = 'taxofficer' AND r.slug = 'tax_officer')
)
ON DUPLICATE KEY UPDATE user_id = user_id;

-- ---------------------------------------------------------------------
-- Second store (for exercising multi-store: user access, per-store
-- transactions/reports filtering - see docs/AUDIT_REPORT.md for the
-- known limitation around per-store STOCK specifically).
-- ---------------------------------------------------------------------
INSERT INTO stores (name, code, address, phone, tax_id, status) VALUES
    ('AZARED Cabang Rungkut', 'STORE-002', 'Jl. Rungkut Industri No. 10, Surabaya', '0800-0000-002', '02.345.678.9-002.000', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Give every demo user access to both stores, primary = store 1
INSERT INTO user_store_access (user_id, store_id, is_primary)
SELECT u.id, s.id, IF(s.code = 'STORE-001', 1, 0)
FROM users u CROSS JOIN stores s
WHERE u.username IN ('owner','manager','cashier','accountant','taxofficer')
ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary);

-- ---------------------------------------------------------------------
-- Units & Categories
-- ---------------------------------------------------------------------
INSERT INTO units (name, symbol, status) VALUES
    ('Pieces', 'pcs', 'active'),
    ('Kilogram', 'kg', 'active'),
    ('Liter', 'ltr', 'active'),
    ('Dus', 'dus', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO categories (name, slug, status) VALUES
    ('Sembako', 'sembako', 'active'),
    ('Minuman', 'minuman', 'active'),
    ('Makanan Ringan', 'makanan-ringan', 'active'),
    ('Kebersihan Rumah Tangga', 'kebersihan-rumah-tangga', 'active'),
    ('Rokok', 'rokok', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- Products (fictional sample catalog)
-- ---------------------------------------------------------------------
INSERT INTO products (sku, name, category_id, unit_id, cost_price, sell_price, wholesale_price, wholesale_min_qty, stock, min_stock, tax_percent, tax_inclusive, status)
SELECT 'BRS-001', 'Beras Premium 5kg', c.id, u.id, 62000, 68000, 65000, 5, 120, 20, 0, 0, 'active'
FROM categories c, units u WHERE c.slug = 'sembako' AND u.symbol = 'dus'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (sku, name, category_id, unit_id, cost_price, sell_price, wholesale_price, wholesale_min_qty, stock, min_stock, tax_percent, tax_inclusive, status)
SELECT 'MYK-001', 'Minyak Goreng 2L', c.id, u.id, 32000, 36000, 34000, 6, 80, 15, 0, 0, 'active'
FROM categories c, units u WHERE c.slug = 'sembako' AND u.symbol = 'pcs'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (sku, name, category_id, unit_id, cost_price, sell_price, stock, min_stock, tax_percent, tax_inclusive, status)
SELECT 'GLA-001', 'Gula Pasir 1kg', c.id, u.id, 13500, 15500, 200, 30, 0, 0, 'active'
FROM categories c, units u WHERE c.slug = 'sembako' AND u.symbol = 'kg'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (sku, name, category_id, unit_id, cost_price, sell_price, stock, min_stock, tax_percent, tax_inclusive, status)
SELECT 'TEH-001', 'Teh Botol 350ml', c.id, u.id, 3500, 5000, 240, 48, 11, 1, 'active'
FROM categories c, units u WHERE c.slug = 'minuman' AND u.symbol = 'pcs'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (sku, name, category_id, unit_id, cost_price, sell_price, stock, min_stock, tax_percent, tax_inclusive, status)
SELECT 'KOP-001', 'Kopi Sachet 3in1 (renceng)', c.id, u.id, 9500, 12000, 60, 10, 11, 1, 'active'
FROM categories c, units u WHERE c.slug = 'minuman' AND u.symbol = 'pcs'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (sku, name, category_id, unit_id, cost_price, sell_price, stock, min_stock, tax_percent, tax_inclusive, status)
SELECT 'SNK-001', 'Keripik Kentang 68g', c.id, u.id, 6500, 9000, 15, 24, 11, 1, 'active'
FROM categories c, units u WHERE c.slug = 'makanan-ringan' AND u.symbol = 'pcs'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (sku, name, category_id, unit_id, cost_price, sell_price, stock, min_stock, tax_percent, tax_inclusive, status)
SELECT 'SBN-001', 'Sabun Cuci Piring 800ml', c.id, u.id, 11000, 14500, 90, 20, 11, 1, 'active'
FROM categories c, units u WHERE c.slug = 'kebersihan-rumah-tangga' AND u.symbol = 'pcs'
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO products (sku, name, category_id, unit_id, cost_price, sell_price, stock, min_stock, tax_percent, tax_inclusive, status)
SELECT 'RKK-001', 'Rokok Kretek Filter (bungkus)', c.id, u.id, 22000, 25000, 100, 20, 11, 1, 'active'
FROM categories c, units u WHERE c.slug = 'rokok' AND u.symbol = 'pcs'
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- Customers
-- ---------------------------------------------------------------------
INSERT INTO customers (code, name, phone, email, address, type, status) VALUES
    ('CUST-0001', 'Pelanggan Umum', NULL, NULL, NULL, 'retail', 'active'),
    ('CUST-0002', 'Toko Barokah Jaya', '0812-1111-2222', 'barokah.jaya@example.com', 'Jl. Kenjeran No. 5, Surabaya', 'wholesale', 'active'),
    ('CUST-0003', 'Siti Aminah', '0813-3333-4444', NULL, 'Jl. Mawar No. 12, Sidoarjo', 'member', 'active'),
    ('CUST-0004', 'CV Sumber Rezeki', '0851-5555-6666', 'purchasing@sumberrezeki.example', 'Jl. Ahmad Yani No. 88, Surabaya', 'corporate', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- Suppliers
-- ---------------------------------------------------------------------
INSERT INTO suppliers (code, name, contact_person, phone, email, address, npwp, status) VALUES
    ('SUP-0001', 'PT Sumber Pangan Nusantara', 'Budi Santoso', '021-5551234', 'sales@sumberpangan.example', 'Jl. Industri Raya No. 20, Jakarta', '01.234.567.8-901.000', 'active'),
    ('SUP-0002', 'CV Distribusi Minuman Sejahtera', 'Dewi Lestari', '031-7778899', 'order@dms.example', 'Jl. Raya Darmo No. 45, Surabaya', '02.345.678.9-012.000', 'active'),
    ('SUP-0003', 'UD Bersih Selalu', 'Agus Wijaya', '0821-9999-1111', NULL, 'Jl. Kebersihan No. 3, Sidoarjo', NULL, 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- Tax setup: standard Indonesian PPN 11%
-- ---------------------------------------------------------------------
INSERT INTO taxes (name, code, tax_type, tax_inclusive, status) VALUES
    ('PPN', 'PPN11', 'ppn', 1, 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO tax_rates (tax_id, rate, effective_from, effective_to)
SELECT t.id, 11.00, '2022-04-01', NULL FROM taxes t
WHERE t.code = 'PPN11'
  AND NOT EXISTS (SELECT 1 FROM tax_rates tr WHERE tr.tax_id = t.id AND tr.effective_to IS NULL);

-- Metode pembayaran (payment methods): tetap sebagai ENUM di tabel
-- `sale_payments.method` (cash, transfer, debit, credit, ewallet, qris,
-- other) - lihat database/migration_002_pos.sql - bukan tabel referensi
-- terpisah, sehingga tidak ada baris seed yang perlu ditambahkan di sini.
