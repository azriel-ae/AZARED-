-- =====================================================================
-- AZARED - Database Schema (Phase 1)
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Table: stores  (toko / cabang)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stores (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150)        NOT NULL,
    code          VARCHAR(30)         NOT NULL,
    address       VARCHAR(255)        NULL,
    phone         VARCHAR(30)         NULL,
    tax_id        VARCHAR(50)         NULL COMMENT 'NPWP toko',
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME            NULL,
    UNIQUE KEY uq_stores_code (code),
    KEY idx_stores_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: roles
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(50)   NOT NULL,
    slug          VARCHAR(50)   NOT NULL,
    description   VARCHAR(255)  NULL,
    is_system     TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'system roles cannot be deleted',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: permissions
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS permissions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(100)  NOT NULL COMMENT 'e.g. users.create',
    group_name    VARCHAR(50)   NOT NULL COMMENT 'e.g. users',
    description   VARCHAR(255)  NULL,
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_permissions_slug (slug),
    KEY idx_permissions_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: role_permissions (many-to-many)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS role_permissions (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id        INT UNSIGNED NOT NULL,
    permission_id  INT UNSIGNED NOT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name             VARCHAR(150)  NOT NULL,
    username              VARCHAR(50)   NOT NULL,
    email                 VARCHAR(150)  NULL,
    phone                 VARCHAR(30)   NULL,
    password_hash         VARCHAR(255)  NOT NULL COMMENT 'bcrypt/argon2 via password_hash()',
    status                ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    must_change_password  TINYINT(1)    NOT NULL DEFAULT 0,
    failed_login_attempts INT UNSIGNED  NOT NULL DEFAULT 0,
    locked_until          DATETIME      NULL,
    last_login_at         DATETIME      NULL,
    last_login_ip         VARCHAR(45)   NULL,
    created_by            BIGINT UNSIGNED NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at            DATETIME      NULL,
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status (status),
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: user_roles (many-to-many; UI currently assigns one primary role)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_roles (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    role_id     INT UNSIGNED    NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_role (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: user_store_access (which stores/branches a user may access)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_store_access (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED NOT NULL,
    store_id    BIGINT UNSIGNED NOT NULL,
    is_primary  TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_store (user_id, store_id),
    CONSTRAINT fk_usa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_usa_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: login_logs (every login attempt, success or fail)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_logs (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            BIGINT UNSIGNED NULL,
    username_attempted VARCHAR(50)     NOT NULL,
    ip_address         VARCHAR(45)     NULL,
    user_agent         VARCHAR(255)    NULL,
    status             ENUM('success','failed') NOT NULL,
    reason             VARCHAR(100)    NULL COMMENT 'e.g. invalid_password, account_locked, account_inactive',
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_logs_user (user_id),
    KEY idx_login_logs_created (created_at),
    KEY idx_login_logs_ip (ip_address),
    CONSTRAINT fk_login_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: audit_logs (generic change/action trail across the whole app)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NULL COMMENT 'actor who performed the action',
    action        VARCHAR(100)    NOT NULL COMMENT 'e.g. user.create, user.update, user.disable',
    entity_type   VARCHAR(50)     NOT NULL COMMENT 'e.g. user, role, store',
    entity_id     BIGINT UNSIGNED NULL,
    old_values    JSON            NULL,
    new_values    JSON            NULL,
    ip_address    VARCHAR(45)     NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: password_resets
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       BIGINT UNSIGNED NOT NULL,
    token_hash    VARCHAR(255)    NOT NULL COMMENT 'hash of the reset token, never store raw token',
    expires_at    DATETIME        NOT NULL,
    used_at       DATETIME        NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_password_resets_user (user_id),
    CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table: sessions (DB-backed PHP sessions - required for serverless/Vercel)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    id             VARCHAR(128) PRIMARY KEY,
    user_id        BIGINT UNSIGNED NULL,
    ip_address     VARCHAR(45)     NULL,
    user_agent     VARCHAR(255)    NULL,
    payload        LONGTEXT        NOT NULL,
    last_activity  INT UNSIGNED    NOT NULL,
    expires_at     DATETIME        NOT NULL,
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_expires (expires_at),
    CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
