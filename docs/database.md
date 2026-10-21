# TikPulse Database & Schema Management 🗄️

TikPulse uses **PostgreSQL 14+** for its primary data store, adhering to strict enterprise database policies, strict data types, and tamper-resistant primary keys.

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
- `role`: System fallback role (`'superadmin'`, `'admin'`, `'operator'`).
- `group_id`: Foreign key reference to `groups(id)`.
- `is_active`: Boolean status flag (deactivated users are blocked from authenticating).
- `avatar_url`: User avatar image URL.
- `preferred_locale`: Language preference (`'en'` or `'id'`).
- `settings`: JSONB storing personal UI preferences (`theme`, `accent_color`, `refresh_interval`, `table_compact_mode`).
- `simulated_group_id`: Transient foreign key used by Superadmins to simulate subordinate group roles.

### 2. `routers`
MikroTik hardware device connection settings and telemetry cache.
- `host`: IP address or FQDN (e.g., `192.168.88.1`).
- `api_port`: API listening port (8728 for socket, 8729 for socket SSL, 443 for REST).
- `protocol`: `'socket'`, `'socket_ssl'`, or `'rest_https'`.
- `password_encrypted`: AES-256 encrypted password text (`Crypt::encryptString`).
- `is_safe_mode_enabled`: Hardware guardrail protection flag (default `true`).
- `status`: Cached connectivity state (`'online'`, `'offline'`, `'error'`, `'unknown'`).
- `ros_version`, `board_model`, `cpu_load`, `free_memory_bytes`, `uptime`: Telemetry metrics cached with 30s throttled commits.

### 3. `groups`
Enterprise access control groups and role definitions.
- `name`: Group display name (e.g., `Super Administrator`, `Branch Operator`).
- `slug`: Unique identifier slug (e.g., `super-administrator`, `branch-operator`).
- `is_superadmin`: Boolean flag granting unconditional root access across all modules and routers.
- `all_routers`: Boolean flag granting access to all routers without explicit router bindings.

### 4. `modules`
Platform feature modules registered for granular access control.
- `name`: Module label (e.g., `Hotspot & Vouchers`, `Routers Fleet`).
- `key`: Unique module key (`dashboard`, `routers`, `hotspot_vouchers`, `ppp_subscribers`, `safe_mode_logs`, `users`, `groups`).
- `available_actions`: JSONB array of supported actions (`["read", "write", "update", "delete"]`).

### 5. `group_module_permissions`
Granular Access Control List (ACL) mapping permissions between groups and modules.
- `group_id`: Foreign key reference to `groups(id)`.
- `module_key`: Foreign key reference to `modules(key)`.
- `can_read`, `can_write`, `can_update`, `can_delete`: Boolean capability flags.

### 6. `group_router`
Pivot table for restricting groups to specific authorized MikroTik routers when `all_routers = false`.
- `group_id`: Foreign key reference to `groups(id)`.
- `router_id`: Foreign key reference to `routers(id)`.

### 7. `hotspot_profiles`
Bandwidth profiles synchronized with MikroTik `/ip/hotspot/user/profile`.
- `rate_limit`: Format `<rx>/<tx>` (e.g., `1M/2M`, `512k/1M`).
- `validity_duration`: Voucher time validity (e.g., `1h`, `1d`, `30d`).
- `price` / `selling_price`: Financial cost and retail pricing for vouchers.
- `address_pool`, `on_login_script`, `on_logout_script`.

### 8. `voucher_batches`
Audit records tracking batch voucher generation and print jobs.
- `id`: ULID or UUID v4 string identifier.
- `router_id`, `profile_id`, `created_by`.
- `name`: Batch label (e.g., `Event-Weekend-Oct`).
- `quantity`, `prefix`, `character_length`, `code_format`, `password_mode`.

### 9. `vouchers`
Hotspot access credentials generated with collision-resistant **ULID/UUID** primary keys.
- `id`: ULID/UUID primary key preventing ID enumeration attacks.
- `batch_id`: Optional reference to `voucher_batches(id)`.
- `username` / `password`: MikroTik Hotspot client credentials.
- `time_limit`, `data_limit_bytes`: Usage quota caps.
- `status`: `'available'`, `'active'`, `'expired'`, `'disabled'`.
- `is_printed`, `printed_at`: Thermal ticket printing state tracking.
- `synced_to_router`: Synchronization confirmation with RouterOS `/ip/hotspot/user`.

### 10. `safe_mode_audit_logs`
Tamper-resistant audit trail recording every guarded command execution attempt.
- `router_id`: Target MikroTik device (optional if fleet-wide).
- `user_id`: Authenticated user who triggered the action.
- `command_type`: Command category (e.g., `routeros_command`, `bulk_delete`).
- `target_endpoint`: Target URL or API path (e.g., `/system/reboot`).
- `payload`: JSONB capturing full request parameters.
- `is_blocked`: Boolean indicating if Safe Mode blocked execution.
- `bypass_reason`: Recorded unlock confirmation or reason string.
- `client_ip`: Remote IP address of the client.

### 11. `ppp_profiles`
Bandwidth and IP pool profiles synchronized with MikroTik `/ppp/profile`.
- `local_address`, `remote_address`: Gateway IP and pool bindings.
- `rate_limit`: Rx/Tx bandwidth limits (e.g., `10M/20M`).
- `only_one`: Enforce single active session per secret.
- `dns_server`, `price`.

### 12. `dedicated_users`
Corporate and residential subscribers synchronized with MikroTik `/ppp/secret`.
- `id`: UUID or ULID anti-tamper primary key.
- `name` / `password`: PPP secret credentials.
- `service`: `'pppoe'`, `'l2tp'`, `'any'`, `'sstp'`, `'ovpn'`.
- `remote_address`: Dedicated static IP binding.
- `caller_id`: Hardware MAC address lock.
- `customer_name`, `customer_phone`, `customer_address`: Subscriber metadata.
- `monthly_price`, `billing_cycle`: Revenue analytics data.
- `disabled`, `synced_to_router`: State management.

### 13. `personal_access_tokens`
Laravel Sanctum personal access tokens for API consumers and Bearer token fallback.

---

## 🔒 Security & Anti-Tamper Keys

- **ULIDs / UUIDs**: Vouchers, voucher batches, and dedicated subscribers utilize collision-resistant string IDs to eliminate sequential number guessing and enumeration attacks.
- **Foreign Key Cascading**: Child records cleanly cascade deletions only when explicitly permitted by Safe Mode guards.
