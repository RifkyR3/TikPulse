# Changelog

All notable changes to **TikPulse** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added


### Changed



---

## [1.0.0] - 2026-10-17

### Added
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
  - Responsive TanStack DataTables with status badge styling, pagination, debounced filtering, and empty states.
  - Seamless dark and light mode themes powered by semantic Tailwind CSS tokens.
- **Database Architecture**:
  - Strict manual DDL policy preventing runtime DDL operations.
  - Idempotent PostgreSQL schema script (`database/sql/schema.sql`) and Laravel migrations.
  - Stateful session authentication via Laravel Sanctum with 30-day "Remember Me" persistence.
