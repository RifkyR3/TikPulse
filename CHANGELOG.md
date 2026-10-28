# Changelog

All notable changes to **TikPulse** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - Unreleased

### Added
- **Enterprise User Management**:
  - Full user account management with search, group filter, status filter, and pagination (`Pages/user/UsersIndex.svelte` and `components/user/UserModal.svelte`).
  - Account guardrails preventing self-deletion, self-deactivation, and demotion/deletion of the last remaining Super Administrator.
- **Access Group & Role Management**:
  - Custom access groups with member count, description, and permission assignment (`components/user/GroupModal.svelte`).
  - Safeguards preventing deletion of the Super Administrator group or any group with active assigned users.
- **Granular Module Permissions (ACL)**:
  - Granular module capabilities (`read`, `write`, `update`, `delete`) across all core modules: `dashboard`, `routers`, `hotspot_vouchers`, `ppp_subscribers`, `safe_mode_logs`, `users`, and `groups`.
  - Backend `AuthorizeModuleAccess` middleware (`module.access`) enforcing permissions on all API endpoints with 403 Forbidden fast-fail.
  - Client-side `$derived` permission helpers in `authStore.can()` dynamically filtering navigation menu items and disabling protected actions.
- **MikroTik Router Scoping per Group**:
  - Groups can be configured with either unrestricted access (`all_routers = true`) or scoped to specific MikroTik router IDs via the `group_router` pivot table.
  - `RouterController` and `ResolveRouterContext` middleware automatically restrict router queries and block unauthorized router access with 403 Forbidden.
- **Superadmin Role Switching & Simulation**:
  - Superadmin users can switch their active group context to test and preview permissions as any operator role via `POST /api/v1/auth/switch-role` and `SwitchRoleModal.svelte`.
  - Top alert `SimulationBanner.svelte` with live role badge and one-click "Exit Simulation" reversion back to Super Administrator.
- **User Settings & Appearance Preferences**:
  - Cross-session persistence for language (`en`, `id`), theme mode (`light`, `dark`, `system`), 6 theme accent colors (`sky`, `indigo`, `emerald`, `violet`, `amber`, `rose`), auto-refresh interval, and table display options via `users.settings` JSON column.
  - Interactive `PreferencesModal.svelte` accessible from user avatar dropdown.
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
- **Package Manager & Node.js Runtime Upgrade**:
  - Migrated frontend package management from `npm` to `pnpm` (`^10.12`) with `pnpm-lock.yaml` and `pnpm-workspace.yaml`.
  - Standardized on Node.js 24 LTS (`^24.22`) across all environments, avoiding static/hardcoded patch version locking.
  - Added `.nvmrc` (`24`) and `.node-version` (`24`) for automatic version manager resolution.
  - Configured `packageManager` and `engines` (`node: "^24.22"`, `pnpm: "^10.12"`) in `package.json`.
  - Upgraded `@types/node` to `^24.19.2`.
- **Container Runtime, PHP 8.5 & Extension Installer Upgrade**:
  - Upgraded production and development container runtime in [`Dockerfile`](file:///d:/Program/web/www/PHP/TikPulse/Dockerfile) to `php:8.5-fpm-alpine`.
  - Replaced manual `apk add`, `pecl install`, and `docker-php-ext-*` scripts with [`mlocati/docker-php-extension-installer`](https://github.com/mlocati/docker-php-extension-installer) for streamlined, reliable PHP extension installation (`pdo_pgsql`, `pgsql`, `bcmath`, `sockets`, `pcntl`, `zip`, `intl`, `redis`).
  - Automatically manages runtime shared libraries and purges temporary compiler artifacts, reducing container image attack surface and size.
  - Updated [`docker-compose.yml`](file:///d:/Program/web/www/PHP/TikPulse/docker-compose.yml) service descriptions to reflect PHP 8.5-FPM.
- Converted live traffic and WAN throughput visualizer from Chart.js to **ApexCharts**:
  - Replaced `chart.js` with `apexcharts` in `package.json` and refactored `InterfaceTrafficChart.svelte`.
  - Implemented zero-animation real-time SVG streaming (`animations.enabled: false`) to ensure high efficiency and zero CPU stutter.
  - Added dynamic theme synchronization with TikPulse `themeStore` supporting instant dark/light mode palette transitions.
  - Styled custom `.apexcharts-tooltip` and crosshairs matching TikPulse semantic OKLCH tokens and monospace metrics typography.
- Migrated Vite build configuration from `@vitejs/plugin-vue` to `@sveltejs/vite-plugin-svelte` with code-splitting.
- Completely removed legacy Vue 3 dependencies, Pinia, Vue Router, and obsolete `.vue` views and components.
- Maintained strict UI/UX uniformity with semantic Tailwind CSS tokens and bilingual English/Indonesian localization.

### Fixed
- Fixed SQLSTATE[42703] PostgreSQL undefined column errors (`ip_address`, `is_active`, `board_name`) in `HandleInertiaRequests` middleware by resolving `Router` entities via `RouterResource::collection()->resolve()` and removing non-existent column projections and active status filters.
- Added virtual `board_name` accessor to `Router` Eloquent model to mirror `RouterResource` behavior.
- Fixed state persistence and disappearing actions on browser hard reload (`Ctrl + F5`):
  - Enriched `HandleInertiaRequests` middleware with `UserResource` to pass complete user context (including `is_superadmin`, `can_switch_role`, `group`, `effective_permissions`, `allowed_router_ids`, and `settings`) and added token cookie authentication fallback.
  - Enabled stateful API sessions via `$middleware->statefulApi()` in `bootstrap/app.php`.
  - Added synchronous `localStorage` persistence and hydration for `authStore.user` (`tikpulse_user`) in `auth.svelte.ts`.
  - Implemented `initFromPageProps` in `authStore` and wired real-time `$effect` and setup hydration in `app.ts` and `AdminLayout.svelte`.
  - Added automatic background `fetchMe()` execution on client boot whenever an auth token exists.
- Resolved Svelte 5 a11y label warnings (`a11y_label_has_associated_control`) across `AddDedicatedUserModal`, `PppProfileModal`, `HotspotProfileModal`, `AddHotspotUserModal`, and `GenerateVoucherModal` by establishing explicit `for` and `id` control associations.
- Moved `Column` interface export in `DataTable.svelte` into a `<script module>` block to conform to Svelte 5 module export requirements.
- Added `locale` getter to `LocaleStore` for type-safe template expressions.
- Added optional `title` prop to `AdminLayout.svelte`.
- Fixed local state reference warning in `DedicatedUsers.svelte`.
- Converted application shell and design layout to [SvelteForge Admin](https://github.com/ColorlibHQ/svelteforge-admin) template architecture:
  - Built pure Svelte 5 UI primitives in `resources/js/components/ui/` (`sidebar`, `card`, `button`, `badge`, `breadcrumb`, `separator`, `input`, `label`, `avatar`).
  - Implemented SvelteForge `Sidebar.Provider`, `Sidebar.Root`, `Sidebar.Inset`, and responsive drawer with keyboard shortcuts.
  - Modernized `AppHeader.svelte` with dynamic breadcrumb trail, sidebar trigger, router telemetry pill, and header action controls.
  - Overhauled `AppSidebar.svelte` with SvelteForge brand logo badge, categorized group navigation, live router status monitor, and user profile footer.
  - Redesigned `Pages/auth/login.svelte` to match SvelteForge Admin Card form design with demo access callout while preserving the signature TikPulse gradient squircle logo.
  - Refactored `Pages/dashboard/Dashboard.svelte` telemetry grid to use SvelteForge Card and Badge design system.
  - Preserved the original TikPulse brand logo (`Activity` icon in gradient squircle `from-sky-500 via-indigo-500 to-primary`) across both `AppSidebar.svelte` and `login.svelte`.

