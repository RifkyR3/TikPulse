# TikPulse Safe Mode & Hardware Guardrails 🛡️

In high-concurrency ISP, enterprise, and hospitality environments, an inadvertent click on a reboot button, bulk delete action, or config reset can cause catastrophic network downtime. 

TikPulse solves this by enforcing **Hardware Safe Mode Guardrails** (`is_safe_mode_enabled = true` by default) at both the backend middleware and application domain layers, integrated seamlessly with Enterprise RBAC permissions.

---

## 🛡️ Multi-Layer Defense Architecture

Any destructive or state-altering command must pass through two independent defense layers before reaching the MikroTik device:

```text
Incoming HTTP Request
       │
       ▼
[ Layer 1: RBAC Middleware ] ──────► Fails? ──► 403 Forbidden (UnauthorizedModuleAccessException)
       │ (checks module.access ability: update/delete)
       ▼
[ Layer 2: Safe Mode Guard ] ──────► Safe Mode Active & No Bypass? ──► 403 Forbidden (SafeModeActiveException)
       │ (verifies X-SafeMode-Bypass token)
       ▼
[ Audit Trail Logger ] ────────────► Records event in safe_mode_audit_logs (Allowed / Blocked)
       │
       ▼
[ MikroTik RouterOS Driver ] ──────► Dispatches command over Socket / REST
```

---

## 🚫 Guarded Destructive Actions

The following operations are strictly intercepted by `EnsureSafeModeCompliance` and `MikrotikSafeModeGuard`:

1. **Hardware Disruption**:
   - Router Reboot (`/system/reboot`)
   - Router Shutdown (`/system/shutdown`)
   - Configuration Reset (`/system/reset-configuration`)
2. **Firmware & Packages**:
   - RouterOS package installation/downgrade (`/system/package/update/*`)
3. **Data Purges & Deletions**:
   - Deleting a router profile from fleet (`DELETE /api/v1/routers/{id}`)
   - Batch deletion of hotspot vouchers (`bulk_delete_vouchers`)
   - Deleting hotspot user profiles (`bulk_delete_profiles`)
   - Deleting individual hotspot users from router (`DELETE /hotspot/users/{username}`)
   - Deleting PPP subscriber secrets (`bulk_delete_ppp_secrets`)
   - Deleting PPP profiles (`bulk_delete_ppp_profiles`)

---

## 🔑 Two-Step Unlock Sequence

When Safe Mode is active, destructive actions return **HTTP 403 Forbidden** (`SafeModeActiveException`). To authorize an action:

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Administrator
    participant UI as Frontend SPA (Modal)
    participant API as Laravel Backend
    participant DB as PostgreSQL
    participant ROS as MikroTik RouterOS

    Admin->>UI: Triggers Destructive Action (e.g. Reboot Router)
    UI->>API: POST /api/v1/routers/1/reboot (without bypass header)
    API-->>UI: 403 Forbidden (SafeModeActiveException)
    UI->>UI: Opens SafeModeUnlockModal
    Admin->>UI: Enters Password + Types "UNLOCK"
    UI->>API: POST /api/v1/routers/1/safe-mode/unlock
    API->>API: Verifies password & confirmation string
    API->>DB: Stores temporary bypass token (15-min TTL)
    API-->>UI: 200 OK (bypass_token)
    UI->>API: POST /api/v1/routers/1/reboot with X-SafeMode-Bypass header
    API->>DB: Logs operation to safe_mode_audit_logs (is_blocked = false)
    API->>ROS: Dispatches /system/reboot
    API-->>UI: 200 OK (Reboot initiated)
```

### Step 1: Administrator Password Verification
The user must provide their account password to confirm identity.

### Step 2: Textual Confirmation String
The user must type the literal string `"UNLOCK"` into the confirmation prompt to ensure deliberate intent.

### Step 3: Temporary Bypass Token (15-Minute TTL)
- Upon successful verification, the API issues a cryptographically secure bypass token valid for exactly **15 minutes**.
- The frontend stores the token in memory (`safeModeStore.svelte.ts`) and sends it via the `X-SafeMode-Bypass` HTTP header on subsequent guarded requests.
- When the 15 minutes expire, the router automatically re-locks.
- Administrators can also manually re-lock at any time via `POST /api/v1/routers/{id}/safe-mode/lock`.

---

## 📋 Audit Trail (`safe_mode_audit_logs`)

Every attempt to execute a guarded operation—whether permitted via token or blocked—is recorded in the `safe_mode_audit_logs` table:

| Column | Type | Description |
|---|---|---|
| `id` | `BIGSERIAL` | Primary Key |
| `router_id` | `BIGINT NULL` | Target Router Profile (`routers.id`), NULL for fleet-wide actions |
| `user_id` | `BIGINT NULL` | User initiating the action (`users.id`) |
| `command_type` | `VARCHAR(50)` | Action category (e.g., `routeros_command`, `bulk_delete`) |
| `target_endpoint` | `VARCHAR(255)` | Target path (e.g., `/system/reboot`, `routers/1/vouchers/bulk-delete`) |
| `payload` | `JSONB NULL` | Captured parameters and payload details |
| `is_blocked` | `BOOLEAN` | `true` if blocked by Safe Mode, `false` if authorized |
| `bypass_reason` | `TEXT NULL` | Provided unlock confirmation or reason string |
| `client_ip` | `VARCHAR(45)` | Remote client IP address |
| `created_at` | `TIMESTAMP` | Timestamp of event |

### Viewing Audit Logs
- **Fleet-Wide Logs**: Accessible via `GET /api/v1/safe-mode/audit-logs` (Web route `/audit-logs`).
- **Router-Scoped Logs**: Accessible via `GET /api/v1/routers/{id}/safe-mode/audit-logs`.
