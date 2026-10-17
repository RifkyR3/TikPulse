# TikPulse Database & Schema Management 🗄️

TikPulse uses **PostgreSQL 14+** for its primary data store, adhering to strict enterprise database policies.

---

## 🛑 Manual DDL Policy (No Runtime Auto-DDL)

To guarantee high availability and prevent accidental lock contention or catastrophic schema mutations in production:

> [!CAUTION]
> **Data Definition Language (DDL) is NEVER executed automatically at application boot or during runtime.**
> All schema modifications must be applied explicitly by administrators using standard migration tools or raw idempotent SQL scripts.

---

## 📜 1. Raw SQL Schema (`database/sql/schema.sql`)

The repository includes a comprehensive, idempotent PostgreSQL schema script located at:
`database/sql/schema.sql`

This script can be executed directly in any database management tool (**DBeaver**, **DataGrip**, **Navicat**, or **psql** CLI):

```bash
psql -U postgres -d tikpulse -f database/sql/schema.sql
```

### Script Idempotence
The SQL script contains guard clauses such as:
- `CREATE EXTENSION IF NOT EXISTS "uuid-ossp";`
- `CREATE EXTENSION IF NOT EXISTS "pgcrypto";`
- `CREATE TABLE IF NOT EXISTS ...`
- `CREATE INDEX IF NOT EXISTS ...`

This makes it safe to run multiple times without corrupting existing structures.

---

## 🐘 2. Laravel Database Migrations

Alternatively, you can manage the schema using standard Laravel artisan commands:

```powershell
# Run pending migrations
php85 artisan migrate

# Seed initial superadmin user and default profiles
php85 artisan db:seed
```

---

## 📊 3. Core Database Entities

### 1. `users`
Enterprise administrator and operator accounts.
- `role`: `'superadmin'`, `'admin'`, or `'operator'`.
- `preferred_locale`: `'en'` or `'id'` (persisted across sessions).

### 2. `routers`
MikroTik hardware device connection settings.
- `password_encrypted`: AES-256 encrypted password text.
- `protocol`: `'socket'` (Port 8728), `'socket_ssl'` (Port 8729), or `'rest_https'` (Port 443).
- `is_safe_mode_enabled`: Hardware guardrail protection flag (default `true`).

### 3. `safe_mode_audit_logs`
Tamper-resistant audit trail recording every guarded command execution attempt.
- `action`: Specific destructive action name (e.g. `reboot_router`, `bulk_delete_vouchers`).
- `status`: `'allowed'`, `'blocked'`, or `'failed'`.
- `ip_address`, `user_agent`, `payload`.

### 4. `hotspot_profiles`
Bandwidth profiles synchronized with MikroTik `/ip/hotspot/user/profile`.

### 5. `vouchers`
Hotspot access vouchers generated with anti-tamper **ULID/UUID** primary keys.
- `code_mode`: `'single'` (`username = password`) or `'dual'` (`username != password`).
- `status`: `'active'`, `'used'`, `'expired'`, `'revoked'`.

### 6. `ppp_profiles`
Bandwidth and IP pool profiles synchronized with MikroTik `/ppp/profile`.
- `rate_limit`: Format `<rx>/<tx>` (e.g., `10M/10M`).
- `local_address_pool`, `remote_address_pool`, `dns_servers`.

### 7. `dedicated_users`
Corporate and residential subscribers synchronized with MikroTik `/ppp/secret`.
- `service`: `'pppoe'`, `'l2tp'`, `'any'`, or `'static'`.
- `remote_address`: Static IP binding.
- `caller_id`: MAC address binding.
- `monthly_price`: Currency tracking for billing analytics.

---

## 🔒 Security & Anti-Tamper Keys

- **ULIDs / UUIDs**: Vouchers and financial transactions use UUID/ULID string identifiers to prevent sequential ID enumeration and tampering.
- **Foreign Key Constraints with Cascade**: Child records cleanly cascade deletions only when explicitly permitted by Safe Mode guards.
