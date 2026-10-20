# Contributing to TikPulse ⚡

Thank you for your interest in contributing to **TikPulse**! We are committed to building a clean, secure, and enterprise-grade MikroTik and Hotspot management system.

Please read this document to understand our contribution workflow, development standards, and code guidelines.

---

## 📜 Code of Conduct

All contributors and maintainers are expected to abide by our [Code of Conduct](file:///CODE_OF_CONDUCT.md). Please report any unacceptable behavior to `rifky.r3@gmail.com`.

---

## 🛠️ Development Setup & Prerequisites

Ensure your development environment meets the following requirements:

- **PHP**: `^8.3` (Project runs on PHP 8.5) with extensions: `pdo_pgsql`, `openssl`, `mbstring`, `sockets`, `curl`, `json`.
- **Node.js**: `^20.0` or later & `npm` / `pnpm`.
- **Database**: PostgreSQL 14+ with `uuid-ossp` and `pgcrypto` extensions.
- **MikroTik RouterOS**: v6.48+ or v7.x (Physical device or CHR instance for hardware testing).

### Setup Instructions

1. Clone your fork of the repository:
   ```bash
   git clone https://github.com/<your-username>/TikPulse.git
   cd TikPulse
   ```

2. Install backend dependencies:
   ```bash
   composer install
   ```

3. Setup environment configuration:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure database in `.env` and run migrations:
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

5. Install frontend dependencies and build:
   ```bash
   npm install
   npm run build
   ```

6. Start local development server:
   ```bash
   npm run dev
   ```

---

## 🌿 Git & Branching Strategy

- `main`: Production-ready, stable releases only.
- Feature branches: `feat/<feature-name>` (e.g., `feat/snmp-traffic-monitor`).
- Bugfix branches: `fix/<bug-name>` (e.g., `fix/circuit-breaker-retry-overflow`).
- Refactoring branches: `refactor/<target>` (e.g., `refactor/ppp-profile-service`).

### Commit Message Guidelines

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

- `feat: add bandwidth graph export in svg format`
- `fix: resolve socket timeout when router is unreachable`
- `refactor: extract safe mode check into policy service`
- `docs: update api reference for ppp endpoints`
- `test: add unit test for circuit breaker trip state`

---

## 📐 Coding Conventions & Quality Standards

To maintain clean architecture and enterprise stability, please adhere to these rules:

### 1. Strict Typing & Layered Architecture
- Every PHP file **MUST** declare strict typing: `declare(strict_types=1);`.
- Use explicit return types and parameter type hints on all methods.
- Maintain layered isolation: `Domain` -> `Application` -> `Infrastructure` -> `Http/Presentation`.
- **NEVER** instantiate or invoke MikroTik socket connections directly inside Controllers. All communication must pass through service contracts in `Application` or `Infrastructure`.

### 2. Safe Mode Guardrails
- Any action that performs a potentially destructive operation (hardware reboot, configuration reset, package update, or bulk deletion) **MUST** be protected by `MikrotikSafeModeGuard` and verify the `X-SafeMode-Bypass` header.
- Every guarded attempt (both authorized and blocked) must be logged to `safe_mode_audit_logs`.

### 3. Frontend Standards (Inertia.js + Svelte 5)
- Use Svelte 5 with runes (`$state`, `$derived`, `$props`, `$effect`, snippets).
- Client-side navigation powered by Inertia.js v3 (`@inertiajs/svelte`).
- Strict TypeScript: define interfaces for all DTOs and API responses in `resources/js/types/`.
- Svelte 5 reactive stores (`.svelte.ts`) with `localStorage` persistence and custom `DataTable.svelte` for tabular displays.
- Real-time charts must use **Chart.js** with `animation: false` for hardware-accelerated canvas rendering without lag.
- Follow UI Uniformity Standards: studio pill badge, `h2 font-black` title, primary gradient CTAs (`from-sky-600 to-indigo-600`), and dark/light semantic design tokens.

### 4. Code Formatting & Static Analysis
Run these checks before submitting any pull request:

```powershell
# Format PHP code with Pint
php85 vendor/bin/pint --format agent

# Check TypeScript types
npm run types:check

# Run test suite
php85 artisan test
```

---

## 📝 Mandatory Changelog Updates

Any pull request that adds a feature, fixes a bug, or refactors existing behavior **MUST** update [CHANGELOG.md](file:///CHANGELOG.md) under the `## [Unreleased]` section.

Categorize your changes using:
- `Added` for new features.
- `Changed` for modifications of existing functionality.
- `Deprecated` for soon-to-be removed features.
- `Removed` for removed features.
- `Fixed` for bug fixes.
- `Security` in case of vulnerabilities.

---

## 🚀 Pull Request Checklist

Before opening a pull request, verify that:
- [ ] Your code passes all tests (`php85 artisan test`).
- [ ] TypeScript compiles cleanly (`npm run types:check`).
- [ ] Code has been formatted using Laravel Pint (`php85 vendor/bin/pint --format agent`).
- [ ] `CHANGELOG.md` has been updated with a clear summary of your changes.
- [ ] Safe Mode compliance is preserved for destructive endpoints.
- [ ] New UI features adhere to the UI/UX Uniformity Standards.
