# Changelog

All notable changes to **TikPulse** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - Unreleased

### Added
- **Modern Inertia.js + Svelte 5 Frontend**:
  - Full client-side architecture migration to Svelte 5 (Runes: `$state`, `$derived`, `$effect`, snippets).
  - Inertia.js v3 integration (`@inertiajs/svelte`) with server-side page routing and shared props (`HandleInertiaRequests`).
  - Svelte 5 reactive stores (`routerContext.svelte.ts`, `auth.svelte.ts`, `safeMode.svelte.ts`, `locale.svelte.ts`, `theme.svelte.ts`).
  - Native Svelte components for all feature modules:
    - Authentication (`Pages/auth/login.svelte`).
    - Telemetry & Router Monitoring Dashboard (`Pages/dashboard/Dashboard.svelte`, `InterfaceTrafficChart.svelte`, `ActiveHotspotTable.svelte`).
    - Multi-Router Fleet Management (`Pages/routers/Index.svelte`, `RouterModal.svelte`).
    - Hotspot Voucher Studio & Print Engine (`Pages/hotspot/VouchersIndex.svelte`, `GenerateVoucherModal.svelte`, `HotspotProfileModal.svelte`, `VoucherPrintModal.svelte`, `AddHotspotUserModal.svelte`, `QrCode.svelte`).
    - Dedicated Subscribers & PPP Studio (`Pages/ppp/DedicatedUsers.svelte`, `AddDedicatedUserModal.svelte`, `PppProfileModal.svelte`).
    - Safe Mode Guardrail & Audit Trail (`Pages/safe-mode/AuditLogs.svelte`, `SafeModeUnlockModal.svelte`, `SafeModeBadge.svelte`).
  - Reusable generic `DataTable.svelte` supporting client-side filtering, sorting, pagination, and manual server-side pagination with custom snippet cells.
- **Clean Architecture Backend**:
  - Strict layered domain design (`Domain`, `Application`, `Infrastructure`, `Http/Presentation`).
  - Strict PHP 8.5 type enforcement with `declare(strict_types=1);` and comprehensive type hinting across all methods.
  - MikroTik socket and REST communications completely isolated into dedicated service layers.
- **Multi-Router Fleet Management**:
  - Dynamic per-request router context switching via the `X-Router-ID` HTTP header and `ResolveRouterContext` middleware.
  - Dual network protocol drivers supporting high-speed native MikroTik RouterOS API socket (Port 8728 / SSL 8729) and RouterOS v7 REST API (HTTPS Port 443).
  - Encrypted storage for all router credentials using AES-256 via Laravel Encrypter (`password_encrypted`).
- **Safe Mode Hardware Guardrails**:
  - Built-in hardware guardrail engine (`is_safe_mode_enabled` default `true`) preventing accidental reboots, config resets, package changes, and mass deletions.
  - Two-Step Unlock sequence requiring administrator password verification plus textual confirmation (`"UNLOCK"`).
  - Time-limited bypass token with 15-minute TTL passed via `X-SafeMode-Bypass` header and automated re-lock.
  - Full anti-tamper audit trail logging of all guarded operations into `safe_mode_audit_logs`.
- **Offline Circuit Breaker & Resilient Polling**:
  - Automated circuit breaker halting socket connections after **3 consecutive failures** to eliminate infinite timeout loops.
  - Auto-pausing telemetry and hotspot polling when routers are unreachable with fast-fail HTTP 503 responses.
  - Manual "Reconnect / Retry Now" action to reset circuit breaker trip counters.
- **Hotspot Voucher Studio & Print Engine**:
  - High-throughput batch voucher generator using collision-resistant, anti-tamper ULIDs/UUIDs.
  - Direct synchronization with MikroTik `/ip/hotspot/user`.
  - Production-ready print templates: **Thermal 58mm roll**, **Thermal 80mm roll**, and **Sheet A4 Grid** with automated QR Code login URLs.
  - Single-field (`Username = Password`) and dual-field (`Username ≠ Password`) voucher modes.
- **Dedicated Subscribers & PPP Studio**:
  - Full PPPoE, L2TP, and Static IP subscriber management (`/ppp/secret`).
  - Bandwidth profiles (`/ppp/profile`) with IP pool bindings, Tx/Rx rate limits, and custom DNS servers.
  - Bi-directional idempotent synchronization between MikroTik RouterOS and local PostgreSQL database.
  - Real-time active connection monitor (`/ppp/active`) with uptime, assigned IPs, and one-click user termination ("Kick").
  - Revenue analytics tracking monthly recurring subscriber revenue.
- **Real-Time Telemetry & Hardware-Accelerated Charts**:
  - Live CPU Load, free memory, system uptime, RouterOS version, and hardware model monitoring.
  - WAN throughput streaming visualizer powered by **Chart.js HTML5 Canvas** running zero-animation mode to eliminate CPU lag.
  - Configurable telemetry polling intervals (`Pause`, `5s`, `10s`, `15s Default`, `30s`, `60s`) with cross-session persistence.
- **Resource Efficiency & Zero-Waste Architecture**:
  - Server-side transient caching (2-3 seconds) on high-frequency polling routes to safeguard MikroTik hardware.
  - Throttled database writes committing router telemetry only on status changes or every 30 seconds.
  - HTML5 Page Visibility API integration automatically pausing polling cycles when browser tab is inactive.
  - Debounced search and table filtering to prevent DOM reflow thrashing.
- **Multilingual Support**:
  - Bilingual interface supporting English (`en`, default) and Bahasa Indonesia (`id`).
  - Automatic locale persistence across sessions via `users.preferred_locale` and `localStorage`.
- **Enterprise UI/UX Design System**:
  - Uniform page blueprint with studio category pill badges, primary gradient action CTAs (`from-sky-600 to-indigo-600`), and underline tabs with count badges.
  - Responsive `DataTable.svelte` with status badge styling, pagination, debounced filtering, and empty states.
  - Seamless dark and light mode themes powered by semantic Tailwind CSS tokens.
- **Database Architecture**:
  - Strict manual DDL policy preventing runtime DDL operations.
  - Idempotent PostgreSQL schema script (`database/sql/schema.sql`) and Laravel migrations.
  - Stateful session authentication via Laravel Sanctum with 30-day "Remember Me" persistence.
- **AI Developer Tooling & Context**:
  - Configured Laravel Boost guidelines, MCP server, and skills for IDE/AI pair programming.

### Changed
- Migrated Vite build configuration from `@vitejs/plugin-vue` to `@sveltejs/vite-plugin-svelte` with code-splitting.
- Completely removed legacy Vue 3 dependencies, Pinia, Vue Router, and obsolete `.vue` views and components.
- Maintained strict UI/UX uniformity with semantic Tailwind CSS tokens and bilingual English/Indonesian localization.

### Fixed
- Resolved Svelte 5 a11y label warnings (`a11y_label_has_associated_control`) across `AddDedicatedUserModal`, `PppProfileModal`, `HotspotProfileModal`, `AddHotspotUserModal`, and `GenerateVoucherModal` by establishing explicit `for` and `id` control associations.
- Moved `Column` interface export in `DataTable.svelte` into a `<script module>` block to conform to Svelte 5 module export requirements.
- Added `locale` getter to `LocaleStore` for type-safe template expressions.
- Added optional `title` prop to `AdminLayout.svelte`.
- Fixed local state reference warning in `DedicatedUsers.svelte`.

