-- =====================================================================
-- AZARED - Migration 006: Finalisasi (Role/Permission UI, Multi-Store
--          management, Pengaturan/Settings)
-- Run this AFTER schema.sql + seed.sql + migration_002..005.
-- Purely additive - safe to run against a live database with existing
-- data. Adds no destructive statement.
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Table: app_settings - small curated key/value store for the
-- Pengaturan (Settings) screen. See App\Models\AppSetting and
-- App\Controllers\SettingsController::ALLOWED_KEYS for the exact set
-- of keys the UI is allowed to write; this is NOT a free-form config
-- bag, every key is read somewhere in the app.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS app_settings (
    `key`       VARCHAR(100)  NOT NULL PRIMARY KEY,
    value       TEXT          NULL,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- New permissions: Role management screen (/roles) and Toko/Cabang
-- management screen (/settings/stores). Read-only permission catalog
-- viewing (/permissions) reuses the existing `settings.view` grant, so
-- no new permission is needed for it.
-- ---------------------------------------------------------------------
INSERT INTO permissions (slug, group_name, description) VALUES
    ('roles.manage',   'roles',  'Mengelola role dan izin akses'),
    ('stores.manage',  'stores', 'Mengelola data toko/cabang')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- Admin & Owner: full access to the new modules too (consistent with
-- how every other permission is granted to these two roles).
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('admin', 'owner') AND p.slug IN ('roles.manage', 'stores.manage')
ON DUPLICATE KEY UPDATE role_id = role_id;

SET FOREIGN_KEY_CHECKS = 1;
