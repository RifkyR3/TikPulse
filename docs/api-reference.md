# TikPulse API Reference (v1) 📡

TikPulse provides a high-performance headless REST API adhering to JSON response standards. All requests are routed through the `/api/v1` namespace and protected by enterprise authentication, granular module ACL, and router scoping.

---

## 📌 Global Request Headers

| Header | Type | Description | Mandatory |
|---|---|---|---|
| `Accept` | `string` | Must be `application/json` | Yes |
| `Content-Type` | `string` | Must be `application/json` | Yes (for POST/PUT) |
| `Authorization` | `string` | `Bearer <token>` (optional if using stateful Sanctum session cookies) | Conditional |
| `X-Router-ID` | `integer` | Target MikroTik Router ID for router-scoped actions | Yes (for router endpoints) |
| `X-SafeMode-Bypass` | `string` | 15-minute temporary bypass token required for destructive actions | Required when Safe Mode is active |

---

## 1. Authentication & Profile Preferences (`/api/v1/auth`)

### Endpoints

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `POST` | `/auth/login` | Authenticate user and issue Sanctum session cookie / token | Rate Limited (`10,1`) |
| `POST` | `/auth/logout` | Invalidate current user session | `auth:sanctum` |
| `GET` | `/auth/me` | Fetch authenticated user, role, group, permissions, and settings | `auth:sanctum` |
| `POST` | `/auth/locale` | Update user language preference (`en` or `id`) | `auth:sanctum` |
| `POST` | `/auth/settings` | Update user appearance preferences in PostgreSQL JSONB | `auth:sanctum` |
| `POST` | `/auth/switch-role` | Simulate a specific group context (Superadmin only) | `auth:sanctum` |
| `POST` | `/auth/reset-role` | Revert simulated group context back to Superadmin | `auth:sanctum` |

#### `POST /auth/login`
**Request Payload:**
```json
{
  "email": "admin@tikpulse.test",
  "password": "secret123",
  "remember": true
}
```
> [!NOTE]
> When `remember` is set to `true`, the Sanctum cookie lifetime is extended to **30 days**.

#### `POST /auth/settings`
**Request Payload:**
```json
{
  "theme": "dark",
  "accent_color": "sky",
  "refresh_interval": 15,
  "table_compact_mode": false
}
```

#### `POST /auth/switch-role`
**Request Payload:**
```json
{
  "group_id": 2
}
```

---

## 2. RBAC & Access Control (`/api/v1/modules`, `/api/v1/groups`, `/api/v1/users`)

All RBAC endpoints are protected by `AuthorizeModuleAccess` middleware (`module.access:<module>,<ability>`).

### Modules & Capabilities (`/api/v1/modules`)

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/modules` | List all system modules, keys, and supported actions | `auth:sanctum` |

Supported modules: `dashboard`, `routers`, `hotspot_vouchers`, `ppp_subscribers`, `safe_mode_logs`, `users`, `groups`. Supported abilities: `read`, `write`, `update`, `delete`.

### Access Groups Management (`/api/v1/groups`)

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/groups` | List access groups with member count & permission matrices | `module.access:groups,read` |
| `POST` | `/groups` | Create custom group with module ACL and router scoping | `module.access:groups,write` |
| `GET` | `/groups/{group}` | View group details and assigned router IDs | `module.access:groups,read` |
| `PUT` | `/groups/{group}` | Update group attributes, permissions, and router scoping | `module.access:groups,update` |
| `DELETE` | `/groups/{group}` | Delete group (Blocked if group has active assigned users) | `module.access:groups,delete` |

#### `POST /groups`
**Request Payload:**
```json
{
  "name": "Branch Operators",
  "slug": "branch-operators",
  "description": "Restricted operators for Branch 1",
  "all_routers": false,
  "router_ids": [1, 2],
  "permissions": [
    {
      "module_key": "hotspot_vouchers",
      "can_read": true,
      "can_write": true,
      "can_update": false,
      "can_delete": false
    },
    {
      "module_key": "routers",
      "can_read": true,
      "can_write": false,
      "can_update": false,
      "can_delete": false
    }
  ]
}
```

### User Accounts Management (`/api/v1/users`)

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/users` | Paginated users list with search, group, & status filters | `module.access:users,read` |
| `POST` | `/users` | Create user account and assign to an access group | `module.access:users,write` |
| `GET` | `/users/{user}` | View user profile details | `module.access:users,read` |
| `PUT` | `/users/{user}` | Update user details, role, group, or active status | `module.access:users,update` |
| `DELETE` | `/users/{user}` | Delete user account (Guardrails prevent self-deletion) | `module.access:users,delete` |

#### `POST /users`
**Request Payload:**
```json
{
  "name": "Operator John",
  "email": "john@branch.test",
  "password": "Password123!",
  "role": "operator",
  "group_id": 2,
  "is_active": true
}
```

---

## 3. Router Fleet Management (`/api/v1/routers`)

### Endpoints

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/routers` | List authorized MikroTik router profiles | `module.access:routers,read` |
| `POST` | `/routers` | Register a new router profile (auto-encrypts credentials) | `module.access:routers,write` |
| `GET` | `/routers/{router}` | View router profile details | `module.access:routers,read` |
| `PUT` | `/routers/{router}` | Update router profile parameters | `module.access:routers,update` |
| `DELETE` | `/routers/{router}` | Delete router profile from fleet | `module.access:routers,delete`, `safe.mode` |
| `POST` | `/routers/{router}/test-connection` | Test connection and reset circuit breaker | `router.context`, `module.access:routers,read` |
| `GET` | `/routers/{router}/telemetry` | Live hardware telemetry (CPU, RAM, Uptime, ROS version) | `router.context`, `module.access:routers,read` |
| `POST` | `/routers/{router}/reboot` | Reboot router hardware | `router.context`, `module.access:routers,update`, `safe.mode:system_reboot` |

#### `POST /routers`
**Request Payload:**
```json
{
  "name": "Core-Gateway-01",
  "host": "192.168.88.1",
  "api_port": 8728,
  "protocol": "socket",
  "username": "api_admin",
  "password": "SuperSecurePassword123!",
  "is_safe_mode_enabled": true,
  "use_tls": false,
  "timeout_seconds": 5,
  "description": "Primary edge router for HQ"
}
```

---

## 4. Safe Mode Guardrails (`/api/v1/routers/{router}/safe-mode`)

### Endpoints

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/safe-mode/audit-logs` | Fleet-wide anti-tamper audit logs | `module.access:safe_mode_logs,read` |
| `GET` | `/routers/{router}/safe-mode/status` | Current Safe Mode state and active bypass countdown | `router.context`, `module.access:safe_mode_logs,read` |
| `POST` | `/routers/{router}/safe-mode/unlock` | Two-Step unlock sequence issuing 15-minute bypass token | `router.context`, `module.access:safe_mode_logs,read` |
| `POST` | `/routers/{router}/safe-mode/lock` | Manually re-lock router and revoke bypass token | `router.context`, `module.access:safe_mode_logs,read` |
| `POST` | `/routers/{router}/safe-mode/toggle` | Permanently toggle Safe Mode flag on router | `router.context`, `module.access:safe_mode_logs,read` |
| `GET` | `/routers/{router}/safe-mode/audit-logs` | Target router audit logs of guarded commands | `router.context`, `module.access:safe_mode_logs,read` |

#### `POST /routers/{router}/safe-mode/unlock`
**Request Payload:**
```json
{
  "password": "administrator_password",
  "confirmation": "UNLOCK"
}
```
**Response (200 OK):**
```json
{
  "success": true,
  "message": "Safe Mode temporarily bypassed for 15 minutes.",
  "bypass_token": "tk_safe_98f411ba718e4722881bb45c",
  "expires_at": "2026-10-21T10:15:00Z"
}
```

---

## 5. Hotspot Voucher Studio & Traffic (`/api/v1/routers/{router}`)

Scoped by router context (`router.context:true`) and module permissions (`hotspot_vouchers`).

### Endpoints

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/routers/{router}/profiles` | List synchronized hotspot user profiles | `module.access:hotspot_vouchers,read` |
| `POST` | `/routers/{router}/profiles` | Create hotspot profile and sync to MikroTik | `module.access:hotspot_vouchers,write` |
| `POST` | `/routers/{router}/profiles/sync` | Pull and sync profiles directly from MikroTik | `module.access:hotspot_vouchers,write` |
| `PUT` | `/routers/{router}/profiles/{profile}` | Update existing hotspot profile | `module.access:hotspot_vouchers,update` |
| `DELETE` | `/routers/{router}/profiles/{profile}` | Delete hotspot profile | `module.access:hotspot_vouchers,delete`, `safe.mode` |
| `GET` | `/routers/{router}/vouchers` | List paginated vouchers with search/batch/status filters | `module.access:hotspot_vouchers,read` |
| `GET` | `/routers/{router}/vouchers/batches` | List batch generation history | `module.access:hotspot_vouchers,read` |
| `GET` | `/routers/{router}/vouchers/batches/{batch}` | View specific batch details and vouchers | `module.access:hotspot_vouchers,read` |
| `POST` | `/routers/{router}/vouchers/generate` | Batch generate vouchers and push to router | `module.access:hotspot_vouchers,write` |
| `POST` | `/routers/{router}/vouchers/mark-printed` | Flag batch vouchers as printed | `module.access:hotspot_vouchers,write` |
| `POST` | `/routers/{router}/vouchers/bulk-delete` | Purge voucher batch from database & MikroTik | `module.access:hotspot_vouchers,delete`, `safe.mode` |
| `GET` | `/routers/{router}/hotspot/active` | Real-time active hotspot client sessions | `module.access:hotspot_vouchers,read` |
| `POST` | `/routers/{router}/hotspot/active/{sessionId}/kick` | Terminate (kick) active hotspot session | `module.access:hotspot_vouchers,update` |
| `GET` | `/routers/{router}/hotspot/users` | List direct hotspot users registered on router | `module.access:hotspot_vouchers,read` |
| `POST` | `/routers/{router}/hotspot/users` | Create single dedicated hotspot user | `module.access:hotspot_vouchers,write` |
| `DELETE` | `/routers/{router}/hotspot/users/{username}` | Delete single hotspot user from router | `module.access:hotspot_vouchers,delete`, `safe.mode` |
| `GET` | `/routers/{router}/traffic` | Real-time WAN throughput Rx/Tx monitor | `module.access:hotspot_vouchers,read` |
| `GET` | `/routers/{router}/interfaces` | List router network interfaces | `module.access:hotspot_vouchers,read` |

#### `POST /routers/{router}/vouchers/generate`
**Request Payload:**
```json
{
  "hotspot_profile_id": 1,
  "count": 50,
  "code_mode": "single",
  "code_format": "alpha_num",
  "char_length": 6,
  "batch_name": "Event-Weekend-Oct"
}
```

---

## 6. Dedicated Subscribers & PPP Studio (`/api/v1/routers/{router}/ppp`)

Scoped by router context (`router.context:true`) and module permissions (`ppp_subscribers`).

### Endpoints

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/ppp/profiles` | List synchronized PPP bandwidth profiles | `module.access:ppp_subscribers,read` |
| `POST` | `/ppp/profiles` | Create new PPP profile and push to MikroTik | `module.access:ppp_subscribers,write` |
| `POST` | `/ppp/profiles/sync` | Pull and sync PPP profiles from MikroTik | `module.access:ppp_subscribers,write` |
| `PUT` | `/ppp/profiles/{profile}` | Update existing PPP profile parameters | `module.access:ppp_subscribers,update` |
| `DELETE` | `/ppp/profiles/{profile}` | Delete PPP profile | `module.access:ppp_subscribers,delete`, `safe.mode` |
| `GET` | `/ppp/pools` | List available IP address pools from router | `module.access:ppp_subscribers,read` |
| `GET` | `/ppp/secrets` | List dedicated subscribers / PPP secrets | `module.access:ppp_subscribers,read` |
| `POST` | `/ppp/secrets` | Register new subscriber secret and push to router | `module.access:ppp_subscribers,write` |
| `POST` | `/ppp/secrets/sync` | Pull and sync PPP secrets from MikroTik | `module.access:ppp_subscribers,write` |
| `PUT` | `/ppp/secrets/{user}` | Update subscriber credentials or static IP | `module.access:ppp_subscribers,update` |
| `POST` | `/ppp/secrets/{user}/toggle` | Toggle secret account status (enable/disable) | `module.access:ppp_subscribers,update` |
| `DELETE` | `/ppp/secrets/{user}` | Delete subscriber secret | `module.access:ppp_subscribers,delete`, `safe.mode` |
| `GET` | `/ppp/active` | Real-time active PPPoE/L2TP connection monitor | `module.access:ppp_subscribers,read` |
| `POST` | `/ppp/active/{sessionId}/kick` | Disconnect active PPP subscriber session | `module.access:ppp_subscribers,update` |

---

## ⚠️ Standard Error Responses

### 403 Forbidden (`UnauthorizedModuleAccessException`)
Triggered when an authenticated user's access group does not possess the required module action capability or is restricted from accessing the specified router:
```json
{
  "message": "User does not have permission to execute 'write' on 'hotspot_vouchers'.",
  "module": "hotspot_vouchers",
  "action": "write"
}
```

### 403 Forbidden (`SafeModeActiveException`)
Triggered when a destructive command is requested without a valid bypass token:
```json
{
  "error": "SafeModeActiveException",
  "message": "Destructive operation blocked by Safe Mode hardware guardrails.",
  "action": "bulk_delete_vouchers",
  "requires_unlock": true
}
```

### 503 Service Unavailable (`CircuitBreakerTrippedException`)
Triggered when the target router has failed 3 consecutive socket connections:
```json
{
  "error": "CircuitBreakerTrippedException",
  "message": "Router connection failed 3 consecutive attempts. Polling paused to prevent server timeout loops.",
  "retry_action": "POST /api/v1/routers/{id}/test-connection"
}
```
