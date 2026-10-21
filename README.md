# TikPulse ⚡

<p align="center">
  <strong>Enterprise-Grade Multi-Router MikroTik & Hotspot Voucher Management System</strong><br>
  <em>A modern, secure, and high-performance alternative to Mikhmon built on Clean Architecture, Strict Typing, and Safe Mode Hardware Guardrails.</em>
</p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-13.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 13"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP-8.5+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.5+"></a>
  <a href="https://svelte.dev"><img src="https://img.shields.io/badge/Svelte-5.x-FF3E00?style=for-the-badge&logo=svelte&logoColor=white" alt="Svelte 5"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia.js-v3-9553E9?style=for-the-badge&logo=inertia&logoColor=white" alt="Inertia.js v3"></a>
  <a href="https://www.typescriptlang.org"><img src="https://img.shields.io/badge/TypeScript-5.x-3178C6?style=for-the-badge&logo=typescript&logoColor=white" alt="TypeScript"></a>
  <a href="https://tailwindcss.com"><img src="https://img.shields.io/badge/Tailwind_CSS-4.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" alt="Tailwind CSS"></a>
  <a href="https://mikrotik.com"><img src="https://img.shields.io/badge/MikroTik-RouterOS_v6%20%7C%20v7-29323C?style=for-the-badge" alt="MikroTik RouterOS"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge" alt="License MIT"></a>
</p>

> [!WARNING]
> **Experimental & AI-Assisted Development Notice**:
> This project is **developed with the assistance of AI** and is **not yet fully field-tested in large-scale production network environments**. While automated test suites pass and Safe Mode hardware guardrails are active by default, you should thoroughly test on a non-production MikroTik device (or Cloud Hosted Router / CHR) before using it on critical networks. Always maintain offline configuration backups (`/export` / `/system backup`).

---

## 🌟 Highlights

- **Enterprise Role-Based Access Control (RBAC)**:
  - Granular module permissions (`read`, `write`, `update`, `delete`) scoped across all platform modules (`dashboard`, `routers`, `hotspot_vouchers`, `ppp_subscribers`, `safe_mode_logs`, `users`, and `groups`).
  - Backend `AuthorizeModuleAccess` middleware (`module.access`) enforcing permissions with immediate HTTP 403 fast-fail.
  - Client-side `$derived` permission helpers in `authStore.can()` dynamically filtering sidebar navigation and disabling protected actions.
- **MikroTik Router Scoping per Group**:
  - Assign groups unrestricted fleet access (`all_routers = true`) or limit operators strictly to authorized routers via `group_router` bindings.
  - Enforced at both database query level and `ResolveRouterContext` middleware.
- **Superadmin Role Switching & Simulation**:
  - Superadmin accounts can simulate any group context in real time to preview exact operator permissions.
  - Sticky simulation banner (`SimulationBanner.svelte`) with one-click return to Super Administrator.
- **Account Safety Guardrails**:
  - Built-in defenses preventing self-deletion, self-deactivation, and demotion/deletion of the last remaining Super Administrator.
  - Protection against deleting the Super Administrator group or groups with active members.
- **Personalized Appearance & Preferences**:
  - Per-user settings persistence in PostgreSQL JSONB (`users.settings`): Theme Mode (`light`, `dark`, `system`), 6 Theme Accent Colors (`sky`, `indigo`, `emerald`, `violet`, `amber`, `rose`), language (`en`, `id`), and configurable telemetry refresh rates.
  - Interactive modal dialog (`PreferencesModal.svelte`) accessible directly from the user avatar dropdown.
- **SvelteForge Admin UI/UX Architecture**:
  - Modern, responsive application layout powered by pure Svelte 5 UI primitives (`sidebar`, `card`, `button`, `badge`, `breadcrumb`, `separator`, `input`, `avatar`).
  - Collapsible sidebar drawer with keyboard shortcut (`Ctrl+B`), live breadcrumbs, and real-time status badges.
- **Multi-Router Fleet Management**: Centrally manage multiple MikroTik routers with seamless per-request context switching via `X-Router-ID`.
- **Dual Network Protocols**: High-speed native API socket (Port 8728 / SSL 8729) and RouterOS v7 REST API (HTTPS Port 443).
- **Safe Mode Hardware Guardrails (Default: Active)**:
  - Intercepts destructive actions (reboot, config reset, package update, mass deletions).
  - Two-Step Unlock sequence (admin password + textual confirmation) issuing a 15-minute temporary bypass token (`X-SafeMode-Bypass`).
  - Full anti-tamper audit logging (`safe_mode_audit_logs`).
- **Offline Circuit Breaker**:
  - Automatically trips after **3 consecutive connection failures**, preventing server timeout loops and socket exhaustion.
  - Pauses live polling on unreachable hardware with immediate fast-fail HTTP 503 responses.
- **Hotspot Voucher Studio & Print Engine**:
  - High-throughput batch voucher generation using collision-resistant, anti-tamper ULIDs/UUIDs.
  - Production-ready thermal ticket printing (**58mm**, **80mm**) and **A4 Sheet Grid** with automated QR Code login URLs.
- **Dedicated Subscribers & PPP Studio**:
  - Complete PPPoE, L2TP, and Static IP management (`/ppp/secret`).
  - Bandwidth profiles (`/ppp/profile`), static IP bindings, and MAC address locks.
  - Live active session monitoring with one-click user termination ("Kick").
  - Bi-directional idempotent synchronization with MikroTik RouterOS.
- **Hardware-Accelerated Real-Time Telemetry**:
  - Real-time WAN bandwidth visualizer powered by **Chart.js HTML5 Canvas** running in zero-animation mode.
  - Configurable polling rate (`Pause`, `5s`, `10s`, `15s`, `30s`, `60s`) with cross-session persistence.
- **Resource Zero-Waste Architecture**:
  - Short-lived transient caching (2-3s) on high-frequency routes.
  - Throttled database writes (30s) for telemetry data.
  - Client-side HTML5 Page Visibility API integration to pause polling automatically when browser tabs are hidden.
- **Multilingual Support**:
  - English (`en`, default) and Bahasa Indonesia (`id`) with user preference persistence.
- **Enterprise UI/UX Design System**:
  - Consistent page hierarchy, primary gradient CTAs, responsive data tables, and dark/light themes.

---

## 🛠️ Tech Stack

| Layer | Technologies |
|---|---|
| **Backend Framework** | Laravel 13 (Inertia.js v3 & Headless REST), PHP 8.5+, Strict Typing |
| **Frontend Architecture** | Svelte 5 (Runes: `$state`, `$derived`, `$props`, `$effect`), Inertia.js v3 (`@inertiajs/svelte`), Vite, TypeScript Strict Mode |
| **UI Components** | Pure Svelte 5 UI Primitives (SvelteForge Admin: sidebar drawer, card, button, badge, breadcrumb, avatar, modal dialogs) |
| **Styling & Icons** | Tailwind CSS v4 (Semantic Tokens & 6 Accent Colors), Lucide Svelte |
| **State Management** | Svelte 5 Reactive Stores (`.svelte.ts`) with `localStorage` persistence and token hydration |
| **Access Control (RBAC)**| Granular Module ACL (`read`, `write`, `update`, `delete`) & Router Scoping with `AuthorizeModuleAccess` middleware |
| **Data Tables** | Custom Svelte 5 DataTable with snippet cells, debounced filtering & pagination |
| **Charts & Visuals** | Chart.js (HTML5 Canvas hardware accelerated) |
| **Notifications** | Svelte Sonner (`svelte-sonner` reactive toast alerts) |
| **Database** | PostgreSQL 14+ (Idempotent schema & manual DDL policy) |
| **Authentication** | Laravel Sanctum (Stateful HTTP-Only Cookie & Bearer Token with 30-day Remember Me) |

---

## 🚀 Quick Start

### Option A: Running with Docker (Recommended)

The easiest way to run TikPulse—including PostgreSQL, Redis, Nginx, and an **auto-provisioned Dummy MikroTik RouterOS (CHR)** for safe testing without touching physical hardware.

#### 1. Development Mode (Live Code Reload + Auto Dummy MikroTik)

```bash
# Start all services including live source reload and dummy MikroTik
docker compose --profile dev up -d
```

* **Live Source Code Updates**: Edits to PHP files (`app/`, `routes/`, `config/`) and Svelte components (`resources/js/`) update **instantly without rebuilding**. Vite HMR runs on port `5173`.
* **Auto-Provisioned Dummy MikroTik**: RouterOS v7 CHR automatically initializes with:
  * Admin user: `admin` / Password: `admin123`
  * Hotspot User Profiles: `1Hour-Regular`, `1Day-Unlimited`, `1Month-VIP`
  * PPP Profiles: `10Mbps-Dedicated`, `20Mbps-Dedicated`
  * Automatically synced and connected inside TikPulse!
* **Access Points**:
  * **TikPulse Web App**: [http://localhost:8000](http://localhost:8000) (Login: `admin@tikpulse.test` / `secret123`)
  * **WinBox Desktop Access**: Connect to `127.0.0.1:8291` (`admin` / `admin123`)
  * **WebFig Browser GUI**: [http://localhost:8080](http://localhost:8080)
  * **MikroTik Socket API**: Port `8728`

To re-run the dummy MikroTik provisioner at any time:
```bash
docker compose exec app php artisan mikrotik:setup-dummy
```

To stop development containers:
```bash
docker compose --profile dev down
```

---

#### 2. Production Mode (Clean, Isolated & No Dummy Router)

In production, run standard Compose **without** the `dev` profile:

```bash
docker compose -f docker-compose.yml up -d --build
```

> [!NOTE]
> In production mode:
> - The `mikrotik-dummy` service is **never installed, downloaded, or started**.
> - Local host code folders are **not bind-mounted**; the application runs from an immutable, optimized production container image.
> - Connect your real production MikroTik routers directly through the TikPulse web dashboard.

---

### Option B: Manual Host Installation (Alternative without Docker)

#### Prerequisites
- PHP `^8.3` or `^8.5` with `pdo_pgsql`, `openssl`, `sockets`, `curl`, `mbstring`
- Composer 2.x
- Node.js 20+ & npm
- PostgreSQL 14+

#### Installation Steps

1. **Clone the repository**:
   ```bash
   git clone https://github.com/RifkyR3/TikPulse.git
   cd TikPulse
   ```

2. **Install backend dependencies**:
   ```bash
   composer install
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   *Edit `.env` to configure your PostgreSQL credentials.*

4. **Initialize Database**:
   ```powershell
   # Run migrations and seed default credentials
   php85 artisan migrate
   php85 artisan db:seed
   ```
   *(Alternatively, run `database/sql/schema.sql` directly in your PostgreSQL client. See [Database Documentation](docs/database.md).)*

5. **Install frontend dependencies & build**:
   ```bash
   npm install
   npm run build
   ```

6. **Start Development Server**:
   ```powershell
   npm run dev
   ```
   *Starts both Laravel backend (`http://localhost:8000`) and Vite dev server (`http://localhost:5173`) concurrently.*

### Default Credentials
- **URL**: `http://localhost:8000`
- **Email**: `admin@tikpulse.test`
- **Password**: `secret123`
- **Role**: `superadmin`

### Primary Application Routes
- 📊 `/dashboard` — Real-time telemetry, live WAN throughput, and active hotspot overview.
- 🌐 `/routers` — Multi-router fleet management, protocol drivers, and connection testing.
- 🎫 `/vouchers` — Hotspot voucher studio, batch generation, profile sync, and thermal print engine.
- 🔌 `/dedicated-users` — Dedicated subscriber secrets (PPPoE/L2TP), bandwidth profiles, and active session kicker.
- 👥 `/users` — Enterprise RBAC, user account management, custom access groups, and module ACL.
- 🛡️ `/audit-logs` — Tamper-resistant Safe Mode guardrail audit trail and security logs.

---

## 📚 Documentation

Detailed documentation is available in the [`docs/`](docs/) directory:

- 📡 **[API Reference](docs/api-reference.md)**: Complete v1 REST endpoints, request/response payloads, and error codes.
- 🏛️ **[Architecture & Principles](docs/architecture.md)**: Clean Architecture layers, service boundaries, circuit breaker, and zero-waste optimizations.
- 🗄️ **[Database & Schema](docs/database.md)**: Schema design, manual DDL execution policy, and PostgreSQL setup.
- 🛡️ **[Safe Mode Guardrails](docs/safe-mode.md)**: Hardware protection rules, two-step unlock sequence, and audit logs.
- 📜 **[Changelog](CHANGELOG.md)**: Chronological record of features, improvements, and fixes.
- 🤝 **[Contributing Guidelines](CONTRIBUTING.md)**: Contribution workflow, branch conventions, and testing standards.
- 🔒 **[Security Policy](SECURITY.md)**: Vulnerability disclosure procedure and architectural security guarantees.

---

## 🧪 Testing & Quality Assurance

```powershell
# Run backend test suite (PHPUnit)
php85 artisan test

# Format PHP code with Laravel Pint
php85 vendor/bin/pint --format agent

# Validate TypeScript types
npm run types:check

# Compile production frontend assets
npm run build
```

---

## ⚠️ Disclaimer

- **AI-Assisted Development**: This software was architected, written, and maintained with the assistance of artificial intelligence (AI coding models).
- **Testing Status**: Core application logic and API endpoints have passing automated PHPUnit and TypeScript test suites. However, the system has **not yet been comprehensively field-tested in live, large-scale production environments** across every MikroTik RouterOS hardware architecture.
- **Liability**: Use this software at your own risk. The authors and maintainers accept no liability for network downtime, loss of data, configuration corruption, or hardware issues. Always stage and verify on test devices or virtual CHR instances prior to production deployment.

---

## 📄 License

This project is open-source software licensed under the **[MIT License](LICENSE)**.

Copyright &copy; 2026 [Rifky Ramadhan](https://github.com/RifkyR3). All rights reserved.
