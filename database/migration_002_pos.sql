-- =====================================================================
-- AZARED - Migration 002: POS / Produk / Stok / Pembelian / Pelanggan / Supplier
-- Run this AFTER schema.sql + seed.sql (Phase 1)
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Table: units (satuan)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS units (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(50)   NOT NULL,
    symbol        VARCHAR(10)   NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_units_symbol (symbol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: categories
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)  NOT NULL,
    slug          VARCHAR(120)  NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME      NULL,
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: products
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS products (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sku               VARCHAR(50)     NOT NULL,
    barcode           VARCHAR(80)     NULL,
    name              VARCHAR(180)    NOT NULL,
    category_id       INT UNSIGNED    NULL,
    unit_id           INT UNSIGNED    NULL,
    cost_price        DECIMAL(15,2)   NOT NULL DEFAULT 0,
    sell_price        DECIMAL(15,2)   NOT NULL DEFAULT 0,
    wholesale_price   DECIMAL(15,2)   NULL,
    wholesale_min_qty DECIMAL(15,3)   NULL,
    stock             DECIMAL(15,3)   NOT NULL DEFAULT 0,
    min_stock         DECIMAL(15,3)   NOT NULL DEFAULT 0,
    tax_percent       DECIMAL(5,2)    NOT NULL DEFAULT 0,
    tax_inclusive     TINYINT(1)      NOT NULL DEFAULT 0,
    status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    image_path        VARCHAR(255)    NULL,
    description       TEXT            NULL,
    allow_negative_stock TINYINT(1)   NOT NULL DEFAULT 0,
    created_by        BIGINT UNSIGNED NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at        DATETIME        NULL,
    UNIQUE KEY uq_products_sku (sku),
    UNIQUE KEY uq_products_barcode (barcode),
    KEY idx_products_category (category_id),
    KEY idx_products_unit (unit_id),
    KEY idx_products_status (status),
    KEY idx_products_name (name),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: stock_movements (single source of truth for every stock change)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_movements (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id     BIGINT UNSIGNED NOT NULL,
    store_id       BIGINT UNSIGNED NULL,
    type           ENUM('initial','purchase','sale','sale_return','purchase_return','adjustment','transfer_in','transfer_out') NOT NULL,
    quantity       DECIMAL(15,3)   NOT NULL COMMENT 'signed delta: positive = stock in, negative = stock out',
    before_stock   DECIMAL(15,3)   NOT NULL,
    after_stock    DECIMAL(15,3)   NOT NULL,
    reference_type VARCHAR(50)     NULL COMMENT 'e.g. sale, purchase, sales_return, purchase_return, transfer',
    reference_id   BIGINT UNSIGNED NULL,
    note           VARCHAR(255)    NULL,
    user_id        BIGINT UNSIGNED NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sm_product (product_id),
    KEY idx_sm_reference (reference_type, reference_id),
    KEY idx_sm_created (created_at),
    CONSTRAINT fk_sm_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_sm_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    CONSTRAINT fk_sm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: customers
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(30)     NOT NULL,
    name          VARCHAR(150)    NOT NULL,
    phone         VARCHAR(30)     NULL,
    email         VARCHAR(150)    NULL,
    address       VARCHAR(255)    NULL,
    npwp          VARCHAR(30)     NULL,
    nik           VARCHAR(20)     NULL,
    type          ENUM('retail','member','wholesale','corporate') NOT NULL DEFAULT 'retail',
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME        NULL,
    UNIQUE KEY uq_customers_code (code),
    KEY idx_customers_name (name),
    KEY idx_customers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: suppliers
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS suppliers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(30)     NOT NULL,
    name            VARCHAR(150)    NOT NULL,
    contact_person  VARCHAR(150)    NULL,
    phone           VARCHAR(30)     NULL,
    email           VARCHAR(150)    NULL,
    address         VARCHAR(255)    NULL,
    npwp            VARCHAR(30)     NULL,
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME        NULL,
    UNIQUE KEY uq_suppliers_code (code),
    KEY idx_suppliers_name (name),
    KEY idx_suppliers_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: invoice_sequences (safe, concurrency-proof invoice numbering)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS invoice_sequences (
    seq_key       VARCHAR(40)     NOT NULL PRIMARY KEY COMMENT 'e.g. SALE-20260811, PO-20260811',
    last_number   INT UNSIGNED    NOT NULL DEFAULT 0,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: sales (POS transactions)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no       VARCHAR(40)     NOT NULL,
    store_id         BIGINT UNSIGNED NULL,
    customer_id      BIGINT UNSIGNED NULL,
    user_id          BIGINT UNSIGNED NOT NULL COMMENT 'cashier',
    subtotal         DECIMAL(15,2)   NOT NULL DEFAULT 0,
    discount_type    ENUM('amount','percent') NOT NULL DEFAULT 'amount',
    discount_value   DECIMAL(15,2)   NOT NULL DEFAULT 0,
    discount_amount  DECIMAL(15,2)   NOT NULL DEFAULT 0,
    tax_amount       DECIMAL(15,2)   NOT NULL DEFAULT 0,
    grand_total      DECIMAL(15,2)   NOT NULL DEFAULT 0,
    paid_total       DECIMAL(15,2)   NOT NULL DEFAULT 0,
    change_amount    DECIMAL(15,2)   NOT NULL DEFAULT 0,
    status           ENUM('completed','held','cancelled','returned','partially_returned') NOT NULL DEFAULT 'completed',
    note             VARCHAR(255)    NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sales_invoice (invoice_no),
    KEY idx_sales_created (created_at),
    KEY idx_sales_customer (customer_id),
    KEY idx_sales_user (user_id),
    KEY idx_sales_status (status),
    CONSTRAINT fk_sales_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_items (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id          BIGINT UNSIGNED NOT NULL,
    product_id       BIGINT UNSIGNED NOT NULL,
    product_name     VARCHAR(180)    NOT NULL COMMENT 'snapshot at time of sale',
    sku              VARCHAR(50)     NOT NULL COMMENT 'snapshot at time of sale',
    qty              DECIMAL(15,3)   NOT NULL,
    unit_price       DECIMAL(15,2)   NOT NULL,
    discount_amount  DECIMAL(15,2)   NOT NULL DEFAULT 0,
    tax_amount       DECIMAL(15,2)   NOT NULL DEFAULT 0,
    subtotal         DECIMAL(15,2)   NOT NULL,
    returned_qty     DECIMAL(15,3)   NOT NULL DEFAULT 0,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_si_sale (sale_id),
    KEY idx_si_product (product_id),
    CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_si_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sale_payments (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id        BIGINT UNSIGNED NOT NULL,
    method         ENUM('cash','transfer','debit','credit','ewallet','qris','other') NOT NULL,
    amount         DECIMAL(15,2)   NOT NULL,
    reference_no   VARCHAR(80)     NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sp_sale (sale_id),
    CONSTRAINT fk_sp_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: held_carts (Hold Cart / Resume Cart on the POS screen)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS held_carts (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(30)     NOT NULL,
    store_id      BIGINT UNSIGNED NULL,
    user_id       BIGINT UNSIGNED NOT NULL,
    customer_id   BIGINT UNSIGNED NULL,
    note          VARCHAR(255)    NULL,
    cart_data     JSON            NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_held_carts_code (code),
    KEY idx_held_carts_user (user_id),
    CONSTRAINT fk_hc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_hc_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: purchases
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchases (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_no         VARCHAR(40)     NOT NULL COMMENT 'internal AZARED number',
    supplier_invoice_no VARCHAR(80)     NULL COMMENT 'supplier''s own invoice number',
    supplier_id         BIGINT UNSIGNED NOT NULL,
    store_id            BIGINT UNSIGNED NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    purchase_date       DATE            NOT NULL,
    subtotal            DECIMAL(15,2)   NOT NULL DEFAULT 0,
    discount_amount     DECIMAL(15,2)   NOT NULL DEFAULT 0,
    tax_amount          DECIMAL(15,2)   NOT NULL DEFAULT 0,
    total                DECIMAL(15,2)  NOT NULL DEFAULT 0,
    paid_total          DECIMAL(15,2)   NOT NULL DEFAULT 0,
    status              ENUM('draft','received','cancelled') NOT NULL DEFAULT 'received',
    note                VARCHAR(255)    NULL,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_purchases_no (purchase_no),
    KEY idx_purchases_supplier (supplier_id),
    KEY idx_purchases_status (status),
    KEY idx_purchases_date (purchase_date),
    CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_purchases_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL,
    CONSTRAINT fk_purchases_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_items (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_id      BIGINT UNSIGNED NOT NULL,
    product_id       BIGINT UNSIGNED NOT NULL,
    qty              DECIMAL(15,3)   NOT NULL,
    cost_price       DECIMAL(15,2)   NOT NULL,
    discount_amount  DECIMAL(15,2)   NOT NULL DEFAULT 0,
    tax_amount       DECIMAL(15,2)   NOT NULL DEFAULT 0,
    subtotal         DECIMAL(15,2)   NOT NULL,
    returned_qty     DECIMAL(15,3)   NOT NULL DEFAULT 0,
    KEY idx_pi_purchase (purchase_id),
    KEY idx_pi_product (product_id),
    CONSTRAINT fk_pi_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_payments (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_id    BIGINT UNSIGNED NOT NULL,
    method         ENUM('cash','transfer','debit','credit','ewallet','qris','other') NOT NULL,
    amount         DECIMAL(15,2)   NOT NULL,
    reference_no   VARCHAR(80)     NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pp_purchase (purchase_id),
    CONSTRAINT fk_pp_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: sales_returns (retur penjualan)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sales_returns (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_no      VARCHAR(40)     NOT NULL,
    sale_id        BIGINT UNSIGNED NOT NULL,
    user_id        BIGINT UNSIGNED NOT NULL,
    reason         VARCHAR(255)    NULL,
    refund_amount  DECIMAL(15,2)   NOT NULL DEFAULT 0,
    restock        TINYINT(1)      NOT NULL DEFAULT 1,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sr_no (return_no),
    KEY idx_sr_sale (sale_id),
    CONSTRAINT fk_sr_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_return_items (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sales_return_id  BIGINT UNSIGNED NOT NULL,
    sale_item_id     BIGINT UNSIGNED NOT NULL,
    product_id       BIGINT UNSIGNED NOT NULL,
    qty              DECIMAL(15,3)   NOT NULL,
    unit_price       DECIMAL(15,2)   NOT NULL,
    subtotal         DECIMAL(15,2)   NOT NULL,
    KEY idx_sri_return (sales_return_id),
    CONSTRAINT fk_sri_return FOREIGN KEY (sales_return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
    CONSTRAINT fk_sri_item FOREIGN KEY (sale_item_id) REFERENCES sale_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sri_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: purchase_returns (retur pembelian ke supplier)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_returns (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_no      VARCHAR(40)     NOT NULL,
    purchase_id    BIGINT UNSIGNED NOT NULL,
    user_id        BIGINT UNSIGNED NOT NULL,
    reason         VARCHAR(255)    NULL,
    refund_amount  DECIMAL(15,2)   NOT NULL DEFAULT 0,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pr_no (return_no),
    KEY idx_pr_purchase (purchase_id),
    CONSTRAINT fk_pr_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_return_items (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_return_id   BIGINT UNSIGNED NOT NULL,
    purchase_item_id     BIGINT UNSIGNED NOT NULL,
    product_id           BIGINT UNSIGNED NOT NULL,
    qty                  DECIMAL(15,3)   NOT NULL,
    cost_price           DECIMAL(15,2)   NOT NULL,
    subtotal             DECIMAL(15,2)   NOT NULL,
    KEY idx_pri_return (purchase_return_id),
    CONSTRAINT fk_pri_return FOREIGN KEY (purchase_return_id) REFERENCES purchase_returns(id) ON DELETE CASCADE,
    CONSTRAINT fk_pri_item FOREIGN KEY (purchase_item_id) REFERENCES purchase_items(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pri_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: stock_transfers (schema-ready for multi-store transfer)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_transfers (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_no    VARCHAR(40)     NOT NULL,
    from_store_id  BIGINT UNSIGNED NOT NULL,
    to_store_id    BIGINT UNSIGNED NOT NULL,
    user_id        BIGINT UNSIGNED NOT NULL,
    status         ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
    note           VARCHAR(255)    NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_st_no (transfer_no),
    CONSTRAINT fk_st_from FOREIGN KEY (from_store_id) REFERENCES stores(id) ON DELETE RESTRICT,
    CONSTRAINT fk_st_to FOREIGN KEY (to_store_id) REFERENCES stores(id) ON DELETE RESTRICT,
    CONSTRAINT fk_st_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id   BIGINT UNSIGNED NOT NULL,
    product_id    BIGINT UNSIGNED NOT NULL,
    qty           DECIMAL(15,3)   NOT NULL,
    KEY idx_sti_transfer (transfer_id),
    CONSTRAINT fk_sti_transfer FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    CONSTRAINT fk_sti_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- Additional permissions (Phase 2)
-- =====================================================================
INSERT INTO permissions (slug, group_name, description) VALUES
    ('categories.manage', 'products',  'Kelola kategori produk'),
    ('units.manage',      'products',  'Kelola satuan produk'),
    ('products.import',   'products',  'Import produk dari file'),
    ('products.export',   'products',  'Export produk ke file'),
    ('pos.access',        'sales',     'Akses halaman kasir/POS'),
    ('sales.return',      'sales',     'Melakukan retur penjualan'),
    ('purchases.edit',    'purchases', 'Mengubah data pembelian'),
    ('purchases.return',  'purchases', 'Melakukan retur pembelian ke supplier'),
    ('customers.create',  'customers', 'Menambah pelanggan'),
    ('customers.edit',    'customers', 'Mengubah data pelanggan'),
    ('suppliers.create',  'suppliers', 'Menambah supplier'),
    ('suppliers.edit',    'suppliers', 'Mengubah data supplier')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- ADMIN & OWNER: everything (re-run cross join so new permissions above are included)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.slug IN ('admin','owner')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- MANAGER: full operational access on the new modules
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'manager'
  AND p.slug IN (
    'categories.manage','units.manage','products.import','products.export',
    'pos.access','sales.return',
    'purchases.edit','purchases.return',
    'customers.create','customers.edit',
    'suppliers.create','suppliers.edit'
  )
ON DUPLICATE KEY UPDATE role_id = role_id;

-- CASHIER: POS + light customer management
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'cashier'
  AND p.slug IN ('pos.access','sales.return','customers.create','customers.edit')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ACCOUNTANT: read-only on purchases already covered; no changes needed here.

-- =====================================================================
-- Seed data: base units & categories so the POS is usable immediately
-- =====================================================================
INSERT INTO units (name, symbol, status) VALUES
    ('Pieces', 'pcs', 'active'),
    ('Box',    'box', 'active'),
    ('Kilogram', 'kg', 'active'),
    ('Gram',   'gr',  'active'),
    ('Liter',  'ltr', 'active'),
    ('Meter',  'mtr', 'active'),
    ('Pack',   'pack','active'),
    ('Lusin',  'lsn', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO categories (name, slug, status) VALUES
    ('Umum', 'umum', 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);
