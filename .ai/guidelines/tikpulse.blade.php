@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# TikPulse Architecture & Enterprise Rules

## 1. Architecture & Code Isolation
- Layered isolation: `Domain` -> `Application` -> `Infrastructure` -> `Http/Presentation`.
- Always declare `declare(strict_types=1);` and enforce explicit type hints on all PHP methods.
- Controllers MUST NEVER touch MikroTik sockets or REST APIs directly. Delegate all network operations to:
  - `MikrotikConnectionService`: Socket & REST connection lifecycle, circuit breaker.
  - `HotspotVoucherService`: Hotspot users, active sessions, profile synchronization.
  - `PppService`: PPP secrets, profiles, active sessions, kick termination.
  - `VoucherGenerationService`: Batch voucher generator, anti-tamper ULID/UUID tokens.
  - `MikrotikSafeModeGuard`: Destructive action guardrail & bypass token verification.
- Enforce Granular RBAC: Protect all controller endpoints with `AuthorizeModuleAccess` middleware (`module.access:<module>,<ability>`). Supported abilities: `read`, `write`, `update`, `delete`.

## 2. Safe Mode Guardrails
- Destructive commands (`system_reboot`, `system_shutdown`, `reset_configuration`, `bulk_delete_*` for vouchers, profiles, PPP secrets) MUST pass `EnsureSafeModeCompliance` middleware & `MikrotikSafeModeGuard`.
- Without `X-SafeMode-Bypass` header or valid bypass token (15-min TTL via `POST /api/v1/routers/{router}/safe-mode/unlock`), return 403 `SafeModeActiveException`.
- Every permitted or blocked attempt MUST be recorded into `safe_mode_audit_logs`.

## 3. Database, Multi-Router & Group Scoping
- PostgreSQL 14+. STRICTLY NO runtime DDL. Keep migrations and `database/sql/schema.sql` strictly synchronized. Voucher transactions use ULID/UUID PKs.
- Multi-router context resolved via `X-Router-ID` header or route param (`ResolveRouterContext`). Verify that user's group permits access (`all_routers = true` or router exists in `allowed_router_ids`).
- Router passwords MUST be stored encrypted (`Crypt::encryptString`). Support socket (8728/8729) & REST (443).
- User Account Guardrails: Prevent self-deletion, self-deactivation, and demotion/deletion of the last Super Administrator.

## 4. Resilience & Performance
- Circuit Breaker trips after 3 consecutive failures (fast-fail 503 `CircuitBreakerTrippedException`), pausing telemetry/polling. Reconnect action resets the counter.
- High-frequency telemetry/active sessions: transient cache (2-3s). Router status DB updates throttled to status change or every 30s.
- Client: Halt polling when browser tab is hidden (`pageVisibility.svelte.ts` / `usePageVisibility`). Debounce all table search/filter inputs.

## 5. UI/UX & Localization Standards
- Bilingual: English (`en`, default) and Bahasa Indonesia (`id`). Persist in `users.preferred_locale` + `localStorage('tikpulse_locale')`. Sync via `localeStore` (`resources/js/stores/locale.svelte.ts`).
- Appearance Preferences: Support theme modes (`light`, `dark`, `system`) and 6 accent colors (`sky`, `indigo`, `emerald`, `violet`, `amber`, `rose`) persisted in `users.settings`.
- Strict UI Uniformity: Built on SvelteForge Admin UI primitives in `resources/js/components/ui/` (`sidebar`, `card`, `button`, `badge`, `breadcrumb`, `separator`, `input`, `avatar`).
- Conditionally render navigation items and action buttons using `$derived` permission checks via `authStore.can(module, ability)`.
- Mirror layout, visual hierarchy, and patterns from `VouchersIndex.svelte`, `DedicatedUsers.svelte`, and `UsersIndex.svelte`.
  - Use `DataTable.svelte`, semantic Tailwind tokens (`bg-card`, `border-border`, `text-foreground`, `text-muted-foreground`), and uniform primary gradient for CTAs (`bg-gradient-to-r from-sky-600 to-indigo-600`).

## 6. Pre-Finalization Checklist & Versioning
- Lint with PHP 8.5: `php85 vendor/bin/pint --format agent`.
- Test & Types: `php85 vendor/bin/phpunit` and `npm run types:check`.
- Mandatory: Record all changes in `CHANGELOG.md` under `[Unreleased]` (`Added`, `Changed`, `Fixed`, `Security`) adhering to Keep a Changelog.
