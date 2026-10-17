# TikPulse API Reference (v1) 📡

TikPulse provides a headless REST API adhering to JSON response standards. All requests are handled by Laravel 12 controllers through the `/api/v1` namespace.

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

## 1. Authentication (`/api/v1/auth`)

### Endpoints

| Method | Endpoint | Description | Rate Limit |
|---|---|---|---|
| `POST` | `/auth/login` | Authenticate user and issue Sanctum session cookie | 10 req / min |
| `POST` | `/auth/logout` | Invalidate current user session | `auth:sanctum` |
| `GET` | `/auth/me` | Fetch currently authenticated user profile | `auth:sanctum` |
| `POST` | `/auth/locale` | Update user language preference (`en` or `id`) | `auth:sanctum` |

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

---

## 2. Router Fleet Management (`/api/v1/routers`)

### Endpoints

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/routers` | List all registered MikroTik router profiles | `auth:sanctum` |
| `POST` | `/routers` | Register a new router profile (auto-encrypts password) | `auth:sanctum` |
| `GET` | `/routers/{router}` | View router profile details | `auth:sanctum` |
| `PUT` | `/routers/{router}` | Update router profile parameters | `auth:sanctum` |
| `DELETE` | `/routers/{router}` | Delete router profile from system | `auth:sanctum`, `safe.mode` |
| `POST` | `/routers/{router}/test-connection` | Test connection and reset circuit breaker | `auth:sanctum` |
| `GET` | `/routers/{router}/telemetry` | Live hardware telemetry (CPU, RAM, Uptime) | `auth:sanctum` |
| `POST` | `/routers/{router}/reboot` | Reboot router hardware | `auth:sanctum`, `safe.mode` |

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

## 3. Safe Mode Guardrails (`/api/v1/routers/{router}/safe-mode`)

### Endpoints

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/safe-mode/status` | Current Safe Mode state and active bypass countdown | `auth:sanctum` |
| `POST` | `/safe-mode/unlock` | Two-Step unlock sequence issuing 15-minute bypass token | `auth:sanctum` |
| `POST` | `/safe-mode/lock` | Manually re-lock router and revoke bypass token | `auth:sanctum` |
| `POST` | `/safe-mode/toggle` | Permanently toggle Safe Mode flag on router | `auth:sanctum` |
| `GET` | `/safe-mode/audit-logs` | Anti-tamper audit logs of guarded commands | `auth:sanctum` |

#### `POST /safe-mode/unlock`
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
  "expires_at": "2026-10-17T22:15:00Z"
}
```

---

## 4. Hotspot Voucher Studio & Traffic (`/api/v1/routers/{router}`)

### Endpoints

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/routers/{router}/profiles` | List hotspot user profiles | `auth:sanctum` |
| `POST` | `/routers/{router}/profiles` | Create hotspot profile and sync to MikroTik | `auth:sanctum` |
| `POST` | `/routers/{router}/profiles/sync` | Sync profiles directly from MikroTik | `auth:sanctum` |
| `DELETE` | `/routers/{router}/profiles/{profile}` | Delete hotspot profile | `auth:sanctum`, `safe.mode` |
| `GET` | `/routers/{router}/vouchers` | List paginated vouchers with batch/status filter | `auth:sanctum` |
| `POST` | `/routers/{router}/vouchers/generate` | Generate batch vouchers and push to router | `auth:sanctum` |
| `POST` | `/routers/{router}/vouchers/bulk-delete` | Purge voucher batch and router accounts | `auth:sanctum`, `safe.mode` |
| `GET` | `/routers/{router}/hotspot/active` | Real-time active client sessions | `auth:sanctum` |
| `POST` | `/routers/{router}/hotspot/active/{id}/kick` | Terminate (kick) active hotspot session | `auth:sanctum` |
| `GET` | `/routers/{router}/traffic` | Real-time WAN bandwidth Rx/Tx monitor | `auth:sanctum` |

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

## 5. Dedicated Subscribers & PPP Studio (`/api/v1/routers/{router}/ppp`)

### Endpoints

| Method | Endpoint | Description | Protection |
|---|---|---|---|
| `GET` | `/ppp/profiles` | List synchronized PPP bandwidth profiles | `auth:sanctum` |
| `POST` | `/ppp/profiles` | Create new PPP profile and push to MikroTik | `auth:sanctum` |
| `POST` | `/ppp/profiles/sync` | Pull and sync PPP profiles from MikroTik | `auth:sanctum` |
| `PUT` | `/ppp/profiles/{profile}` | Update existing PPP profile parameters | `auth:sanctum` |
| `DELETE` | `/ppp/profiles/{profile}` | Delete PPP profile | `auth:sanctum`, `safe.mode` |
| `GET` | `/ppp/pools` | List available IP address pools from router | `auth:sanctum` |
| `GET` | `/ppp/secrets` | List dedicated subscribers / PPP secrets | `auth:sanctum` |
| `POST` | `/ppp/secrets` | Register new subscriber secret and push to router | `auth:sanctum` |
| `POST` | `/ppp/secrets/sync` | Pull and sync PPP secrets from MikroTik | `auth:sanctum` |
| `PUT` | `/ppp/secrets/{user}` | Update subscriber credentials or static IP | `auth:sanctum` |
| `POST` | `/ppp/secrets/{user}/toggle` | Toggle secret account status (enable/disable) | `auth:sanctum` |
| `DELETE` | `/ppp/secrets/{user}` | Delete subscriber secret | `auth:sanctum`, `safe.mode` |
| `GET` | `/ppp/active` | Real-time active PPPoE/L2TP connection monitor | `auth:sanctum` |
| `POST` | `/ppp/active/{sessionId}/kick` | Disconnect active PPP subscriber session | `auth:sanctum` |

---

## ⚠️ Standard Error Responses

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
