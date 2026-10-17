-- ==============================================================================
-- TikPulse - Multi-Router MikroTik & Hotspot Voucher Management System
-- Database Schema: PostgreSQL 14+ Compatible
-- Mode: Idempotent DDL (Safe for manual execution via DBeaver, DataGrip, psql)
-- ==============================================================================

-- Enable UUID extension if not already available
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ------------------------------------------------------------------------------
-- 1. Table: users (Enterprise Administrator & Operator Accounts)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP WITH TIME ZONE NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'operator', -- 'superadmin', 'admin', 'operator'
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    avatar_url VARCHAR(500) NULL,
    preferred_locale VARCHAR(10) NOT NULL DEFAULT 'en',
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

-- ------------------------------------------------------------------------------
-- 2. Table: routers (MikroTik Router Profiles & Connection Configurations)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS routers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    host VARCHAR(255) NOT NULL,                 -- IP Address or FQDN (e.g. 192.168.88.1 / router.domain.net)
    api_port INT NOT NULL DEFAULT 8728,          -- 8728 (Socket plain), 8729 (Socket SSL), 443 (REST HTTPS)
    protocol VARCHAR(20) NOT NULL DEFAULT 'socket', -- 'socket', 'socket_ssl', 'rest_https'
    username VARCHAR(100) NOT NULL,             -- RouterOS API user
    password_encrypted TEXT NOT NULL,           -- Encrypted RouterOS API password (via Laravel Encrypter)
    is_safe_mode_enabled BOOLEAN NOT NULL DEFAULT TRUE, -- Guardrail flag: blocks destructive commands
    use_tls BOOLEAN NOT NULL DEFAULT FALSE,
    timeout_seconds INT NOT NULL DEFAULT 5,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'unknown', -- 'online', 'offline', 'error', 'unknown'
    last_connected_at TIMESTAMP WITH TIME ZONE NULL,
    ros_version VARCHAR(50) NULL,
    board_model VARCHAR(100) NULL,
    cpu_load INT NULL,
    free_memory_bytes BIGINT NULL,
    total_memory_bytes BIGINT NULL,
    uptime VARCHAR(100) NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_routers_host_port ON routers(host, api_port);
CREATE INDEX IF NOT EXISTS idx_routers_status ON routers(status);
CREATE INDEX IF NOT EXISTS idx_routers_safe_mode ON routers(is_safe_mode_enabled);

-- ------------------------------------------------------------------------------
-- 3. Table: hotspot_profiles (Synchronized / Managed Hotspot User Profiles)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hotspot_profiles (
    id BIGSERIAL PRIMARY KEY,
    router_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL,                  -- Profile name matching MikroTik /ip/hotspot/user/profile
    shared_users INT NOT NULL DEFAULT 1,
    rate_limit VARCHAR(50) NULL,                -- e.g. 1M/2M, 512k/1M
    validity_duration VARCHAR(50) NULL,         -- e.g. 1h, 12h, 1d, 7d, 30d
    validity_unit VARCHAR(20) NULL,             -- 'minutes', 'hours', 'days'
    price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,  -- Base buying/cost price
    selling_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00, -- Retail voucher price for customers
    on_login_script TEXT NULL,
    on_logout_script TEXT NULL,
    address_pool VARCHAR(100) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hotspot_profiles_router FOREIGN KEY (router_id) 
        REFERENCES routers(id) ON DELETE CASCADE,
    CONSTRAINT uq_router_profile_name UNIQUE (router_id, name)
);

CREATE INDEX IF NOT EXISTS idx_hotspot_profiles_router_id ON hotspot_profiles(router_id);

-- ------------------------------------------------------------------------------
-- 4. Table: voucher_batches (Tracking Batch Generation & Printing Audits)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS voucher_batches (
    id VARCHAR(36) PRIMARY KEY,                  -- UUID v4 or ULID
    router_id BIGINT NOT NULL,
    profile_id BIGINT NOT NULL,
    name VARCHAR(150) NOT NULL,                  -- Batch title, e.g. "Voucher-1Jam-Batch-001"
    quantity INT NOT NULL DEFAULT 1,
    prefix VARCHAR(20) NULL,
    character_length INT NOT NULL DEFAULT 6,
    code_format VARCHAR(30) NOT NULL DEFAULT 'alphanumeric', -- 'numbers', 'lowercase', 'uppercase', 'alphanumeric'
    password_mode VARCHAR(20) NOT NULL DEFAULT 'same_as_user', -- 'same_as_user', 'separate_random'
    created_by BIGINT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_voucher_batches_router FOREIGN KEY (router_id) 
        REFERENCES routers(id) ON DELETE CASCADE,
    CONSTRAINT fk_voucher_batches_profile FOREIGN KEY (profile_id) 
        REFERENCES hotspot_profiles(id) ON DELETE RESTRICT,
    CONSTRAINT fk_voucher_batches_user FOREIGN KEY (created_by) 
        REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_voucher_batches_router_id ON voucher_batches(router_id);

-- ------------------------------------------------------------------------------
-- 5. Table: vouchers (Hotspot User Credentials & Consumption Lifecycle)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS vouchers (
    id VARCHAR(36) PRIMARY KEY,                  -- UUID or ULID for anti-tamper security
    batch_id VARCHAR(36) NULL,
    router_id BIGINT NOT NULL,
    profile_id BIGINT NOT NULL,
    username VARCHAR(64) NOT NULL,               -- MikroTik Hotspot username
    password VARCHAR(64) NOT NULL,               -- MikroTik Hotspot password
    time_limit VARCHAR(50) NULL,                 -- e.g. '01:00:00', '1d', '30m'
    data_limit_bytes BIGINT NULL,                -- Byte limit (e.g. 1073741824 = 1GB)
    comment VARCHAR(150) NULL,                   -- e.g. "TikPulse-vc-20261015-1h"
    status VARCHAR(20) NOT NULL DEFAULT 'available', -- 'available', 'active', 'expired', 'disabled'
    is_printed BOOLEAN NOT NULL DEFAULT FALSE,
    printed_at TIMESTAMP WITH TIME ZONE NULL,
    first_login_at TIMESTAMP WITH TIME ZONE NULL,
    expires_at TIMESTAMP WITH TIME ZONE NULL,
    mac_address VARCHAR(20) NULL,
    ip_address VARCHAR(45) NULL,
    synced_to_router BOOLEAN NOT NULL DEFAULT FALSE,
    sync_failed_reason TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_vouchers_batch FOREIGN KEY (batch_id) 
        REFERENCES voucher_batches(id) ON DELETE SET NULL,
    CONSTRAINT fk_vouchers_router FOREIGN KEY (router_id) 
        REFERENCES routers(id) ON DELETE CASCADE,
    CONSTRAINT fk_vouchers_profile FOREIGN KEY (profile_id) 
        REFERENCES hotspot_profiles(id) ON DELETE RESTRICT,
    CONSTRAINT uq_router_voucher_username UNIQUE (router_id, username)
);

CREATE INDEX IF NOT EXISTS idx_vouchers_batch_id ON vouchers(batch_id);
CREATE INDEX IF NOT EXISTS idx_vouchers_router_status ON vouchers(router_id, status);
CREATE INDEX IF NOT EXISTS idx_vouchers_username ON vouchers(username);
CREATE INDEX IF NOT EXISTS idx_vouchers_is_printed ON vouchers(is_printed);

-- ------------------------------------------------------------------------------
-- 6. Table: safe_mode_audit_logs (Security Audit Trail for Protected Commands)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS safe_mode_audit_logs (
    id BIGSERIAL PRIMARY KEY,
    router_id BIGINT NULL,
    user_id BIGINT NULL,
    command_type VARCHAR(50) NOT NULL,           -- e.g. 'routeros_command', 'bulk_delete', 'firmware_upgrade'
    target_endpoint VARCHAR(255) NOT NULL,       -- e.g. '/system/reboot', '/ip/hotspot/user/remove'
    payload JSONB NULL,
    is_blocked BOOLEAN NOT NULL DEFAULT TRUE,     -- TRUE if blocked by Safe Mode guardrail
    bypass_reason TEXT NULL,                     -- Provided if explicitly unlocked/bypassed
    client_ip VARCHAR(45) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_router FOREIGN KEY (router_id) 
        REFERENCES routers(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) 
        REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_safe_mode_logs_router ON safe_mode_audit_logs(router_id);
CREATE INDEX IF NOT EXISTS idx_safe_mode_logs_blocked ON safe_mode_audit_logs(is_blocked);
CREATE INDEX IF NOT EXISTS idx_safe_mode_logs_created_at ON safe_mode_audit_logs(created_at);

-- ------------------------------------------------------------------------------
-- 7. Table: personal_access_tokens (Laravel Sanctum API Tokens)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id BIGSERIAL PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT NOT NULL,
    name TEXT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP WITH TIME ZONE NULL,
    expires_at TIMESTAMP WITH TIME ZONE NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_personal_access_tokens_tokenable ON personal_access_tokens(tokenable_type, tokenable_id);
CREATE INDEX IF NOT EXISTS idx_personal_access_tokens_expires_at ON personal_access_tokens(expires_at);

-- ------------------------------------------------------------------------------
-- 8. Table: ppp_profiles (Synchronized / Managed MikroTik PPP Bandwidth Profiles)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ppp_profiles (
    id BIGSERIAL PRIMARY KEY,
    router_id BIGINT NOT NULL,
    name VARCHAR(100) NOT NULL,                  -- Profile name matching MikroTik /ppp/profile
    local_address VARCHAR(50) NULL,             -- Gateway IP
    remote_address VARCHAR(100) NULL,           -- Dedicated IP or IP Pool name
    rate_limit VARCHAR(50) NULL,                -- e.g. 10M/20M, 50M/50M
    only_one BOOLEAN NOT NULL DEFAULT TRUE,     -- Prevent multiple logins per secret
    dns_server VARCHAR(100) NULL,               -- e.g. 8.8.8.8,1.1.1.1
    price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,  -- Monthly subscription price
    comment VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ppp_profiles_router FOREIGN KEY (router_id) 
        REFERENCES routers(id) ON DELETE CASCADE,
    CONSTRAINT uq_router_ppp_profile_name UNIQUE (router_id, name)
);

CREATE INDEX IF NOT EXISTS idx_ppp_profiles_router_id ON ppp_profiles(router_id);

-- ------------------------------------------------------------------------------
-- 9. Table: dedicated_users (Dedicated Customer PPP Secrets / PPPoE Subscribers)
-- ------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dedicated_users (
    id VARCHAR(36) PRIMARY KEY,                  -- UUID or ULID for anti-tamper security
    router_id BIGINT NOT NULL,
    ppp_profile_id BIGINT NULL,
    name VARCHAR(100) NOT NULL,                  -- RouterOS secret username / account identifier
    password VARCHAR(100) NOT NULL,              -- RouterOS secret password
    service VARCHAR(20) NOT NULL DEFAULT 'pppoe', -- 'pppoe', 'any', 'l2tp', 'sstp', 'pptp', 'ovpn'
    profile VARCHAR(100) NOT NULL DEFAULT 'default',
    local_address VARCHAR(50) NULL,
    remote_address VARCHAR(50) NULL,             -- Dedicated static IP or pool
    caller_id VARCHAR(50) NULL,                  -- MAC address lock
    routes VARCHAR(255) NULL,
    customer_name VARCHAR(150) NULL,             -- Dedicated subscriber/company name
    customer_phone VARCHAR(50) NULL,
    customer_address TEXT NULL,
    monthly_price DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
    billing_cycle VARCHAR(30) NOT NULL DEFAULT 'monthly',
    comment VARCHAR(255) NULL,
    disabled BOOLEAN NOT NULL DEFAULT FALSE,
    synced_to_router BOOLEAN NOT NULL DEFAULT FALSE,
    sync_failed_reason TEXT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dedicated_users_router FOREIGN KEY (router_id) 
        REFERENCES routers(id) ON DELETE CASCADE,
    CONSTRAINT fk_dedicated_users_profile FOREIGN KEY (ppp_profile_id) 
        REFERENCES ppp_profiles(id) ON DELETE SET NULL,
    CONSTRAINT uq_router_dedicated_user_name UNIQUE (router_id, name)
);

CREATE INDEX IF NOT EXISTS idx_dedicated_users_router_id ON dedicated_users(router_id);
CREATE INDEX IF NOT EXISTS idx_dedicated_users_customer_name ON dedicated_users(customer_name);
CREATE INDEX IF NOT EXISTS idx_dedicated_users_remote_address ON dedicated_users(remote_address);
CREATE INDEX IF NOT EXISTS idx_dedicated_users_disabled ON dedicated_users(disabled);

