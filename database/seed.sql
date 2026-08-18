-- =====================================================================
-- AZARED - Seed Data (Phase 1)
-- Run this AFTER schema.sql
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Roles
-- ---------------------------------------------------------------------
INSERT INTO roles (name, slug, description, is_system) VALUES
    ('Admin',        'admin',        'Akses penuh ke seluruh sistem',           1),
    ('Owner',        'owner',        'Pemilik bisnis, akses hampir penuh',      1),
    ('Manager',      'manager',      'Manajer operasional toko',                1),
    ('Cashier',      'cashier',      'Kasir, fokus pada transaksi penjualan',   1),
    ('Accountant',   'accountant',   'Akuntan, fokus pada laporan keuangan',    1),
    ('Tax Officer',  'tax_officer',  'Petugas pajak, fokus pada laporan pajak', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- Permissions
-- ---------------------------------------------------------------------
INSERT INTO permissions (slug, group_name, description) VALUES
    ('dashboard.view',   'dashboard', 'Melihat dashboard'),
    ('products.view',    'products',  'Melihat data produk'),
    ('products.create',  'products',  'Menambah produk'),
    ('products.edit',    'products',  'Mengubah produk'),
    ('products.delete',  'products',  'Menghapus produk'),
    ('inventory.view',   'inventory', 'Melihat stok'),
    ('inventory.adjust', 'inventory', 'Menyesuaikan stok'),
    ('sales.view',       'sales',     'Melihat transaksi penjualan'),
    ('sales.create',     'sales',     'Membuat transaksi penjualan'),
    ('sales.cancel',     'sales',     'Membatalkan transaksi penjualan'),
    ('purchases.view',   'purchases', 'Melihat data pembelian'),
    ('purchases.create', 'purchases', 'Membuat pembelian'),
    ('customers.view',   'customers', 'Melihat data pelanggan'),
    ('suppliers.view',   'suppliers', 'Melihat data supplier'),
    ('reports.view',     'reports',   'Melihat laporan umum'),
    ('finance.view',     'finance',   'Melihat laporan keuangan'),
    ('tax.view',         'tax',       'Melihat laporan perpajakan'),
    ('users.view',       'users',     'Melihat data pengguna'),
    ('users.create',     'users',     'Membuat akun pengguna'),
    ('users.edit',       'users',     'Mengubah akun pengguna'),
    ('users.delete',     'users',     'Menonaktifkan/menghapus pengguna'),
    ('settings.view',    'settings',  'Melihat pengaturan toko'),
    ('settings.edit',    'settings',  'Mengubah pengaturan toko'),
    ('audit.view',       'audit',     'Melihat audit log')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ---------------------------------------------------------------------
-- Role <-> Permission mapping
-- ---------------------------------------------------------------------

-- ADMIN: everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- OWNER: everything except deleting users (still needs admin for that) -- here we give full access too,
-- adjust as your business rules require.
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug = 'owner'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- MANAGER: operational, no user deletion, no settings edit
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'manager'
  AND p.slug IN (
    'dashboard.view','products.view','products.create','products.edit',
    'inventory.view','inventory.adjust',
    'sales.view','sales.create','sales.cancel',
    'purchases.view','purchases.create',
    'customers.view','suppliers.view',
    'reports.view','users.view','settings.view'
  )
ON DUPLICATE KEY UPDATE role_id = role_id;

-- CASHIER: only POS-related
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'cashier'
  AND p.slug IN (
    'dashboard.view','products.view','inventory.view',
    'sales.view','sales.create','customers.view'
  )
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ACCOUNTANT: financial reports
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'accountant'
  AND p.slug IN (
    'dashboard.view','reports.view','finance.view',
    'sales.view','purchases.view'
  )
ON DUPLICATE KEY UPDATE role_id = role_id;

-- TAX_OFFICER: tax reports
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'tax_officer'
  AND p.slug IN (
    'dashboard.view','tax.view','reports.view'
  )
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ---------------------------------------------------------------------
-- Default store
-- ---------------------------------------------------------------------
INSERT INTO stores (name, code, address, phone, status) VALUES
    ('AZARED Pusat', 'STORE-001', 'Alamat toko pusat', '0800-0000-000', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ---------------------------------------------------------------------
-- Default ADMIN user
-- Username : admin
-- Password : Azared#2026!   <-- CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN
-- The hash below was generated with PHP's password_hash() (bcrypt, cost 10)
-- ---------------------------------------------------------------------
INSERT INTO users (full_name, username, email, password_hash, status, must_change_password)
VALUES (
    'Administrator AZARED',
    'admin',
    'admin@azared.local',
    '$2b$10$gbm4pZydV7Hy.lqgIG1KmeC1p5nui5sJlmOw2n74gQmYCjgjwy1wi',
    'active',
    1
)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id FROM users u CROSS JOIN roles r
WHERE u.username = 'admin' AND r.slug = 'admin'
ON DUPLICATE KEY UPDATE user_id = user_id;

INSERT INTO user_store_access (user_id, store_id, is_primary)
SELECT u.id, s.id, 1 FROM users u CROSS JOIN stores s
WHERE u.username = 'admin' AND s.code = 'STORE-001'
ON DUPLICATE KEY UPDATE is_primary = 1;
