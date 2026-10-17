<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel 13 application running on PHP 8.5 (installed: PHP 8.5.7, Laravel Framework 13.36.0). Always use the APIs that match the installed major version of each package — do not assume an older version.

> [!IMPORTANT]
> **Host Environment PHP Binary Enforcement**:
> **You MUST ALWAYS use `php85` for all CLI, Artisan, Pint, PHPUnit, and Composer commands** (e.g. `php85 artisan ...`, `php85 vendor/bin/pint --format agent`, `php85 artisan test`).
> Invoking `php artisan` directly will immediately throw a fatal Composer runtime incompatibility error.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `.agents/skills/` and `.claude/skills/`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck:
- `laravel-best-practices`: Laravel backend PHP code patterns, controllers, models, queries, and service architecture.
- `tailwindcss-development`: Tailwind CSS utility classes, responsive layouts, dark mode styling, and Tailwind v4 CSS-first theming.
- `testing-best-practices`: Design, structure, and review of PHPUnit / feature tests.
- `fortify-development`: Authentication flows, session handling, and user security.
- `deploying-to-cloud`: Laravel Cloud deployments, CLI infrastructure, and provisioning.
- `infer-conventions`: Inferring project-specific patterns and conventions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.
- Dev scripts in `package.json` are pre-configured to run `php85 artisan serve` alongside `vite`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line using `php85` (e.g., `php85 artisan route:list`). Use `php85 artisan list` to discover available commands and `php85 artisan [command] --help` to check parameters.
- Inspect routes with `php85 artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php85 artisan config:show app.name`, `php85 artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion in PowerShell: `php85 artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php85 artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass using `php85 artisan test`.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== vue-spa/core rules ===

# Vue 3 Single Page Application (SPA)

- This application is a standalone Vue 3 SPA powered by Vite 8, TypeScript 5 Strict Mode, and Pinia. It communicates with a Headless Laravel 13 REST API.
- Do NOT use React, Blade views for UI rendering, or Inertia.js. All client logic and pages reside in `resources/js/views` and `resources/js/components`. `resources/views/app.blade.php` is strictly the SPA mounting shell.
- Always use Vue 3 Composition API with `<script setup lang="ts">`.
- Use TypeScript interfaces and strict type annotations for all props, emits, API responses, and store states in `resources/js/types/`.
- Client-side routing is handled by Vue Router with `createMemoryHistory()` (`resources/js/router/index.ts`). Active route is persisted across page refreshes via `sessionStorage` (`tikpulse_memory_route`), and browser tab titles are updated dynamically (`meta.title | TikPulse`). Protect auth routes using route meta (`requiresAuth`, `guestOnly`).
- HTTP requests must go through the centralized Axios client (`resources/js/services/api.ts`) with Sanctum CSRF and session headers.
- State management must use Pinia stores (`resources/js/stores/`). Persist user preferences (locale, theme, router context) via Pinia with `localStorage`.
- Styling uses **Tailwind CSS v4** configured with `@tailwindcss/vite` and CSS-first design tokens in `resources/css/app.css` (`@theme`, OKLCH palette, `@custom-variant dark`).
- Real-time charts must use **Chart.js** via HTML5 `<canvas>` with `animation: false` for hardware-accelerated, zero-lag streaming.
- Toast notifications must use **vue-sonner** (`toast.success()`, `toast.error()`).

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php85 artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php85 artisan list` and check their parameters with `php85 artisan [command] --help`.
- If you're creating a generic PHP class, use `php85 artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php85 artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning (`/api/v1/*`) unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php85 artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== api/client rules ===

# Headless REST API Integration

- Frontend connects to the backend through Laravel 13 Headless REST API (`/api/v1/*`).
- Always define TypeScript interfaces for API payloads and responses in `resources/js/types/`.
- Manage router context by passing `X-Router-ID` and Safe Mode bypass by passing `X-SafeMode-Bypass` headers.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `php85 vendor/bin/pint --dirty --format agent` (or `php85 vendor/bin/pint --format agent`) before finalizing changes to ensure your code matches the project's expected style.
- Do not run `php85 vendor/bin/pint --test --format agent` as a replacement for formatting, simply run `php85 vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php85 artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php85 artisan test --compact`.
- Rerun a test after each change to it.
- Run `php85 vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

# TikPulse Architecture & Enterprise Rules

## 1. Clean Architecture & Code Isolation
- Maintain strict layered isolation: `Domain` -> `Application` -> `Infrastructure` -> `Http/Presentation`.
- STRICTLY FORBIDDEN to instantiate or invoke MikroTik socket connections directly inside Controllers. All network communication must pass through:
  - `App\Infrastructure\Network\Mikrotik\Services\MikrotikConnectionService`: Socket & REST driver connection lifecycle and circuit breaker.
  - `App\Infrastructure\Network\Mikrotik\Services\HotspotVoucherService`: MikroTik Hotspot users, active sessions, and profile synchronization.
  - `App\Infrastructure\Network\Mikrotik\Services\PppService`: MikroTik PPP secrets, profiles, active sessions, and termination ("Kick").
  - `App\Application\Hotspot\Services\VoucherGenerationService`: Batch voucher generator and ULID/UUID cryptographic tokens.
  - `App\Application\SafeMode\Services\MikrotikSafeModeGuard`: Destructive action interception and bypass token validation.
- All PHP files MUST declare `declare(strict_types=1);` and enforce full type hinting on all method parameters and return types.

## 2. Safe Mode & Hardware Guardrail Rules
- Every router profile contains an `is_safe_mode_enabled` flag (default: `true`).
- Destructive commands MUST pass through `EnsureSafeModeCompliance` middleware (`app/Http/Middleware/EnsureSafeModeCompliance.php`) and `MikrotikSafeModeGuard`.
- Guarded destructive actions (defined in `App\Domain\SafeMode\Enums\DestructiveAction`):
  - `system_reboot`: Router reboot (`/system/reboot`).
  - `system_shutdown`: Router shutdown (`/system/shutdown`).
  - `reset_configuration`: Router reset (`/system/reset-configuration`).
  - `bulk_delete_vouchers`: Mass hotspot voucher or user deletion (`/vouchers/bulk-delete`, `/hotspot/users/{username}`).
  - `bulk_delete_profiles`: Deleting hotspot bandwidth profiles (`DELETE /profiles/{profile}`).
  - `bulk_delete_ppp_secrets`: Deleting PPP subscriber secrets (`DELETE /ppp/secrets/{dedicatedUser}`).
  - `bulk_delete_ppp_profiles`: Deleting PPP bandwidth profiles (`DELETE /ppp/profiles/{profile}`).
- Destructive operations attempted without the `X-SafeMode-Bypass` header or an active bypass token MUST return `403 Forbidden` (`SafeModeActiveException`).
- Two-Step Unlock Sequence:
  - User submits administrator password + textual confirmation string `"UNLOCK"` to `POST /api/v1/routers/{router}/safe-mode/unlock`.
  - Backend returns a time-limited bypass token with 15-minute TTL.
  - Frontend passes the token in the `X-SafeMode-Bypass` HTTP header.
- All execution attempts—both permitted and blocked—MUST be recorded into the `safe_mode_audit_logs` table.

## 3. Database & DDL Policy (Manual Run)
- DDL operations are STRICTLY PROHIBITED from executing automatically during application boot or runtime.
- Primary database is PostgreSQL 14+ (`DB_CONNECTION=pgsql`).
- All schema modifications MUST be provided in idempotent standard Laravel migrations (`database/migrations/`) and raw SQL scripts in `database/sql/schema.sql`.
- Voucher transaction records MUST use collision-resistant, anti-tamper ULID / UUID strings as primary keys.

## 4. Multi-Router Context
- Any API request requiring router context MUST resolve the Router ID from the `X-Router-ID` header or route parameter via the `ResolveRouterContext` middleware (`app/Http/Middleware/ResolveRouterContext.php`).
- RouterOS password credentials MUST be stored in encrypted form (`password_encrypted`) using Laravel Encrypter (`Crypt::encryptString`).
- Router drivers support dual protocols: native MikroTik API socket (Port 8728 / SSL 8729) and RouterOS v7 REST API (HTTPS Port 443).

## 5. Offline Circuit Breaker, Auto-Refresh & Remember Me
- Unreachable router connections are protected by an automated Circuit Breaker with a maximum threshold of **3 consecutive failures** to prevent socket connection timeout loops.
- When in offline status, live telemetry, bandwidth traffic, and active hotspot polling are automatically paused, and the backend fast-fails with HTTP 503 (`CircuitBreakerTrippedException`).
- A manual "Reconnect / Retry Now" action resets the circuit counter and tests connectivity immediately.
- Stateful session authentication via Laravel Sanctum supports 30-day "Remember Me" persistence.
- All UI text, components, and documentation default to standard English (EN), with full bilingual support.

## 6. Multilingual Architecture & User Locale Persistence
- The application supports two languages: English (`en`, default) and Bahasa Indonesia (`id`).
- User language preferences are persisted in the database (`users.preferred_locale`) and `localStorage.getItem('tikpulse_locale')`.
- Upon user login or application load, Pinia `useLocaleStore()` automatically initializes the user's preferred language (`initFromUser()`) from the user profile or fallback `localStorage`.
- Changing the language via `LanguageSwitcher.vue` instantly switches the SPA display language and dispatches `POST /api/v1/auth/locale` for database persistence when authenticated.

## 7. Resource Efficiency & Popular Libraries Policy
- **Low Server Overhead**:
  - Use short-lived transient caching (2-3 seconds) on high-frequency telemetry polling, active hotspot sessions, and bandwidth traffic routes to protect MikroTik hardware from socket exhaustion and avoid server CPU spikes.
  - Apply throttled database writes on router status (only commit database updates if the router connection status changes or once every 30 seconds).
- **Low Client Overhead**:
  - Integrate the HTML5 Page Visibility API (`usePageVisibility`) to automatically halt polling cycles when the browser tab is hidden or minimized.
  - Apply `debounce` on search and table filter inputs to eliminate typing lag and redundant DOM recalculations.
- **Popular, Battle-Tested Libraries**:
  - `chart.js`: Hardware-accelerated HTML5 `<canvas>` rendering (`animation: false` mode for zero CPU lag).
  - `vue-sonner`: Lightweight, reactive toast notification engine.
  - `lucide-vue-next` / `@lucide/vue`: Tree-shaken SVG icon package.
  - `@tanstack/vue-table`: Headless, responsive data tables with server-side and client-side pagination.
  - `tailwindcss` (v4) with `@tailwindcss/vite`: Lightning-fast, modern CSS engine with zero legacy runtime overhead.

## 8. UI/UX Consistency & Feature Uniformity Standards
- **Core Principle (Design Uniformity Principle)**:
  - Every new menu, view, or feature **MUST STRICTLY FOLLOW THE VISUAL HIERARCHY & LAYOUT BLUEPRINT** established in core modules (`VouchersIndexView.vue`, `DedicatedUsersView.vue`, `RouterListView.vue`, `DashboardView.vue`, `AuditLogsView.vue`).
  - STRICTLY FORBIDDEN to create divergent layouts, button styles, badges, or modal designs without prior review.
- **Standard Page Anatomy Blueprint**:
  1. **Page Header**:
     - **Category/Studio Pill Badge**: Positioned above the title (`inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20`), alongside the active router name indicator (`text-xs text-muted-foreground font-mono`).
     - **Title**: Heading `h2` (`text-2xl font-black tracking-tight text-foreground mt-1`).
     - **Subtitle/Description**: Paragraph `p` (`text-xs text-muted-foreground mt-0.5`) with bilingual support (`localeStore.locale === 'id' ? ... : ...`).
     - **Global Action Bar (Top Right)**:
       - Sync/Pull Button (when connected to MikroTik): Bordered button (`px-3 py-2 rounded-xl border border-border hover:bg-muted text-xs font-semibold text-foreground flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50`) with spinning `RefreshCw` icon (`animate-spin`) during loading.
       - Primary Action Button (CTA): Always use the uniform primary gradient (`px-4 py-2 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center space-x-1.5 transition-colors cursor-pointer disabled:opacity-50`) with Lucide icons (e.g. `Plus`, `Sparkles`, `UserPlus`).
  2. **Tab Navigation (For Multi-Entity / Sub-Module Views)**:
     - Underline horizontal container: `border-b border-border flex items-center space-x-1`.
     - Active Tab: `border-b-2 border-sky-500 text-sky-600 dark:text-sky-400 font-bold`.
     - Inactive Tab: `border-transparent text-muted-foreground hover:text-foreground font-bold`.
     - Includes Lucide icon, tab name, and count pill badge (`px-1.5 py-0.5 rounded-full text-[10px] bg-muted text-muted-foreground font-mono`).
  3. **Metric / Stat Cards Grid**:
     - Display 3 to 4 summary metric cards at the top of content (`grid grid-cols-2 md:grid-cols-4 gap-3.5`).
     - Card container: `rounded-2xl border border-border bg-card p-4 flex items-center justify-between shadow-xs`.
     - Metric value: `text-xl font-black text-foreground`, label: `text-[11px] text-muted-foreground font-medium`.
     - Icon container: `w-10 h-10 rounded-xl flex items-center justify-center shrink-0` with soft tint (`bg-sky-500/10 text-sky-500`, `bg-emerald-500/10 text-emerald-500`, `bg-amber-500/10 text-amber-500`, `bg-indigo-500/10 text-indigo-500`).
  4. **Search, Filter & Control Toolbar**:
     - Container: `bg-card border border-border rounded-2xl p-4 space-y-3 shadow-xs`.
     - Search input with left-aligned `Search` icon, clear button, and MANDATORY `debounce(...)` to eliminate typing lag.
     - Dropdown filter: `rounded-xl border border-border bg-background px-3 py-2 text-xs font-medium text-foreground focus:ring-2 focus:ring-sky-500`.
  5. **Data Table, Badges & Empty States**:
     - Use TanStack `DataTable` (`components/common/DataTable.vue`) for paginated tabular data.
     - **Standard Status Badge Color Palette**:
       - Status Active/Online: Green (`bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20`).
       - Status Disabled/Offline: Rose/Zinc (`bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20` or `bg-muted text-muted-foreground border border-border`).
       - Package Profile: Sky blue (`bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20`).
       - Protocol / Service Type: Indigo (`bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20`).
     - Row action buttons: `p-1.5 rounded-lg border border-border hover:bg-muted text-foreground transition-colors cursor-pointer`.
     - Empty State: Centered layout with faint circular icon background, title "No [items] found", description, and primary add action button.
  6. **Modal Form Dialog**:
     - Backdrop: `fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4`.
     - Modal Card: `bg-card border border-border rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150`.
     - Modal Header: Small gradient icon badge + `h3 font-bold text-foreground` title + close button `X`.
     - Form Input: Label `text-xs font-semibold text-foreground`, helper text `text-[11px] text-muted-foreground`, input `rounded-xl border border-border bg-background px-3 py-2 text-xs focus:ring-2 focus:ring-sky-500`.
     - Modal Footer: Cancel button (`border border-border hover:bg-muted text-xs font-semibold px-4 py-2 rounded-xl`) + Save button with primary gradient (`bg-gradient-to-r from-sky-600 to-indigo-600 text-white font-bold text-xs px-4 py-2 rounded-xl shadow-md`).

## 9. Dedicated Subscriber & PPP Management Architecture
- **Layered Isolation & Service Boundary**:
  - STRICTLY FORBIDDEN to invoke MikroTik socket connections directly inside Controllers.
  - All `/ppp/secret`, `/ppp/profile`, and `/ppp/active` operations MUST pass through `App\Infrastructure\Network\Mikrotik\Services\PppService` in coordination with `MikrotikSafeModeGuard`.
- **Safe Mode Actions**:
  - PPP Profile deletion is protected under Safe Mode guard action `bulk_delete_ppp_profiles`.
  - PPP Secret deletion is protected under Safe Mode guard action `bulk_delete_ppp_secrets`.
  - The frontend MUST integrate `SafeModeUnlockModal`, intercept 403 responses or inspect `routerStore.activeRouter?.is_safe_mode_enabled && !safeModeStore.isUnlocked`, and transmit the `X-SafeMode-Bypass` header when an unlock token is available.
- **Bi-directional Synchronization**:
  - Creating or updating secrets and profiles in TikPulse automatically pushes changes to RouterOS (`synced_to_router = true`).
  - "Sync from Router" pulls all existing MikroTik secrets and profiles into the local database idempotently without duplication.
- **Live Active Monitoring & Zero Client Waste**:
  - "Active Sessions" tab polling MUST utilize the Circuit Breaker (maximum 3 consecutive failures, fast-fail 503) and `usePageVisibility` (auto-pauses polling when the tab is hidden).
  - "Kick Session" terminates active client connections immediately on the router via `/ppp/active/remove`.

## 10. Standard Pre-Finalization Checklist
- [ ] UI Uniformity verified (Header badge, `h2 font-black` title, primary gradient CTA, underline tabs, metric cards, TanStack DataTable, empty states).
- [ ] Dark & Light mode compatibility verified using Tailwind semantic tokens (`bg-card`, `border-border`, `text-foreground`, `text-muted-foreground`, `bg-muted`).
- [ ] Bilingual localization verified (English default + Bahasa Indonesia) via `useLocaleStore`.
- [ ] Safe Mode Guardrail protection enforced on all destructive commands (Reboot, Shutdown, Reset, Delete).
- [ ] Idempotent database schema maintained (`database/sql/schema.sql` and migrations strictly synchronized).
- [ ] Resource efficiency verified (Page Visibility API, search/filter debouncing, transient caching).
- [ ] PHP code formatted using Pint with `php85` (`php85 vendor/bin/pint --format agent`).
- [ ] Unit & Feature tests executed and passing with `php85` (`php85 artisan test`).
- [ ] TypeScript types validated without errors (`npm run types:check`).
- [ ] Update `CHANGELOG.md` under `[Unreleased]` (categories: `Added`, `Changed`, `Fixed`, `Security`) per Keep a Changelog standards.

## 11. Changelog & Versioning Maintenance (Mandatory on Every Change)
- Every bug fix (`fix`), new feature (`feat`), refactoring, or security update MUST be documented by developers and AI agents in `CHANGELOG.md`.
- Recording format MUST adhere to **[Keep a Changelog](https://keepachangelog.com/en/1.1.0/)** and **[Semantic Versioning](https://semver.org/spec/v2.0.0.html)**:
  - Record all latest updates under the `## [Unreleased]` heading.
  - Group change entries into standard sub-categories:
    - `### Added`: For new features, endpoints, or components.
    - `### Changed`: For modifications of existing business logic, UX flows, or architectures.
    - `### Deprecated`: For soon-to-be removed features.
    - `### Removed`: For removed features or deprecated endpoints.
    - `### Fixed`: For bug fixes, runtime errors, unhandled exceptions, or styling corrections.
    - `### Security`: For security patches, authorization improvements, or Safe Mode guardrail hardening.
  - Write concise, informative summaries referencing the affected modules, files, or endpoints.
